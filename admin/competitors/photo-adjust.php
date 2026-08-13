<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;
Auth::requirePermission('competitors.edit');$pdo=Database::connection();$id=(int)($_GET['id']??$_POST['id']??0);
$return=(string)($_GET['return']??($_SESSION['competitor_list_return_'.$id]??'?'));if($return===''||$return[0]!=='?'||str_contains($return,"\r")||str_contains($return,"\n"))$return='?';if($_SERVER['REQUEST_METHOD']==='GET')$_SESSION['competitor_list_return_'.$id]=$return;
$s=$pdo->prepare('SELECT id,bdc_id,exact_name,photo_url,original_photo_url FROM bdc_competitors WHERE id=:id');$s->execute(['id'=>$id]);$c=$s->fetch();if(!$c){http_response_code(404);exit('Competitor not found.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!Csrf::verify($_POST['_csrf']??null))$error='Invalid security token.';else{
  $data=(string)($_POST['cropped_photo_data']??'');
  if(!preg_match('~^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$~',$data,$m))$error='Adjust the photo before saving.';else{
   $bytes=base64_decode($m[1],true);if($bytes===false||strlen($bytes)>3*1024*1024)$error='Adjusted photo is invalid.';else{
    $dir=dirname(__DIR__,2).'/uploads/competitors';if(!is_dir($dir))mkdir($dir,0755,true);$name='competitor-framed-'.$id.'-'.bin2hex(random_bytes(5)).'.jpg';
    if(file_put_contents($dir.'/'.$name,$bytes)===false)$error='Adjusted photo could not be saved.';else{
     $source=(string)($c['original_photo_url']?:$c['photo_url']);$pdo->prepare('UPDATE bdc_competitors SET original_photo_url=COALESCE(original_photo_url,:source),photo_url=:photo WHERE id=:id')->execute(['source'=>$source?:null,'photo'=>url('uploads/competitors/'.$name),'id'=>$id]);Auth::audit((int)Auth::user()['id'],'competitor_photo_adjusted',[],'competitor',$id);header('Location: ./'.$return);exit;
    }
   }
  }
 }
}
$source=$c['original_photo_url']?:$c['photo_url']?:url('public/assets/img/default-competitor.svg');
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Adjust Competitor Photo</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>.crop{width:320px;height:400px;overflow:hidden;background:#ddd;border-radius:18px;position:relative}.crop img{position:absolute;max-width:none;transform-origin:center;cursor:grab}</style></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="edit.php?id=<?=$id?>">← <?=e($c['exact_name'])?></a></div></nav><main class="container py-4" style="max-width:800px"><h1 class="h3">Fit photo inside frame</h1><p class="text-muted">Drag to reposition and use zoom. The original image is preserved.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><form method="post" id="cropForm" class="card border-0 shadow-sm"><div class="card-body p-4"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="cropped_photo_data" id="cropData"><div class="crop mx-auto" id="frame"><img id="photo" src="<?=e($source)?>" crossorigin="anonymous"></div><label class="form-label mt-4">Zoom</label><input class="form-range" id="zoom" type="range" min="1" max="3" step=".01" value="1"></div><div class="card-footer bg-white"><button class="btn btn-dark">Save adjusted photo</button></div></form></main><script>
const frame=document.getElementById('frame'),img=document.getElementById('photo'),zoom=document.getElementById('zoom');let x=0,y=0,drag=false,sx=0,sy=0;
function draw(){const base=Math.max(320/img.naturalWidth,400/img.naturalHeight),s=base*+zoom.value;img.style.width=(img.naturalWidth*s)+'px';img.style.height=(img.naturalHeight*s)+'px';img.style.left=(160-img.naturalWidth*s/2+x)+'px';img.style.top=(200-img.naturalHeight*s/2+y)+'px'}
img.onload=draw;zoom.oninput=draw;frame.onpointerdown=e=>{drag=true;sx=e.clientX-x;sy=e.clientY-y;frame.setPointerCapture(e.pointerId)};frame.onpointermove=e=>{if(drag){x=e.clientX-sx;y=e.clientY-sy;draw()}};frame.onpointerup=()=>drag=false;
document.getElementById('cropForm').onsubmit=()=>{const c=document.createElement('canvas');c.width=640;c.height=800;const ctx=c.getContext('2d'),r=img.getBoundingClientRect(),f=frame.getBoundingClientRect();ctx.drawImage(img,(r.left-f.left)*2,(r.top-f.top)*2,r.width*2,r.height*2);document.getElementById('cropData').value=c.toDataURL('image/jpeg',.9)};
</script></body></html>
