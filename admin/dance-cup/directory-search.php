<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\DanceCupScoringService;
use App\Services\JudgeDirectoryService;

Auth::requireAdmin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$term = trim((string) ($_GET['q'] ?? ''));
$type = (string) ($_GET['type'] ?? 'competitor');
$competitionId = (int) ($_GET['competition_id'] ?? 0);
$test = (string) ($_GET['data_mode'] ?? '') === 'test';
$termLength = function_exists('mb_strlen') ? mb_strlen($term, 'UTF-8') : strlen($term);
if ($termLength < 1) { echo json_encode(['ok' => true, 'items' => []]); exit; }

$lower = static fn(string $value): string => function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
$normal = static fn(string $value): string => preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';

try {
    $pdo = Database::connection();
    if ($type === 'judge') {
        $rows = JudgeDirectoryService::search($pdo, $term, 100);
        $items = array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) ($row['judge_code'] ?? ''),
            'name' => (string) ($row['display_name'] ?: $row['full_name']),
            'meta' => trim(implode(' · ', array_filter([(string) ($row['judge_code'] ?? ''), (string) ($row['country'] ?? '')]))),
        ], $rows);
    } else {
        $category = null;
        if ($competitionId > 0) {
            $tables = DanceCupScoringService::tables($test);
            $categoryQuery = $pdo->prepare("SELECT id,category_name,entry_type,dance_style,competition_level,gender_eligibility FROM {$tables['competitions']} WHERE id=:id LIMIT 1");
            $categoryQuery->execute(['id' => $competitionId]);
            $category = $categoryQuery->fetch() ?: null;
        }

        $contains = '%' . $term . '%';
        $wdc = $pdo->prepare("SELECT w.id wdc_identity_id,w.identity_code,w.entry_type,w.display_name,w.country,w.solo_competitor_id,c.gender,GROUP_CONCAT(CONCAT(r.event_key,':',r.category_key) ORDER BY r.event_key,r.category_key SEPARATOR ' | ') registrations FROM bdc_wdc_identities w LEFT JOIN bdc_competitors c ON c.id=w.solo_competitor_id LEFT JOIN bdc_wdc_registrations r ON r.wdc_identity_id=w.id AND r.status='registered' WHERE w.status='active' AND (LOWER(w.display_name) LIKE LOWER(:contains) OR LOWER(w.identity_code) LIKE LOWER(:contains2) OR LOWER(COALESCE(w.country,'')) LIKE LOWER(:contains3) OR LOWER(COALESCE(r.category_key,'')) LIKE LOWER(:contains4) OR LOWER(COALESCE(r.event_key,'')) LIKE LOWER(:contains5)) GROUP BY w.id,w.identity_code,w.entry_type,w.display_name,w.country,w.solo_competitor_id,c.gender ORDER BY LOWER(w.display_name),w.id LIMIT 100");
        $wdc->execute(['contains'=>$contains,'contains2'=>$contains,'contains3'=>$contains,'contains4'=>$contains,'contains5'=>$contains]);
        $wdcRows = $wdc->fetchAll();
        $ranked = [];
        $seenCompetitors = [];
        foreach ($wdcRows as $row) {
            if ($category) {
                $gender=(string)($row['gender']??'');
                if ($category['gender_eligibility']==='female_only' && $gender!=='' && $gender!=='female') continue;
                if ($category['gender_eligibility']==='male_only' && $gender!=='' && $gender!=='male') continue;
            }
            $registrations=(string)($row['registrations']??'');
            $hay=$normal($registrations.' '.(string)$row['entry_type']);
            $score=0;
            if ($category) {
                $categoryName=$normal((string)$category['category_name']);
                $entry=$normal((string)$category['entry_type']);
                $style=$normal((string)$category['dance_style']);
                $level=$normal((string)$category['competition_level']);
                if ($entry!=='' && $normal((string)$row['entry_type'])===$entry) $score+=30;
                if ($categoryName!=='' && str_contains($hay,$categoryName)) $score+=80;
                if ($style!=='' && str_contains($hay,$style)) $score+=20;
                if ($level!=='' && str_contains($hay,$level)) $score+=10;
                if ($registrations!=='') $score+=8;
            }
            $metaParts=[(string)$row['identity_code'],(string)($row['country']??''),ucwords(str_replace('_',' ',(string)$row['entry_type']))];
            if ($registrations!=='') $metaParts[]='Registered: '.$registrations;
            if ($category && $score>=30) array_unshift($metaParts,'CATEGORY MATCH');
            $ranked[]=['score'=>$score,'item'=>[
                'id'=>(int)($row['solo_competitor_id']??0),
                'wdc_identity_id'=>(int)$row['wdc_identity_id'],
                'code'=>(string)$row['identity_code'],
                'name'=>(string)$row['display_name'],
                'meta'=>trim(implode(' · ',array_filter($metaParts))),
                'category_match'=>$score>=30,
            ]];
            if ((int)($row['solo_competitor_id']??0)>0) $seenCompetitors[(int)$row['solo_competitor_id']]=true;
        }
        usort($ranked, static fn(array $a,array $b):int => ($b['score']<=>$a['score']) ?: strcasecmp((string)$a['item']['name'],(string)$b['item']['name']));
        $items=array_map(static fn(array $row):array=>$row['item'],$ranked);

        // Keep active BDC profiles as a lower-priority fallback, while respecting the current category gender gate.
        $sql="SELECT id,bdc_id,exact_name,country,gender FROM bdc_competitors WHERE status<>'archived' AND (LOWER(exact_name) LIKE LOWER(:contains) OR LOWER(bdc_id) LIKE LOWER(:prefix))";
        if ($category && $category['gender_eligibility']==='female_only') $sql.=" AND gender='female'";
        if ($category && $category['gender_eligibility']==='male_only') $sql.=" AND gender='male'";
        $sql.=" ORDER BY CASE WHEN LOWER(exact_name) LIKE LOWER(:starts) THEN 0 ELSE 1 END,LOWER(exact_name),id LIMIT 100";
        $query=$pdo->prepare($sql);$query->execute(['contains'=>$contains,'prefix'=>$term.'%','starts'=>$term.'%']);
        foreach($query->fetchAll() as $row){
            if(isset($seenCompetitors[(int)$row['id']]))continue;
            $items[]=['id'=>(int)$row['id'],'wdc_identity_id'=>0,'code'=>(string)($row['bdc_id']??''),'name'=>(string)$row['exact_name'],'meta'=>trim(implode(' · ',array_filter(['BDC PROFILE',(string)($row['bdc_id']??''),(string)($row['country']??'')]))),'category_match'=>false];
        }
        $items=array_slice($items,0,100);
    }
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Dance Cup directory search failed: '.$error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Directory search is temporarily unavailable.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
