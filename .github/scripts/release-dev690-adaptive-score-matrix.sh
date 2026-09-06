#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path

feed=Path('live-display/feed.php')
s=feed.read_text()
old='<table class="score-table matrix matrix-heats"><thead><tr><th>Prov.</th><th>BIB</th><th class="name">Competitor</th>'
new='<table class="score-table matrix matrix-heats matrix-judge-count-<?=count($roleJudges)?>"><thead><tr><th>Prov.</th><th>BIB</th><th class="name">Competitor</th>'
if old not in s:
    raise SystemExit('matrix table anchor not found')
s=s.replace(old,new,1)
feed.write_text(s)

css=Path('public/css/projector-safe-v616.css')
c=css.read_text()
block=r'''

/* dev690 adaptive Heats score matrix.
   Preserve projector safe area and scale judge columns without dropping below
   audience-readable typography. */
body[data-screen-type="score_matrix"]:has(.matrix-heats) .stage{
  padding-top:10cqh!important;
  padding-bottom:10cqh!important;
  padding-left:5cqw!important;
  padding-right:5cqw!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .projection-official{top:10cqh!important;right:5cqw!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .projection-heading-row{
  margin-bottom:clamp(6px,.8cqh,12px)!important;
  gap:clamp(12px,1cqw,22px)!important;
}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .projection-heading-row>.projection-brand{width:clamp(72px,7.2cqh,108px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .event{font-size:clamp(24px,1.72vw,42px)!important;line-height:1.02!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .meta{font-size:clamp(13px,.86vw,20px)!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .title{font-size:clamp(24px,1.85vw,44px)!important;margin:.08em 0!important}
body[data-screen-type="score_matrix"]:has(.matrix-heats) .matrix-role{font-size:clamp(14px,1.02vw,23px)!important;min-height:clamp(32px,3.5cqh,44px)!important}

/* Base: 1–5 active judges on that role. */
body[data-screen-type="score_matrix"] .matrix-heats[class*="matrix-judge-count-"] th:nth-child(n+4):not(:last-child){
  font-size:clamp(17px,1.02vw,24px)!important;
  line-height:1!important;
  padding-left:.18em!important;
  padding-right:.18em!important;
}
body[data-screen-type="score_matrix"] .matrix-heats[class*="matrix-judge-count-"] th:nth-child(n+4):not(:last-child) small{
  font-size:clamp(10px,.58vw,13px)!important;
  line-height:1!important;
  max-width:100%!important;
  overflow:hidden!important;
  text-overflow:ellipsis!important;
  white-space:nowrap!important;
}
body[data-screen-type="score_matrix"] .matrix-heats[class*="matrix-judge-count-"] td:nth-child(n+4):not(:last-child){font-size:clamp(18px,1.08vw,26px)!important}

/* 6–7 judges: tighten names/marks modestly, still projector readable. */
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-6 th:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-7 th:nth-child(n+4):not(:last-child){font-size:clamp(16px,.9vw,21px)!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-6 th:nth-child(n+4):not(:last-child) small,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-7 th:nth-child(n+4):not(:last-child) small{font-size:clamp(10px,.52vw,12px)!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-6 td:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-7 td:nth-child(n+4):not(:last-child){font-size:clamp(17px,.96vw,23px)!important}

/* 8–9 judges: compress column padding and competitor width before shrinking text. */
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 th:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 td:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 th:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 td:nth-child(3){width:30%!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 th:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 th:nth-child(n+4):not(:last-child){font-size:clamp(15px,.82vw,19px)!important;padding-left:.1em!important;padding-right:.1em!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 th:nth-child(n+4):not(:last-child) small,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 th:nth-child(n+4):not(:last-child) small{font-size:clamp(9px,.48vw,11px)!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-8 td:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-9 td:nth-child(n+4):not(:last-child){font-size:clamp(16px,.88vw,21px)!important;padding-left:.08em!important;padding-right:.08em!important}

/* 10–12 judges: hard readability floor. Never go below 14px judge header / 15px marks. */
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 th:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 td:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 th:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 td:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 th:nth-child(3),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 td:nth-child(3){width:27%!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 th:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 th:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 th:nth-child(n+4):not(:last-child){font-size:clamp(14px,.74vw,17px)!important;padding-left:.04em!important;padding-right:.04em!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 th:nth-child(n+4):not(:last-child) small,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 th:nth-child(n+4):not(:last-child) small,
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 th:nth-child(n+4):not(:last-child) small{font-size:clamp(9px,.44vw,10px)!important}
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-10 td:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-11 td:nth-child(n+4):not(:last-child),
body[data-screen-type="score_matrix"] .matrix-heats.matrix-judge-count-12 td:nth-child(n+4):not(:last-child){font-size:clamp(15px,.8vw,19px)!important;padding-left:.03em!important;padding-right:.03em!important}
'''
if 'dev690 adaptive Heats score matrix' not in c:
    c += block
css.write_text(c)
PY
php -l live-display/feed.php
python3 - <<'PY'
from pathlib import Path
s=Path('live-display/feed.php').read_text()
c=Path('public/css/projector-safe-v616.css').read_text()
assert 'matrix-judge-count-<?=count($roleJudges)?>' in s
assert 'dev690 adaptive Heats score matrix' in c
assert 'padding-top:10cqh!important' in c
assert 'matrix-judge-count-12' in c
assert 'clamp(14px,.74vw,17px)' in c
print('dev690 assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add live-display/feed.php public/css/projector-safe-v616.css
git commit -m 'Release dev690 adaptive score matrix by judge count'
git push origin HEAD:develop
