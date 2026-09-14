<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Support\SchoolScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(): Response
    {
        $rooms = Room::where('school_id', SchoolScope::id())->withCount([
            'seats',
            'seats as active_seats_count' => fn ($query) => $query->where('is_active', true),
        ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Rooms/Index', [
            'rooms' => $rooms,
        ]);
    }

    public function show(Room $room): Response
    {
        SchoolScope::ensure($room);
        $seats = $room->seats()->orderBy('row')->orderBy('column')->get();

        return Inertia::render('Rooms/Show', [
            'room' => $room,
            'seats' => $seats,
            'maxRow' => $seats->max('row') ?? 0,
            'maxColumn' => $seats->max('column') ?? 0,
            'activeCount' => $seats->where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Salon adı gerekli.',
        ]);

        $room = Room::create([
            'name' => trim($data['name']),
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Salon eklendi (5x6 koltuk oluşturuldu).');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        SchoolScope::ensure($room);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Salon adı gerekli.',
        ]);

        $room->update([
            'name' => trim($data['name']),
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', $room->is_active),
        ]);

        return back()->with('success', 'Salon güncellendi.');
    }

    public function activate(Room $room): RedirectResponse
    {
        SchoolScope::ensure($room);
        $room->update(['is_active' => true]);

        return back()->with('success', $room->name.' aktif edildi.');
    }

    public function deactivate(Room $room): RedirectResponse
    {
        SchoolScope::ensure($room);
        $room->update(['is_active' => false]);

        return back()->with('success', $room->name.' pasife alındı.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        SchoolScope::ensure($room);
        // Koltuklar, sınav haftası seçimleri ve dağıtım planlarındaki
        // bu salona ait atamalar FK cascade ile birlikte silinir.
        $room->delete();

        return back()->with('success', 'Salon silindi.');
    }
}
