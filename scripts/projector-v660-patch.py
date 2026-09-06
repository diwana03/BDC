from pathlib import Path
import json
import re

p = Path('live-display/feed.php')
s = p.read_text()

gate = 'elseif ($type === "matching"): ?><style>.matching-list'
if s.count(gate) != 1:
    raise SystemExit(f'matching gate count={s.count(gate)}')
s = s.replace(gate, 'elseif (in_array($type, ["matching", "matching_couples"], true)): ?><style>.matching-list', 1)

pattern = r'<style>\.matrix-wrap\{.*?</style><div class="matrix-wrap">'
css = '''<style>
.matrix-wrap{flex:1;min-height:0;overflow:hidden;display:flex;flex-direction:column;padding:0 0 .15%;container-type:size}
.provisional-label{flex:0 0 auto;color:#ffcf45;font-weight:900;letter-spacing:.08em;margin:0 0 .22em;font-size:max(9px,min(.7cqw,1.2cqh))}
.score-table.matrix{width:100%;border-collapse:collapse;table-layout:fixed;background:rgba(17,24,39,.82)}
.score-table.matrix th,.score-table.matrix td{text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1}
.score-table.matrix th.name,.score-table.matrix td.name{text-align:left}
.score-table.matrix td.not-applicable{color:#94a3b8;background:rgba(148,163,184,.08);font-weight:850;letter-spacing:.04em}
.matrix-final{flex:1;min-height:0;height:100%;font-size:max(11px,min(.98cqw,1.68cqh))}
.matrix-final thead{height:7%}.matrix-final tbody{height:93%}.matrix-final thead tr{height:100%}.matrix-final tbody tr{height:8.333%}
.matrix-final th,.matrix-final td{padding:0 .3em!important}
.matrix-final th:first-child,.matrix-final td:first-child{width:10%;font-size:1.02em;font-weight:900}
.matrix-final th:nth-child(2),.matrix-final td:nth-child(2){width:40%;font-size:1.04em;font-weight:950;text-align:left}
.matrix-final th:nth-child(n+3){font-size:.8em}.matrix-final th:nth-child(n+3) small{font-size:.66em;display:block;overflow:hidden;text-overflow:ellipsis}.matrix-final td:nth-child(n+3){font-size:1.04em;font-weight:900}
.matrix-split{flex:1;min-height:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1%}
.matrix-panel{min-width:0;min-height:0;overflow:hidden;display:flex;flex-direction:column}
.matrix-role{flex:0 0 auto;display:flex;justify-content:space-between;gap:1em;background:#111827;border:1px solid rgba(255,255,255,.35);padding:.34em .52em;font-size:max(11px,min(.88cqw,1.52cqh));font-weight:950;letter-spacing:.07em;white-space:nowrap}
.matrix-heats{flex:1;min-height:0;height:100%;font-size:max(9px,min(.7cqw,1.22cqh))}
.matrix-heats thead{height:8%}.matrix-heats tbody{height:92%}.matrix-heats thead tr{height:100%}.matrix-heats tbody tr{height:auto}
.matrix-heats th,.matrix-heats td{padding:0 .22em!important}
.matrix-heats th:first-child,.matrix-heats td:first-child{width:9%;font-weight:900}
.matrix-heats th:nth-child(2),.matrix-heats td:nth-child(2){width:31%;font-size:1.05em;font-weight:950;text-align:left}
.matrix-heats th:nth-child(n+3){font-size:.78em}.matrix-heats th:nth-child(n+3) small{font-size:.62em;display:block;overflow:hidden;text-overflow:ellipsis}.matrix-heats td:nth-child(n+3){font-size:1.03em;font-weight:900}
</style><div class="matrix-wrap">'''

s, n = re.subn(pattern, css, s, count=1, flags=re.S)
if n != 1:
    raise SystemExit(f'matrix block count={n}')

final_anchor = '<table class="score-table matrix"><thead><tr><th>Place</th><th>Couple</th>'
heats_anchor = '<table class="score-table matrix"><thead><tr><th>Prov.</th><th class="name">Competitor</th>'
if s.count(final_anchor) != 1:
    raise SystemExit(f'final table count={s.count(final_anchor)}')
if s.count(heats_anchor) != 1:
    raise SystemExit(f'heats table count={s.count(heats_anchor)}')
s = s.replace(final_anchor, '<table class="score-table matrix matrix-final"><thead><tr><th>Place</th><th>Couple</th>', 1)
s = s.replace(heats_anchor, '<table class="score-table matrix matrix-heats"><thead><tr><th>Prov.</th><th class="name">Competitor</th>', 1)

replacements = {
    '.matching-list .matching-card{box-sizing:border-box;flex:0 0 calc(20% - .65%);height:calc((100% - 1.3%)/3);padding:clamp(5px,.7vw,13px);justify-content:space-evenly;min-height:0}': '.matching-list .matching-card{box-sizing:border-box;flex:0 0 calc(20% - .65%);height:calc((100% - 1.3%)/3);padding:clamp(3px,.42vw,8px);justify-content:center;gap:clamp(2px,.28vh,5px);min-height:0;overflow:hidden}',
    '.matching-photo{width:min(7vw,10vh)!important;height:min(7vw,10vh)!important;margin-bottom:.22em!important}': '.matching-photo{width:min(5.6vw,6.8vh)!important;height:min(5.6vw,6.8vh)!important;margin:0 0 .12em!important}',
    '.matching-name{min-width:0;font-size:clamp(16px,1.18vw,28px)!important;': '.matching-name{min-width:0;font-size:clamp(13px,.98vw,22px)!important;',
    '.matching-bib{font-size:clamp(19px,1.55vw,38px)!important;': '.matching-bib{font-size:clamp(16px,1.18vw,28px)!important;',
    '.matching-flag{flex:0 0 auto;width:clamp(27px,1.9vw,42px);': '.matching-flag{flex:0 0 auto;width:clamp(24px,1.55vw,34px);',
}
for old, new in replacements.items():
    if old not in s:
        raise SystemExit('missing matching style anchor: ' + old[:60])
    s = s.replace(old, new, 1)

p.write_text(s)

v = Path('VERSION.json')
d = json.loads(v.read_text())
if d.get('version') != '2.3.3-dev659' or d.get('build') != 3365:
    raise SystemExit(f'unexpected version {d.get("version")} build {d.get("build")}')
d['version'] = '2.3.3-dev660'
d['build'] = 3366
d.setdefault('features', []).insert(0, 'Consolidated repair of dev657-dev659 projector changes: active Emcee matching_couples renderer, separated Final and Heats matrix layouts, and guaranteed 12-row Final fit.')
v.write_text(json.dumps(d, indent=2, ensure_ascii=False) + '\n')

Path('RELEASE-v2.3.3-dev660.md').write_text('''# BDC v2.3.3-dev660 — projector audit repair

Build 3366

Repairs dev657-dev659 as one coherent implementation.

- Emcee matching now renders the normalized `matching_couples` screen.
- Final and Heats score matrices use separate bounded layouts instead of stacked overrides.
- Final matrix reserves all 12 finalist rows so Couple 12 cannot be clipped.
- Emcee cards retain the 5 / 5 / 2 board with safe photo, name, flag and BIB sizing.
- No scoring, pairing, judge, roster or result-data logic is changed.
''')

check = p.read_text()
for needle in ['matching_couples', 'matrix matrix-final', 'matrix matrix-heats', '.matrix-final tbody tr{height:8.333%}']:
    if needle not in check:
        raise SystemExit('post-patch missing ' + needle)
if 'body[data-screen-type="score_matrix"] .matrix-wrap>.score-table.matrix' in check:
    raise SystemExit('old final stacked override remains')
if 'body[data-screen-type="score_matrix"] .matrix-panel>.score-table.matrix' in check:
    raise SystemExit('old heats stacked override remains')
print('projector v660 patch: PASS')
