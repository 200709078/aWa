<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_salon_ekleme_ve_duzenleme(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/rooms', ['name' => 'Salon 1', 'sort_order' => 2])
            ->assertRedirect();

        $this->assertDatabaseHas('rooms', ['name' => 'Salon 1', 'is_active' => true]);

        $room = Room::first();

        $this->actingAs($user)->put("/rooms/{$room->id}", ['name' => 'Salon A', 'is_active' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'name' => 'Salon A', 'is_active' => false]);
    }

    public function test_toplu_koltuk_ekleme_ve_mukerrer_koruma(): void
    {
        $user = User::factory()->create();
        $room = Room::create(['name' => 'Salon 1']);

        $this->actingAs($user)->post("/rooms/{$room->id}/seats/bulk", ['rows' => 5, 'columns' => 6])
            ->assertRedirect();

        $this->assertEquals(30, Seat::where('room_id', $room->id)->count());

        $this->actingAs($user)->post("/rooms/{$room->id}/seats/bulk", ['rows' => 5, 'columns' => 6])
            ->assertRedirect();

        $this->assertEquals(30, Seat::where('room_id', $room->id)->count());
        $this->assertEquals(1, Seat::where('room_id', $room->id)->where('row', 1)->where('column', 1)->count());
    }

    public function test_koltuk_aktif_pasif_ve_kapasite(): void
    {
        $user = User::factory()->create();
        $room = Room::create(['name' => 'Salon 1']);
        Seat::create(['room_id' => $room->id, 'row' => 1, 'column' => 1]);
        Seat::create(['room_id' => $room->id, 'row' => 1, 'column' => 2]);
        $seat = Seat::where('column', 2)->first();

        $this->actingAs($user)->post("/seats/{$seat->id}/toggle")->assertRedirect();
        $this->assertFalse($seat->fresh()->is_active);

        $response = $this->actingAs($user)->get("/rooms/{$room->id}");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('activeCount', 1)
            ->where('seats', fn ($seats) => count($seats) === 2)
        );

        $this->actingAs($user)->post("/rooms/{$room->id}/seats", ['row' => 2, 'column' => 1])
            ->assertRedirect();
        $this->assertEquals(3, Seat::where('room_id', $room->id)->count());
    }
}
