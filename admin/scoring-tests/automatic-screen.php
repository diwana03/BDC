<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
ob_start(static fn(string $html):string=>str_replace('</head>','<script defer src="../../public/assets/js/bdc-theme.js?v=325"></script></head>',$html));
\App\Core\Auth::requireAdmin();
$_SESSION['bdc_test_scoring_mode'] = 'automated';
$roundId = (int) ($_GET['round_id'] ?? 0);
if (($_GET['panel'] ?? '') === '1') {
    $automaticInlineGateway = true;
    require __DIR__ . '/automatic-inline.php';
    exit;
}
$src = url('admin/scoring-tests/index.php?legacy=1&test_mode=automated' . ($roundId > 0 ? '&round_id=' . $roundId : '') . '&automatic_host=1');
$panel = url('admin/scoring-tests/automatic-inline.php');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Automatic Scoring Test | BDC Admin</title><style>html,body{margin:0;height:100%;background:#f4f6f9;overflow:hidden}#bdcAutoFrame{width:100%;height:100%;border:0;display:block;background:#f4f6f9}#bdcAutoError{display:none;position:fixed;left:16px;right:16px;top:16px;z-index:99999;padding:12px 16px;border-radius:8px;background:#842029;color:#fff;font:14px/1.4 Arial,sans-serif}</style></head><body><div id="bdcAutoError"></div><iframe id="bdcAutoFrame" src="<?= e($src) ?>" title="BDC Automatic Scoring Test"></iframe><script>
(function(){
const frame=document.getElementById('bdcAutoFrame'),panelBase=<?= json_encode($panel) ?>,screenBase=<?= json_encode(url('admin/scoring-tests/automatic-screen.php')) ?>,panelGateway=screenBase+'?panel=1',liveBase=<?= json_encode(url('admin/live-screen/test-control.php')) ?>,liveScoringBase=<?= json_encode(url('admin/scoring/index.php')) ?>,errorBox=document.getElementById('bdcAutoError');
function fail(message){errorBox.textContent=message;errorBox.style.display='block'}
function rid(doc){const rendered=Number(doc.querySelector('input[name="round_id"]')?.value||0);if(rendered>0)return rendered;const query=new URL(frame.contentWindow.location.href).searchParams.get('round_id');return Number(query)||0}
function links(doc){doc.querySelectorAll('a[href]').forEach(anchor=>{try{const link=new URL(anchor.href,frame.contentWindow.location.href);if(!/\/admin\/scoring-tests\/(?:index\.php)?$/.test(link.pathname))return;const round=Number(link.searchParams.get('round_id')||0);anchor.href=screenBase+(round>0?'?round_id='+round:'');anchor.target='_top'}catch(error){}})}
function executeScripts(doc,host){host.querySelectorAll('script').forEach(oldScript=>{const script=doc.createElement('script');Array.from(oldScript.attributes).forEach(attribute=>script.setAttribute(attribute.name,attribute.value));script.textContent=oldScript.textContent;oldScript.replaceWith(script)})}
async function fetchJudgePanel(round){const gateway=panelGateway+'&round_id='+round+'&test_mode=automated';let response=await fetch(gateway,{credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});if(response.ok)return response;const fallback=panelBase+'?round_id='+round+'&test_mode=automated';response=await fetch(fallback,{credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});if(response.ok)return response;const detail=(await response.text()).trim().slice(0,180);throw new Error('Judge Live Scoring returned HTTP '+response.status+(detail?' — '+detail.replace(/<[^>]*>/g,' '):''))}
async function install(){
errorBox.style.display='none';let doc;try{doc=frame.contentDocument}catch(error){fail('Automatic Test could not access the dashboard screen.');return}if(!doc)return;
const round=rid(doc);links(doc);if(round>0&&Number(new URL(window.location.href).searchParams.get('round_id')||0)!==round)history.replaceState(null,'',screenBase+'?round_id='+round);
const nav=doc.querySelector('nav .d-flex,nav .container-fluid>div:last-child');if(round>0&&nav&&!nav.querySelector('[data-test-live]')){const anchor=doc.createElement('a');anchor.dataset.testLive='1';anchor.href=liveBase+'?round_id='+round;anchor.target='_blank';anchor.rel='noopener';anchor.className='btn btn-danger btn-sm';anchor.textContent='Live Screen';nav.prepend(anchor)}
const subtitle=[...doc.querySelectorAll('.text-muted')].find(element=>element.textContent.includes('Scoring Engine')&&element.textContent.includes('Event Round Workflow'));if(subtitle)subtitle.textContent='Automatic Scoring Engine · Event Round Workflow';
const heading=[...doc.querySelectorAll('h1')].find(element=>element.textContent.trim().startsWith('Scoring Tests Dashboard'));if(heading&&!heading.querySelector('[data-auto-badge]')){const badge=doc.createElement('span');badge.dataset.autoBadge='1';badge.className='badge text-bg-primary ms-2';badge.style.fontSize='.72rem';badge.textContent='AUTOMATIC TEST';heading.appendChild(badge)}
const liveJudgeSurface=doc.querySelector('iframe[title="Automatic Final Judge Links"],iframe[src*="judge_panel=1"],a[href*="judge_panel=1"]');if(round>0&&liveJudgeSurface){window.top.location.replace(liveScoringBase+'?mode=automated&round_id='+round);return}
if(round<1)return;const manual=doc.getElementById('heatsScoreForm')||doc.getElementById('finalScoreForm');if(!manual)return;const host=doc.createElement('div');host.id='bdcAutomaticNativeHost';manual.replaceWith(host);
try{const response=await fetchJudgePanel(round);host.innerHTML=await response.text();executeScripts(doc,host)}catch(error){const message=String(error.message||error);host.innerHTML='<div class="alert alert-danger"><strong>Judge Live Scoring could not load.</strong><br>'+message+'</div>';fail('Automatic Test panel failed: '+message)}
}
frame.addEventListener('load',install)
})();
</script></body></html>
