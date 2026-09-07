<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function store(Request $request, ExamWeek $examWeek): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'exam_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Sınav adı gerekli.',
            'start_time.date_format' => 'Saat HH:MM formatında olmalı.',
        ]);

        $examWeek->exams()->create([
            'name' => trim($data['name']),
            'exam_date' => $data['exam_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'description' => isset($data['description']) ? trim($data['description']) : null,
        ]);

        return back()->with('success', 'Sınav eklendi.');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $exam->delete();

        return back()->with('success', 'Sınav silindi.');
    }
}
