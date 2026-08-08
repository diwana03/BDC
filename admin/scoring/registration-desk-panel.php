<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;

Auth::requireAdmin();
$pdo=Database::connection();
$roundId=(int)($_GET['round_id']??0);
$roundStmt=$pdo->prepare("SELECT r.*,e.name event_name FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:round LIMIT 1");
$roundStmt->execute(['round'=>$roundId]);
$round=$roundStmt->fetch();
if(!$round){http_response_code(404);exit('Scoring round not found.');}

$stmt=$pdo->prepare("SELECT * FROM bdc_registration_desk_links WHERE event_id=:event AND division=:division LIMIT 1");
$stmt->execute(['event'=>$round['event_id'],'division'=>$round['division']]);
$link=$stmt->fetch();
if(!$link){
    $token=bin2hex(random_bytes(24));
    $insert=$pdo->prepare("INSERT INTO bdc_registration_desk_links(event_id,division,token_hash,token_hint,created_by) VALUES(:event,:division,:hash,:hint,:user)");
    $insert->execute([
        'event'=>$round['event_id'],
        'division'=>$round['division'],
        'hash'=>hash('sha256',$token),
        'hint'=>substr($token,0,8),
        'user'=>(int)(Auth::user()['id']??0)?:null,
    ]);
    $link=['id'=>(int)$pdo->lastInsertId(),'plain_token'=>$token];
    $_SESSION['registration_desk_tokens'][(int)$link['id']]=$token;
}
$token=(string)($link['plain_token']??($_SESSION['registration_desk_tokens'][(int)$link['id']]??''));
$deskUrl='';
if($token!==''){
    $appUrl=rtrim((string)Config::get('app.url',''),'/');
    $path=url('registration-desk/?token='.rawurlencode($token).'&round_id='.$roundId);
    $parts=parse_url($appUrl);
    if(is_array($parts)&&isset($parts['scheme'],$parts['host'])){
        $origin=$parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.(int)$parts['port']:'');
        $deskUrl=$origin.$path;
    }else{$deskUrl=$path;}
}
$category=ucwords(str_replace('_',' ',(string)$round['division']));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#fff;color:#172033}.card{border:1px solid #d9dee6;border-radius:10px;padding:14px}.head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.head h2{font-size:18px;margin:0}.live{font-size:11px;font-weight:800;background:#1774ff;color:#fff;border-radius:999px;padding:4px 8px}.small{font-size:12px;color:#667085}.input{display:flex;gap:7px;margin:12px 0;flex-wrap:wrap}.input input{flex:1;min-width:280px;border:1px solid #ccd2da;border-radius:7px;padding:9px}.btn{display:inline-block;border:1px solid #bfc6d0;background:#fff;color:#172033;border-radius:7px;padding:8px 11px;text-decoration:none;font-weight:700;font-size:12px;cursor:pointer}.btn.primary{background:#1774ff;border-color:#1774ff;color:#fff}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}.stat{border:1px solid #e0e4e9;border-radius:8px;padding:10px}.stat strong{display:block;font-size:12px}.stat span{display:block;font-size:19px;font-weight:800;margin-top:4px}.warn{background:#fff4d5;color:#735300;padding:9px;border-radius:7px;margin-top:10px;font-size:12px}@media(max-width:700px){.stats{grid-template-columns:repeat(2,1fr)}.input input{min-width:100%;width:100%}}
</style></head><body><div class="card"><div class="head"><div><h2>Registration Desk</h2><div class="small"><?=htmlspecialchars((string)$round['event_name'])?> · <?=htmlspecialchars($category)?> · <?=htmlspecialchars(ucfirst((string)$round['round_type']))?></div></div><span class="live">LIVE SYNC</span></div>
<?php if($deskUrl!==''):?><div class="input"><input id="deskUrl" readonly value="<?=htmlspecialchars($deskUrl)?>"><button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('deskUrl').value)">Copy Link</button><a class="btn primary" href="<?=htmlspecialchars($deskUrl)?>" target="_blank" rel="noopener">Open Registration Desk</a></div><?php else:?><div class="warn">The secure Registration Desk token is hidden in this admin session. Open the existing desk from the session where its link was generated, or regenerate it from the normal scoring workflow.</div><?php endif;?>
<div class="stats" id="stats"><div class="stat"><strong>Leaders</strong><span data-stat="leaders">—</span></div><div class="stat"><strong>Followers</strong><span data-stat="followers">—</span></div><div class="stat"><strong>Missing Bibs</strong><span data-stat="missing">—</span></div><div class="stat"><strong>Last Update</strong><span data-stat="updated" style="font-size:12px">—</span></div></div></div>
<script>
async function refreshDesk(){try{const r=await fetch('registration-sync.php?round_id=<?=$roundId?>',{cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});const d=await r.json();if(!d.ok)return;document.querySelector('[data-stat="leaders"]').textContent=d.leaders_ready+' / '+d.leaders_total;document.querySelector('[data-stat="followers"]').textContent=d.followers_ready+' / '+d.followers_total;document.querySelector('[data-stat="missing"]').textContent=d.missing_bibs;document.querySelector('[data-stat="updated"]').textContent=d.last_update||'No desk changes yet';}catch(e){}}refreshDesk();setInterval(refreshDesk,3000);
</script></body></html>
