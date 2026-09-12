<?php
declare(strict_types=1);

namespace App\Services;

final class ProjectionNameService
{
    /**
     * Shorten people names for audience projection. First names are used unless
     * the current screen contains a duplicate, in which case the surname initial
     * is added to every matching first name.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $fields
     * @return array<int,array<string,mixed>>
     */
    public static function abbreviateRows(array $rows, array $fields): array
    {
        $names = [];
        foreach ($rows as $rowIndex => $row) {
            foreach ($fields as $field) {
                $name = trim((string) ($row[$field] ?? ''));
                if ($name !== '') {
                    $names[$rowIndex . ':' . $field] = $name;
                }
            }
        }

        $short = self::abbreviateNames($names);
        foreach ($rows as $rowIndex => &$row) {
            foreach ($fields as $field) {
                $key = $rowIndex . ':' . $field;
                if (isset($short[$key])) {
                    $row[$field] = $short[$key];
                }
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Shorten each person in a saved partner identity without dropping either
     * side of the ampersand. A malformed identity without an ampersand is kept
     * intact so projection never silently turns a duet into one contestant.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $fields
     * @return array<int,array<string,mixed>>
     */
    public static function abbreviatePartnerRows(array $rows, array $fields): array
    {
        $people = [];
        $rowParts = [];
        foreach ($rows as $rowIndex => $row) {
            foreach ($fields as $field) {
                $name = trim((string) ($row[$field] ?? ''));
                $parts = array_map('trim', preg_split('/\s*&\s*/u', $name) ?: []);
                if (count($parts) < 2 || in_array('', $parts, true)) continue;
                foreach ($parts as $partIndex => $part) {
                    $key = $rowIndex . ':' . $field . ':' . $partIndex;
                    $people[$key] = $part;
                    $rowParts[$rowIndex][$field][] = $key;
                }
            }
        }

        $short = self::abbreviateNames($people);
        foreach ($rowParts as $rowIndex => $fieldParts) {
            foreach ($fieldParts as $field => $keys) {
                $rows[$rowIndex][$field] = implode(' & ', array_map(
                    static fn(string $key): string => $short[$key],
                    $keys
                ));
            }
        }
        return $rows;
    }

    /**
     * @param array<string,string> $names
     * @return array<string,string>
     */
    public static function abbreviateNames(array $names): array
    {
        $parsed = [];
        $firstCounts = [];
        foreach ($names as $key => $name) {
            $parts = preg_split('/\s+/u', trim($name)) ?: [];
            $first = (string) ($parts[0] ?? '');
            $last = count($parts) > 1 ? (string) end($parts) : '';
            $countKey = self::lower($first);
            $parsed[$key] = [$first, $last, $countKey];
            $firstCounts[$countKey] = ($firstCounts[$countKey] ?? 0) + 1;
        }

        $result = [];
        foreach ($parsed as $key => [$first, $last, $countKey]) {
            $result[$key] = $first;
            if (($firstCounts[$countKey] ?? 0) > 1 && $last !== '') {
                $result[$key] .= ' ' . self::firstCharacter($last);
            }
        }
        return $result;
    }

    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function firstCharacter(string $value): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, 1, 'UTF-8') : substr($value, 0, 1);
    }
}
