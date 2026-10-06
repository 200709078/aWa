<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Person;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\PhoneNumber;
use App\Support\SchoolScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class BilgiFormuController extends Controller
{
    public function index(Request $request): Response
    {
        $years = AcademicYear::where('school_id', SchoolScope::id())->orderByDesc('name')->get(['id', 'name', 'is_active']);
        $yearId = $request->integer('academic_year_id')
            ?: $years->firstWhere('is_active', true)?->id
            ?? $years->first()?->id;

        $branches = $yearId
            ? Branch::where('academic_year_id', $yearId)->orderBy('name')->get(['id', 'name'])
            : collect();

        $branchId = $request->integer('branch_id') ?: null;
        $search = trim((string) $request->input('q', ''));
        $status = $request->input('durum', 'tumu');

        $query = Student::with([
            'person:id,first_name,last_name,full_name,phone,chronic_illness,disability,updated_at',
            'enrollments' => fn ($q) => $q->where('academic_year_id', $yearId)->with('branch:id,name'),
            'infoForm',
            'guardians.person:id,first_name,last_name,full_name,education_level,is_alive',
        ])
            ->join('people', 'people.id', '=', 'students.person_id')
            ->select('students.*')
            ->when($yearId, fn ($q) => $q->whereHas('enrollments', fn ($qq) => $qq->where('academic_year_id', $yearId)),
                fn ($q) => $q->whereRaw('0 = 1'))
            ->when($branchId, fn ($q) => $q->whereHas('enrollments', fn ($qq) => $qq
                ->where('academic_year_id', $yearId)->where('branch_id', $branchId)))
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search, $yearId) {
                $qq->where('people.full_name', 'like', "%{$search}%")
                    ->orWhereHas('enrollments', fn ($qqq) => $qqq
                        ->where('academic_year_id', $yearId)
                        ->where('school_number', 'like', "%{$search}%"));
            }))
            ->when($status === 'var', fn ($q) => $q->whereHas('infoForm'))
            ->when($status === 'yok', fn ($q) => $q->whereDoesntHave('infoForm'))
            ->orderBy('people.full_name');

        $paginator = $query->paginate(30)->withQueryString();
        $paginator->setCollection($paginator->getCollection()->map(fn (Student $s) => $this->rowPayload($s)));

        return Inertia::render('BilgiFormaleri/Index', [
            'years' => $years,
            'yearId' => $yearId,
            'branches' => $branches,
            'branchId' => $branchId,
            'search' => $search,
            'status' => $status,
            'students' => $paginator,
            'totalStudents' => $paginator->total(),
        ]);
    }

    /**
     * @return array{id: int, school_number: string, full_name: string, branch_name: string, photo_path: ?string, has_form: bool, form_updated_at: ?string, badges: array<int, string>}
     */
    private function rowPayload(Student $student): array
    {
        $flags = $this->riskFlags($student);
        $badges = [];
        if ($flags['own_ill']) {
            $badges[] = 'Hastalık';
        }
        if ($flags['medication']) {
            $badges[] = 'İlaç';
        }
        if ($flags['disability']) {
            $badges[] = 'Engel';
        }
        if ($flags['earthquake']) {
            $badges[] = 'Deprem Kaybı';
        }
        if ($flags['martyr']) {
            $badges[] = 'Şehit Çocuğu';
        }
        if ($flags['parent_dead']) {
            $badges[] = 'Ebeveyn Kaybı';
        }

        return [
            'id' => $student->id,
            'school_number' => $student->enrollments->first()?->school_number ?? '—',
            'full_name' => $student->person?->full_name ?? '—',
            'branch_name' => $student->enrollments->first()?->branch?->name ?? '—',
            'photo_path' => $student->person?->photo_path,
            'has_form' => $student->infoForm !== null,
            'form_updated_at' => $student->infoForm?->updated_at?->format('d.m.Y'),
            'badges' => $badges,
        ];
    }

    /**
     * Form verisinden türetilebilen risk kriterleri.
     *
     * @return array<string, bool>
     */
    private function riskFlags(Student $student): array
    {
        $person = $student->person;
        $form = $student->infoForm;

        $anne = $this->guardianByRelation($student, 'anne');
        $baba = $this->guardianByRelation($student, 'baba');

        $motherDead = $anne?->person?->is_alive === false;
        $fatherDead = $baba?->person?->is_alive === false;

        return [
            'anne_low_edu' => $this->isLowEducation($anne?->person?->education_level),
            'baba_low_edu' => $this->isLowEducation($baba?->person?->education_level),
            'single_child' => $form?->sibling_count === 1,
            'many_siblings' => $form?->sibling_count !== null && $form->sibling_count >= 5,
            'mother_dead' => $motherDead,
            'father_dead' => $fatherDead,
            'both_dead' => $motherDead && $fatherDead,
            'parent_dead' => $motherDead || $fatherDead,
            'martyr' => $this->isYes($form?->martyr_child),
            'family_ill' => $this->isSet($form?->family_illness),
            'own_ill' => $this->isSet($person?->chronic_illness),
            'medication' => $this->isSet($form?->medication),
            'disability' => $this->isSet($person?->disability) || $this->isSet($form?->family_disability),
            'earthquake' => $this->isYes($form?->earthquake_loss),
            'poor' => $this->isPoor($form?->family_income),
        ];
    }

    private function guardianByRelation(Student $student, string $relation): ?Guardian
    {
        return $student->guardians->first(fn (Guardian $g) => $g->pivot->relationship === $relation);
    }

    private function norm(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = str_replace(['ç', 'ğ', 'ı', 'ö', 'ş', 'ü'], ['c', 'g', 'i', 'o', 's', 'u'], $value);

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }

    private function isEmptyVal(?string $value): bool
    {
        if ($value === null) {
            return true;
        }
        $t = trim($value);

        return $t === '' || $t === '-' || $t === '—' || mb_strtolower($t, 'UTF-8') === 'yok' || mb_strtolower($t, 'UTF-8') === 'hayır';
    }

    private function isSet(?string $value): bool
    {
        return ! $this->isEmptyVal($value);
    }

    private function isYes(?string $value): bool
    {
        return str_starts_with($this->norm($value), 'evet');
    }

    private function isLowEducation(?string $value): bool
    {
        return in_array($this->norm($value), ['ilkokul', 'ilkokulmezunu', 'okuryazar', 'okuryazardegil', 'okumayazmabilmiyor'], true);
    }

    private function isPoor(?string $value): bool
    {
        $n = $this->norm($value);

        return $n !== '' && (str_contains($n, 'dusuk') || str_contains($n, 'yetersiz') || str_contains($n, 'kotu'));
    }

    // ------------------------------------------------------------------
    // İçe aktarma
    // ------------------------------------------------------------------

    public function importShow(): Response
    {
        $years = AcademicYear::where('school_id', SchoolScope::id())->orderByDesc('name')->get(['id', 'name', 'is_active']);
        $activeId = $years->firstWhere('is_active', true)?->id ?? $years->first()?->id;

        return Inertia::render('BilgiFormaleri/Import', [
            'years' => $years,
            'activeYearId' => $activeId,
        ]);
    }

    public function importStore(Request $request): Response|RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('school_id', SchoolScope::id())],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'overwrite' => ['sometimes', 'boolean'],
        ], [
            'academic_year_id.required' => 'Akademik yıl seçin.',
            'file.required' => 'Excel dosyası seçin.',
            'file.mimes' => 'Yalnızca .xlsx veya .xls dosyası yükleyin.',
            'file.max' => 'Dosya en fazla 10 MB olabilir.',
        ]);

        $year = AcademicYear::findOrFail($data['academic_year_id']);
        $overwrite = $request->boolean('overwrite');

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        $reader = $ext === 'xls' ? new Xls() : new Xlsx();
        $reader->setReadDataOnly(true);
        $values = $reader->load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        $values = array_values(array_filter($values, fn ($row) => collect($row)->some(fn ($cell) => trim((string) $cell) !== '')));

        if ($values === []) {
            return back()->withErrors(['file' => 'Dosyada okunabilir satır bulunamadı.']);
        }

        $headers = array_map(fn ($cell) => trim((string) $cell), array_shift($values));
        $mapping = [];
        $unmapped = [];
        foreach ($headers as $index => $header) {
            $resolved = $this->resolveField($header);
            if ($resolved) {
                $mapping[$index] = $resolved;
            } else {
                $unmapped[] = $header;
            }
        }

        if (! in_array('number', array_column($mapping, 'field'), true)) {
            return back()->withErrors(['file' => 'Dosyada okul numarası sütunu bulunamadı.']);
        }

        $result = $this->processImportRows($values, $mapping, $year->id, $overwrite);

        return Inertia::render('BilgiFormaleri/ImportResult', [
            'year' => $year->only('id', 'name'),
            'summary' => $result['summary'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
            'unmapped' => array_values(array_unique($unmapped)),
        ]);
    }

    /**
     * @return ?array{owner: string, field: string}
     */
    private function resolveField(string $header): ?array
    {
        $h = $this->norm($header);
        if ($h === '' || $h === 'zamandamgasi') {
            return null;
        }

        $owner = 'student';
        foreach (['annenizin' => 'mother', 'babanizin' => 'father', 'anneniz' => 'mother', 'babaniz' => 'father', 'velinizin' => 'guardian', 'veliniz' => 'guardian'] as $prefix => $o) {
            if (str_starts_with($h, $prefix)) {
                $owner = $o;
                $h = substr($h, strlen($prefix));
                break;
            }
        }

        if ($owner !== 'student') {
            if (str_contains($h, 'adini')) {
                return ['owner' => $owner, 'field' => 'name'];
            }
            if (str_contains($h, 'telefon')) {
                return ['owner' => $owner, 'field' => 'phone'];
            }
            if (str_contains($h, 'dogumyeri') || str_contains($h, 'dogrumyeri')) {
                return ['owner' => $owner, 'field' => 'birth_place'];
            }
            if (str_contains($h, 'dogumtarihi')) {
                return ['owner' => $owner, 'field' => 'birth_date'];
            }
            if (str_contains($h, 'egitim')) {
                return ['owner' => $owner, 'field' => 'education'];
            }
            if (str_contains($h, 'mesleg')) {
                return ['owner' => $owner, 'field' => 'occupation'];
            }
            if (str_contains($h, 'ozmu')) {
                return ['owner' => $owner, 'field' => 'biological'];
            }
            if (str_contains($h, 'sagmi')) {
                return ['owner' => $owner, 'field' => 'alive'];
            }
            if (str_contains($h, 'engeli')) {
                return ['owner' => $owner, 'field' => 'disability'];
            }
            if (str_contains($h, 'hastaligi')) {
                return ['owner' => $owner, 'field' => 'illness'];
            }
            if ($owner === 'guardian' && ($h === 'kim' || str_contains($h, 'yakinlig'))) {
                return ['owner' => 'guardian', 'field' => 'relation'];
            }

            return null;
        }

        $map = [
            'epostaadresi' => 'email',
            'okulnumaraniz' => 'number',
            'adinizsoyadiniz' => 'name',
            'cinsiyet' => 'gender',
            'telefon' => 'phone',
            'sinifiniz' => 'class',
            'kangrubu' => 'blood',
            'dininiz' => 'religion',
            'deprem' => 'earthquake_loss',
            'gelirdurumu' => 'family_income',
            'tasima' => 'transport',
            'ogleyemegi' => 'free_lunch',
            'sehit' => 'martyr_child',
            'boyunuz' => 'height',
            'ikamet' => 'address',
            'okuloncesi' => 'preschool',
            'tibbicihaz' => 'medical_device',
            'ilac' => 'medication',
            'hoslanir' => 'hobbies',
            'hastaliginizvarmi' => 'chronic_illness',
            'tasindinizmi' => 'moved',
            'okuldegistir' => 'changed_school',
            'dersdisi' => 'extracurricular',
            'teknolojik' => 'tech_devices',
            'etkisinden' => 'trauma',
            'kackardes' => 'sibling_count',
            'kacinci' => 'birth_order',
            'okulagiden' => 'school_siblings',
            'engeliolan' => 'family_disability',
            'hastaligiolan' => 'family_illness',
            'kimkimler' => 'household',
            'notlar' => 'notes',
            'kilonuz' => 'weight',
        ];

        foreach ($map as $key => $field) {
            if (str_contains($h, $key)) {
                return ['owner' => 'student', 'field' => $field];
            }
        }

        if (str_contains($h, 'dogrumyeri') || str_contains($h, 'dogumyeri')) {
            return ['owner' => 'student', 'field' => 'birth_place'];
        }
        if (str_contains($h, 'dogumtarihi')) {
            return ['owner' => 'student', 'field' => 'birth_date'];
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{owner: string, field: string}>  $mapping
     * @return array{summary: array<string, int>, errors: array<int, array{line: int, number: string, message: string}>, warnings: array<int, array{line: int, number: string, message: string}>}
     */
    private function processImportRows(array $rows, array $mapping, int $yearId, bool $overwrite): array
    {
        $enrollments = StudentEnrollment::where('academic_year_id', $yearId)
            ->with(['branch:id,name', 'student.person', 'student.guardians.person', 'student.infoForm'])
            ->get()
            ->keyBy(fn (StudentEnrollment $e) => trim((string) $e->school_number));

        $summary = ['toplam' => 0, 'islendi' => 0, 'form_olustu' => 0, 'form_guncellendi' => 0, 'atlandi' => 0];
        $errors = [];
        $warnings = [];
        $seen = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $cells = [];
            foreach ($mapping as $index => $resolved) {
                $cells[$resolved['owner'].'.'.$resolved['field']] = trim((string) ($row[$index] ?? ''));
            }

            $number = $this->normalizeNumber($cells['student.number'] ?? null);
            $summary['toplam']++;

            if ($number === '') {
                $errors[] = ['line' => $line, 'number' => '—', 'message' => 'Okul numarası boş.'];
                $summary['atlandi']++;
                continue;
            }
            if (isset($seen[$number])) {
                $errors[] = ['line' => $line, 'number' => $number, 'message' => 'Bu numara dosyada tekrar ediyor.'];
                $summary['atlandi']++;
                continue;
            }
            $seen[$number] = true;

            $enrollment = $enrollments->get($number);
            if (! $enrollment || ! $enrollment->student) {
                $errors[] = ['line' => $line, 'number' => $number, 'message' => 'Bu numaralı öğrenci bu akademik yılda bulunamadı.'];
                $summary['atlandi']++;
                continue;
            }

            $importBranch = StudentImportController::normalizeBranch($cells['student.class'] ?? null);
            $systemBranch = StudentImportController::normalizeBranch($enrollment->branch?->name);
            if ($importBranch !== '' && $systemBranch !== '' && $importBranch !== $systemBranch) {
                $warnings[] = ['line' => $line, 'number' => $number, 'message' => "Sınıf uyuşmuyor (formda {$importBranch}, sistemde {$systemBranch}); satır işlenmedi."];
                $summary['atlandi']++;
                continue;
            }

            try {
                DB::transaction(function () use ($enrollment, $cells, $overwrite, &$summary) {
                    $this->applyImportRow($enrollment->student, $cells, $overwrite, $summary);
                });
            } catch (\Illuminate\Validation\ValidationException|\Illuminate\Database\QueryException $e) {
                $errors[] = ['line' => $line, 'number' => $number, 'message' => 'Satır işlenemedi (geçersiz veri).'];
                $summary['atlandi']++;
                continue;
            }
            $summary['islendi']++;
        }

        return ['summary' => $summary, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, string>  $cells
     */
    private function applyImportRow(Student $student, array $cells, bool $overwrite, array &$summary): void
    {
        $student->loadMissing(['person', 'guardians.person', 'infoForm']);

        $personVals = [
            'email' => $this->cleanEmail($cells['student.email'] ?? null),
            'phone' => $this->cleanPhone($cells['student.phone'] ?? null),
            'address' => $this->cleanText($cells['student.address'] ?? null),
            'gender' => $this->parseGender($cells['student.gender'] ?? null),
            'birth_place' => $this->cleanText($cells['student.birth_place'] ?? null),
            'birth_date' => $this->parseDate($cells['student.birth_date'] ?? null),
            'blood_type' => $this->parseBlood($cells['student.blood'] ?? null),
            'religion' => $this->cleanText($cells['student.religion'] ?? null),
            'height_cm' => $this->parseInt($cells['student.height'] ?? null),
            'weight_kg' => $this->parseInt($cells['student.weight'] ?? null),
            'chronic_illness' => $this->cleanText($cells['student.chronic_illness'] ?? null),
        ];
        if ($student->person) {
            $this->applyPersonFields($student->person, $personVals, $overwrite);
        }

        $this->applyGuardianBlock($student, 'anne', $this->guardianVals($cells, 'mother'), $overwrite);
        $this->applyGuardianBlock($student, 'baba', $this->guardianVals($cells, 'father'), $overwrite);

        $role = $this->parseRelation($cells['guardian.relation'] ?? null);
        $veliVals = $this->guardianVals($cells, 'guardian');
        if ($role === 'anne' || $role === 'baba') {
            $target = $this->applyGuardianBlock($student, $role, $veliVals, $overwrite, true);
            if ($target) {
                $this->setPrimary($student, $target->id);
            }
        } elseif ($this->blockFilled($veliVals)) {
            $target = $this->applyGuardianBlock($student, $role ?? 'veli', $veliVals, $overwrite);
            if ($target) {
                $this->setPrimary($student, $target->id);
            }
        } elseif ($role) {
            $existing = $student->guardians->first(fn (Guardian $g) => $g->pivot->relationship === $role);
            if ($existing) {
                $this->setPrimary($student, $existing->id);
            }
        }

        $formVals = [
            'earthquake_loss' => $this->cleanText($cells['student.earthquake_loss'] ?? null),
            'family_income' => $this->cleanText($cells['student.family_income'] ?? null),
            'transport' => $this->cleanText($cells['student.transport'] ?? null),
            'free_lunch' => $this->cleanText($cells['student.free_lunch'] ?? null),
            'martyr_child' => $this->cleanText($cells['student.martyr_child'] ?? null),
            'preschool' => $this->cleanText($cells['student.preschool'] ?? null),
            'medication' => $this->cleanText($cells['student.medication'] ?? null),
            'medical_device' => $this->cleanText($cells['student.medical_device'] ?? null),
            'hobbies' => $this->cleanText($cells['student.hobbies'] ?? null),
            'moved' => $this->cleanText($cells['student.moved'] ?? null),
            'changed_school' => $this->cleanText($cells['student.changed_school'] ?? null),
            'extracurricular' => $this->cleanText($cells['student.extracurricular'] ?? null),
            'tech_devices' => $this->cleanText($cells['student.tech_devices'] ?? null),
            'trauma' => $this->cleanText($cells['student.trauma'] ?? null),
            'sibling_count' => $this->parseInt($cells['student.sibling_count'] ?? null),
            'birth_order' => $this->parseInt($cells['student.birth_order'] ?? null),
            'school_siblings' => $this->parseInt($cells['student.school_siblings'] ?? null),
            'family_disability' => $this->cleanText($cells['student.family_disability'] ?? null),
            'family_illness' => $this->cleanText($cells['student.family_illness'] ?? null),
            'household' => $this->cleanText($cells['student.household'] ?? null),
            'notes' => $this->cleanText($cells['student.notes'] ?? null),
        ];

        $form = $student->infoForm;
        if (! $form) {
            $filled = array_filter($formVals, fn ($v) => $v !== null);
            if ($filled !== []) {
                $student->infoForm()->create($filled);
                $summary['form_olustu']++;
            }
        } else {
            $updates = [];
            foreach ($formVals as $key => $incoming) {
                if ($incoming === null) {
                    continue;
                }
                if ($overwrite || $form->{$key} === null || trim((string) $form->{$key}) === '') {
                    $updates[$key] = $incoming;
                }
            }
            if ($updates !== []) {
                $form->update($updates);
            }
            $summary['form_guncellendi']++;
        }
    }

    /**
     * @param  array<string, string>  $cells
     * @return array<string, mixed>
     */
    private function guardianVals(array $cells, string $owner): array
    {
        return [
            'name' => $this->cleanText($cells[$owner.'.name'] ?? null),
            'phone' => $this->cleanPhone($cells[$owner.'.phone'] ?? null),
            'birth_place' => $this->cleanText($cells[$owner.'.birth_place'] ?? null),
            'birth_date' => $this->parseDate($cells[$owner.'.birth_date'] ?? null),
            'education' => $this->cleanText($cells[$owner.'.education'] ?? null),
            'occupation' => $this->cleanText($cells[$owner.'.occupation'] ?? null),
            'biological' => $this->parseYesNo($cells[$owner.'.biological'] ?? null),
            'alive' => $this->parseYesNo($cells[$owner.'.alive'] ?? null),
            'disability' => $this->cleanText($cells[$owner.'.disability'] ?? null),
            'illness' => $this->cleanText($cells[$owner.'.illness'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $vals
     */
    private function blockFilled(array $vals): bool
    {
        foreach (['name', 'phone', 'birth_place', 'birth_date', 'education', 'occupation', 'disability', 'illness'] as $key) {
            if ($vals[$key] !== null && trim((string) $vals[$key]) !== '') {
                return true;
            }
        }

        return $vals['biological'] !== null || $vals['alive'] !== null;
    }

    /**
     * @param  array<string, mixed>  $vals
     */
    private function applyGuardianBlock(Student $student, string $relation, array $vals, bool $overwrite, bool $allowNameless = false): ?Guardian
    {
        $link = $student->guardians->first(fn (Guardian $g) => $g->pivot->relationship === $relation);

        if (! $link) {
            if (! $this->blockFilled($vals)) {
                return null;
            }
            if (empty($vals['name']) && ! $allowNameless) {
                return null;
            }
            [$first, $last] = StudentImportController::splitName((string) ($vals['name'] ?? '—'));
            $person = Person::create([
                'school_id' => $student->school_id,
                'first_name' => $first ?? (string) ($vals['name'] ?? '—'),
                'last_name' => $last ?? '',
                'full_name' => (string) ($vals['name'] ?? '—'),
                'phone' => $vals['phone'] ? PhoneNumber::normalizeOrFail($vals['phone'], 'phone', 'Geçerli bir telefon girin.') : null,
            ]);
            $guardian = Guardian::create(['person_id' => $person->id]);
            $student->guardians()->attach($guardian->id, ['relationship' => $relation, 'is_primary' => false]);
            $student->load('guardians.person');
            $link = $student->guardians->first(fn (Guardian $g) => $g->pivot->relationship === $relation);
        }

        if ($link?->person) {
            $personVals = [
                'phone' => $vals['phone'],
                'birth_place' => $vals['birth_place'],
                'birth_date' => $vals['birth_date'],
                'education_level' => $vals['education'],
                'occupation' => $vals['occupation'],
                'is_alive' => $vals['alive'],
                'disability' => $vals['disability'],
                'chronic_illness' => $vals['illness'],
            ];
            if (! empty($vals['name'])) {
                [$first, $last] = StudentImportController::splitName((string) $vals['name']);
                $personVals['first_name'] = $overwrite || empty($link->person->first_name) ? ($first ?? $vals['name']) : $link->person->first_name;
                $personVals['last_name'] = $overwrite || empty($link->person->last_name) ? ($last ?? '') : $link->person->last_name;
                $personVals['full_name'] = $overwrite || empty($link->person->full_name) ? $vals['name'] : $link->person->full_name;
            }
            $this->applyPersonFields($link->person, $personVals, $overwrite);

            if ($vals['biological'] !== null && ($overwrite || $link->pivot->is_biological === null)) {
                $student->guardians()->updateExistingPivot($link->id, ['is_biological' => $vals['biological']]);
            }
        }

        return $link;
    }

    private function setPrimary(Student $student, int $guardianId): void
    {
        foreach ($student->guardians as $guardian) {
            $student->guardians()->updateExistingPivot($guardian->id, ['is_primary' => $guardian->id === $guardianId]);
        }
    }

    /**
     * @param  array<string, mixed>  $vals
     */
    private function applyPersonFields(Person $person, array $vals, bool $overwrite): void
    {
        $updates = [];
        foreach ($vals as $key => $incoming) {
            if ($incoming === null || $incoming === '') {
                continue;
            }
            $current = $person->{$key};
            if ($current instanceof \DateTimeInterface) {
                $current = $current->format('Y-m-d');
            }
            if ($overwrite || $current === null || trim((string) $current) === '') {
                $updates[$key] = $incoming;
            }
        }

        if ($updates !== []) {
            if (isset($updates['phone'])) {
                $updates['phone'] = PhoneNumber::normalizeOrFail($updates['phone'], 'phone', 'Geçerli bir telefon girin (örn. 0532 666 65 49).');
            }
            $person->update($updates);
        }
    }

    private function normalizeNumber(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $text = trim((string) $value);
        if ($text !== '' && is_numeric($text)) {
            $float = (float) $text;
            if ($float == (int) $float) {
                return (string) (int) $float;
            }
        }

        return $text;
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '-') {
            return null;
        }

        return $text;
    }

    private function cleanPhone(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return null;
        }
        if (is_numeric($text) && str_contains($text, '.')) {
            $float = (float) $text;
            if ($float == (int) $float) {
                $text = (string) (int) $float;
            }
        }

        return $text;
    }

    private function cleanEmail(mixed $value): ?string
    {
        $text = mb_strtolower(trim((string) ($value ?? '')), 'UTF-8');
        if ($text === '' || $text === '-') {
            return null;
        }

        return filter_var($text, FILTER_VALIDATE_EMAIL) ? $text : null;
    }

    private function parseGender(mixed $value): ?string
    {
        return match ($this->norm(is_string($value) ? $value : null)) {
            'kiz', 'k' => 'Kız',
            'erkek', 'e' => 'Erkek',
            default => null,
        };
    }

    private function parseRelation(?string $value): ?string
    {
        $n = $this->norm($value);
        if ($n === '') {
            return null;
        }
        if (str_contains($n, 'anne')) {
            return 'anne';
        }
        if (str_contains($n, 'baba')) {
            return 'baba';
        }
        if (str_contains($n, 'vasi')) {
            return 'vasi';
        }
        if (str_contains($n, 'veli')) {
            return 'veli';
        }

        return null;
    }

    private function parseYesNo(mixed $value): ?bool
    {
        return match ($this->norm(is_string($value) ? $value : null)) {
            'evet' => true,
            'hayir' => false,
            default => null,
        };
    }

    private function parseBlood(mixed $value): ?string
    {
        $map = [
            'arh+' => 'A Rh+', 'arh-' => 'A Rh-',
            'brh+' => 'B Rh+', 'brh-' => 'B Rh-',
            'abrh+' => 'AB Rh+', 'abrh-' => 'AB Rh-',
            '0rh+' => '0 Rh+', '0rh-' => '0 Rh-',
            'orh+' => '0 Rh+', 'orh-' => '0 Rh-',
        ];

        $n = mb_strtolower(trim((string) ($value ?? '')), 'UTF-8');
        $n = str_replace(['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', ' '], ['c', 'g', 'i', 'o', 's', 'u', ''], $n);
        $n = preg_replace('/[^a-z0-9+\-]/', '', $n) ?? '';

        return $map[$n] ?? null;
    }

    private function parseInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (preg_match('/(\d+)/', (string) $value, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            $ts = (((float) $value) - 25569) * 86400;

            return gmdate('Y-m-d', (int) $ts);
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d', 'd.m.Y', 'd/m/Y'] as $format) {
            $dt = \DateTime::createFromFormat($format, $text);
            if ($dt && $dt->format($format) === $text) {
                return $dt->format('Y-m-d');
            }
        }
        $ts = strtotime($text);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    // ------------------------------------------------------------------
    // Düzenleme (tam sayfa)
    // ------------------------------------------------------------------

    public function edit(Request $request, Student $student): Response
    {
        SchoolScope::ensure($student);

        $student->load([
            'person',
            'guardians.person',
            'infoForm',
        ]);

        $enrollment = $student->enrollments()->with('branch:id,name,academic_year_id')->latest('id')->first();

        $guardianPayload = function (?Guardian $link): array {
            return [
                'name' => $link?->person?->full_name ?? '',
                'phone' => $link?->person?->phone ?? '',
                'birth_place' => $link?->person?->birth_place ?? '',
                'birth_date' => $link?->person?->birth_date?->format('Y-m-d') ?? '',
                'education' => $link?->person?->education_level ?? '',
                'occupation' => $link?->person?->occupation ?? '',
                'biological' => $link?->pivot->is_biological,
                'alive' => $link?->person?->is_alive,
                'disability' => $link?->person?->disability ?? '',
                'illness' => $link?->person?->chronic_illness ?? '',
            ];
        };

        $primary = $student->guardians->first(fn (Guardian $g) => (bool) $g->pivot->is_primary);

        return Inertia::render('BilgiFormaleri/Form', [
            'student' => [
                'id' => $student->id,
                'school_number' => $enrollment?->school_number ?? '—',
                'branch_name' => $enrollment?->branch?->name ?? '—',
                'full_name' => $student->person?->full_name ?? '—',
            ],
            'person' => [
                'email' => $student->person?->email ?? '',
                'phone' => $student->person?->phone ?? '',
                'address' => $student->person?->address ?? '',
                'gender' => $student->person?->gender,
                'birth_place' => $student->person?->birth_place ?? '',
                'birth_date' => $student->person?->birth_date?->format('Y-m-d') ?? '',
                'blood_type' => $student->person?->blood_type,
                'religion' => $student->person?->religion ?? '',
                'height_cm' => $student->person?->height_cm,
                'weight_kg' => $student->person?->weight_kg,
                'chronic_illness' => $student->person?->chronic_illness ?? '',
            ],
            'guardian' => $guardianPayload($primary),
            'guardian_relation' => $primary?->pivot->relationship ?? 'veli',
            'mother' => $guardianPayload($this->guardianByRelation($student, 'anne')),
            'father' => $guardianPayload($this->guardianByRelation($student, 'baba')),
            'form' => $student->infoForm?->only([
                'earthquake_loss', 'family_income', 'transport', 'free_lunch', 'martyr_child',
                'preschool', 'medication', 'medical_device', 'hobbies', 'moved', 'changed_school',
                'extracurricular', 'tech_devices', 'trauma', 'sibling_count', 'birth_order',
                'school_siblings', 'family_disability', 'family_illness', 'household', 'notes',
            ]) ?? [],
            'backUrl' => $this->formBackUrl($request),
        ]);
    }

    private function formBackUrl(Request $request): string
    {
        $params = array_filter([
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
            'q' => trim((string) $request->input('q', '')) ?: null,
            'durum' => $request->input('durum') ?: null,
        ]);

        return '/bilgi-formlari'.($params === [] ? '' : '?'.http_build_query($params));
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        SchoolScope::ensure($student);

        $data = $request->validate([
            'person.email' => ['nullable', 'email', 'max:100'],
            'person.phone' => ['nullable', 'string', 'max:30'],
            'person.address' => ['nullable', 'string', 'max:500'],
            'person.gender' => ['nullable', Rule::in(['Kız', 'Erkek'])],
            'person.birth_place' => ['nullable', 'string', 'max:100'],
            'person.birth_date' => ['nullable', 'date'],
            'person.blood_type' => ['nullable', Rule::in(['A Rh+', 'A Rh-', 'B Rh+', 'B Rh-', 'AB Rh+', 'AB Rh-', '0 Rh+', '0 Rh-'])],
            'person.religion' => ['nullable', 'string', 'max:50'],
            'person.height_cm' => ['nullable', 'integer', 'min:50', 'max:250'],
            'person.weight_kg' => ['nullable', 'integer', 'min:15', 'max:300'],
            'person.chronic_illness' => ['nullable', 'string', 'max:255'],
            'guardian_relation' => ['nullable', Rule::in(['anne', 'baba', 'veli', 'vasi'])],
            'guardian.name' => ['nullable', 'string', 'max:100'],
            'guardian.phone' => ['nullable', 'string', 'max:30'],
            'guardian.birth_place' => ['nullable', 'string', 'max:100'],
            'guardian.birth_date' => ['nullable', 'date'],
            'guardian.education' => ['nullable', 'string', 'max:50'],
            'guardian.occupation' => ['nullable', 'string', 'max:100'],
            'guardian.biological' => ['nullable', 'boolean'],
            'guardian.alive' => ['nullable', 'boolean'],
            'guardian.disability' => ['nullable', 'string', 'max:255'],
            'guardian.illness' => ['nullable', 'string', 'max:255'],
            'mother.name' => ['nullable', 'string', 'max:100'],
            'mother.phone' => ['nullable', 'string', 'max:30'],
            'mother.birth_place' => ['nullable', 'string', 'max:100'],
            'mother.birth_date' => ['nullable', 'date'],
            'mother.education' => ['nullable', 'string', 'max:50'],
            'mother.occupation' => ['nullable', 'string', 'max:100'],
            'mother.biological' => ['nullable', 'boolean'],
            'mother.alive' => ['nullable', 'boolean'],
            'mother.disability' => ['nullable', 'string', 'max:255'],
            'mother.illness' => ['nullable', 'string', 'max:255'],
            'father.name' => ['nullable', 'string', 'max:100'],
            'father.phone' => ['nullable', 'string', 'max:30'],
            'father.birth_place' => ['nullable', 'string', 'max:100'],
            'father.birth_date' => ['nullable', 'date'],
            'father.education' => ['nullable', 'string', 'max:50'],
            'father.occupation' => ['nullable', 'string', 'max:100'],
            'father.biological' => ['nullable', 'boolean'],
            'father.alive' => ['nullable', 'boolean'],
            'father.disability' => ['nullable', 'string', 'max:255'],
            'father.illness' => ['nullable', 'string', 'max:255'],
            'form.earthquake_loss' => ['nullable', 'string', 'max:50'],
            'form.family_income' => ['nullable', 'string', 'max:50'],
            'form.transport' => ['nullable', 'string', 'max:50'],
            'form.free_lunch' => ['nullable', 'string', 'max:50'],
            'form.martyr_child' => ['nullable', 'string', 'max:50'],
            'form.preschool' => ['nullable', 'string', 'max:50'],
            'form.medication' => ['nullable', 'string', 'max:255'],
            'form.medical_device' => ['nullable', 'string', 'max:255'],
            'form.hobbies' => ['nullable', 'string', 'max:500'],
            'form.moved' => ['nullable', 'string', 'max:50'],
            'form.changed_school' => ['nullable', 'string', 'max:50'],
            'form.extracurricular' => ['nullable', 'string', 'max:500'],
            'form.tech_devices' => ['nullable', 'string', 'max:500'],
            'form.trauma' => ['nullable', 'string', 'max:500'],
            'form.sibling_count' => ['nullable', 'integer', 'min:0', 'max:30'],
            'form.birth_order' => ['nullable', 'integer', 'min:0', 'max:30'],
            'form.school_siblings' => ['nullable', 'integer', 'min:0', 'max:30'],
            'form.family_disability' => ['nullable', 'string', 'max:255'],
            'form.family_illness' => ['nullable', 'string', 'max:255'],
            'form.household' => ['nullable', 'string', 'max:500'],
            'form.notes' => ['nullable', 'string'],
        ]);

        $nullify = fn ($v) => trim((string) ($v ?? '')) === '' ? null : trim((string) $v);
        $personVals = $data['person'] ?? [];
        foreach (['email', 'phone', 'address', 'birth_place', 'religion', 'chronic_illness'] as $k) {
            $personVals[$k] = $nullify($personVals[$k] ?? null);
        }
        foreach (['gender', 'birth_date', 'blood_type', 'height_cm', 'weight_kg'] as $k) {
            if (($personVals[$k] ?? null) === '') {
                $personVals[$k] = null;
            }
        }

        DB::transaction(function () use ($student, $data, $personVals, $nullify) {
            $student->loadMissing(['person', 'guardians.person', 'infoForm']);

            if ($student->person) {
                $student->person->update($personVals);
            }

            $mother = $this->writeGuardianBlock($student, 'anne', $data['mother'] ?? [], $nullify);
            $father = $this->writeGuardianBlock($student, 'baba', $data['father'] ?? [], $nullify);

            $role = $data['guardian_relation'] ?? 'veli';
            if ($role === 'anne' && $mother) {
                $this->writeGuardianBlock($student, 'anne', $data['guardian'] ?? [], $nullify, $mother);
                $this->setPrimary($student, $mother->id);
            } elseif ($role === 'baba' && $father) {
                $this->writeGuardianBlock($student, 'baba', $data['guardian'] ?? [], $nullify, $father);
                $this->setPrimary($student, $father->id);
            } else {
                $veli = $this->writeGuardianBlock($student, $role, $data['guardian'] ?? [], $nullify);
                if ($veli) {
                    $this->setPrimary($student, $veli->id);
                }
            }

            $formVals = [];
            foreach ($data['form'] ?? [] as $key => $value) {
                $formVals[$key] = is_int($value) ? $value : $nullify($value);
            }
            if ($student->infoForm) {
                $student->infoForm->update($formVals);
            } elseif (collect($formVals)->some(fn ($v) => $v !== null)) {
                $student->infoForm()->create($formVals);
            }
        });

        return redirect()->route('bilgi-formlari.index')->with('success', 'Bilgi formu kaydedildi.');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function writeGuardianBlock(Student $student, string $relation, array $input, callable $nullify, ?Guardian $existing = null): ?Guardian
    {
        $student->loadMissing('guardians.person');
        $link = $existing ?? $student->guardians->first(fn (Guardian $g) => $g->pivot->relationship === $relation);

        $filled = $link !== null;
        if (! $filled) {
            foreach (['name', 'phone', 'birth_place', 'birth_date', 'education', 'occupation', 'disability', 'illness'] as $k) {
                if ($nullify($input[$k] ?? null) !== null) {
                    $filled = true;
                    break;
                }
            }
            if (! $filled && (($input['biological'] ?? null) !== null || ($input['alive'] ?? null) !== null)) {
                $filled = true;
            }
        }
        if (! $link && ! $filled) {
            return null;
        }

        if (! $link) {
            $name = $nullify($input['name'] ?? null) ?? '—';
            [$first, $last] = StudentImportController::splitName($name);
            $person = Person::create([
                'school_id' => $student->school_id,
                'first_name' => $first ?? $name,
                'last_name' => $last ?? '',
                'full_name' => $name,
                'phone' => isset($input['phone']) ? PhoneNumber::normalizeOrFail($nullify($input['phone']), 'phone', 'Geçerli bir telefon girin.') : null,
            ]);
            $link = Guardian::create(['person_id' => $person->id]);
            $student->guardians()->attach($link->id, ['relationship' => $relation, 'is_primary' => false]);
            $student->load('guardians.person');
            $link = $student->guardians->first(fn (Guardian $g) => $g->pivot->relationship === $relation);
        }

        if ($link?->person) {
            $name = $nullify($input['name'] ?? null);
            $updates = [
                'phone' => isset($input['phone']) ? PhoneNumber::normalizeOrFail($nullify($input['phone']), 'phone', 'Geçerli bir telefon girin.') : null,
                'birth_place' => $nullify($input['birth_place'] ?? null),
                'birth_date' => empty($input['birth_date']) ? null : $input['birth_date'],
                'education_level' => $nullify($input['education'] ?? null),
                'occupation' => $nullify($input['occupation'] ?? null),
                'is_alive' => $input['alive'] ?? null,
                'disability' => $nullify($input['disability'] ?? null),
                'chronic_illness' => $nullify($input['illness'] ?? null),
            ];
            if ($name !== null) {
                [$first, $last] = StudentImportController::splitName($name);
                $updates['first_name'] = $first ?? $name;
                $updates['last_name'] = $last ?? '';
                $updates['full_name'] = $name;
            }
            $link->person->update($updates);

            if (array_key_exists('biological', $input)) {
                $student->guardians()->updateExistingPivot($link->id, ['is_biological' => $input['biological']]);
            }
        }

        return $link;
    }

    // ------------------------------------------------------------------
    // Yazdırma (tek + toplu, tek sayfa)
    // ------------------------------------------------------------------

    public function print(Request $request): Response
    {
        $ids = collect(explode(',', (string) $request->input('ids', '')))
            ->map(fn ($v) => (int) trim($v))->filter(fn ($v) => $v > 0)->unique()->values();

        $students = $ids->isEmpty() ? collect() : Student::with([
            'person',
            'guardians.person',
            'infoForm',
            'enrollments' => fn ($q) => $q->with('branch:id,name')->latest('id'),
        ])->whereIn('id', $ids->all())->get()->keyBy('id');

        $ordered = $ids->map(fn ($id) => $students->get($id))->filter();

        return Inertia::render('Prints/BilgiFormu', [
            'forms' => $ordered->map(fn (Student $s) => $this->printPayload($s))->values()->all(),
            'school' => ['name' => \App\Models\School::find(SchoolScope::id())?->name ?? ''],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function printPayload(Student $student): array
    {
        $mother = $this->guardianByRelation($student, 'anne');
        $father = $this->guardianByRelation($student, 'baba');
        $primary = $student->guardians->first(fn (Guardian $g) => (bool) $g->pivot->is_primary);
        $enrollment = $student->enrollments->first();
        $form = $student->infoForm;

        $g = fn (?Guardian $link) => [
            'name' => $link?->person?->full_name ?? '',
            'phone' => $link?->person?->phone ?? '',
            'birth' => trim(($link?->person?->birth_place ?? '').' / '.($link?->person?->birth_date?->format('d.m.Y') ?? ''), ' /'),
            'alive' => $link?->person?->is_alive === null ? '' : ($link->person->is_alive ? 'Evet' : 'Hayır'),
            'biological' => $link?->pivot->is_biological === null ? '' : ($link->pivot->is_biological ? 'Evet' : 'Hayır'),
            'disability' => $link?->person?->disability ?? '',
            'education' => $link?->person?->education_level ?? '',
            'job' => $link?->person?->occupation ?? '',
        ];

        return [
            'full_name' => $student->person?->full_name ?? '',
            'gender' => $student->person?->gender ?? '',
            'number' => $enrollment?->school_number ?? '',
            'branch' => $enrollment?->branch?->name ?? '',
            'birth' => trim(($student->person?->birth_place ?? '').' / '.($student->person?->birth_date?->format('d.m.Y') ?? ''), ' /'),
            'address' => $student->person?->address ?? '',
            'preschool' => $form?->preschool ?? '',
            'medication' => $form?->medical_device ? trim(($form->medication ?? '').' / '.$form->medical_device, ' /') : ($form?->medication ?? ''),
            'hobbies' => $form?->hobbies ?? '',
            'illness' => $student->person?->chronic_illness ?? '',
            'moved' => $form && ($form->moved || $form->changed_school)
                ? trim(($form->moved ?? '').' / '.($form->changed_school ?? ''), ' /') : '',
            'extracurricular' => $form?->extracurricular ?? '',
            'tech' => $form?->tech_devices ?? '',
            'trauma' => $form?->trauma ?? '',
            'guardian' => [
                'name' => $primary?->person?->full_name ?? '',
                'relation' => $primary?->pivot->relationship ?? '',
                'phone' => $primary?->person?->phone ?? '',
                'education' => $primary?->person?->education_level ?? '',
                'job' => $primary?->person?->occupation ?? '',
            ],
            'mother' => $g($mother),
            'father' => $g($father),
            'siblings' => $form?->sibling_count,
            'birth_order' => $form?->birth_order,
            'school_siblings' => $form?->school_siblings,
            'family_ill' => trim(($form?->family_disability ?? '').' / '.($form?->family_illness ?? ''), ' /'),
            'household' => $form?->household ?? '',
            'notes' => $form?->notes ?? '',
        ];
    }

    // ------------------------------------------------------------------
    // Sınıf Risk Haritası
    // ------------------------------------------------------------------

    public function riskMap(Request $request): Response
    {
        $branch = Branch::findOrFail($request->integer('branch_id', 0));
        $year = AcademicYear::findOrFail($branch->academic_year_id);
        SchoolScope::ensure($year);

        $students = Student::with(['person:id,full_name,chronic_illness', 'infoForm', 'guardians.person:id,education_level,is_alive'])
            ->whereHas('enrollments', fn ($q) => $q
                ->where('academic_year_id', $year->id)
                ->where('branch_id', $branch->id))
            ->join('people', 'people.id', '=', 'students.person_id')
            ->select('students.*')
            ->orderBy('people.full_name')
            ->get();

        $enrollments = StudentEnrollment::where('academic_year_id', $year->id)
            ->where('branch_id', $branch->id)
            ->pluck('school_number', 'student_id');

        $criteria = [
            'anne_low_edu' => 'Anne en fazla ilkokul mezunu',
            'baba_low_edu' => 'Baba en fazla ilkokul mezunu',
            'single_child' => 'Tek çocuk olan',
            'many_siblings' => '5 ve üstü kardeşi olan',
            'parents_separate' => 'Anne ve babası ayrı yaşayan',
            'parents_divorced' => 'Anne ve babası boşanmış olan',
            'only_mother' => 'Yalnızca annesi ile yaşayan',
            'only_father' => 'Yalnızca babası ile yaşayan',
            'mother_dead' => 'Annesi hayatta olmayan',
            'father_dead' => 'Babası hayatta olmayan',
            'both_dead' => 'Anne ve babası hayatta olmayan',
            'martyr' => 'Şehit Çocuğu',
            'only_grandparents' => 'Yalnızca büyükanne/büyükbabasıyla yaşayan',
            'only_relatives' => 'Yalnızca diğer akrabalarıyla yaşayan',
            'foster' => 'Koruyucu aile gözetiminde olan',
            'sevgi_evi' => 'Sevgi Evlerinde kalan',
            'shcek' => 'Sosyal Hizmetler Çocuk Esirgeme Kurumu',
            'family_ill' => 'Ailesinde süreğen hastalığı olan',
            'family_mental' => 'Ailesinde ruhsal hastalığı olan',
            'family_addict' => 'Ailesinde Bağımlı Bireyler Bulunan (alkol/madde)',
            'family_convict' => 'Ailesinde cezai hükmü bulunan',
            'seasonal' => 'Ailesi mevsimlik işçi olan',
            'violence' => 'Aile içi şiddete maruz kalan',
            'gifted' => 'Özel Yetenekli tanısı olan',
            'special_ed' => 'Yetersizlik alanında özel eğitim raporu olan',
            'own_ill' => 'Süreğen hastalığı olan',
            'own_mental' => 'Ruhsal hastalığı olan',
            'counseling' => 'Danışmanlık Tedbir Kararı Olan',
            'education_measure' => 'Eğitim Tedbir Kararı Olan',
            'poor' => 'Maddi Sıkıntı Yaşayan',
            'absent' => 'Sürekli Devamsız olan',
            'working' => 'Bir işte çalışan',
            'low_success' => 'Akademik Başarısı Düşük',
            'risky_peers' => 'Riskli akran grubuna dahil olan',
            'other' => 'Diğer',
        ];

        $auto = ['anne_low_edu', 'baba_low_edu', 'single_child', 'many_siblings', 'mother_dead', 'father_dead', 'both_dead', 'martyr', 'family_ill', 'own_ill', 'poor'];

        $rows = $students->values()->map(function (Student $s, int $i) use ($enrollments, $criteria, $auto) {
            $flags = $this->riskFlags($s);
            $marks = [];
            foreach ($criteria as $key => $label) {
                $marks[$key] = in_array($key, $auto, true) && ($flags[$key] ?? false);
            }

            return [
                'no' => $i + 1,
                'school_number' => $enrollments->get($s->id) ?? '',
                'full_name' => $s->person?->full_name ?? '',
                'marks' => $marks,
            ];
        })->all();

        $totals = [];
        foreach ($criteria as $key => $label) {
            $totals[$key] = count(array_filter($rows, fn ($r) => $r['marks'][$key]));
        }

        return Inertia::render('Prints/RiskHaritasi', [
            'branch' => $branch->only('id', 'name'),
            'year' => $year->only('id', 'name'),
            'criteria' => $criteria,
            'rows' => $rows,
            'totals' => $totals,
            'school' => ['name' => \App\Models\School::find(SchoolScope::id())?->name ?? ''],
        ]);
    }
}
