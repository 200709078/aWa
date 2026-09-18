<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesStudents;

class StudentPhotoTest extends TestCase
{
    use CreatesStudents;
    use RefreshDatabase;

    private function makeJpg(string $filename, int $w = 1200, int $h = 900): string
    {
        $path = sys_get_temp_dir().'/'.$filename;
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 100, 150, 200));
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }

    private function setupData(): AcademicYear
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branch = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $this->makeStudent($year, $branch, '145', 'Ali Veli');
        $this->makeStudent($year, $branch, '146', 'Ayşe Yılmaz');

        return $year;
    }

    private function studentByNumber(string $number): Student
    {
        return StudentEnrollment::where('school_number', $number)->firstOrFail()->student;
    }

    /**
     * @return array{token: string, files: array}
     */
    private function match(AcademicYear $year, array $photos): array
    {
        $user = User::first() ?? User::factory()->create();
        $token = Str::uuid()->toString();

        $response = $this->actingAs($user)->postJson('/students/photos/match', array_merge([
            'academic_year_id' => $year->id,
            'token' => $token,
        ], ['photos' => $photos]));
        $response->assertOk();

        return $response->json();
    }

    private function confirm(AcademicYear $year, string $token)
    {
        $user = User::first() ?? User::factory()->create();

        return $this->actingAs($user)->postJson('/students/photos/confirm', [
            'academic_year_id' => $year->id,
            'token' => $token,
        ]);
    }

    public function test_eslestirme_kaydetmeden_onizleme_doner(): void
    {
        User::factory()->create();
        $year = $this->setupData();

        $p1 = $this->makeJpg('145.jpg');
        $p2 = $this->makeJpg('999.jpg');

        $match = $this->match($year, [
            new UploadedFile($p1, '145.jpg', 'image/jpeg', null, true),
            new UploadedFile($p2, '999.jpg', 'image/jpeg', null, true),
        ]);

        $this->assertCount(2, $match['files']);
        $this->assertTrue($match['files'][0]['matched']);
        $this->assertEquals('145', $match['files'][0]['school_number']);
        $this->assertFalse($match['files'][1]['matched']);

        // Eşleştirme kaydetmez.
        $this->assertNull($this->studentByNumber('145')->person->photo_path);

        unlink($p1);
        unlink($p2);
    }

    public function test_coklu_fotograf_yukleme_ve_eslestirme(): void
    {
        User::factory()->create();
        $year = $this->setupData();

        $p1 = $this->makeJpg('145.jpg');
        $p2 = $this->makeJpg('999.jpg');

        $match = $this->match($year, [
            new UploadedFile($p1, '145.jpg', 'image/jpeg', null, true),
            new UploadedFile($p2, '999.jpg', 'image/jpeg', null, true),
        ]);

        $response = $this->confirm($year, $match['token']);
        $response->assertOk();

        $summary = $response->json('summary');
        $this->assertEquals(['eslesen' => 1, 'eslesmeyen' => 1, 'fotografsiz' => 1, 'hatali' => 0], $summary);
        $this->assertEquals(['999.jpg'], $response->json('unmatched'));

        $student = $this->studentByNumber('145');
        $this->assertEquals("students/{$student->id}.jpg", $student->person->photo_path);
        Storage::disk('public')->assertExists($student->person->photo_path);
        [$width] = getimagesize(Storage::disk('public')->path($student->person->photo_path));
        $this->assertLessThanOrEqual(800, $width);

        Storage::disk('public')->delete($student->person->photo_path);
        unlink($p1);
        unlink($p2);
    }

    public function test_bmp_icerikli_jpg_dosyasi_donusturulur(): void
    {
        User::factory()->create();
        $year = $this->setupData();

        // E-Okul dosyaları gibi: uzantı .jpg ama içerik BMP.
        $bmpPath = sys_get_temp_dir().'/145.jpg';
        $img = imagecreatetruecolor(133, 171);
        imagefill($img, 0, 0, imagecolorallocate($img, 120, 140, 160));
        imagebmp($img, $bmpPath);
        imagedestroy($img);

        $match = $this->match($year, [
            new UploadedFile($bmpPath, '145.jpg', 'image/bmp', null, true),
        ]);

        $response = $this->confirm($year, $match['token']);
        $response->assertOk();
        $this->assertEquals(1, $response->json('summary.eslesen'));

        $student = $this->studentByNumber('145');
        $this->assertEquals('image/jpeg', finfo_file(finfo_open(FILEINFO_MIME_TYPE), Storage::disk('public')->path($student->person->photo_path)));

        Storage::disk('public')->delete($student->person->photo_path);
        unlink($bmpPath);
    }

    public function test_eslesmesiz_onay_reddedilir(): void
    {
        User::factory()->create();
        $year = $this->setupData();

        $this->confirm($year, Str::uuid()->toString())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Eşleştirme bulunamadı. Yeniden eşleştirin.');
    }

    public function test_desteklenmeyen_format_reddedilir(): void    {
        User::factory()->create();
        $year = $this->setupData();

        $txt = sys_get_temp_dir().'/145.txt';
        file_put_contents($txt, 'sahte');

        $this->actingAs(User::first())->post('/students/photos/match', [
            'academic_year_id' => $year->id,
            'token' => Str::uuid()->toString(),
            'photos' => [new UploadedFile($txt, '145.txt', 'text/plain', null, true)],
        ])->assertSessionHasErrors('photos.0');

        unlink($txt);
    }
}
