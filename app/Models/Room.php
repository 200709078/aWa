<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'name', 'description', 'is_active', 'sort_order'])]
class Room extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Room $room) {
            if (empty($room->school_id)) {
                $room->school_id = function_exists('session') && session()->has('current_school_id')
                    ? session('current_school_id')
                    : School::orderBy('id')->value('id');
            }
        });

        static::created(function (Room $room) {
            foreach (range(1, 5) as $row) {
                foreach (range(1, 6) as $column) {
                    Seat::firstOrCreate(
                        ['room_id' => $room->id, 'row' => $row, 'column' => $column],
                        ['is_active' => true]
                    );
                }
            }
        });
    }
}
