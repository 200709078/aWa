<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Student;
use App\Support\SchoolScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $students = Student::with('branch:id,name')
            ->withCount('seatingAssignments')
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId), fn ($query) => $query->whereRaw('0 = 1'))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('school_number', 'like', "%{$search}%");
            }))
            ->orderBy('full_name')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Students/Index', [
            'years' => $years,
            'yearId' => $yearId,
            'branches' => $branches,
            'allBranches' => Branch::whereIn('academic_year_id', $years->pluck('id'))->orderBy('name')->get(['id', 'name']),
            'branchId' => $branchId,
            'search' => $search,
            'students' => $students,
            'totalStudents' => Student::whereIn('academic_year_id', $years->pluck('id'))->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            Student::create($this->validated($request));
        } catch (QueryException $e) {
            return back()->withErrors(['school_number' => 'Bu okul numarası bu akademik yılda zaten kayıtlı.'])->withInput();
        }

        return back()->with('success', 'Öğrenci eklendi.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        SchoolScope::ensure($student);
        try {
            $student->update($this->validated($request, $student));
        } catch (QueryException $e) {
            return back()->withErrors(['school_number' => 'Bu okul numarası bu akademik yılda zaten kayıtlı.'])->withInput();
        }

        return back()->with('success', 'Öğrenci güncellendi.');
    }

    public function activate(Student $student): RedirectResponse
    {
        SchoolScope::ensure($student);
        $student->update(['is_active' => true]);

        return back()->with('success', $student->full_name.' aktif edildi.');
    }

    public function deactivate(Student $student): RedirectResponse
    {
        SchoolScope::ensure($student);
        $student->update(['is_active' => false]);

        return back()->with('success', $student->full_name.' pasife alındı.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        SchoolScope::ensure($student);
        if ($student->seatingAssignments()->exists()) {
            return back()->withErrors(['student' => 'Bu öğrenci bir oturma planında kullanıldığı için silinemez.']);
        }

        if ($student->photo_path) {
            Storage::disk('public')->delete($student->photo_path);
        }

        $student->delete();

        return back()->with('success', $student->full_name.' silindi.');
    }

    /**
     * @return array{academic_year_id: int, branch_id: int, school_number: string, full_name: string, is_active: bool}
     */
    private function validated(Request $request, ?Student $student = null): array
    {
        $branch = Branch::findOrFail($request->input('branch_id', $student?->branch_id));

        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereIn('academic_year_id', SchoolScope::yearIds())],
            'school_number' => [
                'required', 'string', 'max:20',
                Rule::unique('students')->where(fn ($query) => $query->where('academic_year_id', $branch->academic_year_id))->ignore($student?->id),
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
            'academic_year_id' => $branch->academic_year_id,
            'branch_id' => $branch->id,
            'school_number' => trim($data['school_number']),
            'full_name' => trim($data['full_name']),
            'is_active' => $request->boolean('is_active', $student?->is_active ?? true),
        ];
    }
}
