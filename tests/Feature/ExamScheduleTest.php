<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sinav_ekleme_listeleme_silme(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);

        $this->actingAs($user)->post("/exam-weeks/{$week->id}/exams", [
            'name' => 'Matematik',
            'exam_date' => '2026-11-03',
            'start_time' => '09:00',
            'description' => 'Ortak sınav',
        ])->assertRedirect();

        $this->assertDatabaseHas('exams', ['exam_week_id' => $week->id, 'name' => 'Matematik']);

        $response = $this->actingAs($user)->get("/exam-weeks/{$week->id}");
        $response->assertInertia(fn ($page) => $page
            ->where('exams.0.name', 'Matematik')
            ->where('exams.0.start_time', '09:00')
        );

        $exam = Exam::first();
        $this->actingAs($user)->delete("/exams/{$exam->id}")->assertRedirect();
        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
    }

    public function test_sinav_adi_zorunlu_tarih_opsiyonel(): void
    {
        $user = User::factory()->create();
        $year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
        $week = ExamWeek::create(['academic_year_id' => $year->id, 'name' => '1. Dönem']);

        $this->actingAs($user)->post("/exam-weeks/{$week->id}/exams", ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($user)->post("/exam-weeks/{$week->id}/exams", ['name' => 'Tarihsiz'])
            ->assertRedirect();

        $this->assertDatabaseHas('exams', ['name' => 'Tarihsiz', 'exam_date' => null]);
    }
}
