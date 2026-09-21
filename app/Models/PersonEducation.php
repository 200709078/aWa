<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['person_id', 'institution_name', 'faculty', 'department', 'city'])]
class PersonEducation extends Model
{
    protected $table = 'person_educations';

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
