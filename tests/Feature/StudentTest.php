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
use Tests\Traits\CreatesStudents;

class StudentTest extends TestCase
{
    use CreatesStudents;
    use RefreshDatabase;

    private function setupYear(): AcademicYear
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        Branch::create(['academic_year_id' => $year->id, 'name' => '9B', 'grade_level' => 9, 'section' => 'B']);

        return $year;
    }

    public function test_ogrenci_ekleme_ve_listeleme(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();

        $this->actingAs($user)->post('/students', [
            'branch_id' => $branch->id,
            'school_number' => '145',
            'first_name' => 'Ali',
            'last_name' => 'Veli',
        ])->assertRedirect();

        $this->assertDatabaseHas('people', [
            'full_name' => 'Ali Veli',
        ]);
        $this->assertDatabaseHas('student_enrollments', [
            'academic_year_id' => $year->id,
            'branch_id' => $branch->id,
            'school_number' => '145',
        ]);

        $response = $this->actingAs($user)->get('/students');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('students.total', 1));
    }

    public function test_arama_ve_sube_filtresi(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $b9a = Branch::where('name', '9A')->first();
        $b9b = Branch::where('name', '9B')->first();
        $this->makeStudent($year, $b9a, '145', 'Ali Veli');
        $this->makeStudent($year, $b9b, '146', 'Ayşe Yılmaz');

        $response = $this->actingAs($user)->get('/students?q=145');
        $response->assertInertia(fn ($page) => $page->where('students.total', 1));

        $response = $this->actingAs($user)->get("/students?branch_id={$b9b->id}");
        $response->assertInertia(fn ($page) => $page
            ->where('students.total', 1)
            ->where('students.data.0.full_name', 'Ayşe Yılmaz')
        );
    }

    public function test_sinif_sayfalama_ve_numara_sirasi(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $b9a = Branch::where('name', '9A')->first();
        $b9b = Branch::where('name', '9B')->first();
        $this->makeStudent($year, $b9a, '145', 'Ali Veli');
        $this->makeStudent($year, $b9a, '9', 'Küçük Numara');
        $this->makeStudent($year, $b9a, '30', 'Orta Numara');
        $this->makeStudent($year, $b9b, '146', 'Ayşe Yılmaz');

        // 1. sayfa ilk sınıf (9A), numaraya göre doğal sıralı
        $response = $this->actingAs($user)->get('/students');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('students.total', 3)
            ->where('students.data.0.school_number', '9')
            ->where('students.data.1.school_number', '30')
            ->where('students.data.2.school_number', '145')
        );

        // 2. sayfa ikinci sınıf
        $response = $this->actingAs($user)->get('/students?page=2');
        $response->assertInertia(fn ($page) => $page
            ->where('students.total', 1)
            ->where('students.data.0.full_name', 'Ayşe Yılmaz')
        );
    }

    public function test_ayni_yilda_mukerrer_numara_engellenir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $this->makeStudent($year, $branch, '145', 'Ali Veli');

        $this->actingAs($user)->post('/students', [
            'branch_id' => $branch->id,
            'school_number' => '145',
            'first_name' => 'Başka',
            'last_name' => 'Biri',
        ])->assertSessionHasErrors('school_number');
    }

    public function test_ogrenci_guncelleme_ve_pasife_alma(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $b9a = Branch::where('name', '9A')->first();
        $b9b = Branch::where('name', '9B')->first();
        $student = $this->makeStudent($year, $b9a, '145', 'Ali Veli');

        $this->actingAs($user)->put("/students/{$student->id}", [
            'branch_id' => $b9b->id,
            'school_number' => '145',
            'first_name' => 'Ali Veli',
            'last_name' => 'Güncel',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id, 'branch_id' => $b9b->id, 'school_number' => '145']);
        $this->assertDatabaseHas('people', ['id' => $student->person_id, 'full_name' => 'Ali Veli Güncel']);

        $this->actingAs($user)->post("/students/{$student->id}/deactivate")->assertRedirect();
        $this->assertFalse($student->fresh()->is_active);
    }

    public function test_ogrenci_veli_ile_eklenir_ve_guncellenir(): void
    {
        $user = User::factory()->create();
        $this->setupYear();
        $branch = Branch::where('name', '9A')->first();

        $this->actingAs($user)->post('/students', [
            'branch_id' => $branch->id,
            'school_number' => '145',
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'guardians' => [
                ['relation' => 'anne', 'first_name' => 'Anne', 'last_name' => 'Veli', 'phone' => '05320000001', 'is_primary' => true],
                ['relation' => 'baba', 'first_name' => 'Baba', 'last_name' => 'Veli', 'phone' => '05320000002'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $student = Student::first();
        $this->assertCount(2, $student->guardians);
        $this->assertDatabaseHas('people', ['full_name' => 'Anne Veli', 'phone' => '05320000001']);
        $this->assertEquals(1, $student->guardians()->wherePivot('is_primary', true)->count());

        $anne = $student->guardians()->wherePivot('relationship', 'anne')->first();
        $baba = $student->guardians()->wherePivot('relationship', 'baba')->first();

        // Anneyi güncelle, babayı kaldır, dede ekle (birincil değişir).
        $this->actingAs($user)->put("/students/{$student->id}", [
            'branch_id' => $branch->id,
            'school_number' => '145',
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'guardians' => [
                ['id' => $anne->id, 'relation' => 'anne', 'first_name' => 'Anne', 'last_name' => 'Veli', 'phone' => '05329999999'],
                ['relation' => 'veli', 'first_name' => 'Dede', 'last_name' => 'Veli', 'phone' => '', 'is_primary' => true],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertCount(2, $student->guardians);
        $this->assertDatabaseHas('people', ['full_name' => 'Anne Veli', 'phone' => '05329999999']);
        $this->assertDatabaseHas('people', ['full_name' => 'Dede Veli']);
        // Kaldırılan babanın yetim kaydı temizlenir.
        $this->assertDatabaseMissing('guardians', ['id' => $baba->id]);
        $this->assertEquals('Dede Veli', $student->guardians()->wherePivot('is_primary', true)->first()->person->full_name);
    }

    private function makePhoto(string $filename, int $w = 1200, int $h = 900): string
    {
        $path = sys_get_temp_dir().'/'.$filename;
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 100, 150, 200));
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }

    public function test_ogrenci_tum_kisi_bilgileriyle_eklenir(): void
    {
        $user = User::factory()->create();
        $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $photo = $this->makePhoto('ogrenci.jpg');

        $this->actingAs($user)->post('/students', [
            'branch_id' => $branch->id,
            'school_number' => '145',
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'phone' => '05320000000',
            'email' => 'ali@example.com',
            'address' => 'Örnek Mah.',
            'photo' => new UploadedFile($photo, 'ogrenci.jpg', 'image/jpeg', null, true),
        ])->assertRedirect();

        $this->assertDatabaseHas('people', [
            'full_name' => 'Ali Veli',
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'phone' => '05320000000',
            'email' => 'ali@example.com',
            'address' => 'Örnek Mah.',
        ]);

        $student = Student::first();
        $this->assertEquals("students/{$student->id}.jpg", $student->person->photo_path);
        Storage::disk('public')->assertExists($student->person->photo_path);
        [$width] = getimagesize(Storage::disk('public')->path($student->person->photo_path));
        $this->assertLessThanOrEqual(800, $width);

        Storage::disk('public')->delete($student->person->photo_path);
        unlink($photo);
    }

    public function test_ad_soyad_zorunludur(): void
    {
        $user = User::factory()->create();
        $this->setupYear();
        $branch = Branch::where('name', '9A')->first();

        $this->actingAs($user)->post('/students', [
            'branch_id' => $branch->id,
            'school_number' => '146',
            'first_name' => 'Ayşe',
        ])->assertSessionHasErrors(['last_name']);
    }

    public function test_ogrenci_guncelleme_kisi_bilgisi_ve_fotograf(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $student = $this->makeStudent($year, $branch, '145', 'Ali Veli');
        $student->person->update(['phone' => '05320000000']);

        $photo = $this->makePhoto('yeni.jpg', 400, 300);

        $this->actingAs($user)->post("/students/{$student->id}", [
            '_method' => 'PUT',
            'branch_id' => $branch->id,
            'school_number' => '145',
            'first_name' => 'Ali Can',
            'last_name' => 'Veli',
            'phone' => '',
            'email' => 'alican@example.com',
            'address' => '',
            'photo' => new UploadedFile($photo, 'yeni.jpg', 'image/jpeg', null, true),
        ])->assertRedirect();

        $person = $student->person->fresh();
        $this->assertEquals('Ali Can Veli', $person->full_name);
        $this->assertEquals('Ali Can', $person->first_name);
        $this->assertNull($person->phone);
        $this->assertEquals('alican@example.com', $person->email);
        $this->assertEquals("students/{$student->id}.jpg", $person->photo_path);
        Storage::disk('public')->assertExists($person->photo_path);

        Storage::disk('public')->delete($person->photo_path);
        unlink($photo);
    }
}
