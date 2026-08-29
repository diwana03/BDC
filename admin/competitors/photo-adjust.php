<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

Auth::requirePermission('competitors.edit');
$pdo=Database::connection();
$id=(int)($_GET['id']??$_POST['id']??0);
$return=(string)($_GET['return']??($_SESSION['competitor_list_return_'.$id]??'?'));
if($return===''||(!str_starts_with($return,'?')&&$return!=='../dance-cup/participants.php')||str_contains($return,"\r")||str_contains($return,"\n"))$return='?';
if($_SERVER['REQUEST_METHOD']==='GET')$_SESSION['competitor_list_return_'.$id]=$return;
$s=$pdo->prepare('SELECT id,bdc_id,exact_name,photo_url,original_photo_url FROM bdc_competitors WHERE id=:id');
$s->execute(['id'=>$id]);
$c=$s->fetch();
if(!$c){http_response_code(404);exit('Competitor not found.');}

$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!Csrf::verify($_POST['_csrf']??null))$error='Invalid security token.';
 elseif(($_POST['action']??'adjust')==='replace'){
  $file=$_FILES['replacement_photo']??null;
  $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  if(!$file||empty($file['tmp_name']))$error='Choose a JPG, PNG or WebP photo to upload.';
  else{
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
   if(!isset($types[$mime])||(int)$file['size']>5*1024*1024)$error='Photo must be JPG, PNG or WebP and under 5 MB.';
   else{
    $dir=dirname(__DIR__,2).'/uploads/competitors';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))$error='Photo upload folder is unavailable.';
    else{
     $name='competitor-original-'.$id.'-'.bin2hex(random_bytes(5)).'.'.$types[$mime];
     if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))$error='Replacement photo could not be uploaded.';
     else{
      $photo=url('uploads/competitors/'.$name);
      $pdo->prepare('UPDATE bdc_competitors SET original_photo_url=:original,photo_url=:current WHERE id=:id')->execute(['original'=>$photo,'current'=>$photo,'id'=>$id]);
      Auth::audit((int)Auth::user()['id'],'competitor_photo_replaced',[],'competitor',$id);
      header('Location: photo-adjust.php?id='.$id.'&return='.rawurlencode($return));
      exit;
     }
    }
   }
  }
 }else{
  $data=(string)($_POST['cropped_photo_data']??'');
  if(!preg_match('~^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$~',$data,$m))$error='Adjust the photo before saving.';
  else{
   $bytes=base64_decode($m[1],true);
   if($bytes===false||strlen($bytes)>3*1024*1024)$error='Adjusted photo is invalid.';
   else{
    $dir=dirname(__DIR__,2).'/uploads/competitors';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))$error='Photo upload folder is unavailable.';
    else{
     $name='competitor-framed-'.$id.'-'.bin2hex(random_bytes(5)).'.jpg';
     if(file_put_contents($dir.'/'.$name,$bytes)===false)$error='Adjusted photo could not be saved.';
     else{
      $source=(string)($c['original_photo_url']?:$c['photo_url']);
      $pdo->prepare('UPDATE bdc_competitors SET original_photo_url=COALESCE(original_photo_url,:source),photo_url=:photo WHERE id=:id')->execute(['source'=>$source?:null,'photo'=>url('uploads/competitors/'.$name),'id'=>$id]);
      Auth::audit((int)Auth::user()['id'],'competitor_photo_adjusted',[],'competitor',$id);
      header('Location: ./'.$return);
      exit;
     }
    }
   }
  }
 }
}

$source=$c['original_photo_url']?:$c['photo_url']?:url('public/assets/img/default-competitor.svg');
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Adjust Competitor Photo</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>
.crop{width:min(320px,100%);aspect-ratio:4/5;overflow:hidden;background:#ddd;border-radius:18px;position:relative;touch-action:none;user-select:none;-webkit-user-select:none}
.crop img{position:absolute;max-width:none;transform-origin:center;cursor:grab;pointer-events:none;-webkit-user-drag:none}
.crop.is-dragging img{cursor:grabbing}
</style></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="edit.php?id=<?=$id?>">← <?=e($c['exact_name'])?></a></div></nav><main class="container py-4" style="max-width:800px"><h1 class="h3">Fit photo inside frame</h1><p class="text-muted">Drag to reposition and use zoom. The original image is preserved.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<section class="card border-0 shadow-sm mb-3"><div class="card-body"><h2 class="h5">Replace photo</h2><p class="text-muted small">Upload a newer photo, then position it below.</p><form method="post" enctype="multipart/form-data" class="row g-2 align-items-end"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="replace"><div class="col-md"><label class="form-label" for="replacementPhoto">New photo</label><input class="form-control" id="replacementPhoto" type="file" name="replacement_photo" accept="image/jpeg,image/png,image/webp" required></div><div class="col-md-auto"><button class="btn btn-outline-primary">Upload and adjust</button></div></form></div></section>
<form method="post" id="cropForm" class="card border-0 shadow-sm"><div class="card-body p-4"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="adjust"><input type="hidden" name="cropped_photo_data" id="cropData"><div class="crop mx-auto" id="frame"><img id="photo" src="<?=e($source)?>" crossorigin="anonymous" draggable="false"></div><label class="form-label mt-4">Zoom</label><input class="form-range" id="zoom" type="range" min="1" max="3" step=".01" value="1"></div><div class="card-footer bg-white d-flex gap-2 flex-wrap"><button class="btn btn-dark">Save adjusted photo</button><a class="btn btn-outline-secondary" href="edit.php?id=<?=$id?>">Back to competitor</a></div></form></main><script>
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
