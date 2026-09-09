const assert = require('assert');
const fs = require('fs');

const read = path => fs.readFileSync(path, 'utf8');
const directory = read('public/js/dance-cup-directory.js');
const automatic = read('admin/dance-cup/automatic-setup.php');
const automaticView = read('app/Views/admin/dance-cup-automatic-page.php');
const manual = read('admin/dance-cup/category.php');
const duplicate = read('app/Services/DanceCupCategoryDuplicateService.php');
const danceCupFeed = read('admin/dance-cup/projection-feed.php');
const jackJillFeed = read('live-display/feed.php');
const version = JSON.parse(read('VERSION.json'));

assert(directory.includes('data-directory-wdc-target') === false, 'HTML attribute belongs in the rendered forms');
assert(directory.includes('input.dataset.directoryWdcTarget'), 'directory control must discover the WDC identity field');
assert(directory.includes("wdcHidden.value=item.wdc_identity_id||''"), 'selected WDC identity must be retained');
assert(directory.includes("if(wdcHidden)wdcHidden.value=''"), 'typing a new value must clear a stale WDC link');

for (const source of [automaticView, manual]) {
  assert(source.includes('data-directory-wdc-target='), 'Dance Cup roster form is missing the WDC link target');
  assert(source.includes('name="wdc_identity_id"'), 'Dance Cup roster form is missing the WDC identity value');
}
assert(automatic.includes('INSERT INTO {$prefix}_entries(competition_id,competitor_id,wdc_identity_id,bib_number,display_name)'), 'Automatic setup must save the WDC identity with the roster entry');
assert(manual.includes('SET competitor_id=:directory,wdc_identity_id=:wdc'), 'Manual setup must save both roster identity links');
assert(duplicate.includes('competition_id,competitor_id,wdc_identity_id,bib_number,display_name,status'), 'copied categories must retain adjusted-photo identity links');
assert(danceCupFeed.includes("CASE WHEN COUNT(*)=1 THEN MAX(NULLIF(wi.photo_url,'')) ELSE NULL END"), 'existing copied rows need an ambiguity-safe WDC photo recovery');
assert(danceCupFeed.includes('LOWER(TRIM(wi.display_name))=LOWER(TRIM(e.display_name))'), 'existing copied rows must recover by exact normalized display name');

assert(jackJillFeed.includes("UPPER({$alias}.bdc_id) LIKE 'BDC-%'"), 'Bachata Test projection must resolve the current BDC adjusted photo');
assert(jackJillFeed.includes("UPPER({$alias}.bdc_id) LIKE 'SDC-%'"), 'Salsa Test projection must resolve the current SDC adjusted photo');
assert(jackJillFeed.includes('LEFT JOIN bdc_sdc_competitors os ON os.competitor_id=oc.id'), 'Salsa photo link must use the canonical SDC profile');
for (const alias of ['c', 'lc', 'fc']) assert(jackJillFeed.includes(`$linkedCompetitorPhoto('${alias}')`), `missing ${alias} photo link for solo/couple projector queries`);
assert((jackJillFeed.match(/\{\$photoC\} photo_url/g) || []).length >= 3, 'solo/callback projector screens must use the live adjusted photo');
assert((jackJillFeed.match(/\{\$photoLc\} leader_photo/g) || []).length >= 3, 'Jack & Jill leader screens must use the live adjusted photo');
assert((jackJillFeed.match(/\{\$photoFc\} follower_photo/g) || []).length >= 3, 'Jack & Jill follower screens must use the live adjusted photo');

assert(Number(version.version.match(/^2\.3\.6-dev(\d+)$/)?.[1]||0)>=715);
assert(version.build>=3421);
console.log('dev715 council photo zoom linkage checks passed');
