<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassroomPlan;
use App\Models\ClassroomPlanSeat;
use App\Models\Person;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesStudents;

class ClassroomPlanTest extends TestCase
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

    public function test_plan_olusturma_otuz_alti_koltuk_ve_numara_sirasiyla_dagitim(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $this->makeStudent($year, $branch, '27', 'Yirmi Yedi');
        $this->makeStudent($year, $branch, '3', 'Üç Numara');
        $this->makeStudent($year, $branch, '12', 'On İki');

        $response = $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branch->id]);
        $response->assertRedirect();

        $plan = ClassroomPlan::where('branch_id', $branch->id)->first();
        $this->assertNotNull($plan);
        $this->assertEquals(36, $plan->seats()->count());

        $filled = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)
            ->whereNotNull('student_id')
            ->with('student.enrollments')
            ->orderBy('row')->orderBy('column')
            ->get();

        // Her öğrenci bir kez, her koltuk bir kez; numara sırasıyla.
        // Yerleştirme öğretmen masası tarafından (son satır) başlar,
        // boş koltuklar arkada (üst satırlarda) kalır.
        $this->assertEquals(3, $filled->count());
        $this->assertEquals(['3', '12', '27'], $filled->map(fn ($s) => $s->student->enrollments->first()->school_number)->all());
        $this->assertEquals([[6, 1], [6, 2], [6, 3]], $filled->map(fn ($s) => [$s->row, $s->column])->all());
    }

    public function test_siniflar_arasi_tasima_engellenir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branchA = Branch::where('name', '9A')->first();
        $branchB = Branch::where('name', '9B')->first();
        $this->makeStudent($year, $branchA, '10', 'Dokuz A');
        $outsider = $this->makeStudent($year, $branchB, '20', 'Dokuz B');

        $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branchA->id]);
        $plan = ClassroomPlan::where('branch_id', $branchA->id)->first();

        $empty = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->whereNull('student_id')->first();

        $this->actingAs($user)->post("/oturme-planlari/{$plan->id}/tasi", [
            'student_id' => $outsider->id,
            'to_seat_id' => $empty->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('classroom_plan_seats', [
            'classroom_plan_id' => $plan->id, 'student_id' => $outsider->id,
        ]);
    }

    public function test_takas_ve_her_koltuk_silinebilir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $first = $this->makeStudent($year, $branch, '1', 'Birinci');
        $this->makeStudent($year, $branch, '2', 'İkinci');

        $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branch->id]);
        $plan = ClassroomPlan::where('branch_id', $branch->id)->first();

        $seatA = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->whereNotNull('student_id')->orderBy('row')->orderBy('column')->first();
        $seatB = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->whereNotNull('student_id')->orderBy('row')->orderBy('column')->skip(1)->first();
        $firstId = $seatA->student_id;

        $this->actingAs($user)->post("/oturme-planlari/{$plan->id}/takas", [
            'seat_id_a' => $seatA->id, 'seat_id_b' => $seatB->id,
        ])->assertRedirect();

        $this->assertEquals($seatB->student_id, $seatA->fresh()->student_id);
        $this->assertEquals($firstId, $seatB->fresh()->student_id);

        // Dolu karttaki silme önce öğrenciyi çıkarır, koltuk korunur.
        $displacedId = $seatA->fresh()->student_id;
        $this->actingAs($user)->delete("/oturme-planlari/koltuk/{$seatA->id}")->assertRedirect();

        $this->assertDatabaseHas('classroom_plan_seats', ['id' => $seatA->id, 'student_id' => null]);

        $show = $this->actingAs($user)->get("/oturme-planlari/{$plan->id}")->assertOk();
        $unseated = collect($show->viewData('page')['props']['unseated']);
        $this->assertTrue($unseated->contains(fn ($s) => $s['id'] === $displacedId));

        // Boş koltuk silinince koltuk tamamen kalkar.
        $empty = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->whereNull('student_id')->first();
        $this->actingAs($user)->delete("/oturme-planlari/koltuk/{$empty->id}")->assertRedirect();
        $this->assertDatabaseMissing('classroom_plan_seats', ['id' => $empty->id]);
    }

    public function test_silinen_koltuk_ayni_konuma_geri_eklenir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $this->makeStudent($year, $branch, '1', 'Birinci');

        $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branch->id])->assertRedirect();
        $plan = ClassroomPlan::where('branch_id', $branch->id)->first();

        $seat = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->where('row', 2)->where('column', 2)->first();
        $this->actingAs($user)->delete("/oturme-planlari/koltuk/{$seat->id}")->assertRedirect();
        $this->assertEquals(35, $plan->seats()->count());

        $this->actingAs($user)->post("/oturme-planlari/{$plan->id}/koltuk", ['row' => 2, 'column' => 2])->assertRedirect();

        $this->assertEquals(36, $plan->seats()->count());
        $this->assertDatabaseHas('classroom_plan_seats', [
            'classroom_plan_id' => $plan->id, 'row' => 2, 'column' => 2,
        ]);
    }

    public function test_dolu_koltuktan_bos_koltuga_tasima_yapilir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $this->makeStudent($year, $branch, '1', 'Birinci');
        $this->makeStudent($year, $branch, '2', 'İkinci');

        $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branch->id])->assertRedirect();
        $plan = ClassroomPlan::where('branch_id', $branch->id)->first();

        $from = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->whereNotNull('student_id')->first();
        $to = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->whereNull('student_id')->first();
        $studentId = $from->student_id;

        $this->actingAs($user)->post("/oturme-planlari/{$plan->id}/tasi", [
            'from_seat_id' => $from->id,
            'to_seat_id' => $to->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($from->fresh()->student_id);
        $this->assertEquals($studentId, $to->fresh()->student_id);
    }

    public function test_listeyle_esitle_once_bos_koltuklari_doldurur(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $this->makeStudent($year, $branch, '1', 'Birinci');
        $this->makeStudent($year, $branch, '2', 'İkinci');
        $this->makeStudent($year, $branch, '3', 'Üçüncü');

        $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branch->id])->assertRedirect();
        $plan = ClassroomPlan::where('branch_id', $branch->id)->first();

        $newcomer = $this->makeStudent($year, $branch, '4', 'Dördüncü');
        $this->actingAs($user)->post("/oturme-planlari/{$plan->id}/esitle")->assertRedirect();

        // Mevcut 36 koltuk boşken yeni satır açılmaz; gelen öğrenci
        // öğretmen masası tarafındaki ilk boş koltuğa yerleşir.
        $this->assertEquals(36, $plan->seats()->count());
        $seat = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->where('student_id', $newcomer->id)->first();
        $this->assertNotNull($seat);
        $this->assertEquals([6, 4], [$seat->row, $seat->column]);
    }

    public function test_arsive_alinan_ogrenci_plandan_kaldirilir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $student = $this->makeStudent($year, $branch, '1', 'Birinci');

        $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branch->id]);
        $plan = ClassroomPlan::where('branch_id', $branch->id)->first();
        $seatCount = $plan->seats()->count();
        $this->assertNotNull(ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->where('student_id', $student->id)->first());

        $this->actingAs($user)->delete("/students/{$student->id}")->assertRedirect();

        // Koltuk düzeni korunur, yalnız öğrencisi boşaltılır.
        $this->assertEquals($seatCount, $plan->seats()->count());
        $this->assertDatabaseMissing('classroom_plan_seats', [
            'classroom_plan_id' => $plan->id, 'student_id' => $student->id,
        ]);
    }

    public function test_kalici_silinen_ogrenci_plandan_kaldirilir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $student = $this->makeStudent($year, $branch, '1', 'Birinci');

        $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branch->id]);
        $plan = ClassroomPlan::where('branch_id', $branch->id)->first();

        $this->actingAs($user)->delete("/students/{$student->id}")->assertRedirect();
        $this->actingAs($user)->delete("/arsiv/ogrenciler/{$student->id}")->assertRedirect();

        $this->assertDatabaseMissing('classroom_plan_seats', [
            'classroom_plan_id' => $plan->id, 'student_id' => $student->id,
        ]);
    }

    public function test_sube_rehber_ogretmeni_planda_fotografiyla_gorunur(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $this->makeStudent($year, $branch, '1', 'Birinci');

        $person = Person::create(['school_id' => $year->school_id, 'full_name' => 'Rehber Öğretmen']);
        $teacher = Teacher::create(['person_id' => $person->id]);

        $this->actingAs($user)->put("/branches/{$branch->id}", [
            'academic_year_id' => $year->id,
            'name' => '9A',
            'grade_level' => 9,
            'section' => 'A',
            'teacher_id' => $teacher->id,
        ])->assertRedirect();

        $this->assertEquals($teacher->id, $branch->fresh()->teacher_id);

        $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branch->id])->assertRedirect();
        $plan = ClassroomPlan::where('branch_id', $branch->id)->first();

        $show = $this->actingAs($user)->get("/oturme-planlari/{$plan->id}")->assertOk();
        $this->assertEquals('Rehber Öğretmen', $show->viewData('page')['props']['teacher']['full_name']);

        $print = $this->actingAs($user)->get("/oturme-planlari/{$plan->id}/yazdir")->assertOk();
        $this->assertEquals('Rehber Öğretmen', $print->viewData('page')['props']['teacher']['full_name']);
    }

    public function test_satir_ve_sutun_ekleme(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $this->makeStudent($year, $branch, '1', 'Birinci');

        $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branch->id])->assertRedirect();
        $plan = ClassroomPlan::where('branch_id', $branch->id)->first();

        $this->actingAs($user)->post("/oturme-planlari/{$plan->id}/satir-ekle")->assertRedirect();
        $this->assertEquals(42, $plan->seats()->count());
        $this->assertEquals(7, ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->max('row'));

        $this->actingAs($user)->post("/oturme-planlari/{$plan->id}/sutun-ekle")->assertRedirect();
        $this->assertEquals(49, $plan->seats()->count());
        $this->assertEquals(7, ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->max('column'));

        // Yerleşen öğrenci kıpırdamadı.
        $this->assertEquals(1, ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->whereNotNull('student_id')->count());
    }

    public function test_listeyle_esitle_gideni_bosaltir_geleni_yerlestirir(): void
    {
        $user = User::factory()->create();
        $year = $this->setupYear();
        $branch = Branch::where('name', '9A')->first();
        $leaver = $this->makeStudent($year, $branch, '5', 'Giden Öğrenci');
        $stayer = $this->makeStudent($year, $branch, '6', 'Kalan Öğrenci');

        $this->actingAs($user)->post('/oturme-planlari', ['branch_id' => $branch->id]);
        $plan = ClassroomPlan::where('branch_id', $branch->id)->first();
        $stayerSeat = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->where('student_id', $stayer->id)->first();

        // Nakil gidenin kaydı silinir, yeni öğrenci gelir.
        $leaver->enrollments()->delete();
        $newcomer = $this->makeStudent($year, $branch, '7', 'Gelen Öğrenci');

        $this->actingAs($user)->post("/oturme-planlari/{$plan->id}/esitle")->assertRedirect();

        // Gidenin koltuğu boşaldı, kalanın yeri korunuyor, gelen yerleşti.
        $this->assertDatabaseMissing('classroom_plan_seats', [
            'classroom_plan_id' => $plan->id, 'student_id' => $leaver->id,
        ]);
        $this->assertEquals($stayerSeat->id, ClassroomPlanSeat::where('student_id', $stayer->id)->value('id'));
        $this->assertNotNull(ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->where('student_id', $newcomer->id)->first());

        // Liste ve yazdırma ekranları açılır.
        $this->actingAs($user)->get('/oturme-planlari')->assertOk();
        $this->actingAs($user)->get("/oturme-planlari/{$plan->id}")->assertOk();
        $this->actingAs($user)->get("/oturme-planlari/{$plan->id}/yazdir")->assertOk();
    }
}
