<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'full_name' => 'Ali Veli',
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

    public function test_ayni_yilda_mukerrer_numara_engellenir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $this->makeStudent($year, $branch, '145', 'Ali Veli');

        $this->actingAs($user)->post('/students', [
            'branch_id' => $branch->id,
            'school_number' => '145',
            'full_name' => 'Başka Biri',
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
            'full_name' => 'Ali Veli Güncel',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id, 'branch_id' => $b9b->id, 'school_number' => '145']);
        $this->assertDatabaseHas('people', ['id' => $student->person_id, 'full_name' => 'Ali Veli Güncel']);

        $this->actingAs($user)->post("/students/{$student->id}/deactivate")->assertRedirect();
        $this->assertFalse($student->fresh()->is_active);
    }
}
