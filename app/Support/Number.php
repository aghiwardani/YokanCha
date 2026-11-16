<?php

namespace App\Support;

/**
 * Reads amounts typed the Indonesian way: "." groups thousands, "," is the
 * decimal mark ("32.000", "2.000", "2,5", "1.250,75"). Admin inputs show
 * that format and normally submit plain numbers; this is the fallback when
 * the formatted text reaches the server as typed.
 */
class Number
{
    /**
     * Plain numeric string ("1250.75") for an Indonesian-formatted value;
     * anything else is returned unchanged so validation can judge it.
     * "2.5" stays 2.5: dots only mean thousands in groups of three.
     */
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $text = str_replace(' ', '', trim($value));

        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $text) && (str_contains($text, ',') || str_contains($text, '.'))) {
            return str_replace(['.', ','], ['', '.'], $text);
        }

        if (preg_match('/^\d+,\d+$/', $text)) {
            return str_replace(',', '.', $text);
        }

        return $text;
    }

    /**
     * normalize() applied to every leaf of a nested array (recipe grids).
     */
    public static function normalizeAll(mixed $value): mixed
    {
        if (! is_array($value)) {
            return self::normalize($value);
        }

        return array_map(fn ($item) => self::normalizeAll($item), $value);
    }
}
