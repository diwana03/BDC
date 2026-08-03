<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\SchemaUpdater;
use App\Services\DivisionProgressionService;

Auth::requirePermission('competitors.view');

$pdo = Database::connection();
SchemaUpdater::run($pdo);

$q        = trim((string)($_GET['q'] ?? ''));
$filter   = (string)($_GET['filter'] ?? '');
$country  = trim((string)($_GET['country'] ?? ''));
$role     = (string)($_GET['role'] ?? '');
$division = (string)($_GET['division'] ?? '');
$status   = (string)($_GET['status'] ?? '');
$sort     = (string)($_GET['sort'] ?? 'name');
$order    = strtolower((string)($_GET['order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$perPage  = (int)($_GET['per_page'] ?? 50);
$page     = max(1, (int)($_GET['page'] ?? 1));

$allowedPerPage = [25, 50, 100, 200, 500];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 50;
}

$allowedRoles = ['leader', 'follower', 'both', 'unknown'];
$allowedDivisions = ['novice', 'intermediate', 'advanced', 'all_star', 'professional', 'unknown'];
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
} elseif ($filter === 'missing_role') {
    $where[] = "c.dance_role = 'unknown'";
} elseif ($filter === 'missing_division') {
    $where[] = "c.current_division = 'unknown'";
}

if ($country !== '') {
    $where[] = 'c.country = :country';
    $params['country'] = $country;
}
if (in_array($role, $allowedRoles, true)) {
    $where[] = 'c.dance_role = :role';
    $params['role'] = $role;
}
if (in_array($division, $allowedDivisions, true)) {
    $where[] = 'c.current_division = :division';
    $params['division'] = $division;
}
if (in_array($status, $allowedStatuses, true)) {
    $where[] = 'c.status = :status';
    $params['status'] = $status;
}

$sortMap = [
    'name'       => 'c.exact_name',
    'bdc_id'     => 'c.bdc_id',
    'country'    => 'c.country',
    'role'       => 'c.dance_role',
    'division'   => 'c.current_division',
    'total'      => 'total_points',
    'status'     => 'c.status',
    'created'    => 'c.created_at',
    'updated'    => 'c.updated_at',
];
$orderBy = $sortMap[$sort] ?? $sortMap['name'];
$sort = array_key_exists($sort, $sortMap) ? $sort : 'name';
$orderSql = strtoupper($order);
$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) FROM bdc_competitors c WHERE {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT c.*,
        COALESCE(SUM(CASE WHEN p.division='novice' THEN p.points ELSE 0 END),0) AS novice_points,
        COALESCE(SUM(CASE WHEN p.division='intermediate' THEN p.points ELSE 0 END),0) AS intermediate_points,
        COALESCE(SUM(CASE WHEN p.division='advanced' THEN p.points ELSE 0 END),0) AS advanced_points,
        COALESCE(SUM(p.points),0) AS total_points
    FROM bdc_competitors c
    LEFT JOIN bdc_point_transactions p ON p.competitor_id = c.id
    WHERE {$whereSql}
    GROUP BY c.id
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
    'missing_photo'    => (int)$pdo->query("SELECT COUNT(*) FROM bdc_competitors WHERE photo_url IS NULL OR TRIM(photo_url)='' ")->fetchColumn(),
    'missing_country'  => (int)$pdo->query("SELECT COUNT(*) FROM bdc_competitors WHERE country IS NULL OR TRIM(country)='' ")->fetchColumn(),
    'missing_role'     => (int)$pdo->query("SELECT COUNT(*) FROM bdc_competitors WHERE dance_role='unknown'")->fetchColumn(),
    'missing_division' => (int)$pdo->query("SELECT COUNT(*) FROM bdc_competitors WHERE current_division='unknown'")->fetchColumn(),
];

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
        .competitor-photo { width:48px; height:48px; object-fit:cover; border-radius:8px; }
        .table thead th { vertical-align: middle; }
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
        <?php if (Auth::can('competitors.edit')): ?>
            <div class="d-flex gap-2"><a class="btn btn-outline-danger" href="merge.php">Merge duplicates</a> <a class="btn btn-outline-primary" href="career-links.php">Move Results & Career Links</a><a class="btn btn-dark" href="edit.php">Add competitor</a></div>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach ($counts as $key => $count): ?>
            <div class="col-6 col-lg-3">
                <a class="card filter-card <?= $filter === $key ? 'active' : '' ?> text-decoration-none shadow-sm border-0" href="<?= e(queryUrl(['filter' => $filter === $key ? '' : $key, 'page' => 1])) ?>">
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
                        <?php foreach ($allowedDivisions as $item): ?>
                            <option value="<?= e($item) ?>" <?= $division === $item ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $item))) ?></option>
                        <?php endforeach; ?>
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
                        <?php foreach (['missing_photo', 'missing_country', 'missing_role', 'missing_division'] as $item): ?>
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
                    <th><a class="sortable" href="<?= e(sortUrl('role', $sort, $order)) ?>">Role<?= sortMark('role', $sort, $order) ?></a></th>
                    <th><a class="sortable" href="<?= e(sortUrl('division', $sort, $order)) ?>">Division<?= sortMark('division', $sort, $order) ?></a></th>
                    <th>Division Status</th>
                    <th><a class="sortable" href="<?= e(sortUrl('total', $sort, $order)) ?>">Total<?= sortMark('total', $sort, $order) ?></a></th>
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
                    $effectiveDivision=DivisionProgressionService::effectiveDivision(
                        (float)$row['novice_points'],
                        (float)$row['intermediate_points'],
                        (float)$row['advanced_points'],
                        $row['current_division'],
                        (bool)$row['novice_manual_out'],
                        (bool)$row['intermediate_manual_out']
                    );
                ?>
                    <tr>
                        <td><img src="<?= e($photo) ?>" class="competitor-photo" alt=""></td>
                        <td><code><?= e((string)$row['bdc_id']) ?></code></td>
                        <td>
                            <strong><?= e($row['exact_name']) ?></strong>
                            <div class="small text-muted"><?= e((string)$row['instagram']) ?></div>
                        </td>
                        <td><?= e($row['country'] ?: '—') ?></td>
                        <td><?= e(ucfirst($row['dance_role'])) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $row['current_division']))) ?></td>
                        <td>
                            <span class="badge text-bg-primary"><?=e(DivisionProgressionService::label($effectiveDivision))?></span>
                            <?php if($effectiveDivision!==$row['current_division']):?><div class="small text-warning mt-1">Rule-based division differs from stored division</div><?php endif;?>
                            <?php if(!empty($row['division_override_reason'])):?><div class="small text-muted mt-1"><?=e($row['division_override_reason'])?></div><?php endif;?>
                        </td>
                        <td><?= e((string)(float)$row['total_points']) ?></td>
                        <td><span class="badge text-bg-<?= $row['status'] === 'active' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                        <td class="text-end">
                            <?php if (Auth::can('competitors.edit')): ?>
                                <a class="btn btn-sm btn-outline-dark" href="edit.php?id=<?= (int)$row['id'] ?>">Edit</a>
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
