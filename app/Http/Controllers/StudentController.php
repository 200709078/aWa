<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Person;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\SchoolScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $years = AcademicYear::where('school_id', SchoolScope::id())->orderByDesc('name')->get(['id', 'name', 'is_active']);

        $yearId = $request->integer('academic_year_id')
            ?: $years->firstWhere('is_active', true)?->id
            ?? $years->first()?->id;

        if ($yearId && ! $years->contains('id', $yearId)) {
            $yearId = $years->firstWhere('is_active', true)?->id
                ?? $years->first()?->id;
        }

        $branches = $yearId
            ? Branch::where('academic_year_id', $yearId)->orderBy('name')->get(['id', 'name'])
            : [];

        $branchId = $request->integer('branch_id') ?: null;

        if ($branchId && ! $branches->contains('id', $branchId)) {
            $branchId = null;
        }
        $search = trim((string) $request->input('q', ''));

        $paginator = Student::with([
            'person:id,full_name,photo_path',
            'enrollments' => fn ($query) => $query->where('academic_year_id', $yearId)->with('branch:id,name'),
        ])
            ->withCount('seatingAssignments')
            ->join('people', 'people.id', '=', 'students.person_id')
            ->when($yearId, fn ($query) => $query->whereHas('enrollments', fn ($query) => $query
                ->where('academic_year_id', $yearId)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ), fn ($query) => $query->whereRaw('0 = 1'))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search, $yearId) {
                $query->where('people.full_name', 'like', "%{$search}%")
                    ->orWhereHas('enrollments', fn ($query) => $query
                        ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
                        ->where('school_number', 'like', "%{$search}%"));
            }))
            ->orderBy('people.full_name')
            ->select('students.*')
            ->paginate(30)
            ->withQueryString();

        $paginator->setCollection($paginator->getCollection()->map(fn (Student $student) => [
            'id' => $student->id,
            'school_number' => $student->enrollments->first()?->school_number ?? '—',
            'full_name' => $student->person?->full_name ?? '—',
            'photo_path' => $student->person?->photo_path,
            'is_active' => $student->is_active,
            'seating_assignments_count' => $student->seating_assignments_count,
            'branch' => [
                'id' => $student->enrollments->first()?->branch?->id,
                'name' => $student->enrollments->first()?->branch?->name ?? '—',
            ],
        ]));

        return Inertia::render('Students/Index', [
            'years' => $years,
            'yearId' => $yearId,
            'branches' => $branches,
            'allBranches' => Branch::whereIn('academic_year_id', $years->pluck('id'))->orderBy('name')->get(['id', 'name']),
            'branchId' => $branchId,
            'search' => $search,
            'students' => $paginator,
            'totalStudents' => Student::whereHas('enrollments', fn ($query) => $query->whereIn('academic_year_id', $years->pluck('id')))->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            DB::transaction(function () use ($validated) {
                $schoolId = $validated['branch']->academicYear->school_id;

                $person = Person::create([
                    'school_id' => $schoolId,
                    'full_name' => $validated['full_name'],
                ]);

                $student = Student::create([
                    'school_id' => $schoolId,
                    'person_id' => $person->id,
                    'is_active' => $validated['is_active'],
                ]);

                StudentEnrollment::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $validated['branch']->academic_year_id,
                    'branch_id' => $validated['branch']->id,
                    'school_number' => $validated['school_number'],
                    'status' => 'active',
                ]);
            });
        } catch (QueryException $e) {
            return back()->withErrors(['school_number' => 'Bu okul numarası bu akademik yılda zaten kayıtlı.'])->withInput();
        }

        return back()->with('success', 'Öğrenci eklendi.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        SchoolScope::ensure($student);
        $validated = $this->validated($request, $student);

        try {
            DB::transaction(function () use ($student, $validated) {
                if ($validated['enrollment']) {
                    $validated['enrollment']->update([
                        'branch_id' => $validated['branch']->id,
                        'school_number' => $validated['school_number'],
                    ]);
                } else {
                    StudentEnrollment::create([
                        'student_id' => $student->id,
                        'academic_year_id' => $validated['branch']->academic_year_id,
                        'branch_id' => $validated['branch']->id,
                        'school_number' => $validated['school_number'],
                        'status' => 'active',
                    ]);
                }

                $student->person?->update(['full_name' => $validated['full_name']]);
                $student->update(['is_active' => $validated['is_active']]);
            });
        } catch (QueryException $e) {
            return back()->withErrors(['school_number' => 'Bu okul numarası bu akademik yılda zaten kayıtlı.'])->withInput();
        }

        return back()->with('success', 'Öğrenci güncellendi.');
    }

    public function activate(Student $student): RedirectResponse
    {
        SchoolScope::ensure($student);
        $student->update(['is_active' => true]);

        return back()->with('success', $student->person?->full_name.' aktif edildi.');
    }

    public function deactivate(Student $student): RedirectResponse
    {
        SchoolScope::ensure($student);
        $student->update(['is_active' => false]);

        return back()->with('success', $student->person?->full_name.' pasife alındı.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        SchoolScope::ensure($student);
        if ($student->seatingAssignments()->exists()) {
            return back()->withErrors(['student' => 'Bu öğrenci bir oturma planında kullanıldığı için silinemez.']);
        }

        $name = $student->person?->full_name ?? 'Öğrenci';

        if ($student->person?->photo_path) {
            Storage::disk('public')->delete($student->person->photo_path);
            $student->person->update(['photo_path' => null]);
        }

        $student->delete();

        return back()->with('success', $name.' silindi.');
    }

    /**
     * @return array{branch: Branch, enrollment: ?StudentEnrollment, school_number: string, full_name: string, is_active: bool}
     */
    private function validated(Request $request, ?Student $student = null): array
    {
        $branch = Branch::findOrFail($request->input('branch_id', $student?->enrollments()->first()?->branch_id));

        $enrollment = $student?->enrollmentForYear($branch->academic_year_id);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereIn('academic_year_id', SchoolScope::yearIds())],
            'school_number' => [
                'required', 'string', 'max:20',
                Rule::unique('student_enrollments')->where(fn ($query) => $query->where('academic_year_id', $branch->academic_year_id))->ignore($enrollment?->id),
            ],
            'full_name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'branch_id.required' => 'Şube seçin.',
            'branch_id.exists' => 'Seçilen şube bulunamadı.',
            'school_number.required' => 'Okul numarası gerekli.',
            'school_number.unique' => 'Bu okul numarası bu akademik yılda zaten kayıtlı.',
            'full_name.required' => 'Ad soyad gerekli.',
        ]);

        return [
            'branch' => $branch,
            'enrollment' => $enrollment,
            'school_number' => trim($data['school_number']),
            'full_name' => trim($data['full_name']),
            'is_active' => $request->boolean('is_active', $student?->is_active ?? true),
        ];
    }
}
