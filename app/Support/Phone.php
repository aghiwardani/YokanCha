<?php

namespace App\Support;

/**
 * Indonesian WhatsApp numbers are written many ways (0812…, +62 812…, 62812…).
 * normalize() reduces them to international digits (62812…), the form wa.me expects.
 */
class Phone
{
    public static function normalize(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    public static function same(string $a, string $b): bool
    {
        $a = self::normalize($a);

        return $a !== '' && $a === self::normalize($b);
    }

    /**
     * Plausible phone number: only digits and common separators, 10–15 digits
     * once normalized.
     */
    public static function looksValid(string $number): bool
    {
        if (! preg_match('/^[\d\s+().-]+$/', $number)) {
            return false;
        }

        $length = strlen(self::normalize($number));

        return $length >= 10 && $length <= 15;
    }
}
