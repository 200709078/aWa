<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\PhotoService;
use App\Support\SchoolScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class StudentPhotoController extends Controller
{
    private const MAX_WIDTH = 800;

    private const MAX_HEIGHT = 800;

    private const QUALITY = 80;

    private const MAX_FILES = 500;

    public function show(): Response
    {
        $years = AcademicYear::where('school_id', SchoolScope::id())->orderByDesc('name')->get(['id', 'name', 'is_active']);
        $activeId = $years->firstWhere('is_active', true)?->id ?? $years->first()?->id;

        return Inertia::render('Students/Photos', [
            'years' => $years,
            'activeYearId' => $activeId,
        ]);
    }

    /**
     * Dosyaları geçici alana alıp öğrenciyle eşleştirir, kaydetmez.
     */
    public function match(): JsonResponse
    {
        $data = request()->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('school_id', SchoolScope::id())],
            'token' => ['required', 'string', 'uuid'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,bmp', 'max:10240'],
        ], [
            'academic_year_id.required' => 'Akademik yıl seçin.',
            'token.required' => 'Eşleştirme anahtarı gerekli.',
            'token.uuid' => 'Eşleştirme anahtarı geçersiz.',
            'photos.array' => 'Fotoğraflar geçersiz.',
            'photos.*.image' => 'Yalnızca resim dosyası yükleyin.',
            'photos.*.mimes' => 'Desteklenen formatlar: jpg, jpeg, png, webp, bmp.',
            'photos.*.max' => 'Her dosya en fazla 10 MB olabilir.',
            'photos.*.uploaded' => 'Dosya sunucuya yüklenemedi, dosya çok büyük olabilir. Daha küçük dosyalar seçin.',
        ]);

        $uploads = request()->file('photos', []);

        if ($uploads === []) {
            return response()->json(['message' => 'Fotoğraf dosyası seçin.'], 422);
        }

        $dir = "photo_match/{$data['token']}";
        $stored = Storage::disk('local')->exists($dir) ? Storage::disk('local')->files($dir) : [];

        if (count($stored) + count($uploads) > self::MAX_FILES) {
            return response()->json(['message' => 'Tek seferde en fazla '.self::MAX_FILES.' dosya eşleştirin.'], 422);
        }

        $yearId = (int) $data['academic_year_id'];
        $enrollments = StudentEnrollment::where('academic_year_id', $yearId)
            ->with('student.person:id,full_name')
            ->get()
            ->keyBy(fn (StudentEnrollment $e) => $this->normalizeNumber($e->school_number));

        Storage::disk('local')->makeDirectory($dir);

        $files = [];
        foreach ($uploads as $file) {
            $filename = basename($file->getClientOriginalName());
            $file->storeAs($dir, $filename, 'local');

            $number = $this->normalizeNumber(pathinfo($filename, PATHINFO_FILENAME));
            $enrollment = $enrollments[$number] ?? null;

            if ($enrollment?->student) {
                $files[] = [
                    'filename' => $filename,
                    'matched' => true,
                    'school_number' => $number,
                    'full_name' => $enrollment->student->person?->full_name,
                ];
            } else {
                $files[] = ['filename' => $filename, 'matched' => false];
            }
        }

        return response()->json(['token' => $data['token'], 'files' => $files]);
    }

    /**
     * Eşleştirilen dosyaları işleyip kaydeder.
     */
    public function confirm(): JsonResponse
    {
        $data = request()->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('school_id', SchoolScope::id())],
            'token' => ['required', 'string', 'uuid'],
            'filenames' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'filenames.*' => ['string', 'max:255'],
        ], [
            'academic_year_id.required' => 'Akademik yıl seçin.',
            'token.required' => 'Eşleştirme anahtarı gerekli.',
            'token.uuid' => 'Eşleştirme anahtarı geçersiz.',
        ]);

        $dir = "photo_match/{$data['token']}";

        if (! Storage::disk('local')->exists($dir)) {
            return response()->json(['message' => 'Eşleştirme bulunamadı. Yeniden eşleştirin.'], 422);
        }

        $candidates = array_map(
            fn ($path) => ['filename' => basename($path), 'source' => Storage::disk('local')->path($path)],
            Storage::disk('local')->files($dir)
        );

        // Grup grup onayda yalnız istenen dosyalar işlenir, işlenenler silinir.
        $only = $data['filenames'] ?? null;
        if (is_array($only)) {
            $only = array_values(array_unique(array_map('basename', $only)));
            $candidates = array_values(array_filter(
                $candidates,
                fn ($candidate) => in_array($candidate['filename'], $only, true),
            ));
        }

        $payload = $this->processCandidates($candidates, (int) $data['academic_year_id']);

        if (is_array($only)) {
            foreach ($candidates as $candidate) {
                Storage::disk('local')->delete("{$dir}/{$candidate['filename']}");
            }
            if (Storage::disk('local')->files($dir) === []) {
                Storage::disk('local')->deleteDirectory($dir);
            }
        } else {
            Storage::disk('local')->deleteDirectory($dir);
        }

        return response()->json($payload);
    }

    /**
     * @param  array<int, array{filename: string, source: mixed}>  $candidates
     */
    private function processCandidates(array $candidates, int $yearId): array
    {
        $enrollments = StudentEnrollment::where('academic_year_id', $yearId)
            ->with('student.person')
            ->get()
            ->keyBy(fn (StudentEnrollment $e) => $this->normalizeNumber($e->school_number));

        $matched = 0;
        $unmatched = [];
        $failed = [];

        foreach ($candidates as $candidate) {
            $number = $this->normalizeNumber(pathinfo($candidate['filename'], PATHINFO_FILENAME));

            if ($candidate['source'] === null || $candidate['source'] === '') {
                $failed[] = ['filename' => $candidate['filename'], 'message' => 'Dosya okunamadı.'];
                continue;
            }

            if (! isset($enrollments[$number]) || ! $enrollments[$number]->student || ! $enrollments[$number]->student->person) {
                $unmatched[] = $candidate['filename'];
                continue;
            }

            try {
                $student = $enrollments[$number]->student;
                PhotoService::store($candidate['source'], "students/{$student->id}.jpg", self::MAX_WIDTH, self::QUALITY);
                $student->person->update(['photo_path' => "students/{$student->id}.jpg"]);
                $matched++;
            } catch (Throwable $e) {
                $failed[] = ['filename' => $candidate['filename'], 'message' => 'Dosya işlenemedi.'];
            }
        }

        $withoutPhoto = Student::whereHas('enrollments', fn ($query) => $query->where('academic_year_id', $yearId))
            ->whereHas('person', fn ($query) => $query->whereNull('photo_path'))
            ->with([
                'person:id,full_name',
                'enrollments' => fn ($query) => $query->where('academic_year_id', $yearId),
            ])
            ->join('people', 'people.id', '=', 'students.person_id')
            ->orderBy('people.full_name')
            ->select('students.*')
            ->get()
            ->map(fn (Student $student) => [
                'school_number' => $student->enrollments->first()?->school_number,
                'full_name' => $student->person?->full_name,
            ]);

        $year = AcademicYear::find($yearId);

        return [
            'year' => $year?->only('id', 'name'),
            'summary' => [
                'eslesen' => $matched,
                'eslesmeyen' => count($unmatched),
                'fotografsiz' => $withoutPhoto->count(),
                'hatali' => count($failed),
            ],
            'unmatched' => array_values($unmatched),
            'failed' => $failed,
            'withoutPhoto' => $withoutPhoto->take(100)->values(),
            'withoutPhotoTruncated' => $withoutPhoto->count() > 100,
        ];
    }

    private function normalizeNumber(?string $value): string    {
        $text = trim((string) $value);

        if ($text !== '' && is_numeric($text)) {
            $float = (float) $text;
            if ($float == (int) $float) {
                return (string) (int) $float;
            }
        }

        return $text;
    }
}
