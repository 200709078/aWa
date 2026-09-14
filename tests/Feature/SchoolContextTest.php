<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_cok_okullu_kullanici_okul_secer(): void
    {
        $user = User::factory()->create(['password' => '123456']);
        $a = School::create(['name' => 'A Okulu', 'kurum_kodu' => '111111']);
        $b = School::create(['name' => 'B Okulu', 'kurum_kodu' => '222222']);
        $user->schools()->attach([$a->id, $b->id]);

        $this->post('/login', ['email' => $user->email, 'password' => '123456'])
            ->assertRedirect('/select-school');

        $this->assertGuest();

        $this->post('/select-school', ['school_id' => $b->id])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertEquals($b->id, session('current_school_id'));
    }

    public function test_okulsuz_kullanici_giremez(): void
    {
        $user = User::factory()->create(['password' => '123456']);

        $this->post('/login', ['email' => $user->email, 'password' => '123456'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_baska_okulun_verisine_erisilemez(): void
    {
        $school = School::first();
        $other = School::create(['name' => 'Diğer Okul', 'kurum_kodu' => '333333']);
        $otherYear = AcademicYear::create(['school_id' => $other->id, 'name' => '2026-2027']);
        $branch = Branch::create(['academic_year_id' => $otherYear->id, 'name' => '9A', 'grade_level' => 9, 'section' => 'A']);

        $user = User::factory()->create();
        $user->schools()->attach($school->id);

        $this->actingAs($user)->put("/branches/{$branch->id}", [
            'academic_year_id' => $otherYear->id,
            'name' => '9B',
            'grade_level' => 9,
            'section' => 'B',
        ])->assertNotFound();
    }

    public function test_baska_okulun_yiliyla_sube_eklenemez(): void
    {
        $other = School::create(['name' => 'Diğer Okul', 'kurum_kodu' => '333333']);
        $otherYear = AcademicYear::create(['school_id' => $other->id, 'name' => '2026-2027']);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/branches', [
            'academic_year_id' => $otherYear->id,
            'name' => '9A',
            'grade_level' => 9,
            'section' => 'A',
        ])->assertSessionHasErrors('academic_year_id');

        $this->assertEquals(0, Branch::count());
    }
}
