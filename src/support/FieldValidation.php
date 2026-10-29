<?php
declare(strict_types=1);

namespace Adlexone\support;

/**
 * Checks a field value against its type (or a stored validation_type on text).
 */
final class FieldValidation
{
    /**
     * @param array<string, mixed> $field custom_fields row
     */
    public static function kind(array $field): string
    {
        $implied = FieldTypes::impliedValidation((string) ($field['field_type'] ?? ''));
        if ($implied !== '') {
            return $implied;
        }
        return FieldTypes::normaliseKind((string) ($field['validation_type'] ?? ''));
    }

    public static function jsType(string $kind): string
    {
        return match ($kind) {
            'date' => 'date',
            'time' => 'time',
            'datetime' => 'datetime',
            'email' => 'email',
            'numeric' => 'numeric',
            'url' => 'url',
            'ip' => 'ip',
            'string' => 'string',
            'alphanumeric' => 'alphanumeric',
            default => '',
        };
    }

    public static function message(string $kind, string $fieldName): string
    {
        $name = trim($fieldName);
        $in = $name !== '' ? ' in ' . $name : '';
        return match ($kind) {
            'date' => 'Enter a date' . $in . '.',
            'time' => 'Enter a time' . $in . '.',
            'datetime' => 'Enter a date and time' . $in . '.',
            'email' => 'Enter an email address' . $in . '.',
            'numeric' => 'Enter a number' . $in . '.',
            'url' => 'Enter a web address' . $in . '.',
            'ip' => 'Enter an IP address' . $in . '.',
            'string' => 'Enter letters' . $in . '.',
            'alphanumeric' => 'Enter letters or numbers' . $in . '.',
            default => $name !== '' ? 'Enter a value in ' . $name . '.' : 'Enter a value.',
        };
    }

    public static function requiredMessage(string $fieldName): string
    {
        $name = trim($fieldName);
        return $name !== '' ? 'Enter a value in ' . $name . '.' : 'This field is required.';
    }

    /**
     * @param array<string, mixed> $field
     */
    public static function error(mixed $value, array $field): ?string
    {
        $text = is_array($value) ? '' : trim((string) ($value ?? ''));
        $required = strcasecmp((string) ($field['required'] ?? ''), 'Yes') === 0;
        $label = (string) ($field['custom_field_name'] ?? '');
        $kind = self::kind($field);
        $type = FieldTypes::normalise((string) ($field['field_type'] ?? ''));
        if ($type === FieldTypes::CHECK_BOX) {
            if ($required && ($text === '' || $text === '0')) {
                return self::requiredMessage($label);
            }
            return null;
        }
        if ($text === '') {
            return $required ? self::requiredMessage($label) : null;
        }
        if ($kind === '' || self::isValid($kind, $text)) {
            return null;
        }
        return self::message($kind, $label);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, string> input name => message
     */
    public static function postedErrors(array $post, int $itemTypeId): array
    {
        if ($itemTypeId <= 0 || !Database::tableExists('custom_fields')) {
            return [];
        }
        $links = Database::select(
            'item_type_custom_fields',
            ['custom_field_id'],
            'item_type_id = ?',
            [$itemTypeId],
            'custom_field_order ASC'
        );
        $errors = [];
        foreach ($links as $link) {
            $field = Database::first('custom_fields', '*', 'custom_field_id = ?', [(int) $link['custom_field_id']]);
            if ($field === null) {
                continue;
            }
            $field['field_type'] = FieldTypes::normalise((string) ($field['field_type'] ?? ''));
            if (($field['enabled'] ?? '') !== 'Yes') {
                continue;
            }
            $type = (string) $field['field_type'];
            if (str_contains($type, 'worker') || $type === 'hidden' || $type === 'fieldSeparator') {
                continue;
            }
            if (MenuOptions::coveredByParentOnItemType((int) $field['custom_field_id'], $itemTypeId)) {
                continue;
            }
            $name = 'custom_field_' . (int) $field['custom_field_id'];
            $message = self::error($post[$name] ?? '', $field);
            if ($message !== null) {
                $errors[$name] = $message;
            }
        }
        return $errors;
    }

    public static function toDateInput(mixed $value): string
    {
        $date = self::parseDate((string) ($value ?? ''));
        return $date === null ? '' : $date->format('Y-m-d');
    }

    public static function toTimeInput(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $text, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }
        $date = self::parseDate($text);
        return $date === null ? '' : $date->format('H:i');
    }

    public static function toDatetimeInput(mixed $value): string
    {
        $date = self::parseDate((string) ($value ?? ''));
        return $date === null ? '' : $date->format('Y-m-d\TH:i');
    }

    /**
     * @param array<string, mixed> $field
     */
    public static function display(array $field, mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }
        return match (self::kind($field)) {
            'date' => self::formatDate($text),
            'time' => self::toTimeInput($text),
            'datetime' => self::formatDatetime($text),
            default => $text,
        };
    }

    public static function formatDate(string $value): string
    {
        $date = self::parseDate($value);
        if ($date === null) {
            return $value;
        }
        return $date->format(self::dateFormat());
    }

    public static function formatDatetime(string $value): string
    {
        $date = self::parseDate($value);
        if ($date === null) {
            return $value;
        }
        $format = defined('SET_DATE_FORMAT') ? (string) SET_DATE_FORMAT : 'd-m-Y, h:i A';
        return $date->format($format);
    }

    public static function today(): string
    {
        return (new \DateTimeImmutable('today'))->format(self::dateFormat());
    }

    public static function isValid(string $kind, string $value): bool
    {
        $value = trim($value);
        return match ($kind) {
            'date' => self::parseDate($value, false) !== null && !self::hasClock($value),
            'time' => preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m) === 1
                && (int) $m[1] <= 23 && (int) $m[2] <= 59 && (!isset($m[3]) || (int) $m[3] <= 59),
            'datetime' => self::parseDate($value, false) !== null,
            'email' => self::validEmail($value),
            'numeric' => is_numeric($value),
            'url' => self::validUrl($value),
            'ip' => filter_var($value, FILTER_VALIDATE_IP) !== false,
            'string' => preg_match("/^[a-z\\s'.-]+$/i", $value) === 1,
            'alphanumeric' => preg_match('/^[A-Za-z0-9_]+$/', $value) === 1,
            default => true,
        };
    }

    private static function hasClock(string $value): bool
    {
        return (bool) preg_match('/\d{1,2}:\d{2}/', $value);
    }

    private static function validEmail(string $value): bool
    {
        foreach (explode(',', $value) as $address) {
            $address = trim($address);
            if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                return false;
            }
        }
        return true;
    }

    private static function validUrl(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            $value = 'http://' . $value;
        }
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public static function parseDate(string $value, bool $loose = true): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value) === 1) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i' . (substr_count($value, ':') === 2 ? ':s' : ''), $value);
            return self::strict($parsed);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return self::strict(\DateTimeImmutable::createFromFormat('!Y-m-d', $value));
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $value, $m) === 1) {
            $year = (int) $m[3];
            if ($year < 100) {
                $year += $year > 50 ? 1900 : 2000;
            }
            $dmy = self::dayFirst();
            $day = $dmy ? (int) $m[1] : (int) $m[2];
            $month = $dmy ? (int) $m[2] : (int) $m[1];
            $hour = isset($m[4]) ? (int) $m[4] : 0;
            $minute = isset($m[5]) ? (int) $m[5] : 0;
            $second = isset($m[6]) ? (int) $m[6] : 0;
            if (!checkdate($month, $day, $year)) {
                return null;
            }
            return \DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second)
            ) ?: null;
        }
        if ($loose && ctype_digit($value) && strlen($value) >= 9) {
            $stamp = (int) $value;
            if ($stamp > 0) {
                return (new \DateTimeImmutable())->setTimestamp($stamp);
            }
        }
        if (!$loose) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private static function strict(\DateTimeImmutable|false|null $date): ?\DateTimeImmutable
    {
        if (!$date instanceof \DateTimeImmutable) {
            return null;
        }
        $errors = \DateTimeImmutable::getLastErrors();
        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return null;
        }
        return $date;
    }

    private static function dayFirst(): bool
    {
        if (!defined('SET_DATE_FORMAT')) {
            return true;
        }
        $head = strtolower(trim(explode(',', (string) SET_DATE_FORMAT)[0]));
        return !str_starts_with($head, 'm');
    }

    private static function dateFormat(): string
    {
        if (!defined('SET_DATE_FORMAT')) {
            return 'd-m-Y';
        }
        $head = trim(explode(',', (string) SET_DATE_FORMAT)[0]);
        return $head !== '' ? $head : 'd-m-Y';
    }
}
