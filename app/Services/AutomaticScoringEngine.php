<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class AutomaticScoringEngine
{
    /**
     * @param array<int,array{id:int,dance_role:string}> $entries
     * @param array<int,array{id:int,is_chief:int,scoring_scope:string}> $judges
     * @param array<int,array<int,float|int|string|null>> $marks entry id => judge id => score
     * @return array<int,array<string,int|float|string|null>>
     */
    public static function calculateHeats(
        array $entries,
        array $judges,
        array $marks,
        int $callbackCount,
        int $minimumJudges = 3
    ): array {
        if ($callbackCount < 1) {
            throw new RuntimeException('Callback count must be at least 1.');
        }

        $chiefIds = array_values(array_map(
            static fn(array $judge): int => (int)$judge['id'],
            array_filter($judges, static fn(array $judge): bool => !empty($judge['is_chief']))
        ));
        if (count($chiefIds) !== 1) {
            throw new RuntimeException('Exactly one Chief Judge is required.');
        }
        $chiefId = $chiefIds[0];
        $chiefJudge = current(array_filter(
            $judges,
            static fn(array $judge): bool => (int)$judge['id'] === $chiefId
        ));
        if (($chiefJudge['scoring_scope'] ?? 'all') !== 'all') {
            throw new RuntimeException('The Chief Judge must be assigned to All competitors in Automatic Scoring.');
        }

        $rowsByRole = ['leader' => [], 'follower' => []];
        foreach ($entries as $entry) {
            $entryId = (int)$entry['id'];
            $role = (string)$entry['dance_role'];
            if (!isset($rowsByRole[$role])) {
                throw new RuntimeException('Invalid competitor role in the scoring round.');
            }

            $scores = [];
            $scoreByJudge = [];
            foreach ($judges as $judge) {
                $scope = (string)($judge['scoring_scope'] ?? 'all');
                if (!in_array($scope, ['all', $role], true)) {
                    continue;
                }
                $judgeId = (int)$judge['id'];
                $raw = $marks[$entryId][$judgeId] ?? null;
                if ($raw === null || $raw === '') {
                    throw new RuntimeException('Every assigned judge must score every active competitor.');
                }
                if (!is_numeric($raw)) {
                    throw new RuntimeException('Automatic scores must be numeric.');
                }
                $score = round((float)$raw, 2);
                if ($score < 0 || $score > 100) {
                    throw new RuntimeException('Automatic scores must be between 0 and 100.');
                }
                $scores[] = $score;
                $scoreByJudge[$judgeId] = $score;
            }

            if (count($scores) < $minimumJudges) {
                throw new RuntimeException(ucfirst($role).' competitors require at least '.$minimumJudges.' valid judge scores.');
            }

            $rowsByRole[$role][] = [
                'entry_id' => $entryId,
                'average_score' => round(array_sum($scores) / count($scores), 4),
                'score_total' => round(array_sum($scores), 2),
                'valid_judges' => count($scores),
                'chief_score' => $scoreByJudge[$chiefId] ?? null,
                'scores' => $scoreByJudge,
                'majority_wins' => 0,
            ];
        }

        $results = [];
        foreach ($rowsByRole as $role => $rows) {
            foreach ($rows as $leftIndex => $left) {
                foreach ($rows as $rightIndex => $right) {
                    if ($leftIndex >= $rightIndex || abs($left['average_score'] - $right['average_score']) > 0.0001) {
                        continue;
                    }
                    $leftVotes = 0;
                    $rightVotes = 0;
                    foreach ($left['scores'] as $judgeId => $leftScore) {
                        $rightScore = $right['scores'][$judgeId] ?? null;
                        if ($rightScore === null) {
                            continue;
                        }
                        if ($leftScore > $rightScore) {
                            $leftVotes++;
                        } elseif ($rightScore > $leftScore) {
                            $rightVotes++;
                        }
                    }
                    if ($leftVotes > $rightVotes) {
                        $rows[$leftIndex]['majority_wins']++;
                    } elseif ($rightVotes > $leftVotes) {
                        $rows[$rightIndex]['majority_wins']++;
                    }
                }
            }

            usort($rows, static function (array $a, array $b): int {
                if (abs($a['average_score'] - $b['average_score']) > 0.0001) {
                    return $b['average_score'] <=> $a['average_score'];
                }
                if ($a['majority_wins'] !== $b['majority_wins']) {
                    return $b['majority_wins'] <=> $a['majority_wins'];
                }
                $chiefA = $a['chief_score'] ?? -1;
                $chiefB = $b['chief_score'] ?? -1;
                if (abs($chiefA - $chiefB) > 0.0001) {
                    return $chiefB <=> $chiefA;
                }
                return $a['entry_id'] <=> $b['entry_id'];
            });

            foreach ($rows as $index => &$row) {
                $row['rank'] = $index + 1;
                $row['status'] = $row['rank'] <= $callbackCount
                    ? 'callback'
                    : ($row['rank'] <= $callbackCount + 3 ? 'alternate' : 'eliminated');
                $row['alternate_rank'] = $row['status'] === 'alternate'
                    ? $row['rank'] - $callbackCount
                    : null;
                $row['role'] = $role;
            }
            unset($row);

            if (isset($rows[$callbackCount - 1], $rows[$callbackCount])) {
                $lastCallback = $rows[$callbackCount - 1];
                $firstOutside = $rows[$callbackCount];
                $unresolved = abs($lastCallback['average_score'] - $firstOutside['average_score']) < 0.0001
                    && $lastCallback['majority_wins'] === $firstOutside['majority_wins']
                    && abs((float)$lastCallback['chief_score'] - (float)$firstOutside['chief_score']) < 0.0001;
                if ($unresolved) {
                    foreach ($rows as &$row) {
                        if (abs($row['average_score'] - $lastCallback['average_score']) < 0.0001
                            && $row['majority_wins'] === $lastCallback['majority_wins']
                            && abs((float)$row['chief_score'] - (float)$lastCallback['chief_score']) < 0.0001) {
                            $row['status'] = 'tie_pending';
                            $row['rank'] = $callbackCount;
                            $row['alternate_rank'] = null;
                        }
                    }
                    unset($row);
                }
            }
            $results = array_merge($results, $rows);
        }

        return $results;
    }
}
