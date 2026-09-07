<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Seat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SeatController extends Controller
{
    public function bulk(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'integer', 'min:1', 'max:50'],
            'columns' => ['required', 'integer', 'min:1', 'max:50'],
        ], [
            'rows.required' => 'Satır sayısı gerekli.',
            'rows.min' => 'Satır sayısı en az 1 olmalı.',
            'rows.max' => 'Satır sayısı en fazla 50 olabilir.',
            'columns.required' => 'Sütun sayısı gerekli.',
            'columns.min' => 'Sütun sayısı en az 1 olmalı.',
            'columns.max' => 'Sütun sayısı en fazla 50 olabilir.',
        ]);

        $added = 0;
        for ($row = 1; $row <= $data['rows']; $row++) {
            for ($column = 1; $column <= $data['columns']; $column++) {
                $seat = Seat::firstOrCreate(
                    ['room_id' => $room->id, 'row' => $row, 'column' => $column],
                    ['is_active' => true]
                );
                if ($seat->wasRecentlyCreated) {
                    $added++;
                }
            }
        }

        $total = $data['rows'] * $data['columns'];

        return back()->with('success', "{$added} koltuk eklendi (".($total - $added).' zaten vardı).');
    }

    public function store(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'row' => ['required', 'integer', 'min:1', 'max:50'],
            'column' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $seat = Seat::firstOrCreate(
            ['room_id' => $room->id, 'row' => $data['row'], 'column' => $data['column']],
            ['is_active' => true]
        );

        return back()->with('success', $seat->wasRecentlyCreated ? 'Koltuk eklendi.' : 'Bu koltuk zaten var.');
    }

    public function toggle(Seat $seat): RedirectResponse
    {
        $seat->update(['is_active' => ! $seat->is_active]);

        return back()->with('success', $seat->is_active ? 'Koltuk aktif edildi.' : 'Koltuk pasife alındı.');
    }
}
