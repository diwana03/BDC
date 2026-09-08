#!/usr/bin/env bash
set -euo pipefail

python3 <<'PY'
from pathlib import Path
import json

# 1) Final Relative Placement: keep one normal Couple table cell, make the
# invisible person wrappers structural only, and pin & to the exact center.
p = Path('public/css/projector-safe-v616.css')
s = p.read_text()
block = r'''

/* dev705 Final Relative Placement: one Couple cell, no visible inner cells.
   The equal left/right tracks keep the gold ampersand on the exact geometric
   centerline while BIB, real flag and first name remain a single identity row. */
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-row{
  display:grid!important;
  grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)!important;
  align-items:center!important;
  column-gap:clamp(8px,.48vw,13px)!important;
  width:100%!important;
  min-width:0!important;
  border:0!important;
  outline:0!important;
  background:transparent!important;
  box-shadow:none!important;
  padding:0!important;
  margin:0!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-person{
  min-width:0!important;
  width:100%!important;
  display:grid!important;
  grid-template-columns:auto auto minmax(0,1fr)!important;
  align-items:center!important;
  column-gap:clamp(6px,.38vw,10px)!important;
  white-space:nowrap!important;
  overflow:hidden!important;
  border:0!important;
  outline:0!important;
  background:transparent!important;
  box-shadow:none!important;
  padding:0!important;
  margin:0!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-leader{
  grid-column:1!important;
  justify-self:stretch!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-follower{
  grid-column:3!important;
  justify-self:stretch!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-person>strong,
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-person>.final-matrix-flag{
  min-width:0!important;
  border-left:0!important;
  border-right:0!important;
  box-shadow:none!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-couple-person>.final-couple-name{
  min-width:0!important;
  width:100%!important;
  overflow:hidden!important;
  text-overflow:ellipsis!important;
  white-space:nowrap!important;
  border:0!important;
  background:transparent!important;
  box-shadow:none!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-final) .matrix-final .final-matrix-amp{
  grid-column:2!important;
  justify-self:center!important;
  align-self:center!important;
  width:auto!important;
  min-width:0!important;
  margin:0!important;
  padding:0 clamp(3px,.2vw,6px)!important;
  border:0!important;
  outline:0!important;
  background:transparent!important;
  box-shadow:none!important;
}
'''
if 'dev705 Final Relative Placement: one Couple cell, no visible inner cells.' not in s:
    s += block
p.write_text(s)

# 2) Dance Cup detailed Final Result: add resolved Chief Judge tie audit only.
p = Path('admin/dance-cup/judge-sheet.php')
s = p.read_text()
use_old = 'use App\\Services\\DanceCupScoringService;'
use_new = 'use App\\Services\\DanceCupScoringService;\nuse App\\Services\\DanceCupTieService;'
if 'use App\\Services\\DanceCupTieService;' not in s:
    if use_old not in s:
        raise SystemExit('Dance Cup TieService import target not found')
    s = s.replace(use_old, use_new, 1)

result_anchor = '''foreach($query->fetchAll() as $result)$resultByEntry[(int)$result['entry_id']]=$result;\n$rankedEntries=$entries;'''
result_insert = '''foreach($query->fetchAll() as $result)$resultByEntry[(int)$result['entry_id']]=$result;\n\n// dev705: resolved Chief Judge tie decisions are official result evidence.\n// Never expose private judge/CJ comments in this summary.\n$resolvedTies=[];\nforeach(DanceCupTieService::ties($pdo,$competitionId,$test) as $tie){\n $task=$tie['task']??null;\n if((string)($task['status']??'')!=='resolved')continue;\n $ordered=json_decode((string)($task['resolved_order_json']??''),true);\n if(!is_array($ordered)||!$ordered)continue;\n $entryById=[];\n foreach(($tie['entries']??[]) as $tieEntry)$entryById[(int)$tieEntry['entry_id']]=$tieEntry;\n $order=[];$basePlace=(int)($tie['base_place']??0);\n foreach(array_values($ordered) as $index=>$entryId){\n  $tieEntry=$entryById[(int)$entryId]??null;if(!$tieEntry)continue;\n  $order[]='#'.($basePlace+$index).' · No. '.(int)$tieEntry['bib_number'].' · '.(string)$tieEntry['display_name'];\n }\n if(!$order)continue;\n $resolvedAt=trim((string)($task['resolved_at']??''));\n $resolvedTies[]=[\n  'score'=>(float)($tie['score']??0),\n  'chief_name'=>trim((string)($task['chief_name']??''))?:'Chief Judge',\n  'resolved_at'=>$resolvedAt!==''?date('j M Y H:i',strtotime($resolvedAt)):'',\n  'order'=>$order,\n ];\n}\n$rankedEntries=$entries;'''
if 'dev705: resolved Chief Judge tie decisions are official result evidence.' not in s:
    if result_anchor not in s:
        raise SystemExit('Dance Cup result anchor not found')
    s = s.replace(result_anchor, result_insert, 1)

style_anchor = '.summary-table .contestant{width:52mm}.summary-table .number{width:18mm}.summary-table .placement{width:18mm;font-size:11pt;font-weight:900;color:var(--wine)}.summary-table .combined{width:24mm;font-weight:900;background:#fff8e8}.summary-intro{display:flex;justify-content:space-between;gap:8mm;margin:4mm 0 3mm;font-size:8pt;color:var(--muted)}'
style_new = style_anchor + '\n.tie-audit{margin-top:4mm;border:1px solid #c7a45a;border-left:4px solid var(--wine);background:#fffaf0;padding:3mm 4mm}.tie-audit h3{margin:0 0 2mm;font-size:10pt;color:var(--wine);text-transform:uppercase;letter-spacing:.35px}.tie-row{display:grid;grid-template-columns:30mm 42mm 1fr;gap:4mm;align-items:start;padding:1.5mm 0;border-top:1px solid #e8dcc3;font-size:8pt}.tie-row:first-of-type{border-top:0}.tie-order{font-weight:700;line-height:1.35}.tie-meta{color:var(--muted);font-size:7.5pt;line-height:1.35}'
if '.tie-audit{' not in s:
    if style_anchor not in s:
        raise SystemExit('Dance Cup tie style anchor not found')
    s = s.replace(style_anchor, style_new, 1)

summary_anchor = ''' </table>\n <footer class="footer"><div class="signature"><b>Scoring Administrator / Witness</b></div><div></div><div class="note">Final result. Individual judge criterion pages follow. <?=$test?'<span class="test">TEST DATA</span>':''?></div></footer>'''
tie_section = ''' </table>\n <?php if($resolvedTies):?>\n <section class="tie-audit" aria-label="Chief Judge tie decisions">\n  <h3>Chief Judge Tie Decision</h3>\n  <?php foreach($resolvedTies as $tie):?>\n  <div class="tie-row">\n   <div><strong>Tied score</strong><br><?=e(dcSheetNumber((float)$tie['score']))?></div>\n   <div class="tie-meta"><strong><?=e($tie['chief_name'])?></strong><br>Chief Judge<?php if($tie['resolved_at']!==''):?><br><?=e($tie['resolved_at'])?><?php endif;?></div>\n   <div class="tie-order">Final order: <?=e(implode(' → ',$tie['order']))?></div>\n  </div>\n  <?php endforeach;?>\n </section>\n <?php endif;?>\n <footer class="footer"><div class="signature"><b>Scoring Administrator / Witness</b></div><div></div><div class="note">Final result. Individual judge criterion pages follow. <?=$test?'<span class="test">TEST DATA</span>':''?></div></footer>'''
if 'aria-label="Chief Judge tie decisions"' not in s:
    if summary_anchor not in s:
        raise SystemExit('Dance Cup summary table footer anchor not found')
    s = s.replace(summary_anchor, tie_section, 1)
p.write_text(s)

# 3) Release metadata.
p = Path('VERSION.json')
d = json.loads(p.read_text())
d['version'] = '2.3.6-dev705'
d['build'] = 3411
features = d.setdefault('features', [])
new_features = [
 'Dance Cup Final Result tie evidence: the detailed final-result sheet now shows resolved Chief Judge tie score, Chief Judge name, final order and resolution time, while private comments remain excluded and scores are unchanged.',
 'Final Relative Placement visual cleanup: keeps the proven eight-judge pagination, removes any visible inner Couple subcells, and locks the gold ampersand to the exact center between equal Lead and Follow identity tracks without changing scoring or result data.'
]
for feature in reversed(new_features):
    if feature not in features:
        features.insert(0, feature)
p.write_text(json.dumps(d, indent=2, ensure_ascii=False) + '\n')
PY

php -l admin/dance-cup/judge-sheet.php
php -l live-display/feed.php

python3 <<'PY'
from pathlib import Path
import json
css = Path('public/css/projector-safe-v616.css').read_text()
sheet = Path('admin/dance-cup/judge-sheet.php').read_text()
feed = Path('live-display/feed.php').read_text()
ver = json.loads(Path('VERSION.json').read_text())
assert 'dev705 Final Relative Placement: one Couple cell, no visible inner cells.' in css
assert 'grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)!important' in css
assert 'grid-column:2!important' in css
assert 'aria-label="Chief Judge tie decisions"' in sheet
assert "(string)($task['status']??'')!=='resolved'" in sheet
assert "resolved_order_json" in sheet and "chief_name" in sheet and "resolved_at" in sheet
assert 'Final order:' in sheet
# dev700 judge pagination remains in the active Final matrix code; dev705 never rewrites feed.php.
assert 'final-couple-person final-couple-leader' in feed
assert 'final-couple-person final-couple-follower' in feed
assert ver['version'] == '2.3.6-dev705'
assert ver['build'] == 3411
print('dev705 Final matrix + Dance Cup tie report assertions passed')
PY

git diff --check
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add public/css/projector-safe-v616.css admin/dance-cup/judge-sheet.php VERSION.json
git commit -m 'Release dev705 fix Final matrix and add CJ tie report'
git push origin HEAD:develop
