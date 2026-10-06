<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'earthquake_loss', 'family_income', 'transport', 'free_lunch', 'martyr_child', 'preschool', 'medication', 'medical_device', 'hobbies', 'moved', 'changed_school', 'extracurricular', 'tech_devices', 'trauma', 'sibling_count', 'birth_order', 'school_siblings', 'family_disability', 'family_illness', 'household', 'notes'])]
class StudentInfoForm extends Model
{
    protected function casts(): array
    {
        return [
            'sibling_count' => 'integer',
            'birth_order' => 'integer',
            'school_siblings' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
