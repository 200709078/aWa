<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_akademik_yil_ekleme_ve_duzenleme(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->post('/academic-years', ['name' => '2026-2027'])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_years', ['name' => '2026-2027', 'is_active' => false]);

        $year = AcademicYear::first();

        $this->actingAs($user)->put("/academic-years/{$year->id}", ['name' => '2026-2028'])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_years', ['id' => $year->id, 'name' => '2026-2028']);
    }

    public function test_aktif_yil_secimi_tekil_olur(): void
    {
        $user = \App\Models\User::factory()->create();
        $y1 = AcademicYear::create(['name' => '2026-2027']);
        $y2 = AcademicYear::create(['name' => '2027-2028']);

        $this->actingAs($user)->post("/academic-years/{$y1->id}/activate")->assertRedirect();
        $this->actingAs($user)->post("/academic-years/{$y2->id}/activate")->assertRedirect();

        $this->assertFalse($y1->fresh()->is_active);
        $this->assertTrue($y2->fresh()->is_active);

        $this->actingAs($user)->post("/academic-years/{$y2->id}/deactivate")->assertRedirect();
        $this->assertFalse($y2->fresh()->is_active);
    }

    public function test_yil_adi_zorunlu(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->post('/academic-years', ['name' => ''])
            ->assertSessionHasErrors('name');
    }
}
