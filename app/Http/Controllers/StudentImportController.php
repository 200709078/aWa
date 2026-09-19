<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Person;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\SchoolScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    private const FIELDS = [
        'school_number', 'full_name', 'branch',
        'student_phone', 'student_email', 'address',
        'guardian_name', 'guardian_phone', 'guardian_email',
    ];

    private const REQUIRED_FIELDS = ['school_number', 'full_name', 'branch'];

    private const FIELD_LABELS = [
        'school_number' => 'Okul numarası',
        'full_name' => 'Ad soyad',
        'branch' => 'Şube',
        'student_phone' => 'Öğrenci telefonu',
        'student_email' => 'Öğrenci e-postası',
        'address' => 'Adres',
        'guardian_name' => 'Veli ad soyad',
        'guardian_phone' => 'Veli telefonu',
        'guardian_email' => 'Veli e-postası',
    ];

    private const HEADER_MAP = [
        'school_number' => ['okulno', 'ogrencino', 'numara', 'no', 'okulnumarasi', 'ogrencinumarasi'],
        'full_name' => ['adsoyad', 'adisoyadi', 'adsoyadi', 'ogrenciadsoyad', 'isim', 'adi'],
        'branch' => ['sinif', 'sube', 'sinifi', 'subesi', 'sinifsube'],
        'student_phone' => ['ogrencitelefon', 'ogrencicep', 'ogrenciceptelefonu', 'ceptelefonu', 'cep', 'telefon', 'ceptel', 'gsm'],
        'student_email' => ['ogrencieposta', 'ogrenciemail', 'eposta', 'email', 'epost'],
        'address' => ['adres', 'evadres', 'acikadres', 'ikametgah', 'adresbilgisi'],
        'guardian_name' => ['veliadsoyad', 'veliadisoyadi', 'veliadsoyadi', 'veliadi', 'veliisim', 'veli'],
        'guardian_phone' => ['velitelefon', 'velicep', 'veliceptelefonu', 'veligsm', 'veliceptel'],
        'guardian_email' => ['velieposta', 'veliemail', 'veliepost'],
    ];

    public function show(): Response
    {
        $years = AcademicYear::where('school_id', SchoolScope::id())->orderByDesc('name')->get(['id', 'name', 'is_active']);
        $activeId = $years->firstWhere('is_active', true)?->id ?? $years->first()?->id;

        return Inertia::render('Students/Import', [
            'years' => $years,
            'activeYearId' => $activeId,
        ]);
    }

    public function template(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Öğrenciler');
        $sheet->fromArray([['Okul No', 'Ad Soyad', 'Sınıf', 'Öğrenci Telefon', 'Öğrenci E-posta', 'Adres', 'Veli Ad Soyad', 'Veli Telefon', 'Veli E-posta']], null, 'A1', true);
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(35);
        $sheet->getColumnDimension('G')->setWidth(30);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(25);
        $sheet->freezePane('A2');

        $path = tempnam(sys_get_temp_dir(), 'sablon').'.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, 'ogrenci-aktarim-sablonu.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function preview(): Response|RedirectResponse
    {
        $year = AcademicYear::findOrFail(request()->input('academic_year_id', 0));
        SchoolScope::ensure($year);

        if (request()->hasFile('file')) {
            request()->validate([
                'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            ], [
                'file.required' => 'Excel dosyası seçin.',
                'file.mimes' => 'Yalnızca .xlsx veya .xls dosyası yükleyin.',
                'file.max' => 'Dosya en fazla 10 MB olabilir.',
            ]);

            $old = request()->input('stored_path');
            if (is_string($old) && $this->isSafePath($old)) {
                Storage::disk('local')->delete($old);
            }

            $ext = strtolower(request()->file('file')->getClientOriginalExtension());
            $storedPath = request()->file('file')->storeAs('imports', Str::uuid().'.'.$ext, 'local');
        } else {
            $storedPath = request()->input('stored_path');
            if (! is_string($storedPath) || ! $this->isSafePath($storedPath) || ! Storage::disk('local')->exists($storedPath)) {
                return back()->withErrors(['file' => 'Önce bir Excel dosyası yükleyin.']);
            }
        }

        [$headers, $rows] = $this->loadSheet(Storage::disk('local')->path($storedPath));

        if (count($headers) === 0) {
            return back()->withErrors(['file' => 'Dosyada okunabilir sütun bulunamadı.']);
        }

        $mapping = $this->resolveMapping(request()->input('mapping', []), $headers);
        $result = $this->processRows($rows, $mapping, $year->id);

        return Inertia::render('Students/ImportPreview', [
            'year' => $year->only('id', 'name'),
            'storedPath' => $storedPath,
            'columns' => $this->columns($headers),
            'mapping' => $mapping,
            'rows' => $result['rows'],
            'summary' => $result['summary'],
        ]);
    }

    public function confirm(): Response|RedirectResponse
    {
        $data = request()->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('school_id', SchoolScope::id())],
            'stored_path' => ['required', 'string', 'regex:/^imports\/[A-Za-z0-9\-]+\.(xlsx|xls)$/'],
            'mapping' => ['required', 'array'],
            'mapping.school_number' => ['required', 'integer', 'min:0'],
            'mapping.full_name' => ['required', 'integer', 'min:0'],
            'mapping.branch' => ['required', 'integer', 'min:0'],
            'mapping.student_phone' => ['nullable', 'integer', 'min:0'],
            'mapping.student_email' => ['nullable', 'integer', 'min:0'],
            'mapping.address' => ['nullable', 'integer', 'min:0'],
            'mapping.guardian_name' => ['nullable', 'integer', 'min:0'],
            'mapping.guardian_phone' => ['nullable', 'integer', 'min:0'],
            'mapping.guardian_email' => ['nullable', 'integer', 'min:0'],
        ], [
            'mapping.school_number.required' => 'Okul numarası sütunu seçin.',
            'mapping.full_name.required' => 'Ad soyad sütunu seçin.',
            'mapping.branch.required' => 'Şube sütunu seçin.',
        ]);

        if (! Storage::disk('local')->exists($data['stored_path'])) {
            return back()->withErrors(['file' => 'Yüklenen dosya bulunamadı, dosyayı yeniden yükleyin.']);
        }

        [$headers, $rows] = $this->loadSheet(Storage::disk('local')->path($data['stored_path']));
        $mapping = $this->resolveMapping($data['mapping'], $headers);
        $result = $this->processRows($rows, $mapping, (int) $data['academic_year_id']);

        $added = 0;
        $updated = 0;
        $addedGuardians = 0;
        $updatedGuardians = 0;
        $yearId = (int) $data['academic_year_id'];
        $schoolId = AcademicYear::whereKey($yearId)->value('school_id');
        $guardianCache = [];

        foreach ($result['rows'] as $row) {
            if ($row['action'] === 'add') {
                DB::transaction(function () use ($row, $yearId, $schoolId, &$guardianCache, &$addedGuardians, &$updatedGuardians) {
                    $student = isset($row['student_id']) && $row['student_id']
                        ? Student::find($row['student_id'])
                        : null;

                    if ($student) {
                        $this->refreshPersonContacts($student->person, $row);
                    } else {
                        [$firstName, $lastName] = self::splitName($row['full_name']);

                        $person = Person::create([
                            'school_id' => $schoolId,
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'full_name' => $row['full_name'],
                            'phone' => $row['student_phone'] === '' ? null : $row['student_phone'],
                            'email' => $row['student_email'] === '' ? null : $row['student_email'],
                            'address' => $row['address'] === '' ? null : $row['address'],
                        ]);

                        $student = Student::create([
                            'school_id' => $schoolId,
                            'person_id' => $person->id,
                            'is_active' => true,
                        ]);
                    }

                    StudentEnrollment::create([
                        'student_id' => $student->id,
                        'academic_year_id' => $yearId,
                        'branch_id' => $row['branch_id'],
                        'school_number' => $row['school_number'],
                        'status' => 'active',
                    ]);

                    $this->persistGuardian($row, $student, $schoolId, $guardianCache, $addedGuardians, $updatedGuardians);
                });
                $added++;
            } elseif ($row['action'] === 'update') {
                DB::transaction(function () use ($row, $yearId, $schoolId, &$guardianCache, &$addedGuardians, &$updatedGuardians) {
                    $enrollment = StudentEnrollment::where('academic_year_id', $yearId)
                        ->where('school_number', $row['school_number'])
                        ->first();

                    $enrollment?->update(['branch_id' => $row['branch_id']]);

                    $person = $enrollment?->student?->person;
                    if ($person) {
                        $this->refreshPersonContacts($person, $row);

                        $this->persistGuardian($row, $enrollment->student, $schoolId, $guardianCache, $addedGuardians, $updatedGuardians);
                    }
                });
                $updated++;
            }
        }

        Storage::disk('local')->delete($data['stored_path']);

        $summary = $result['summary'];
        $summary['eklendi'] = $added;
        $summary['guncellendi'] = $updated;
        $summary['eklenen_veli'] = $addedGuardians;
        $summary['guncellenen_veli'] = $updatedGuardians;

        $year = AcademicYear::find($data['academic_year_id']);

        return Inertia::render('Students/ImportResult', [
            'year' => $year?->only('id', 'name'),
            'summary' => $summary,
            'errors' => array_values(array_filter($result['rows'], fn ($row) => $row['action'] === 'error')),
        ]);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function loadSheet(string $absolutePath): array
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $reader = $ext === 'xls' ? new Xls() : new Xlsx();
        $reader->setReadDataOnly(true);

        $values = $reader->load($absolutePath)->getActiveSheet()->toArray(null, true, true, false);

        $values = array_values(array_filter($values, fn ($row) => collect($row)->some(fn ($cell) => trim((string) $cell) !== '')));

        if ($values === []) {
            return [[], []];
        }

        $headers = array_map(fn ($cell) => trim((string) $cell), array_shift($values));

        return [$headers, array_values($values)];
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int|null>
     */
    private function resolveMapping(mixed $input, array $headers): array
    {
        $count = count($headers);
        $mapping = [];
        $used = [];

        foreach (self::FIELDS as $field) {
            $explicit = is_array($input) && array_key_exists($field, $input) ? $input[$field] : null;

            $index = is_numeric($explicit)
                ? max(0, min((int) $explicit, $count - 1))
                : $this->detectColumn($headers, $field, $used);

            if ($index !== null && in_array($index, $used, true)) {
                $index = $this->firstUnusedColumn($count, $used);
            }

            if ($index === null && in_array($field, self::REQUIRED_FIELDS, true)) {
                $index = $this->firstUnusedColumn($count, $used) ?? 0;
            }

            $mapping[$field] = $index;
            if ($index !== null) {
                $used[] = $index;
            }
        }

        return $mapping;
    }

    /**
     * @param  array<int>  $used
     */
    private function firstUnusedColumn(int $count, array $used): ?int
    {
        foreach (range(0, $count - 1) as $index) {
            if (! in_array($index, $used, true)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int>  $used
     */
    private function detectColumn(array $headers, string $field, array $used): ?int
    {
        foreach ($headers as $index => $header) {
            if (in_array($index, $used, true)) {
                continue;
            }
            if (in_array($this->normalizeHeader($header), self::HEADER_MAP[$field], true)) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeHeader(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = str_replace(['ç', 'ğ', 'ı', 'ö', 'ş', 'ü'], ['c', 'g', 'i', 'o', 's', 'u'], $value);

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }

    public static function normalizeBranch(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value), 'UTF-8');

        if (preg_match('/^(\d+)\s*[\/\-. ]\s*([A-ZÇĞİÖŞÜ]+)$/u', $value, $m)) {
            return $m[1].$m[2];
        }

        return preg_replace('/\s+/u', '', $value) ?? '';
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

    /**
     * Excel'in 5462636932.0 gibi sayı yazdığı telefonları düzeltir.
     * Baştaki sıfırı korumak için yalnız ondalıklı yazımlar dönüştürülür.
     */
    private function normalizePhone(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = trim((string) $value);

        if ($text !== '' && is_numeric($text) && str_contains($text, '.')) {
            $float = (float) $text;
            if ($float == (int) $float) {
                return (string) (int) $float;
            }
        }

        return $text;
    }

    private function normalizeEmail(mixed $value): string
    {
        return mb_strtolower(trim((string) $value), 'UTF-8');
    }

    /**
     * "Ahmet Bayram Gün" -> ["Ahmet Bayram", "Gün"].
     * Türkçe düzen gereği soyadı her zaman en sondaki kelimedir.
     * Tek kelimede güvenli ayrım yapılamaz, [null, null] döner.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function splitName(string $fullName): array
    {
        $fullName = preg_replace('/\s+/u', ' ', trim($fullName)) ?? '';
        $pos = mb_strrpos($fullName, ' ');

        if ($pos === false || $fullName === '') {
            return [null, null];
        }

        return [mb_substr($fullName, 0, $pos), mb_substr($fullName, $pos + 1)];
    }

    private function normalizeNameKey(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int|null>  $mapping
     */
    private function processRows(array $rows, array $mapping, int $yearId): array
    {
        $branchMap = Branch::where('academic_year_id', $yearId)->get()
            ->keyBy(fn (Branch $branch) => self::normalizeBranch($branch->name));

        $existing = StudentEnrollment::where('academic_year_id', $yearId)->pluck('student_id', 'school_number');

        $studentsById = Student::whereIn('id', $existing->values()->all())
            ->with('guardians.person')
            ->get()
            ->keyBy('id');

        $cell = fn (array $row, ?int $index): string => $index === null ? '' : trim((string) ($row[$index] ?? ''));

        $result = [];
        $seen = [];
        $seenGuardians = [];
        $summary = [
            'toplam' => 0, 'eklenecek' => 0, 'guncellenecek' => 0,
            'eklenecek_veli' => 0, 'guncellenecek_veli' => 0,
            'atlandi' => 0, 'hatali' => 0,
        ];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $number = $this->normalizeNumber($mapping['school_number'] === null ? null : ($row[$mapping['school_number']] ?? null));
            $name = $cell($row, $mapping['full_name']);
            $branchName = self::normalizeBranch($cell($row, $mapping['branch']));
            $studentPhone = $this->normalizePhone($mapping['student_phone'] === null ? null : ($row[$mapping['student_phone']] ?? null));
            $studentEmail = $this->normalizeEmail($cell($row, $mapping['student_email']));
            $address = $cell($row, $mapping['address']);
            $guardianName = $cell($row, $mapping['guardian_name']);
            $guardianPhone = $this->normalizePhone($mapping['guardian_phone'] === null ? null : ($row[$mapping['guardian_phone']] ?? null));
            $guardianEmail = $this->normalizeEmail($cell($row, $mapping['guardian_email']));

            $entry = [
                'line' => $line, 'school_number' => $number, 'full_name' => $name, 'branch' => $branchName,
                'student_phone' => $studentPhone, 'student_email' => $studentEmail, 'address' => $address,
                'guardian_name' => $guardianName, 'guardian_phone' => $guardianPhone, 'guardian_email' => $guardianEmail,
                'guardian_action' => null,
            ];

            $allEmpty = $number === '' && $name === '' && $branchName === '' && $studentPhone === ''
                && $studentEmail === '' && $address === '' && $guardianName === ''
                && $guardianPhone === '' && $guardianEmail === '';

            if ($allEmpty) {
                $entry['action'] = 'skip';
                $summary['atlandi']++;
            } elseif ($number === '') {
                $entry['action'] = 'error';
                $entry['message'] = 'Okul numarası boş.';
                $summary['hatali']++;
            } elseif ($name === '') {
                $entry['action'] = 'error';
                $entry['message'] = 'Ad soyad boş.';
                $summary['hatali']++;
            } elseif ($branchName === '') {
                $entry['action'] = 'error';
                $entry['message'] = 'Şube boş.';
                $summary['hatali']++;
            } elseif (! isset($branchMap[$branchName])) {
                $entry['action'] = 'error';
                $entry['message'] = "Şube bulunamadı: {$branchName}.";
                $summary['hatali']++;
            } elseif (isset($seen[$number])) {
                $entry['action'] = 'error';
                $entry['message'] = "Dosyada mükerrer okul numarası: {$number} (önce satır {$seen[$number]}).";
                $summary['hatali']++;
            } else {
                $seen[$number] = $line;
                $entry['branch_id'] = $branchMap[$branchName]->id;
                if (isset($existing[$number])) {
                    $entry['action'] = 'update';
                    $entry['student_id'] = $existing[$number];
                    $summary['guncellenecek']++;
                } else {
                    $entry['action'] = 'add';
                    $entry['student_id'] = $this->findCrossYearStudent($yearId, $number, $name)?->id;
                    $summary['eklenecek']++;
                }

                if ($guardianName !== '') {
                    $guardianKey = $this->normalizeNameKey($guardianName).'|'.$guardianPhone;
                    $linkedStudent = isset($existing[$number])
                        ? ($studentsById[$existing[$number]] ?? null)
                        : ($entry['student_id'] ? Student::with('guardians.person')->find($entry['student_id']) : null);
                    $matched = $this->findStudentGuardian($linkedStudent, $guardianName) !== null;

                    if ($matched || isset($seenGuardians[$guardianKey])) {
                        $entry['guardian_action'] = 'update';
                        $summary['guncellenecek_veli']++;
                    } else {
                        $entry['guardian_action'] = 'add';
                        $summary['eklenecek_veli']++;
                    }
                    $seenGuardians[$guardianKey] = true;
                }
            }

            $summary['toplam']++;
            $result[] = $entry;
        }

        return ['rows' => $result, 'summary' => $summary];
    }

    /**
     * @return array<string, string>
     */
    private function splitNameData(string $fullName): array
    {
        [$firstName, $lastName] = self::splitName($fullName);

        return $firstName === null ? [] : ['first_name' => $firstName, 'last_name' => $lastName];
    }

    /**
     * Kişi adını ve dolu gelen iletişim alanlarını güvenli şekilde yazar.
     * Excel hücresi boşsa mevcut veri korunur.
     */
    private function refreshPersonContacts(?Person $person, array $row): void
    {
        if (! $person) {
            return;
        }

        $data = array_merge(['full_name' => $row['full_name']], $this->splitNameData($row['full_name']));
        foreach (['phone' => $row['student_phone'], 'email' => $row['student_email'], 'address' => $row['address']] as $column => $value) {
            if ($value !== '') {
                $data[$column] = $value;
            }
        }
        $person->update($data);
    }

    private function findStudentGuardian(?Student $student, string $guardianName): ?Guardian
    {
        if (! $student) {
            return null;
        }

        $key = $this->normalizeNameKey($guardianName);

        return $student->guardians->first(
            fn (Guardian $guardian) => $this->normalizeNameKey($guardian->person?->full_name) === $key
        );
    }

    /**
     * Başka yıldaki aynı numara + aynı isimli öğrenciyi bulur.
     * Numara başka öğrenciye verilmişse (isim farklı) veya öğrencinin
     * bu yılda kaydı varsa null döner.
     */
    private function findCrossYearStudent(int $yearId, string $number, string $name): ?Student
    {
        if ($number === '' || $name === '') {
            return null;
        }

        $key = $this->normalizeNameKey($name);

        return Student::whereHas('enrollments', fn ($query) => $query
            ->where('school_number', $number)
            ->where('academic_year_id', '!=', $yearId))
            ->whereDoesntHave('enrollments', fn ($query) => $query->where('academic_year_id', $yearId))
            ->with('person:id,full_name')
            ->get()
            ->first(fn (Student $student) => $this->normalizeNameKey($student->person?->full_name) === $key);
    }

    /**
     * Veli adı yoksa hiçbir şey yapmaz. Aynı isimli veli öğrencide
     * zaten varsa iletişimini tazeler; dosya içinde tekrar eden veliyi
     * tek kayda bağlar; yoksa yeni kişi + veli + bağlantı oluşturur.
     *
     * @param  array<string, int>  $guardianCache
     */
    private function persistGuardian(array $row, Student $student, mixed $schoolId, array &$guardianCache, int &$added, int &$updated): void
    {
        $name = $row['guardian_name'] ?? '';
        if ($name === '') {
            return;
        }

        $key = $this->normalizeNameKey($name).'|'.($row['guardian_phone'] ?? '');
        $contactData = [];
        foreach (['phone' => $row['guardian_phone'] ?? '', 'email' => $row['guardian_email'] ?? ''] as $column => $value) {
            if ($value !== '') {
                $contactData[$column] = $value;
            }
        }

        $student->loadMissing('guardians.person');
        $match = $this->findStudentGuardian($student, $name);

        if ($match) {
            if ($contactData !== []) {
                $match->person?->update($contactData);
            }
            $guardianCache[$key] = $match->id;
            $updated++;

            return;
        }

        if (isset($guardianCache[$key]) && ($guardian = Guardian::find($guardianCache[$key]))) {
            if (! $student->guardians()->whereKey($guardian->id)->exists()) {
                $student->guardians()->attach($guardian->id, [
                    'relationship' => 'veli',
                    'is_primary' => ! $student->guardians()->exists(),
                ]);
            }
            if ($contactData !== []) {
                $guardian->person?->update($contactData);
            }
            $updated++;

            return;
        }

        [$firstName, $lastName] = self::splitName($name);
        $person = Person::create([
            'school_id' => $schoolId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $name,
            'phone' => ($row['guardian_phone'] ?? '') === '' ? null : $row['guardian_phone'],
            'email' => ($row['guardian_email'] ?? '') === '' ? null : $row['guardian_email'],
        ]);

        $guardian = Guardian::create(['person_id' => $person->id]);
        $isPrimary = $student->guardians()->count() === 0;
        $student->guardians()->attach($guardian->id, [
            'relationship' => 'veli',
            'is_primary' => $isPrimary,
        ]);

        $guardianCache[$key] = $guardian->id;
        $added++;
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function columns(array $headers): array
    {
        $columns = [];
        foreach ($headers as $index => $header) {
            $columns[] = ['index' => $index, 'letter' => $this->columnLetter($index), 'header' => $header];
        }

        return $columns;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }

    private function isSafePath(string $path): bool
    {
        return preg_match('/^imports\/[A-Za-z0-9\-]+\.(xlsx|xls)$/', $path) === 1;
    }
}
