<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Decides which individual roles require preliminary judging.
 *
 * This deliberately does not calculate scores or placements. The established
 * scoring engines still own weights, ties, ranking and callback results.
 */
final class RoleAdvancementService
{
    /** @return array{count:int,yes_required:int,alternate_count:int,direct_to_final:bool,requires_judging:bool} */
    public static function rolePlan(int $count, int $yesQuota): array
    {
        $count=max(0,$count);
        $yesQuota=max(1,$yesQuota);
        $direct=$count>0 && $count<=$yesQuota;
        return [
            'count'=>$count,
            'yes_required'=>$direct?0:min($yesQuota,$count),
            'alternate_count'=>$direct?0:min(3,max(0,$count-$yesQuota)),
            'direct_to_final'=>$direct,
            'requires_judging'=>$count>0 && !$direct,
        ];
    }

    /** @return array{leader:array,follower:array} */
    public static function roundPlan(int $leaders, int $followers, int $yesQuota): array
    {
        return [
            'leader'=>self::rolePlan($leaders,$yesQuota),
            'follower'=>self::rolePlan($followers,$yesQuota),
        ];
    }
}
