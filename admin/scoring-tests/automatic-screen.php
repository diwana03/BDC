<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
\App\Core\Auth::requireAdmin();
$_SESSION['bdc_test_scoring_mode']='automated';
$roundId=(int)($_GET['round_id']??0);
$src=url('admin/scoring-tests/index.php?legacy=1&test_mode=automated'.($roundId>0?'&round_id='.$roundId:'').'&automatic_host=1');
$panel=url('admin/scoring-tests/automatic-inline.php');
$home=url('admin/');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Automatic Scoring Test | BDC Admin</title>
<style>
html,body{margin:0;height:100%;background:#f4f6f9;overflow:hidden}
#bdcAutoFrame{width:100%;height:100%;border:0;display:block;background:#f4f6f9}
#bdcAutoError{display:none;position:fixed;left:16px;right:16px;top:16px;z-index:99999;padding:12px 16px;border-radius:8px;background:#842029;color:#fff;font:14px/1.4 Arial,sans-serif;box-shadow:0 8px 28px rgba(0,0,0,.25)}
</style>
</head>
<body>
<div id="bdcAutoError"></div>
<iframe id="bdcAutoFrame" src="<?=e($src)?>" title="BDC Automatic Scoring Test"></iframe>
<script>
(function(){
 const frame=document.getElementById('bdcAutoFrame');
 const panelBase=<?=json_encode($panel,JSON_UNESCAPED_SLASHES)?>;
 const screenBase=<?=json_encode(url('admin/scoring-tests/automatic-screen.php'),JSON_UNESCAPED_SLASHES)?>;
 const adminHome=<?=json_encode($home,JSON_UNESCAPED_SLASHES)?>;
 const errorBox=document.getElementById('bdcAutoError');
 function fail(message){errorBox.textContent=message;errorBox.style.display='block';}
 function roundId(doc){
   const q=new URL(frame.contentWindow.location.href).searchParams.get('round_id');
   if(q)return Number(q)||0;
   const input=doc.querySelector('input[name="round_id"]');
   return Number(input&&input.value||0);
 }
 function preserveRoundLinks(doc){
   doc.querySelectorAll('a[href]').forEach(a=>{
     try{
       const u=new URL(a.href,frame.contentWindow.location.href);
       if(!/\/admin\/scoring-tests\/(?:index\.php)?$/.test(u.pathname))return;
       const rid=Number(u.searchParams.get('round_id')||0);
       if(rid>0){a.href=screenBase+'?round_id='+rid;a.target='_top';}
     }catch(e){}
   });
 }
 async function install(){
   errorBox.style.display='none';
   let doc;
   try{doc=frame.contentDocument;}catch(e){fail('Automatic Test could not access the dashboard screen.');return;}
   if(!doc)return;

   const subtitle=[...doc.querySelectorAll('.text-muted')].find(x=>x.textContent.includes('Scoring Engine')&&x.textContent.includes('Event Round Workflow'));
   if(subtitle)subtitle.textContent='Automatic Scoring Engine · Event Round Workflow';
   const heading=[...doc.querySelectorAll('h1')].find(x=>x.textContent.trim().startsWith('Scoring Tests Dashboard'));
   if(heading&&!heading.querySelector('[data-auto-badge]')){
      const badge=doc.createElement('span');badge.dataset.autoBadge='1';badge.className='badge text-bg-primary ms-2';badge.style.fontSize='.72rem';badge.textContent='AUTOMATIC TEST';heading.appendChild(badge);
   }

   preserveRoundLinks(doc);
   const rid=roundId(doc);
   if(rid<1)return;

   const manual=doc.getElementById('heatsScoreForm');
   if(!manual)return;
   const host=doc.createElement('div');host.id='bdcAutomaticNativeHost';
   manual.replaceWith(host);
   try{
     const response=await fetch(panelBase+'?round_id='+rid+'&test_mode=automated',{credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});
     if(!response.ok)throw new Error('Judge Live Scoring returned HTTP '+response.status);
     host.innerHTML=await response.text();
     host.querySelectorAll('script').forEach(old=>{const s=doc.createElement('script');s.textContent=old.textContent;old.replaceWith(s);});
     host.querySelectorAll('form').forEach(form=>{
       const action=form.getAttribute('action')||'';
       if(!action.includes('automatic-inline.php'))return;
       form.addEventListener('submit',async ev=>{
         ev.preventDefault();
         if(form.onsubmit&&form.onsubmit()===false)return;
         try{
           const r=await fetch(form.action,{method:'POST',body:new FormData(form),credentials:'same-origin',redirect:'follow'});
           if(!r.ok)throw new Error('Action returned HTTP '+r.status);
           frame.src=<?=json_encode(url('admin/scoring-tests/index.php?legacy=1&test_mode=automated&automatic_host=1'),JSON_UNESCAPED_SLASHES)?>+'&round_id='+rid+'&_refresh='+Date.now();
         }catch(e){fail(String(e.message||e));}
       },true);
     });
   }catch(e){
     host.innerHTML='<div class="alert alert-danger"><strong>Judge Live Scoring could not load.</strong><br>'+String(e.message||e)+'</div>';
   }
 }
 frame.addEventListener('load',install);
})();
</script>
</body>
</html>
