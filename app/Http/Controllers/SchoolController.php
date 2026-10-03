<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\PhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function index(): Response
    {
        $schools = School::withCount(['users', 'academicYears', 'rooms'])
            ->orderBy('name')
            ->get()
            ->map(fn (School $school) => [
                ...$school->toArray(),
                'photo_version' => $school->updated_at?->timestamp,
            ]);

        return Inertia::render('Schools/Index', [
            'schools' => $schools,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $school = School::create($data + ['is_active' => $request->boolean('is_active', true)]);

            if ($request->file('photo')) {
                $this->storePhoto($school, $request->file('photo'));
            }
        });

        return back()->with('success', 'Okul eklendi.');
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $data = $this->validated($request, $school);

        DB::transaction(function () use ($request, $school, $data) {
            $school->update($data + ['is_active' => $request->boolean('is_active', $school->is_active)]);

            if ($request->file('photo')) {
                $this->storePhoto($school->fresh(), $request->file('photo'));
            }
        });

        return back()->with('success', 'Okul güncellendi.');
    }

    public function destroy(School $school): RedirectResponse
    {
        if ($school->academicYears()->exists() || $school->rooms()->exists() || $school->users()->exists()) {
            return back()->withErrors(['school' => 'Bu okula ait yıl, salon veya kullanıcı olduğu için silinemez.']);
        }

        $school->delete();

        if ($school->photo_path) {
            Storage::disk('public')->delete($school->photo_path);
        }

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
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Okul adı gerekli.',
            'kurum_kodu.required' => 'Kurum kodu gerekli.',
            'kurum_kodu.unique' => 'Bu kurum kodu zaten kayıtlı.',
            'mail.email' => 'Geçerli bir e-posta adresi girin.',
            'photo.image' => 'Yalnızca resim dosyası yükleyin.',
            'photo.mimes' => 'Desteklenen formatlar: jpg, jpeg, png, webp.',
            'photo.max' => 'Fotoğraf en fazla 10 MB olabilir.',
            'photo.uploaded' => 'Fotoğraf yüklenemedi, dosya çok büyük olabilir.',
        ]);
    }

    private function storePhoto(School $school, UploadedFile $photo): void
    {
        PhotoService::store($photo, "schools/{$school->id}.jpg");

        // Yol aynı kalsa bile sürüm değişsin ki liste önbelleğe takılmasın.
        $school->forceFill(['photo_path' => "schools/{$school->id}.jpg"])->save();
        $school->touch();
    }
}
