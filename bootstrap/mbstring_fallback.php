<?php
declare(strict_types=1);

/**
 * UTF-8 stand-ins for the five mbstring functions Adlexone calls, so a host without
 * the mbstring extension still runs. Each is defined only when the extension is
 * absent. They handle UTF-8 only, and invalid UTF-8 is treated as single bytes.
 * mb_strtoupper covers ASCII, Latin-1, Latin Extended-A, Greek, and the basic
 * Cyrillic block, which is enough for names and initials.
 * Loaded by Composer (autoload.files).
 */

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int
    {
        $count = preg_match_all('/./su', $string);

        return $count === false ? strlen($string) : $count;
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        $characters = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return $length === null ? substr($string, $start) : substr($string, $start, $length);
        }

        return implode('', $length === null ? array_slice($characters, $start) : array_slice($characters, $start, $length));
    }
}

if (!function_exists('mb_stripos')) {
    function mb_stripos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false
    {
        $length = mb_strlen($haystack);
        if ($offset < 0) {
            $offset = max(0, $length + $offset);
        }
        if ($offset > $length) {
            throw new ValueError('mb_stripos(): Argument #3 ($offset) must be contained in argument #1 ($haystack)');
        }
        $tail = mb_substr($haystack, $offset);
        if ($needle === '') {
            return $offset;
        }
        if (@preg_match('/' . preg_quote($needle, '/') . '/iu', $tail, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return false;
        }

        return $offset + mb_strlen(substr($tail, 0, $match[0][1]));
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $string, ?string $encoding = null): string
    {
        $ascii = static fn (string $text): string => strtr($text, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        if (preg_match('/[^\x00-\x7F]/', $string) !== 1) {
            return $ascii($string);
        }
        $out = preg_replace_callback(
            '/[^\x00-\x7F]/u',
            static function (array $match): string {
                $code = mb_fallback_ord($match[0]);
                if ($code === 0xDF) {
                    return 'SS';
                }
                if ($code === 0x149) {
                    return "\u{2BC}N";
                }
                $upper = match (true) {
                    $code === 0xB5 => 0x39C,
                    $code === 0x131 => 0x49,
                    $code === 0x17F => 0x53,
                    $code >= 0xE0 && $code <= 0xFE && $code !== 0xF7 => $code - 0x20,
                    $code === 0xFF => 0x178,
                    $code >= 0x100 && $code <= 0x137 && $code !== 0x131 => $code % 2 === 1 ? $code - 1 : $code,
                    $code >= 0x139 && $code <= 0x148 => $code % 2 === 0 ? $code - 1 : $code,
                    $code >= 0x14A && $code <= 0x177 => $code % 2 === 1 ? $code - 1 : $code,
                    $code >= 0x179 && $code <= 0x17E => $code % 2 === 0 ? $code - 1 : $code,
                    $code === 0x3AC => 0x386,
                    $code >= 0x3AD && $code <= 0x3AF => $code - 0x25,
                    $code === 0x3CC => 0x38C,
                    $code === 0x3CD || $code === 0x3CE => $code - 0x3F,
                    $code >= 0x3B1 && $code <= 0x3CB && $code !== 0x3C2 => $code - 0x20,
                    $code === 0x3C2 => 0x3A3,
                    $code >= 0x430 && $code <= 0x44F => $code - 0x20,
                    $code >= 0x450 && $code <= 0x45F => $code - 0x50,
                    default => $code,
                };

                return mb_fallback_chr($upper);
            },
            $ascii($string)
        );

        return $out ?? $ascii($string);
    }
}

if (!function_exists('mb_fallback_ord')) {
    function mb_fallback_ord(string $character): int
    {
        $bytes = array_values(unpack('C*', $character) ?: [0]);

        return match (count($bytes)) {
            1 => $bytes[0],
            2 => (($bytes[0] & 0x1F) << 6) | ($bytes[1] & 0x3F),
            3 => (($bytes[0] & 0x0F) << 12) | (($bytes[1] & 0x3F) << 6) | ($bytes[2] & 0x3F),
            default => (($bytes[0] & 0x07) << 18) | (($bytes[1] & 0x3F) << 12) | (($bytes[2] & 0x3F) << 6) | ($bytes[3] & 0x3F),
        };
    }
}

if (!function_exists('mb_fallback_chr')) {
    function mb_fallback_chr(int $code): string
    {
        return match (true) {
            $code < 0x80 => chr($code),
            $code < 0x800 => chr(0xC0 | ($code >> 6)) . chr(0x80 | ($code & 0x3F)),
            $code < 0x10000 => chr(0xE0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 0x3F)) . chr(0x80 | ($code & 0x3F)),
            default => chr(0xF0 | ($code >> 18)) . chr(0x80 | (($code >> 12) & 0x3F)) . chr(0x80 | (($code >> 6) & 0x3F)) . chr(0x80 | ($code & 0x3F)),
        };
    }
}
