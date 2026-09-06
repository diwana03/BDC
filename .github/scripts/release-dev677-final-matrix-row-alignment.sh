#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
p = Path('live-display/feed.php')
s = p.read_text()
old_css = ".matrix-final .final-matrix-couple{display:flex!important;align-items:center;gap:clamp(6px,.42vw,10px);min-width:0;width:100%;white-space:nowrap;overflow:hidden}\n.matrix-final .final-matrix-person{display:flex!important;align-items:center;gap:clamp(5px,.34vw,8px);min-width:0;white-space:nowrap;overflow:hidden}"
new_css = ".matrix-final .final-matrix-couple{white-space:nowrap;overflow:hidden;vertical-align:middle!important}\n.matrix-final .final-matrix-couple-inner{display:flex;align-items:center;gap:clamp(6px,.42vw,10px);min-width:0;width:100%;height:100%;white-space:nowrap;overflow:hidden}\n.matrix-final .final-matrix-person{display:flex!important;align-items:center;gap:clamp(5px,.34vw,8px);min-width:0;white-space:nowrap;overflow:hidden}"
if old_css not in s:
    raise SystemExit('Expected final matrix CSS block not found')
s = s.replace(old_css, new_css, 1)
old_td = '<td class="name final-matrix-couple"><span class="final-matrix-person"><strong>BIB <?=(int)$x["leader_bib"]?></strong><?php if($leaderFlag=country_flag_url((string)($x["leader_country"]??""))):?><img class="final-matrix-flag" src="<?=e($leaderFlag)?>" alt="<?=e((string)$x["leader_country"])?> flag"><?php endif;?><span><?=e($cleanFinalProjectionName($x["leader_name"] ?? "", $x["leader_bib"] ?? null))?></span></span><span class="final-matrix-amp">&amp;</span><span class="final-matrix-person"><strong>BIB <?=(int)$x["follower_bib"]?></strong><?php if($followerFlag=country_flag_url((string)($x["follower_country"]??""))):?><img class="final-matrix-flag" src="<?=e($followerFlag)?>" alt="<?=e((string)$x["follower_country"])?> flag"><?php endif;?><span><?=e($cleanFinalProjectionName($x["follower_name"] ?? "", $x["follower_bib"] ?? null))?></span></span></td>'
new_td = '<td class="name final-matrix-couple"><div class="final-matrix-couple-inner"><span class="final-matrix-person"><strong>BIB <?=(int)$x["leader_bib"]?></strong><?php if($leaderFlag=country_flag_url((string)($x["leader_country"]??""))):?><img class="final-matrix-flag" src="<?=e($leaderFlag)?>" alt="<?=e((string)$x["leader_country"])?> flag"><?php endif;?><span><?=e($cleanFinalProjectionName($x["leader_name"] ?? "", $x["leader_bib"] ?? null))?></span></span><span class="final-matrix-amp">&amp;</span><span class="final-matrix-person"><strong>BIB <?=(int)$x["follower_bib"]?></strong><?php if($followerFlag=country_flag_url((string)($x["follower_country"]??""))):?><img class="final-matrix-flag" src="<?=e($followerFlag)?>" alt="<?=e((string)$x["follower_country"])?> flag"><?php endif;?><span><?=e($cleanFinalProjectionName($x["follower_name"] ?? "", $x["follower_bib"] ?? null))?></span></span></div></td>'
if old_td not in s:
    raise SystemExit('Expected final matrix couple cell not found')
s = s.replace(old_td, new_td, 1)
old_helper = "$cleanFinalProjectionName = static function (mixed $value, mixed $bib = null): string {\n    $name = trim((string) $value);\n    $bibNumber = (int) $bib;\n    if ($bibNumber > 0) {\n        $name = preg_replace('/^\\s*BIB\\s*' . preg_quote((string) $bibNumber, '/') . '\\b\\s*/i', '', $name) ?? $name;\n    }\n    $name = preg_replace('/^\\s*[A-Z]{2,3}\\s+(?=\\S)/u', '', $name) ?? $name;\n    $name = preg_replace('/\\s+/u', ' ', $name) ?? $name;\n    return trim($name);\n};"
new_helper = "$cleanFinalProjectionName = static function (mixed $value, mixed $bib = null): string {\n    $name = trim((string) $value);\n    $bibNumber = (int) $bib;\n    if ($bibNumber > 0) {\n        $name = preg_replace('/^\\s*(?:BIB\\s*)?' . preg_quote((string) $bibNumber, '/') . '\\b[\\s·|:,-]*/iu', '', $name) ?? $name;\n    }\n    // Projection already renders the real flag, so strip any leading flag emoji/country token from stored display names.\n    $name = preg_replace('/^\\s*(?:[\\x{1F1E6}-\\x{1F1FF}]{2}\\s*)+/u', '', $name) ?? $name;\n    $name = preg_replace('/^\\s*[A-Z]{2,3}(?:\\s*[·|:,-]\\s*|\\s+)(?=\\S)/u', '', $name) ?? $name;\n    $name = preg_replace('/\\s+/u', ' ', $name) ?? $name;\n    return trim($name);\n};"
if old_helper not in s:
    raise SystemExit('Expected projection name helper not found')
s = s.replace(old_helper, new_helper, 1)
p.write_text(s)

v = Path('VERSION.json')
import json
data = json.loads(v.read_text())
data['version'] = '2.3.3-dev677'
data['build'] = 3383
features = data.get('features', [])
msg = 'Final relative placement matrix row alignment restored: Couple remains a real table cell with an inner flex wrapper, and duplicated country tokens are stripped while preserving flags and current matrix sizing.'
if isinstance(features, list):
    features.append(msg)
else:
    data['features'] = [msg]
v.write_text(json.dumps(data, indent=2) + '\n')
PY
php -l live-display/feed.php

git add live-display/feed.php VERSION.json
git commit -m "Fix final matrix row alignment"
git push origin develop
