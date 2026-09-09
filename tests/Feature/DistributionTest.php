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
use App\Services\DistributionException;
use App\Services\SeatingDistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionTest extends TestCase
{
    use RefreshDatabase;

    private function addSeats(Room $room, int $rows, int $cols): void
    {
        for ($r = 1; $r <= $rows; $r++) {
            for ($c = 1; $c <= $cols; $c++) {
                Seat::create(['room_id' => $room->id, 'row' => $r, 'column' => $c]);
            }
        }
    }

    private function setupWeek(): ExamWeek
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branches = [];
        foreach (['9A' => 9, '10A' => 10, '11A' => 11] as $name => $grade) {
            $branches[] = Branch::create(['academic_year_id' => $year->id, 'name' => $name, 'grade_level' => $grade, 'section' => substr($name, -1)]);
        }

        $no = 0;
        foreach ($branches as $branch) {
            for ($i = 0; $i < 6; $i++) {
                $no++;
                Student::create(['academic_year_id' => $year->id, 'branch_id' => $branch->id, 'school_number' => (string) $no, 'full_name' => "Öğrenci $no"]);
            }
        }

        $rooms = [];
        foreach ([[3, 4], [3, 4], [2, 5]] as $i => [$rows, $cols]) {
            $room = Room::create(['name' => 'Salon '.($i + 1), 'sort_order' => $i]);
            $this->addSeats($room, $rows, $cols);
            $rooms[] = $room;
        }

        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);
        $week->branches()->sync(array_map(fn ($b) => $b->id, $branches));
        $week->rooms()->sync(array_map(fn ($r) => $r->id, $rooms));

        return $week;
    }

    public function test_dagitimda_yatay_ihlal_yok_ve_atamalar_tekil(): void
    {
        $week = $this->setupWeek();
        $service = new SeatingDistributionService();

        $plan = $service->distribute($week, 'Test planı', User::factory()->create()->id);

        $this->assertEquals(18, $plan->total_students);
        $this->assertEquals(18, SeatingAssignment::where('seating_plan_id', $plan->id)->count());
        $this->assertEquals(18, SeatingAssignment::where('seating_plan_id', $plan->id)->distinct('student_id')->count('student_id'));
        $this->assertEquals(18, SeatingAssignment::where('seating_plan_id', $plan->id)->distinct('seat_id')->count('seat_id'));

        $summary = $service->summary($plan);
        $this->assertEquals(0, $summary['violations']);
        $this->assertEquals(18, $summary['used_seats']);
    }

    public function test_minimum_salon_kullanilir(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $b1 = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $b2 = Branch::create(['academic_year_id' => $year->id, 'name' => '10A', 'grade_level' => 10, 'section' => 'A']);
        for ($i = 1; $i <= 5; $i++) {
            Student::create(['academic_year_id' => $year->id, 'branch_id' => $b1->id, 'school_number' => "1$i", 'full_name' => "A $i"]);
            Student::create(['academic_year_id' => $year->id, 'branch_id' => $b2->id, 'school_number' => "2$i", 'full_name' => "B $i"]);
        }

        $roomIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $room = Room::create(['name' => "Salon $i", 'sort_order' => $i]);
            $this->addSeats($room, 2, 5);
            $roomIds[] = $room->id;
        }

        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);
        $week->branches()->sync([$b1->id, $b2->id]);
        $week->rooms()->sync($roomIds);

        $plan = (new SeatingDistributionService())->distribute($week);

        $this->assertEquals(1, $plan->used_room_count);
        $this->assertEquals(0, (new SeatingDistributionService())->summary($plan)['violations']);
    }

    public function test_ayni_seviye_farkli_sube_yan_yana_ihlal_sayar(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $b1 = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        $b2 = Branch::create(['academic_year_id' => $year->id, 'name' => '9B', 'grade_level' => 9, 'section' => 'B']);
        Student::create(['academic_year_id' => $year->id, 'branch_id' => $b1->id, 'school_number' => '1', 'full_name' => 'Ali']);
        Student::create(['academic_year_id' => $year->id, 'branch_id' => $b2->id, 'school_number' => '2', 'full_name' => 'Veli']);

        $room = Room::create(['name' => 'Salon 1']);
        $this->addSeats($room, 1, 2);

        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);
        $week->branches()->sync([$b1->id, $b2->id]);
        $week->rooms()->sync([$room->id]);

        $plan = (new SeatingDistributionService())->distribute($week);

        // Tek satırda yan yana oturmak zorundalar ve aynı seviyedeler.
        $this->assertEquals(1, (new SeatingDistributionService())->summary($plan)['violations']);
    }

    public function test_plan_silme_atamalari_da_siler(): void
    {
        $user = User::factory()->create();
        $week = $this->setupWeek();

        $plan = (new SeatingDistributionService())->distribute($week);
        $this->assertGreaterThan(0, SeatingAssignment::where('seating_plan_id', $plan->id)->count());

        $this->actingAs($user)->delete("/distribution/plans/{$plan->id}")->assertRedirect();

        $this->assertDatabaseMissing('seating_plans', ['id' => $plan->id]);
        $this->assertEquals(0, SeatingAssignment::where('seating_plan_id', $plan->id)->count());
    }

    public function test_kapasite_yetersizken_hata_verir(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $branch = Branch::create(['academic_year_id' => $year->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);
        for ($i = 1; $i <= 5; $i++) {
            Student::create(['academic_year_id' => $year->id, 'branch_id' => $branch->id, 'school_number' => (string) $i, 'full_name' => "Ö $i"]);
        }
        $room = Room::create(['name' => 'Salon 1']);
        $this->addSeats($room, 1, 2);

        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);
        $week->branches()->sync([$branch->id]);
        $week->rooms()->sync([$room->id]);

        $this->expectException(DistributionException::class);
        $this->expectExceptionMessageMatches('/Kapasite yetersiz/');

        (new SeatingDistributionService())->distribute($week);
    }

    public function test_ogrenci_yokken_hata_verir(): void
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => 'Boş Hafta']);

        $this->expectException(DistributionException::class);

        (new SeatingDistributionService())->distribute($week);
    }

    public function test_http_dagitim_akisi_yeni_plan_olusturur(): void
    {
        $user = User::factory()->create();
        $week = $this->setupWeek();

        $response = $this->actingAs($user)->post('/distribution', ['exam_week_id' => $week->id, 'name' => 'Web planı']);
        $plan = SeatingPlan::first();

        $response->assertRedirect("/distribution/plans/{$plan->id}");
        $this->assertEquals('draft', $plan->status);
        $this->assertEquals($user->id, $plan->created_by);

        $this->actingAs($user)->get("/distribution/plans/{$plan->id}")->assertOk();

        $this->actingAs($user)->post("/distribution/plans/{$plan->id}/finalize")->assertRedirect();
        $this->assertEquals('final', $plan->fresh()->status);
    }
}
