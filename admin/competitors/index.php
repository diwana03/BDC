<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\SchemaUpdater;
use App\Services\DivisionProgressionService;
use App\Services\SpecialCategoryService;
use App\Services\CountryFlagService;

Auth::requirePermission('competitors.view');

$pdo = Database::connection();

$dashboard = in_array((string)($_GET['dashboard'] ?? ''), ['bachata','salsa'], true) ? (string)$_GET['dashboard'] : '';
$dashboardTitle = $dashboard === 'bachata' ? 'Bachata J&J Competitor' : ($dashboard === 'salsa' ? 'Salsa J&J Competitor' : 'Competitor Management');
$dashboardCouncil = $dashboard === 'salsa' ? 'sdc' : 'bdc';
$dashboardIdLabel = strtoupper($dashboardCouncil).' ID';
$dashboardAccent = $dashboard === 'salsa' ? 'salsa' : 'bachata';
$dashboardEyebrow = $dashboard === 'salsa' ? 'SALSA DANCE COUNCIL' : 'BACHATA DANCE COUNCIL';
$dashboardDescription = $dashboard === 'salsa'
    ? 'Dedicated SDC identities, Salsa roles and registered competition categories.'
    : 'Dedicated BDC identities, Bachata progression, points and competition categories.';

$q        = trim((string)($_GET['q'] ?? ''));
$filter   = (string)($_GET['filter'] ?? '');
$country  = CountryFlagService::canonicalName((string)($_GET['country'] ?? ''));
$role     = (string)($_GET['role'] ?? '');
$division = (string)($_GET['division'] ?? '');
$status   = (string)($_GET['status'] ?? '');
$danceStyle = $dashboard !== '' ? $dashboard : (in_array((string)($_GET['dance_style'] ?? ''), ['bachata', 'salsa'], true) ? (string)$_GET['dance_style'] : '');
$sort     = (string)($_GET['sort'] ?? 'name');
$order    = strtolower((string)($_GET['order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$perPage  = (int)($_GET['per_page'] ?? 50);
$page     = max(1, (int)($_GET['page'] ?? 1));

$allowedPerPage = [25, 50, 100, 200, 500];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 50;
}

$allowedRoles = ['leader', 'follower', 'both', 'unknown'];
$allowedDivisions = ['novice', 'intermediate', 'advanced', 'all_star', 'professional', 'bachata_rising', 'bachata_open', 'bachata_invitational', 'salsa_rising', 'salsa_open', 'salsa_invitational', 'unknown'];
$allowedStatuses = ['active', 'pending', 'archived'];

$countryRows = $pdo->query("SELECT DISTINCT country FROM bdc_competitors WHERE country IS NOT NULL AND TRIM(country)<>'' ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);
$countries = array_values(array_unique(array_filter(array_map(static fn($value):string=>CountryFlagService::canonicalName((string)$value),$countryRows))));
sort($countries, SORT_NATURAL | SORT_FLAG_CASE);

$where = ['1=1'];
$params = [];

if ($q !== '') {
    // Use unique placeholders because native PDO MySQL prepared statements
    // cannot reliably reuse the same named parameter more than once.
    $where[] = '(LOWER(c.exact_name) LIKE LOWER(:q_name) OR LOWER(c.normalised_name) LIKE LOWER(:q_normalised) OR LOWER(ri.identity_code) LIKE LOWER(:q_identity) OR LOWER(c.instagram) LIKE LOWER(:q_instagram) OR LOWER(c.email) LIKE LOWER(:q_email))';
    $searchValue = '%' . $q . '%';
    $params['q_name'] = $searchValue;
    $params['q_normalised'] = $searchValue;
    $params['q_identity'] = $searchValue;
    $params['q_instagram'] = $searchValue;
    $params['q_email'] = $searchValue;
}

if ($filter === 'missing_photo') {
    $where[] = "(c.photo_url IS NULL OR TRIM(c.photo_url) = '')";
} elseif ($filter === 'missing_country') {
    $where[] = "(c.country IS NULL OR TRIM(c.country) = '')";
} elseif ($filter === 'incomplete_profile') {
    $where[] = "EXISTS (SELECT 1 FROM bdc_competitor_discipline_profiles ip WHERE ip.competitor_id=c.id AND (:profile_style='' OR ip.dance_style=:profile_style2) AND (ip.dance_role='unknown' OR ip.current_division='unknown'))";
    $params['profile_style'] = $danceStyle; $params['profile_style2'] = $danceStyle;
} elseif ($filter === 'special_category') {
    $where[] = "EXISTS (SELECT 1 FROM bdc_competitor_special_categories spx WHERE spx.competitor_id=c.id AND (:special_style='' OR spx.dance_style=:special_style2))";
    $params['special_style'] = $danceStyle; $params['special_style2'] = $danceStyle;
}

if ($country !== '') {
    $countryVariants=[];
    foreach($countryRows as $rawCountry){
        if(CountryFlagService::canonicalName((string)$rawCountry)===$country)$countryVariants[]=(string)$rawCountry;
    }
    $countryVariants=array_values(array_unique(array_merge([$country],$countryVariants)));
    $countryPlaceholders=[];
    foreach($countryVariants as $index=>$rawCountry){
        $key='country_variant_'.$index;
        $countryPlaceholders[]=':'.$key;
        $params[$key]=$rawCountry;
    }
    $where[]='c.country IN ('.implode(',',$countryPlaceholders).')';
}
if ($danceStyle !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM bdc_competitor_discipline_profiles ds WHERE ds.competitor_id=c.id AND ds.dance_style=:dance_style)';
    $params['dance_style'] = $danceStyle;
}
if (in_array($role, $allowedRoles, true)) {
    $where[] = 'EXISTS (SELECT 1 FROM bdc_competitor_discipline_profiles rp WHERE rp.competitor_id=c.id AND rp.dance_role=:role AND (:role_style="" OR rp.dance_style=:role_style2))';
    $params['role'] = $role;
    $params['role_style'] = $danceStyle; $params['role_style2'] = $danceStyle;
}
if (in_array($division, $allowedDivisions, true)) {
    $where[] = '((EXISTS (SELECT 1 FROM bdc_competitor_discipline_profiles dp WHERE dp.competitor_id=c.id AND dp.current_division=:division AND (:division_style="" OR dp.dance_style=:division_style2))) OR (EXISTS (SELECT 1 FROM bdc_competitor_special_categories dsc WHERE dsc.competitor_id=c.id AND dsc.category=:special_division AND (:special_division_style="" OR dsc.dance_style=:special_division_style2))))';
    $params['division'] = $division;
    $params['division_style'] = $danceStyle; $params['division_style2'] = $danceStyle;
    $params['special_division'] = $division;
    $params['special_division_style'] = $danceStyle; $params['special_division_style2'] = $danceStyle;
}
if (in_array($status, $allowedStatuses, true)) {
    $where[] = 'c.status = :status';
    $params['status'] = $status;
}

$sortMap = [
    'name'       => 'c.exact_name',
    'bdc_id'     => 'ri.identity_code',
    'country'    => 'c.country',
    'role'       => 'COALESCE(bp.dance_role,sp.dance_role)',
    'division'   => 'COALESCE(bp.current_division,sp.current_division)',
    'total'      => $dashboard!==''?$dashboard.'_points':'total_points',
    'status'     => 'c.status',
    'created'    => 'c.created_at',
    'updated'    => 'c.updated_at',
];
$orderBy = $sortMap[$sort] ?? $sortMap['name'];
$sort = array_key_exists($sort, $sortMap) ? $sort : 'name';
$orderSql = strtoupper($order);
$whereSql = implode(' AND ', $where);
$identitySource=$dashboardCouncil==='sdc'
    ?"(SELECT competitor_id,sdc_id identity_code FROM bdc_sdc_competitors WHERE status='active')"
    :"(SELECT id competitor_id,bdc_id identity_code FROM bdc_competitors WHERE bdc_id LIKE 'BDC-%')";
$salsaProfileSource="(SELECT competitor_id,dance_role,current_division FROM bdc_sdc_competitors WHERE status='active')";
$categorySource="(SELECT competitor_id,GROUP_CONCAT(CASE WHEN dance_style='bachata' THEN category END ORDER BY category SEPARATOR ',') bachata_special_categories,NULL salsa_special_categories FROM bdc_competitor_special_categories WHERE dance_style='bachata' GROUP BY competitor_id UNION ALL SELECT s.competitor_id,NULL,GROUP_CONCAT(c.category ORDER BY c.category SEPARATOR ',') FROM bdc_sdc_competitors s LEFT JOIN bdc_sdc_competitor_categories c ON c.sdc_competitor_id=s.id WHERE s.status='active' GROUP BY s.competitor_id)";

$baseListSql = "SELECT c.*,bp.dance_role bachata_role,bp.current_division bachata_division,sc.bachata_special_categories,
        ri.identity_code dashboard_identity_code,
        sp.dance_role salsa_role,sp.current_division salsa_division,sc.salsa_special_categories,
        COALESCE(pp.bachata_points,0) bachata_points,COALESCE(pp.bachata_novice_points,0) bachata_novice_points,COALESCE(pp.bachata_intermediate_points,0) bachata_intermediate_points,COALESCE(pp.bachata_advanced_points,0) bachata_advanced_points,
        COALESCE(pp.salsa_points,0) salsa_points,COALESCE(pp.salsa_novice_points,0) salsa_novice_points,COALESCE(pp.salsa_intermediate_points,0) salsa_intermediate_points,COALESCE(pp.salsa_advanced_points,0) salsa_advanced_points,
        COALESCE(pp.bachata_points,0)+COALESCE(pp.salsa_points,0) AS total_points
    FROM bdc_competitors c
    JOIN {$identitySource} ri ON ri.competitor_id=c.id
    LEFT JOIN bdc_competitor_discipline_profiles bp ON bp.competitor_id=c.id AND bp.dance_style='bachata'
    LEFT JOIN {$salsaProfileSource} sp ON sp.competitor_id=c.id
    LEFT JOIN (SELECT competitor_id,MAX(bachata_special_categories) bachata_special_categories,MAX(salsa_special_categories) salsa_special_categories FROM {$categorySource} grouped_categories GROUP BY competitor_id) sc ON sc.competitor_id=c.id
    LEFT JOIN (SELECT competitor_id,
        SUM(CASE WHEN dance_style='bachata' THEN points ELSE 0 END) bachata_points,
        SUM(CASE WHEN dance_style='bachata' AND division='novice' THEN points ELSE 0 END) bachata_novice_points,
        SUM(CASE WHEN dance_style='bachata' AND division='intermediate' THEN points ELSE 0 END) bachata_intermediate_points,
        SUM(CASE WHEN dance_style='bachata' AND division='advanced' THEN points ELSE 0 END) bachata_advanced_points,
        SUM(CASE WHEN dance_style='salsa' THEN points ELSE 0 END) salsa_points,
        SUM(CASE WHEN dance_style='salsa' AND division='novice' THEN points ELSE 0 END) salsa_novice_points,
        SUM(CASE WHEN dance_style='salsa' AND division='intermediate' THEN points ELSE 0 END) salsa_intermediate_points,
        SUM(CASE WHEN dance_style='salsa' AND division='advanced' THEN points ELSE 0 END) salsa_advanced_points
        FROM bdc_point_transactions GROUP BY competitor_id) pp ON pp.competitor_id=c.id
    WHERE {$whereSql}";

if ((string)($_GET['export'] ?? '') === 'csv') {
    Auth::requireSuperAdmin();
    $exportStmt = $pdo->prepare($baseListSql . " ORDER BY {$orderBy} {$orderSql}, c.id ASC");
    foreach ($params as $key => $value) {
        $exportStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $exportStmt->execute();
    $exportRows = $exportStmt->fetchAll();

    Auth::audit((int)(Auth::user()['id'] ?? 0), 'competitors_exported', [
        'format' => 'csv',
        'row_count' => count($exportRows),
        'filters' => array_intersect_key($_GET, array_flip(['q','filter','country','dance_style','role','division','status','sort','order'])),
    ], 'competitor_export');

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.($dashboardCouncil==='sdc'?'sdc':'bdc').'-competitors-' . date('Y-m-d-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        http_response_code(500);
        exit('Unable to create competitor export.');
    }
    fwrite($out, "\xEF\xBB\xBF");
    $exportStyles=$dashboard!==''?[$dashboard]:['bachata','salsa'];
    $exportHeader=[$dashboardIdLabel,'Exact Name','Email','Phone','Instagram','Country'];
    foreach($exportStyles as $style){$label=ucfirst($style);array_push($exportHeader,$label.' Role',$label.' Division',$label.' Special Category',$label.' Points');}
    array_push($exportHeader,'Total Points','Status','Created At','Updated At');fputcsv($out,$exportHeader);
    $safeCsv = static function ($value) {
        $text = (string)($value ?? '');
        return preg_match('/^[=+\-@]/u', $text) === 1 ? "'" . $text : $text;
    };
    foreach ($exportRows as $exportRow) {
        $exportLine=[
            $safeCsv($exportRow['dashboard_identity_code'] ?? ''),
            $safeCsv($exportRow['exact_name'] ?? ''),
            $safeCsv($exportRow['email'] ?? ''),
            $safeCsv($exportRow['phone'] ?? ''),
            $safeCsv($exportRow['instagram'] ?? ''),
            $safeCsv($exportRow['country'] ?? ''),
        ];
        foreach($exportStyles as $style){array_push($exportLine,$safeCsv($exportRow[$style.'_role']??''),$safeCsv($exportRow[$style.'_division']??''),$safeCsv($exportRow[$style.'_special_categories']??''),(float)($exportRow[$style.'_points']??0));}
        array_push($exportLine,$dashboard!==''?(float)($exportRow[$dashboard.'_points']??0):(float)($exportRow['total_points']??0),
            $safeCsv($exportRow['status'] ?? ''),
            $safeCsv($exportRow['created_at'] ?? ''),
            $safeCsv($exportRow['updated_at'] ?? ''));fputcsv($out,$exportLine);
    }
    fclose($out);
    exit;
}

$countSql = "SELECT COUNT(*) FROM bdc_competitors c JOIN {$identitySource} ri ON ri.competitor_id=c.id WHERE {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = $baseListSql . "
    ORDER BY {$orderBy} {$orderSql}, c.id ASC
    LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$countScope=$pdo->quote($dashboardCouncil);$danceScope=$pdo->quote($danceStyle?:'bachata');
if($dashboardCouncil==='sdc'){$counts=[
    'all_participants'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_sdc_competitors WHERE status='active'")->fetchColumn(),
    'missing_photo'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_sdc_competitors s JOIN bdc_competitors c ON c.id=s.competitor_id WHERE s.status='active' AND (c.photo_url IS NULL OR TRIM(c.photo_url)='')")->fetchColumn(),
    'missing_country'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_sdc_competitors s JOIN bdc_competitors c ON c.id=s.competitor_id WHERE s.status='active' AND (c.country IS NULL OR TRIM(c.country)='')")->fetchColumn(),
    'incomplete_profile'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_sdc_competitors WHERE status='active' AND dance_role='unknown'")->fetchColumn(),
    'special_category'=>(int)$pdo->query("SELECT COUNT(DISTINCT sdc_competitor_id) FROM bdc_sdc_competitor_categories")->fetchColumn(),
];}else{$counts=[
    'all_participants'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_competitors WHERE status='active' AND bdc_id LIKE 'BDC-%'")->fetchColumn(),
    'missing_photo'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_competitors c WHERE c.status='active' AND c.bdc_id LIKE 'BDC-%' AND (c.photo_url IS NULL OR TRIM(c.photo_url)='')")->fetchColumn(),
    'missing_country'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_competitors c WHERE c.status='active' AND c.bdc_id LIKE 'BDC-%' AND (c.country IS NULL OR TRIM(c.country)='')")->fetchColumn(),
    'incomplete_profile'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_competitor_discipline_profiles p JOIN bdc_competitors c ON c.id=p.competitor_id AND c.status='active' AND c.bdc_id LIKE 'BDC-%' WHERE p.dance_style='bachata' AND (p.dance_role='unknown' OR p.current_division='unknown')")->fetchColumn(),
    'special_category'=>(int)$pdo->query("SELECT COUNT(DISTINCT s.competitor_id) FROM bdc_competitor_special_categories s JOIN bdc_competitors c ON c.id=s.competitor_id AND c.status='active' AND c.bdc_id LIKE 'BDC-%' WHERE s.dance_style='bachata'")->fetchColumn(),
];}
$hasListFilters=$q!==''||$filter!==''||$country!==''||($dashboard===''&&$danceStyle!=='')||$role!==''||$division!==''||$status!=='';

function queryUrl(array $changes = []): string
{
    $query = array_merge($_GET, $changes);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }
    return '?' . http_build_query($query);
}

function sortUrl(string $column, string $currentSort, string $currentOrder): string
{
    $nextOrder = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
    return queryUrl(['sort' => $column, 'order' => $nextOrder, 'page' => 1]);
}

function sortMark(string $column, string $currentSort, string $currentOrder): string
{
    if ($column !== $currentSort) {
        return '';
    }
    return $currentOrder === 'asc' ? ' ▲' : ' ▼';
}
$currentListReturn = '?' . http_build_query($_GET);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=e($dashboardTitle)?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('public/assets/css/app.css')) ?>" rel="stylesheet">
    <style>
        :root{--portal-navy:#0d1b36;--portal-wine:#5a1734;--portal-gold:#d9b567;--portal-ink:#152039;--portal-muted:#667085;--portal-line:#dce3ed;--portal-accent:<?=$dashboardAccent==='salsa'?'#0d7b83':'#9b284d'?>;--portal-accent-soft:<?=$dashboardAccent==='salsa'?'#e4f7f6':'#f9eaf0'?>}
        body.competitor-premium{background:radial-gradient(circle at 85% 0,<?=$dashboardAccent==='salsa'?'#dff7f5':'#f7e1ea'?> 0,transparent 28rem),linear-gradient(145deg,#edf3fa,#f8f9fc 55%,#f4eef3);color:var(--portal-ink);min-height:100vh}
        .premium-shell{max-width:1800px;margin:auto}.premium-hero{position:relative;overflow:hidden;border-radius:24px;padding:clamp(1.35rem,3vw,2.3rem);background:linear-gradient(120deg,var(--portal-navy),#172e58 62%,<?=$dashboardAccent==='salsa'?'#075b65':'var(--portal-wine)'?>);color:#fff;box-shadow:0 20px 45px rgba(13,27,54,.2)}
        .premium-hero:after{content:"";position:absolute;width:300px;height:300px;border:1px solid rgba(255,255,255,.16);border-radius:50%;right:-90px;top:-150px;box-shadow:0 0 0 45px rgba(255,255,255,.04),0 0 0 90px rgba(255,255,255,.025)}
        .premium-eyebrow{font-size:.72rem;font-weight:800;letter-spacing:.16em;color:#f4d99d}.premium-hero h1{font-weight:800;letter-spacing:-.035em}.premium-hero p{color:#dce5f4;max-width:720px}.premium-actions{position:relative;z-index:1}.premium-actions .btn{border-radius:999px;font-weight:700;padding:.58rem .9rem}.premium-actions .btn-light{color:var(--portal-navy)}
        .summary-card{height:100%;border:1px solid rgba(255,255,255,.9)!important;border-radius:18px!important;background:rgba(255,255,255,.92);box-shadow:0 12px 30px rgba(31,49,78,.08)!important;transition:.18s ease}.summary-card:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(31,49,78,.13)!important}.summary-card.active{outline:3px solid var(--portal-accent);outline-offset:1px}.summary-value{font-size:2rem;font-weight:850;color:var(--portal-navy)}
        .premium-panel{border:1px solid rgba(255,255,255,.95)!important;border-radius:20px!important;background:rgba(255,255,255,.94);box-shadow:0 12px 32px rgba(31,49,78,.08)!important}.premium-panel .form-control,.premium-panel .form-select{border-color:#ccd6e4;border-radius:11px;min-height:43px}.premium-panel .form-control:focus,.premium-panel .form-select:focus{border-color:var(--portal-accent);box-shadow:0 0 0 .2rem rgba(13,123,131,.13)}
        .premium-table-wrap{border-radius:20px;overflow:hidden}.premium-table thead th{background:var(--portal-navy);color:#fff;border:0;padding:.9rem .75rem;font-size:.75rem;letter-spacing:.045em;text-transform:uppercase}.premium-table thead .sortable{color:#fff}.premium-table tbody td{padding:.8rem .75rem;border-color:var(--portal-line)}.premium-table tbody tr:hover>*{background:var(--portal-accent-soft)}.identity-code{font-weight:800;color:var(--portal-accent);white-space:nowrap}.competitor-name{font-size:1rem;color:var(--portal-ink)}
        .sortable { color: inherit; text-decoration: none; white-space: nowrap; }
        .sortable:hover { text-decoration: underline; }
        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:1rem; }
        .competitor-photo { width:58px; height:68px; object-fit:cover; border-radius:13px;box-shadow:0 4px 12px rgba(21,32,57,.16);background:#e8edf4 }
        .table thead th { vertical-align: middle; }
        .profile-box { min-width:190px; padding:.65rem .75rem; border:1px solid var(--portal-line); border-radius:12px; background:#fff; }
        .profile-box.special { border-color:var(--portal-accent); background:var(--portal-accent-soft); }.profile-box .badge{border-radius:999px}.scope-note{border-left:4px solid var(--portal-accent);background:#fff;border-radius:12px;padding:.75rem 1rem;color:var(--portal-muted)}
        @media(max-width:767px){.premium-hero{border-radius:18px}.premium-actions{width:100%}.premium-actions .btn{flex:1 1 45%}.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.summary-value{font-size:1.6rem}.premium-table-wrap{border-radius:14px}.competitor-photo{width:52px;height:62px}}
    </style>
    <link rel="stylesheet" href="<?= e(url('public/assets/css/admin-mobile-v428.css?v=428')) ?>">
    <script defer src="<?= e(url('public/assets/js/admin-mobile-v428.js?v=428')) ?>"></script>
</head>
<body class="bdc-mobile-admin competitor-premium">
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= e(url('admin/')) ?>">BDC Admin</a>
        <div><a class="btn btn-outline-light btn-sm" href="<?= e(url('leaderboard/')) ?>">Leaderboard</a></div>
    </div>
</nav>

<div class="container-fluid py-4 premium-shell">
    <?php if($dashboard!==''):?><section class="premium-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-4 position-relative" style="z-index:1"><div><div class="premium-eyebrow mb-2"><?=e($dashboardEyebrow)?></div><h1 class="display-6 mb-2"><?=e($dashboardTitle)?></h1><p class="mb-0"><?=e($dashboardDescription)?></p></div>
        <div class="premium-actions d-flex flex-wrap gap-2"><a class="btn btn-light" href="#competitor-directory">Browse directory</a><?php if(Auth::can('competitors.edit')):?><a class="btn btn-outline-light" href="edit.php?dance=<?=e($dashboard)?>&amp;dashboard=<?=e($dashboard)?>&amp;return=<?=e(rawurlencode($currentListReturn))?>">Add <?=e(strtoupper($dashboardCouncil))?> competitor</a><?php endif;?></div></div>
    </section><?php endif;?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 <?=$dashboard!==''?'visually-hidden':''?>">
        <div>
            <h1 class="h3 mb-1"><?=e($dashboardTitle)?></h1>
            <p class="text-muted mb-0">Admin and assigned admin access only.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 bdc-mobile-actions">
            <?php if (Auth::isSuperAdmin()): ?><a class="btn btn-outline-success" href="<?= e(queryUrl(['export' => 'csv', 'page' => null])) ?>">Export Competitors CSV</a><a class="btn btn-outline-danger" href="special-category-reconciliation.php">Evidence Review</a><?php endif; ?>
            <?php if (Auth::can('competitors.edit')): ?><a class="btn btn-outline-warning" href="special-category-recovery.php">Special Category Recovery</a><a class="btn btn-outline-info" href="test-event-profile-report.php">Test Event Profile Evidence</a><a class="btn btn-outline-danger" href="merge.php">Merge duplicates</a> <a class="btn btn-outline-primary" href="career-links.php">Move Results & Career Links</a><a class="btn btn-dark" href="edit.php?dance=<?=e($dashboard?:'bachata')?><?= $dashboard!==''?'&amp;dashboard='.e($dashboard):'' ?>&amp;return=<?=e(rawurlencode($currentListReturn))?>">Add competitor</a><?php endif; ?>
        </div>
    </div>

    <div class="summary-grid mb-4">
        <?php foreach ($counts as $key => $count): ?>
            <?php $isAll=$key==='all_participants';$isActive=$isAll?!$hasListFilters:$filter===$key; ?>
            <div>
                <a class="card summary-card <?= $isActive ? 'active' : '' ?> text-decoration-none" href="<?=e(queryUrl(['filter'=>$isAll?null:$key,'q'=>null,'country'=>null,'role'=>null,'division'=>null,'status'=>null,'page'=>1]))?>">
                    <div class="card-body">
                        <div class="small text-muted"><?= e(ucwords(str_replace('_', ' ', $key))) ?></div>
                        <div class="summary-value"><?= $count ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <form id="competitor-filter-form" class="card premium-panel mb-3" method="get" action="">
        <?php if($dashboard!==''):?><input type="hidden" name="dashboard" value="<?=e($dashboard)?>"><?php endif;?>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small text-muted">Search</label>
                    <input id="competitor-search" class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, <?=e($dashboardIdLabel)?>, Instagram or email" autocomplete="off">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label small text-muted">Country</label>
                    <select class="form-select" name="country">
                        <option value="">All countries</option>
                        <?php foreach ($countries as $item): ?>
                            <option value="<?= e((string)$item) ?>" <?= $country === $item ? 'selected' : '' ?>><?= e((string)$item) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if($dashboard===''):?><div class="col-lg-2 col-md-3">
                    <label class="form-label small text-muted">Dance Style</label>
                    <select class="form-select" name="dance_style"><option value="">Bachata &amp; Salsa</option><option value="bachata" <?=$danceStyle==='bachata'?'selected':''?>>Bachata</option><option value="salsa" <?=$danceStyle==='salsa'?'selected':''?>>Salsa</option></select>
                </div><?php endif;?>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label small text-muted">Role</label>
                    <select class="form-select" name="role">
                        <option value="">All roles</option>
                        <?php foreach ($allowedRoles as $item): ?>
                            <option value="<?= e($item) ?>" <?= $role === $item ? 'selected' : '' ?>><?= e(ucfirst($item)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label small text-muted">Division</label>
                    <select class="form-select" name="division">
                        <option value="">All divisions</option>
                        <optgroup label="Career Divisions"><?php foreach (($dashboard==='salsa'?['novice','intermediate','advanced','unknown']:['novice','intermediate','advanced','all_star','professional','unknown']) as $item): ?><option value="<?=e($item)?>" <?=$division===$item?'selected':''?>><?=e(ucwords(str_replace('_',' ',$item)))?></option><?php endforeach;?></optgroup>
                        <optgroup label="Special Categories"><?php foreach(($dashboard==='salsa'?['salsa_rising'=>'Salsa Rising','salsa_open'=>'Salsa Open','salsa_invitational'=>'Salsa Invitational']:($dashboard==='bachata'?['bachata_rising'=>'Bachata Rising','bachata_open'=>'Bachata Open','bachata_invitational'=>'Bachata Invitational']:['bachata_rising'=>'Bachata Rising','bachata_open'=>'Bachata Open','bachata_invitational'=>'Bachata Invitational','salsa_rising'=>'Salsa Rising','salsa_open'=>'Salsa Open','salsa_invitational'=>'Salsa Invitational'])) as $value=>$label):?><option value="<?=e($value)?>" <?=$division===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></optgroup>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label small text-muted">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($allowedStatuses as $item): ?>
                            <option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e(ucfirst($item)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-4">
                    <label class="form-label small text-muted">Missing information</label>
                    <select class="form-select" name="filter">
                        <option value="">Any</option>
                        <?php foreach (['missing_photo', 'missing_country', 'incomplete_profile', 'special_category'] as $item): ?>
                            <option value="<?= e($item) ?>" <?= $filter === $item ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $item))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label small text-muted">Sort by</label>
                    <select class="form-select" name="sort">
                        <?php foreach ([
                            'name' => 'Name', 'bdc_id' => 'BDC ID', 'country' => 'Country', 'role' => 'Role',
                            'division' => 'Division', 'total' => 'Total points', 'status' => 'Status',
                            'created' => 'Date created', 'updated' => 'Last updated'
                        ] as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small text-muted">Order</label>
                    <select class="form-select" name="order">
                        <option value="asc" <?= $order === 'asc' ? 'selected' : '' ?>>Ascending</option>
                        <option value="desc" <?= $order === 'desc' ? 'selected' : '' ?>>Descending</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label small text-muted">Rows per page</label>
                    <select class="form-select" name="per_page">
                        <?php foreach ($allowedPerPage as $item): ?>
                            <option value="<?= $item ?>" <?= $perPage === $item ? 'selected' : '' ?>><?= $item ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-9 d-flex align-items-end gap-2">
                    <button class="btn btn-dark flex-grow-1">Apply</button>
                    <a class="btn btn-outline-secondary" href="<?=$dashboard!==''?'?dashboard='.e($dashboard):'?'?>">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="text-muted small">
            Showing <?= $totalRows === 0 ? 0 : $offset + 1 ?> to <?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?> competitors
        </div>
        <div class="text-muted small">Click any column heading to sort.</div>
    </div>

    <?php if($dashboard!==''):?><div class="scope-note mb-3"><strong><?=e(strtoupper($dashboardCouncil))?> integrity scope:</strong> this directory reads only <?=e($dashboard==='salsa'?'active SDC identities and Salsa profiles':'BDC result identities and Bachata profiles')?>. Shared contact details and photos do not create an identity in another council.</div><?php endif;?>
    <div id="competitor-directory" class="card premium-panel premium-table-wrap">
        <div class="table-responsive">
            <table class="table premium-table table-hover align-middle mb-0" data-mobile-cards>
                <thead>
                <tr>
                    <th>Photo</th>
                    <th><a class="sortable" href="<?= e(sortUrl('bdc_id', $sort, $order)) ?>"><?=e($dashboardIdLabel)?><?= sortMark('bdc_id', $sort, $order) ?></a></th>
                    <th><a class="sortable" href="<?= e(sortUrl('name', $sort, $order)) ?>">Name<?= sortMark('name', $sort, $order) ?></a></th>
                    <th><a class="sortable" href="<?= e(sortUrl('country', $sort, $order)) ?>">Country<?= sortMark('country', $sort, $order) ?></a></th>
                    <?php if($dashboard===''):?><th>Bachata Profile</th><th>Salsa Profile</th><?php else:?><th><?=e(ucfirst($dashboard))?> Profile</th><?php endif;?>
                    <?php if($dashboard!=='salsa'):?><th><a class="sortable" href="<?= e(sortUrl('total', $sort, $order)) ?>"><?=$dashboard===''?'Points by Style':e(ucfirst($dashboard).' Points')?><?= sortMark('total', $sort, $order) ?></a></th><?php endif;?>
                    <th><a class="sortable" href="<?= e(sortUrl('status', $sort, $order)) ?>">Status<?= sortMark('status', $sort, $order) ?></a></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="10" class="text-center text-muted py-5">No competitors match the selected filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row):
                    $photo = $row['photo_url'] ?: url('public/assets/img/default-competitor.svg');
                ?>
                    <tr id="competitor-<?= (int)$row['id'] ?>">
                        <td><img src="<?= e($photo) ?>" class="competitor-photo" alt=""></td>
                        <td><code class="identity-code"><?= e((string)$row['dashboard_identity_code']) ?></code></td>
                        <td>
                            <strong class="competitor-name"><?= e($row['exact_name']) ?></strong>
                            <div class="small text-muted"><?= e((string)$row['instagram']) ?></div>
                        </td>
                        <td><?= e($row['country'] ?: '—') ?></td>
                        <?php foreach ($dashboard!==''?[$dashboard]:['bachata','salsa'] as $style): $div=(string)($row[$style.'_division']??'');$rrole=(string)($row[$style.'_role']??'');$specials=array_values(array_filter(explode(',',(string)($row[$style.'_special_categories']??''))));?><td><div class="profile-box <?=$specials!==[]?'special':''?>"><strong><?=ucfirst($style)?></strong><?php if($div):?><div><span class="badge text-bg-primary"><?=e(ucwords(str_replace('_',' ',$div)))?></span></div><?php endif;?><?php foreach($specials as $special):?><div class="mt-1"><span class="badge text-bg-info"><?=e(SpecialCategoryService::label($special))?></span></div><?php endforeach;?><div class="small text-muted mt-1"><?=$rrole==='unknown'?'Role not required / unset':e(ucfirst($rrole))?></div><?php if(!$div&&$specials===[]):?><div class="small text-muted">No profile</div><?php endif;?></div></td><?php endforeach;?>
                        <?php if($dashboard!=='salsa'):?><td><?php foreach($dashboard!==''?[$dashboard=>ucfirst($dashboard)]:['bachata'=>'Bachata'] as $style=>$label):?><div><strong><?=e($label)?> Total:</strong> <?=e((string)(float)$row[$style.'_points'])?></div><div class="small text-muted"><span>Novice: <?=e((string)(float)$row[$style.'_novice_points'])?></span> · <span>Intermediate: <?=e((string)(float)$row[$style.'_intermediate_points'])?></span> · <span>Advanced: <?=e((string)(float)$row[$style.'_advanced_points'])?></span></div><?php endforeach;?></td><?php endif;?>
                        <td><span class="badge text-bg-<?= $row['status'] === 'active' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                        <td class="text-end">
                            <?php if (Auth::can('competitors.edit')): ?>
                                <?php $rowReturn=$currentListReturn.'#competitor-'.(int)$row['id']; ?>
                                <a class="btn btn-sm btn-outline-dark" href="edit.php?id=<?= (int)$row['id'] ?>&amp;dance=<?=e($dashboard?:'bachata')?><?= $dashboard!==''?'&amp;dashboard='.e($dashboard):'' ?>&amp;return=<?= e(rawurlencode($rowReturn)) ?>">Edit</a>
                                <a class="btn btn-sm btn-outline-primary" href="photo-adjust.php?id=<?= (int)$row['id'] ?>&amp;dashboard=<?=e($dashboard)?>&amp;return=<?= e(rawurlencode($rowReturn)) ?>">Adjust photo</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3" aria-label="Competitor pages">
            <ul class="pagination pagination-sm flex-wrap">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(queryUrl(['page' => max(1, $page - 1)])) ?>">Previous</a></li>
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                if ($start > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= e(queryUrl(['page' => 1])) ?>">1</a></li>
                    <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(queryUrl(['page' => $i])) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= e(queryUrl(['page' => $totalPages])) ?>"><?= $totalPages ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(queryUrl(['page' => min($totalPages, $page + 1)])) ?>">Next</a></li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<script>
(function () {
    const form = document.getElementById('competitor-filter-form');
    const search = document.getElementById('competitor-search');
    if (!form || !search) return;

    let timer = null;
    search.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
            const page = form.querySelector('input[name="page"]');
            if (page) page.value = '1';
            form.requestSubmit();
        }, 450);
    });

    form.querySelectorAll('select').forEach(function (select) {
        select.addEventListener('change', function () {
            form.requestSubmit();
        });
    });
})();
</script>
</body>
</html>
