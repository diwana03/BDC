const fs=require('fs');
const assert=require('assert');
const read=file=>fs.readFileSync(file,'utf8');

const dance=read('app/Services/DanceCupCategoryDuplicateService.php');
const jj=read('app/Services/ScoringEventDuplicateService.php');
const danceUi=read('admin/dance-cup/workflow.php');
const liveUi=read('admin/scoring/active-dashboard.php');
const testUi=read('admin/scoring-tests/index.php');

assert(dance.includes('_entries')&&dance.includes('_judges'),'Dance Cup roster and judges must be copied');
assert(dance.includes('ensureAutomation($pdo,$newId,$test)'),'Dance Cup Automatic links must be fresh');
assert(!dance.includes('INSERT INTO {$prefix}_marks'),'Dance Cup marks must never be copied');
assert(!dance.includes('INSERT INTO {$prefix}_scoring_results'),'Dance Cup results must never be copied');

for(const marker of ['entryAllowed','judgeAllowed',"status']='draft'",'AutomaticJudgeBrowserService::regenerate','TestAutomaticJudgeService::regenerate'])assert(jj.includes(marker),`Missing J&J safeguard: ${marker}`);
assert(jj.includes('WHERE event_id=:event ORDER BY id'),'J&J duplication must include completed events whose source rounds are archived');
assert(!jj.includes("status<>'archived'"),'archived source rounds must not block a completed J&J event copy');
for(const forbidden of ['bdc_scoring_marks','bdc_scoring_results','bdc_scoring_publications','bdc_scoring_final_marks','bdc_scoring_final_results','bdc_scoring_checkpoints'])assert(!jj.includes(forbidden),`J&J duplicate must not copy ${forbidden}`);

assert(danceUi.includes('Duplicate category'),'Dance Cup duplicate action missing');
assert(liveUi.includes('Duplicate Jack &amp; Jill Event'),'Live J&J duplicate UI missing');
assert(liveUi.includes('any Draft or completed event setup'),'Live J&J duplicate UI must explain completed-event support');
assert(testUi.includes('Duplicate Test Jack &amp; Jill Event'),'Test J&J duplicate UI missing');
for(const endpoint of ['admin/dance-cup/duplicate-category.php','admin/scoring/duplicate-event.php','admin/scoring-tests/duplicate-event.php']){const source=read(endpoint);assert(source.includes("REQUEST_METHOD']!=='POST'"),`${endpoint} must be POST only`);assert(source.includes('Csrf::verify'),`${endpoint} must enforce CSRF`);}

console.log('dev481 safe duplicate workflow checks passed');
