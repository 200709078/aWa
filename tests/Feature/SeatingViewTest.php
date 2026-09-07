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

class SeatingViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_koltukta_fotograf_bilgisi_gosterilir(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branch = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $room = Room::create(['name' => 'Salon 1']);
        $seat1 = Seat::create(['room_id' => $room->id, 'row' => 1, 'column' => 1]);
        $seat2 = Seat::create(['room_id' => $room->id, 'row' => 1, 'column' => 2]);

        $withPhoto = Student::create(['academic_year_id' => $year->id, 'branch_id' => $branch->id, 'school_number' => '1', 'full_name' => 'Fotolu', 'photo_path' => 'students/1.jpg']);
        $withoutPhoto = Student::create(['academic_year_id' => $year->id, 'branch_id' => $branch->id, 'school_number' => '2', 'full_name' => 'Fotosuz']);

        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);
        $week->branches()->sync([$branch->id]);
        $week->rooms()->sync([$room->id]);

        $plan = SeatingPlan::create(['exam_week_id' => $week->id, 'status' => 'draft', 'total_students' => 2, 'used_room_count' => 1]);
        SeatingAssignment::create(['seating_plan_id' => $plan->id, 'student_id' => $withPhoto->id, 'seat_id' => $seat1->id]);
        SeatingAssignment::create(['seating_plan_id' => $plan->id, 'student_id' => $withoutPhoto->id, 'seat_id' => $seat2->id]);

        $response = $this->actingAs($user)->get("/distribution/plans/{$plan->id}");
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('roomsData.0.seats.0.student.photo_url', fn ($url) => str_ends_with((string) $url, 'students/1.jpg'))
            ->where('roomsData.0.seats.1.student.photo_url', null)
        );
    }
}
