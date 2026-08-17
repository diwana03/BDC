<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class RelativePlacementCalculator
{
    /**
     * @param int[] $pairIds
     * @param int[] $judgeIds
     * @param array<int,array<int,int>> $marks pair_id => judge_id => rank
     * @return array<int,array<string,mixed>>
     */
    public static function calculate(
        array $pairIds,
        array $judgeIds,
        int $chiefJudgeId,
        array $marks,
        ?int $rankLimit = null
    ): array {
        $pairIds = array_values(array_map('intval', $pairIds));
        $judgeIds = array_values(array_map('intval', $judgeIds));
        $pairCount = count($pairIds);
        $judgeCount = count($judgeIds);
        $rankLimit = min($pairCount, max(1, $rankLimit ?? $pairCount));

        if ($pairCount < 1) {
            throw new RuntimeException('At least one confirmed Final couple is required.');
        }
        if ($judgeCount < 3) {
            throw new RuntimeException('At least 3 judges are required for Final scoring.');
        }

        foreach ($judgeIds as $judgeId) {
            $used = [];
            foreach ($pairIds as $pairId) {
                $rank = (int)($marks[$pairId][$judgeId] ?? 0);
                if ($rank === 0) {
                    continue;
                }
                if ($rank < 1 || $rank > $rankLimit) {
                    throw new RuntimeException(
                        'Every judge must use ranks 1 to ' . $rankLimit . ' only.'
                    );
                }
                if (isset($used[$rank])) {
                    throw new RuntimeException(
                        'A judge used rank ' . $rank . ' more than once.'
                    );
                }
                $used[$rank] = true;
            }
            $usedRanks = array_keys($used);
            sort($usedRanks, SORT_NUMERIC);
            if ($usedRanks !== range(1, $rankLimit)) {
                throw new RuntimeException('Every judge must use each rank from 1 to ' . $rankLimit . ' exactly once. Other couples remain unranked.');
            }
        }

        $majority = (int)floor($judgeCount / 2) + 1;
        $remaining = $pairIds;
        $final = [];
        $place = 1;

        while ($remaining) {
            $profiles = [];
            foreach ($remaining as $pairId) {
                $profile = [
                    'pair_id' => $pairId,
                    'levels' => [],
                    'initial_level' => $rankLimit + 1,
                    'chief_rank' => $chiefJudgeId > 0
                        ? (int)($marks[$pairId][$chiefJudgeId] ?? ($rankLimit + 1))
                        : ($rankLimit + 1),
                    'total_sum' => 0,
                ];

                foreach ($judgeIds as $judgeId) {
                    $profile['total_sum'] += (int)($marks[$pairId][$judgeId] ?? ($rankLimit + 1));
                }

                for ($level = 1; $level <= $rankLimit + 1; $level++) {
                    $count = 0;
                    $sum = 0;
                    $included = [];

                    foreach ($judgeIds as $judgeId) {
                        $rank = (int)($marks[$pairId][$judgeId] ?? ($rankLimit + 1));
                        if ($rank <= $level) {
                            $count++;
                            $sum += $rank;
                            $included[] = [
                                'judge_id' => $judgeId,
                                'rank' => $rank,
                            ];
                        }
                    }

                    $profile['levels'][$level] = [
                        'level' => $level,
                        'count' => $count,
                        'sum' => $sum,
                        'included' => $included,
                    ];

                    if (
                        $profile['initial_level'] === $rankLimit + 1
                        && $count >= $majority
                    ) {
                        $profile['initial_level'] = $level;
                    }
                }

                $profiles[$pairId] = $profile;
            }

            $comparisonLog = [];
            $candidateIds = $remaining;

            $earliestLevel = min(array_map(
                fn(int $pairId): int => $profiles[$pairId]['initial_level'],
                $candidateIds
            ));
            $candidateIds = array_values(array_filter(
                $candidateIds,
                fn(int $pairId): bool =>
                    $profiles[$pairId]['initial_level'] === $earliestLevel
            ));
            $comparisonLog[] = [
                'step' => 'earliest_majority',
                'level' => $earliestLevel,
                'remaining' => $candidateIds,
            ];

            // Correct Relative Placement tie resolution:
            // compare count and sum at the majority level, then keep expanding
            // through Top N until the tie is broken.
            for (
                $level = $earliestLevel;
                $level <= $rankLimit + 1 && count($candidateIds) > 1;
                $level++
            ) {
                $bestCount = max(array_map(
                    fn(int $pairId): int =>
                        $profiles[$pairId]['levels'][$level]['count'],
                    $candidateIds
                ));
                $candidateIds = array_values(array_filter(
                    $candidateIds,
                    fn(int $pairId): bool =>
                        $profiles[$pairId]['levels'][$level]['count'] === $bestCount
                ));
                $comparisonLog[] = [
                    'step' => 'count',
                    'level' => $level,
                    'best' => $bestCount,
                    'remaining' => $candidateIds,
                ];

                if (count($candidateIds) <= 1) {
                    break;
                }

                $bestSum = min(array_map(
                    fn(int $pairId): int =>
                        $profiles[$pairId]['levels'][$level]['sum'],
                    $candidateIds
                ));
                $candidateIds = array_values(array_filter(
                    $candidateIds,
                    fn(int $pairId): bool =>
                        $profiles[$pairId]['levels'][$level]['sum'] === $bestSum
                ));
                $comparisonLog[] = [
                    'step' => 'sum',
                    'level' => $level,
                    'best' => $bestSum,
                    'remaining' => $candidateIds,
                ];
            }

            // Once cumulative Relative Placement comparisons are exhausted,
            // compare the tied couples as a mini-contest. A judge votes for
            // whichever tied couple they placed higher. This is evaluated
            // before the Chief Judge fallback.
            if (count($candidateIds) > 1) {
                $headToHeadWins = array_fill_keys($candidateIds, 0);
                foreach ($candidateIds as $leftIndex => $leftId) {
                    foreach ($candidateIds as $rightIndex => $rightId) {
                        if ($leftIndex >= $rightIndex) continue;
                        $leftVotes = 0;
                        $rightVotes = 0;
                        foreach ($judgeIds as $judgeId) {
                            $leftRank = (int)($marks[$leftId][$judgeId] ?? ($rankLimit + 1));
                            $rightRank = (int)($marks[$rightId][$judgeId] ?? ($rankLimit + 1));
                            if ($leftRank < $rightRank) $leftVotes++;
                            elseif ($rightRank < $leftRank) $rightVotes++;
                        }
                        if ($leftVotes > $rightVotes) $headToHeadWins[$leftId]++;
                        elseif ($rightVotes > $leftVotes) $headToHeadWins[$rightId]++;
                    }
                }
                $bestHeadToHead = max($headToHeadWins);
                $candidateIds = array_values(array_filter(
                    $candidateIds,
                    fn(int $pairId): bool => $headToHeadWins[$pairId] === $bestHeadToHead
                ));
                $comparisonLog[] = [
                    'step' => 'head_to_head',
                    'best' => $bestHeadToHead,
                    'wins' => $headToHeadWins,
                    'remaining' => $candidateIds,
                ];
            }

            if (count($candidateIds) > 1 && $chiefJudgeId > 0) {
                $bestChiefRank = min(array_map(
                    fn(int $pairId): int => $profiles[$pairId]['chief_rank'],
                    $candidateIds
                ));
                $candidateIds = array_values(array_filter(
                    $candidateIds,
                    fn(int $pairId): bool =>
                        $profiles[$pairId]['chief_rank'] === $bestChiefRank
                ));
                $comparisonLog[] = [
                    'step' => 'chief_judge',
                    'best' => $bestChiefRank,
                    'remaining' => $candidateIds,
                ];
            }

            if (count($candidateIds) > 1) {
                throw new RuntimeException(
                    'Final placement remains tied after Relative Placement, head-to-head and Chief Judge comparison.'
                );
            }

            sort($candidateIds, SORT_NUMERIC);
            $winnerId = $candidateIds[0];
            $winner = $profiles[$winnerId];
            $initial = $winner['levels'][$winner['initial_level']];

            $decidingStep = 'earliest_majority';
            foreach (array_reverse($comparisonLog) as $logRow) {
                if (count($logRow['remaining'] ?? []) === 1) {
                    $decidingStep = (string)$logRow['step'];
                    break;
                }
            }

            $final[] = [
                'pair_id' => $winnerId,
                'final_rank' => $place++,
                'level' => $winner['initial_level'],
                'count' => $initial['count'],
                'sum' => $initial['sum'],
                'chief_rank' => $winner['chief_rank'],
                'total_sum' => $winner['total_sum'],
                'majority' => $majority,
                'levels' => $winner['levels'],
                'comparison_log' => $comparisonLog,
                'deciding_step' => $decidingStep,
            ];

            $remaining = array_values(array_filter(
                $remaining,
                fn(int $pairId): bool => $pairId !== $winnerId
            ));
            if ($place > $rankLimit) {
                break;
            }
        }

        return $final;
    }
}
