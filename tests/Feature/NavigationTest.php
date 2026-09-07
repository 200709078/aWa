<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tum_yonetim_sayfalari_korumali_ve_erisilebilir(): void
    {
        $routes = ['/', '/students', '/students/import', '/branches', '/academic-years', '/rooms', '/exam-weeks', '/distribution', '/reports', '/settings'];

        foreach ($routes as $route) {
            $this->get($route)->assertRedirect('/login');
        }

        $user = User::factory()->create();

        foreach ($routes as $route) {
            $this->actingAs($user)->get($route)->assertOk();
        }
    }
}
