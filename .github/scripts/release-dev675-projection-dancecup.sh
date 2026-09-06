#!/usr/bin/env bash
set -euo pipefail

python3 <<'PY'
from pathlib import Path
import json


def replace_once(path, old, new):
    p=Path(path)
    s=p.read_text()
    if old not in s:
        raise SystemExit(f"required pattern not found in {path}: {old[:100]!r}")
    p.write_text(s.replace(old,new,1))

# 1) Callback/countdown must be visually blank behind the numbers.
replace_once(
    'live-display/index.php',
    "ctx.fillStyle='rgba(3,5,9,.88)';ctx.fillRect(0,0,innerWidth,innerHeight);",
    "ctx.fillStyle='#000';ctx.fillRect(0,0,innerWidth,innerHeight);"
)
# Force this release's projector stylesheet through the outer shell cache as well.
replace_once(
    'live-display/index.php',
    "safe.href='../public/css/projector-safe-v616.css?v=650';",
    "safe.href='../public/css/projector-safe-v616.css?v=675';"
)

# 2) Finalist Couples + provisional Final matrix: use the same audience-first safe-area logic as Heats.
css_path=Path('public/css/projector-safe-v616.css')
css=css_path.read_text()
marker='/* dev675 audience pass: Finalist Couples and Final matrix */'
if marker not in css:
    css += r'''

/* dev675 audience pass: Finalist Couples and Final matrix */
body[data-screen-type="final_couples"] .projection-heading-row{
  margin-bottom:clamp(8px,1.15cqh,16px)!important;
  gap:clamp(12px,1cqw,22px)!important;
}
body[data-screen-type="final_couples"] .projection-heading-row>.projection-brand{width:clamp(78px,8.2cqh,118px)!important}
body[data-screen-type="final_couples"] .event{font-size:clamp(24px,1.75vw,42px)!important;line-height:1.02!important}
body[data-screen-type="final_couples"] .meta{font-size:clamp(12px,.82vw,19px)!important;line-height:1!important}
body[data-screen-type="final_couples"] .title{font-size:clamp(24px,1.9vw,46px)!important;line-height:1!important;margin:.08em 0!important}
body[data-screen-type="final_couples"] .stage .list{
  width:100%!important;height:100%!important;min-height:0!important;
  display:flex!important;flex-wrap:wrap!important;align-content:stretch!important;justify-content:center!important;
  gap:.55cqh .55cqw!important;padding:0!important;
}
body[data-screen-type="final_couples"] .stage .list>.item{
  flex:0 0 calc(20% - .55cqw)!important;
  height:calc((100% - 1.1cqh)/3)!important;
  min-height:0!important;padding:clamp(4px,.42vw,8px)!important;
  justify-content:flex-start!important;overflow:hidden!important;
}
body[data-screen-type="final_couples"] .stage .list>.item>.small:first-child{
  font-size:clamp(13px,.86vw,20px)!important;line-height:1!important;margin:0 0 .18cqh!important;
}
body[data-screen-type="final_couples"] .stage .list>.item>div:nth-child(2){
  flex:1 1 auto!important;min-height:0!important;width:100%!important;gap:clamp(7px,.55vw,12px)!important;margin:0!important;
}
body[data-screen-type="final_couples"] .final-couple-person{
  flex:1 1 0!important;max-width:calc(50% - 4px)!important;min-width:0!important;
  padding:0!important;row-gap:clamp(1px,.14cqh,3px)!important;
}
body[data-screen-type="final_couples"] .final-couple-person>.photo{
  width:clamp(52px,6.7cqh,92px)!important;height:clamp(52px,6.7cqh,92px)!important;margin:0!important;
}
body[data-screen-type="final_couples"] .final-couple-person>.name{
  font-size:clamp(13px,.9vw,21px)!important;line-height:1!important;letter-spacing:-.01em!important;
}
body[data-screen-type="final_couples"] .final-couple-person>.flight-country img{width:clamp(25px,1.55vw,36px)!important}
body[data-screen-type="final_couples"] .final-couple-person>.small:not(.flight-country){
  font-size:clamp(17px,1.28vw,30px)!important;line-height:.96!important;margin:.05cqh 0 0!important;
}

body[data-screen-type="score_matrix"]:has(.matrix-final) .stage{
  padding-top:10cqh!important;padding-bottom:10cqh!important;padding-left:5cqw!important;padding-right:5cqw!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .projection-official{top:10cqh!important;right:5cqw!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .projection-heading-row{
  margin-bottom:clamp(6px,.65cqh,10px)!important;gap:clamp(12px,1cqw,20px)!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .projection-heading-row>.projection-brand{width:clamp(76px,7cqh,108px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .event{font-size:clamp(23px,1.62vw,40px)!important;line-height:1.02!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .meta{font-size:clamp(11px,.78vw,18px)!important;line-height:1!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .title{font-size:clamp(23px,1.72vw,42px)!important;line-height:1!important;margin:.08em 0!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-wrap{flex:1 1 auto!important;min-height:0!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final{height:100%!important;font-size:clamp(14px,.92vw,21px)!important;table-layout:fixed!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final thead{height:7%!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final tbody{height:93%!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final tbody tr{height:8.333%!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final th,
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final td{padding:0 .26em!important;overflow:hidden!important;text-overflow:clip!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final th:first-child,
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final td:first-child{font-size:clamp(14px,.9vw,21px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final th:nth-child(2){font-size:clamp(14px,.92vw,22px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final td:nth-child(2){font-size:clamp(15px,1vw,23px)!important;padding-left:.5em!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final th:nth-child(n+3){font-size:clamp(10px,.61vw,14px)!important;line-height:1!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final th:nth-child(n+3) small{font-size:clamp(7px,.42vw,10px)!important;line-height:1!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final td:nth-child(n+3){font-size:clamp(15px,.94vw,22px)!important;font-weight:950!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-couple{
  display:flex!important;align-items:center!important;gap:clamp(5px,.35vw,9px)!important;width:100%!important;min-width:0!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-person{
  flex:1 1 0!important;max-width:calc(50% - 12px)!important;min-width:0!important;gap:clamp(4px,.28vw,7px)!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-person strong{font-size:.86em!important}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-person>span:last-child{
  min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .final-matrix-flag{width:clamp(23px,1.35vw,33px)!important}
'''
    css_path.write_text(css)

# Tighten the provisional final matrix's structural column widths: no giant dead couple column.
replace_once(
    'live-display/feed.php',
    "colgroup.innerHTML='<col style=\"width:14%\"><col style=\"width:42%\">'+Array.from({length:judgeCount},()=>'<col style=\"width:'+(44/judgeCount)+'%\">').join('');",
    "colgroup.innerHTML='<col style=\"width:10%\"><col style=\"width:36%\">'+Array.from({length:judgeCount},()=>'<col style=\"width:'+(54/judgeCount)+'%\">').join('');"
)

# 3) Final Relative Placement: same 10/5 safe area and audience-balanced columns.
replace_once('live-display/final-relative-placement.php', '.stage{width:100vw;height:100vh;padding:5vh 5vw;', '.stage{width:100vw;height:100vh;padding:10vh 5vw;')
replace_once('live-display/final-relative-placement.php', 'th:first-child,td:first-child{width:9%;', 'th:first-child,td:first-child{width:10%;')
replace_once('live-display/final-relative-placement.php', 'th:nth-child(2),td:nth-child(2){width:43%;', 'th:nth-child(2),td:nth-child(2){width:36%;')
replace_once('live-display/final-relative-placement.php', 'font-size:clamp(16px,1.08vw,26px);overflow:hidden', 'font-size:clamp(15px,1vw,23px);overflow:hidden')
replace_once('live-display/final-relative-placement.php', 'font-size:clamp(15px,.95vw,23px);font-weight:950', 'font-size:clamp(15px,.94vw,22px);font-weight:950')
replace_once('live-display/final-relative-placement.php', '.test{position:absolute;top:5vh;left:5vw;', '.test{position:absolute;top:10vh;left:5vw;')

# 4) Dance Cup projector: BDC Official Live Display badge is the audience fullscreen control.
projector=Path('admin/dance-cup/projector.php')
ps=projector.read_text()
if 'data-dc-fullscreen-ready="1"' not in ps:
    ps=ps.replace(
        '.official{display:inline-block;border:1px solid var(--line);border-radius:999px;padding:.5em 1em;font-size:clamp(10px,.8vw,15px);font-weight:900;letter-spacing:.1em}',
        '.official{display:inline-block;border:1px solid var(--line);border-radius:999px;padding:.5em 1em;font-size:clamp(10px,.8vw,15px);font-weight:900;letter-spacing:.1em;cursor:pointer;user-select:none;transition:transform .14s ease,border-color .14s ease,background .14s ease}.official:hover,.official:focus-visible{transform:translateY(-1px);border-color:var(--accent);background:color-mix(in srgb,var(--accent) 16%,transparent);outline:none}'
    )
    ps=ps.replace(
        '<span class="official <?=$test?\'test\':\'\'?>">',
        '<span class="official <?=$test?\'test\':\'\'?>" data-dc-fullscreen-ready="1" role="button" tabindex="0" title="Enter full screen" aria-label="Enter full screen">'
    )
    fullscreen_script=r'''<script>
(function(){
 const badge=document.querySelector('[data-dc-fullscreen-ready="1"]');
 if(!badge)return;
 const enter=()=>{if(document.fullscreenElement)return;const root=document.documentElement,request=root.requestFullscreen||root.webkitRequestFullscreen;if(request){const result=request.call(root);if(result&&typeof result.catch==='function')result.catch(()=>{});}};
 badge.addEventListener('click',enter);
 badge.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();enter();}});
})();
</script>'''
    if '</body>' not in ps: raise SystemExit('projector body close not found')
    ps=ps.replace('</body>',fullscreen_script+'\n</body>',1)
    projector.write_text(ps)

# 5) Dance Cup control: add J&J-equivalent format choices and an audience-open action.
control=Path('admin/dance-cup/projection-control.php')
cs=control.read_text()
if 'id="dcScreenFormat"' not in cs:
    anchor='<?php if($changed===\'theme\'):?><div class="console-status mb-3">✓ Premium background applied to the live projector.</div><?php elseif($changed===\'effect\'):?><div class="console-status mb-3">✓ Presentation effect sent to the live projector.</div><?php endif;?>'
    if anchor not in cs: raise SystemExit('Dance Cup projection-control insert anchor not found')
    format_card=r'''<section class="card border-0 shadow-sm mb-4"><div class="card-body p-3 p-md-4"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><span class="badge text-bg-primary">SCREEN &amp; LAYOUT</span><h2 class="h4 mt-2 mb-1">Projector Size</h2><p class="text-muted mb-0">Main venue target is 16:9 4K. Other formats open with the same responsive Dance Cup presentation and safe area.</p></div><span class="badge text-bg-dark">10% TOP/BOTTOM · 5% LEFT/RIGHT</span></div><div class="row g-2 align-items-end mt-2"><div class="col-md-4"><label class="form-label fw-bold" for="dcScreenFormat">Screen Format</label><select id="dcScreenFormat" class="form-select"><option value="16:9" selected>16:9 Landscape · Main 4K</option><option value="9:16">9:16 Portrait</option><option value="4:3">4:3 Standard</option><option value="16:10">16:10 Landscape</option><option value="21:9">21:9 Ultra-wide</option><option value="32:9">32:9 Super-wide</option><option value="1:1">1:1 Square</option><option value="custom">Custom Resolution</option></select></div><div class="col-md-2"><label class="form-label" for="dcCustomWidth">Custom Width</label><input id="dcCustomWidth" class="form-control" type="number" min="100" value="1920"></div><div class="col-md-2"><label class="form-label" for="dcCustomHeight">Custom Height</label><input id="dcCustomHeight" class="form-control" type="number" min="100" value="1080"></div><div class="col-md-4"><button type="button" id="dcOpenProjector" class="btn btn-primary w-100">Open Projector</button></div></div><div class="small text-muted mt-2">On the audience screen, click <strong>BDC · Official Live Display</strong> to enter browser fullscreen exactly like J&amp;J.</div></div></section>'''
    cs=cs.replace(anchor,anchor+format_card,1)
    open_script=r'''<script>
(function(){
 const button=document.getElementById('dcOpenProjector'),format=document.getElementById('dcScreenFormat'),width=document.getElementById('dcCustomWidth'),height=document.getElementById('dcCustomHeight');
 if(!button||!format)return;
 const sizes={'16:9':[1920,1080],'9:16':[900,1600],'4:3':[1280,960],'16:10':[1440,900],'21:9':[1680,720],'32:9':[1920,540],'1:1':[1000,1000]};
 function applyPreset(){if(format.value==='custom')return;const size=sizes[format.value]||sizes['16:9'];width.value=size[0];height.value=size[1];}
 format.addEventListener('change',applyPreset);
 button.addEventListener('click',()=>{const w=Math.max(100,Number(width.value)||1920),h=Math.max(100,Number(height.value)||1080),url=new URL(<?=json_encode($projector)?>,location.href);url.searchParams.set('format',format.value);url.searchParams.set('width',String(w));url.searchParams.set('height',String(h));window.open(url.toString(),'_blank','noopener,width='+w+',height='+h);});
})();
</script>'''
    if '</body>' not in cs: raise SystemExit('projection-control body close not found')
    cs=cs.replace('</body>',open_script+'\n</body>',1)
    control.write_text(cs)

# 6) Dance Cup category-aware directory search. WDC registrations rank first; BDC fallback remains available.
directory=Path('admin/dance-cup/directory-search.php')
directory.write_text(r'''<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\DanceCupScoringService;
use App\Services\JudgeDirectoryService;

Auth::requireAdmin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$term = trim((string) ($_GET['q'] ?? ''));
$type = (string) ($_GET['type'] ?? 'competitor');
$competitionId = (int) ($_GET['competition_id'] ?? 0);
$test = (string) ($_GET['data_mode'] ?? '') === 'test';
$termLength = function_exists('mb_strlen') ? mb_strlen($term, 'UTF-8') : strlen($term);
if ($termLength < 1) { echo json_encode(['ok' => true, 'items' => []]); exit; }

$lower = static fn(string $value): string => function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
$normal = static fn(string $value): string => preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';

try {
    $pdo = Database::connection();
    if ($type === 'judge') {
        $rows = JudgeDirectoryService::search($pdo, $term, 100);
        $items = array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) ($row['judge_code'] ?? ''),
            'name' => (string) ($row['display_name'] ?: $row['full_name']),
            'meta' => trim(implode(' · ', array_filter([(string) ($row['judge_code'] ?? ''), (string) ($row['country'] ?? '')]))),
        ], $rows);
    } else {
        $category = null;
        if ($competitionId > 0) {
            $tables = DanceCupScoringService::tables($test);
            $categoryQuery = $pdo->prepare("SELECT id,category_name,entry_type,dance_style,competition_level,gender_eligibility FROM {$tables['competitions']} WHERE id=:id LIMIT 1");
            $categoryQuery->execute(['id' => $competitionId]);
            $category = $categoryQuery->fetch() ?: null;
        }

        $contains = '%' . $term . '%';
        $wdc = $pdo->prepare("SELECT w.id wdc_identity_id,w.identity_code,w.entry_type,w.display_name,w.country,w.solo_competitor_id,c.gender,GROUP_CONCAT(CONCAT(r.event_key,':',r.category_key) ORDER BY r.event_key,r.category_key SEPARATOR ' | ') registrations FROM bdc_wdc_identities w LEFT JOIN bdc_competitors c ON c.id=w.solo_competitor_id LEFT JOIN bdc_wdc_registrations r ON r.wdc_identity_id=w.id AND r.status='registered' WHERE w.status='active' AND (LOWER(w.display_name) LIKE LOWER(:contains) OR LOWER(w.identity_code) LIKE LOWER(:contains2) OR LOWER(COALESCE(w.country,'')) LIKE LOWER(:contains3) OR LOWER(COALESCE(r.category_key,'')) LIKE LOWER(:contains4) OR LOWER(COALESCE(r.event_key,'')) LIKE LOWER(:contains5)) GROUP BY w.id,w.identity_code,w.entry_type,w.display_name,w.country,w.solo_competitor_id,c.gender ORDER BY LOWER(w.display_name),w.id LIMIT 100");
        $wdc->execute(['contains'=>$contains,'contains2'=>$contains,'contains3'=>$contains,'contains4'=>$contains,'contains5'=>$contains]);
        $wdcRows = $wdc->fetchAll();
        $ranked = [];
        $seenCompetitors = [];
        foreach ($wdcRows as $row) {
            if ($category) {
                $gender=(string)($row['gender']??'');
                if ($category['gender_eligibility']==='female_only' && $gender!=='' && $gender!=='female') continue;
                if ($category['gender_eligibility']==='male_only' && $gender!=='' && $gender!=='male') continue;
            }
            $registrations=(string)($row['registrations']??'');
            $hay=$normal($registrations.' '.(string)$row['entry_type']);
            $score=0;
            if ($category) {
                $categoryName=$normal((string)$category['category_name']);
                $entry=$normal((string)$category['entry_type']);
                $style=$normal((string)$category['dance_style']);
                $level=$normal((string)$category['competition_level']);
                if ($entry!=='' && $normal((string)$row['entry_type'])===$entry) $score+=30;
                if ($categoryName!=='' && str_contains($hay,$categoryName)) $score+=80;
                if ($style!=='' && str_contains($hay,$style)) $score+=20;
                if ($level!=='' && str_contains($hay,$level)) $score+=10;
                if ($registrations!=='') $score+=8;
            }
            $metaParts=[(string)$row['identity_code'],(string)($row['country']??''),ucwords(str_replace('_',' ',(string)$row['entry_type']))];
            if ($registrations!=='') $metaParts[]='Registered: '.$registrations;
            if ($category && $score>=30) array_unshift($metaParts,'CATEGORY MATCH');
            $ranked[]=['score'=>$score,'item'=>[
                'id'=>(int)($row['solo_competitor_id']??0),
                'wdc_identity_id'=>(int)$row['wdc_identity_id'],
                'code'=>(string)$row['identity_code'],
                'name'=>(string)$row['display_name'],
                'meta'=>trim(implode(' · ',array_filter($metaParts))),
                'category_match'=>$score>=30,
            ]];
            if ((int)($row['solo_competitor_id']??0)>0) $seenCompetitors[(int)$row['solo_competitor_id']]=true;
        }
        usort($ranked, static fn(array $a,array $b):int => ($b['score']<=>$a['score']) ?: strcasecmp((string)$a['item']['name'],(string)$b['item']['name']));
        $items=array_map(static fn(array $row):array=>$row['item'],$ranked);

        // Keep active BDC profiles as a lower-priority fallback, while respecting the current category gender gate.
        $sql="SELECT id,bdc_id,exact_name,country,gender FROM bdc_competitors WHERE status<>'archived' AND (LOWER(exact_name) LIKE LOWER(:contains) OR LOWER(bdc_id) LIKE LOWER(:prefix))";
        if ($category && $category['gender_eligibility']==='female_only') $sql.=" AND gender='female'";
        if ($category && $category['gender_eligibility']==='male_only') $sql.=" AND gender='male'";
        $sql.=" ORDER BY CASE WHEN LOWER(exact_name) LIKE LOWER(:starts) THEN 0 ELSE 1 END,LOWER(exact_name),id LIMIT 100";
        $query=$pdo->prepare($sql);$query->execute(['contains'=>$contains,'prefix'=>$term.'%','starts'=>$term.'%']);
        foreach($query->fetchAll() as $row){
            if(isset($seenCompetitors[(int)$row['id']]))continue;
            $items[]=['id'=>(int)$row['id'],'wdc_identity_id'=>0,'code'=>(string)($row['bdc_id']??''),'name'=>(string)$row['exact_name'],'meta'=>trim(implode(' · ',array_filter(['BDC PROFILE',(string)($row['bdc_id']??''),(string)($row['country']??'')]))),'category_match'=>false];
        }
        $items=array_slice($items,0,100);
    }
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Dance Cup directory search failed: '.$error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Directory search is temporarily unavailable.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
''')

# Make the directory client pass current category context automatically.
replace_once(
    'public/js/dance-cup-directory.js',
    "endpoint.searchParams.set('type',type);endpoint.searchParams.set('q',q);const response=await fetch(endpoint",
    "endpoint.searchParams.set('type',type);endpoint.searchParams.set('q',q);if(type==='competitor'){const params=new URLSearchParams(location.search);if(params.get('id'))endpoint.searchParams.set('competition_id',params.get('id'));if(params.get('data_mode')==='test')endpoint.searchParams.set('data_mode','test');}const response=await fetch(endpoint"
)

# Make the UI explicit that Dance Cup category/WDC registrations are searched first.
replace_once(
    'app/Views/admin/dance-cup-automatic-page.php',
    'placeholder="Type competitor name or BDC ID" data-directory-type="competitor"',
    'placeholder="Type name, WDC ID or BDC ID" data-directory-type="competitor"'
)
replace_once(
    'app/Views/admin/dance-cup-automatic-page.php',
    'Choose a database suggestion to link the BDC profile.',
    'Current Dance Cup category registrations and WDC identities are shown first; BDC profiles remain available as fallback.'
)

# Prevent duplicate manual/WDC-name rows when a WDC identity is not linked to a BDC person.
replace_once(
    'admin/dance-cup/automatic-setup.php',
    "if($name===''||$number<1)throw new RuntimeException('Contestant name and number are required.');\n            $q=$pdo->prepare(\"INSERT INTO {$prefix}_entries(competition_id,competitor_id,bib_number,display_name) VALUES(:competition,:directory,:number,:name)\");",
    "if($name===''||$number<1)throw new RuntimeException('Contestant name and number are required.');\n            if($directoryCompetitorId<1){$duplicateName=$pdo->prepare(\"SELECT COUNT(*) FROM {$prefix}_entries WHERE competition_id=:competition AND status='active' AND LOWER(TRIM(display_name))=LOWER(TRIM(:name))\");$duplicateName->execute(['competition'=>$id,'name'=>$name]);if((int)$duplicateName->fetchColumn()>0)throw new RuntimeException('This contestant is already assigned to this category.');}\n            $q=$pdo->prepare(\"INSERT INTO {$prefix}_entries(competition_id,competitor_id,bib_number,display_name) VALUES(:competition,:directory,:number,:name)\");"
)

# Version / release note.
vpath=Path('VERSION.json')
data=json.loads(vpath.read_text())
if data.get('version')!='2.3.3-dev674' or int(data.get('build',0))!=3380:
    raise SystemExit(f"Unexpected release base: {data.get('version')} build {data.get('build')}")
data['version']='2.3.3-dev675'
data['build']=3381
features=data.setdefault('features',[])
features.insert(0,'Projection and Dance Cup audience release: callback countdowns blank the underlying projector, Finalist Couples and both Final placement screens use the proven Heats safe-area/readability rules, Dance Cup adds J&J-style screen-format choices and click-to-fullscreen Official Live Display, and Automatic Dance Cup contestant search prioritizes current-category WDC registrations with BDC fallback.')
vpath.write_text(json.dumps(data,ensure_ascii=False,indent=2)+"\n")
PY

php -l live-display/index.php
php -l live-display/feed.php
php -l live-display/final-relative-placement.php
php -l admin/dance-cup/projector.php
php -l admin/dance-cup/projection-control.php
php -l admin/dance-cup/directory-search.php
php -l admin/dance-cup/automatic-setup.php
php -l app/Views/admin/dance-cup-automatic-page.php
node --check public/js/dance-cup-directory.js

git config user.name "BDC Release Bot"
git config user.email "actions@users.noreply.github.com"
git add live-display/index.php live-display/feed.php live-display/final-relative-placement.php public/css/projector-safe-v616.css admin/dance-cup/projector.php admin/dance-cup/projection-control.php admin/dance-cup/directory-search.php admin/dance-cup/automatic-setup.php app/Views/admin/dance-cup-automatic-page.php public/js/dance-cup-directory.js VERSION.json
if git diff --cached --quiet; then
  echo "No changes to commit"
  exit 0
fi
git commit -m "Release dev675 projection and Dance Cup audience fixes"
git push origin HEAD:develop
