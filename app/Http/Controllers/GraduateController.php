<?php

namespace App\Http\Controllers;

use App\Models\Graduate;
use App\Models\Person;
use App\Services\PhotoService;
use App\Services\RehberExportService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GraduateController extends Controller
{
    public function __construct(private RehberExportService $export) {}

    public function index(Request $request): Response
    {
        $years = Graduate::distinct()->orderByDesc('graduation_year')->pluck('graduation_year')->values();
        $search = trim((string) $request->input('q', ''));

        // Arama varsa yıllara bakılmaksızın düz liste; yoksa her sayfa bir yıl.
        if ($search !== '') {
            $paginator = Graduate::with(['person:id,first_name,last_name,full_name,phone,email,photo_path', 'person.educations', 'person.employments'])
                ->where(function ($query) use ($search) {
                    $query->whereHas('person', fn ($query) => $query->where('full_name', 'like', "%{$search}%"))
                        ->orWhere('graduation_number', 'like', "%{$search}%");
                })
                ->orderByDesc('graduation_year')
                ->orderBy('graduation_number')
                ->paginate(30)
                ->withQueryString();

            $paginator->setCollection($paginator->getCollection()->map(fn (Graduate $graduate) => $this->flatten($graduate)));

            return Inertia::render('Mezunlar/Index', [
                'years' => $years,
                'year' => null,
                'page' => 1,
                'search' => $search,
                'graduates' => $paginator,
                'totalGraduates' => Graduate::count(),
            ]);
        }

        $page = min(max(1, $request->integer('page', 1)), max(1, $years->count()));
        $year = $years->get($page - 1);

        $rows = $year === null
            ? collect()
            : Graduate::with(['person:id,first_name,last_name,full_name,phone,email,photo_path', 'person.educations', 'person.employments'])
                ->where('graduation_year', $year)
                ->orderBy('graduation_number')
                ->get();

        $data = $rows->map(fn (Graduate $graduate) => $this->flatten($graduate))->values()->all();
        $total = count($data);

        return Inertia::render('Mezunlar/Index', [
            'years' => $years,
            'year' => $year,
            'page' => $page,
            'search' => $search,
            'graduates' => [
                'data' => $data,
                'from' => $total > 0 ? 1 : null,
                'to' => $total,
                'total' => $total,
                'prev_page_url' => $page > 1 ? "/mezunlar?page=".($page - 1) : null,
                'next_page_url' => $page < $years->count() ? "/mezunlar?page=".($page + 1) : null,
            ],
            'totalGraduates' => Graduate::count(),
        ]);
    }

    /**
     * @return array{id: int, year: int, number: string, full_name: string, first_name: ?string, last_name: ?string, phone: ?string, email: ?string, photo_path: ?string, educations: array<int, string>, institution_name: ?string, faculty: ?string, department: ?string, company: ?string, job_city: ?string}
     */
    private function flatten(Graduate $graduate): array
    {
        $educations = $graduate->person?->educations ?? collect();
        $employment = $graduate->person?->employments->first();

        return [
            'id' => $graduate->id,
            'year' => $graduate->graduation_year,
            'number' => $graduate->graduation_number,
            'full_name' => $graduate->person?->full_name ?? '—',
            'first_name' => $graduate->person?->first_name,
            'last_name' => $graduate->person?->last_name,
            'phone' => $graduate->person?->phone,
            'email' => $graduate->person?->email,
            'photo_path' => $graduate->person?->photo_path,
            'educations' => $educations
                ->map(fn ($item) => implode(' / ', array_filter([
                    $item->city ?? '',
                    $item->institution_name ?? '',
                    $item->faculty ?? '',
                    $item->department ?? '',
                ])))
                ->filter()
                ->values()
                ->all(),
            'institution_name' => $educations->first()?->institution_name,
            'faculty' => $educations->first()?->faculty,
            'department' => $educations->first()?->department,
            'education_rows' => $educations->map(fn ($item) => [
                'id' => $item->id,
                'city' => $item->city,
                'institution_name' => $item->institution_name,
                'faculty' => $item->faculty,
                'department' => $item->department,
            ])->values()->all(),
            'company' => $employment?->company_name,
            'job_city' => $employment?->city,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            DB::transaction(function () use ($validated) {
                $person = Person::create([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'full_name' => $validated['full_name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                ]);

                $graduate = Graduate::create([
                    'person_id' => $person->id,
                    'student_id' => null,
                    'graduation_year' => $validated['graduation_year'],
                    'graduation_number' => $validated['graduation_number'],
                ]);

                $this->syncEducations($person, $validated);
                $this->syncEmployment($person, $validated);

                if ($validated['photo']) {
                    $this->storePersonPhoto($person, $graduate, $validated['photo']);
                }
            });
        } catch (QueryException $e) {
            return back()->withErrors(['graduation_number' => 'Bu yıl ve numarayla zaten bir mezun kayıtlı.'])->withInput();
        }

        return back()->with('success', 'Mezun eklendi.');
    }

    public function update(Request $request, Graduate $graduate): RedirectResponse
    {
        $validated = $this->validated($request, $graduate);

        try {
            DB::transaction(function () use ($graduate, $validated) {
                $graduate->update([
                    'graduation_year' => $validated['graduation_year'],
                    'graduation_number' => $validated['graduation_number'],
                ]);

                $graduate->person?->update([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'full_name' => $validated['full_name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                ]);

                if ($graduate->person) {
                    $this->syncEducations($graduate->person, $validated);
                    $this->syncEmployment($graduate->person, $validated);

                    if ($validated['photo']) {
                        $this->storePersonPhoto($graduate->person, $graduate, $validated['photo']);
                    }
                }
            });
        } catch (QueryException $e) {
            return back()->withErrors(['graduation_number' => 'Bu yıl ve numarayla zaten bir mezun kayıtlı.'])->withInput();
        }

        return back()->with('success', 'Mezun güncellendi.');
    }

    public function destroy(Graduate $graduate): RedirectResponse
    {
        $name = $graduate->person?->full_name ?? 'Mezun';
        $person = $graduate->person;

        DB::transaction(function () use ($graduate, $person) {
            $graduate->delete();

            // Kişinin başka rolü (öğrenci/öğretmen/veli) yoksa fotoğrafıyla birlikte kaydını da temizle.
            if ($person && ! $person->student && ! $person->teacher && ! $person->guardian) {
                if ($person->photo_path) {
                    Storage::disk('public')->delete($person->photo_path);
                }
                $person->delete();
            }
        });

        return back()->with('success', $name.' silindi.');
    }

    /**
     * @return array{graduation_year: int, graduation_number: string, first_name: string, last_name: string, full_name: string, phone: ?string, email: ?string, educations: array<int, array{id: ?int, city: ?string, institution_name: string, faculty: ?string, department: ?string}>, company: ?string, job_city: ?string, photo: ?UploadedFile}
     */
    private function validated(Request $request, ?Graduate $graduate = null): array
    {
        $data = $request->validate([
            'graduation_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'graduation_number' => [
                'required', 'string', 'max:20',
                Rule::unique('graduates')->where(fn ($query) => $query->where('graduation_year', (int) $request->input('graduation_year')))->ignore($graduate?->id),
            ],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'educations' => ['nullable', 'array'],
            'educations.*.id' => ['nullable', 'integer'],
            'educations.*.city' => ['nullable', 'string', 'max:100'],
            'educations.*.institution_name' => ['required', 'string', 'max:100'],
            'educations.*.faculty' => ['nullable', 'string', 'max:100'],
            'educations.*.department' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:100'],
            'job_city' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'graduation_year.required' => 'Mezuniyet yılı gerekli.',
            'graduation_number.required' => 'Mezuniyet numarası gerekli.',
            'graduation_number.unique' => 'Bu yıl ve numarayla zaten bir mezun kayıtlı.',
            'first_name.required' => 'Ad gerekli.',
            'last_name.required' => 'Soyad gerekli.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'educations.*.institution_name.required' => 'Her eğitim satırında üniversite gerekli.',
            'photo.image' => 'Yalnızca resim dosyası yükleyin.',
            'photo.mimes' => 'Desteklenen formatlar: jpg, jpeg, png, webp.',
            'photo.max' => 'Fotoğraf en fazla 10 MB olabilir.',
            'photo.uploaded' => 'Fotoğraf yüklenemedi, dosya çok büyük olabilir.',
        ]);

        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);

        $nullify = fn ($value) => trim((string) $value) === '' ? null : trim((string) $value);

        $educations = [];
        foreach ($data['educations'] ?? [] as $row) {
            $institution = trim((string) ($row['institution_name'] ?? ''));
            if ($institution === '') {
                continue;
            }
            $educations[] = [
                'id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null,
                'city' => $nullify($row['city'] ?? null),
                'institution_name' => $institution,
                'faculty' => $nullify($row['faculty'] ?? null),
                'department' => $nullify($row['department'] ?? null),
            ];
        }

        return [
            'graduation_year' => (int) $data['graduation_year'],
            'graduation_number' => trim($data['graduation_number']),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $firstName.' '.$lastName,
            'phone' => $nullify($data['phone'] ?? null),
            'email' => $nullify($data['email'] ?? null),
            'educations' => $educations,
            'company' => $nullify($data['company'] ?? null),
            'job_city' => $nullify($data['job_city'] ?? null),
            'photo' => $request->file('photo'),
        ];
    }

    /**
     * @param  array{educations: array<int, array{id: ?int, city: ?string, institution_name: string, faculty: ?string, department: ?string}>}  $validated
     */
    private function syncEducations(Person $person, array $validated): void
    {
        $kept = [];
        foreach ($validated['educations'] as $row) {
            $data = [
                'city' => $row['city'],
                'institution_name' => $row['institution_name'],
                'faculty' => $row['faculty'],
                'department' => $row['department'],
            ];

            if ($row['id'] && ($education = $person->educations()->find($row['id']))) {
                $education->update($data);
                $kept[] = $education->id;
            } else {
                $kept[] = $person->educations()->create($data)->id;
            }
        }

        $person->educations()->whereNotIn('id', $kept)->delete();
    }

    /**
     * @param  array{company: ?string, job_city: ?string}  $validated
     */
    private function syncEmployment(Person $person, array $validated): void
    {
        $employment = $person->employments()->first();

        if ($validated['company'] === null && $validated['job_city'] === null) {
            return;
        }

        $data = [
            'company_name' => $validated['company'] ?? $employment?->company_name ?? '',
            'city' => $validated['job_city'],
        ];

        if ($employment) {
            $employment->update($data);
        } elseif ($validated['company'] !== null) {
            $person->employments()->create($data);
        }
    }

    private function storePersonPhoto(Person $person, Graduate $graduate, UploadedFile $photo): void
    {
        PhotoService::store($photo, "graduates/{$graduate->id}.jpg");

        $person->update(['photo_path' => "graduates/{$graduate->id}.jpg"]);
    }

    public function vcf(Request $request): BinaryFileResponse
    {
        $years = Graduate::distinct()->pluck('graduation_year');
        $year = $request->input('year');
        $year = $year === null || $year === '' ? null : (int) $year;
        if ($year && ! $years->contains($year)) {
            $year = null;
        }
        $search = trim((string) $request->input('q', ''));
        $withPhoto = $request->boolean('photo', true);

        $result = $this->export->buildGraduateVcf($year, $search === '' ? null : $search, $withPhoto);

        $path = tempnam(sys_get_temp_dir(), 'mezun').'.vcf';
        file_put_contents($path, $result['content']);

        return response()->download($path, $result['filename'], [
            'Content-Type' => 'text/vcard; charset=utf-8',
        ])->deleteFileAfterSend();
    }
}
