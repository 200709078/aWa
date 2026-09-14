<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Support\SchoolScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $years = AcademicYear::where('school_id', SchoolScope::id())->orderByDesc('name')->get(['id', 'name', 'is_active']);

        $selectedYearId = $request->integer('academic_year_id')
            ?: $years->firstWhere('is_active', true)?->id
            ?? $years->first()?->id;

        $branches = $selectedYearId
            ? Branch::withCount('students')
                ->where('academic_year_id', $selectedYearId)
                ->orderBy('name')
                ->get()
            : [];

        return Inertia::render('Branches/Index', [
            'years' => $years,
            'selectedYearId' => $selectedYearId,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            Branch::create($this->validated($request));
        } catch (QueryException $e) {
            return back()->withErrors(['name' => 'Bu akademik yılda bu sınıf zaten kayıtlı.'])->withInput();
        }

        return back()->with('success', 'Sınıf eklendi.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        SchoolScope::ensure($branch);
        try {
            $branch->update($this->validated($request, $branch));
        } catch (QueryException $e) {
            return back()->withErrors(['name' => 'Bu akademik yılda bu şube zaten kayıtlı.'])->withInput();
        }

        return back()->with('success', 'Sınıf güncellendi.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        SchoolScope::ensure($branch);
        if ($branch->students()->exists()) {
            return back()->withErrors(['branch' => 'Bu sınıfta kayıtlı öğrenci olduğu için silinemez.']);
        }

        // Sınav haftası seçimleri FK cascade ile kaldırılır.
        $branch->delete();

        return back()->with('success', 'Sınıf silindi.');
    }

    public function activate(Branch $branch): RedirectResponse
    {
        SchoolScope::ensure($branch);
        $branch->update(['is_active' => true]);

        return back()->with('success', $branch->name.' aktif edildi.');
    }

    public function deactivate(Branch $branch): RedirectResponse
    {
        SchoolScope::ensure($branch);
        $branch->update(['is_active' => false]);

        return back()->with('success', $branch->name.' pasife alındı.');
    }

    /**
     * @return array{academic_year_id: int, name: string, grade_level: int, section: string, is_active: bool}
     */
    private function validated(Request $request, ?Branch $branch = null): array
    {
        $yearId = $request->input('academic_year_id', $branch?->academic_year_id);

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('school_id', SchoolScope::id())],
            'name' => [
                'required', 'string', 'max:10',
                Rule::unique('branches')->where(fn ($query) => $query->where('academic_year_id', $yearId))->ignore($branch?->id),
            ],
            'grade_level' => ['required', 'integer', 'min:1', 'max:12'],
            'section' => ['required', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'academic_year_id.required' => 'Akademik yıl seçin.',
            'academic_year_id.exists' => 'Seçilen akademik yıl bulunamadı.',
            'name.required' => 'Sınıf adı gerekli.',
            'name.unique' => 'Bu akademik yılda bu sınıf zaten kayıtlı.',
            'grade_level.required' => 'Seviye gerekli.',
            'grade_level.integer' => 'Seviye sayı olmalı.',
            'grade_level.min' => 'Seviye 1-12 arasında olmalı.',
            'grade_level.max' => 'Seviye 1-12 arasında olmalı.',
            'section.required' => 'Şube gerekli.',
        ]);

        $data['name'] = mb_strtoupper(trim($data['name']));
        $data['section'] = mb_strtoupper(trim($data['section']));
        $data['is_active'] = $request->boolean('is_active', $branch?->is_active ?? true);

        return $data;
    }
}
