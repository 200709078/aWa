<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_sube_ekleme_ve_buyuk_harfe_cevirme(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);

        $this->actingAs($user)->post('/branches', [
            'academic_year_id' => $year->id,
            'name' => '9a',
            'grade_level' => 9,
            'section' => 'a',
        ])->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'academic_year_id' => $year->id,
            'name' => '9A',
            'grade_level' => 9,
            'section' => 'A',
            'is_active' => true,
        ]);
    }

    public function test_ayni_yilda_mukerrer_sube_engellenir(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);

        $this->actingAs($user)->post('/branches', [
            'academic_year_id' => $year->id,
            'name' => '9A',
            'grade_level' => 9,
            'section' => 'A',
        ])->assertSessionHasErrors('name');
    }

    public function test_farkli_yilda_ayni_sube_adi_serbest(): void
    {
        $user = User::factory()->create();
        $y1 = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $y2 = AcademicYear::create(['name' => '2027-2028']);

        $this->actingAs($user)->post('/branches', [
            'academic_year_id' => $y1->id,
            'name' => '9A',
            'grade_level' => 9,
            'section' => 'A',
        ])->assertRedirect();

        $this->actingAs($user)->post('/branches', [
            'academic_year_id' => $y2->id,
            'name' => '9A',
            'grade_level' => 9,
            'section' => 'A',
        ])->assertRedirect();

        $this->assertEquals(2, Branch::where('name', '9A')->count());
    }

    public function test_sube_guncelleme_ve_yila_gore_filtre(): void
    {
        $user = User::factory()->create();
        $y1 = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $y2 = AcademicYear::create(['name' => '2027-2028']);
        $branch = Branch::create(['academic_year_id' => $y1->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);

        $this->actingAs($user)->put("/branches/{$branch->id}", [
            'academic_year_id' => $y1->id,
            'name' => '9B',
            'grade_level' => 9,
            'section' => 'B',
            'is_active' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => '9B', 'is_active' => false]);

        $response = $this->actingAs($user)->get("/branches?academic_year_id={$y2->id}");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('selectedYearId', $y2->id)
            ->where('branches', [])
        );
    }
}
