<?php

namespace App\Http\Controllers;

use App\Models\SeatingAssignment;
use App\Models\SeatingPlan;
use App\Services\SeatingDistributionService;
use App\Support\SchoolScope;
use Inertia\Inertia;
use Inertia\Response;

class PrintController extends Controller
{
    public function __construct(private SeatingDistributionService $service) {}

    public function seating(SeatingPlan $plan): Response
    {
        SchoolScope::ensure($plan);
        $plan->load('examWeek:id,name');

        return Inertia::render('Prints/Seating', [
            'plan' => ['id' => $plan->id, 'name' => $plan->name],
            'week' => ['name' => $plan->examWeek->name],
            'showPhotos' => request()->boolean('photo', true),
            'grid' => $this->service->seatingGrid($plan),
        ]);
    }

    public function branches(SeatingPlan $plan): Response
    {
        SchoolScope::ensure($plan);
        $plan->load('examWeek:id,name,academic_year_id');
        $yearId = (int) $plan->examWeek->academic_year_id;

        $assignments = SeatingAssignment::where('seating_plan_id', $plan->id)
            ->with(['student.person:id,full_name', 'student.enrollments.branch:id,name', 'seat.room:id,name'])
            ->get();

        $groups = $assignments
            ->groupBy(fn ($a) => $a->student->enrollmentForYear($yearId)?->branch_id ?? 0)
            ->map(fn ($items) => [
                'branch' => $items->first()->student->enrollmentForYear($yearId)?->branch?->name ?? '—',
                'students' => $items
                    ->sortBy(fn ($a) => $a->student->enrollmentForYear($yearId)?->school_number)
                    ->values()
                    ->map(fn ($a) => [
                        'school_number' => $a->student->enrollmentForYear($yearId)?->school_number,
                        'full_name' => $a->student->person?->full_name,
                        'room' => $a->seat->room->name,
                        'seat' => ($a->seat->label ?: $a->seat->row.'-'.$a->seat->column),
                    ])->all(),
            ])
            ->sortBy('branch')
            ->values()
            ->all();

        return Inertia::render('Prints/Branches', [
            'plan' => ['id' => $plan->id, 'name' => $plan->name],
            'week' => ['name' => $plan->examWeek->name],
            'groups' => $groups,
        ]);
    }

    public function rooms(SeatingPlan $plan): Response
    {
        SchoolScope::ensure($plan);
        $plan->load('examWeek:id,name');

        $grid = $this->service->seatingGrid($plan);

        $lists = array_map(fn ($room) => [
            'room' => $room['room'],
            'students' => collect($room['seats'])
                ->filter(fn ($seat) => $seat['student'] !== null)
                ->sortBy([['row', 'asc'], ['column', 'asc']])
                ->values()
                ->map(fn ($seat) => [
                    'seat' => $seat['label'] ?: $seat['row'].'-'.$seat['column'],
                    'school_number' => $seat['student']['school_number'],
                    'full_name' => $seat['student']['full_name'],
                    'branch' => $seat['student']['branch'],
                ])->all(),
        ], $grid);

        return Inertia::render('Prints/Rooms', [
            'plan' => ['id' => $plan->id, 'name' => $plan->name],
            'week' => ['name' => $plan->examWeek->name],
            'lists' => $lists,
        ]);
    }

    public function summary(SeatingPlan $plan): Response
    {
        SchoolScope::ensure($plan);
        $plan->load('examWeek:id,name,academic_year_id');
        $yearId = (int) $plan->examWeek->academic_year_id;

        $summary = $this->service->summary($plan);

        $assignments = SeatingAssignment::where('seating_plan_id', $plan->id)
            ->with(['student.enrollments.branch:id,name', 'seat.room:id,name,sort_order'])
            ->get();

        $branches = $assignments
            ->map(fn ($a) => $a->student->enrollmentForYear($yearId)?->branch)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $rooms = $assignments
            ->map(fn ($a) => $a->seat->room)
            ->unique('id')
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->values();

        $matrix = [];
        foreach ($rooms as $room) {
            $row = [
                'room' => $room->name,
                'capacity' => \App\Models\Seat::where('room_id', $room->id)->where('is_active', true)->count(),
                'cells' => [],
                'total' => 0,
            ];
            foreach ($branches as $branch) {
                $n = $assignments->filter(fn ($a) => $a->seat->room_id === $room->id && $a->student->enrollmentForYear($yearId)?->branch_id === $branch->id)->count();
                $row['cells'][] = $n;
                $row['total'] += $n;
            }
            $matrix[] = $row;
        }

        $branchTotals = [];
        foreach ($branches as $branch) {
            $branchTotals[] = $assignments->filter(fn ($a) => $a->student->enrollmentForYear($yearId)?->branch_id === $branch->id)->count();
        }

        return Inertia::render('Prints/Summary', [
            'plan' => ['id' => $plan->id, 'name' => $plan->name],
            'week' => ['name' => $plan->examWeek->name],
            'summary' => $summary,
            'branches' => $branches->map(fn ($b) => $b->name)->all(),
            'matrix' => $matrix,
            'branchTotals' => $branchTotals,
        ]);
    }
}
