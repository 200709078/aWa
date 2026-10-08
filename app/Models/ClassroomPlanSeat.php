<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['classroom_plan_id', 'row', 'column', 'student_id'])]
class ClassroomPlanSeat extends Model
{
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ClassroomPlan::class, 'classroom_plan_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
