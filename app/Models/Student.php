<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_id', 'person_id', 'is_active'])]
class Student extends Model
{
    use SoftDeletes;
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian')
            ->withPivot('relationship', 'is_primary')
            ->withTimestamps();
    }

    public function seatingAssignments(): HasMany
    {
        return $this->hasMany(SeatingAssignment::class);
    }

    /**
     * Öğrencinin verilen akademik yıldaki kaydı.
     */
    public function enrollmentForYear(int $academicYearId): ?StudentEnrollment
    {
        if ($this->relationLoaded('enrollments')) {
            return $this->enrollments->firstWhere('academic_year_id', $academicYearId);
        }

        return $this->enrollments()->where('academic_year_id', $academicYearId)->first();
    }
}
