<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['person_id', 'company_name', 'job_title', 'city', 'start_year', 'end_year', 'is_current', 'notes'])]
class PersonEmployment extends Model
{
    protected $table = 'person_employments';

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
