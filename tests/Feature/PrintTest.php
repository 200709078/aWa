<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ExamWeek;
use App\Models\Room;
use App\Models\SeatingAssignment;
use App\Models\SeatingPlan;
use App\Models\Seat;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesStudents;

class PrintTest extends TestCase
{
    use CreatesStudents;
    use RefreshDatabase;

    private function setupPlan(): SeatingPlan
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branch = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $room = Room::create(['name' => 'Salon 1']);
        $seat = Seat::where('room_id', $room->id)->where('row', 1)->where('column', 1)->first();
        $student = $this->makeStudent($year, $branch, '1', 'Ali');

        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);
        $week->branches()->sync([$branch->id]);
        $week->rooms()->sync([$room->id]);

        $plan = SeatingPlan::create(['exam_week_id' => $week->id, 'status' => 'draft', 'total_students' => 1, 'used_room_count' => 1]);
        SeatingAssignment::create(['seating_plan_id' => $plan->id, 'student_id' => $student->id, 'seat_id' => $seat->id]);

        return $plan;
    }

    public function test_cikti_sayfalari_korumali_ve_acilir(): void
    {
        $plan = $this->setupPlan();
        $urls = [
            "/distribution/plans/{$plan->id}/print/seating",
            "/distribution/plans/{$plan->id}/print/seating?photo=0",
            "/distribution/plans/{$plan->id}/print/branches",
            "/distribution/plans/{$plan->id}/print/rooms",
            "/distribution/plans/{$plan->id}/print/summary",
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        $user = User::first();

        foreach ($urls as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        $response = $this->actingAs($user)->get("/distribution/plans/{$plan->id}/print/seating?photo=0");
        $response->assertInertia(fn ($page) => $page->where('showPhotos', false));
    }

    public function test_ozet_icerigi_dogru(): void    {
        $plan = $this->setupPlan();
        $user = User::first();

        $response = $this->actingAs($user)->get("/distribution/plans/{$plan->id}/print/summary");
        $response->assertInertia(fn ($page) => $page
            ->where('summary.total_students', 1)
            ->where('branches', ['9A'])
            ->where('branchTotals', [1])
        );
    }
}
