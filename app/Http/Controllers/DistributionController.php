<?php

namespace App\Http\Controllers;

use App\Models\ExamWeek;
use App\Models\Room;
use App\Models\SeatingPlan;
use App\Models\Student;
use App\Services\DistributionException;
use App\Services\SeatingDistributionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DistributionController extends Controller
{
    public function __construct(private SeatingDistributionService $service) {}

    public function index(): Response
    {
        $weeks = ExamWeek::with('academicYear:id,name')
            ->orderByDesc('id')
            ->get(['id', 'name', 'academic_year_id', 'is_active']);

        $selectedId = request()->integer('exam_week_id')
            ?: $weeks->firstWhere('is_active', true)?->id
            ?? $weeks->first()?->id;

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
        $plan->load(['examWeek:id,name', 'creator:id,name']);

        $summary = $this->service->summary($plan);
        $plan->loadMissing(['assignments.student.branch:id,name']);

        $assignedBySeat = $plan->assignments->keyBy(fn ($a) => $a->seat_id);

        $rooms = Room::whereIn('id', collect($summary['used_rooms'])->pluck('id'))
            ->with(['seats' => fn ($query) => $query->where('is_active', true)->orderBy('row')->orderBy('column')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $violating = $summary['violating_seat_ids'];

        $roomsData = $rooms->map(fn (Room $room) => [
            'room' => ['id' => $room->id, 'name' => $room->name],
            'maxRow' => $room->seats->max('row') ?? 0,
            'maxColumn' => $room->seats->max('column') ?? 0,
            'seats' => $room->seats->map(fn ($seat) => [
                'id' => $seat->id,
                'row' => $seat->row,
                'column' => $seat->column,
                'label' => $seat->label,
                'violation' => in_array($seat->id, $violating, true),
                'student' => isset($assignedBySeat[$seat->id]) ? [
                    'school_number' => $assignedBySeat[$seat->id]->student->school_number,
                    'full_name' => $assignedBySeat[$seat->id]->student->full_name,
                    'branch' => $assignedBySeat[$seat->id]->student->branch?->name,
                ] : null,
            ])->all(),
        ])->all();

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
            'roomsData' => $roomsData,
        ]);
    }

    public function finalize(SeatingPlan $plan): RedirectResponse
    {
        $plan->update(['status' => 'final']);

        return back()->with('success', 'Plan final olarak işaretlendi.');
    }

    public function reopen(SeatingPlan $plan): RedirectResponse
    {
        $plan->update(['status' => 'draft']);

        return back()->with('success', 'Plan taslağa alındı.');
    }

    private function preSummary(ExamWeek $examWeek): array
    {
        $branches = $examWeek->branches()
            ->withCount(['students as active_students_count' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get(['branches.id', 'branches.name']);

        $studentCount = Student::whereIn('branch_id', $branches->pluck('id'))
            ->where('is_active', true)
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
