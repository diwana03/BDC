<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class DanceCupScoringService
{
    /** @return array{competitions:string,criteria:string,events:string} */
    public static function tables(bool $test = false): array
    {
        return $test
            ? ['competitions' => 'bdc_test_dance_cup_competitions', 'criteria' => 'bdc_test_dance_cup_criteria', 'events' => 'bdc_test_events']
            : ['competitions' => 'bdc_dance_cup_competitions', 'criteria' => 'bdc_dance_cup_criteria', 'events' => 'bdc_events'];
    }

    /** @param array<int,array{name:string,max:float}> $criteria */
    public static function createCompetition(PDO $pdo, array $data, array $criteria, ?int $userId, bool $test = false): int
    {
        $tables = self::tables($test);
        $eventId = (int) ($data['event_id'] ?? 0);
        $category = trim((string) ($data['category_name'] ?? ''));
        $entryType = (string) ($data['entry_type'] ?? 'solo');
        $roundName = (string) ($data['round_name'] ?? 'final');
        if ($eventId < 1 || $category === '') throw new RuntimeException('Event and category name are required.');
        if (!in_array($entryType, ['solo', 'couple', 'duo', 'team'], true)) throw new RuntimeException('Invalid entry type.');
        if (!in_array($roundName, ['qualifier', 'quarterfinal', 'semifinal', 'final'], true)) throw new RuntimeException('Invalid Dance Cup round.');
        if (!$criteria) throw new RuntimeException('Add at least one scoring criterion.');
        $maximum = 0.0;
        $seen = [];
        foreach ($criteria as $criterion) {
            $name = trim($criterion['name']);
            $max = (float) $criterion['max'];
            $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            if ($name === '' || $max <= 0) throw new RuntimeException('Every criterion needs a name and a maximum above zero.');
            if (isset($seen[$key])) throw new RuntimeException('Criterion names must be unique.');
            $seen[$key] = true;
            $maximum += $max;
        }
        if ($maximum > 1000) throw new RuntimeException('The combined maximum score cannot exceed 1000.');

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare("INSERT INTO {$tables['competitions']}(event_id,category_name,entry_type,dance_style,round_name,maximum_score,created_by) VALUES(:event,:category,:entry_type,:dance_style,:round_name,:maximum,:user)");
            $insert->execute([
                'event' => $eventId,
                'category' => $category,
                'entry_type' => $entryType,
                'dance_style' => trim((string) ($data['dance_style'] ?? '')) ?: null,
                'round_name' => $roundName,
                'maximum' => $maximum,
                'user' => $userId ?: null,
            ]);
            $competitionId = (int) $pdo->lastInsertId();
            $criterionInsert = $pdo->prepare("INSERT INTO {$tables['criteria']}(competition_id,criterion_name,maximum_points,sort_order) VALUES(:competition,:name,:maximum,:sort)");
            foreach ($criteria as $index => $criterion) {
                $criterionInsert->execute(['competition' => $competitionId, 'name' => trim($criterion['name']), 'maximum' => (float) $criterion['max'], 'sort' => $index + 1]);
            }
            $pdo->commit();
            return $competitionId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
