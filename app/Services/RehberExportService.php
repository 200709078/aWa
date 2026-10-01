<?php

namespace App\Services;

use App\Http\Controllers\StudentImportController;
use App\Models\Branch;
use App\Models\Graduate;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class RehberExportService
{
    /**
     * SMS yükleme düzeninde Excel listesi: her öğrenciye veli (V) ve öğrenci (O) satırı.
     *
     * @param  array<int>  $branchIds
     * @return array{filename: string, filepath: string, rows: int}
     */
    public function buildExcel(int $yearId, array $branchIds, string $type, string $yearName): array
    {
        [$students] = $this->loadPeople($yearId, $branchIds, 'all');

        $ordered = $students
            ->sort(fn (Student $a, Student $b) => strnatcmp(
                $a->enrollments->first()?->branch?->name ?? '',
                $b->enrollments->first()?->branch?->name ?? ''
            ) ?: strnatcmp(
                $a->enrollments->first()?->school_number ?? '',
                $b->enrollments->first()?->school_number ?? ''
            ))
            ->values();

        $rows = [['SINIF', 'NO', 'ÖĞRENCİ ADI SOYADI', 'VELİ ADI SOYADI', 'VELİ TEL']];

        foreach ($ordered as $student) {
            $enrollment = $student->enrollments->first();
            if (! $enrollment) {
                continue;
            }
            $class = $this->classCode($enrollment->branch?->name ?? '');
            $guardian = $student->guardians->sortByDesc(fn ($g) => (bool) $g->pivot->is_primary)->first();

            if ($type !== 'student') {
                $rows[] = [
                    'V'.$class,
                    $enrollment->school_number,
                    $student->person?->full_name ?? '',
                    $guardian?->person?->full_name ?? '',
                    self::exportPhone($guardian?->person?->phone) ?? '',
                ];
            }
            if ($type !== 'guardian') {
                $rows[] = [
                    'O'.$class,
                    $enrollment->school_number,
                    $student->person?->full_name ?? '',
                    $guardian?->person?->full_name ?? '',
                    self::exportPhone($student->person?->phone) ?? '',
                ];
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Liste');
        foreach ($rows as $r => $cells) {
            foreach ($cells as $c => $value) {
                $sheet->setCellValueExplicit([$c + 1, $r + 1], $value, DataType::TYPE_STRING);
            }
        }
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'liste').'.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $dataRows = count($rows) - 1;
        $scope = $this->scopeLabel($yearId, $branchIds);

        return [
            'filename' => Str::slug("{$yearName} {$scope} {$type} excel-liste {$dataRows}").'.xlsx',
            'filepath' => $path,
            'rows' => $dataRows,
        ];
    }

    /**
     * @param  array<int>  $branchIds
     * @return array{students: int, guardians: int, cards: int, without_phone: int}
     */
    public function summary(int $yearId, array $branchIds, string $type): array
    {
        [$students, $guardians] = $this->loadPeople($yearId, $branchIds, $type);

        $cards = 0;
        $withoutPhone = 0;
        foreach ($this->cardPersons($students, $guardians, $type) as $person) {
            if (self::exportPhone($person['phone']) === null) {
                $withoutPhone++;
            } else {
                $cards++;
            }
        }

        return [
            'students' => $students->count(),
            'guardians' => $guardians->count(),
            'cards' => $cards,
            'without_phone' => $withoutPhone,
        ];
    }

    /**
     * @return array{filename: string, content: string, cards: int, skipped: int}
     */
    public function buildGraduateVcf(?int $year, ?string $search, bool $withPhoto): array
    {
        $graduates = Graduate::with(['person', 'person.educations', 'person.employments'])
            ->when($year, fn ($query) => $query->where('graduation_year', $year))
            ->when($search, fn ($query) => $query->whereHas('person', fn ($query) => $query->where('full_name', 'like', "%{$search}%")))
            ->orderByDesc('graduation_year')
            ->orderBy('graduation_number')
            ->get();

        $cards = [];
        $cardCount = 0;
        $skipped = 0;
        foreach ($graduates as $graduate) {
            $tel = self::exportPhone($graduate->person?->phone);
            if ($tel === null) {
                $skipped++;
                continue;
            }
            $cardCount++;
            $prefix = $graduate->graduation_year.(ctype_digit($graduate->graduation_number)
                ? str_pad($graduate->graduation_number, 3, '0', STR_PAD_LEFT)
                : $graduate->graduation_number);
            $employment = $graduate->person?->employments->first();
            $educations = $graduate->person?->educations ?? collect();
            $orgLines = $educations
                ->map(fn ($item) => trim(trim($item->city ?? '').' '.trim($item->institution_name ?? '')))
                ->filter()
                ->values();
            $titleLines = $educations
                ->map(fn ($item) => trim(trim($item->faculty ?? '').' '.trim($item->department ?? '')))
                ->filter()
                ->values();
            $note = trim(trim($employment?->city ?? '').' '.trim($employment?->company_name ?? ''));
            $cards = array_merge($cards, $this->vcard(
                prefix: $prefix,
                name: (string) $graduate->person?->full_name,
                tel: $tel,
                school: '',
                title: $titleLines->implode(' / '),
                category: "Mezun {$graduate->graduation_year}",
                photoPath: $withPhoto ? $graduate->person?->photo_path : null,
                email: $graduate->person?->email,
                company: $orgLines->implode(' / ') ?: null,
                note: $note === '' ? null : $note,
            ));
        }

        $scope = $year ? (string) $year : 'tum-yillar';

        return [
            'filename' => Str::slug("mezunlar {$scope} {$cardCount}").'.vcf',
            'content' => implode("\r\n", $cards),
            'cards' => $cardCount,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<int>  $branchIds
     * @return array{filename: string, filepath: string, rows: int}
     */
    public function build(int $yearId, array $branchIds, string $type, string $schoolName, string $yearName, bool $withPhoto): array
    {
        [$students, $guardians] = $this->loadPeople($yearId, $branchIds, $type);

        $cards = [];
        $skipped = 0;
        $cardCount = 0;
        foreach ($this->cardPersons($students, $guardians, $type) as $person) {
            $tel = self::exportPhone($person['phone']);
            if ($tel === null) {
                $skipped++;
                continue;
            }
            $cardCount++;
            $cards = array_merge($cards, $this->vcard(
                prefix: $this->prefix($person['branch'], $person['number']),
                name: (string) $person['name'],
                tel: $tel,
                school: $schoolName,
                title: $person['title'],
                category: $person['branch'],
                photoPath: $withPhoto ? $person['photo'] : null,
            ));
        }

        $scope = $this->scopeLabel($yearId, $branchIds);

        return [
            'filename' => Str::slug("{$yearName} {$scope} {$type} {$cardCount}").'.vcf',
            'content' => implode("\r\n", $cards),
            'cards' => $cardCount,
            'skipped' => $skipped,
        ];
    }

    /**
     * Telefonu metin olarak temizler, başına tek 0 koyar. Boş/geçersizse null döner.
     */
    public static function exportPhone(mixed $phone): ?string
    {
        $s = preg_replace('/[\s\-()]+/', '', trim((string) $phone)) ?? '';

        if ($s === '' || $s === '0' || $s === '00') {
            return null;
        }

        return str_starts_with($s, '0') ? $s : '0'.$s;
    }

    /**
     * @param  array<int>  $branchIds
     * @return array{0: Collection, 1: Collection}
     */
    private function loadPeople(int $yearId, array $branchIds, string $type): array
    {
        $students = collect();
        $guardians = collect();

        if ($type !== 'guardian') {
            $students = Student::where('is_active', true)
                ->whereHas('enrollments', fn ($query) => $query
                    ->where('academic_year_id', $yearId)
                    ->whereIn('branch_id', $branchIds))
                ->with([
                    'person',
                    'enrollments' => fn ($query) => $query->where('academic_year_id', $yearId)->with('branch'),
                ])
                ->get();
        }

        if ($type !== 'student') {
            $guardianStudents = $type === 'guardian'
                ? Student::where('is_active', true)
                    ->whereHas('enrollments', fn ($query) => $query
                        ->where('academic_year_id', $yearId)
                        ->whereIn('branch_id', $branchIds))
                    ->with(['guardians.person', 'enrollments' => fn ($query) => $query->where('academic_year_id', $yearId)->with('branch')])
                    ->get()
                : $students->loadMissing('guardians.person');

            $seen = [];
            foreach ($guardianStudents as $student) {
                $enrollment = $student->enrollments->first();
                foreach ($student->guardians as $guardian) {
                    if (isset($seen[$guardian->id])) {
                        continue;
                    }
                    $seen[$guardian->id] = true;
                    $guardian->setAttribute('_enrollment', $enrollment);
                    $guardians->push($guardian);
                }
            }
        }

        return [$students, $guardians];
    }

    /**
     * @return array<int, array{name: ?string, phone: ?string, photo: ?string, branch: string, number: string, title: string}>
     */
    private function cardPersons(Collection $students, Collection $guardians, string $type): array
    {
        $out = [];

        if ($type !== 'guardian') {
            foreach ($students as $student) {
                $enrollment = $student->enrollments->first();
                if (! $enrollment) {
                    continue;
                }
                $out[] = [
                    'name' => $student->person?->full_name,
                    'phone' => $student->person?->phone,
                    'photo' => $student->person?->photo_path,
                    'branch' => $enrollment->branch?->name ?? '',
                    'number' => $enrollment->school_number,
                    'title' => 'Öğrenci',
                ];
            }
        }

        if ($type !== 'student') {
            foreach ($guardians as $guardian) {
                $enrollment = $guardian->getAttribute('_enrollment');
                if (! $enrollment) {
                    continue;
                }
                $out[] = [
                    'name' => $guardian->person?->full_name,
                    'phone' => $guardian->person?->phone,
                    'photo' => $guardian->person?->photo_path,
                    'branch' => $enrollment->branch?->name ?? '',
                    'number' => $enrollment->school_number,
                    'title' => 'Veli',
                ];
            }
        }

        return $out;
    }

    private function prefix(string $branch, string $number): string
    {
        $number = ctype_digit($number) ? str_pad($number, 3, '0', STR_PAD_LEFT) : $number;

        return "{$this->classCode($branch)}-{$number}";
    }

    private function classCode(string $branch): string
    {
        $branch = StudentImportController::normalizeBranch($branch);

        return preg_replace_callback('/^(\d+)/', fn ($m) => str_pad($m[1], 2, '0', STR_PAD_LEFT), $branch) ?? $branch;
    }

    private function scopeLabel(int $yearId, array $branchIds): string
    {
        $all = Branch::where('academic_year_id', $yearId)->pluck('id')->sort()->values()->all();
        $wanted = collect($branchIds)->sort()->values()->all();

        if ($all !== [] && $all === $wanted) {
            return 'tum-subeler';
        }

        return Branch::whereIn('id', $branchIds)->orderBy('name')->pluck('name')->implode(' ');
    }

    /**
     * @return array<int, string>
     */
    private function vcard(string $prefix, string $name, string $tel, string $school, string $title, string $category, ?string $photoPath, ?string $email = null, ?string $company = null, ?string $note = null): array
    {
        [$ad, $soyad] = StudentImportController::splitName(trim($name));
        $ad ??= trim($name);
        $soyad ??= '';

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            $this->fold('FN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:'.$this->qp(trim("{$prefix} {$ad}".($soyad !== '' ? " {$soyad}" : '')))),
            $this->fold('N;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:'.$this->qp($soyad).';'.$this->qp(trim("{$prefix} {$ad}")).';;;'),
            "TEL;CELL:{$tel}",
        ];

        if ($email !== null && $email !== '') {
            $lines[] = 'EMAIL;HOME:'.$email;
        }
        if ($company !== null && $company !== '') {
            $lines[] = $this->fold('ORG;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:'.$this->qp($company));
        } elseif ($school !== '') {
            $lines[] = $this->fold('ORG;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:'.$this->qp($school));
        }
        if ($title !== '') {
            $lines[] = $this->fold('TITLE;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:'.$this->qp($title));
        }
        $lines[] = 'CATEGORIES:'.$category;
        if ($note !== null && $note !== '') {
            $lines[] = $this->fold('NOTE;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:'.$this->qp(str_replace(["\r\n", "\r", "\n"], ' ', $note)));
        }

        if (($b64 = $this->photoBase64($photoPath)) !== null) {
            $pre = 'PHOTO;ENCODING=b;TYPE=JPEG:';
            $lines[] = $pre.substr($b64, 0, 75 - strlen($pre));
            foreach (str_split(substr($b64, 75 - strlen($pre)) ?: '', 74) as $chunk) {
                $lines[] = ' '.$chunk;
            }
        }

        $lines[] = 'END:VCARD';
        $lines[] = '';

        return $lines;
    }

    private function photoBase64(?string $photoPath): ?string
    {
        if (! $photoPath) {
            return null;
        }

        $absolute = Storage::disk('public')->path($photoPath);

        if (! is_file($absolute)) {
            return null;
        }

        $encoded = PhotoService::jpegBytes($absolute, 400, 80);

        return $encoded === null ? null : base64_encode($encoded);
    }

    private function qp(string $value): string
    {
        $out = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            $ord = ord($char);
            if ($char === ' ') {
                $out .= '=20';
            } elseif ($ord >= 33 && $ord <= 126 && $char !== '=') {
                $out .= $char;
            } else {
                $out .= sprintf('=%02X', $ord);
            }
        }

        return $out;
    }

    /**
     * Satırları 75 sekizliğe katlar, =XX kaçışlarını bölmez.
     */
    private function fold(string $line): string
    {
        $parts = [];
        $current = '';
        $length = strlen($line);

        for ($i = 0; $i < $length;) {
            $take = $line[$i] === '=' ? 3 : 1;
            if ($current !== '' && $current !== ' ' && strlen($current) + $take > 75) {
                $parts[] = $current;
                $current = ' ';
                continue;
            }
            $current .= substr($line, $i, $take);
            $i += $take;
        }
        $parts[] = $current;

        return implode("\r\n", $parts);
    }
}
