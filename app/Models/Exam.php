<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exam_week_id', 'name', 'exam_date', 'start_time', 'description'])]
class Exam extends Model
{
    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
        ];
    }

    public function examWeek(): BelongsTo
    {
        return $this->belongsTo(ExamWeek::class);
    }
}
