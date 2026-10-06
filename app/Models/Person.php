<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_id', 'first_name', 'last_name', 'full_name', 'phone', 'email', 'address', 'photo_path', 'gender', 'birth_place', 'birth_date', 'blood_type', 'religion', 'height_cm', 'weight_kg', 'education_level', 'occupation', 'is_alive', 'disability', 'chronic_illness'])]
class Person extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'height_cm' => 'integer',
            'weight_kg' => 'integer',
            'is_alive' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function graduate(): HasOne
    {
        return $this->hasOne(Graduate::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(PersonEducation::class);
    }

    public function employments(): HasMany
    {
        return $this->hasMany(PersonEmployment::class);
    }
}
