<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

if($_SERVER['REQUEST_METHOD']==='GET'){
    $query=http_build_query(array_filter(['token'=>$_GET['token']??null,'round_id'=>$_GET['round_id']??null],static fn($value):bool=>$value!==null&&$value!==''));
    header('Location: index.php'.($query!==''?'?'.$query:''),true,303);
    exit;
}

use App\Core\Csrf;
use App\Core\Database;
use App\Services\SpecialCategoryService;

$pdo=Database::connection();
SpecialCategoryService::ensureSchema($pdo);

$token=trim((string)($_GET['token']??$_POST['token']??''));
if($token===''){http_response_code(403);exit('Registration Desk link is missing.');}
$stmt=$pdo->prepare("SELECT l.*,e.name event_name,e.event_date FROM bdc_registration_desk_links l JOIN bdc_events e ON e.id=l.event_id WHERE l.token_hash=:hash AND l.is_enabled=1 AND (l.expires_at IS NULL OR l.expires_at>NOW()) LIMIT 1");
$stmt->execute(['hash'=>hash('sha256',$token)]);
$desk=$stmt->fetch();
if(!$desk||!SpecialCategoryService::isSpecial((string)$desk['division'])){http_response_code(403);exit('This special-category Registration Desk link is invalid, disabled or expired.');}

$requestedRoundId=(int)($_GET['round_id']??$_POST['round_id']??0);
$roundStmt=$pdo->prepare($requestedRoundId>0
    ?"SELECT * FROM bdc_scoring_rounds WHERE id=:round AND event_id=:event AND division=:category AND status<>'archived' LIMIT 1"
    :"SELECT * FROM bdc_scoring_rounds WHERE event_id=:event AND division=:category AND status NOT IN('archived','completed') ORDER BY id DESC LIMIT 1");
$params=['event'=>$desk['event_id'],'category'=>$desk['division']];
if($requestedRoundId>0)$params['round']=$requestedRoundId;
$roundStmt->execute($params);
$round=$roundStmt->fetch();
if(!$round){http_response_code(404);exit('The selected special-category scoring round is not available.');}
$roundId=(int)$round['id'];
$category=(string)$round['division'];

function specialDeskRespond(array $payload,int $status=200):never{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

function specialDeskEntries(PDO $pdo,int $roundId):array{
    $stmt=$pdo->prepare("SELECT se.id,se.competitor_id,se.dance_role,se.bib_number,se.display_name,se.entry_status,se.desk_checked_in,se.desk_ready,se.desk_updated_at,se.updated_at,c.bdc_id FROM bdc_scoring_entries se LEFT JOIN bdc_competitors c ON c.id=se.competitor_id WHERE se.round_id=:round ORDER BY se.dance_role,se.bib_number IS NULL,se.bib_number,se.display_name");
    $stmt->execute(['round'=>$roundId]);
    return $stmt->fetchAll();
}

function specialDeskLog(PDO $pdo,array $desk,string $action,array $competitor,array $details=[]):void{
    $stmt=$pdo->prepare("INSERT INTO bdc_registration_desk_activity(desk_link_id,event_id,division,action,competitor_id,competitor_name,details) VALUES(:link,:event,:category,:action,:competitor,:name,:details)");
    $stmt->execute([
        'link'=>$desk['id'],
        'event'=>$desk['event_id'],
        'category'=>$desk['division'],
        'action'=>$action,
        'competitor'=>$competitor['id']??$competitor['competitor_id']??null,
        'name'=>$competitor['exact_name']??$competitor['display_name']??null,
        'details'=>json_encode($details,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    ]);
}

function specialDeskFind(PDO $pdo,string $term,string $role):?array{
    $bdc='';if(preg_match('/^(BDC-\d+)/i',$term,$match))$bdc=strtoupper($match[1]);
    $stmt=$pdo->prepare("SELECT id,bdc_id,exact_name,dance_role,current_division,status FROM bdc_competitors WHERE status<>'archived' AND dance_role IN(:role,'both') AND (bdc_id=:bdc OR id=:numeric OR LOWER(exact_name)=LOWER(:exact)) ORDER BY CASE WHEN dance_role=:preferred THEN 0 ELSE 1 END,id LIMIT 1");
    $stmt->execute(['role'=>$role,'bdc'=>$bdc!==''?$bdc:$term,'numeric'=>ctype_digit($term)?(int)$term:0,'exact'=>$term,'preferred'=>$role]);
    return $stmt->fetch()?:null;
}

function specialDeskCreate(PDO $pdo,string $name,string $role,string $reason):array{
    if($reason==='')throw new RuntimeException('Enter a reason before creating a new BDC competitor.');
    $normalised=strtolower(trim((string)preg_replace('/\s+/',' ',$name)));
    $same=$pdo->prepare('SELECT bdc_id,exact_name FROM bdc_competitors WHERE normalised_name=:name LIMIT 1');
    $same->execute(['name'=>$normalised]);
    if($existing=$same->fetch())throw new RuntimeException('A BDC record already exists: '.$existing['exact_name'].' ('.$existing['bdc_id'].').');

    $pdo->beginTransaction();
    try{
        $next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(bdc_id,5) AS UNSIGNED)),0)+1 FROM bdc_competitors WHERE bdc_id LIKE 'BDC-%'")->fetchColumn();
        $bdcId='BDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);
        $stmt=$pdo->prepare("INSERT INTO bdc_competitors(bdc_id,exact_name,normalised_name,dance_role,current_division,status,is_historical,admin_notes) VALUES(:bdc,:name,:normalised,:role,'novice','pending',0,:notes)");
        $stmt->execute(['bdc'=>$bdcId,'name'=>$name,'normalised'=>$normalised,'role'=>$role,'notes'=>'Special-category Registration Desk provisional entry: '.$reason]);
        $id=(int)$pdo->lastInsertId();
        $pdo->commit();
        return ['id'=>$id,'bdc_id'=>$bdcId,'exact_name'=>$name,'dance_role'=>$role,'current_division'=>'novice','status'=>'pending'];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

$isAjax=str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json')||($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest';

if(isset($_GET['competitor_search'])){
    $term=trim((string)$_GET['competitor_search']);
    $role=(string)($_GET['role']??'');
    if(!in_array($role,['leader','follower'],true)||mb_strlen($term)<2)specialDeskRespond(['ok'=>true,'competitors'=>[]]);
    $stmt=$pdo->prepare("SELECT id,bdc_id,exact_name,dance_role,current_division,status FROM bdc_competitors WHERE status<>'archived' AND dance_role IN(:role,'both') AND (bdc_id LIKE :prefix OR exact_name LIKE :contains) ORDER BY CASE WHEN dance_role=:preferred THEN 0 ELSE 1 END,exact_name LIMIT 20");
    $stmt->execute(['role'=>$role,'prefix'=>$term.'%','contains'=>'%'.$term.'%','preferred'=>$role]);
    $matches=[];
    foreach($stmt->fetchAll() as $competitor){
        $bucket=SpecialCategoryService::pointDivision($pdo,(int)$competitor['id'],$role);
        $matches[]=[
            'bdc_id'=>$competitor['bdc_id'],
            'name'=>$competitor['exact_name'],
            'role'=>$competitor['dance_role'],
            'eligible'=>true,
            'reason'=>SpecialCategoryService::entryEligibility($category)['reason'].' Points will currently record under '.ucfirst($bucket).'.',
        ];
    }
    specialDeskRespond(['ok'=>true,'competitors'=>$matches]);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token. Refresh the desk and try again.');
        $action=(string)($_POST['action']??'');
        $entryId=(int)($_POST['entry_id']??0);

        if($action==='add_entry'){
            $role=(string)($_POST['dance_role']??'');
            $term=trim((string)($_POST['competitor_search']??''));
            $bib=(int)($_POST['bib']??0);
            $create=($_POST['entry_mode']??'existing')==='create';
            $reason=trim((string)($_POST['override_reason']??''));
            if(!in_array($role,['leader','follower'],true)||$term===''||$bib<1)throw new RuntimeException('Choose a role, competitor and valid bib number.');

            $dup=$pdo->prepare("SELECT display_name FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND entry_status='active' LIMIT 1");
            $dup->execute(['round'=>$roundId,'role'=>$role,'bib'=>$bib]);
            if($name=$dup->fetchColumn())throw new RuntimeException('Bib '.$bib.' is already assigned to '.$name.'.');

            $competitor=specialDeskFind($pdo,$term,$role);
            if($competitor){
                if($create)throw new RuntimeException('This dancer already has a BDC record. Select '.$competitor['bdc_id'].' instead.');
            }else{
                if(!$create)throw new RuntimeException('Competitor not found. Use the new-competitor option only when no BDC record exists.');
                $competitor=specialDeskCreate($pdo,$term,$role,$reason);
            }

            $pdo->prepare("INSERT INTO bdc_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name,desk_checked_in,desk_updated_at) VALUES(:round,:competitor,:role,:bib,:name,1,NOW()) ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number),display_name=VALUES(display_name),entry_status='active',desk_checked_in=1,desk_updated_at=NOW()")
                ->execute(['round'=>$roundId,'competitor'=>$competitor['id'],'role'=>$role,'bib'=>$bib,'name'=>$competitor['exact_name']]);
            specialDeskLog($pdo,$desk,'special_competitor_added',$competitor,[
                'role'=>$role,
                'bib'=>$bib,
                'bdc_id'=>$competitor['bdc_id'],
                'category'=>$category,
                'provisional'=>$create,
                'point_division'=>SpecialCategoryService::pointDivision($pdo,(int)$competitor['id'],$role),
            ]);
        }else{
            $entryStmt=$pdo->prepare('SELECT * FROM bdc_scoring_entries WHERE id=:id AND round_id=:round');
            $entryStmt->execute(['id'=>$entryId,'round'=>$roundId]);
            $entry=$entryStmt->fetch();
            if(!$entry)throw new RuntimeException('Entry not found in this round.');

            if($action==='update_entry'){
                $bib=trim((string)($_POST['bib']??''));
                $bibNumber=$bib===''?null:(int)$bib;
                if($bibNumber!==null&&$bibNumber<1)throw new RuntimeException('Bib must be blank or at least 1.');
                if($bibNumber!==null){
                    $dup=$pdo->prepare("SELECT display_name FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND id<>:id AND entry_status='active' LIMIT 1");
                    $dup->execute(['round'=>$roundId,'role'=>$entry['dance_role'],'bib'=>$bibNumber,'id'=>$entryId]);
                    if($name=$dup->fetchColumn())throw new RuntimeException('Bib '.$bibNumber.' is already assigned to '.$name.'.');
                }
                $checked=($_POST['checked']??'0')==='1'?1:0;
                $ready=($_POST['ready']??'0')==='1'?1:0;
                $status=(string)($_POST['status']??'active');
                if(!in_array($status,['active','withdrawn'],true))throw new RuntimeException('Invalid attendance status.');
                if($status==='withdrawn')$ready=0;
                $pdo->prepare('UPDATE bdc_scoring_entries SET bib_number=:bib,entry_status=:status,desk_checked_in=:checked,desk_ready=:ready,desk_updated_at=NOW() WHERE id=:id AND round_id=:round')
                    ->execute(['bib'=>$bibNumber,'status'=>$status,'checked'=>$checked,'ready'=>$ready,'id'=>$entryId,'round'=>$roundId]);
                specialDeskLog($pdo,$desk,'special_entry_updated',$entry,['bib'=>$bibNumber,'status'=>$status,'checked'=>$checked,'ready'=>$ready]);
            }elseif($action==='restore_entry'){
                $pdo->prepare("UPDATE bdc_scoring_entries SET entry_status='active',desk_updated_at=NOW() WHERE id=:id AND round_id=:round")->execute(['id'=>$entryId,'round'=>$roundId]);
                specialDeskLog($pdo,$desk,'special_entry_restored',$entry);
            }else{
                throw new RuntimeException('Invalid Registration Desk action.');
            }
        }
        if($isAjax)specialDeskRespond(['ok'=>true,'entries'=>specialDeskEntries($pdo,$roundId)]);
    }catch(Throwable $e){
        if($isAjax)specialDeskRespond(['ok'=>false,'error'=>$e->getMessage()],422);
        $error=$e->getMessage();
    }
}

if(isset($_GET['live'])){
    $since=trim((string)($_GET['since']??''));
    $entries=specialDeskEntries($pdo,$roundId);
    $latest='';foreach($entries as $entry)$latest=max($latest,(string)($entry['desk_updated_at']?:$entry['updated_at']));
    specialDeskRespond(['ok'=>true,'changed'=>$since===''||$latest>$since,'latest'=>$latest,'entries'=>$since===''||$latest>$since?$entries:[]]);
}

$entries=specialDeskEntries($pdo,$roundId);
$csrf=Csrf::token();
$label=SpecialCategoryService::label($category);
$schedule=[];foreach(SpecialCategoryService::schedule($category) as $rank=>$points)$schedule[]=$rank.'='.number_format((float)$points,0);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BDC Special Registration Desk</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f3f4f6}.desk-head{background:#111827;color:#fff}.entry-ready{background:#dcfce7}.entry-withdrawn{opacity:.55}.sticky-tools{position:sticky;top:0;z-index:10;background:#f3f4f6;padding:.5rem 0}</style>
</head>
<body>
<header class="desk-head py-3"><div class="container"><h1 class="h4 mb-1">BDC Special Category Registration Desk</h1><div><?=e($desk['event_name'])?> · <?=e($label)?> · <?=e(ucfirst((string)$round['round_type']))?></div></div></header>
<main class="container py-3">
<div class="alert alert-info"><strong><?=e($label)?> fixed points:</strong> <?=e(implode(' · ',$schedule))?>. New competitors receive the normal next BDC ID and begin in the Novice progression bucket. Existing competitors keep their existing BDC ID.</div>
<?php if(isset($error)&&$error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div id="notice"></div>
<div class="card shadow-sm"><div class="card-body"><h2 class="h5">Add competitor and assign bib</h2><p class="small text-muted">Search the BDC database first. Special categories do not use participant-count point tiers.</p>
<form method="post" class="row g-2">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="add_entry"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="round_id" value="<?=$roundId?>">
<div class="col-md-2"><label class="form-label">Role</label><select class="form-select" name="dance_role"><option value="leader">Leader</option><option value="follower">Follower</option></select></div>
<div class="col-md-2"><label class="form-label">Bib</label><input class="form-control" type="number" min="1" name="bib" required></div>
<div class="col-md-5"><label class="form-label">BDC competitor</label><input class="form-control" name="competitor_search" placeholder="Name or BDC ID" required></div>
<div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100" name="entry_mode" value="existing">Add Existing BDC Competitor</button></div>
<div class="col-12 mt-3"><details><summary class="fw-semibold">Competitor is genuinely new to BDC</summary><div class="border rounded p-3 mt-2"><p class="small text-muted">The normal BDC ID sequence is used. This does not create a special-category ID.</p><input class="form-control mb-2" name="override_reason" maxlength="255" placeholder="Reason new BDC record is required"><button class="btn btn-outline-dark" name="entry_mode" value="create">Create New BDC ID &amp; Add</button></div></details></div>
</form></div></div>
<div class="card shadow-sm mt-3"><div class="card-body"><h2 class="h5">Current Entries</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Role</th><th>Bib</th><th>BDC ID</th><th>Name</th><th>Status</th></tr></thead><tbody><?php if(!$entries):?><tr><td colspan="5" class="text-muted">No competitors added yet.</td></tr><?php else:foreach($entries as $entry):?><tr class="<?=$entry['entry_status']==='withdrawn'?'entry-withdrawn':''?>"><td><?=e(ucfirst((string)$entry['dance_role']))?></td><td><?=e((string)$entry['bib_number'])?></td><td><?=e((string)$entry['bdc_id'])?></td><td><?=e($entry['display_name'])?></td><td><?=e(ucfirst((string)$entry['entry_status']))?></td></tr><?php endforeach;endif;?></tbody></table></div></div></div>
</main>
</body>
</html>
