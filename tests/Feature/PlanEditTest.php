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

class PlanEditTest extends TestCase
{
    use RefreshDatabase;

    private function setupPlan(): SeatingPlan
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $bx = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $by = Branch::create(['academic_year_id' => $year->id, 'name' => '10A', 'grade_level' => 10, 'section' => 'A']);

        $room = Room::create(['name' => 'Salon 1']);
        $seats = [];
        for ($c = 1; $c <= 4; $c++) {
            $seats[$c] = Seat::where('room_id', $room->id)->where('row', 1)->where('column', $c)->first();
        }

        $sx1 = Student::create(['academic_year_id' => $year->id, 'branch_id' => $bx->id, 'school_number' => '1', 'full_name' => 'X1']);
        $sy = Student::create(['academic_year_id' => $year->id, 'branch_id' => $by->id, 'school_number' => '2', 'full_name' => 'Y']);
        $sx2 = Student::create(['academic_year_id' => $year->id, 'branch_id' => $bx->id, 'school_number' => '3', 'full_name' => 'X2']);

        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);
        $week->branches()->sync([$bx->id, $by->id]);
        $week->rooms()->sync([$room->id]);

        $plan = SeatingPlan::create(['exam_week_id' => $week->id, 'status' => 'draft', 'total_students' => 3, 'used_room_count' => 1]);
        SeatingAssignment::create(['seating_plan_id' => $plan->id, 'student_id' => $sx1->id, 'seat_id' => $seats[1]->id]);
        SeatingAssignment::create(['seating_plan_id' => $plan->id, 'student_id' => $sy->id, 'seat_id' => $seats[2]->id]);
        SeatingAssignment::create(['seating_plan_id' => $plan->id, 'student_id' => $sx2->id, 'seat_id' => $seats[3]->id]);

        return $plan;
    }

    public function test_bos_koltuga_tasima_kaydedilir(): void
    {
        $user = User::factory()->create();
        $plan = $this->setupPlan();
        $moving = SeatingAssignment::where('seating_plan_id', $plan->id)->whereHas('student', fn ($q) => $q->where('school_number', '3'))->first();
        $target = Seat::where('column', 4)->first();

        $response = $this->actingAs($user)->postJson("/distribution/plans/{$plan->id}/move", [
            'assignment_id' => $moving->id,
            'seat_id' => $target->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('applied', true);
        $this->assertEquals($target->id, $moving->fresh()->seat_id);
    }

    public function test_ihlal_olusturan_takasta_onay_istenir(): void
    {
        $user = User::factory()->create();
        $plan = $this->setupPlan();
        // col3'teki X2 ile col2'deki Y takas edilirse X2, col1'deki X1 ile yan yana gelir.
        $x2 = SeatingAssignment::where('seating_plan_id', $plan->id)->whereHas('student', fn ($q) => $q->where('school_number', '3'))->first();
        $y = SeatingAssignment::where('seating_plan_id', $plan->id)->whereHas('student', fn ($q) => $q->where('school_number', '2'))->first();

        $preview = $this->actingAs($user)->postJson("/distribution/plans/{$plan->id}/swap", [
            'assignment_id' => $x2->id,
            'other_assignment_id' => $y->id,
        ]);

        $preview->assertOk();
        $preview->assertJsonPath('applied', false);
        $preview->assertJsonPath('needs_confirm', true);
        $this->assertEquals($x2->seat_id, $x2->fresh()->seat_id);

        $force = $this->actingAs($user)->postJson("/distribution/plans/{$plan->id}/swap", [
            'assignment_id' => $x2->id,
            'other_assignment_id' => $y->id,
            'force' => true,
        ]);

        $force->assertOk();
        $force->assertJsonPath('applied', true);
        $this->assertEquals(1, $force->json('violations'));
        $this->assertEquals($y->seat_id, $x2->fresh()->seat_id);
    }

    public function test_kuralsiz_takas_dogrudan_uygulanir(): void
    {
        $user = User::factory()->create();
        $plan = $this->setupPlan();
        // col1'deki X1 ile col3'teki X2 takası ihlal oluşturmaz.
        $x1 = SeatingAssignment::where('seating_plan_id', $plan->id)->whereHas('student', fn ($q) => $q->where('school_number', '1'))->first();
        $x2 = SeatingAssignment::where('seating_plan_id', $plan->id)->whereHas('student', fn ($q) => $q->where('school_number', '3'))->first();
        $seatOfX2 = $x2->seat_id;

        $response = $this->actingAs($user)->postJson("/distribution/plans/{$plan->id}/swap", [
            'assignment_id' => $x1->id,
            'other_assignment_id' => $x2->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('applied', true);
        $this->assertEquals($seatOfX2, $x1->fresh()->seat_id);
    }

    public function test_dolu_koltuga_tasima_reddedilir(): void
    {
        $user = User::factory()->create();
        $plan = $this->setupPlan();
        $x1 = SeatingAssignment::where('seating_plan_id', $plan->id)->whereHas('student', fn ($q) => $q->where('school_number', '1'))->first();
        $occupiedSeat = SeatingAssignment::where('seating_plan_id', $plan->id)->whereHas('student', fn ($q) => $q->where('school_number', '2'))->first()->seat_id;

        $response = $this->actingAs($user)->postJson("/distribution/plans/{$plan->id}/move", [
            'assignment_id' => $x1->id,
            'seat_id' => $occupiedSeat,
        ]);

        $response->assertStatus(422);
        $this->assertNotEquals($occupiedSeat, $x1->fresh()->seat_id);
    }

    public function test_salonlar_arasi_tasima(): void
    {
        $user = User::factory()->create();
        $plan = $this->setupPlan();
        $room2 = Room::create(['name' => 'Salon 2']);
        $target = Seat::where('room_id', $room2->id)->where('row', 1)->where('column', 1)->first();
        $plan->examWeek->rooms()->attach($room2->id);

        $x1 = SeatingAssignment::where('seating_plan_id', $plan->id)->whereHas('student', fn ($q) => $q->where('school_number', '1'))->first();

        $response = $this->actingAs($user)->postJson("/distribution/plans/{$plan->id}/move", [
            'assignment_id' => $x1->id,
            'seat_id' => $target->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('applied', true);
        $this->assertEquals($target->id, $x1->fresh()->seat_id);
        $this->assertEquals(2, $plan->fresh()->used_room_count);
    }
}
