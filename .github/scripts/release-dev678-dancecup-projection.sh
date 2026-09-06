#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path
import json


def replace_once(path, old, new):
    p=Path(path); s=p.read_text()
    if old not in s:
        raise SystemExit(f'required pattern not found in {path}: {old[:120]!r}')
    p.write_text(s.replace(old,new,1))

# Exact Dance Cup projector screen shown at the venue.
p=Path('admin/dance-cup/projector.php')
s=p.read_text()

replace_once('admin/dance-cup/projector.php',
    '#app{width:100vw;height:100vh;padding:10vh 5vw;display:flex;flex-direction:column;gap:clamp(10px,1.2vh,20px)}',
    '#app{width:100vw;height:100vh;padding:clamp(18px,2.6vh,34px) clamp(26px,2.8vw,54px) clamp(20px,2.7vh,36px);display:flex;flex-direction:column;gap:clamp(8px,1vh,16px)}')
replace_once('admin/dance-cup/projector.php',
    '.top{width:78vw;max-width:100%;margin-inline:auto;display:grid;',
    '.top{width:100%;max-width:100%;margin-inline:auto;display:grid;')
replace_once('admin/dance-cup/projector.php',
    'padding:clamp(20px,3.2vh,48px) 1vw clamp(12px,1.8vh,26px)',
    'padding:clamp(8px,1.1vh,14px) 1vw clamp(7px,.9vh,12px)')

# The old identity rule is the reason Kazakhstan/New Zealand/South Korea were
# rendered vertically. Replace it structurally, not by hiding country text.
replace_once('admin/dance-cup/projector.php',
    '.identity-meta.multi-country{flex-wrap:wrap;align-content:center}.identity-country{display:inline-flex;flex-direction:column;align-items:center;justify-content:center;min-width:0;max-width:18%;line-height:1.05;text-align:center;overflow-wrap:anywhere}',
    '.identity-meta.multi-country{flex-wrap:wrap;align-content:center;justify-content:center}.identity-country{display:inline-flex;flex-direction:row;align-items:center;justify-content:center;gap:clamp(5px,.42vw,9px);min-width:0;max-width:100%;line-height:1.05;text-align:center;white-space:nowrap;overflow-wrap:normal;word-break:normal}.identity-country .flag-image{display:block;width:clamp(30px,2.15vw,46px);height:auto;aspect-ratio:3/2;object-fit:cover;border:1px solid rgba(255,255,255,.72);border-radius:3px;box-shadow:0 2px 5px #0005}.identity-country>.flag:empty{display:none}')

marker='/* dev678 exact Dance Cup audience boards */'
if marker not in s:
    block=r'''
/* dev678 exact Dance Cup audience boards */
.grid:has(>.contestant-card){
  grid-template-columns:repeat(5,minmax(0,1fr))!important;
  grid-template-rows:repeat(2,minmax(0,1fr))!important;
  grid-auto-rows:minmax(0,1fr)!important;
  gap:clamp(8px,.65vw,14px)!important;
  align-content:stretch!important;
}
.grid:has(>.contestant-card)>.contestant-card{min-height:0!important;padding:clamp(7px,.72vw,13px)!important}
.grid:has(>.contestant-card) .profile-photo{width:clamp(62px,6.1vh,92px)!important;height:clamp(62px,6.1vh,92px)!important;margin-bottom:clamp(2px,.32vh,5px)!important}
.grid:has(>.contestant-card) .kicker{font-size:clamp(9px,.58vw,13px)!important;line-height:1!important}
.grid:has(>.contestant-card) .bib{font-size:clamp(40px,4.1vw,78px)!important;line-height:.9!important}
.grid:has(>.contestant-card) .contestant-name{font-size:clamp(16px,1.2vw,25px)!important;line-height:1!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
.grid:has(>.contestant-card) .identity-meta{font-size:clamp(11px,.78vw,16px)!important;margin-top:clamp(3px,.42vh,6px)!important;gap:clamp(4px,.35vw,8px)!important;white-space:nowrap!important;overflow:hidden!important}
.grid:has(>.contestant-card) .identity-country{max-width:100%!important;flex:0 1 auto!important;overflow:hidden!important;text-overflow:ellipsis!important}
.grid:has(>.contestant-card) .identity-country>span:last-child{min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important}

.grid:has(>.judge-card){
  grid-template-columns:minmax(0,1fr)!important;
  grid-auto-rows:minmax(0,1fr)!important;
  gap:clamp(9px,.7vw,15px)!important;
  align-content:stretch!important;
}
.grid:has(>.judge-card:nth-child(2)){grid-template-columns:repeat(2,minmax(0,1fr))!important}
.grid:has(>.judge-card:nth-child(3)){grid-template-columns:repeat(3,minmax(0,1fr))!important}
.grid:has(>.judge-card:nth-child(4)){grid-template-columns:repeat(4,minmax(0,1fr))!important}
.grid:has(>.judge-card:nth-child(5)){grid-template-columns:repeat(5,minmax(0,1fr))!important}
.grid:has(>.judge-card:nth-child(6)){grid-template-columns:repeat(6,minmax(0,1fr))!important}
.grid:has(>.judge-card:nth-child(7)){grid-template-columns:repeat(4,minmax(0,1fr))!important}
.grid:has(>.judge-card:nth-child(9)){grid-template-columns:repeat(5,minmax(0,1fr))!important}
.grid:has(>.judge-card:nth-child(11)){grid-template-columns:repeat(6,minmax(0,1fr))!important}
.grid:has(>.judge-card)>.judge-card{min-height:0!important;padding:clamp(8px,.75vw,14px)!important}
.grid:has(>.judge-card) .profile-photo{width:clamp(76px,min(11vw,12vh),132px)!important;height:clamp(76px,min(11vw,12vh),132px)!important;margin-bottom:clamp(4px,.55vh,8px)!important}
.grid:has(>.judge-card) .kicker{font-size:clamp(10px,.68vw,14px)!important;line-height:1!important}
.grid:has(>.judge-card) strong{font-size:clamp(18px,1.45vw,29px)!important;line-height:1!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
.grid:has(>.judge-card) .identity-meta{font-size:clamp(11px,.76vw,16px)!important;white-space:nowrap!important;overflow:hidden!important;gap:clamp(4px,.32vw,7px)!important}
.grid:has(>.judge-card) .identity-country{max-width:100%!important;flex:0 1 auto!important;overflow:hidden!important}
.grid:has(>.judge-card) .identity-country>span:last-child{min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important}
.grid:has(>.judge-card) .judge-status-stack{gap:clamp(2px,.28vh,4px)!important;margin-top:clamp(4px,.55vh,8px)!important}
.grid:has(>.judge-card) .judge-status-stack .status{font-size:clamp(9px,.58vw,12px)!important;padding:.3em .5em!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
'''
    s=s.replace('</style>',block+'\n</style>',1)
    p.write_text(s)

# One-screen judge board: the old renderer silently paged at 8.
replace_once('admin/dance-cup/projector.php',
    "}else pagedCards(data.judges,8,'Judges',cards,data);}",
    "}else pagedCards(data.judges,Math.max(1,data.judges.length),'Judges',cards,data);}")

# Real SVG flags for Dance Cup entries/judges, using the existing local flag assets.
feed=Path('admin/dance-cup/projection-feed.php')
fs=feed.read_text()
fs=fs.replace(
    "$entry['flags']=array_map(static fn(string $country):string=>CountryFlagService::emoji($country),$entry['countries']);\n    $entry['flag']=$entry['flags'][0]??'';",
    "$entry['flags']=array_map(static fn(string $country):string=>CountryFlagService::emoji($country),$entry['countries']);\n    $entry['country_codes']=array_map(static fn(string $country):string=>strtolower((string)(CountryFlagService::code($country)??'')),$entry['countries']);\n    $entry['flag']=$entry['flags'][0]??'';")
fs=fs.replace(
    "['photo_url'=>$entry['photo_url']??null,'country'=>$entry['country']??null,'countries'=>$entry['countries'],'flags'=>$entry['flags']]",
    "['photo_url'=>$entry['photo_url']??null,'country'=>$entry['country']??null,'countries'=>$entry['countries'],'flags'=>$entry['flags'],'country_codes'=>$entry['country_codes']]"
)
fs=fs.replace(
    "$result['countries']=$identity['countries'];$result['flags']=$identity['flags'];",
    "$result['countries']=$identity['countries'];$result['flags']=$identity['flags'];$result['country_codes']=$identity['country_codes'];")
fs=fs.replace(
    "foreach($judges as &$judge){$judge['countries']=CountrySetService::fromRow($judge);$judge['flags']=array_map(static fn(string $country):string=>CountryFlagService::emoji($country),$judge['countries']);$judge['flag']=$judge['flags'][0]??'';}unset($judge);",
    "foreach($judges as &$judge){$judge['countries']=CountrySetService::fromRow($judge);$judge['flags']=array_map(static fn(string $country):string=>CountryFlagService::emoji($country),$judge['countries']);$judge['country_codes']=array_map(static fn(string $country):string=>strtolower((string)(CountryFlagService::code($country)??'')),$judge['countries']);$judge['flag']=$judge['flags'][0]??'';}unset($judge);")
feed.write_text(fs)

# Switch identity renderer to real local SVG flags when a country code exists.
p=Path('admin/dance-cup/projector.php'); s=p.read_text()
old="const identity=item=>{const countries=Array.isArray(item?.countries)&&item.countries.length?item.countries:[item?.country].filter(Boolean),flags=Array.isArray(item?.flags)?item.flags:[];if(!countries.length)return '<div class=\"identity-meta\"><span>Country not set</span></div>';return '<div class=\"identity-meta multi-country\">'+countries.slice(0,5).map((country,index)=>'<span class=\"identity-country\"><span class=\"flag\">'+esc(flags[index]||'')+'</span><span>'+esc(country)+'</span></span>').join('')+'</div>'};"
new="const identity=item=>{const countries=Array.isArray(item?.countries)&&item.countries.length?item.countries:[item?.country].filter(Boolean),flags=Array.isArray(item?.flags)?item.flags:[],codes=Array.isArray(item?.country_codes)?item.country_codes:[];if(!countries.length)return '<div class=\"identity-meta\"><span>Country not set</span></div>';return '<div class=\"identity-meta multi-country\">'+countries.slice(0,5).map((country,index)=>{const code=String(codes[index]||'').toLowerCase().replace(/[^a-z]/g,'');const flag=code?'<img class=\"flag-image\" src=\"../../public/assets/flags/'+esc(code)+'.svg\" alt=\"'+esc(country)+' flag\">':'<span class=\"flag\">'+esc(flags[index]||'')+'</span>';return '<span class=\"identity-country\">'+flag+'<span>'+esc(country)+'</span></span>'}).join('')+'</div>'};"
if old not in s: raise SystemExit('Dance Cup identity renderer anchor missing')
p.write_text(s.replace(old,new,1))

vp=Path('VERSION.json'); data=json.loads(vp.read_text())
data['version']='2.3.3-dev678'; data['build']=3384; data['release_date']='2026-09-07'
feature='Dance Cup projector exact-screen repair: removes the rigid 10vh/5vw dead space, shows 10 contestants as a full 2×5 audience board, shows the complete judge panel on one adaptive screen including five judges in one row, prevents country names from vertical character wrapping, and renders real local SVG flags without changing scoring, marks, results or competition data.'
features=data.setdefault('features',[])
if not features or features[0]!=feature: features.insert(0,feature)
vp.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
PY
php -l admin/dance-cup/projector.php
php -l admin/dance-cup/projection-feed.php
python3 - <<'PY'
from pathlib import Path
p=Path('admin/dance-cup/projector.php').read_text()
f=Path('admin/dance-cup/projection-feed.php').read_text()
assert 'dev678 exact Dance Cup audience boards' in p
assert 'grid-template-columns:repeat(5' in p
assert "Math.max(1,data.judges.length)" in p
assert 'flex-direction:row' in p
assert 'overflow-wrap:normal' in p
assert 'flag-image' in p
assert 'country_codes' in f
assert 'padding:10vh 5vw' not in p
print('dev678 exact Dance Cup projector assertions passed')
PY
git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add admin/dance-cup/projector.php admin/dance-cup/projection-feed.php VERSION.json
git commit -m 'Release dev678 exact Dance Cup projection repair'
git push origin HEAD:develop
