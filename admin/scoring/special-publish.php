<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\ResultStorageService;
use App\Services\SpecialCategoryService;

Auth::requireAdmin();
$pdo=Database::connection();
SpecialCategoryService::ensureSchema($pdo);
$userId=(int)(Auth::user()['id']??0);
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
$error='';$notice='';

function specialPublishIsSuperAdmin():bool{
    try{if(method_exists(Auth::class,'isSuperAdmin')&&Auth::isSuperAdmin())return true;}catch(Throwable $e){}
    $user=Auth::user();
    $role=strtolower(str_replace(['-',' '],'_',trim((string)($user['role']??$user['user_role']??''))));
    return in_array($role,['super_admin','superadmin','owner','root','system_admin'],true)||!empty($user['is_super_admin']);
}

function specialPublishRound(PDO $pdo,int $roundId):array{
    $stmt=$pdo->prepare("SELECT r.*,e.name event_name,e.event_date,e.venue FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:round AND r.round_type='final' LIMIT 1");
    $stmt->execute(['round'=>$roundId]);
    $round=$stmt->fetch();
    if(!$round||!SpecialCategoryService::isSpecial((string)$round['division']))throw new RuntimeException('Special-category Final round not found.');
    return $round;
}

function specialPublishOrdinal(int $rank):string{
    if($rank%100>=11&&$rank%100<=13)return $rank.'th';
    return $rank.([1=>'st',2=>'nd',3=>'rd'][$rank%10]??'th');
}

function specialPublishPairs(PDO $pdo,array $round):array{
    $stmt=$pdo->prepare("SELECT fp.id pair_id,fp.pair_number,fr.final_rank,
      le.competitor_id leader_competitor_id,le.display_name leader_name,le.bib_number leader_bib,lc.bdc_id leader_bdc,
      fe.competitor_id follower_competitor_id,fe.display_name follower_name,fe.bib_number follower_bib,fc.bdc_id follower_bdc
      FROM bdc_scoring_final_pairs fp
      JOIN bdc_scoring_final_results fr ON fr.round_id=fp.round_id AND fr.pair_id=fp.id
      JOIN bdc_scoring_entries le ON le.id=fp.leader_entry_id
      JOIN bdc_scoring_entries fe ON fe.id=fp.follower_entry_id
      LEFT JOIN bdc_competitors lc ON lc.id=le.competitor_id
      LEFT JOIN bdc_competitors fc ON fc.id=fe.competitor_id
      WHERE fp.round_id=:round AND fp.pairing_status='confirmed'
      ORDER BY fr.final_rank,fp.pair_number");
    $stmt->execute(['round'=>$round['id']]);
    $pairs=$stmt->fetchAll();
    if(!$pairs)throw new RuntimeException('Calculate and submit Final rankings before publication.');
    foreach($pairs as &$pair){
        $pair['points']=SpecialCategoryService::fixedPoints((string)$round['division'],(int)$pair['final_rank']);
        $pair['leader_point_division']=SpecialCategoryService::pointDivision($pdo,(int)$pair['leader_competitor_id'],'leader');
        $pair['follower_point_division']=SpecialCategoryService::pointDivision($pdo,(int)$pair['follower_competitor_id'],'follower');
    }
    unset($pair);
    return $pairs;
}

function specialPublishRecord(PDO $pdo,int $roundId):?array{
    $stmt=$pdo->prepare('SELECT * FROM bdc_scoring_publications WHERE final_round_id=:round LIMIT 1');
    $stmt->execute(['round'=>$roundId]);
    return $stmt->fetch()?:null;
}

function specialPublishAudit(PDO $pdo,int $roundId,int $userId,string $action,array $details=[]):void{
    $stmt=$pdo->prepare('INSERT INTO bdc_scoring_audit(round_id,user_id,action,details_json) VALUES(:round,:user,:action,:details)');
    $stmt->execute(['round'=>$roundId,'user'=>$userId?:null,'action'=>$action,'details'=>json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
}

function specialPublishSafe(string $value):string{
    $value=preg_replace('/[^A-Za-z0-9_-]+/','-',trim($value))??'';
    return trim($value,'-')?:'result';
}

function specialPublishWrite(string $filename,string $html):array{
    $root=ResultStorageService::root();
    if(!is_dir($root)&&!mkdir($root,0755,true)&&!is_dir($root))throw new RuntimeException('Could not create result storage.');
    $path=$root.'/'.$filename;
    if(file_put_contents($path,$html)===false)throw new RuntimeException('Could not write result archive.');
    @chmod($path,0644);
    return [
        'absolute_path'=>$path,
        'storage_path'=>ResultStorageService::relative($filename),
        'url'=>ResultStorageService::publicUrl($filename),
    ];
}

function specialPublishHtml(string $title,string $subtitle,string $body):string{
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</title><style>body{font-family:Arial,sans-serif;color:#171717;margin:32px}h1{margin-bottom:4px}.sub{color:#666;margin-bottom:24px}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #d7d7d7;padding:9px;text-align:left}th{background:#171717;color:#fff}.note{margin-top:20px;padding:14px;border-left:5px solid #c8102e;background:#fff4f5}.small{font-size:12px;color:#666}</style></head><body><h1>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</h1><div class="sub">'.htmlspecialchars($subtitle,ENT_QUOTES,'UTF-8').'</div>'.$body.'<div class="small" style="margin-top:24px">Bachata Dance Council · Special Category Scoring</div></body></html>';
}

function specialPublishArchives(PDO $pdo,array $round,array $pairs):array{
    $label=SpecialCategoryService::label((string)$round['division']);
    $date=(string)($round['event_date']?:date('Y-m-d'));
    $base=specialPublishSafe((string)$round['event_name']).'-'.specialPublishSafe($label).'-'.$date;

    $heatsStmt=$pdo->prepare("SELECT r.id FROM bdc_scoring_rounds r WHERE r.event_id=:event AND r.division=:category AND r.round_type='heats' ORDER BY r.id ASC LIMIT 1");
    $heatsStmt->execute(['event'=>$round['event_id'],'category'=>$round['division']]);
    $heatsId=(int)$heatsStmt->fetchColumn();
    $heatsRows=[];
    if($heatsId>0){
        $stmt=$pdo->prepare("SELECT se.dance_role,se.bib_number,se.display_name,c.bdc_id,sr.rank_number,sr.result_status FROM bdc_scoring_entries se LEFT JOIN bdc_competitors c ON c.id=se.competitor_id LEFT JOIN bdc_scoring_results sr ON sr.round_id=se.round_id AND sr.entry_id=se.id WHERE se.round_id=:round AND se.entry_status='active' ORDER BY se.dance_role,sr.rank_number,se.bib_number");
        $stmt->execute(['round'=>$heatsId]);$heatsRows=$stmt->fetchAll();
    }
    $heatsBody='<table><thead><tr><th>Role</th><th>Bib</th><th>BDC ID</th><th>Competitor</th><th>Rank</th><th>Status</th></tr></thead><tbody>';
    if(!$heatsRows)$heatsBody.='<tr><td colspan="6">Direct-to-Final format. No Heats round was used.</td></tr>';
    foreach($heatsRows as $row)$heatsBody.='<tr><td>'.htmlspecialchars(ucfirst((string)$row['dance_role'])).'</td><td>'.(int)$row['bib_number'].'</td><td>'.htmlspecialchars((string)$row['bdc_id']).'</td><td>'.htmlspecialchars((string)$row['display_name']).'</td><td>'.htmlspecialchars((string)($row['rank_number']??'')).'</td><td>'.htmlspecialchars(ucwords(str_replace('_',' ',(string)($row['result_status']??'')))).'</td></tr>';
    $heatsBody.='</tbody></table>';

    $finalBody='<table><thead><tr><th>Place</th><th>Leader</th><th>Follower</th></tr></thead><tbody>';
    foreach($pairs as $pair)$finalBody.='<tr><td>'.htmlspecialchars(specialPublishOrdinal((int)$pair['final_rank'])).'</td><td>'.htmlspecialchars($pair['leader_name'].' ('.$pair['leader_bdc'].')').'</td><td>'.htmlspecialchars($pair['follower_name'].' ('.$pair['follower_bdc'].')').'</td></tr>';
    $finalBody.='</tbody></table>';

    $pointsBody='<div class="note"><strong>Fixed special-category points.</strong> Participant-count BDC point tiers do not apply. Points are recorded under each dancer’s current role-specific BDC progression division.</div><table><thead><tr><th>Place</th><th>Competitor</th><th>Role</th><th>Points</th><th>Recorded Under</th></tr></thead><tbody>';
    foreach($pairs as $pair){
        foreach([
            ['name'=>$pair['leader_name'],'bdc'=>$pair['leader_bdc'],'role'=>'Leader','bucket'=>$pair['leader_point_division']],
            ['name'=>$pair['follower_name'],'bdc'=>$pair['follower_bdc'],'role'=>'Follower','bucket'=>$pair['follower_point_division']],
        ] as $person){
            $pointsBody.='<tr><td>'.htmlspecialchars(specialPublishOrdinal((int)$pair['final_rank'])).'</td><td>'.htmlspecialchars($person['name'].' ('.$person['bdc'].')').'</td><td>'.$person['role'].'</td><td>'.htmlspecialchars((string)(float)$pair['points']).'</td><td>'.htmlspecialchars(ucfirst((string)$person['bucket'])).'</td></tr>';
        }
    }
    $pointsBody.='</tbody></table>';

    return [
        'heats'=>specialPublishWrite($base.'-Heats.html',specialPublishHtml($round['event_name'].' — '.$label.' Heats',$date,$heatsBody)),
        'finals'=>specialPublishWrite($base.'-Final.html',specialPublishHtml($round['event_name'].' — '.$label.' Final',$date,$finalBody)),
        'points'=>specialPublishWrite($base.'-Points.html',specialPublishHtml($round['event_name'].' — '.$label.' Points',$date,$pointsBody)),
    ];
}

$isSuperAdmin=specialPublishIsSuperAdmin();

try{
    $round=specialPublishRound($pdo,$roundId);
    $pairs=specialPublishPairs($pdo,$round);
    $publication=specialPublishRecord($pdo,$roundId);

    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
        $action=(string)($_POST['action']??'');

        if($action==='submit_for_approval'){
            if($round['status']!=='scores_submitted')throw new RuntimeException('Final scores must be submitted before requesting approval.');
            if($publication&&$publication['status']==='published')throw new RuntimeException('This competition is already published.');
            $pdo->beginTransaction();
            try{
                if($publication){
                    $pdo->prepare("UPDATE bdc_scoring_publications SET points_tier='1',status='pending_approval',submitted_by=:user,submitted_at=NOW(),rejected_by=NULL,rejected_at=NULL,rejection_reason=NULL,updated_at=NOW() WHERE id=:id")
                        ->execute(['user'=>$userId?:null,'id'=>$publication['id']]);
                    $publicationId=(int)$publication['id'];
                }else{
                    $pdo->prepare("INSERT INTO bdc_scoring_publications(event_id,final_round_id,division,points_tier,status,submitted_by,submitted_at,published_at) VALUES(:event,:round,:category,'1','pending_approval',:user,NOW(),NULL)")
                        ->execute(['event'=>$round['event_id'],'round'=>$roundId,'category'=>$round['division'],'user'=>$userId?:null]);
                    $publicationId=(int)$pdo->lastInsertId();
                }
                $pdo->prepare("UPDATE bdc_scoring_rounds SET status='pending_approval',publication_id=:publication,locked_at=NOW(),locked_by=:user WHERE id=:round")
                    ->execute(['publication'=>$publicationId,'user'=>$userId?:null,'round'=>$roundId]);
                specialPublishAudit($pdo,$roundId,$userId,'special_competition_submitted_for_approval',['publication_id'=>$publicationId,'category'=>$round['division'],'point_rule'=>'fixed']);
                $pdo->commit();
                $notice='Special-category competition submitted for Super Admin approval. No points have been added yet.';
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        }

        if($action==='approve_publication'){
            if(!$isSuperAdmin)throw new RuntimeException('Only Super Admin can approve publication.');
            $publication=specialPublishRecord($pdo,$roundId);
            if(!$publication||$publication['status']!=='pending_approval')throw new RuntimeException('No pending approval was found.');
            if($round['status']!=='pending_approval')throw new RuntimeException('The Final round is not awaiting approval.');

            $archives=specialPublishArchives($pdo,$round,$pairs);
            $pdo->beginTransaction();
            try{
                $transactionStmt=$pdo->prepare("INSERT INTO bdc_point_transactions(competitor_id,event_id,division,dance_role,points,placement,notes,source_type,source_row_hash,created_by) VALUES(:competitor,:event,:division,:role,:points,:placement,:notes,'scoring_engine',:hash,:user)");
                $resultStmt=$pdo->prepare("INSERT INTO bdc_participant_results(event_id,competitor_id,division,dance_role,placement,finalist_status,partner_name,points_awarded,source,point_transaction_id) VALUES(:event,:competitor,:category,:role,:placement,:status,:partner,:points,'scoring_engine',:transaction)");
                $linkStmt=$pdo->prepare("INSERT INTO bdc_scoring_publication_points(publication_id,pair_id,competitor_id,dance_role,final_rank,points_awarded,point_transaction_id,participant_result_id) VALUES(:publication,:pair,:competitor,:role,:rank,:points,:transaction,:result)");

                foreach($pairs as $pair){
                    foreach([
                        ['role'=>'leader','id'=>(int)$pair['leader_competitor_id'],'name'=>$pair['leader_name'],'partner'=>$pair['follower_name'],'bucket'=>$pair['leader_point_division']],
                        ['role'=>'follower','id'=>(int)$pair['follower_competitor_id'],'name'=>$pair['follower_name'],'partner'=>$pair['leader_name'],'bucket'=>$pair['follower_point_division']],
                    ] as $person){
                        if($person['id']<1)throw new RuntimeException('Every finalist must be linked to a BDC competitor before approval.');
                        $rank=(int)$pair['final_rank'];
                        $points=(float)$pair['points'];
                        $placement=specialPublishOrdinal($rank);
                        $hash=hash('sha256','special-scoring-publication|'.$publication['id'].'|'.$person['role'].'|'.$person['id']);
                        $transactionStmt->execute([
                            'competitor'=>$person['id'],'event'=>$round['event_id'],'division'=>$person['bucket'],'role'=>$person['role'],'points'=>$points,'placement'=>$placement,
                            'notes'=>SpecialCategoryService::label((string)$round['division']).' scoring publication #'.$publication['id'],
                            'hash'=>$hash,'user'=>$userId?:null,
                        ]);
                        $transactionId=(int)$pdo->lastInsertId();
                        $finalistStatus=$rank===1?'winner':($points>0?'placed':'finalist');
                        $resultStmt->execute([
                            'event'=>$round['event_id'],'competitor'=>$person['id'],'category'=>$round['division'],'role'=>$person['role'],'placement'=>$placement,'status'=>$finalistStatus,'partner'=>$person['partner'],'points'=>$points,'transaction'=>$transactionId,
                        ]);
                        $resultId=(int)$pdo->lastInsertId();
                        $linkStmt->execute([
                            'publication'=>$publication['id'],'pair'=>$pair['pair_id'],'competitor'=>$person['id'],'role'=>$person['role'],'rank'=>$rank,'points'=>$points,'transaction'=>$transactionId,'result'=>$resultId,
                        ]);
                    }
                }

                $docStmt=$pdo->prepare("INSERT INTO bdc_result_documents(event_id,title,document_category,file_type,url,storage_path,status,source,created_by) VALUES(:event,:title,:category,'external',:url,:storage,'published','scoring_engine',:user)");
                $mapStmt=$pdo->prepare("INSERT INTO bdc_scoring_publication_documents(publication_id,document_category,repository_document_id,storage_path) VALUES(:publication,:category,:document,:storage)");
                $docIds=[];
                foreach([
                    'heats'=>'HEATS RESULTS — '.$round['event_name'].' ('.SpecialCategoryService::label((string)$round['division']).')',
                    'finals'=>'FINAL RESULTS — '.$round['event_name'].' ('.SpecialCategoryService::label((string)$round['division']).')',
                    'points'=>'FINAL RANKING & POINTS — '.$round['event_name'].' ('.SpecialCategoryService::label((string)$round['division']).')',
                ] as $type=>$title){
                    $docStmt->execute(['event'=>$round['event_id'],'title'=>$title,'category'=>$type,'url'=>$archives[$type]['url'],'storage'=>$archives[$type]['storage_path'],'user'=>$userId?:null]);
                    $docIds[$type]=(int)$pdo->lastInsertId();
                    $mapStmt->execute(['publication'=>$publication['id'],'category'=>$type,'document'=>$docIds[$type],'storage'=>$archives[$type]['storage_path']]);
                }

                $pdo->prepare("UPDATE bdc_scoring_publications SET status='published',repository_document_id=:document,report_url=:report,published_by=:user,approved_by=:user2,approved_at=NOW(),published_at=NOW(),updated_at=NOW() WHERE id=:publication")
                    ->execute(['document'=>$docIds['finals'],'report'=>$archives['points']['url'],'user'=>$userId?:null,'user2'=>$userId?:null,'publication'=>$publication['id']]);
                $pdo->prepare("UPDATE bdc_scoring_rounds SET status='archived',published_document_id=:document,locked_at=NOW(),locked_by=:user WHERE id=:round")
                    ->execute(['document'=>$docIds['finals'],'user'=>$userId?:null,'round'=>$roundId]);
                $pdo->prepare("UPDATE bdc_events SET status='completed',updated_at=NOW() WHERE id=:event")->execute(['event'=>$round['event_id']]);
                specialPublishAudit($pdo,$roundId,$userId,'special_competition_approved_and_published',['publication_id'=>$publication['id'],'category'=>$round['division'],'point_rule'=>'fixed','schedule'=>SpecialCategoryService::schedule((string)$round['division'])]);
                $pdo->commit();
                $notice='Approved. Fixed special-category points were recorded under each competitor’s BDC progression division and the result was published.';
            }catch(Throwable $e){
                if($pdo->inTransaction())$pdo->rollBack();
                foreach($archives??[] as $archive)if(!empty($archive['absolute_path'])&&is_file($archive['absolute_path']))@unlink($archive['absolute_path']);
                throw $e;
            }
        }

        if($action==='reject_approval'){
            if(!$isSuperAdmin)throw new RuntimeException('Only Super Admin can reject an approval request.');
            $reason=trim((string)($_POST['rejection_reason']??''));if($reason==='')throw new RuntimeException('Rejection reason is required.');
            $publication=specialPublishRecord($pdo,$roundId);if(!$publication||$publication['status']!=='pending_approval')throw new RuntimeException('No pending approval was found.');
            $pdo->beginTransaction();
            try{
                $pdo->prepare("UPDATE bdc_scoring_publications SET status='rejected',rejected_by=:user,rejected_at=NOW(),rejection_reason=:reason,updated_at=NOW() WHERE id=:publication")
                    ->execute(['user'=>$userId?:null,'reason'=>$reason,'publication'=>$publication['id']]);
                $pdo->prepare("UPDATE bdc_scoring_rounds SET status='scores_submitted',locked_at=NULL,locked_by=NULL WHERE id=:round")->execute(['round'=>$roundId]);
                specialPublishAudit($pdo,$roundId,$userId,'special_approval_rejected',['publication_id'=>$publication['id'],'reason'=>$reason]);
                $pdo->commit();$notice='Approval rejected. Final scoring is open for correction.';
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        }

        if($action==='rollback'){
            if(!$isSuperAdmin)throw new RuntimeException('Only Super Admin can roll back a published competition.');
            $reason=trim((string)($_POST['rollback_reason']??''));if($reason==='')throw new RuntimeException('Rollback reason is required.');
            $publication=specialPublishRecord($pdo,$roundId);if(!$publication||$publication['status']!=='published')throw new RuntimeException('Published competition not found.');
            $linksStmt=$pdo->prepare('SELECT * FROM bdc_scoring_publication_points WHERE publication_id=:publication');$linksStmt->execute(['publication'=>$publication['id']]);$links=$linksStmt->fetchAll();
            $docsStmt=$pdo->prepare('SELECT repository_document_id,storage_path FROM bdc_scoring_publication_documents WHERE publication_id=:publication');$docsStmt->execute(['publication'=>$publication['id']]);$docs=$docsStmt->fetchAll();
            $pdo->beginTransaction();
            try{
                foreach($links as $link){
                    if(!empty($link['participant_result_id']))$pdo->prepare('DELETE FROM bdc_participant_results WHERE id=:id')->execute(['id'=>$link['participant_result_id']]);
                    if(!empty($link['point_transaction_id']))$pdo->prepare('DELETE FROM bdc_point_transactions WHERE id=:id')->execute(['id'=>$link['point_transaction_id']]);
                }
                $pdo->prepare('DELETE FROM bdc_scoring_publication_points WHERE publication_id=:publication')->execute(['publication'=>$publication['id']]);
                foreach($docs as $doc)$pdo->prepare('DELETE FROM bdc_result_documents WHERE id=:id')->execute(['id'=>$doc['repository_document_id']]);
                $pdo->prepare('DELETE FROM bdc_scoring_publication_documents WHERE publication_id=:publication')->execute(['publication'=>$publication['id']]);
                $pdo->prepare("UPDATE bdc_scoring_publications SET status='rolled_back',repository_document_id=NULL,report_url=NULL,rolled_back_by=:user,rolled_back_at=NOW(),rollback_reason=:reason,updated_at=NOW() WHERE id=:publication")
                    ->execute(['user'=>$userId?:null,'reason'=>$reason,'publication'=>$publication['id']]);
                $pdo->prepare("UPDATE bdc_scoring_rounds SET status='scores_submitted',publication_id=NULL,published_document_id=NULL,locked_at=NULL,locked_by=NULL WHERE id=:round")->execute(['round'=>$roundId]);
                specialPublishAudit($pdo,$roundId,$userId,'special_publication_rolled_back',['publication_id'=>$publication['id'],'reason'=>$reason]);
                $pdo->commit();
                foreach($docs as $doc){$path=ResultStorageService::resolve((string)($doc['storage_path']??''));if($path&&is_file($path))@unlink($path);}
                $notice='Rollback completed. Special-category points, participant results and repository files were removed.';
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        }

        $round=specialPublishRound($pdo,$roundId);$pairs=specialPublishPairs($pdo,$round);$publication=specialPublishRecord($pdo,$roundId);
    }
}catch(Throwable $e){$error=$e->getMessage();}

$csrf=Csrf::token();
$label=isset($round)?SpecialCategoryService::label((string)$round['division']):'Special Category';
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Special Category Publication | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=274" rel="stylesheet"></head>
<body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="?mode=special">BDC Special Scoring</a><a class="btn btn-outline-light btn-sm" href="index.php?mode=special&amp;round_id=<?=$roundId?>">Back to Final</a></div></nav>
<main class="container py-4" style="max-width:1200px">
<div class="mb-4"><div class="text-uppercase text-primary fw-bold small">Fixed Special Category Points</div><h1 class="h2"><?=e((string)($round['event_name']??''))?></h1><p class="text-muted"><?=e($label)?> · Final Publication Review</p></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?>
<?php if(isset($round,$pairs)):?>
<div class="alert alert-info"><strong>No participant-count point tier applies.</strong> <?=e($label)?> uses fixed points. Each dancer’s award is written to their role-specific BDC Novice, Intermediate or Advanced points bucket. Existing BDC IDs remain unchanged.</div>
<div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5">Point Preview</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Place</th><th>Leader</th><th>Leader Bucket</th><th>Follower</th><th>Follower Bucket</th><th>Fixed Points</th></tr></thead><tbody><?php foreach($pairs as $pair):?><tr><td><?=e(specialPublishOrdinal((int)$pair['final_rank']))?></td><td><?=e($pair['leader_name'].' · '.$pair['leader_bdc'])?></td><td><?=e(ucfirst((string)$pair['leader_point_division']))?></td><td><?=e($pair['follower_name'].' · '.$pair['follower_bdc'])?></td><td><?=e(ucfirst((string)$pair['follower_point_division']))?></td><td><strong><?=e((string)(float)$pair['points'])?></strong></td></tr><?php endforeach;?></tbody></table></div></div></div>
<div class="card shadow-sm"><div class="card-body"><h2 class="h5">Publication Status</h2><p>Status: <strong><?=e(ucwords(str_replace('_',' ',(string)($publication['status']??'not submitted'))))?></strong></p>
<?php if(!$publication||in_array($publication['status'],['rejected','rolled_back'],true)):?><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><button class="btn btn-primary" name="action" value="submit_for_approval">Submit for Super Admin Approval</button></form><?php endif;?>
<?php if($publication&&$publication['status']==='pending_approval'&&$isSuperAdmin):?><div class="d-flex flex-wrap gap-2"><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><button class="btn btn-success" name="action" value="approve_publication" onclick="return confirm('Approve and publish fixed special-category points?')">Approve &amp; Publish</button></form><form method="post" class="d-flex gap-2"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input class="form-control" name="rejection_reason" placeholder="Rejection reason" required><button class="btn btn-outline-danger" name="action" value="reject_approval">Reject</button></form></div><?php endif;?>
<?php if($publication&&$publication['status']==='published'&&$isSuperAdmin):?><form method="post" class="d-flex gap-2"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input class="form-control" name="rollback_reason" placeholder="Rollback reason" required><button class="btn btn-danger" name="action" value="rollback" onclick="return confirm('Rollback this published special-category result and remove its points?')">Rollback Publication</button></form><?php endif;?>
</div></div>
<?php endif;?>
</main></body></html>
