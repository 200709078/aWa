<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Tüm fotoğraf girişleri (tekil form, toplu yükleme, VCF gömme) buradan geçer.
 * Büyük görselleri oranlayarak küçültür, JPEG'e çevirir.
 */
final class PhotoService
{
    /**
     * @param  string|UploadedFile  $source
     */
    public static function store(mixed $source, string $relativePath, int $maxDim = 800, int $quality = 80): void
    {
        $manager = new ImageManager(new Driver());
        $temps = [];

        try {
            $path = $source instanceof UploadedFile ? $source->getRealPath() : (string) $source;
            if (! is_file($path)) {
                $path = self::writeTemp((string) $source, $temps);
            }
            $path = self::convertBmpIfNeeded($path, $temps);

            Storage::disk('public')->makeDirectory(dirname($relativePath));
            $manager->decode($path)
                ->scaleDown($maxDim, $maxDim)
                ->encode(new JpegEncoder(quality: $quality))
                ->save(Storage::disk('public')->path($relativePath));
        } finally {
            foreach ($temps as $temp) {
                @unlink($temp);
            }
        }
    }

    /**
     * @param  string|UploadedFile  $source
     */
    public static function jpegBytes(mixed $source, int $maxDim = 400, int $quality = 80): ?string
    {
        try {
            $manager = new ImageManager(new Driver());
            $temps = [];

            try {
                $path = $source instanceof UploadedFile ? $source->getRealPath() : (string) $source;
                $path = self::convertBmpIfNeeded($path, $temps);

                $encoded = (string) $manager->decode($path)
                    ->scaleDown($maxDim, $maxDim)
                    ->encode(new JpegEncoder(quality: $quality));
            } finally {
                foreach ($temps as $temp) {
                    @unlink($temp);
                }
            }

            return $encoded === '' ? null : $encoded;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, string>  $temps
     */
    private static function writeTemp(string $contents, array &$temps): string
    {
        $path = tempnam(sys_get_temp_dir(), 'foto').'.bin';
        file_put_contents($path, $contents);
        $temps[] = $path;

        return $path;
    }

    /**
     * @param  array<int, string>  $temps
     */
    private static function convertBmpIfNeeded(string $path, array &$temps): string
    {
        if (! self::isBmp($path)) {
            return $path;
        }

        $gd = @imagecreatefrombmp($path);
        if ($gd === false) {
            throw new \RuntimeException('Dosya okunamadı.');
        }
        $jpg = tempnam(sys_get_temp_dir(), 'foto').'.jpg';
        imagejpeg($gd, $jpg, 92);
        imagedestroy($gd);
        $temps[] = $jpg;

        return $jpg;
    }

    private static function isBmp(string $path): bool
    {
        if (@file_get_contents($path, false, null, 0, 2) === 'BM') {
            return true;
        }

        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path) === 'image/bmp';
    }
}
