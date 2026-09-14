<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function index(): Response
    {
        $schools = School::withCount(['users', 'academicYears', 'rooms'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Schools/Index', [
            'schools' => $schools,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        School::create($data + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', 'Okul eklendi.');
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $data = $this->validated($request, $school);

        $school->update($data + ['is_active' => $request->boolean('is_active', $school->is_active)]);

        return back()->with('success', 'Okul güncellendi.');
    }

    public function destroy(School $school): RedirectResponse
    {
        if ($school->academicYears()->exists() || $school->rooms()->exists() || $school->users()->exists()) {
            return back()->withErrors(['school' => 'Bu okula ait yıl, salon veya kullanıcı olduğu için silinemez.']);
        }

        $school->delete();

        if ((int) session('current_school_id') === $school->id) {
            session()->forget('current_school_id');
        }

        return back()->with('success', $school->name.' silindi.');
    }

    /**
     * @return array{name: string, kurum_kodu: string, mail: ?string, telefon: ?string, mudur: ?string, muduryrd: ?string}
     */
    private function validated(Request $request, ?School $school = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kurum_kodu' => ['required', 'string', 'max:20', Rule::unique('schools', 'kurum_kodu')->ignore($school?->id)],
            'mail' => ['nullable', 'email', 'max:100'],
            'telefon' => ['nullable', 'string', 'max:30'],
            'mudur' => ['nullable', 'string', 'max:100'],
            'muduryrd' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Okul adı gerekli.',
            'kurum_kodu.required' => 'Kurum kodu gerekli.',
            'kurum_kodu.unique' => 'Bu kurum kodu zaten kayıtlı.',
            'mail.email' => 'Geçerli bir e-posta adresi girin.',
        ]);
    }
}
