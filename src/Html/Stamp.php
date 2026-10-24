<?php
declare(strict_types=1);

namespace Adlexone\Html;

/**
 * Formats timestamps with the site date format, so every screen shows a date the same way.
 */
final class Stamp
{
    private const DEFAULT_FORMAT = 'd-m-Y, h:i A';

    public static function dayTime(int $timestamp): string
    {
        return date(self::format(), $timestamp);
    }

    public static function day(int $timestamp): string
    {
        $head = trim(explode(',', self::format())[0]);

        return date($head !== '' ? $head : 'd-m-Y', $timestamp);
    }

    private static function format(): string
    {
        return defined('SET_DATE_FORMAT') && trim((string) SET_DATE_FORMAT) !== ''
            ? (string) SET_DATE_FORMAT
            : self::DEFAULT_FORMAT;
    }
}
