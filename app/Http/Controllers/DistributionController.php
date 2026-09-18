<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ExamWeek;
use App\Support\SchoolScope;
use App\Models\Room;
use App\Models\SeatingPlan;
use App\Models\Student;
use App\Services\DistributionException;
use App\Services\SeatingDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DistributionController extends Controller
{
    public function __construct(private SeatingDistributionService $service) {}

    public function index(): Response
    {
        $schoolId = SchoolScope::id();
        $yearId = AcademicYear::where('school_id', $schoolId)->where('is_active', true)->orderByDesc('name')->value('id')
            ?? AcademicYear::where('school_id', $schoolId)->orderByDesc('name')->value('id');

        $weeks = ExamWeek::with('academicYear:id,name')
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId), fn ($query) => $query->whereRaw('0 = 1'))
            ->orderByDesc('id')
            ->get(['id', 'name', 'academic_year_id', 'is_active']);

        $selectedId = request()->integer('exam_week_id')
            ?: $weeks->firstWhere('is_active', true)?->id
            ?? $weeks->first()?->id;

        if ($selectedId && ! $weeks->contains('id', $selectedId)) {
            $selectedId = $weeks->firstWhere('is_active', true)?->id
                ?? $weeks->first()?->id;
        }

        $week = $selectedId ? ExamWeek::find($selectedId) : null;

        return Inertia::render('Distribution/Index', [
            'weeks' => $weeks,
            'selectedWeekId' => $selectedId,
            'summary' => $week ? $this->preSummary($week) : null,
            'plans' => $week
                ? $week->seatingPlans()->with('creator:id,name')->latest()->get(['id', 'exam_week_id', 'name', 'status', 'total_students', 'used_room_count', 'created_by', 'created_at'])
                : [],
        ]);
    }

    public function store(): RedirectResponse
    {
        $data = request()->validate([
            'exam_week_id' => ['required', 'integer', 'exists:exam_weeks,id'],
            'name' => ['nullable', 'string', 'max:100'],
        ], [
            'exam_week_id.required' => 'Sınav haftası seçin.',
            'exam_week_id.exists' => 'Seçilen sınav haftası bulunamadı.',
        ]);

        SchoolScope::ensure(ExamWeek::findOrFail($data['exam_week_id']));

        try {
            $plan = $this->service->distribute(
                ExamWeek::findOrFail($data['exam_week_id']),
                $data['name'] ?? null,
                auth()->id()
            );
        } catch (DistributionException $e) {
            return back()->withErrors(['exam_week_id' => $e->getMessage()]);
        }

        return redirect("/distribution/plans/{$plan->id}")->with('success', 'Dağıtım oluşturuldu.');
    }

    public function show(SeatingPlan $plan): Response
    {
        SchoolScope::ensure($plan);
        $plan->load(['examWeek:id,name', 'creator:id,name']);

        $summary = $this->service->summary($plan);

        return Inertia::render('Distribution/Show', [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'status' => $plan->status,
                'total_students' => $plan->total_students,
                'used_room_count' => $plan->used_room_count,
                'created_at' => $plan->created_at?->format('d.m.Y H:i'),
                'creator' => $plan->creator?->name,
            ],
            'week' => ['id' => $plan->examWeek->id, 'name' => $plan->examWeek->name],
            'summary' => $summary,
            'roomsData' => $this->roomGridData($plan, $summary['violating_seat_ids']),
        ]);
    }

    public function finalize(SeatingPlan $plan): RedirectResponse    {
        SchoolScope::ensure($plan);
        $plan->update(['status' => 'final']);

        return back()->with('success', 'Plan final olarak işaretlendi.');
    }

    public function reopen(SeatingPlan $plan): RedirectResponse
    {
        SchoolScope::ensure($plan);
        $plan->update(['status' => 'draft']);

        return back()->with('success', 'Plan taslağa alındı.');
    }

    public function destroy(SeatingPlan $plan): RedirectResponse
    {
        SchoolScope::ensure($plan);
        $plan->delete();

        return back()->with('success', 'Dağıtım planı silindi.');
    }

    public function move(SeatingPlan $plan): JsonResponse
    {
        SchoolScope::ensure($plan);
        $data = request()->validate([
            'assignment_id' => ['required', 'integer'],
            'seat_id' => ['required', 'integer'],
            'force' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $this->service->moveAssignment(
                $plan,
                (int) $data['assignment_id'],
                (int) $data['seat_id'],
                (bool) ($data['force'] ?? false)
            );
        } catch (DistributionException $e) {
            return response()->json(['applied' => false, 'needs_confirm' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json($this->withGrid($plan, $result));
    }

    public function swap(SeatingPlan $plan): JsonResponse
    {
        SchoolScope::ensure($plan);
        $data = request()->validate([
            'assignment_id' => ['required', 'integer'],
            'other_assignment_id' => ['required', 'integer'],
            'force' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $this->service->swapAssignments(
                $plan,
                (int) $data['assignment_id'],
                (int) $data['other_assignment_id'],
                (bool) ($data['force'] ?? false)
            );
        } catch (DistributionException $e) {
            return response()->json(['applied' => false, 'needs_confirm' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json($this->withGrid($plan, $result));
    }

    private function withGrid(SeatingPlan $plan, array $result): array
    {
        if (! $result['applied']) {
            return $result;
        }

        $fresh = $plan->fresh();
        $result['summary'] = $this->service->summary($fresh);
        $result['roomsData'] = $this->roomGridData($fresh, $result['violating_seat_ids']);

        return $result;
    }

    private function roomGridData(SeatingPlan $plan, array $violating): array
    {
        return collect($this->service->seatingGrid($plan))
            ->map(fn ($room) => [
                ...$room,
                'seats' => collect($room['seats'])
                    ->map(fn ($seat) => [...$seat, 'violation' => in_array($seat['id'], $violating, true)])
                    ->all(),
            ])
            ->all();
    }

    private function preSummary(ExamWeek $examWeek): array
    {
        $branches = $examWeek->branches()
            ->withCount(['students as active_students_count' => fn ($query) => $query->where('students.is_active', true)])
            ->orderBy('name')
            ->get(['branches.id', 'branches.name']);

        $studentCount = Student::where('is_active', true)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('academic_year_id', $examWeek->academic_year_id)
                ->whereIn('branch_id', $branches->pluck('id')))
            ->count();

        $rooms = $examWeek->rooms()
            ->withCount(['seats as active_seats_count' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['rooms.id', 'rooms.name', 'rooms.is_active']);

        $capacity = $rooms->where('is_active', true)->sum('active_seats_count');
        $minRooms = $this->service->estimateMinRooms($examWeek);

        return [
            'week' => ['id' => $examWeek->id, 'name' => $examWeek->name],
            'branches' => $branches,
            'branchCount' => $branches->count(),
            'studentCount' => $studentCount,
            'rooms' => $rooms,
            'roomCount' => $rooms->count(),
            'capacity' => $capacity,
            'minRooms' => $minRooms,
            'feasible' => $minRooms !== null,
        ];
    }
}
