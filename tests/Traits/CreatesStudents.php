<?php

namespace Tests\Traits;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Person;
use App\Models\Student;
use App\Models\StudentEnrollment;

trait CreatesStudents
{
    /**
     * @param  array{is_active?: bool, photo_path?: ?string}  $overrides
     */
    public function makeStudent(AcademicYear $year, Branch $branch, string $number, string $name, array $overrides = []): Student
    {
        $person = Person::create([
            'school_id' => $year->school_id,
            'full_name' => $name,
            'photo_path' => $overrides['photo_path'] ?? null,
        ]);

        $student = Student::create([
            'school_id' => $year->school_id,
            'person_id' => $person->id,
            'is_active' => $overrides['is_active'] ?? true,
        ]);

        StudentEnrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'branch_id' => $branch->id,
            'school_number' => $number,
            'status' => 'active',
        ]);

        return $student;
    }
}
