<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Person;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\PhotoService;
use App\Support\SchoolScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            : collect();

        $branchId = $request->integer('branch_id') ?: null;

        if ($branchId && ! $branches->contains('id', $branchId)) {
            $branchId = null;
        }
        $search = trim((string) $request->input('q', ''));

        $baseQuery = fn () => Student::with([
            'person:id,first_name,last_name,full_name,phone,email,address,photo_path',
            'enrollments' => fn ($query) => $query->where('academic_year_id', $yearId)->with('branch:id,name'),
            'guardians.person:id,first_name,last_name,full_name,phone',
        ])
            ->withCount('seatingAssignments')
            ->join('people', 'people.id', '=', 'students.person_id')
            ->select('students.*');

        $flatten = fn (Student $student) => [
            'id' => $student->id,
            'school_number' => $student->enrollments->first()?->school_number ?? '—',
            'first_name' => $student->person?->first_name,
            'last_name' => $student->person?->last_name,
            'full_name' => $student->person?->full_name ?? '—',
            'phone' => $student->person?->phone,
            'email' => $student->person?->email,
            'address' => $student->person?->address,
            'photo_path' => $student->person?->photo_path,
            'is_active' => $student->is_active,
            'seating_assignments_count' => $student->seating_assignments_count,
            'branch' => [
                'id' => $student->enrollments->first()?->branch?->id,
                'name' => $student->enrollments->first()?->branch?->name ?? '—',
            ],
            'guardians' => $student->guardians->map(fn ($guardian) => [
                'id' => $guardian->id,
                'relation' => $guardian->pivot->relationship,
                'first_name' => $guardian->person?->first_name,
                'last_name' => $guardian->person?->last_name,
                'phone' => $guardian->person?->phone,
                'is_primary' => (bool) $guardian->pivot->is_primary,
            ])->values()->all(),
        ];

        // Arama varsa yıllara bakılmaksızın düz liste; yoksa her sayfa bir sınıf.
        if ($search !== '') {
            $paginator = $baseQuery()
                ->when($yearId, fn ($query) => $query->whereHas('enrollments', fn ($query) => $query->where('academic_year_id', $yearId)),
                    fn ($query) => $query->whereRaw('0 = 1'))
                ->where(function ($query) use ($search, $yearId) {
                    $query->where('people.full_name', 'like', "%{$search}%")
                        ->orWhereHas('enrollments', fn ($query) => $query
                            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
                            ->where('school_number', 'like', "%{$search}%"));
                })
                ->orderBy('people.full_name')
                ->paginate(30)
                ->withQueryString();

            $paginator->setCollection($paginator->getCollection()->map($flatten));

            return Inertia::render('Students/Index', $this->indexProps($years, $yearId, $branches, $branchId, $search, $paginator, 1));
        }

        if ($branchId) {
            $page = max(1, $branches->search(fn ($branch) => $branch->id === $branchId) + 1);
        } else {
            $page = $request->integer('page', 1);
        }
        $page = min(max(1, $page), max(1, $branches->count()));
        $branch = $branches->get($page - 1);
        $branchId = $branch?->id;

        $rows = $branch
            ? $baseQuery()
                ->whereHas('enrollments', fn ($query) => $query
                    ->where('academic_year_id', $yearId)
                    ->where('branch_id', $branch->id))
                ->get()
                ->sort(fn (Student $a, Student $b) => strnatcmp(
                    $a->enrollments->first()?->school_number ?? '',
                    $b->enrollments->first()?->school_number ?? ''
                ))
                ->values()
            : collect();

        $data = $rows->map($flatten)->all();
        $total = count($data);

        $students = [
            'data' => $data,
            'from' => $total > 0 ? 1 : null,
            'to' => $total,
            'total' => $total,
            'prev_page_url' => $this->studentPageUrl($request, $yearId, $page - 1, $page > 1),
            'next_page_url' => $this->studentPageUrl($request, $yearId, $page + 1, $page < $branches->count()),
        ];

        return Inertia::render('Students/Index', $this->indexProps($years, $yearId, $branches, $branchId, $search, $students, $page));
    }

    private function studentPageUrl(Request $request, ?int $yearId, int $page, bool $enabled): ?string
    {
        if (! $enabled) {
            return null;
        }

        $params = array_filter([
            'academic_year_id' => $yearId ?? $request->integer('academic_year_id') ?: null,
            'page' => $page,
        ]);

        return '/students?'.http_build_query($params);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $branches
     */
    private function indexProps($years, $yearId, $branches, $branchId, string $search, $students, int $page): array
    {
        return [
            'years' => $years,
            'yearId' => $yearId,
            'branches' => $branches,
            'allBranches' => Branch::whereIn('academic_year_id', $years->pluck('id'))->orderBy('name')->get(['id', 'name']),
            'branchId' => $branchId,
            'page' => $page,
            'search' => $search,
            'students' => $students,
            'totalStudents' => Student::whereHas('enrollments', fn ($query) => $query->whereIn('academic_year_id', $years->pluck('id')))->count(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            DB::transaction(function () use ($validated) {
                $schoolId = $validated['branch']->academicYear->school_id;

                $person = Person::create([
                    'school_id' => $schoolId,
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'full_name' => $validated['full_name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                    'address' => $validated['address'],
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

                $this->syncGuardians($student, $validated['guardians'], $schoolId);

                if ($validated['photo']) {
                    $this->storePersonPhoto($person, $student, $validated['photo']);
                }
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

                $student->person?->update([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'full_name' => $validated['full_name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                    'address' => $validated['address'],
                ]);
                $student->update(['is_active' => $validated['is_active']]);
                $this->syncGuardians($student, $validated['guardians'], $student->school_id);

                if ($validated['photo'] && $student->person) {
                    $this->storePersonPhoto($student->person, $student, $validated['photo']);
                }
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
            return back()->withErrors(['student' => 'Bu öğrenci bir oturma planında kullanıldığı için arşive gönderilemez.']);
        }

        $name = $student->person?->full_name ?? 'Öğrenci';
        $person = $student->person;

        DB::transaction(function () use ($student, $person) {
            // Fotoğraf dosyası arşivde saklanır, silinmez.
            $student->enrollments()->delete();
            $student->delete();

            // Kişinin başka aktif rolü yoksa kişi de arşivlenir.
            if ($person) {
                $hasRole = $person->student()->exists()
                    || $person->graduate()->exists()
                    || $person->teacher()->exists()
                    || $person->guardian()->exists();
                if (! $hasRole) {
                    $person->delete();
                }
            }
        });

        return back()->with('success', $name.' arşive gönderildi.');
    }

    /**
     * @return array{branch: Branch, enrollment: ?StudentEnrollment, school_number: string, first_name: string, last_name: string, full_name: string, phone: ?string, email: ?string, address: ?string, photo: ?UploadedFile, is_active: bool, guardians: array<int, array{id: ?int, relation: ?string, first_name: string, last_name: string, phone: ?string, is_primary: bool}>}
     */
    private function validated(Request $request, ?Student $student = null): array
    {
        $branch = Branch::findOrFail($request->input('branch_id', $student?->enrollments()->first()?->branch_id));

        $enrollment = $student?->enrollmentForYear($branch->academic_year_id);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereIn('academic_year_id', SchoolScope::yearIds())],
            'school_number' => [
                'required', 'string', 'max:20',
                Rule::unique('student_enrollments', 'school_number')
                    ->where(fn ($query) => $query->where('academic_year_id', $branch->academic_year_id)->whereNull('deleted_at'))
                    ->ignore($enrollment?->id),
            ],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'is_active' => ['sometimes', 'boolean'],
            'guardians' => ['nullable', 'array', 'max:4'],
            'guardians.*.id' => ['nullable', 'integer', 'exists:guardians,id'],
            'guardians.*.relation' => ['nullable', Rule::in(['anne', 'baba', 'veli', 'vasi'])],
            'guardians.*.first_name' => ['nullable', 'string', 'max:50'],
            'guardians.*.last_name' => ['nullable', 'string', 'max:50'],
            'guardians.*.phone' => ['nullable', 'string', 'max:30'],
            'guardians.*.is_primary' => ['sometimes', 'boolean'],
        ], [
            'branch_id.required' => 'Şube seçin.',
            'branch_id.exists' => 'Seçilen şube bulunamadı.',
            'school_number.required' => 'Okul numarası gerekli.',
            'school_number.unique' => 'Bu okul numarası bu akademik yılda zaten kayıtlı.',
            'first_name.required' => 'Ad gerekli.',
            'last_name.required' => 'Soyad gerekli.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'photo.image' => 'Yalnızca resim dosyası yükleyin.',
            'photo.mimes' => 'Desteklenen formatlar: jpg, jpeg, png, webp.',
            'photo.max' => 'Fotoğraf en fazla 10 MB olabilir.',
            'photo.uploaded' => 'Fotoğraf yüklenemedi, dosya çok büyük olabilir.',
            'guardians.*.relation.in' => 'Veli yakınlığı geçersiz.',
        ]);

        $guardians = [];
        foreach ($data['guardians'] ?? [] as $row) {
            $first = trim((string) ($row['first_name'] ?? ''));
            $last = trim((string) ($row['last_name'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));

            if ($first === '' && $last === '' && $phone === '') {
                continue;
            }
            if ($first === '' || $last === '') {
                throw ValidationException::withMessages(['guardians' => 'Veli adı ve soyadı gerekli.']);
            }
            $guardians[] = [
                'id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null,
                'relation' => $row['relation'] ?? null,
                'first_name' => $first,
                'last_name' => $last,
                'phone' => $phone === '' ? null : $phone,
                'is_primary' => ! empty($row['is_primary']),
            ];
        }

        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);

        $nullify = fn ($value) => trim((string) $value) === '' ? null : trim((string) $value);

        return [
            'branch' => $branch,
            'enrollment' => $enrollment,
            'school_number' => trim($data['school_number']),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $firstName.' '.$lastName,
            'phone' => $nullify($data['phone'] ?? null),
            'email' => $nullify($data['email'] ?? null),
            'address' => $nullify($data['address'] ?? null),
            'photo' => $request->file('photo'),
            'is_active' => $request->boolean('is_active', $student?->is_active ?? true),
            'guardians' => $guardians,
        ];
    }

    /**
     * @param  array<int, array{id: ?int, relation: ?string, first_name: string, last_name: string, phone: ?string, is_primary: bool}>  $guardians
     */
    private function syncGuardians(Student $student, array $guardians, mixed $schoolId): void
    {
        $kept = [];
        $primaryId = null;

        foreach ($guardians as $row) {
            $guardian = $row['id'] ? Guardian::find($row['id']) : null;

            if ($guardian && ! $student->guardians()->whereKey($guardian->id)->exists()) {
                $student->guardians()->attach($guardian->id, [
                    'relationship' => $row['relation'] ?? 'veli',
                    'is_primary' => false,
                ]);
            }

            if (! $guardian) {
                [$gFirst, $gLast] = StudentImportController::splitName($row['first_name'].' '.$row['last_name']);
                $person = Person::create([
                    'school_id' => $schoolId,
                    'first_name' => $gFirst ?? $row['first_name'],
                    'last_name' => $gLast ?? $row['last_name'],
                    'full_name' => $row['first_name'].' '.$row['last_name'],
                    'phone' => $row['phone'],
                ]);
                $guardian = Guardian::create(['person_id' => $person->id]);
                $student->guardians()->attach($guardian->id, [
                    'relationship' => $row['relation'] ?? 'veli',
                    'is_primary' => false,
                ]);
            } else {
                $guardian->person?->update([
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'full_name' => $row['first_name'].' '.$row['last_name'],
                    'phone' => $row['phone'],
                ]);
                $student->guardians()->updateExistingPivot($guardian->id, [
                    'relationship' => $row['relation'] ?? $guardian->pivot->relationship ?? 'veli',
                ]);
            }

            $kept[] = $guardian->id;
            if ($primaryId === null && $row['is_primary']) {
                $primaryId = $guardian->id;
            }
        }

        if ($primaryId === null && $kept !== []) {
            $primaryId = $kept[0];
        }

        foreach ($kept as $id) {
            $student->guardians()->updateExistingPivot($id, ['is_primary' => $id === $primaryId]);
        }

        $removed = $student->guardians()->whereNotIn('guardians.id', $kept)->get();
        foreach ($removed as $guardian) {
            $student->guardians()->detach($guardian->id);
            $this->cleanupGuardian($guardian);
        }
    }

    private function cleanupGuardian(Guardian $guardian): void
    {
        if ($guardian->students()->exists()) {
            return;
        }

        $person = $guardian->person;
        $guardian->delete();

        if ($person && ! $person->student()->exists() && ! $person->graduate()->exists()
            && ! $person->teacher()->exists() && ! $person->guardian()->exists()) {
            $person->delete();
        }
    }

    private function storePersonPhoto(Person $person, Student $student, UploadedFile $photo): void
    {
        PhotoService::store($photo, "students/{$student->id}.jpg");

        $person->update(['photo_path' => "students/{$student->id}.jpg"]);
    }
}
