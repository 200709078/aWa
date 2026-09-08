<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;
use ZipArchive;

class StudentPhotoController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'bmp'];

    private const MAX_WIDTH = 800;

    private const MAX_HEIGHT = 800;

    private const QUALITY = 80;

    private const MAX_FILES = 500;

    public function show(): Response
    {
        $years = AcademicYear::orderByDesc('name')->get(['id', 'name', 'is_active']);
        $activeId = $years->firstWhere('is_active', true)?->id ?? $years->first()?->id;

        return Inertia::render('Students/Photos', [
            'years' => $years,
            'activeYearId' => $activeId,
        ]);
    }

    public function store(): Response|RedirectResponse
    {
        $data = request()->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,bmp', 'max:10240'],
            'zip_file' => ['nullable', 'file', 'mimes:zip', 'max:51200'],
        ], [
            'academic_year_id.required' => 'Akademik yıl seçin.',
            'photos.array' => 'Fotoğraflar geçersiz.',
            'photos.*.image' => 'Yalnızca resim dosyası yükleyin.',
            'photos.*.mimes' => 'Desteklenen formatlar: jpg, jpeg, png, webp, bmp.',
            'photos.*.max' => 'Her dosya en fazla 10 MB olabilir.',
            'zip_file.mimes' => 'Yalnızca .zip dosyası yükleyin.',
            'zip_file.max' => 'ZIP dosyası en fazla 50 MB olabilir.',
        ]);

        $uploads = request()->file('photos', []);
        $zipFile = request()->file('zip_file');

        if ($uploads === [] && ! $zipFile) {
            return back()->withErrors(['photos' => 'Fotoğraf dosyası veya ZIP seçin.']);
        }

        /** @var array<string, array{filename: string, source: mixed}> $candidates */
        $candidates = [];

        foreach ($uploads as $file) {
            $candidates[] = ['filename' => $file->getClientOriginalName(), 'source' => $file->getRealPath()];
        }

        if ($zipFile) {
            $zip = new ZipArchive();
            if ($zip->open($zipFile->getRealPath()) !== true) {
                return back()->withErrors(['zip_file' => 'ZIP dosyası açılamadı.']);
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false || str_ends_with($name, '/')) {
                    continue;
                }
                $basename = basename($name);
                if ($basename === '' || str_starts_with($basename, '.')) {
                    continue;
                }
                $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    $candidates[] = ['filename' => $basename, 'source' => null, 'note' => 'Desteklenmeyen dosya türü.'];
                    continue;
                }
                $contents = $zip->getFromIndex($i);
                $candidates[] = ['filename' => $basename, 'source' => $contents === false ? null : $contents];
            }
            $zip->close();
        }

        if (count($candidates) > self::MAX_FILES) {
            return back()->withErrors(['photos' => 'Tek seferde en fazla '.self::MAX_FILES.' dosya yükleyin.']);
        }

        $yearId = (int) $data['academic_year_id'];
        $students = Student::where('academic_year_id', $yearId)->get()->keyBy(fn (Student $s) => $this->normalizeNumber($s->school_number));

        $manager = new ImageManager(new Driver());
        Storage::disk('public')->makeDirectory('students');

        $matched = 0;
        $unmatched = [];
        $failed = [];

        foreach ($candidates as $candidate) {
            $number = $this->normalizeNumber(pathinfo($candidate['filename'], PATHINFO_FILENAME));

            if ($candidate['source'] === null || $candidate['source'] === '') {
                $failed[] = ['filename' => $candidate['filename'], 'message' => $candidate['note'] ?? 'Dosya okunamadı.'];
                continue;
            }

            if (! isset($students[$number])) {
                $unmatched[] = $candidate['filename'];
                continue;
            }

            try {
                $student = $students[$number];
                [$image, $temps] = $this->loadImage($manager, $candidate);
                try {
                    $absolute = Storage::disk('public')->path("students/{$student->id}.jpg");
                    $image->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT)
                        ->encode(new JpegEncoder(quality: self::QUALITY))
                        ->save($absolute);
                    $student->update(['photo_path' => "students/{$student->id}.jpg"]);
                    $matched++;
                } finally {
                    foreach ($temps as $temp) {
                        @unlink($temp);
                    }
                }
            } catch (Throwable $e) {
                $failed[] = ['filename' => $candidate['filename'], 'message' => 'Dosya işlenemedi.'];
            }
        }

        $withoutPhoto = Student::where('academic_year_id', $yearId)
            ->whereNull('photo_path')
            ->orderBy('full_name')
            ->get(['school_number', 'full_name']);

        $year = AcademicYear::find($yearId);

        return Inertia::render('Students/PhotosResult', [
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
        ]);
    }

    /**
     * Kaynağı işlenebilir görüntüye çevirir. BMP içerikler (uzantısı ne olursa olsun)
     * önce JPEG'e dönüştürülür. Dönen geçici dosyaların silinmesi çağırana aittir.
     *
     * @param  array{filename: string, source: mixed}  $candidate
     * @return array{0: \Intervention\Image\Interfaces\ImageInterface, 1: array<int, string>}
     */
    private function loadImage(ImageManager $manager, array $candidate): array
    {
        $temps = [];
        $source = $candidate['source'];

        if (is_string($source) && is_file($source)) {
            $path = $source;
        } else {
            $path = tempnam(sys_get_temp_dir(), 'foto').'.bin';
            file_put_contents($path, (string) $source);
            $temps[] = $path;
        }

        if ($this->isBmp($path)) {
            $gd = @imagecreatefrombmp($path);
            if ($gd === false) {
                throw new \RuntimeException('Dosya okunamadı.');
            }
            $jpg = tempnam(sys_get_temp_dir(), 'foto').'.jpg';
            imagejpeg($gd, $jpg, 92);
            imagedestroy($gd);
            $path = $jpg;
            $temps[] = $path;
        }

        try {
            return [$manager->decode($path), $temps];
        } catch (Throwable $e) {
            foreach ($temps as $temp) {
                @unlink($temp);
            }
            throw $e;
        }
    }

    private function isBmp(string $path): bool
    {
        if (@file_get_contents($path, false, null, 0, 2) === 'BM') {
            return true;
        }

        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path) === 'image/bmp';
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
