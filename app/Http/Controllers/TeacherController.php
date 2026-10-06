<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\School;
use App\Models\Teacher;
use App\Services\PhotoService;
use App\Support\PhoneNumber;
use App\Support\SchoolScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('q', ''));

        $query = Teacher::with('person:id,school_id,first_name,last_name,full_name,phone,email,address,photo_path,updated_at')
            ->whereHas('person', fn ($q) => $q->where('school_id', SchoolScope::id()))
            ->when($search !== '', fn ($q) => $q->whereHas('person', fn ($qq) => $qq
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")));

        $teachers = $query->orderByRaw('started_at IS NULL, started_at ASC')->paginate(30)->withQueryString();

        $teachers->setCollection($teachers->getCollection()->map(fn (Teacher $t) => [
            'id' => $t->id,
            'first_name' => $t->person?->first_name,
            'last_name' => $t->person?->last_name,
            'full_name' => $t->person?->full_name ?? '—',
            'phone' => $t->person?->phone,
            'email' => $t->person?->email,
            'address' => $t->person?->address,
            'photo_path' => $t->person?->photo_path,
            'photo_version' => $t->person?->updated_at?->timestamp,
            'duty' => $t->duty,
            'branch' => $t->branch,
            'started_at' => $t->started_at?->format('Y-m-d'),
            'started_at_label' => $t->started_at?->format('d.m.Y'),
            'is_active' => $t->is_active,
        ]));

        return Inertia::render('Personel/Index', [
            'search' => $search,
            'teachers' => $teachers,
            'totalTeachers' => Teacher::whereHas('person', fn ($q) => $q->where('school_id', SchoolScope::id()))->count(),
            'school' => $this->schoolProp(),
        ]);
    }

    public function badges(Request $request): Response
    {
        $schoolId = SchoolScope::id();

        $ids = collect(explode(',', (string) $request->input('ids', '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        $search = trim((string) $request->input('q', ''));

        $query = Teacher::with('person:id,first_name,last_name,full_name')
            ->whereHas('person', fn ($q) => $q->where('school_id', $schoolId))
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('teachers.id', $ids->all()))
            ->when($ids->isEmpty() && $request->boolean('all'), fn ($q) => $q->where('teachers.is_active', true))
            ->when($ids->isEmpty() && $search !== '', fn ($q) => $q->whereHas('person', fn ($qq) => $qq
                ->where('full_name', 'like', "%{$search}%")));

        // Seçim yoksa boş liste dön, hata verme.
        if ($ids->isEmpty() && ! $request->boolean('all') && $search === '') {
            $teachers = collect();
        } else {
            $teachers = $query
                ->join('people', 'people.id', '=', 'teachers.person_id')
                ->orderBy('people.full_name')
                ->select('teachers.*')
                ->limit(200)
                ->get();
        }

        $badges = $teachers->map(fn (Teacher $t) => [
            'id' => $t->id,
            'full_name' => $t->person?->full_name ?? '—',
            'title' => $t->branch ?? ($t->duty ?? 'Öğretmen'),
            'duty' => $t->duty,
        ])->all();

        return Inertia::render('Prints/Badges', [
            'teachers' => $badges,
            'school' => $this->schoolProp(),
        ]);
    }

    /**
     * @return array{name: string, logo_url: ?string}
     */
    private function schoolProp(): array
    {
        $school = School::find(SchoolScope::id());

        return [
            'name' => $school?->name ?? '',
            'logo_url' => $school?->photo_path
                ? '/storage/'.$school->photo_path.'?v='.($school->updated_at?->timestamp ?? 0)
                : null,
        ];
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Personel/Create', [
            'backUrl' => $this->listBackUrl($request),
        ]);
    }

    public function edit(Request $request, Teacher $teacher): Response
    {
        $this->ensure($teacher);

        $teacher->load('person:id,first_name,last_name,full_name,phone,email,address,photo_path,updated_at');

        return Inertia::render('Personel/Edit', [
            'teacher' => [
                'id' => $teacher->id,
                'first_name' => $teacher->person?->first_name,
                'last_name' => $teacher->person?->last_name,
                'full_name' => $teacher->person?->full_name ?? '—',
                'phone' => $teacher->person?->phone,
                'email' => $teacher->person?->email,
                'address' => $teacher->person?->address,
                'photo_path' => $teacher->person?->photo_path,
                'photo_version' => $teacher->person?->updated_at?->timestamp,
                'duty' => $teacher->duty,
                'branch' => $teacher->branch,
                'started_at' => $teacher->started_at?->format('Y-m-d'),
                'started_at_label' => $teacher->started_at?->format('d.m.Y'),
                'is_active' => $teacher->is_active,
            ],
            'backUrl' => $this->listBackUrl($request),
        ]);
    }

    private function listBackUrl(Request $request): string
    {
        $q = trim((string) $request->input('q', ''));

        return '/personel'.($q === '' ? '' : '?'.http_build_query(['q' => $q]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $person = Person::create([
                'school_id' => SchoolScope::id(),
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'address' => $validated['address'],
            ]);

            $teacher = Teacher::create([
                'person_id' => $person->id,
                'duty' => $validated['duty'],
                'branch' => $validated['branch'],
                'started_at' => $validated['started_at'],
                'is_active' => $validated['is_active'],
            ]);

            if ($validated['photo']) {
                $this->storePersonPhoto($person, $teacher, $validated['photo']);
            }
        });

        return redirect()->route('personel.index')->with('success', 'Personel eklendi.');
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $this->ensure($teacher);
        $validated = $this->validated($request, $teacher);

        DB::transaction(function () use ($teacher, $validated) {
            $teacher->update([
                'duty' => $validated['duty'],
                'branch' => $validated['branch'],
                'started_at' => $validated['started_at'],
                'is_active' => $validated['is_active'],
            ]);

            $teacher->person?->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'address' => $validated['address'],
            ]);

            if ($validated['photo'] && $teacher->person) {
                $this->storePersonPhoto($teacher->person, $teacher, $validated['photo']);
            }
        });

        return redirect()->route('personel.index')->with('success', 'Personel güncellendi.');
    }

    public function activate(Teacher $teacher): RedirectResponse
    {
        $this->ensure($teacher);
        $teacher->update(['is_active' => true]);

        return back()->with('success', $teacher->person?->full_name.' aktif edildi.');
    }

    public function deactivate(Teacher $teacher): RedirectResponse
    {
        $this->ensure($teacher);
        $teacher->update(['is_active' => false]);

        return back()->with('success', $teacher->person?->full_name.' pasife alındı.');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        $this->ensure($teacher);

        $name = $teacher->person?->full_name ?? 'Personel';
        $person = $teacher->person;

        DB::transaction(function () use ($teacher, $person) {
            // Fotoğraf dosyası arşivde saklanır, silinmez.
            $teacher->delete();

            if ($person) {
                $hasRole = $person->student()->exists()
                    || $person->graduate()->exists()
                    || $person->teacher()->exists()
                    || $person->guardian()->exists();
                if (! $hasRole) {
                    $person->delete();
                }
            }
        });

        return back()->with('success', $name.' arşive gönderildi.');
    }

    private function ensure(Teacher $teacher): void
    {
        abort_unless($teacher->person?->school_id === SchoolScope::id(), 404);
    }

    /**
     * @return array{first_name: string, last_name: string, full_name: string, phone: ?string, email: ?string, address: ?string, duty: ?string, branch: ?string, started_at: ?string, photo: ?UploadedFile, is_active: bool}
     */
    private function validated(Request $request, ?Teacher $teacher = null): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'duty' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'started_at' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'first_name.required' => 'Ad gerekli.',
            'last_name.required' => 'Soyad gerekli.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'started_at.date' => 'Geçerli bir tarih girin.',
            'photo.image' => 'Yalnızca resim dosyası yükleyin.',
            'photo.mimes' => 'Desteklenen formatlar: jpg, jpeg, png, webp.',
            'photo.max' => 'Fotoğraf en fazla 10 MB olabilir.',
            'photo.uploaded' => 'Fotoğraf yüklenemedi, dosya çok büyük olabilir.',
        ]);

        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);
        $nullify = fn ($value) => trim((string) $value) === '' ? null : trim((string) $value);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $firstName.' '.$lastName,
            'phone' => PhoneNumber::normalizeOrFail($data['phone'] ?? null, 'phone', 'Geçerli bir telefon girin (örn. 0532 666 65 49).'),
            'email' => $nullify($data['email'] ?? null),
            'address' => $nullify($data['address'] ?? null),
            'duty' => $nullify($data['duty'] ?? null),
            'branch' => $nullify($data['branch'] ?? null),
            'started_at' => $data['started_at'] ?? null,
            'photo' => $request->file('photo'),
            'is_active' => $request->boolean('is_active', $teacher?->is_active ?? true),
        ];
    }

    private function storePersonPhoto(Person $person, Teacher $teacher, UploadedFile $photo): void
    {
        PhotoService::store($photo, "teachers/{$teacher->id}.jpg");

        // Yol aynı kalsa bile sürüm değişsin ki liste önbelleğe takılmasın.
        $person->forceFill(['photo_path' => "teachers/{$teacher->id}.jpg"])->save();
        $person->touch();
    }
}
