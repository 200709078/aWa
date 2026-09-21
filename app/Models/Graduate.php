<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['person_id', 'student_id', 'graduation_year', 'graduation_number'])]
class Graduate extends Model
{
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
