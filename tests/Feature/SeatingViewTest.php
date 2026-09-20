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

class SeatingViewTest extends TestCase
{
    use CreatesStudents;
    use RefreshDatabase;

    public function test_koltukta_fotograf_bilgisi_gosterilir(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branch = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $room = Room::create(['name' => 'Salon 1']);
        $seat1 = Seat::where('room_id', $room->id)->where('row', 1)->where('column', 1)->first();
        $seat2 = Seat::where('room_id', $room->id)->where('row', 1)->where('column', 2)->first();

        $withPhoto = $this->makeStudent($year, $branch, '1', 'Fotolu', ['photo_path' => 'students/1.jpg']);
        $withoutPhoto = $this->makeStudent($year, $branch, '2', 'Fotosuz');

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

    public function test_planda_numara_ve_sube_gorunur(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branch = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $room = Room::create(['name' => 'Salon 1']);
        $seat = Seat::where('room_id', $room->id)->where('row', 1)->where('column', 1)->first();

        $student = $this->makeStudent($year, $branch, '1', 'Numaralı');

        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);
        $week->branches()->sync([$branch->id]);
        $week->rooms()->sync([$room->id]);

        $plan = SeatingPlan::create(['exam_week_id' => $week->id, 'status' => 'draft', 'total_students' => 1, 'used_room_count' => 1]);
        SeatingAssignment::create(['seating_plan_id' => $plan->id, 'student_id' => $student->id, 'seat_id' => $seat->id]);

        // Controller examWeek'i eksik sütunlarla önyükler; ızgara yine dolu gelmeli.
        $response = $this->actingAs($user)->get("/distribution/plans/{$plan->id}");
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('roomsData.0.seats.0.student.school_number', '1')
            ->where('roomsData.0.seats.0.student.full_name', 'Numaralı')
            ->where('roomsData.0.seats.0.student.branch', '9A')
            ->where('roomsData.0.seats.0.student.grade_level', 9)
        );
    }
}
