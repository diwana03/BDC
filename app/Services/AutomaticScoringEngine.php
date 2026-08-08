<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Automatic scoring is an input channel, not a separate scoring formula.
 *
 * Judges submit BDC YES/A1/A2/A3 marks from their browsers. Those marks are
 * normalized to the same locked BDC weights used by Manual scoring, then this
 * adapter delegates the calculation to HeatsScoringEngine.
 */
final class AutomaticScoringEngine
{
    /**
     * @param array<int,array<string,mixed>> $entries
     * @param array<int,array<string,mixed>> $judges
     * @param array<int,array<int,float|int|string|null>> $marks entry id => judge id => normalized weighted score
     * @return array<int,array<string,mixed>>
     */
    public static function calculateHeats(
        array $entries,
        array $judges,
        array $marks,
        int $callbackCount,
        int $minimumJudges = ScoringRulesService::MINIMUM_JUDGES_PER_ROLE
    ): array {
        // Keep the public method signature used by the browser workflow, but the
        // minimum is governed centrally by ScoringRulesService.
        unset($minimumJudges);

        $byRole=HeatsScoringEngine::calculate($judges,$entries,$marks,$callbackCount);
        $results=[];
        foreach(['leader','follower'] as $role){
            foreach($byRole[$role] as $row){
                $results[]=[
                    'entry_id'=>$row['entry_id'],
                    'score_total'=>$row['total_score'],
                    'average_score'=>$row['total_score'],
                    'chief_score'=>$row['chief_score'],
                    'rank'=>$row['rank_number'],
                    'status'=>$row['result_status'],
                    'alternate_rank'=>$row['alternate_rank'],
                    'role'=>$role,
                    'valid_judges'=>null,
                    'majority_wins'=>null,
                    'scores'=>$marks[$row['entry_id']]??[],
                ];
            }
        }
        return $results;
    }
}
