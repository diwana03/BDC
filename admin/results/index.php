<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;

if(!Auth::check()){header('Location: ../');exit;}

$pdo=Database::connection();
SchemaUpdater::run($pdo);

// v2.0.40: repair permissions on HTML archives created by earlier builds.
$publicResultRoot=dirname(__DIR__,2).'/public/results';
if(is_dir($publicResultRoot)){
    @chmod($publicResultRoot,0755);
    foreach(glob($publicResultRoot.'/*.html')?:[] as $publicResultFile){
        if(is_file($publicResultFile)){
            @chmod($publicResultFile,0644);
        }
    }
}

$error='';
$notice='';
$editId=(int)($_GET['edit']??0);
$editDoc=null;

function resultStorageRoot(): string {
    $root=dirname(__DIR__,2).'/storage/results';
    if(!is_dir($root) && !mkdir($root,0755,true) && !is_dir($root)) {
        throw new RuntimeException('Could not create the result storage folder.');
    }
    return $root;
}

function deleteStoredResult(?string $storagePath): void {
    if(!$storagePath) return;
    $base=realpath(dirname(__DIR__,2).'/storage/results');
    $file=realpath(dirname(__DIR__,2).'/'.ltrim($storagePath,'/'));
    if($base && $file && str_starts_with($file,$base.DIRECTORY_SEPARATOR) && is_file($file)) {
        @unlink($file);
    }
}

function processResultUpload(?array $file): ?array {
    if(!$file || ($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException('The replacement file could not be uploaded.');
    $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,['pdf','csv'],true)) throw new RuntimeException('Only PDF or CSV uploads are allowed.');
    if((int)$file['size']>20*1024*1024) throw new RuntimeException('File must be 20 MB or smaller.');
    $safe=date('YmdHis').'-'.bin2hex(random_bytes(5)).'.'.$ext;
    $relative='storage/results/'.$safe;
    $target=resultStorageRoot().'/'.$safe;
    if(!move_uploaded_file((string)$file['tmp_name'],$target)) throw new RuntimeException('Could not store uploaded file.');
    $url=url($relative);
    return ['url'=>$url,'storage_path'=>$relative,'file_type'=>$ext];
}

try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!Csrf::verify($_POST['_csrf']??null)) throw new RuntimeException('Invalid security token. Refresh the page and try again.');
        $action=(string)($_POST['action']??'add');

        if($action==='delete'){
            $id=(int)($_POST['id']??0);
            $stmt=$pdo->prepare('SELECT storage_path FROM bdc_result_documents WHERE id=:id');
            $stmt->execute(['id'=>$id]);
            $existing=$stmt->fetch();
            if(!$existing) throw new RuntimeException('Repository item not found.');
            $pdo->beginTransaction();
            try{
                $stmt=$pdo->prepare('DELETE FROM bdc_result_documents WHERE id=:id');
                $stmt->execute(['id'=>$id]);
                $pdo->commit();
                deleteStoredResult($existing['storage_path']??null);
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            $notice='Repository item deleted.';
            $editId=0;
        } elseif($action==='legacy_upload') {
            Auth::requireSuperAdmin();
            $eventId=(int)($_POST['legacy_event_id']??0);
            $category=(string)($_POST['legacy_category']??'');
            if($eventId<1) throw new RuntimeException('Select an event.');
            if(!in_array($category,['heats','finals','points'],true)) throw new RuntimeException('Select Heats, Final or Points.');
            $eventStmt=$pdo->prepare('SELECT id,name,event_date FROM bdc_events WHERE id=:id');
            $eventStmt->execute(['id'=>$eventId]);
            $event=$eventStmt->fetch();
            if(!$event) throw new RuntimeException('Selected event was not found.');
            $upload=processResultUpload($_FILES['legacy_file']??null);
            if(!$upload) throw new RuntimeException('Choose a PDF or CSV file.');
            $replace=!empty($_POST['replace_existing']);
            $existingStmt=$pdo->prepare("SELECT * FROM bdc_result_documents WHERE event_id=:event AND document_category=:category AND status='published' ORDER BY id DESC LIMIT 1");
            $existingStmt->execute(['event'=>$eventId,'category'=>$category]);
            $existing=$existingStmt->fetch();
            if($existing && !$replace){
                deleteStoredResult($upload['storage_path']);
                throw new RuntimeException(ucfirst($category).' already exists for this event. Select Replace existing file or edit the existing repository item.');
            }
            $title=($category==='finals'?'Final':ucfirst($category)).' — '.$event['name'];
            if($existing){
                $stmt=$pdo->prepare("UPDATE bdc_result_documents SET title=:title,file_type=:type,url=:url,storage_path=:storage,status='published',source='legacy_upload',version_number=version_number+1,updated_at=NOW() WHERE id=:id");
                $stmt->execute(['title'=>$title,'type'=>$upload['file_type'],'url'=>$upload['url'],'storage'=>$upload['storage_path'],'id'=>(int)$existing['id']]);
                if(!empty($existing['storage_path']) && $existing['storage_path']!==$upload['storage_path']) deleteStoredResult($existing['storage_path']);
                $notice='Legacy '.($category==='finals'?'Final':ucfirst($category)).' file replaced and repository link repaired.';
            } else {
                $stmt=$pdo->prepare("INSERT INTO bdc_result_documents(event_id,title,document_category,file_type,url,storage_path,status,source,created_by) VALUES(:event,:title,:category,:type,:url,:storage,'published','legacy_upload',:user)");
                $stmt->execute(['event'=>$eventId,'title'=>$title,'category'=>$category,'type'=>$upload['file_type'],'url'=>$upload['url'],'storage'=>$upload['storage_path'],'user'=>(int)Auth::user()['id']]);
                $notice='Legacy file uploaded and added to the Result Repository.';
            }
        } elseif($action==='add' || $action==='update') {
            $id=(int)($_POST['id']??0);
            $eventId=(int)($_POST['event_id']??0);
            $title=trim((string)($_POST['title']??''));
            $url=trim((string)($_POST['url']??''));
            $category=(string)($_POST['document_category']??'other');
            $fileType=(string)($_POST['file_type']??'external');
            $allowedCategories=['heats','finals','points','full_results','other'];
            $allowedTypes=['pdf','csv','world_result','external'];
            if($title==='') throw new RuntimeException('Title is required.');
            if(!in_array($category,$allowedCategories,true)) $category='other';
            if(!in_array($fileType,$allowedTypes,true)) $fileType='external';

            $existing=null;
            if($action==='update'){
                $stmt=$pdo->prepare('SELECT * FROM bdc_result_documents WHERE id=:id');
                $stmt->execute(['id'=>$id]);
                $existing=$stmt->fetch();
                if(!$existing) throw new RuntimeException('Repository item not found.');
            }

            $upload=processResultUpload($_FILES['result_file']??null);
            $storage=$existing['storage_path']??null;
            $oldStorage=$storage;
            if($upload){
                $url=$upload['url'];
                $storage=$upload['storage_path'];
                $fileType=$upload['file_type'];
            } elseif($action==='update' && $url==='' && !empty($existing['url'])) {
                $url=(string)$existing['url'];
            }

            if($url==='' || !filter_var($url,FILTER_VALIDATE_URL)) {
                if($upload) deleteStoredResult($storage);
                throw new RuntimeException('Enter a valid URL or upload a PDF/CSV file.');
            }

            // Prevent accidental duplicate repository documents for events held on the same date.
            // One Heats, Finals, Points or Full Results document is allowed per event date/category.
            if($eventId>0 && in_array($category,['heats','finals','points','full_results'],true)) {
                $dupSql="SELECT d.id,d.title,e.name,e.event_date
                         FROM bdc_result_documents d
                         INNER JOIN bdc_events e ON e.id=d.event_id
                         INNER JOIN bdc_events selected_event ON selected_event.id=:event
                         WHERE e.event_date=selected_event.event_date
                           AND d.document_category=:category
                           AND d.status='published'
                           AND d.id<>:id
                         ORDER BY d.id DESC LIMIT 1";
                $dup=$pdo->prepare($dupSql);
                $dup->execute(['event'=>$eventId,'category'=>$category,'id'=>$action==='update'?$id:0]);
                $duplicate=$dup->fetch();
                if($duplicate) {
                    if($upload) deleteStoredResult($storage);
                    throw new RuntimeException(
                        'Duplicate check: '.ucwords(str_replace('_',' ',$category)).
                        ' already exists for '.$duplicate['event_date'].' under "'.$duplicate['name'].'" (item #'.$duplicate['id'].'). Edit or replace that item instead.'
                    );
                }
            }

            if($action==='add'){
                $stmt=$pdo->prepare("INSERT INTO bdc_result_documents(event_id,title,document_category,file_type,url,storage_path,status,source,created_by) VALUES(NULLIF(:event,0),:title,:category,:type,:url,:storage,'published','manual_upload',:user)");
                $stmt->execute(['event'=>$eventId,'title'=>$title,'category'=>$category,'type'=>$fileType,'url'=>$url,'storage'=>$storage,'user'=>(int)Auth::user()['id']]);
                $notice='Result repository item published.';
            } else {
                $stmt=$pdo->prepare("UPDATE bdc_result_documents SET event_id=NULLIF(:event,0),title=:title,document_category=:category,file_type=:type,url=:url,storage_path=:storage,status='published',version_number=version_number+1,updated_at=NOW() WHERE id=:id");
                $stmt->execute(['event'=>$eventId,'title'=>$title,'category'=>$category,'type'=>$fileType,'url'=>$url,'storage'=>$storage,'id'=>$id]);
                if($upload && $oldStorage && $oldStorage!==$storage) deleteStoredResult($oldStorage);
                $notice=$upload?'Repository file replaced and item updated.':'Repository item updated.';
                $editId=0;
            }
        }
    }
}catch(Throwable $e){$error=$e->getMessage();}

$events=$pdo->query('SELECT id,name,event_date FROM bdc_events ORDER BY event_date DESC,name')->fetchAll();
if($editId>0){
    $stmt=$pdo->prepare('SELECT * FROM bdc_result_documents WHERE id=:id');
    $stmt->execute(['id'=>$editId]);
    $editDoc=$stmt->fetch()?:null;
}
$search=trim((string)($_GET['q']??''));
$filterEvent=(int)($_GET['filter_event_id']??0);
$filterCategory=(string)($_GET['filter_category']??'');
if(!in_array($filterCategory,['','heats','finals','points','full_results','other'],true)) $filterCategory='';

$where=[];
$params=[];
if($search!=='') {
    $where[]="(d.title LIKE :search OR e.name LIKE :search OR DATE_FORMAT(e.event_date,'%Y-%m-%d') LIKE :search)";
    $params['search']='%'.$search.'%';
}
if($filterEvent>0) {
    $where[]='d.event_id=:filter_event';
    $params['filter_event']=$filterEvent;
}
if($filterCategory!=='') {
    $where[]='d.document_category=:filter_category';
    $params['filter_category']=$filterCategory;
}
$sql='SELECT d.*,e.name event_name,e.event_date FROM bdc_result_documents d LEFT JOIN bdc_events e ON e.id=d.event_id';
if($where) $sql.=' WHERE '.implode(' AND ',$where);
$sql.=' ORDER BY e.event_date DESC,d.id DESC LIMIT 250';
$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$docs=$stmt->fetchAll();

$duplicateDates=$pdo->query("SELECT event_date,COUNT(*) event_count,GROUP_CONCAT(CONCAT(id,': ',name) ORDER BY name SEPARATOR ' | ') events
                            FROM bdc_events
                            WHERE event_date IS NOT NULL
                            GROUP BY event_date
                            HAVING COUNT(*)>1
                            ORDER BY event_date DESC")->fetchAll();
$form=$editDoc?:['id'=>0,'event_id'=>0,'title'=>'','document_category'=>'other','file_type'=>'external','url'=>'','storage_path'=>null];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Result Repository | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">
<nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="../../results/" target="_blank" rel="noopener">Public results</a></div></nav>
<div class="container py-4" style="max-width:1200px">
<?php if(Auth::isSuperAdmin()): ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <h2 class="h5 mb-1">Legacy Missing File Upload</h2>
    <p class="text-muted small">Upload an old Heats, Final or Points PDF/CSV directly to the repository. This does not import or change competitor points.</p>
    <form method="post" enctype="multipart/form-data" class="row g-3">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="legacy_upload">
      <div class="col-md-5"><label class="form-label">Event</label><select class="form-select" name="legacy_event_id" required><option value="">Select event</option><?php foreach($events as $ev): ?><option value="<?= (int)$ev['id'] ?>"><?= e((string)$ev['event_date'].' — '.$ev['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label">Document</label><select class="form-select" name="legacy_category" required><option value="heats">Heats</option><option value="finals">Final</option><option value="points">Points</option></select></div>
      <div class="col-md-5"><label class="form-label">PDF or CSV</label><input class="form-control" type="file" name="legacy_file" accept=".pdf,.csv,application/pdf,text/csv" required></div>
      <div class="col-12 d-flex align-items-center gap-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="replace_existing" value="1" id="legacyReplace"><label class="form-check-label" for="legacyReplace">Replace existing file in this slot</label></div><button class="btn btn-dark">Upload legacy file</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<div class="d-flex justify-content-between mb-4"><div><h1 class="h3">Result Repository</h1><div class="text-muted">Add, edit, replace or delete PDF, CSV and World Result links</div></div><a class="btn btn-outline-secondary" href="../">Dashboard</a></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?>
<?php if($duplicateDates):?><div class="alert alert-warning"><strong>Duplicate event dates detected.</strong> Review these event records before publishing results:<ul class="mb-0 mt-2"><?php foreach(array_slice($duplicateDates,0,8) as $dupDate):?><li><strong><?=e((string)$dupDate['event_date'])?></strong>: <?=e((string)$dupDate['events'])?></li><?php endforeach;?></ul><?php if(count($duplicateDates)>8):?><div class="small mt-2">Plus <?=count($duplicateDates)-8?> more duplicate date group(s).</div><?php endif;?></div><?php endif;?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
<h2 class="h5"><?= $editDoc?'Edit or replace result':'Publish result document' ?></h2>
<?php if($editDoc):?><div class="alert alert-info py-2">Editing item #<?=(int)$editDoc['id']?>. Uploading a new file will replace the current file. Leave the upload field empty to keep it.</div><?php endif;?>
<form method="post" enctype="multipart/form-data" class="row g-3"><?=Csrf::field()?><input type="hidden" name="action" value="<?=$editDoc?'update':'add'?>"><input type="hidden" name="id" value="<?=(int)$form['id']?>">
<div class="col-md-6"><label class="form-label">Event</label><select class="form-select" name="event_id"><option value="0">No linked event</option><?php foreach($events as $ev):?><option value="<?=$ev['id']?>" <?=((int)$form['event_id']===(int)$ev['id'])?'selected':''?>><?=e($ev['event_date'].' — '.$ev['name'])?></option><?php endforeach;?></select></div>
<div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" value="<?=e((string)$form['title'])?>" required></div>
<div class="col-md-3"><label class="form-label">Category</label><select class="form-select" name="document_category"><?php foreach(['heats','finals','points','full_results','other'] as $v):?><option value="<?=$v?>" <?=$form['document_category']===$v?'selected':''?>><?=e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label">File type</label><select class="form-select" name="file_type"><?php foreach(['pdf','csv','world_result','external'] as $v):?><option value="<?=$v?>" <?=$form['file_type']===$v?'selected':''?>><?=e(strtoupper(str_replace('_',' ',$v)))?></option><?php endforeach;?></select></div>
<div class="col-md-6"><label class="form-label">External URL / World Result URL</label><input class="form-control" type="url" name="url" value="<?=e((string)$form['url'])?>"></div>
<div class="col-md-6"><label class="form-label"><?=$editDoc?'Replace with new PDF/CSV':'Or upload PDF/CSV'?></label><input class="form-control" type="file" name="result_file" accept=".pdf,.csv,application/pdf,text/csv"><?php if($editDoc && $form['storage_path']):?><div class="form-text">Current uploaded file: <?=e(basename((string)$form['storage_path']))?></div><?php endif;?></div>
<div class="col-12"><button class="btn btn-dark"><?=$editDoc?'Save changes':'Publish to repository'?></button><?php if($editDoc):?> <a class="btn btn-outline-secondary" href="./">Cancel</a><?php endif;?></div>
</form></div></div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
<h2 class="h5">Search repository</h2>
<form method="get" class="row g-3 align-items-end">
<div class="col-md-5"><label class="form-label">Search event, title or date</label><input class="form-control" name="q" value="<?=e($search)?>" placeholder="Example: BASS, SBTA or 2026-02-21"></div>
<div class="col-md-4"><label class="form-label">Specific event</label><select class="form-select" name="filter_event_id"><option value="0">All events</option><?php foreach($events as $ev):?><option value="<?=$ev['id']?>" <?=$filterEvent===(int)$ev['id']?'selected':''?>><?=e($ev['event_date'].' — '.$ev['name'])?></option><?php endforeach;?></select></div>
<div class="col-md-2"><label class="form-label">Category</label><select class="form-select" name="filter_category"><option value="">All categories</option><?php foreach(['heats','finals','points','full_results','other'] as $v):?><option value="<?=$v?>" <?=$filterCategory===$v?'selected':''?>><?=e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select></div>
<div class="col-md-1 d-grid"><button class="btn btn-primary">Search</button></div>
<div class="col-12"><a class="btn btn-sm btn-outline-secondary" href="./">Clear search</a> <span class="text-muted small"><?=count($docs)?> item(s) shown</span></div>
</form></div></div>
<div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5">Repository items</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Event</th><th>Title</th><th>Type</th><th>Version</th><th>Source</th><th>Actions</th></tr></thead><tbody>
<?php foreach($docs as $d):?><tr><td><?=e((string)($d['event_date']??''))?><div class="small"><?=e((string)($d['event_name']??'Unlinked'))?></div></td><td><?=e($d['title'])?></td><td><span class="badge text-bg-secondary"><?=e(strtoupper($d['file_type']))?></span> <?=e(ucwords(str_replace('_',' ',$d['document_category'])))?></td><td>v<?=(int)$d['version_number']?></td><td><?=e($d['source'])?></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?=e($d['url'])?>" target="_blank" rel="noopener">Open</a> <a class="btn btn-sm btn-outline-secondary" href="?edit=<?=(int)$d['id']?>">Edit / Replace</a> <form method="post" class="d-inline" onsubmit="return confirm('Delete this repository item? This cannot be undone.')"><?=Csrf::field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr><?php endforeach;?>
<?php if(!$docs):?><tr><td colspan="6" class="text-center text-muted py-4">No repository items found.</td></tr><?php endif;?>
</tbody></table></div></div></div></div></body></html>
