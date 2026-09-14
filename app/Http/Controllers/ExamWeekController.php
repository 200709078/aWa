<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Support\SchoolScope;
use App\Models\Branch;
use App\Models\ExamWeek;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExamWeekController extends Controller
{
    public function index(): Response
    {
        $weeks = ExamWeek::with(['academicYear:id,name'])
            ->withCount(['branches', 'rooms', 'seatingPlans', 'exams'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (ExamWeek $week) => [
                'id' => $week->id,
                'name' => $week->name,
                'description' => $week->description,
                'academic_year_id' => $week->academic_year_id,
                'academic_year' => $week->academicYear?->name,
                'starts_at' => $week->starts_at?->format('Y-m-d'),
                'ends_at' => $week->ends_at?->format('Y-m-d'),
                'is_active' => $week->is_active,
                'branches_count' => $week->branches_count,
                'rooms_count' => $week->rooms_count,
                'plans_count' => $week->seating_plans_count,
                'exams_count' => $week->exams_count,
                'student_count' => $this->studentCount($week),
                'capacity' => $this->capacity($week),
            ]);

        $years = AcademicYear::where('school_id', SchoolScope::id())->orderByDesc('name')->get(['id', 'name', 'is_active']);
        $activeId = $years->firstWhere('is_active', true)?->id ?? $years->first()?->id;

        return Inertia::render('ExamWeeks/Index', [
            'weeks' => $weeks,
            'years' => $years,
            'activeYearId' => $activeId,
        ]);
    }

    public function show(ExamWeek $examWeek): Response
    {
        SchoolScope::ensure($examWeek);
        $examWeek->load(['branches:id', 'rooms:id']);

        $branches = Branch::where('academic_year_id', $examWeek->academic_year_id)
            ->withCount(['students as active_students_count' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get(['id', 'name']);

        $rooms = Room::withCount(['seats as active_seats_count' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return Inertia::render('ExamWeeks/Show', [
            'week' => [
                'id' => $examWeek->id,
                'name' => $examWeek->name,
                'description' => $examWeek->description,
                'academic_year' => $examWeek->academicYear?->name,
                'starts_at' => $examWeek->starts_at?->format('Y-m-d'),
                'ends_at' => $examWeek->ends_at?->format('Y-m-d'),
                'is_active' => $examWeek->is_active,
            ],
            'exams' => $examWeek->exams()->orderBy('exam_date')->orderBy('start_time')->get(['id', 'name', 'exam_date', 'start_time', 'description']),
            'branches' => $branches,
            'rooms' => $rooms,
            'selectedBranchIds' => $examWeek->branches->pluck('id'),
            'selectedRoomIds' => $examWeek->rooms->pluck('id'),
            'summary' => [
                'student_count' => $this->studentCount($examWeek),
                'room_count' => $examWeek->rooms()->where('rooms.is_active', true)->count(),
                'capacity' => $this->capacity($examWeek),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        ExamWeek::create($data);

        return back()->with('success', 'Sınav haftası eklendi.');
    }

    public function update(Request $request, ExamWeek $examWeek): RedirectResponse
    {
        SchoolScope::ensure($examWeek);
        $examWeek->update($this->validated($request));

        return back()->with('success', 'Sınav haftası güncellendi.');
    }

    public function destroy(ExamWeek $examWeek): RedirectResponse
    {
        SchoolScope::ensure($examWeek);
        if ($examWeek->seatingPlans()->exists() || $examWeek->exams()->exists()) {
            return back()->withErrors(['week' => 'Bu sınav haftasına ait dağıtım planı veya sınav olduğu için silinemez.']);
        }

        // Şube/salon seçimleri FK cascade ile birlikte silinir; şube, salon ve öğrenciler korunur.
        $examWeek->delete();

        return back()->with('success', 'Sınav haftası silindi.');
    }

    public function activate(ExamWeek $examWeek): RedirectResponse
    {
        SchoolScope::ensure($examWeek);
        $examWeek->update(['is_active' => true]);

        return back()->with('success', $examWeek->name.' aktif edildi.');
    }

    public function deactivate(ExamWeek $examWeek): RedirectResponse
    {
        SchoolScope::ensure($examWeek);
        $examWeek->update(['is_active' => false]);

        return back()->with('success', $examWeek->name.' pasife alındı.');
    }

    public function syncBranches(Request $request, ExamWeek $examWeek): RedirectResponse
    {
        SchoolScope::ensure($examWeek);
        $data = $request->validate([
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')->where('academic_year_id', $examWeek->academic_year_id)],
        ], [
            'branch_ids.*.exists' => 'Seçilen şubelerden biri bu akademik yıla ait değil.',
        ]);

        $examWeek->branches()->sync($data['branch_ids'] ?? []);

        return back()->with('success', 'Dağıtıma dahil şubeler kaydedildi.');
    }

    public function syncRooms(Request $request, ExamWeek $examWeek): RedirectResponse
    {
        SchoolScope::ensure($examWeek);
        $data = $request->validate([
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', Rule::exists('rooms', 'id')->where('school_id', SchoolScope::id())],
        ], [
            'room_ids.*.exists' => 'Seçilen salonlardan biri bulunamadı.',
        ]);

        $examWeek->rooms()->sync($data['room_ids'] ?? []);

        return back()->with('success', 'Kullanılmasına izin verilen salonlar kaydedildi.');
    }

    /**
     * @return array{academic_year_id: int, name: string, description: ?string, starts_at: ?string, ends_at: ?string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('school_id', SchoolScope::id())],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ], [
            'academic_year_id.required' => 'Akademik yıl seçin.',
            'name.required' => 'Sınav haftası adı gerekli. (örn. 1. Dönem Sınavları)',
            'ends_at.after_or_equal' => 'Bitiş tarihi başlangıçtan önce olamaz.',
        ]);

        return [
            'academic_year_id' => $data['academic_year_id'],
            'name' => trim($data['name']),
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }

    private function studentCount(ExamWeek $week): int
    {
        $branchIds = $week->branches()->pluck('branches.id');

        if ($branchIds->isEmpty()) {
            return 0;
        }

        return Student::whereIn('branch_id', $branchIds)->where('is_active', true)->count();
    }

    private function capacity(ExamWeek $week): int
    {
        $roomIds = $week->rooms()->where('rooms.is_active', true)->pluck('rooms.id');

        if ($roomIds->isEmpty()) {
            return 0;
        }

        return Seat::whereIn('room_id', $roomIds)->where('is_active', true)->count();
    }
}
