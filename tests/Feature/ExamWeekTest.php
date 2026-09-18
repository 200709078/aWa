<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ExamWeek;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesStudents;

class ExamWeekTest extends TestCase
{
    use CreatesStudents;
    use RefreshDatabase;

    private function setupData(): ExamWeek
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $b1 = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $b2 = Branch::create(['academic_year_id' => $year->id, 'name' => '9B', 'grade_level' => 9, 'section' => 'B']);
        $this->makeStudent($year, $b1, '1', 'Ali');
        $this->makeStudent($year, $b1, '2', 'Veli', ['is_active' => false]);
        $this->makeStudent($year, $b2, '3', 'Ayşe');
        $room = Room::create(['name' => 'Salon 1']);
        Seat::where('room_id', $room->id)->update(['is_active' => false]);
        Seat::where('room_id', $room->id)->where('row', 1)->where('column', 1)->update(['is_active' => true]);

        return ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);
    }

    public function test_sinav_haftasi_ekleme_ve_duzenleme(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);

        $this->actingAs($user)->post('/exam-weeks', [
            'academic_year_id' => $year->id,
            'name' => '1. Dönem',
            'starts_at' => '2026-11-01',
            'ends_at' => '2026-11-05',
        ])->assertRedirect();

        $this->assertDatabaseHas('exam_weeks', ['name' => '1. Dönem']);

        $week = ExamWeek::first();

        $this->actingAs($user)->put("/exam-weeks/{$week->id}", [
            'academic_year_id' => $year->id,
            'name' => '2. Dönem',
        ])->assertRedirect();

        $this->assertDatabaseHas('exam_weeks', ['id' => $week->id, 'name' => '2. Dönem']);
    }

    public function test_sube_ve_salon_secimi_ve_ozet(): void
    {
        $user = User::factory()->create();
        $week = $this->setupData();
        $b1 = Branch::where('name', '9A')->first();
        $b2 = Branch::where('name', '9B')->first();
        $room = Room::first();

        $this->actingAs($user)->put("/exam-weeks/{$week->id}/branches", ['branch_ids' => [$b1->id]])
            ->assertRedirect();

        $this->actingAs($user)->put("/exam-weeks/{$week->id}/rooms", ['room_ids' => [$room->id]])
            ->assertRedirect();

        // Başka yıla ait şube reddedilir
        $otherYear = AcademicYear::create(['name' => '2027-2028']);
        $otherBranch = Branch::create(['academic_year_id' => $otherYear->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $this->actingAs($user)->put("/exam-weeks/{$week->id}/branches", ['branch_ids' => [$otherBranch->id]])
            ->assertSessionHasErrors('branch_ids.0');

        $response = $this->actingAs($user)->get("/exam-weeks/{$week->id}");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('summary.student_count', 1)
            ->where('summary.room_count', 1)
            ->where('summary.capacity', 1)
            ->where('selectedBranchIds', [$b1->id])
        );
    }
}
