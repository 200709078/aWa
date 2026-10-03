<?php

namespace App\Http\Controllers;

use App\Models\Graduate;
use App\Models\Person;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Support\SchoolScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->input('tab');
        $tab = in_array($tab, ['students', 'teachers', 'graduates'], true) ? $tab : 'students';
        $search = trim((string) $request->input('q', ''));

        $trashedStudents = Student::onlyTrashed()->where('school_id', SchoolScope::id())->count();
        $trashedTeachers = Teacher::onlyTrashed()->whereHas('person', fn ($q) => $q->withTrashed()->where('school_id', SchoolScope::id()))->count();
        $trashedGraduates = Graduate::onlyTrashed()->count();

        $emptyStudents = ['data' => [], 'total' => 0];
        $emptyTeachers = ['data' => [], 'total' => 0];
        $emptyGraduates = ['data' => [], 'total' => 0];

        if ($tab === 'teachers') {
            $query = Teacher::onlyTrashed()
                ->whereHas('person', fn ($q) => $q->withTrashed()->where('school_id', SchoolScope::id()))
                ->with(['person' => fn ($q) => $q->withTrashed()]);

            if ($search !== '') {
                $query->whereHas('person', fn ($q) => $q->withTrashed()->where(function ($qq) use ($search) {
                    $qq->where('full_name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
                }));
            }

            $paginator = $query->orderByDesc('deleted_at')->paginate(30)->withQueryString();
            $paginator->setCollection($paginator->getCollection()->map(fn (Teacher $t) => [
                'id' => $t->id,
                'full_name' => $t->person?->full_name ?? '—',
                'phone' => $t->person?->phone,
                'photo_path' => $t->person?->photo_path,
                'duty' => $t->duty,
                'branch' => $t->branch,
                'deleted_at' => $t->deleted_at?->format('d.m.Y H:i'),
            ]));

            return Inertia::render('Archive/Index', [
                'tab' => $tab,
                'search' => $search,
                'students' => $emptyStudents,
                'teachers' => $paginator,
                'graduates' => $emptyGraduates,
                'trashedStudents' => $trashedStudents,
                'trashedTeachers' => $trashedTeachers,
                'trashedGraduates' => $trashedGraduates,
            ]);
        }

        if ($tab === 'graduates') {
            $query = Graduate::onlyTrashed()->with(['person' => fn ($q) => $q->withTrashed()]);

            if ($search !== '') {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('person', fn ($q) => $q->withTrashed()->where('full_name', 'like', "%{$search}%"))
                        ->orWhere('graduation_number', 'like', "%{$search}%");
                });
            }

            $paginator = $query->orderByDesc('deleted_at')->paginate(30)->withQueryString();
            $paginator->setCollection($paginator->getCollection()->map(fn (Graduate $g) => [
                'id' => $g->id,
                'year' => $g->graduation_year,
                'number' => $g->graduation_number,
                'full_name' => $g->person?->full_name ?? '—',
                'phone' => $g->person?->phone,
                'photo_path' => $g->person?->photo_path,
                'deleted_at' => $g->deleted_at?->format('d.m.Y H:i'),
            ]));

            return Inertia::render('Archive/Index', [
                'tab' => $tab,
                'search' => $search,
                'students' => ['data' => [], 'total' => 0],
                'teachers' => ['data' => [], 'total' => 0],
                'graduates' => $paginator,
                'trashedStudents' => $trashedStudents,
                'trashedTeachers' => $trashedTeachers,
                'trashedGraduates' => $trashedGraduates,
            ]);
        }

        $query = Student::onlyTrashed()
            ->where('students.school_id', SchoolScope::id())
            ->with([
                'person' => fn ($q) => $q->withTrashed(),
                'enrollments' => fn ($q) => $q->withTrashed()->with(['branch:id,name', 'academicYear:id,name']),
            ]);

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->whereHas('person', fn ($q) => $q->withTrashed()->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('enrollments', fn ($q) => $q->withTrashed()->where('school_number', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->orderByDesc('deleted_at')->paginate(30)->withQueryString();
        $paginator->setCollection($paginator->getCollection()->map(fn (Student $s) => [
            'id' => $s->id,
            'full_name' => $s->person?->full_name ?? '—',
            'photo_path' => $s->person?->photo_path,
            'deleted_at' => $s->deleted_at?->format('d.m.Y H:i'),
            'enrollments' => $s->enrollments->map(fn ($e) => [
                'id' => $e->id,
                'year' => $e->academicYear?->name ?? '—',
                'academic_year_id' => $e->academic_year_id,
                'branch' => $e->branch?->name ?? '—',
                'school_number' => $e->school_number,
            ])->values()->all(),
        ]));

        return Inertia::render('Archive/Index', [
            'tab' => $tab,
            'search' => $search,
            'students' => $paginator,
            'teachers' => ['data' => [], 'total' => 0],
            'graduates' => ['data' => [], 'total' => 0],
            'trashedStudents' => $trashedStudents,
            'trashedTeachers' => $trashedTeachers,
            'trashedGraduates' => $trashedGraduates,
        ]);
    }

    public function restoreStudent(Request $request, int $id): RedirectResponse
    {
        $student = Student::onlyTrashed()->findOrFail($id);
        SchoolScope::ensure($student);

        $enrollments = $student->enrollments()->withTrashed()->get();
        $numbers = $request->input('numbers', []);
        if (! is_array($numbers)) {
            $numbers = [];
        }

        $effective = [];
        foreach ($enrollments as $enrollment) {
            $raw = $numbers[$enrollment->id] ?? $numbers[(string) $enrollment->id] ?? null;
            $effective[$enrollment->id] = $raw === null || trim((string) $raw) === ''
                ? $enrollment->school_number
                : trim((string) $raw);
        }

        foreach ($enrollments as $enrollment) {
            $candidate = $effective[$enrollment->id];
            if ($candidate === '') {
                return back()->withErrors(['restore' => 'Okul numarası boş olamaz.']);
            }

            $conflict = StudentEnrollment::where('academic_year_id', $enrollment->academic_year_id)
                ->where('school_number', $candidate)
                ->whereNull('deleted_at')
                ->where('student_id', '!=', $student->id)
                ->with('student.person')
                ->first();

            if ($conflict) {
                $owner = $conflict->student?->person?->full_name ?? 'başka bir öğrenci';
                return back()->withErrors(['restore' => "{$candidate} numara {$conflict->academicYear?->name} yılında artık {$owner} kaydında. Yeni boş bir numara girerek geri alabilirsiniz."]);
            }
        }

        DB::transaction(function () use ($student, $enrollments, $effective) {
            foreach ($enrollments as $enrollment) {
                if ($enrollment->school_number !== $effective[$enrollment->id]) {
                    $enrollment->update(['school_number' => $effective[$enrollment->id]]);
                }
            }
            $student->enrollments()->withTrashed()->restore();
            $student->restore();

            $person = Person::withTrashed()->find($student->person_id);
            $person?->restore();
        });

        $name = $student->person?->full_name ?? 'Öğrenci';

        return back()->with('success', $name.' arşivden çıkarıldı.');
    }

    public function forceStudent(int $id): RedirectResponse
    {
        $student = Student::withTrashed()->findOrFail($id);
        SchoolScope::ensure($student);

        if ($student->seatingAssignments()->exists()) {
            return back()->withErrors(['student' => 'Bu öğrenci bir oturma planında kullanıldığı için kalıcı silinemez.']);
        }

        $person = Person::withTrashed()->find($student->person_id);
        $name = $person?->full_name ?? 'Öğrenci';
        $photo = $person?->photo_path;

        DB::transaction(function () use ($student, $person, $photo) {
            $student->enrollments()->withTrashed()->forceDelete();
            $student->forceDelete();

            if ($person) {
                $hasRole = Student::withTrashed()->where('person_id', $person->id)->exists()
                    || Graduate::withTrashed()->where('person_id', $person->id)->exists()
                    || $person->teacher()->exists()
                    || $person->guardian()->exists();

                if (! $hasRole) {
                    if ($photo) {
                        Storage::disk('public')->delete($photo);
                    }
                    $person->forceDelete();
                } elseif ($person->trashed()) {
                    $person->restore();
                }
            }
        });

        return back()->with('success', $name.' kalıcı olarak silindi.');
    }

    public function restoreTeacher(int $id): RedirectResponse
    {
        $teacher = Teacher::onlyTrashed()->findOrFail($id);
        abort_unless($teacher->person()->withTrashed()->first()?->school_id === SchoolScope::id(), 404);

        DB::transaction(function () use ($teacher) {
            $teacher->restore();

            $person = Person::withTrashed()->find($teacher->person_id);
            $person?->restore();
        });

        $name = $teacher->person?->full_name ?? 'Personel';

        return back()->with('success', $name.' arşivden çıkarıldı.');
    }

    public function forceTeacher(int $id): RedirectResponse
    {
        $teacher = Teacher::withTrashed()->findOrFail($id);
        abort_unless($teacher->person()->withTrashed()->first()?->school_id === SchoolScope::id(), 404);

        $person = Person::withTrashed()->find($teacher->person_id);
        $name = $person?->full_name ?? 'Personel';
        $photo = $person?->photo_path;

        DB::transaction(function () use ($teacher, $person, $photo) {
            $teacher->forceDelete();

            if ($person) {
                $hasRole = Student::withTrashed()->where('person_id', $person->id)->exists()
                    || Graduate::withTrashed()->where('person_id', $person->id)->exists()
                    || $person->teacher()->exists()
                    || $person->guardian()->exists();

                if (! $hasRole) {
                    if ($photo) {
                        Storage::disk('public')->delete($photo);
                    }
                    $person->forceDelete();
                } elseif ($person->trashed()) {
                    $person->restore();
                }
            }
        });

        return back()->with('success', $name.' kalıcı olarak silindi.');
    }

    public function restoreGraduate(Request $request, int $id): RedirectResponse
    {
        $graduate = Graduate::onlyTrashed()->findOrFail($id);

        $number = trim((string) $request->input('graduation_number', $graduate->graduation_number));
        if ($number === '') {
            return back()->withErrors(['restore' => 'Mezuniyet numarası boş olamaz.']);
        }

        $conflict = Graduate::where('graduation_year', $graduate->graduation_year)
            ->where('graduation_number', $number)
            ->whereNull('deleted_at')
            ->where('id', '!=', $graduate->id)
            ->with('person')
            ->first();

        if ($conflict) {
            $owner = $conflict->person?->full_name ?? 'başka bir mezun';
            return back()->withErrors(['restore' => "{$number} numara {$graduate->graduation_year} yılında artık {$owner} kaydında. Yeni boş bir numara girerek geri alabilirsiniz."]);
        }

        DB::transaction(function () use ($graduate, $number) {
            if ($graduate->graduation_number !== $number) {
                $graduate->update(['graduation_number' => $number]);
            }
            $graduate->restore();

            $person = Person::withTrashed()->find($graduate->person_id);
            $person?->restore();
        });

        $name = $graduate->person?->full_name ?? 'Mezun';

        return back()->with('success', $name.' arşivden çıkarıldı.');
    }

    public function forceGraduate(int $id): RedirectResponse
    {
        $graduate = Graduate::withTrashed()->findOrFail($id);

        $person = Person::withTrashed()->find($graduate->person_id);
        $name = $person?->full_name ?? 'Mezun';
        $photo = $person?->photo_path;

        DB::transaction(function () use ($graduate, $person, $photo) {
            $graduate->forceDelete();

            if ($person) {
                $hasRole = Student::withTrashed()->where('person_id', $person->id)->exists()
                    || Graduate::withTrashed()->where('person_id', $person->id)->exists()
                    || $person->teacher()->exists()
                    || $person->guardian()->exists();

                if (! $hasRole) {
                    if ($photo) {
                        Storage::disk('public')->delete($photo);
                    }
                    $person->forceDelete();
                } elseif ($person->trashed()) {
                    $person->restore();
                }
            }
        });

        return back()->with('success', $name.' kalıcı olarak silindi.');
    }
}
