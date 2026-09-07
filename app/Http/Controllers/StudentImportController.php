<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class StudentImportController extends Controller
{
    private const FIELDS = ['school_number', 'full_name', 'branch'];

    private const FIELD_LABELS = [
        'school_number' => 'Okul numarası',
        'full_name' => 'Ad soyad',
        'branch' => 'Şube',
    ];

    private const HEADER_MAP = [
        'school_number' => ['okulno', 'ogrencino', 'numara', 'no', 'okulnumarasi', 'ogrencinumarasi'],
        'full_name' => ['adsoyad', 'adisoyadi', 'adsoyadi', 'ogrenciadsoyad', 'isim', 'adi'],
        'branch' => ['sinif', 'sube', 'sinifi', 'subesi', 'sinifsube'],
    ];

    public function show(): Response
    {
        $years = AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_active']);
        $activeId = $years->firstWhere('is_active', true)?->id ?? $years->first()?->id;

        return Inertia::render('Students/Import', [
            'years' => $years,
            'activeYearId' => $activeId,
        ]);
    }

    public function preview(): Response|RedirectResponse
    {
        $year = AcademicYear::findOrFail(request()->input('academic_year_id', 0));

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
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'stored_path' => ['required', 'string', 'regex:/^imports\/[A-Za-z0-9\-]+\.(xlsx|xls)$/'],
            'mapping' => ['required', 'array', 'size:3'],
            'mapping.school_number' => ['required', 'integer', 'min:0'],
            'mapping.full_name' => ['required', 'integer', 'min:0'],
            'mapping.branch' => ['required', 'integer', 'min:0'],
        ], [
            'mapping.size' => 'Üç alan için de sütun seçin.',
        ]);

        if (! Storage::disk('local')->exists($data['stored_path'])) {
            return back()->withErrors(['file' => 'Yüklenen dosya bulunamadı, dosyayı yeniden yükleyin.']);
        }

        [$headers, $rows] = $this->loadSheet(Storage::disk('local')->path($data['stored_path']));
        $mapping = $this->resolveMapping($data['mapping'], $headers);
        $result = $this->processRows($rows, $mapping, (int) $data['academic_year_id']);

        $added = 0;
        $updated = 0;
        foreach ($result['rows'] as $row) {
            if ($row['action'] === 'add') {
                Student::create([
                    'academic_year_id' => (int) $data['academic_year_id'],
                    'branch_id' => $row['branch_id'],
                    'school_number' => $row['school_number'],
                    'full_name' => $row['full_name'],
                    'is_active' => true,
                ]);
                $added++;
            } elseif ($row['action'] === 'update') {
                Student::where('academic_year_id', (int) $data['academic_year_id'])
                    ->where('school_number', $row['school_number'])
                    ->first()
                    ?->update(['full_name' => $row['full_name'], 'branch_id' => $row['branch_id']]);
                $updated++;
            }
        }

        Storage::disk('local')->delete($data['stored_path']);

        $summary = $result['summary'];
        $summary['eklendi'] = $added;
        $summary['guncellendi'] = $updated;

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
     * @return array{school_number: int, full_name: int, branch: int}
     */
    private function resolveMapping(mixed $input, array $headers): array
    {
        $count = count($headers);
        $mapping = [];
        $used = [];

        foreach (self::FIELDS as $field) {
            $index = is_array($input) && isset($input[$field]) && is_numeric($input[$field])
                ? (int) $input[$field]
                : $this->detectColumn($headers, $field, $used);

            $index = max(0, min($index, $count - 1));

            if (in_array($index, $used, true)) {
                foreach (range(0, $count - 1) as $candidate) {
                    if (! in_array($candidate, $used, true)) {
                        $index = $candidate;
                        break;
                    }
                }
            }

            $mapping[$field] = $index;
            $used[] = $index;
        }

        return $mapping;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int>  $used
     */
    private function detectColumn(array $headers, string $field, array $used): int
    {
        foreach ($headers as $index => $header) {
            if (in_array($index, $used, true)) {
                continue;
            }
            if (in_array($this->normalizeHeader($header), self::HEADER_MAP[$field], true)) {
                return $index;
            }
        }

        foreach (range(0, count($headers) - 1) as $index) {
            if (! in_array($index, $used, true)) {
                return $index;
            }
        }

        return 0;
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
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array{school_number: int, full_name: int, branch: int}  $mapping
     */
    private function processRows(array $rows, array $mapping, int $yearId): array
    {
        $branchMap = Branch::where('academic_year_id', $yearId)->get()
            ->keyBy(fn (Branch $branch) => self::normalizeBranch($branch->name));

        $existing = Student::where('academic_year_id', $yearId)->pluck('id', 'school_number');

        $result = [];
        $seen = [];
        $summary = ['toplam' => 0, 'eklenecek' => 0, 'guncellenecek' => 0, 'atlandi' => 0, 'hatali' => 0];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $number = $this->normalizeNumber($row[$mapping['school_number']] ?? null);
            $name = trim((string) ($row[$mapping['full_name']] ?? ''));
            $branchName = self::normalizeBranch((string) ($row[$mapping['branch']] ?? ''));

            $entry = ['line' => $line, 'school_number' => $number, 'full_name' => $name, 'branch' => $branchName];

            if ($number === '' && $name === '' && $branchName === '') {
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
                    $summary['guncellenecek']++;
                } else {
                    $entry['action'] = 'add';
                    $summary['eklenecek']++;
                }
            }

            $summary['toplam']++;
            $result[] = $entry;
        }

        return ['rows' => $result, 'summary' => $summary];
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
