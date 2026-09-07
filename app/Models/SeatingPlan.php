<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['exam_week_id', 'name', 'status', 'total_students', 'used_room_count', 'notes', 'created_by'])]
class SeatingPlan extends Model
{
    public function examWeek(): BelongsTo
    {
        return $this->belongsTo(ExamWeek::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SeatingAssignment::class);
    }
}
