<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;

final class PublicationArchiveLookup
{
    public static function recover(PDO $pdo, int $roundId, int $publicationId, string $documentTable, array $documents): array
    {
        $prefix = match ($documentTable) {
            'bdc_result_documents' => 'bdc_',
            'bdc_test_result_documents' => 'bdc_test_',
            default => throw new RuntimeException('Invalid result document table.'),
        };
        $missing = array_diff(['heats', 'finals', 'points'], array_keys($documents));
        if (!$missing) return $documents;

        // The legacy Salsa special publisher saved exact document IDs in its
        // approval audit, but did not populate publication_documents.
        $audit = $pdo->prepare("SELECT a.details_json,p.event_id
            FROM {$prefix}scoring_audit a
            JOIN {$prefix}scoring_publications p ON p.final_round_id=a.round_id
            WHERE a.round_id=:round_id AND p.id=:publication_id
              AND p.status='published' AND a.action='salsa_special_approved'");
        $audit->execute(['round_id'=>$roundId, 'publication_id'=>$publicationId]);
        $ids = []; $eventId = 0;
        foreach ($audit->fetchAll() as $row) {
            $details = json_decode((string)$row['details_json'], true);
            if (!is_array($details) || (int)($details['publication_id'] ?? 0) !== $publicationId) continue;
            foreach ($missing as $category) {
                $id = (int)($details['documents'][$category] ?? 0);
                if ($id < 1) continue;
                if (isset($ids[$category]) && $ids[$category] !== $id) {
                    throw new RuntimeException('Conflicting publication archive audit records. Nothing was changed.');
                }
                $ids[$category] = $id;
            }
            $eventId = (int)$row['event_id'];
        }
        foreach ($missing as $category) {
            if (empty($ids[$category]) || $eventId < 1) {
                throw new RuntimeException('The published '.ucfirst($category).' document mapping is missing from both publication links and the approval audit. Nothing was changed.');
            }
            $query = $pdo->prepare("SELECT id,document_category,storage_path,url FROM {$documentTable}
                WHERE id=:document_id AND event_id=:event_id AND document_category=:category
                  AND status='published' AND source='scoring_engine'");
            $query->execute(['document_id'=>$ids[$category], 'event_id'=>$eventId, 'category'=>$category]);
            $document = $query->fetch();
            if (!$document) throw new RuntimeException('The audited '.ucfirst($category).' document is unavailable or does not belong to this event. Nothing was changed.');
            $documents[$category] = $document;
        }
        return $documents;
    }
}
