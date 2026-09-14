<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Exam;
use App\Models\ExamWeek;
use App\Models\Room;
use App\Models\SeatingPlan;
use App\Models\Seat;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Oturumdaki okul bağlamı ve okul kapsamı denetimleri.
 */
final class SchoolScope
{
    public static function id(): ?int
    {
        $id = session('current_school_id');

        return $id ? (int) $id : null;
    }

    /**
     * @return Collection<int, int>
     */
    public static function yearIds(): Collection
    {
        return AcademicYear::where('school_id', static::id())->pluck('id');
    }

    public static function ensure(Model $model): void
    {
        $schoolId = static::id();

        $ok = match (true) {
            $model instanceof AcademicYear => $model->school_id === $schoolId,
            $model instanceof Room => $model->school_id === $schoolId,
            $model instanceof Branch => $model->academicYear?->school_id === $schoolId,
            $model instanceof Student => $model->academic_year_id && AcademicYear::where('id', $model->academic_year_id)->where('school_id', $schoolId)->exists(),
            $model instanceof ExamWeek => $model->academic_year_id && AcademicYear::where('id', $model->academic_year_id)->where('school_id', $schoolId)->exists(),
            $model instanceof Exam => $model->examWeek && self::inSchoolWeek($model->examWeek, $schoolId),
            $model instanceof SeatingPlan => $model->examWeek && self::inSchoolWeek($model->examWeek, $schoolId),
            $model instanceof Seat => $model->room?->school_id === $schoolId,
            default => false,
        };

        abort_unless($ok, 404);
    }

    private static function inSchoolWeek(ExamWeek $week, ?int $schoolId): bool
    {
        return $week->academic_year_id
            && AcademicYear::where('id', $week->academic_year_id)->where('school_id', $schoolId)->exists();
    }
}
