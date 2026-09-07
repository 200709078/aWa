<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_misafir_korumali_sayfaya_erisemez(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/')->assertRedirect('/login');
    }

    public function test_giris_yapan_kullanici_ana_sayfayi_acar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk();
    }

    public function test_giris_ve_cikis_akisi_calisir(): void
    {
        $user = User::factory()->create(['password' => '123456']);

        $this->post('/login', ['email' => $user->email, 'password' => '123456'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_hatali_bilgiyle_giris_basarisiz_olur(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'yanlis-sifre'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
