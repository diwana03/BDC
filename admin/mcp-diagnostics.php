<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

Auth::requireSuperAdmin();
$pdo=Database::connection();

$stmt=$pdo->query("SELECT id,user_id,action,details_json,ip_address,created_at FROM bdc_audit_logs WHERE entity_type='mcp_connector' OR action LIKE 'mcp_%' ORDER BY id DESC LIMIT 75");
$rows=$stmt->fetchAll();

function diagDetails(array $row):array{
    $decoded=json_decode((string)($row['details_json']??''),true);
    return is_array($decoded)?$decoded:[];
}
function diagBadge(string $action):string{
    if(str_contains($action,'_ok'))return 'ok';
    if(str_contains($action,'rejected')||str_contains($action,'error')||str_contains($action,'exception'))return 'bad';
    return 'neutral';
}

$latestFailure=null;
foreach($rows as $row){
    $action=(string)$row['action'];
    if(str_contains($action,'rejected')||str_contains($action,'error')||str_contains($action,'exception')){$latestFailure=$row;break;}
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>BDC MCP Diagnostics</title>
<style>
body{margin:0;background:#f4f6f8;color:#17202a;font:14px/1.45 system-ui,-apple-system,Segoe UI,Arial,sans-serif}.wrap{max-width:1380px;margin:0 auto;padding:28px}.top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}.top h1{margin:0;font-size:26px}.top a{color:#173b63;text-decoration:none;font-weight:700}.card{background:#fff;border:1px solid #dfe5eb;border-radius:14px;padding:18px;margin-bottom:20px;box-shadow:0 3px 12px rgba(0,0,0,.04)}.card h2{margin:0 0 12px;font-size:18px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}.kv{background:#f7f9fb;border-radius:10px;padding:10px}.kv b{display:block;font-size:11px;text-transform:uppercase;color:#687787;margin-bottom:4px}.ok{color:#0a6b37}.bad{color:#a61b29}.neutral{color:#665b16}table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #dfe5eb;border-radius:14px;overflow:hidden}th,td{padding:10px 12px;border-bottom:1px solid #e8edf2;text-align:left;vertical-align:top}th{background:#eef3f7;font-size:12px;text-transform:uppercase;color:#536373}tr:last-child td{border-bottom:0}code{font:12px ui-monospace,SFMono-Regular,Consolas,monospace;white-space:pre-wrap;word-break:break-word}.small{font-size:12px;color:#6a7886}.pill{display:inline-block;border-radius:999px;padding:3px 8px;background:#edf1f4;font-size:12px;font-weight:700}.pill.ok{background:#e7f6ed}.pill.bad{background:#fdebed}.pill.neutral{background:#fff7d8}@media(max-width:760px){.wrap{padding:14px}table{display:block;overflow:auto;white-space:nowrap}}
</style></head><body><div class="wrap">
<div class="top"><div><h1>BDC MCP Diagnostics</h1><div class="small">Super Admin only · no bearer tokens or credentials are stored or displayed</div></div><div><a href="<?=e(url('admin/'))?>">← Admin</a> · <a href="<?=e(url('admin/mcp-diagnostics.php'))?>">Refresh</a></div></div>
<?php if($latestFailure): $d=diagDetails($latestFailure); ?>
<div class="card"><h2 class="bad">Latest MCP failure</h2><div class="grid">
<div class="kv"><b>Time</b><?=e((string)$latestFailure['created_at'])?></div>
<div class="kv"><b>Action</b><?=e((string)$latestFailure['action'])?></div>
<div class="kv"><b>Diagnostic ID</b><?=e((string)($d['diagnostic_id']??'—'))?></div>
<div class="kv"><b>Server Version</b><?=e((string)($d['server_version']??'—'))?></div>
<div class="kv"><b>Method / Tool</b><?=e(trim((string)($d['method']??'').' '.(string)($d['tool']??''))?:'—')?></div>
<div class="kv"><b>Header Source</b><?=e((string)($d['header_source']??'—'))?></div>
<div class="kv"><b>Header Present</b><?=!empty($d['header_present'])?'YES':'NO'?></div>
<div class="kv"><b>Required Scope</b><?=e((string)($d['required_scope']??'—'))?></div>
<div class="kv"><b>Exception</b><?=e((string)($d['exception']??'—'))?></div>
<div class="kv"><b>Message</b><?=e((string)($d['message']??'—'))?></div>
</div><?php if(isset($d['auth'])&&is_array($d['auth'])): ?><h3>Authentication diagnostic</h3><code><?=e(json_encode($d['auth'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></code><?php endif; ?></div>
<?php else: ?><div class="card"><h2>No MCP failure recorded yet</h2><p>Make one real BDC Portal tool call, then refresh this page.</p></div><?php endif; ?>
<table><thead><tr><th>Time</th><th>Status</th><th>Action</th><th>Diagnostic</th><th>Method / Tool</th><th>Header</th><th>Auth / Error detail</th></tr></thead><tbody>
<?php foreach($rows as $row): $d=diagDetails($row); $badge=diagBadge((string)$row['action']); ?>
<tr><td><?=e((string)$row['created_at'])?></td><td><span class="pill <?=$badge?>"><?=e($badge==='ok'?'OK':($badge==='bad'?'FAIL':'INFO'))?></span></td><td><?=e((string)$row['action'])?></td><td><code><?=e((string)($d['diagnostic_id']??'—'))?></code></td><td><?=e(trim((string)($d['method']??'').' '.(string)($d['tool']??''))?:'—')?></td><td><?=e((string)($d['header_source']??'—'))?><?=array_key_exists('header_present',$d)?(' · '.(!empty($d['header_present'])?'present':'missing')):''?></td><td><code><?=e(isset($d['auth'])?json_encode($d['auth'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):(string)($d['message']??$d['exception']??'—'))?></code></td></tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7">No MCP diagnostic records found.</td></tr><?php endif; ?>
</tbody></table>
</div></body></html>
