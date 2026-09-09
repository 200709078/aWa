<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(): Response
    {
        $years = AcademicYear::withCount('branches')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('AcademicYears/Index', [
            'years' => $years,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20'],
        ], [
            'name.required' => 'Akademik yıl adı gerekli. (örn. 2026-2027)',
            'name.max' => 'Akademik yıl adı en fazla 20 karakter olabilir.',
        ]);

        AcademicYear::create(['name' => trim($data['name'])]);

        return back()->with('success', 'Akademik yıl eklendi.');
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20'],
        ], [
            'name.required' => 'Akademik yıl adı gerekli. (örn. 2026-2027)',
            'name.max' => 'Akademik yıl adı en fazla 20 karakter olabilir.',
        ]);

        $academicYear->update(['name' => trim($data['name'])]);

        return back()->with('success', 'Akademik yıl güncellendi.');
    }

    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('id', '!=', $academicYear->id)->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });

        return back()->with('success', $academicYear->name.' aktif yıl olarak seçildi.');
    }

    public function deactivate(AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update(['is_active' => false]);

        return back()->with('success', $academicYear->name.' pasife alındı.');
    }
}
