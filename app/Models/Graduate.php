<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['person_id', 'student_id', 'graduation_year', 'graduation_number'])]
class Graduate extends Model
{
    use SoftDeletes;
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
