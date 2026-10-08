<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassroomPlan;
use App\Models\ClassroomPlanSeat;
use App\Models\Student;
use App\Support\SchoolScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomPlanController extends Controller
{
    private const DEFAULT_ROWS = 6;
    private const DEFAULT_COLUMNS = 6;

    public function index(): Response
    {
        $schoolId = SchoolScope::id();
        $years = AcademicYear::where('school_id', $schoolId)->orderByDesc('name')->get(['id', 'name', 'is_active']);

        $yearId = request()->integer('academic_year_id')
            ?: $years->firstWhere('is_active', true)?->id
            ?? $years->first()?->id;

        $branches = $yearId
            ? Branch::where('academic_year_id', $yearId)->orderBy('name')->get(['id', 'name', 'is_active'])
            : collect();

        $plans = $yearId
            ? ClassroomPlan::where('academic_year_id', $yearId)->withCount('seats')->get()->keyBy('branch_id')
            : collect();

        $rows = $branches->map(fn (Branch $branch) => [
            'id' => $branch->id,
            'name' => $branch->name,
            'is_active' => $branch->is_active,
            'student_count' => $this->roster($yearId, $branch->id)->count(),
            'plan_id' => $plans->get($branch->id)?->id,
            'seat_count' => $plans->get($branch->id)?->seats_count ?? 0,
            'seated_count' => $plans->get($branch->id)
                ? ClassroomPlanSeat::where('classroom_plan_id', $plans->get($branch->id)->id)->whereNotNull('student_id')->count()
                : 0,
        ])->all();

        return Inertia::render('ClassroomPlans/Index', [
            'years' => $years,
            'selectedYearId' => $yearId,
            'branches' => $rows,
        ]);
    }

    public function store(): RedirectResponse
    {
        $data = request()->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ], [
            'branch_id.required' => 'Sınıf seçin.',
            'branch_id.exists' => 'Seçilen sınıf bulunamadı.',
        ]);

        $branch = Branch::findOrFail($data['branch_id']);
        SchoolScope::ensure($branch);
        $yearId = (int) $branch->academic_year_id;

        if (ClassroomPlan::where('academic_year_id', $yearId)->where('branch_id', $branch->id)->exists()) {
            return back()->withErrors(['branch_id' => 'Bu sınıfın oturma planı zaten var.']);
        }

        $plan = DB::transaction(function () use ($branch, $yearId) {
            $plan = ClassroomPlan::create([
                'school_id' => SchoolScope::id(),
                'academic_year_id' => $yearId,
                'branch_id' => $branch->id,
            ]);

            $seats = [];
            foreach (range(1, self::DEFAULT_ROWS) as $row) {
                foreach (range(1, self::DEFAULT_COLUMNS) as $column) {
                    $seats[] = new ClassroomPlanSeat(['row' => $row, 'column' => $column]);
                }
            }
            $plan->seats()->saveMany($seats);

            $this->placeStudents($plan, $this->roster($yearId, $branch->id));

            return $plan;
        });

        return redirect("/oturme-planlari/{$plan->id}")->with('success', 'Oturma planı oluşturuldu.');
    }

    public function show(ClassroomPlan $plan): Response
    {
        SchoolScope::ensure($plan);
        $plan->load(['academicYear:id,name', 'branch:id,name,teacher_id', 'branch.teacher.person:id,full_name,photo_path']);

        return Inertia::render('ClassroomPlans/Show', [
            'plan' => ['id' => $plan->id],
            'year' => ['id' => $plan->academicYear->id, 'name' => $plan->academicYear->name],
            'branch' => ['id' => $plan->branch->id, 'name' => $plan->branch->name],
            'teacher' => $this->teacherData($plan),
            'grid' => $this->gridData($plan),
            'unseated' => $this->unseatedStudents($plan),
        ]);
    }

    public function destroy(ClassroomPlan $plan): RedirectResponse
    {
        SchoolScope::ensure($plan);
        $plan->delete();

        return redirect('/oturme-planlari')->with('success', 'Oturma planı silindi.');
    }

    /**
     * Listeyle Eşitle: gidenlerin koltuğunu boşaltır, yeni gelenleri
     * numara sırasıyla boş koltuklara yerleştirir. Mevcut yerleşim korunur.
     */
    public function sync(ClassroomPlan $plan): RedirectResponse
    {
        SchoolScope::ensure($plan);

        $result = DB::transaction(function () use ($plan) {
            $roster = $this->roster((int) $plan->academic_year_id, (int) $plan->branch_id);
            $rosterIds = $roster->pluck('id')->all();

            $removed = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)
                ->whereNotNull('student_id')
                ->whereNotIn('student_id', $rosterIds)
                ->update(['student_id' => null]);

            $seatedIds = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)
                ->whereNotNull('student_id')
                ->pluck('student_id')
                ->all();

            $newcomers = $roster->reject(fn (Student $s) => in_array($s->id, $seatedIds, true))->values();
            $added = $this->placeStudents($plan, $newcomers);

            return ['added' => $added, 'removed' => $removed];
        });

        return back()->with('success', "Liste eşitlendi: {$result['added']} yerleştirildi, {$result['removed']} koltuk boşaltıldı.");
    }

    /**
     * Alta yeni satır ekler (mevcut sütun genişliğinde).
     */
    public function addRow(ClassroomPlan $plan): RedirectResponse
    {
        SchoolScope::ensure($plan);

        $maxRow = (int) ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->max('row');
        $maxColumn = (int) ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->max('column') ?: self::DEFAULT_COLUMNS;

        foreach (range(1, $maxColumn) as $column) {
            ClassroomPlanSeat::firstOrCreate([
                'classroom_plan_id' => $plan->id,
                'row' => $maxRow + 1,
                'column' => $column,
            ]);
        }

        return back()->with('success', 'Satır eklendi.');
    }

    /**
     * Sağa yeni sütun ekler (mevcut satır yüksekliğinde).
     */
    public function addColumn(ClassroomPlan $plan): RedirectResponse
    {
        SchoolScope::ensure($plan);

        $maxRow = (int) ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->max('row') ?: self::DEFAULT_ROWS;
        $maxColumn = (int) ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->max('column');

        foreach (range(1, $maxRow) as $row) {
            ClassroomPlanSeat::firstOrCreate([
                'classroom_plan_id' => $plan->id,
                'row' => $row,
                'column' => $maxColumn + 1,
            ]);
        }

        return back()->with('success', 'Sütun eklendi.');
    }

    public function removeSeat(ClassroomPlanSeat $seat): RedirectResponse
    {
        $plan = $seat->plan;
        SchoolScope::ensure($plan);

        $seat->delete();

        return back()->with('success', 'Koltuk silindi.');
    }

    /**
     * Aynı sınıf içi taşıma: dolu koltuktan boş koltuğa veya
     * listedeki (henüz yerleşmemiş) öğrenciyi boş koltuğa yerleştirme.
     */
    public function move(ClassroomPlan $plan): RedirectResponse
    {
        SchoolScope::ensure($plan);

        $data = request()->validate([
            'from_seat_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'to_seat_id' => ['required', 'integer'],
        ]);

        $to = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->findOrFail($data['to_seat_id']);

        if ($to->student_id) {
            return back()->withErrors(['seat' => 'Hedef koltuk dolu, takas kullanın.']);
        }

        if (! empty($data['from_seat_id'])) {
            $from = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->findOrFail($data['from_seat_id']);

            if (! $from->student_id) {
                return back()->withErrors(['seat' => 'Kaynak koltuk boş.']);
            }

            $this->ensureRoster($plan, (int) $from->student_id);

            $to->update(['student_id' => $from->student_id]);
            $from->update(['student_id' => null]);
        } else {
            if (empty($data['student_id'])) {
                return back()->withErrors(['seat' => 'Taşınacak öğrenci seçin.']);
            }

            $this->ensureRoster($plan, (int) $data['student_id']);

            $taken = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)
                ->where('student_id', $data['student_id'])->exists();

            if ($taken) {
                return back()->withErrors(['seat' => 'Bu öğrenci zaten bir koltukta oturuyor.']);
            }

            $to->update(['student_id' => $data['student_id']]);
        }

        return back()->with('success', 'Taşıma yapıldı.');
    }

    /**
     * Aynı sınıf içi takas: iki dolu koltuğun öğrencileri yer değiştirir.
     */
    public function swap(ClassroomPlan $plan): RedirectResponse
    {
        SchoolScope::ensure($plan);

        $data = request()->validate([
            'seat_id_a' => ['required', 'integer'],
            'seat_id_b' => ['required', 'integer'],
        ]);

        $a = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->findOrFail($data['seat_id_a']);
        $b = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->findOrFail($data['seat_id_b']);

        if (! $a->student_id || ! $b->student_id) {
            return back()->withErrors(['seat' => 'Takas için iki koltuk da dolu olmalı.']);
        }

        // Önce bir taraf boşaltılır, yoksa unique kısıtı çakışır.
        DB::transaction(function () use ($a, $b) {
            $aStudent = $a->student_id;
            $bStudent = $b->student_id;
            $a->update(['student_id' => null]);
            $b->update(['student_id' => $aStudent]);
            $a->update(['student_id' => $bStudent]);
        });

        return back()->with('success', 'Takas yapıldı.');
    }

    public function print(ClassroomPlan $plan): Response
    {
        SchoolScope::ensure($plan);
        $plan->load(['academicYear:id,name', 'branch:id,name,teacher_id', 'branch.teacher.person:id,full_name,photo_path']);

        return Inertia::render('Prints/ClassroomSeating', [
            'plan' => ['id' => $plan->id],
            'year' => ['name' => $plan->academicYear->name],
            'branch' => ['name' => $plan->branch->name],
            'teacher' => $this->teacherData($plan),
            'grid' => $this->gridData($plan),
        ]);
    }

    /**
     * Sınıf rehber öğretmeni (şubeden gelir, planda sorulmaz).
     */
    private function teacherData(ClassroomPlan $plan): ?array
    {
        $person = $plan->branch?->teacher?->person;

        if (! $person) {
            return null;
        }

        return [
            'full_name' => $person->full_name,
            'photo_url' => $person->photo_path ? asset('storage/'.$person->photo_path) : null,
        ];
    }

    /**
     * Sınıf mevcudu: bu yıl bu şubeye kayıtlı aktif öğrenciler, numara sırasıyla.
     *
     * @return \Illuminate\Support\Collection<int, Student>
     */
    private function roster(int $yearId, int $branchId)
    {
        return Student::where('is_active', true)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('academic_year_id', $yearId)
                ->where('branch_id', $branchId))
            ->with(['person:id,full_name,photo_path', 'enrollments' => fn ($query) => $query->where('academic_year_id', $yearId)])
            ->get()
            ->sort(fn (Student $a, Student $b) => strnatcmp(
                $a->enrollments->first()?->school_number ?? '',
                $b->enrollments->first()?->school_number ?? ''
            ))
            ->values();
    }

    /**
     * Öğrenci bu planın sınıf listesinde değilse reddet (sınıflar arası taşıma yok).
     */
    private function ensureRoster(ClassroomPlan $plan, int $studentId): void
    {
        $ok = $this->roster((int) $plan->academic_year_id, (int) $plan->branch_id)
            ->contains(fn (Student $s) => $s->id === $studentId);

        abort_unless($ok, 422, 'Bu öğrenci bu sınıfın listesinde değil.');
    }

    /**
     * Verilen öğrencileri numara sırasıyla boş koltuklara yerleştirir.
     * Boş koltuk yetmezse alta yeni satırlar ekler. Yerleşen sayısını döner.
     *
     * @param  \Illuminate\Support\Collection<int, Student>  $students
     */
    private function placeStudents(ClassroomPlan $plan, $students): int
    {
        $placed = 0;

        foreach ($students as $student) {
            $seat = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)
                ->whereNull('student_id')
                ->orderBy('row')->orderBy('column')
                ->first();

            if (! $seat) {
                $maxRow = (int) ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->max('row');
                $maxColumn = (int) ClassroomPlanSeat::where('classroom_plan_id', $plan->id)->max('column') ?: self::DEFAULT_COLUMNS;
                $seat = ClassroomPlanSeat::create([
                    'classroom_plan_id' => $plan->id,
                    'row' => $maxRow + 1,
                    'column' => 1,
                ]);
                for ($c = 2; $c <= $maxColumn; $c++) {
                    ClassroomPlanSeat::create([
                        'classroom_plan_id' => $plan->id,
                        'row' => $maxRow + 1,
                        'column' => $c,
                    ]);
                }
            }

            $seat->update(['student_id' => $student->id]);
            $placed++;
        }

        return $placed;
    }

    private function gridData(ClassroomPlan $plan): array
    {
        $yearId = (int) $plan->academic_year_id;

        $seats = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)
            ->with(['student.person:id,full_name,photo_path', 'student.enrollments' => fn ($query) => $query->where('academic_year_id', $yearId)])
            ->orderBy('row')->orderBy('column')
            ->get();

        return [
            'maxRow' => $seats->max('row') ?? 0,
            'maxColumn' => $seats->max('column') ?? 0,
            'seats' => $seats->map(fn (ClassroomPlanSeat $seat) => [
                'id' => $seat->id,
                'row' => $seat->row,
                'column' => $seat->column,
                'student' => $seat->student ? [
                    'id' => $seat->student->id,
                    'school_number' => $seat->student->enrollments->first()?->school_number,
                    'full_name' => $seat->student->person?->full_name,
                    'photo_url' => $seat->student->person?->photo_path
                        ? asset('storage/'.$seat->student->person->photo_path)
                        : null,
                ] : null,
            ])->all(),
        ];
    }

    private function unseatedStudents(ClassroomPlan $plan): array
    {
        $seatedIds = ClassroomPlanSeat::where('classroom_plan_id', $plan->id)
            ->whereNotNull('student_id')
            ->pluck('student_id')
            ->all();

        return $this->roster((int) $plan->academic_year_id, (int) $plan->branch_id)
            ->reject(fn (Student $s) => in_array($s->id, $seatedIds, true))
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'school_number' => $s->enrollments->first()?->school_number,
                'full_name' => $s->person?->full_name,
            ])
            ->values()
            ->all();
    }
}
