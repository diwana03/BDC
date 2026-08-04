<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class CsvImportService
{
    private const POINT_HEADERS = ['Contestant Name','Country','Dropdown','Division','J&J Novice Points','Aggregate Novice Points','J&J Intermediate Points','Aggregate Intermediate Points','Event Name','Date / Time'];
    private const EVENT_HEADERS = ['Organizer Name','Email','Event Name','Date / Time','Website / URL'];
    private const RESULT_HEADERS = ['Event Name','Date / Time','Results'];

    public function __construct(private PDO $pdo)
    {

    }

    public function inspect(string $path): array
    {
        [$headers, $rows] = $this->readCsv($path, 25);
        $type = $this->detectType($headers);
        return [
            'type' => $type,
            'headers' => $headers,
            'rows' => $rows,
            'total_rows' => $this->countRows($path),
        ];
    }

    public function import(string $path, string $originalName, int $userId): array
    {
        [$headers] = $this->readCsv($path, 0);
        $type = $this->detectType($headers);
        $batchType = match ($type) {
            'points' => 'points',
            'events' => 'events',
            'results' => 'results',
            default => 'other',
        };

        $stmt = $this->pdo->prepare("INSERT INTO bdc_import_batches (file_name, import_type, status, started_at, created_by) VALUES (:file, :type, 'processing', NOW(), :user)");
        $stmt->execute(['file' => $originalName, 'type' => $batchType, 'user' => $userId]);
        $batchId = (int)$this->pdo->lastInsertId();

        $stats = ['total' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0, 'competitors_created' => 0, 'events_created' => 0, 'documents_created' => 0];

        try {
            $this->pdo->beginTransaction();
            $handle = fopen($path, 'rb');
            if (!$handle) {
                throw new RuntimeException('Unable to open uploaded CSV.');
            }
            $csvHeaders = fgetcsv($handle);
            if (!is_array($csvHeaders)) {
                throw new RuntimeException('CSV header row is missing.');
            }
            $csvHeaders = array_map([$this, 'cleanHeader'], $csvHeaders);
            $rowNumber = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($this->isBlankRow($values)) {
                    continue;
                }
                $stats['total']++;
                $values = array_pad($values, count($csvHeaders), '');
                $row = array_combine($csvHeaders, array_slice($values, 0, count($csvHeaders)));
                if (!is_array($row)) {
                    $this->recordError($batchId, $rowNumber, $values, 'Column count does not match header row.');
                    $stats['errors']++;
                    continue;
                }
                try {
                    $result = match ($type) {
                        'points' => $this->importPointRow($row, $batchId, $userId, $rowNumber),
                        'events' => $this->importEventRow($row, $batchId),
                        'results' => $this->importResultRow($row, $batchId),
                        default => throw new RuntimeException('Unsupported CSV format.'),
                    };
                    if ($result['status'] === 'skipped') {
                        $stats['skipped']++;
                    } else {
                        $stats['imported']++;
                    }
                    foreach (['competitors_created','events_created','documents_created'] as $key) {
                        $stats[$key] += (int)($result[$key] ?? 0);
                    }
                } catch (Throwable $e) {
                    $this->recordError($batchId, $rowNumber, $row, $e->getMessage());
                    $stats['errors']++;
                }
            }
            fclose($handle);

            $status = $stats['errors'] > 0 ? 'completed_with_errors' : 'completed';
            $update = $this->pdo->prepare("UPDATE bdc_import_batches SET status=:status,total_rows=:total,imported_rows=:imported,skipped_rows=:skipped,error_rows=:errors,summary_json=:summary,completed_at=NOW() WHERE id=:id");
            $update->execute([
                'status' => $status,
                'total' => $stats['total'],
                'imported' => $stats['imported'],
                'skipped' => $stats['skipped'],
                'errors' => $stats['errors'],
                'summary' => json_encode($stats, JSON_UNESCAPED_SLASHES),
                'id' => $batchId,
            ]);
            $this->audit($userId, 'csv_import_completed', 'import_batch', $batchId, $stats);
            $this->pdo->commit();
            return ['batch_id' => $batchId, 'type' => $type, 'status' => $status] + $stats;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $update = $this->pdo->prepare("UPDATE bdc_import_batches SET status='failed', error_rows=1, summary_json=:summary, completed_at=NOW() WHERE id=:id");
            $update->execute(['summary' => json_encode(['fatal_error' => $e->getMessage()]), 'id' => $batchId]);
            throw $e;
        }
    }

    public function rollback(int $batchId, int $userId): array
    {
        $batch = $this->pdo->prepare('SELECT * FROM bdc_import_batches WHERE id=:id');
        $batch->execute(['id' => $batchId]);
        $record = $batch->fetch();
        if (!$record) {
            throw new RuntimeException('Import batch not found.');
        }
        if (!empty($record['rolled_back_at'])) {
            throw new RuntimeException('This import has already been rolled back.');
        }

        $this->pdo->beginTransaction();
        try {
            $counts = [];
            $stmt = $this->pdo->prepare('DELETE FROM bdc_result_documents WHERE import_batch_id=:id');
            $stmt->execute(['id' => $batchId]);
            $counts['documents'] = $stmt->rowCount();

            $stmt = $this->pdo->prepare('DELETE FROM bdc_point_transactions WHERE import_batch_id=:id');
            $stmt->execute(['id' => $batchId]);
            $counts['transactions'] = $stmt->rowCount();

            $stmt = $this->pdo->prepare('DELETE c FROM bdc_competitors c LEFT JOIN bdc_point_transactions p ON p.competitor_id=c.id LEFT JOIN bdc_claims cl ON cl.competitor_id=c.id WHERE c.import_batch_id=:id AND p.id IS NULL AND cl.id IS NULL AND c.user_id IS NULL');
            $stmt->execute(['id' => $batchId]);
            $counts['competitors'] = $stmt->rowCount();

            $stmt = $this->pdo->prepare('DELETE e FROM bdc_events e LEFT JOIN bdc_point_transactions p ON p.event_id=e.id LEFT JOIN bdc_result_documents d ON d.event_id=e.id WHERE e.import_batch_id=:id AND p.id IS NULL AND d.id IS NULL');
            $stmt->execute(['id' => $batchId]);
            $counts['events'] = $stmt->rowCount();

            $stmt = $this->pdo->prepare("UPDATE bdc_import_batches SET status='failed', rolled_back_at=NOW(), summary_json=:summary WHERE id=:id");
            $stmt->execute(['summary' => json_encode(['rolled_back' => $counts]), 'id' => $batchId]);
            $this->audit($userId, 'csv_import_rolled_back', 'import_batch', $batchId, $counts);
            $this->pdo->commit();
            return $counts;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function importPointRow(array $row, int $batchId, int $userId, int $rowNumber): array
    {
        $name = trim((string)($row['Contestant Name'] ?? ''));
        $eventName = trim((string)($row['Event Name'] ?? ''));
        if ($name === '' || $eventName === '') {
            throw new RuntimeException('Contestant Name and Event Name are required.');
        }
        $date = $this->parseDate((string)($row['Date / Time'] ?? ''));
        $division = $this->mapDivision((string)($row['Division'] ?? ''));
        $role = $this->mapRole((string)($row['Dropdown'] ?? ''));
        $points = $division === 'intermediate'
            ? $this->number($row['J&J Intermediate Points'] ?? null)
            : $this->number($row['J&J Novice Points'] ?? null);
        if ($points === null) {
            $alternate = $division === 'intermediate'
                ? $this->number($row['J&J Novice Points'] ?? null)
                : $this->number($row['J&J Intermediate Points'] ?? null);
            $points = $alternate;
        }
        if ($points === null) {
            return ['status' => 'skipped'];
        }

        [$competitorId, $createdCompetitor] = $this->findOrCreateCompetitor($name, trim((string)($row['Country'] ?? '')), $role, $batchId);
        [$eventId, $createdEvent] = $this->findOrCreateEvent($eventName, $date, $batchId);
        $hash = hash('sha256', implode('|', ['points', $name, $eventName, $date ?? '', $division, $role, (string)$points]));

        $stmt = $this->pdo->prepare("INSERT IGNORE INTO bdc_point_transactions (competitor_id,event_id,division,dance_role,points,source_type,import_batch_id,source_row_hash,created_by,notes) VALUES (:competitor,:event,:division,:role,:points,'csv_import',:batch,:hash,:user,:notes)");
        $stmt->execute([
            'competitor' => $competitorId,
            'event' => $eventId,
            'division' => $division,
            'role' => $role,
            'points' => $points,
            'batch' => $batchId,
            'hash' => $hash,
            'user' => $userId,
            'notes' => 'Imported from WPForms row ' . $rowNumber,
        ]);
        return [
            'status' => $stmt->rowCount() === 0 ? 'skipped' : 'imported',
            'competitors_created' => $createdCompetitor ? 1 : 0,
            'events_created' => $createdEvent ? 1 : 0,
        ];
    }

    private function importEventRow(array $row, int $batchId): array
    {
        $name = trim((string)($row['Event Name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Event Name is required.');
        }
        $date = $this->parseDate((string)($row['Date / Time'] ?? ''));
        [$eventId, $created] = $this->findOrCreateEvent($name, $date, $batchId);
        $stmt = $this->pdo->prepare('UPDATE bdc_events SET organiser_name=COALESCE(NULLIF(:organiser,\'\'),organiser_name), organiser_email=COALESCE(NULLIF(:email,\'\'),organiser_email), website_url=COALESCE(NULLIF(:website,\'\'),website_url) WHERE id=:id');
        $stmt->execute([
            'organiser' => trim((string)($row['Organizer Name'] ?? '')),
            'email' => trim((string)($row['Email'] ?? '')),
            'website' => trim((string)($row['Website / URL'] ?? '')),
            'id' => $eventId,
        ]);
        return ['status' => $created ? 'imported' : 'skipped', 'events_created' => $created ? 1 : 0];
    }

    private function importResultRow(array $row, int $batchId): array
    {
        $name = trim((string)($row['Event Name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Event Name is required.');
        }
        $date = $this->parseDate((string)($row['Date / Time'] ?? ''));
        [$eventId, $createdEvent] = $this->findOrCreateEvent($name, $date, $batchId);
        $raw = trim((string)($row['Results'] ?? ''));
        if ($raw === '') {
            return ['status' => 'skipped', 'events_created' => $createdEvent ? 1 : 0];
        }
        preg_match_all('~https?://[^\s]+~i', $raw, $matches);
        $createdDocs = 0;
        foreach (array_unique($matches[0] ?? []) as $url) {
            $url = trim($url);
            $type = $this->documentType($url);
            $title = strtoupper($type) . ' – ' . $name;
            $hash = hash('sha256', 'document|' . $eventId . '|' . $url);
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO bdc_result_documents (event_id,title,document_category,file_type,url,status,source,import_batch_id,source_row_hash) VALUES (:event,:title,:type,:file_type,:url,'published','historical_import',:batch,:hash)");
            $stmt->execute(['event' => $eventId, 'title' => $title, 'type' => $type, 'file_type' => $this->fileType($url), 'url' => $url, 'batch' => $batchId, 'hash' => $hash]);
            $createdDocs += $stmt->rowCount();
        }
        return [
            'status' => $createdDocs > 0 ? 'imported' : 'skipped',
            'events_created' => $createdEvent ? 1 : 0,
            'documents_created' => $createdDocs,
        ];
    }

    private function findOrCreateCompetitor(string $name, string $country, string $role, int $batchId): array
    {
        $stmt = $this->pdo->prepare('SELECT id,country,dance_role FROM bdc_competitors WHERE exact_name=:name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $existing = $stmt->fetch();
        if ($existing) {
            $newRole = $existing['dance_role'] === 'unknown' ? $role : ($existing['dance_role'] !== $role && $role !== 'unknown' ? 'both' : $existing['dance_role']);
            $update = $this->pdo->prepare('UPDATE bdc_competitors SET country=COALESCE(NULLIF(country,\'\'),NULLIF(:country,\'\')), dance_role=:role WHERE id=:id');
            $update->execute(['country' => $country, 'role' => $newRole, 'id' => $existing['id']]);
            return [(int)$existing['id'], false];
        }
        $stmt = $this->pdo->prepare("INSERT INTO bdc_competitors (exact_name,normalised_name,country,dance_role,status,is_historical,import_batch_id) VALUES (:exact,:normalised,NULLIF(:country,''),:role,'active',1,:batch)");
        $stmt->execute(['exact' => $name, 'normalised' => $this->normalise($name), 'country' => $country, 'role' => $role, 'batch' => $batchId]);
        return [(int)$this->pdo->lastInsertId(), true];
    }

    private function findOrCreateEvent(string $name, ?string $date, int $batchId): array
    {
        $normalised = $this->normalise($name);
        $stmt = $this->pdo->prepare('SELECT id FROM bdc_events WHERE normalised_name=:normalised AND ((event_date=:event_date) OR (event_date IS NULL AND :event_date2 IS NULL)) LIMIT 1');
        $stmt->execute(['normalised' => $normalised, 'event_date' => $date, 'event_date2' => $date]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return [(int)$id, false];
        }
        $slug = $this->slug($name) . '-' . substr(hash('sha256', $normalised . '|' . ($date ?? '')), 0, 8);
        $stmt = $this->pdo->prepare("INSERT INTO bdc_events (name,normalised_name,slug,event_date,status,import_batch_id) VALUES (:name,:normalised,:slug,:date,'completed',:batch)");
        $stmt->execute(['name' => $name, 'normalised' => $normalised, 'slug' => $slug, 'date' => $date, 'batch' => $batchId]);
        return [(int)$this->pdo->lastInsertId(), true];
    }

    private function readCsv(string $path, int $limit): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException('Unable to read CSV file.');
        }
        $headers = fgetcsv($handle);
        if (!is_array($headers)) {
            fclose($handle);
            throw new RuntimeException('CSV header row is missing.');
        }
        $headers = array_map([$this, 'cleanHeader'], $headers);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($values)) {
                continue;
            }
            $values = array_pad($values, count($headers), '');
            $row = array_combine($headers, array_slice($values, 0, count($headers)));
            if (is_array($row)) {
                $rows[] = $row;
            }
            if ($limit > 0 && count($rows) >= $limit) {
                break;
            }
        }
        fclose($handle);
        return [$headers, $rows];
    }

    private function countRows(string $path): int
    {
        $handle = fopen($path, 'rb');
        if (!$handle) return 0;
        $count = -1;
        while (fgetcsv($handle) !== false) $count++;
        fclose($handle);
        return max(0, $count);
    }

    private function detectType(array $headers): string
    {
        foreach (['points' => self::POINT_HEADERS, 'events' => self::EVENT_HEADERS, 'results' => self::RESULT_HEADERS] as $type => $required) {
            if (count(array_diff($required, $headers)) === 0) return $type;
        }
        throw new RuntimeException('Unknown CSV format. Upload an original BDC Point Entry, Event Registration, or Result Entry export.');
    }

    private function cleanHeader(string $header): string
    {
        return trim(preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header);
    }

    private function isBlankRow(array $values): bool
    {
        return count(array_filter($values, fn($v) => trim((string)$v) !== '')) === 0;
    }

    private function normalise(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii) ?? 'event', '-'));
        return substr($slug !== '' ? $slug : 'event', 0, 150);
    }

    private function parseDate(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if ($value === '') {
            return null;
        }

        // Some historical WPForms rows contain the same date twice,
        // for example "07/16/2022 07/16/2022". Keep one copy.
        if (preg_match('/^(\d{1,2}\/\d{1,2}\/\d{4})\s+\1$/', $value, $matches) === 1) {
            $value = $matches[1];
        }

        // WPForms exports use US month/day/year dates. ISO formats are
        // also accepted for manually prepared or future exports.
        $formats = [
            '!m/d/Y g:i A',
            '!m/d/Y h:i A',
            '!m/d/Y H:i:s',
            '!m/d/Y H:i',
            '!m/d/Y',
            '!n/j/Y g:i A',
            '!n/j/Y h:i A',
            '!n/j/Y H:i:s',
            '!n/j/Y H:i',
            '!n/j/Y',
            '!Y-m-d H:i:s',
            '!Y-m-d H:i',
            '!Y-m-d',
        ];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            $valid = $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
            if ($valid) {
                return $date->format('Y-m-d');
            }
        }

        throw new RuntimeException('Invalid event date: ' . $value);
    }

    private function mapDivision(string $value): string
    {
        $value = strtolower(trim($value));
        return match (true) {
            str_contains($value, 'intermediate') => 'intermediate',
            str_contains($value, 'advanced') => 'advanced',
            str_contains($value, 'all') && str_contains($value, 'star') => 'all_star',
            str_contains($value, 'novice') => 'novice',
            default => 'unknown',
        };
    }

    private function mapRole(string $value): string
    {
        $value = strtolower(trim($value));
        return match (true) {
            str_contains($value, 'lead') => 'leader',
            str_contains($value, 'follow') => 'follower',
            str_contains($value, 'both') => 'both',
            default => 'unknown',
        };
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || trim((string)$value) === '') return null;
        return is_numeric($value) ? (float)$value : null;
    }

    private function fileType(string $url): string
    {
        $path = strtolower((string)(parse_url($url, PHP_URL_PATH) ?? ''));
        if (str_ends_with($path, '.pdf')) return 'pdf';
        if (str_ends_with($path, '.csv')) return 'csv';
        if (str_contains(strtolower($url), 'worldresult')) return 'world_result';
        return 'external';
    }

    private function documentType(string $url): string
    {
        $upper = strtoupper($url);
        return match (true) {
            str_contains($upper, 'HEAT') || str_contains($upper, 'PRELIM') => 'heats',
            str_contains($upper, 'FINAL') => 'finals',
            str_contains($upper, 'POINT') => 'points',
            default => 'other',
        };
    }

    private function recordError(int $batchId, int $rowNumber, mixed $raw, string $message): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO bdc_import_errors (batch_id,row_number,raw_data_json,error_message) VALUES (:batch,:row,:raw,:message)');
        $stmt->execute(['batch' => $batchId, 'row' => $rowNumber, 'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'message' => $message]);
    }

    private function audit(int $userId, string $action, string $entityType, int $entityId, array $details): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO bdc_audit_logs (user_id,action,entity_type,entity_id,details_json,ip_address) VALUES (:user,:action,:type,:id,:details,:ip)');
        $stmt->execute(['user' => $userId, 'action' => $action, 'type' => $entityType, 'id' => $entityId, 'details' => json_encode($details), 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}
