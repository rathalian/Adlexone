<?php
declare(strict_types=1);

namespace Adlexone\support;

/**
 * Session keys carried across a form repost, marked with the VBL_ prefix.
 */
final class FormState
{
    public const PREFIX = 'VBL_';

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public static function keys(array $values, string $action): array
    {
        switch ($action) {
            case 'add':
                foreach (array_keys($values) as $key) {
                    if (stripos((string) $key, self::PREFIX) !== 0) {
                        $values[self::PREFIX . $key] = $values[$key];
                        unset($values[$key]);
                    }
                }
                break;
            case 'remove':
                foreach (array_keys($values) as $key) {
                    $name = (string) $key;
                    if (stripos($name, self::PREFIX) === 0) {
                        $values[substr($name, strlen(self::PREFIX))] = $values[$key];
                        unset($values[$key]);
                    }
                }
                break;
            case 'unset':
                $values = array_filter(
                    $values,
                    static fn ($key) => stripos((string) $key, self::PREFIX) !== 0,
                    ARRAY_FILTER_USE_KEY
                );
                break;
        }

        return $values;
    }
}
