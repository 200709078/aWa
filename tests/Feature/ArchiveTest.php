<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Graduate;
use App\Models\Person;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesStudents;

class ArchiveTest extends TestCase
{
    use CreatesStudents;
    use RefreshDatabase;

    private function setupYear(): AcademicYear
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);

        return $year;
    }

    public function test_ogrenci_sil_arsive_gonderir_listeden_gizlenir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $student = $this->makeStudent($year, $branch, '145', 'Ali Veli');

        $this->actingAs($user)->delete("/students/{$student->id}")->assertRedirect();

        $this->assertSoftDeleted('students', ['id' => $student->id]);
        // Fotoğraf değil ama enrollment da arşivlenir.
        $this->assertSoftDeleted('student_enrollments', ['student_id' => $student->id]);

        // Normal listede görünmez.
        $this->actingAs($user)->get('/students')->assertInertia(fn ($page) => $page->where('students.total', 0));

        // Arşivde görünür.
        $this->actingAs($user)->get('/arsiv?tab=students')->assertOk()
            ->assertInertia(fn ($page) => $page->where('students.total', 1)->where('trashedStudents', 1));
    }

    public function test_arsivdeki_numara_bosa_cikar_cakisan_geri_alma_engellenir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $archived = $this->makeStudent($year, $branch, '145', 'Ali Veli');

        $this->actingAs($user)->delete("/students/{$archived->id}")->assertRedirect();

        // Aynı numarayla yeni kayıt açılabilir.
        $this->actingAs($user)->post('/students', [
            'branch_id' => $branch->id,
            'school_number' => '145',
            'first_name' => 'Yeni',
            'last_name' => 'Kayıt',
        ])->assertRedirect();
        $fresh = Student::whereHas('enrollments', fn ($q) => $q->where('academic_year_id', $year->id)->where('school_number', '145'))->first();
        $this->assertNotEquals($archived->id, $fresh->id);

        // Numara doluyken geri alma engellenir, arşivde kalır.
        $this->actingAs($user)->post("/arsiv/ogrenciler/{$archived->id}/restore")->assertSessionHasErrors('restore');
        $this->assertTrue(Student::onlyTrashed()->whereKey($archived->id)->exists());

        // Yeni boş numarayla geri alınabilir.
        $this->actingAs($user)->post("/arsiv/ogrenciler/{$archived->id}/restore", [
            'numbers' => [$archived->enrollments()->withTrashed()->first()->id => '146'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $archived->id, 'school_number' => '146']);
        $this->assertFalse(Student::onlyTrashed()->whereKey($archived->id)->exists());
    }

    public function test_ogrenci_kalici_sil_ve_fotograf_temizligi(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $student = $this->makeStudent($year, $branch, '145', 'Ali Veli');
        $personId = $student->person_id;

        $this->actingAs($user)->delete("/students/{$student->id}")->assertRedirect();
        $this->actingAs($user)->delete("/arsiv/ogrenciler/{$student->id}")->assertRedirect();

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('student_enrollments', ['student_id' => $student->id]);
        // Başka rolü yoksa kişi de kalıcı silinir.
        $this->assertDatabaseMissing('people', ['id' => $personId]);
    }

    public function test_mezun_arsiv_geri_al_kalici_sil_numara_bosa_cikar(): void
    {
        $user = User::factory()->create();
        $person = Person::create(['full_name' => 'Mezun Kişi']);
        $graduate = Graduate::create(['person_id' => $person->id, 'graduation_year' => 2020, 'graduation_number' => '7']);

        $this->actingAs($user)->delete("/mezunlar/{$graduate->id}")->assertRedirect();
        $this->assertSoftDeleted('graduates', ['id' => $graduate->id]);
        $this->actingAs($user)->get('/mezunlar')->assertInertia(fn ($page) => $page->where('totalGraduates', 0));

        // Aynı yıl+numarayla yeni mezun açılabilir.
        $this->actingAs($user)->post('/mezunlar', [
            'graduation_year' => 2020,
            'graduation_number' => '7',
            'first_name' => 'Yeni',
            'last_name' => 'Mezun',
        ])->assertRedirect();

        // Dolu numaraya geri alma engellenir.
        $this->actingAs($user)->post("/arsiv/mezunlar/{$graduate->id}/restore")->assertSessionHasErrors('restore');

        // Yeni numarayla geri alınır.
        $this->actingAs($user)->post("/arsiv/mezunlar/{$graduate->id}/restore", [
            'graduation_number' => '8',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('graduates', ['id' => $graduate->id, 'graduation_number' => '8']);

        $this->actingAs($user)->delete("/mezunlar/{$graduate->id}")->assertRedirect();
        $this->actingAs($user)->delete("/arsiv/mezunlar/{$graduate->id}")->assertRedirect();
        $this->assertDatabaseMissing('graduates', ['id' => $graduate->id]);
    }
}
