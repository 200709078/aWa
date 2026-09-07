<?php

namespace App\Http\Controllers;

use App\Models\SeatingPlan;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        $plans = SeatingPlan::with('examWeek:id,name')
            ->latest()
            ->get(['id', 'exam_week_id', 'name', 'status', 'total_students', 'used_room_count', 'created_at'])
            ->map(fn (SeatingPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name ?: "Plan #{$plan->id}",
                'week' => $plan->examWeek?->name,
                'status' => $plan->status,
                'total_students' => $plan->total_students,
                'used_room_count' => $plan->used_room_count,
            ]);

        return Inertia::render('Reports/Index', [
            'plans' => $plans,
        ]);
    }
}
