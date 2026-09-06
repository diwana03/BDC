#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
p=Path('public/css/projector-safe-v616.css')
s=p.read_text()
marker='/* dev691 hard projector safety + readable adaptive matrix */'
if marker not in s:
    s += r'''

/* dev691 hard projector safety + readable adaptive matrix */
/* HARD RULE: all audience projection content stays inside 10% top/bottom and 5% left/right. */
body[data-screen-type="judges"] .stage,
body[data-screen-type="score_matrix"]:has(.matrix-heats) .stage{
  padding-top:10cqh!important;
  padding-bottom:10cqh!important;
  padding-left:5cqw!important;
  padding-right:5cqw!important;
}
body[data-screen-type="judges"] .projection-official,
body[data-screen-type="score_matrix"]:has(.matrix-heats) .projection-official{
  top:10cqh!important;
  right:5cqw!important;
}

/* Judges board must use only the safe-area canvas. */
body[data-screen-type="judges"] .projection-heading-row{
  margin-bottom:clamp(8px,1cqh,14px)!important;
}
body[data-screen-type="judges"] .stage .list.judge-list{
  width:100%!important;
  max-width:100%!important;
  min-width:0!important;
}

/* Adaptive Heats matrix: reclaim width from non-judge columns first. */
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 th:first-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 td:first-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 th:first-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 td:first-child{width:6%!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 th:nth-child(2),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 td:nth-child(2),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 th:nth-child(2),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 td:nth-child(2){width:10%!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 th:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 td:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 th:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 td:nth-child(3){width:25%!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 th:last-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 td:last-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 th:last-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 td:last-child{width:8%!important}

body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 th:first-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 td:first-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 th:first-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 td:first-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 th:first-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 td:first-child{width:5.5%!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 th:nth-child(2),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 td:nth-child(2),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 th:nth-child(2),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 td:nth-child(2),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 th:nth-child(2),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 td:nth-child(2){width:9%!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 th:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 td:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 th:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 td:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 th:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 td:nth-child(3){width:22%!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 th:last-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 td:last-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 th:last-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 td:last-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 th:last-child,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 td:last-child{width:7.5%!important}

/* Human-readable floors: never collapse judge labels/marks into tiny text. */
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 th:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 th:nth-child(n+4):not(:last-child){
  font-size:clamp(15px,.82vw,19px)!important;
  padding-left:.06em!important;
  padding-right:.06em!important;
}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 th:nth-child(n+4):not(:last-child) small,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 th:nth-child(n+4):not(:last-child) small{
  font-size:clamp(12px,.58vw,14px)!important;
}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 td:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 td:nth-child(n+4):not(:last-child){
  font-size:clamp(17px,.9vw,21px)!important;
  padding-left:.04em!important;
  padding-right:.04em!important;
}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 th:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 th:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 th:nth-child(n+4):not(:last-child){
  font-size:clamp(14px,.72vw,17px)!important;
  padding-left:.02em!important;
  padding-right:.02em!important;
}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 th:nth-child(n+4):not(:last-child) small,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 th:nth-child(n+4):not(:last-child) small,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 th:nth-child(n+4):not(:last-child) small{
  font-size:clamp(11px,.52vw,13px)!important;
}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 td:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 td:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 td:nth-child(n+4):not(:last-child){
  font-size:clamp(16px,.82vw,19px)!important;
  padding-left:.02em!important;
  padding-right:.02em!important;
}
'''
    p.write_text(s)
PY
python3 <<'PY'
from pathlib import Path
s=Path('public/css/projector-safe-v616.css').read_text()
assert '/* dev691 hard projector safety + readable adaptive matrix */' in s
assert 'body[data-screen-type="judges"] .stage,' in s
assert 'padding-top:10cqh!important' in s
assert 'font-size:clamp(12px,.58vw,14px)!important' in s
assert 'font-size:clamp(11px,.52vw,13px)!important' in s
j=Path('judge-scoring/index.php').read_text()
assert "['leader'=>'Leaders','follower'=>'Followers'] as $sheetRole=>$sheetLabel" in j
assert '<strong><?=e($sheetLabel)?>:</strong> Choose <strong>' in j
print('dev691 assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add public/css/projector-safe-v616.css
git commit -m 'Release dev691 enforce projector safe area and readable matrix'
git push origin HEAD:develop
