<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Support\SchoolScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(): Response
    {
        $years = AcademicYear::where('school_id', SchoolScope::id())->withCount(['branches', 'students', 'examWeeks'])
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
        SchoolScope::ensure($academicYear);
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
        SchoolScope::ensure($academicYear);
        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('school_id', $academicYear->school_id)
                ->where('id', '!=', $academicYear->id)
                ->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });

        return back()->with('success', $academicYear->name.' aktif yıl olarak seçildi.');
    }

    public function deactivate(AcademicYear $academicYear): RedirectResponse
    {
        SchoolScope::ensure($academicYear);
        $academicYear->update(['is_active' => false]);

        return back()->with('success', $academicYear->name.' pasife alındı.');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        SchoolScope::ensure($academicYear);
        if ($academicYear->branches()->exists() || $academicYear->students()->exists() || $academicYear->examWeeks()->exists()) {
            return back()->withErrors(['year' => 'Bu yıla ait sınıf, öğrenci veya sınav haftası olduğu için silinemez.']);
        }

        $academicYear->delete();

        return back()->with('success', $academicYear->name.' silindi.');
    }
}
