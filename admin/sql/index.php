<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

Auth::requireSuperAdmin();
$pdo = Database::connection();

$sql = '';
$message = '';
$error = '';
$columns = [];
$rows = [];
$affectedRows = null;
$elapsedMs = null;
$maxRows = 500;

function firstSqlKeyword(string $sql): string
{
    $clean = preg_replace('/\A(?:\s|--[^\r\n]*(?:\r?\n|\z)|#[^\r\n]*(?:\r?\n|\z)|\/\*.*?\*\/)+/s', '', $sql) ?? $sql;
    if (preg_match('/\A([a-z]+)/i', $clean, $m)) {
        return strtoupper($m[1]);
    }
    return '';
}

function containsMultipleStatements(string $sql): bool
{
    $sql = rtrim($sql);
    if (str_ends_with($sql, ';')) {
        $sql = rtrim(substr($sql, 0, -1));
    }
    return str_contains($sql, ';');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = trim((string)($_POST['sql'] ?? ''));
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Refresh the page and try again.';
    } elseif ($sql === '') {
        $error = 'Enter an SQL statement.';
    } elseif (strlen($sql) > 20000) {
        $error = 'The SQL statement is too long. Maximum length is 20,000 characters.';
    } elseif (containsMultipleStatements($sql)) {
        $error = 'Run one SQL statement at a time. Multiple statements are blocked.';
    } else {
        $keyword = firstSqlKeyword($sql);
        $readOnlyKeywords = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'];
        $isReadOnly = in_array($keyword, $readOnlyKeywords, true);

        if (!$isReadOnly && empty($_POST['confirm_write'])) {
            $error = 'Confirm that you understand this statement may change the database.';
        } else {
            $started = microtime(true);
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $elapsedMs = round((microtime(true) - $started) * 1000, 2);

                if ($stmt->columnCount() > 0) {
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (count($rows) > $maxRows) {
                        $rows = array_slice($rows, 0, $maxRows);
                        $message = "Query succeeded. Showing the first {$maxRows} rows.";
                    } else {
                        $message = 'Query succeeded. ' . count($rows) . ' row(s) returned.';
                    }
                    if ($rows) {
                        $columns = array_keys($rows[0]);
                    }
                } else {
                    $affectedRows = $stmt->rowCount();
                    $message = 'Statement executed successfully. ' . $affectedRows . ' row(s) affected.';
                }

                Auth::audit(
                    (int)(Auth::user()['id'] ?? 0),
                    'sql_console_execute',
                    [
                        'keyword' => $keyword,
                        'statement' => mb_substr($sql, 0, 2000),
                        'affected_rows' => $affectedRows,
                        'returned_rows' => count($rows),
                        'elapsed_ms' => $elapsedMs,
                    ],
                    'database'
                );
            } catch (Throwable $e) {
                $elapsedMs = round((microtime(true) - $started) * 1000, 2);
                $error = $e->getMessage();
                Auth::audit(
                    (int)(Auth::user()['id'] ?? 0),
                    'sql_console_error',
                    [
                        'keyword' => $keyword,
                        'statement' => mb_substr($sql, 0, 2000),
                        'error' => mb_substr($e->getMessage(), 0, 1000),
                    ],
                    'database'
                );
            }
        }
    }
}

$csrf = Csrf::token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SQL Console | BDC Competitor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('public/assets/css/app.css')) ?>" rel="stylesheet">
    <style>
        .sql-editor { min-height: 230px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .result-wrap { max-height: 65vh; overflow: auto; }
        .result-wrap th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
        .result-wrap td { white-space: nowrap; max-width: 420px; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= e(url('admin/')) ?>">BDC Admin</a>
        <span class="text-white small">Super Admin only</span>
    </div>
</nav>
<div class="container-fluid px-lg-5 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">SQL Console</h1>
            <p class="text-muted mb-0">Execute one SQL statement at a time directly against the portal database.</p>
        </div>
        <a class="btn btn-outline-dark" href="<?= e(url('admin/')) ?>">Dashboard</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?><?php if ($elapsedMs !== null): ?> <span class="text-muted">Completed in <?= e((string)$elapsedMs) ?> ms.</span><?php endif; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><strong>SQL error:</strong> <?= e($error) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <label class="form-label fw-semibold" for="sql">SQL statement</label>
                <textarea class="form-control sql-editor" id="sql" name="sql" spellcheck="false" required><?= e($sql) ?></textarea>
                <div class="form-text">Only one statement is accepted per execution. Query results are limited to 500 displayed rows.</div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" id="confirm_write" name="confirm_write">
                    <label class="form-check-label" for="confirm_write">I understand that non-read-only SQL can permanently change or delete portal data.</label>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button class="btn btn-danger" type="submit">Execute SQL</button>
                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('sql').value=''">Clear</button>
                    <button class="btn btn-outline-dark" type="button" onclick="document.getElementById('sql').value=`UPDATE competitors\nSET current_division = 'Novice'\nWHERE current_division IS NULL\n   OR TRIM(current_division) = ''\n   OR LOWER(TRIM(current_division)) = 'unknown'`">Load Unknown → Novice SQL</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($columns): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body p-0">
            <div class="result-wrap">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead><tr><?php foreach ($columns as $column): ?><th class="px-3 py-2"><?= e((string)$column) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?><tr><?php foreach ($columns as $column): ?><td class="px-3 py-2" title="<?= e((string)($row[$column] ?? '')) ?>"><?= e($row[$column] === null ? 'NULL' : (string)$row[$column]) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="alert alert-warning mt-4 mb-0"><strong>Important:</strong> SQL changes bypass normal portal validation. Take a database backup before running UPDATE, DELETE, ALTER, DROP, or TRUNCATE statements.</div>
</div>
</body>
</html>
