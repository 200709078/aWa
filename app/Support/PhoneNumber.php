<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Kullanıcı her biçimde yazabilir (0532..., 532..., +90...,
 * aralıklı); kabul edilenler +905XXXXXXXXX biçimine çevrilir.
 */
final class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            return '+'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '+90'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+90'.$digits;
        }

        return null;
    }

    /**
     * Boş bırakılabilir alanlar için: boşsa null, dolu ama
     * geçersizse Türkçe hata fırlatır.
     */
    public static function normalizeOrFail(?string $value, string $field, string $message): ?string
    {
        if (trim((string) $value) === '') {
            return null;
        }

        $normalized = self::normalize($value);

        if ($normalized === null) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $normalized;
    }
}
