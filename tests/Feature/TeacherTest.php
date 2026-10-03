<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherTest extends TestCase
{
    use RefreshDatabase;

    public function test_personel_ekleme_guncelleme_arsiv_geri_al_kalici_sil(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/personel', [
            'first_name' => 'Ayşe',
            'last_name' => 'Öğretmen',
            'phone' => '05320000000',
            'email' => 'ayse@example.com',
            'duty' => 'Öğretmen',
            'branch' => 'Matematik',
            'started_at' => '2010-09-01',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $teacher = Teacher::first();
        $this->assertDatabaseHas('people', ['full_name' => 'Ayşe Öğretmen']);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'duty' => 'Öğretmen', 'branch' => 'Matematik']);

        $this->actingAs($user)->get('/personel')->assertOk()
            ->assertInertia(fn ($page) => $page->where('totalTeachers', 1));

        $this->actingAs($user)->put("/personel/{$teacher->id}", [
            'first_name' => 'Ayşe',
            'last_name' => 'Öğretmen',
            'duty' => 'Müdür Yardımcısı',
            'branch' => 'Matematik',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'duty' => 'Müdür Yardımcısı']);

        // Arşive gönder: listeden gizlenir, arşivde görünür.
        $this->actingAs($user)->delete("/personel/{$teacher->id}")->assertRedirect();
        $this->assertSoftDeleted('teachers', ['id' => $teacher->id]);
        $this->actingAs($user)->get('/personel')->assertInertia(fn ($page) => $page->where('totalTeachers', 0));
        $this->actingAs($user)->get('/arsiv?tab=teachers')->assertOk()
            ->assertInertia(fn ($page) => $page->where('trashedTeachers', 1));

        // Geri al.
        $this->actingAs($user)->post("/arsiv/personel/{$teacher->id}/restore")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse(Teacher::onlyTrashed()->whereKey($teacher->id)->exists());

        // Kalıcı sil.
        $personId = $teacher->person_id;
        $this->actingAs($user)->delete("/personel/{$teacher->id}")->assertRedirect();
        $this->actingAs($user)->delete("/arsiv/personel/{$teacher->id}")->assertRedirect();
        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseMissing('people', ['id' => $personId]);
    }
}
