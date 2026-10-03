<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['person_id', 'duty', 'branch', 'started_at', 'is_active'])]
class Teacher extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
