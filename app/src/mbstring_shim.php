<?php

/**
 * Minimal mbstring shim for environments where the mbstring extension is not
 * installed (e.g. this sandbox runs PHP 8.5 without mbstring). Only the small
 * subset of mb_* functions that Laravel/Termwind actually call at runtime is
 * provided. On any system with the real extension, these are skipped.
 */

if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth(string $string, int $start, int $width, string $trim_marker = '', ?string $encoding = null): string
    {
        $str = $start > 0 ? mb_substr($string, $start) : $string;
        if (mb_strlen($str) > $width) {
            $str = mb_substr($str, 0, $width) . $trim_marker;
        }
        return $str;
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen(?string $string, ?string $encoding = null): int
    {
        return strlen((string) $string);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(?string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        return substr((string) $string, $start, $length ?? strlen((string) $string));
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(?string $string, ?string $encoding = null): string
    {
        return strtolower((string) $string);
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(?string $string, ?string $encoding = null): string
    {
        return strtoupper((string) $string);
    }
}

if (!function_exists('mb_convert_case')) {
    function mb_convert_case(?string $string, int $mode, ?string $encoding = null): string
    {
        return match ($mode) {
            MB_CASE_UPPER => strtoupper((string) $string),
            MB_CASE_LOWER => strtolower((string) $string),
            MB_CASE_TITLE => ucwords(strtolower((string) $string)),
            default => (string) $string,
        };
    }
}

if (!function_exists('mb_internal_encoding')) {
    function mb_internal_encoding(?string $encoding = null): string|bool
    {
        return 'UTF-8';
    }
}

if (!defined('MB_CASE_UPPER')) { define('MB_CASE_UPPER', 0); }
if (!defined('MB_CASE_LOWER')) { define('MB_CASE_LOWER', 1); }
if (!defined('MB_CASE_TITLE')) { define('MB_CASE_TITLE', 2); }
