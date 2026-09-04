const fs=require('fs');
const assert=require('assert');

const service=fs.readFileSync('app/Services/JudgeDirectoryService.php','utf8');
const live=fs.readFileSync('admin/scoring/core.php','utf8');
const testPage=fs.readFileSync('admin/scoring-tests/index.php','utf8');

assert(service.includes("'country_code'=>\"CHAR(2) NULL AFTER country\""),'Older Judge Database tables must receive country_code before directory queries');
for(const [name,source] of [['Live',live],['Test',testPage]]){
  assert(source.includes('ScoringJudgeAssignmentService::directory($pdo)'),`${name} must load the active Judge Database directory`);
  assert(source.includes('id="judgeDirectorySuggestions"'),`${name} must render Judge Database suggestions`);
  assert(/name="judge_name\[\]"[^>]*list="judgeDirectorySuggestions"|list="judgeDirectorySuggestions"[^>]*name="judge_name\[\]"/.test(source),`${name} setup rows must support Judge Database search`);
  assert(source.includes('list="judgeDirectorySuggestions" name="final_judges['),`${name} Final rows must support Judge Database search`);
}
assert((live.match(/id="judgeDirectorySuggestions"/g)||[]).length===1,'Live must not render duplicate datalist IDs');
console.log('Judge Database search v594 tests passed.');
