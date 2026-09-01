const fs=require('fs');
const assert=require('assert');
const read=file=>fs.readFileSync(file,'utf8');

const roster=read('app/Services/DanceCupRosterService.php');
const live=read('admin/scoring/core.php');
const test=read('admin/scoring-tests/index.php');
const tie=read('admin/scoring/tie-resolution-panel.php');
const competitors=read('admin/competitors/merge.php');
const judges=read('admin/judges/index.php');
const judgeService=read('app/Services/JudgeDirectoryService.php');
const directory=read('admin/dance-cup/directory-search.php');
const judgeMerge=read('app/Services/JudgeMergeService.php');
const judgeMergePage=read('admin/judges/merge.php');

assert(roster.includes("if ($kind === 'entry')")&&roster.includes('bib_number=bib_number+:offset'),'Dance Cup removal must compact contestant numbers collision-safely');
for(const [name,source] of [['Live',live],['Test',test]]){
  assert(source.includes("roster_renumbered'=>true"),name+' J&J removal must compact role bibs');
  assert(source.includes('bib_number=bib_number+1000000'),name+' J&J renumber must avoid transient bib collisions');
}
assert(tie.includes('data-chief-tie-alert')&&tie.includes('Chief Judge action required'),'Tie workflow must show a blocking Chief Judge alert');
assert(competitors.includes('Possible Duplicates')&&competitors.includes('LOWER(TRIM(exact_name))'),'Competitor merge must expose case-insensitive duplicate suggestions');
for(const marker of ['Search &amp; Sort','set_status','Possible duplicate judges','LOWER(full_name) LIKE LOWER'])assert(judges.includes(marker),'Judge Directory hardening missing '+marker);
assert(judgeService.includes('LOWER(full_name) LIKE LOWER'),'Judge roster search must be case-insensitive');
assert(directory.includes('LOWER(exact_name) LIKE LOWER'),'Dance Cup competitor search must be case-insensitive');
for(const table of ['bdc_scoring_judges','bdc_test_scoring_judges','bdc_dance_cup_judges','bdc_test_dance_cup_judges'])assert(judgeMerge.includes(table),'Judge merge must preserve '+table);
assert(judgeMerge.includes('same scoring panel')&&judgeMerge.includes("'judge_merged'"),'Judge merge conflict gate or audit missing');
assert(judgeMergePage.includes('MERGE JUDGE')&&judgeMergePage.includes('LOWER(full_name) LIKE LOWER'),'Protected case-insensitive Judge Merge page missing');
for(const table of ['bdc_scoring_entries','bdc_dance_cup_entries','bdc_dance_cup_result_history'])assert(competitors.includes(table),'Competitor merge must preserve '+table);
assert(!competitors.includes("['bdc_test_scoring_entries','round_id']"),'Live competitor merge must not mutate isolated Test identities');
console.log('Scoring rigidity v548 checks passed');
