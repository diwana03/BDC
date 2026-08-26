<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\SchemaUpdater;
use App\Services\DivisionProgressionService;
use App\Services\SpecialCategoryService;

Auth::requirePermission('competitors.view');

$pdo = Database::connection();


$q        = trim((string)($_GET['q'] ?? ''));
$filter   = (string)($_GET['filter'] ?? '');
$country  = trim((string)($_GET['country'] ?? ''));
$role     = (string)($_GET['role'] ?? '');
$division = (string)($_GET['division'] ?? '');
$status   = (string)($_GET['status'] ?? '');
$danceStyle = in_array((string)($_GET['dance_style'] ?? ''), ['bachata', 'salsa'], true) ? (string)$_GET['dance_style'] : '';
$sort     = (string)($_GET['sort'] ?? 'name');
$order    = strtolower((string)($_GET['order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$perPage  = (int)($_GET['per_page'] ?? 50);
$page     = max(1, (int)($_GET['page'] ?? 1));

$allowedPerPage = [25, 50, 100, 200, 500];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 50;
}

$allowedRoles = ['leader', 'follower', 'both', 'unknown'];
$allowedDivisions = ['novice', 'intermediate', 'advanced', 'all_star', 'professional', 'bachata_rising', 'bachata_open', 'bachata_invitational', 'salsa_rising', 'salsa_open', 'unknown'];
$allowedStatuses = ['active', 'pending', 'archived'];

$where = ['1=1'];
$params = [];

if ($q !== '') {
    // Use unique placeholders because native PDO MySQL prepared statements
    // cannot reliably reuse the same named parameter more than once.
    $where[] = '(c.exact_name LIKE :q_name OR c.normalised_name LIKE :q_normalised OR c.bdc_id LIKE :q_bdc OR c.instagram LIKE :q_instagram OR c.email LIKE :q_email)';
    $searchValue = '%' . $q . '%';
    $params['q_name'] = $searchValue;
    $params['q_normalised'] = $searchValue;
    $params['q_bdc'] = $searchValue;
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
    $where[] = 'c.country = :country';
    $params['country'] = $country;
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
    'bdc_id'     => 'c.bdc_id',
    'country'    => 'c.country',
    'role'       => 'COALESCE(bp.dance_role,sp.dance_role)',
    'division'   => 'COALESCE(bp.current_division,sp.current_division)',
    'total'      => 'total_points',
    'status'     => 'c.status',
    'created'    => 'c.created_at',
    'updated'    => 'c.updated_at',
];
$orderBy = $sortMap[$sort] ?? $sortMap['name'];
$sort = array_key_exists($sort, $sortMap) ? $sort : 'name';
$orderSql = strtoupper($order);
$whereSql = implode(' AND ', $where);

$baseListSql = "SELECT c.*,bp.dance_role bachata_role,bp.current_division bachata_division,sc.bachata_special_categories,
        sp.dance_role salsa_role,sp.current_division salsa_division,sc.salsa_special_categories,
        COALESCE(pp.bachata_points,0) bachata_points,COALESCE(pp.bachata_novice_points,0) bachata_novice_points,COALESCE(pp.bachata_intermediate_points,0) bachata_intermediate_points,COALESCE(pp.bachata_advanced_points,0) bachata_advanced_points,
        COALESCE(pp.salsa_points,0) salsa_points,COALESCE(pp.salsa_novice_points,0) salsa_novice_points,COALESCE(pp.salsa_intermediate_points,0) salsa_intermediate_points,COALESCE(pp.salsa_advanced_points,0) salsa_advanced_points,
        COALESCE(pp.bachata_points,0)+COALESCE(pp.salsa_points,0) AS total_points
    FROM bdc_competitors c
    LEFT JOIN bdc_competitor_discipline_profiles bp ON bp.competitor_id=c.id AND bp.dance_style='bachata'
    LEFT JOIN bdc_competitor_discipline_profiles sp ON sp.competitor_id=c.id AND sp.dance_style='salsa'
    LEFT JOIN (SELECT competitor_id,GROUP_CONCAT(CASE WHEN dance_style='bachata' THEN category END ORDER BY category SEPARATOR ',') bachata_special_categories,GROUP_CONCAT(CASE WHEN dance_style='salsa' THEN category END ORDER BY category SEPARATOR ',') salsa_special_categories FROM bdc_competitor_special_categories GROUP BY competitor_id) sc ON sc.competitor_id=c.id
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
    header('Content-Disposition: attachment; filename="bdc-competitors-' . date('Y-m-d-His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        http_response_code(500);
        exit('Unable to create competitor export.');
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'BDC ID','Exact Name','Email','Phone','Instagram','Country',
        'Bachata Role','Bachata Division','Bachata Special Category','Salsa Role','Salsa Division','Salsa Special Category',
        'Bachata Points','Salsa Points','Total Points','Status','Created At','Updated At',
    ]);
    $safeCsv = static function ($value) {
        $text = (string)($value ?? '');
        return preg_match('/^[=+\-@]/u', $text) === 1 ? "'" . $text : $text;
    };
    foreach ($exportRows as $exportRow) {
        fputcsv($out, [
            $safeCsv($exportRow['bdc_id'] ?? ''),
            $safeCsv($exportRow['exact_name'] ?? ''),
            $safeCsv($exportRow['email'] ?? ''),
            $safeCsv($exportRow['phone'] ?? ''),
            $safeCsv($exportRow['instagram'] ?? ''),
            $safeCsv($exportRow['country'] ?? ''),
            $safeCsv($exportRow['bachata_role'] ?? ''),
            $safeCsv($exportRow['bachata_division'] ?? ''),
            $safeCsv($exportRow['bachata_special_categories'] ?? ''),
            $safeCsv($exportRow['salsa_role'] ?? ''),
            $safeCsv($exportRow['salsa_division'] ?? ''),
            $safeCsv($exportRow['salsa_special_categories'] ?? ''),
            (float)($exportRow['bachata_points'] ?? 0),
            (float)($exportRow['salsa_points'] ?? 0),
            (float)($exportRow['total_points'] ?? 0),
            $safeCsv($exportRow['status'] ?? ''),
            $safeCsv($exportRow['created_at'] ?? ''),
            $safeCsv($exportRow['updated_at'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

$countSql = "SELECT COUNT(*) FROM bdc_competitors c WHERE {$whereSql}";
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

$counts = [
    'all_participants' => (int)$pdo->query("SELECT COUNT(*) FROM bdc_competitors")->fetchColumn(),
    'missing_photo'    => (int)$pdo->query("SELECT COUNT(*) FROM bdc_competitors WHERE photo_url IS NULL OR TRIM(photo_url)='' ")->fetchColumn(),
    'missing_country'  => (int)$pdo->query("SELECT COUNT(*) FROM bdc_competitors WHERE country IS NULL OR TRIM(country)='' ")->fetchColumn(),
    'incomplete_profile' => (int)$pdo->query("SELECT COUNT(DISTINCT competitor_id) FROM bdc_competitor_discipline_profiles WHERE dance_role='unknown' OR current_division='unknown'")->fetchColumn(),
    'special_category' => (int)$pdo->query("SELECT COUNT(DISTINCT competitor_id) FROM bdc_competitor_special_categories")->fetchColumn(),
];
$hasListFilters=$q!==''||$filter!==''||$country!==''||$danceStyle!==''||$role!==''||$division!==''||$status!=='';

$countries = $pdo->query("SELECT DISTINCT country FROM bdc_competitors WHERE country IS NOT NULL AND TRIM(country)<>'' ORDER BY country")->fetchAll(PDO::FETCH_COLUMN);

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
    <title>Competitor Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('public/assets/css/app.css')) ?>" rel="stylesheet">
    <style>
        .sortable { color: inherit; text-decoration: none; white-space: nowrap; }
        .sortable:hover { text-decoration: underline; }
        .filter-card.active { outline: 2px solid #212529; }
        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:1rem; }
        .competitor-photo { width:48px; height:48px; object-fit:cover; border-radius:8px; }
        .table thead th { vertical-align: middle; }
        .profile-box { min-width:190px; padding:.55rem .7rem; border:1px solid #dee2e6; border-radius:.55rem; background:#fff; }
        .profile-box.special { border-color:#0dcaf0; background:#f0fbff; }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= e(url('admin/')) ?>">BDC Admin</a>
        <div><a class="btn btn-outline-light btn-sm" href="<?= e(url('leaderboard/')) ?>">Leaderboard</a></div>
    </div>
</nav>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Competitor Management</h1>
            <p class="text-muted mb-0">Admin and assigned admin access only.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (Auth::isSuperAdmin()): ?><a class="btn btn-outline-success" href="<?= e(queryUrl(['export' => 'csv', 'page' => null])) ?>">Export Competitors CSV</a><?php endif; ?>
            <?php if (Auth::can('competitors.edit')): ?><a class="btn btn-outline-warning" href="special-category-recovery.php">Special Category Recovery</a><a class="btn btn-outline-info" href="test-event-profile-report.php">Test Event Profile Evidence</a><a class="btn btn-outline-danger" href="merge.php">Merge duplicates</a> <a class="btn btn-outline-primary" href="career-links.php">Move Results & Career Links</a><a class="btn btn-dark" href="edit.php">Add competitor</a><?php endif; ?>
        </div>
    </div>

    <div class="summary-grid mb-4">
        <?php foreach ($counts as $key => $count): ?>
            <?php $isAll=$key==='all_participants';$isActive=$isAll?!$hasListFilters:$filter===$key; ?>
            <div>
                <a class="card filter-card <?= $isActive ? 'active' : '' ?> text-decoration-none shadow-sm border-0" href="<?= $isAll?'?':e(queryUrl(['filter' => $filter === $key ? '' : $key, 'page' => 1])) ?>">
                    <div class="card-body">
                        <div class="small text-muted"><?= e(ucwords(str_replace('_', ' ', $key))) ?></div>
                        <div class="fs-2 fw-bold"><?= $count ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <form id="competitor-filter-form" class="card border-0 shadow-sm mb-3" method="get" action="">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small text-muted">Search</label>
                    <input id="competitor-search" class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, BDC ID, Instagram or email" autocomplete="off">
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
                <div class="col-lg-2 col-md-3">
                    <label class="form-label small text-muted">Dance Style</label>
                    <select class="form-select" name="dance_style"><option value="">Bachata &amp; Salsa</option><option value="bachata" <?=$danceStyle==='bachata'?'selected':''?>>Bachata</option><option value="salsa" <?=$danceStyle==='salsa'?'selected':''?>>Salsa</option></select>
                </div>
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
                        <optgroup label="Career Divisions"><?php foreach (['novice','intermediate','advanced','all_star','professional','unknown'] as $item): ?><option value="<?=e($item)?>" <?=$division===$item?'selected':''?>><?=e(ucwords(str_replace('_',' ',$item)))?></option><?php endforeach;?></optgroup>
                        <optgroup label="Special Categories"><option value="bachata_rising" <?=$division==='bachata_rising'?'selected':''?>>Bachata Rising</option><option value="bachata_open" <?=$division==='bachata_open'?'selected':''?>>Bachata Open</option><option value="bachata_invitational" <?=$division==='bachata_invitational'?'selected':''?>>Bachata Invitational</option><option value="salsa_rising" <?=$division==='salsa_rising'?'selected':''?>>Salsa Rising</option><option value="salsa_open" <?=$division==='salsa_open'?'selected':''?>>Salsa Open</option></optgroup>
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
                    <a class="btn btn-outline-secondary" href="?">Reset</a>
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

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Photo</th>
                    <th><a class="sortable" href="<?= e(sortUrl('bdc_id', $sort, $order)) ?>">BDC ID<?= sortMark('bdc_id', $sort, $order) ?></a></th>
                    <th><a class="sortable" href="<?= e(sortUrl('name', $sort, $order)) ?>">Name<?= sortMark('name', $sort, $order) ?></a></th>
                    <th><a class="sortable" href="<?= e(sortUrl('country', $sort, $order)) ?>">Country<?= sortMark('country', $sort, $order) ?></a></th>
                    <th>Bachata Profile</th><th>Salsa Profile</th>
                    <th><a class="sortable" href="<?= e(sortUrl('total', $sort, $order)) ?>">Points by Style<?= sortMark('total', $sort, $order) ?></a></th>
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
                        <td><code><?= e((string)$row['bdc_id']) ?></code></td>
                        <td>
                            <strong><?= e($row['exact_name']) ?></strong>
                            <div class="small text-muted"><?= e((string)$row['instagram']) ?></div>
                        </td>
                        <td><?= e($row['country'] ?: '—') ?></td>
                        <?php foreach (['bachata','salsa'] as $style): $div=(string)($row[$style.'_division']??'');$rrole=(string)($row[$style.'_role']??'');$specials=array_values(array_filter(explode(',',(string)($row[$style.'_special_categories']??''))));?><td><div class="profile-box <?=$specials!==[]?'special':''?>"><strong><?=ucfirst($style)?></strong><?php if($div):?><div><span class="badge text-bg-primary"><?=e(ucwords(str_replace('_',' ',$div)))?></span></div><?php endif;?><?php foreach($specials as $special):?><div class="mt-1"><span class="badge text-bg-info"><?=e(SpecialCategoryService::label($special))?></span></div><?php endforeach;?><div class="small text-muted mt-1"><?=$rrole==='unknown'?'Role not required / unset':e(ucfirst($rrole))?></div><?php if(!$div&&$specials===[]):?><div class="small text-muted">No profile</div><?php endif;?></div></td><?php endforeach;?>
                        <td><?php foreach(['bachata'=>'Bachata','salsa'=>'Salsa'] as $style=>$label):?><div class="<?=$style==='salsa'?'mt-2':''?>"><strong><?=e($label)?> Total:</strong> <?=e((string)(float)$row[$style.'_points'])?></div><div class="small text-muted"><span>Novice: <?=e((string)(float)$row[$style.'_novice_points'])?></span> · <span>Intermediate: <?=e((string)(float)$row[$style.'_intermediate_points'])?></span> · <span>Advanced: <?=e((string)(float)$row[$style.'_advanced_points'])?></span></div><?php endforeach;?></td>
                        <td><span class="badge text-bg-<?= $row['status'] === 'active' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                        <td class="text-end">
                            <?php if (Auth::can('competitors.edit')): ?>
                                <?php $rowReturn=$currentListReturn.'#competitor-'.(int)$row['id']; ?>
                                <a class="btn btn-sm btn-outline-dark" href="edit.php?id=<?= (int)$row['id'] ?>&amp;return=<?= e(rawurlencode($rowReturn)) ?>">Edit</a>
                                <a class="btn btn-sm btn-outline-primary" href="photo-adjust.php?id=<?= (int)$row['id'] ?>&amp;return=<?= e(rawurlencode($rowReturn)) ?>">Adjust photo</a>
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
