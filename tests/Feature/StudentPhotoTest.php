<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class StudentPhotoTest extends TestCase
{
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
        Student::create(['academic_year_id' => $year->id, 'branch_id' => $branch->id, 'school_number' => '145', 'full_name' => 'Ali Veli']);
        Student::create(['academic_year_id' => $year->id, 'branch_id' => $branch->id, 'school_number' => '146', 'full_name' => 'Ayşe Yılmaz']);

        return $year;
    }

    public function test_coklu_fotograf_yukleme_ve_eslestirme(): void
    {
        $user = User::factory()->create();
        $year = $this->setupData();

        $p1 = $this->makeJpg('145.jpg');
        $p2 = $this->makeJpg('999.jpg');

        $response = $this->actingAs($user)->post('/students/photos', [
            'academic_year_id' => $year->id,
            'photos' => [
                new UploadedFile($p1, '145.jpg', 'image/jpeg', null, true),
                new UploadedFile($p2, '999.jpg', 'image/jpeg', null, true),
            ],
        ]);
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertEquals(['eslesen' => 1, 'eslesmeyen' => 1, 'fotografsiz' => 1, 'hatali' => 0], $props['summary']);
        $this->assertEquals(['999.jpg'], $props['unmatched']);

        $student = Student::where('school_number', '145')->first();
        $this->assertEquals("students/{$student->id}.jpg", $student->photo_path);
        Storage::disk('public')->assertExists($student->photo_path);
        [$width] = getimagesize(Storage::disk('public')->path($student->photo_path));
        $this->assertLessThanOrEqual(800, $width);

        Storage::disk('public')->delete($student->photo_path);
        unlink($p1);
        unlink($p2);
    }

    public function test_zip_ile_fotograf_yukleme(): void
    {
        $user = User::factory()->create();
        $year = $this->setupData();

        $img = $this->makeJpg('146.jpg', 400, 300);
        $zipPath = sys_get_temp_dir().'/fotograflar.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($img, '146.jpg');
        $zip->addFromString('not.txt', 'metin dosyası');
        $zip->close();

        $response = $this->actingAs($user)->post('/students/photos', [
            'academic_year_id' => $year->id,
            'zip_file' => new UploadedFile($zipPath, 'fotograflar.zip', 'application/zip', null, true),
        ]);
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertEquals(1, $props['summary']['eslesen']);
        $this->assertEquals(1, $props['summary']['fotografsiz']);

        $student = Student::where('school_number', '146')->first();
        $this->assertEquals("students/{$student->id}.jpg", $student->photo_path);

        Storage::disk('public')->delete($student->photo_path);
        unlink($img);
        unlink($zipPath);
    }

    public function test_bmp_icerikli_jpg_dosyasi_donusturulur(): void
    {
        $user = User::factory()->create();
        $year = $this->setupData();

        // E-Okul dosyaları gibi: uzantı .jpg ama içerik BMP.
        $bmpPath = sys_get_temp_dir().'/145.jpg';
        $img = imagecreatetruecolor(133, 171);
        imagefill($img, 0, 0, imagecolorallocate($img, 120, 140, 160));
        imagebmp($img, $bmpPath);
        imagedestroy($img);

        $response = $this->actingAs($user)->post('/students/photos', [
            'academic_year_id' => $year->id,
            'photos' => [new UploadedFile($bmpPath, '145.jpg', 'image/bmp', null, true)],
        ]);
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertEquals(1, $props['summary']['eslesen']);

        $student = Student::where('school_number', '145')->first();
        $this->assertEquals('image/jpeg', finfo_file(finfo_open(FILEINFO_MIME_TYPE), Storage::disk('public')->path($student->photo_path)));

        Storage::disk('public')->delete($student->photo_path);
        unlink($bmpPath);
    }

    public function test_desteklenmeyen_format_reddedilir(): void    {
        $user = User::factory()->create();
        $year = $this->setupData();

        $txt = sys_get_temp_dir().'/145.txt';
        file_put_contents($txt, 'sahte');

        $this->actingAs($user)->post('/students/photos', [
            'academic_year_id' => $year->id,
            'photos' => [new UploadedFile($txt, '145.txt', 'text/plain', null, true)],
        ])->assertSessionHasErrors('photos.0');

        unlink($txt);
    }
}
