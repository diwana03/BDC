<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class DanceCupRosterService
{
    public static function apply(PDO $pdo, int $competitionId, string $action, array $input, bool $test = false): string
    {
        $prefix = $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup';
        return match ($action) {
            'remove_competitor' => self::removeCompetitor($pdo, $prefix, $competitionId, (int) ($input['entry_id'] ?? 0)),
            'move_competitor' => self::move($pdo, $prefix, $competitionId, 'entry', (int) ($input['entry_id'] ?? 0), (string) ($input['direction'] ?? '')),
            'remove_judge' => self::removeJudge($pdo, $prefix, $competitionId, (int) ($input['judge_assignment_id'] ?? 0)),
            'move_judge' => self::move($pdo, $prefix, $competitionId, 'judge', (int) ($input['judge_assignment_id'] ?? 0), (string) ($input['direction'] ?? '')),
            'set_chief_judge' => self::setChief($pdo, $prefix, $competitionId, (int) ($input['judge_assignment_id'] ?? 0)),
            default => throw new RuntimeException('Unsupported Dance Cup roster action.'),
        };
    }

    public static function makeAddedJudgeChief(PDO $pdo, string $prefix, int $competitionId, int $judgeId): void
    {
        $pdo->prepare("UPDATE {$prefix}_judges SET is_chief=(id=:judge) WHERE competition_id=:competition")
            ->execute(['judge' => $judgeId, 'competition' => $competitionId]);
    }

    private static function removeCompetitor(PDO $pdo, string $prefix, int $competitionId, int $entryId): string
    {
        self::assertMember($pdo, "{$prefix}_entries", $competitionId, $entryId);
        self::assertNoMarks($pdo, $prefix, $competitionId, 'entry_id', $entryId, 'contestant');
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM {$prefix}_scoring_results WHERE competition_id=:competition AND entry_id=:entry")
                ->execute(['competition' => $competitionId, 'entry' => $entryId]);
            $pdo->prepare("DELETE FROM {$prefix}_entries WHERE competition_id=:competition AND id=:entry")
                ->execute(['competition' => $competitionId, 'entry' => $entryId]);
            self::renumber($pdo, $prefix, $competitionId, 'entry');
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return 'Contestant removed and roster order updated.';
    }

    private static function removeJudge(PDO $pdo, string $prefix, int $competitionId, int $judgeId): string
    {
        self::assertMember($pdo, "{$prefix}_judges", $competitionId, $judgeId);
        self::assertNoMarks($pdo, $prefix, $competitionId, 'judge_id', $judgeId, 'judge');
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM {$prefix}_judge_sessions WHERE competition_id=:competition AND judge_assignment_id=:judge")
                ->execute(['competition' => $competitionId, 'judge' => $judgeId]);
            $pdo->prepare("DELETE FROM {$prefix}_judges WHERE competition_id=:competition AND id=:judge")
                ->execute(['competition' => $competitionId, 'judge' => $judgeId]);
            self::renumber($pdo, $prefix, $competitionId, 'judge');
            $chief = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}_judges WHERE competition_id=:competition AND is_chief=1");
            $chief->execute(['competition' => $competitionId]);
            if ((int) $chief->fetchColumn() === 0) {
                $pdo->prepare("UPDATE {$prefix}_judges SET is_chief=1 WHERE competition_id=:competition ORDER BY judge_order,id LIMIT 1")
                    ->execute(['competition' => $competitionId]);
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return 'Judge removed and panel order updated.';
    }

    private static function setChief(PDO $pdo, string $prefix, int $competitionId, int $judgeId): string
    {
        self::assertMember($pdo, "{$prefix}_judges", $competitionId, $judgeId);
        $marks = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}_marks WHERE competition_id=:competition");
        $marks->execute(['competition' => $competitionId]);
        if ((int) $marks->fetchColumn() > 0) {
            throw new RuntimeException('Cannot change the Chief Judge after scoring has started. Use the protected scoring recovery workflow.');
        }
        self::makeAddedJudgeChief($pdo, $prefix, $competitionId, $judgeId);
        return 'Chief Judge updated.';
    }

    private static function move(PDO $pdo, string $prefix, int $competitionId, string $kind, int $id, string $direction): string
    {
        if (!in_array($direction, ['up', 'down'], true)) throw new RuntimeException('Invalid roster direction.');
        $table = $kind === 'judge' ? "{$prefix}_judges" : "{$prefix}_entries";
        self::assertMember($pdo, $table, $competitionId, $id);
        $order = $kind === 'judge' ? 'judge_order,id' : 'bib_number,id';
        $query = $pdo->prepare("SELECT id FROM {$table} WHERE competition_id=:competition" . ($kind === 'entry' ? " AND status='active'" : '') . " ORDER BY {$order}");
        $query->execute(['competition' => $competitionId]);
        $ids = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
        $index = array_search($id, $ids, true);
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index === false || !isset($ids[$target])) return ucfirst($kind) . ' is already at the panel boundary.';
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        $pdo->beginTransaction();
        try {
            if ($kind === 'judge') {
                $update = $pdo->prepare("UPDATE {$table} SET judge_order=:position WHERE competition_id=:competition AND id=:id");
                foreach ($ids as $position => $rowId) $update->execute(['position' => $position + 1, 'competition' => $competitionId, 'id' => $rowId]);
            } else {
                $bibs = $pdo->prepare("SELECT id,bib_number FROM {$table} WHERE competition_id=:competition AND status='active'");
                $bibs->execute(['competition' => $competitionId]);
                $numbers = array_column($bibs->fetchAll(), 'bib_number', 'id');
                $first = $ids[$target]; $second = $ids[$index];
                $pdo->prepare("UPDATE {$table} SET bib_number=0 WHERE id=:id AND competition_id=:competition")->execute(['id' => $first, 'competition' => $competitionId]);
                $pdo->prepare("UPDATE {$table} SET bib_number=:bib WHERE id=:id AND competition_id=:competition")->execute(['bib' => $numbers[$first], 'id' => $second, 'competition' => $competitionId]);
                $pdo->prepare("UPDATE {$table} SET bib_number=:bib WHERE id=:id AND competition_id=:competition")->execute(['bib' => $numbers[$second], 'id' => $first, 'competition' => $competitionId]);
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return ucfirst($kind) . ' order updated.';
    }

    private static function renumber(PDO $pdo, string $prefix, int $competitionId, string $kind): void
    {
        if ($kind !== 'judge') return;
        $query = $pdo->prepare("SELECT id FROM {$prefix}_judges WHERE competition_id=:competition ORDER BY judge_order,id");
        $query->execute(['competition' => $competitionId]);
        $update = $pdo->prepare("UPDATE {$prefix}_judges SET judge_order=:position WHERE id=:id AND competition_id=:competition");
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $position => $id) {
            $update->execute(['position' => $position + 1, 'id' => (int) $id, 'competition' => $competitionId]);
        }
    }

    private static function assertMember(PDO $pdo, string $table, int $competitionId, int $id): void
    {
        $query = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE competition_id=:competition AND id=:id");
        $query->execute(['competition' => $competitionId, 'id' => $id]);
        if ($id < 1 || (int) $query->fetchColumn() !== 1) throw new RuntimeException('Roster member was not found in this category.');
    }

    private static function assertNoMarks(PDO $pdo, string $prefix, int $competitionId, string $column, int $id, string $label): void
    {
        $query = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}_marks WHERE competition_id=:competition AND {$column}=:id");
        $query->execute(['competition' => $competitionId, 'id' => $id]);
        if ((int) $query->fetchColumn() > 0) throw new RuntimeException("Cannot remove this {$label} after scoring has started. Use the protected scoring recovery workflow.");
    }
}
