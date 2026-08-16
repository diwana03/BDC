<?php
declare(strict_types=1);

namespace App\Services;

final class ScoringRulesService
{
    public const YES_WEIGHT = 10.0;
    public const ALT1_WEIGHT = 4.5;
    public const ALT2_WEIGHT = 4.3;
    public const ALT3_WEIGHT = 4.2;
    public const ALTERNATE_COUNT = 3;
    public const MINIMUM_JUDGES_PER_ROLE = 3;

    public static function weights(): array
    {
        return [
            'yes' => self::YES_WEIGHT,
            'alt1' => self::ALT1_WEIGHT,
            'alt2' => self::ALT2_WEIGHT,
            'alt3' => self::ALT3_WEIGHT,
        ];
    }

    public static function tierFromRoleCount(int $largestRoleCount): int
    {
        if ($largestRoleCount <= 15) return 1;
        if ($largestRoleCount <= 30) return 2;
        return 3;
    }

    public static function yesCountForTier(int $tier): int
    {
        return match ($tier) {
            1 => 5,
            2 => 10,
            3 => 15,
            default => throw new \InvalidArgumentException('Unknown BDC scoring tier.'),
        };
    }

    public static function tierFromRoleCounts(int $leaders, int $followers): array
    {
        $largest = max(0, $leaders, $followers);
        $tier = self::tierFromRoleCount($largest);
        return [
            'leaders' => max(0, $leaders),
            'followers' => max(0, $followers),
            'largest' => $largest,
            'tier' => $tier,
            'yes_count' => self::yesCountForTier($tier),
            'alternate_count' => self::ALTERNATE_COUNT,
            'weights' => self::weights(),
        ];
    }

    public static function normalizeNormalRoundTier(
        int $leaders,
        int $followers,
        int $storedYesCount,
        int $storedCallbackCount,
        bool $manualOverride
    ): array {
        $automatic = self::tierFromRoleCounts($leaders, $followers);
        $yesCount = (int) $automatic['yes_count'];
        return array_merge($automatic, [
            'callback_count' => $yesCount,
            'corrected' => $storedYesCount !== $yesCount || $storedCallbackCount !== $yesCount,
        ]);
    }

    public static function markWeight(string $markType, ?int $alternateRank = null): float
    {
        $markType = strtolower(trim($markType));
        if ($markType === 'yes') return self::YES_WEIGHT;
        if ($markType !== 'alt') return 0.0;
        return match ($alternateRank) {
            1 => self::ALT1_WEIGHT,
            2 => self::ALT2_WEIGHT,
            3 => self::ALT3_WEIGHT,
            default => 0.0,
        };
    }

    public static function isSpecialCategory(string $division): bool
    {
        return SpecialCategoryService::isSpecial($division);
    }

    public static function specialPointSchedule(string $division): array
    {
        return SpecialCategoryService::schedule($division);
    }

    public static function specialPoints(string $division, int $rank): float
    {
        return SpecialCategoryService::fixedPoints($division, $rank);
    }
}
