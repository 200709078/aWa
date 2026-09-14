<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    public function test_super_admin_okul_ekler(): void
    {
        $this->actingAs($this->superAdmin())->post('/schools', [
            'name' => 'Yeni Okul',
            'kurum_kodu' => '123456',
            'mail' => 'okul@ornek.tr',
            'telefon' => '03120000000',
            'mudur' => 'Ali Yılmaz',
            'muduryrd' => 'Ayşe Demir',
        ])->assertRedirect();

        $this->assertDatabaseHas('schools', ['name' => 'Yeni Okul', 'kurum_kodu' => '123456', 'mudur' => 'Ali Yılmaz']);
    }

    public function test_kurum_kodu_benzersiz_olmali(): void
    {
        $school = School::first();

        $this->actingAs($this->superAdmin())->post('/schools', [
            'name' => 'Başka Okul',
            'kurum_kodu' => $school->kurum_kodu,
        ])->assertSessionHasErrors('kurum_kodu');
    }

    public function test_kullanilan_okul_silinemez(): void
    {
        $school = School::first();
        AcademicYear::create(['school_id' => $school->id, 'name' => '2026-2027']);

        $this->actingAs($this->superAdmin())->delete("/schools/{$school->id}")
            ->assertSessionHasErrors('school');

        $this->assertDatabaseHas('schools', ['id' => $school->id]);
    }

    public function test_okul_yoneticisi_yonetim_sayfalarina_giremez(): void
    {
        $user = User::factory()->create(['role' => 'school_admin']);

        $this->actingAs($user)->get('/schools')->assertForbidden();
        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_super_admin_kullanici_ekler_ve_okul_atar(): void
    {
        $school = School::first();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Okul Müdürü',
            'email' => 'mudur@ornek.tr',
            'password' => 'gizli-sifre-1',
            'role' => 'school_admin',
            'school_ids' => [$school->id],
        ])->assertRedirect();

        $created = User::where('email', 'mudur@ornek.tr')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->schools()->where('schools.id', $school->id)->exists());
    }

    public function test_kullanici_kendini_silemez_ve_yetkisini_dusuremez(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->delete("/users/{$admin->id}")
            ->assertSessionHasErrors('user');

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'school_admin',
            'school_ids' => [School::first()->id],
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }
}
