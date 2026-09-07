<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(): Response
    {
        $rooms = Room::withCount([
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

        Room::create([
            'name' => trim($data['name']),
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Salon eklendi.');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
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
        $room->update(['is_active' => true]);

        return back()->with('success', $room->name.' aktif edildi.');
    }

    public function deactivate(Room $room): RedirectResponse
    {
        $room->update(['is_active' => false]);

        return back()->with('success', $room->name.' pasife alındı.');
    }
}
