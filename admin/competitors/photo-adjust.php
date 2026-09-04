<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\PhotoBackgroundRemovalService;

Auth::requirePermission('competitors.edit');
$pdo=Database::connection();
$id=(int)($_GET['id']??$_POST['id']??0);
$dashboardCandidate=(string)($_GET['dashboard']??$_POST['dashboard']??($_SESSION['competitor_dashboard_'.$id]??''));
$dashboard=in_array($dashboardCandidate,['bachata','salsa'],true)?$dashboardCandidate:'';
if(isset($_GET['dashboard']))$_SESSION['competitor_dashboard_'.$id]=$dashboard;
$return=(string)($_GET['return']??($_SESSION['competitor_list_return_'.$id]??'?'));
if($return===''||(!str_starts_with($return,'?')&&$return!=='../dance-cup/participants.php')||str_contains($return,"\r")||str_contains($return,"\n"))$return='?';
if($_SERVER['REQUEST_METHOD']==='GET')$_SESSION['competitor_list_return_'.$id]=$return;
$s=$pdo->prepare('SELECT id,bdc_id,exact_name,photo_url,original_photo_url FROM bdc_competitors WHERE id=:id');
$s->execute(['id'=>$id]);
$c=$s->fetch();
if(!$c){http_response_code(404);exit('Competitor not found.');}

$error='';$notice='';$previewKey='competitor_bg_preview_'.$id;$preview=$_SESSION[$previewKey]??null;
$clearPreview=static function()use($previewKey,&$preview):void{
 if(is_array($preview)&&isset($preview['path'])&&is_string($preview['path'])){
  $root=realpath(dirname(__DIR__,2).'/uploads/background-removal');
  $file=realpath($preview['path']);
  if($root!==false&&$file!==false&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file))@unlink($file);
 }
 unset($_SESSION[$previewKey]);$preview=null;
};
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
  $action=(string)($_POST['action']??'adjust');
  if(in_array($action,['remove_background','apply_background_removal','discard_background_preview','restore_original'],true)){
   if(!Auth::isSuperAdmin())throw new RuntimeException('Only the Super Admin can remove or restore photo backgrounds.');
   if($action==='remove_background'){
    $current=trim((string)($c['photo_url']??''));
    if($current==='')throw new RuntimeException('Upload a competitor photo before removing its background.');
    $clearPreview();
    $bytes=PhotoBackgroundRemovalService::remove($current);
    $dir=dirname(__DIR__,2).'/uploads/background-removal';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Background-removal preview folder is unavailable.');
    $name='competitor-preview-'.$id.'-'.bin2hex(random_bytes(8)).'.png';$file=$dir.'/'.$name;
    if(file_put_contents($file,$bytes,LOCK_EX)===false)throw new RuntimeException('Background-removal preview could not be saved.');
    $preview=['path'=>$file,'url'=>url('uploads/background-removal/'.$name),'created_at'=>time(),'source'=>(string)$c['photo_url']];
    $_SESSION[$previewKey]=$preview;$notice='Background removed for preview. Review it before applying.';
    Auth::audit((int)(Auth::user()['id']??0),'competitor_background_preview_created',[],'competitor',$id);
   }elseif($action==='apply_background_removal'){
    if(!is_array($preview)||!isset($preview['path'])||!is_file((string)$preview['path']))throw new RuntimeException('The background-removal preview expired. Generate it again.');
    $previewRoot=realpath(dirname(__DIR__,2).'/uploads/background-removal');$previewFile=realpath((string)$preview['path']);
    if($previewRoot===false||$previewFile===false||!str_starts_with($previewFile,$previewRoot.DIRECTORY_SEPARATOR))throw new RuntimeException('The background-removal preview is invalid.');
    $dir=dirname(__DIR__,2).'/uploads/competitors';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Photo upload folder is unavailable.');
    $name='competitor-bgremoved-'.$id.'-'.bin2hex(random_bytes(6)).'.png';$destination=$dir.'/'.$name;
    if(!rename($previewFile,$destination))throw new RuntimeException('The approved background-removal photo could not be saved.');
    $source=(string)($c['original_photo_url']?:($preview['source']??$c['photo_url']));
    $photo=url('uploads/competitors/'.$name);
    $pdo->prepare('UPDATE bdc_competitors SET original_photo_url=COALESCE(original_photo_url,:source),photo_url=:photo WHERE id=:id')->execute(['source'=>$source?:null,'photo'=>$photo,'id'=>$id]);
    unset($_SESSION[$previewKey]);$preview=null;
    Auth::audit((int)(Auth::user()['id']??0),'competitor_background_removed',[],'competitor',$id);
    header('Location: photo-adjust.php?id='.$id.'&dashboard='.rawurlencode($dashboard).'&return='.rawurlencode($return).'&background_removed=1');exit;
   }elseif($action==='discard_background_preview'){
    $clearPreview();$notice='Background-removal preview discarded. The live photo was not changed.';
   }else{
    $original=trim((string)($c['original_photo_url']??''));
    if($original==='')throw new RuntimeException('No preserved original photo is available to restore.');
    $clearPreview();
    $pdo->prepare('UPDATE bdc_competitors SET photo_url=:photo WHERE id=:id')->execute(['photo'=>$original,'id'=>$id]);
    Auth::audit((int)(Auth::user()['id']??0),'competitor_photo_original_restored',[],'competitor',$id);
    header('Location: photo-adjust.php?id='.$id.'&dashboard='.rawurlencode($dashboard).'&return='.rawurlencode($return).'&original_restored=1');exit;
   }
  }elseif($action==='replace'){
   $file=$_FILES['replacement_photo']??null;$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
   if(!$file||empty($file['tmp_name']))throw new RuntimeException('Choose a JPG, PNG or WebP photo to upload.');
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
   if(!isset($types[$mime])||(int)$file['size']>5*1024*1024)throw new RuntimeException('Photo must be JPG, PNG or WebP and under 5 MB.');
   $dir=dirname(__DIR__,2).'/uploads/competitors';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Photo upload folder is unavailable.');
   $name='competitor-original-'.$id.'-'.bin2hex(random_bytes(5)).'.'.$types[$mime];
   if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))throw new RuntimeException('Replacement photo could not be uploaded.');
   $clearPreview();$photo=url('uploads/competitors/'.$name);
   $pdo->prepare('UPDATE bdc_competitors SET original_photo_url=:original,photo_url=:current WHERE id=:id')->execute(['original'=>$photo,'current'=>$photo,'id'=>$id]);
   Auth::audit((int)Auth::user()['id'],'competitor_photo_replaced',[],'competitor',$id);
   header('Location: photo-adjust.php?id='.$id.'&dashboard='.rawurlencode($dashboard).'&return='.rawurlencode($return));exit;
  }else{
   $data=(string)($_POST['cropped_photo_data']??'');
   if(!preg_match('~^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$~',$data,$m))throw new RuntimeException('Adjust the photo before saving.');
   $bytes=base64_decode($m[1],true);if($bytes===false||strlen($bytes)>3*1024*1024)throw new RuntimeException('Adjusted photo is invalid.');
   $dir=dirname(__DIR__,2).'/uploads/competitors';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Photo upload folder is unavailable.');
   $name='competitor-framed-'.$id.'-'.bin2hex(random_bytes(5)).'.jpg';
   if(file_put_contents($dir.'/'.$name,$bytes)===false)throw new RuntimeException('Adjusted photo could not be saved.');
   $clearPreview();$source=(string)($c['original_photo_url']?:$c['photo_url']);
   $pdo->prepare('UPDATE bdc_competitors SET original_photo_url=COALESCE(original_photo_url,:source),photo_url=:photo WHERE id=:id')->execute(['source'=>$source?:null,'photo'=>url('uploads/competitors/'.$name),'id'=>$id]);
   Auth::audit((int)Auth::user()['id'],'competitor_photo_adjusted',[],'competitor',$id);
   header('Location: ./'.$return);exit;
  }
 }catch(Throwable $exception){$error=$exception->getMessage();}
}
if(isset($_GET['background_removed']))$notice='Transparent background photo applied. The original remains available for restore.';
if(isset($_GET['original_restored']))$notice='Original competitor photo restored.';

$source=$c['original_photo_url']?:$c['photo_url']?:url('public/assets/img/default-competitor.svg');
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Adjust Competitor Photo</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>
.crop{width:min(320px,100%);aspect-ratio:4/5;overflow:hidden;background:#ddd;border-radius:18px;position:relative;touch-action:none;user-select:none;-webkit-user-select:none}
.crop img{position:absolute;max-width:none;transform-origin:center;cursor:grab;pointer-events:none;-webkit-user-drag:none}
.crop.is-dragging img{cursor:grabbing}
</style></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="edit.php?id=<?=$id?>&amp;dance=<?=e($dashboard?:'bachata')?>&amp;dashboard=<?=e($dashboard)?>">← <?=e($c['exact_name'])?></a></div></nav><main class="container py-4" style="max-width:800px"><h1 class="h3">Fit <?=e(strtoupper($dashboard==='salsa'?'sdc':'bdc'))?> photo inside frame</h1><p class="text-muted">Drag to reposition and use zoom. The original shared person image is preserved.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?>
<section class="card border-0 shadow-sm mb-3"><div class="card-body"><h2 class="h5">Replace photo</h2><p class="text-muted small">Upload a newer photo, then position it below.</p><form method="post" enctype="multipart/form-data" class="row g-2 align-items-end"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="dashboard" value="<?=e($dashboard)?>"><input type="hidden" name="action" value="replace"><div class="col-md"><label class="form-label" for="replacementPhoto">New photo</label><input class="form-control" id="replacementPhoto" type="file" name="replacement_photo" accept="image/jpeg,image/png,image/webp" required></div><div class="col-md-auto"><button class="btn btn-outline-primary">Upload and adjust</button></div></form></div></section>
<?php if(Auth::isSuperAdmin()):?><section class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><h2 class="h5 mb-1">Remove actual photo background</h2><p class="text-muted small mb-0">Creates a transparent PNG preview first. Nothing changes until you approve it, and the original remains recoverable.</p></div><span class="badge <?=PhotoBackgroundRemovalService::configured()?'text-bg-success':'text-bg-warning'?>"><?=PhotoBackgroundRemovalService::configured()?'API ready':'API key required'?></span></div><?php if(is_array($preview)&&!empty($preview['url'])):?><div class="row g-3 align-items-center mt-2"><div class="col-md-5 text-center"><div style="background:repeating-conic-gradient(#e5e7eb 0 25%,#fff 0 50%) 50%/20px 20px;border-radius:16px;padding:14px"><img src="<?=e((string)$preview['url'])?>" alt="Transparent background preview" style="max-width:100%;max-height:360px;object-fit:contain"></div></div><div class="col-md-7"><div class="alert alert-info">Preview only. Check the hair, hands and clothing edges before applying.</div><div class="d-flex gap-2 flex-wrap"><form method="post"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="dashboard" value="<?=e($dashboard)?>"><input type="hidden" name="return" value="<?=e($return)?>"><button class="btn btn-success" name="action" value="apply_background_removal">Apply transparent photo</button></form><form method="post"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="dashboard" value="<?=e($dashboard)?>"><input type="hidden" name="return" value="<?=e($return)?>"><button class="btn btn-outline-secondary" name="action" value="discard_background_preview">Discard preview</button></form></div></div></div><?php else:?><form method="post" class="mt-3"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="dashboard" value="<?=e($dashboard)?>"><input type="hidden" name="return" value="<?=e($return)?>"><button class="btn btn-primary" name="action" value="remove_background" <?=PhotoBackgroundRemovalService::configured()?'':'disabled'?>>Remove Background &amp; Preview</button><?php if(!PhotoBackgroundRemovalService::configured()):?><div class="form-text">Configure <code>BDC_REMOVE_BG_API_KEY</code> or protected file <code><?=e(PhotoBackgroundRemovalService::secretFilePath())?></code>.</div><?php endif;?></form><?php endif;?><?php if(!empty($c['original_photo_url'])&&$c['photo_url']!==$c['original_photo_url']):?><hr><form method="post" onsubmit="return confirm('Restore the preserved original photo?')"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="dashboard" value="<?=e($dashboard)?>"><input type="hidden" name="return" value="<?=e($return)?>"><button class="btn btn-outline-dark" name="action" value="restore_original">Restore Original Photo</button></form><?php endif;?></div></section><?php endif;?>
<form method="post" id="cropForm" class="card border-0 shadow-sm"><div class="card-body p-4"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="dashboard" value="<?=e($dashboard)?>"><input type="hidden" name="action" value="adjust"><input type="hidden" name="cropped_photo_data" id="cropData"><div class="crop mx-auto" id="frame"><img id="photo" src="<?=e($source)?>" crossorigin="anonymous" draggable="false"></div><label class="form-label mt-4">Zoom</label><input class="form-range" id="zoom" type="range" min="1" max="3" step=".01" value="1"></div><div class="card-footer bg-white d-flex gap-2 flex-wrap"><button class="btn btn-dark">Save adjusted photo</button><a class="btn btn-outline-secondary" href="edit.php?id=<?=$id?>&amp;dance=<?=e($dashboard?:'bachata')?>&amp;dashboard=<?=e($dashboard)?>">Back to competitor</a></div></form></main><script>
const frame=document.getElementById('frame'),img=document.getElementById('photo'),zoom=document.getElementById('zoom');let x=0,y=0,drag=false,pointerId=null,sx=0,sy=0,raf=0;
function metrics(){const w=frame.clientWidth,h=frame.clientHeight,base=Math.max(w/img.naturalWidth,h/img.naturalHeight),scale=base*Number(zoom.value);return{w,h,iw:img.naturalWidth*scale,ih:img.naturalHeight*scale}}
function draw(){cancelAnimationFrame(raf);raf=requestAnimationFrame(()=>{if(!img.naturalWidth)return;const m=metrics(),maxX=Math.max(0,(m.iw-m.w)/2),maxY=Math.max(0,(m.ih-m.h)/2);x=Math.max(-maxX,Math.min(maxX,x));y=Math.max(-maxY,Math.min(maxY,y));img.style.width=m.iw+'px';img.style.height=m.ih+'px';img.style.left=(m.w/2-m.iw/2+x)+'px';img.style.top=(m.h/2-m.ih/2+y)+'px'})}
function stop(e){if(pointerId!==null&&frame.hasPointerCapture?.(pointerId))frame.releasePointerCapture(pointerId);drag=false;pointerId=null;frame.classList.remove('is-dragging');if(e)e.preventDefault()}
img.onload=draw;zoom.addEventListener('input',draw);window.addEventListener('resize',draw);
frame.addEventListener('pointerdown',e=>{if(e.button!==undefined&&e.button!==0)return;drag=true;pointerId=e.pointerId;sx=e.clientX-x;sy=e.clientY-y;frame.classList.add('is-dragging');frame.setPointerCapture?.(e.pointerId);e.preventDefault()});
frame.addEventListener('pointermove',e=>{if(!drag||e.pointerId!==pointerId)return;x=e.clientX-sx;y=e.clientY-sy;draw();e.preventDefault()});
['pointerup','pointercancel','lostpointercapture'].forEach(type=>frame.addEventListener(type,stop));frame.addEventListener('dragstart',e=>e.preventDefault());
document.getElementById('cropForm').onsubmit=()=>{const c=document.createElement('canvas');c.width=640;c.height=800;const ctx=c.getContext('2d'),r=img.getBoundingClientRect(),f=frame.getBoundingClientRect(),scaleX=640/f.width,scaleY=800/f.height;ctx.drawImage(img,(r.left-f.left)*scaleX,(r.top-f.top)*scaleY,r.width*scaleX,r.height*scaleY);document.getElementById('cropData').value=c.toDataURL('image/jpeg',.9)};
</script></body></html>
