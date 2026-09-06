#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json,re

def replace_once(path, old, new):
    p=Path(path); s=p.read_text()
    if old not in s:
        raise SystemExit(f'required pattern not found in {path}: {old[:120]!r}')
    p.write_text(s.replace(old,new,1))

helper = r'''$cleanFinalProjectionName = static function (mixed $value, mixed $bib = null): string {
    $name = trim((string) $value);
    $bibNumber = (int) $bib;
    if ($bibNumber > 0) {
        $name = preg_replace('/^\s*BIB\s*' . preg_quote((string) $bibNumber, '/') . '\b\s*/i', '', $name) ?? $name;
    }
    $name = preg_replace('/^\s*[A-Z]{2,3}\s+(?=\S)/u', '', $name) ?? $name;
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    return trim($name);
};
'''

# live-display/feed.php
p=Path('live-display/feed.php'); s=p.read_text()
if '$cleanFinalProjectionName = static function' not in s:
    anchor='$settings = ProjectionSettingsService::get($pdo, $roundId, $test);\n'
    if anchor not in s: raise SystemExit('feed helper anchor missing')
    s=s.replace(anchor, anchor+helper,1)
s=s.replace('<span><?=e((string)$x["leader_name"])?></span>', '<span><?=e($cleanFinalProjectionName($x["leader_name"] ?? "", $x["leader_bib"] ?? null))?></span>')
s=s.replace('<span><?=e((string)$x["follower_name"])?></span>', '<span><?=e($cleanFinalProjectionName($x["follower_name"] ?? "", $x["follower_bib"] ?? null))?></span>')
p.write_text(s)

# live-display/final-relative-placement.php
p=Path('live-display/final-relative-placement.php'); s=p.read_text()
if '$cleanFinalProjectionName = static function' not in s:
    anchor='$marks=[];\n'
    if anchor not in s: raise SystemExit('final relative helper anchor missing')
    s=s.replace(anchor, helper+'\n'+anchor,1)
s=s.replace("<span><?=e((string)$p['leader_name'])?></span>", "<span><?=e($cleanFinalProjectionName($p['leader_name'] ?? '', $p['leader_bib'] ?? null))?></span>")
s=s.replace("<span><?=e((string)$p['follower_name'])?></span>", "<span><?=e($cleanFinalProjectionName($p['follower_name'] ?? '', $p['follower_bib'] ?? null))?></span>")
p.write_text(s)

# version
vp=Path('VERSION.json'); data=json.loads(vp.read_text())
data['version']='2.3.3-dev676'; data['build']=3382
feature='Final projection couple identity cleanup: removes duplicated BIB prefixes and legacy 2/3-letter country-code prefixes from finalist names while preserving real flags, current 10%/5% safe area, matrix sizing, judge columns, Heats and scoring logic.'
features=data.setdefault('features',[])
if not features or features[0]!=feature: features.insert(0,feature)
vp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l live-display/feed.php
php -l live-display/final-relative-placement.php
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add live-display/feed.php live-display/final-relative-placement.php VERSION.json
git commit -m 'Release dev676 final couple identity cleanup'
git push origin HEAD:develop
