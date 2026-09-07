<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_year_id', 'name', 'description', 'starts_at', 'ends_at', 'is_active'])]
class ExamWeek extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'exam_week_branches');
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'exam_week_rooms');
    }

    public function seatingPlans(): HasMany
    {
        return $this->hasMany(SeatingPlan::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
