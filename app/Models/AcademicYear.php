<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'name', 'is_active'])]
class AcademicYear extends Model
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

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function examWeeks(): HasMany
    {
        return $this->hasMany(ExamWeek::class);
    }

    protected static function booted(): void
    {
        static::creating(function (AcademicYear $year) {
            if (empty($year->school_id)) {
                $year->school_id = function_exists('session') && session()->has('current_school_id')
                    ? session('current_school_id')
                    : School::orderBy('id')->value('id');
            }
        });
    }
}
