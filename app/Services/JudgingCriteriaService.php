<?php
declare(strict_types=1);

namespace App\Services;

final class JudgingCriteriaService
{
    public const VERSION = 'BDC-2026.1';

    public static function weights(): array
    {
        return [
            ['Timing', '20%'], ['Technique', '20%'], ['Connection', '20%'],
            ['Musicality', '20%'], ['Presentation', '10%'], ['Difficulty', '10%'],
        ];
    }

    public static function requiresAcceptance(array $session): bool
    {
        return empty($session['criteria_accepted_at']) || (string)($session['criteria_version'] ?? '') !== self::VERSION;
    }
}
