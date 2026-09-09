const assert=require('assert');
const fs=require('fs');
const read=file=>fs.readFileSync(file,'utf8');
const service=read('app/Services/ScoringEventDuplicateService.php');
const live=read('admin/scoring/active-dashboard.php');
const test=read('admin/scoring-tests/index.php');
const version=JSON.parse(read('VERSION.json'));

assert(service.includes('WHERE event_id=:event ORDER BY id'),'service must load archived rounds from completed events');
assert(!service.includes("status<>'archived'"),'service still rejects completed event setup');
assert(live.includes('JOIN bdc_scoring_rounds r ON r.event_id=e.id ORDER BY'),'completed J&J events must remain selectable');
assert(!live.includes("WHERE r.status<>'archived'"),'dashboard still hides completed events');
assert(live.includes('data-saved-round-duplicate')&&test.includes('data-saved-round-duplicate'),'Saved Rounds must expose Duplicate Event directly in Test and Live');
assert(live.includes('action="duplicate-event.php"')&&test.includes('action="duplicate-event.php"'),'Saved Rounds duplicate controls must use the protected endpoints');
for(const safe of ["status']='draft'",'chief_judge_id','scheduled_at'])assert(service.includes(safe),`fresh Draft reset missing: ${safe}`);
for(const forbidden of ['bdc_scoring_marks','bdc_scoring_results','bdc_scoring_publications','bdc_scoring_final_marks','bdc_scoring_final_results'])assert(!service.includes(forbidden),`completed-event copy must not carry ${forbidden}`);
assert(Number(version.version.match(/^2\.3\.6-dev(\d+)$/)?.[1]||0)>=718);
assert(version.build>=3424);
console.log('dev718 completed J&J event duplication checks passed');
