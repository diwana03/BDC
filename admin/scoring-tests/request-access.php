<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Database;
use App\Services\SystemTestAccessService;
$pdo=Database::connection();$error='';$token=(string)($_GET['request']??$_POST['request']??'');
try{
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&($_POST['action']??'')==='request'){
        $token=SystemTestAccessService::request($pdo,(string)($_SERVER['REMOTE_ADDR']??'unknown'),(string)($_SERVER['HTTP_USER_AGENT']??''));
        header('Location: ?request='.rawurlencode($token),true,303);exit;
    }
    $state=$token!==''?SystemTestAccessService::status($pdo,$token):null;
}catch(Throwable $e){$error=$e->getMessage();$state=null;}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Request BDC Test Access</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5" style="max-width:760px"><div class="card shadow-sm"><div class="card-body p-4"><span class="badge text-bg-danger">ISOLATED SYSTEM TEST ONLY</span><h1 class="h3 mt-3">Request dashboard approval</h1><p class="text-muted">This creates no Admin login. A Super Admin must approve a short-lived request that can open only the isolated BDC System Test Runner.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if(!$token):?><form method="post"><input type="hidden" name="action" value="request"><button class="btn btn-danger btn-lg">Request Test Access</button></form><?php else:$status=(string)($state['status']??'invalid');?><div class="alert alert-<?=$status==='approved'?'success':($status==='pending'?'warning':'secondary')?>"><strong>Status: <?=e(strtoupper($status))?></strong><?php if($status==='pending'):?><div>Ask the Super Admin to approve this request on the BDC Admin Dashboard.</div><?php endif;?></div><?php if($status==='approved'):?><a class="btn btn-success btn-lg" href="system-test.php?access=<?=e(rawurlencode($token))?>">Open Approved Test Runner</a><?php elseif($status==='pending'):?><button class="btn btn-outline-secondary" onclick="location.reload()">Check Approval</button><script>setTimeout(()=>location.reload(),5000)</script><?php else:?><form method="post"><input type="hidden" name="action" value="request"><button class="btn btn-danger">Create New Request</button></form><?php endif;?><?php endif;?></div></div></main></body></html>
