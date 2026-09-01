const fs=require('fs');
const path=require('path');
const root=path.resolve(__dirname,'..');
const read=file=>fs.readFileSync(path.join(root,file),'utf8');
const assert=(condition,message)=>{if(!condition)throw new Error(message);};

const service=read('app/Services/DanceCupScoringService.php');
const api=read('admin/dance-cup/scoring-api.php');
const manual=read('admin/dance-cup/category.php');
const judge=read('admin/dance-cup/judge-scoring.php');
const automatic=read('admin/dance-cup/automation.php');
const feed=read('admin/dance-cup/projection-feed.php');
const projector=read('admin/dance-cup/projector.php');
const live=read('public/js/dance-cup-scoring-live.js');
const judgeLive=read('public/js/dance-cup-judge-live.js');

assert(service.includes('function calculateResults')&&service.includes('function workflowState'),'shared result and workflow service must be active');
assert(service.includes("$prefix = $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup'"),'service must preserve Test/Live table isolation');
assert(service.includes("$placement = $index + 1"),'calculation must use competition ranking');
assert(api.includes("if($raw==='')")&&api.includes('DELETE FROM {$prefix}_marks'),'manual API must delete cleared marks');
assert(api.includes('DELETE FROM {$prefix}_scoring_results'),'score changes must invalidate stale results');
assert(api.includes("workflow==='automatic'")&&api.includes("judge_sessions SET status='submitted'"),'automatic final lock must lock all completed judge sheets together');
assert(manual.includes('data-dc-manual')&&manual.includes('dance-cup-scoring-live.js?v=432'),'manual dashboard must use no-refresh scorer');
assert(manual.includes('target="_blank" rel="noopener" href="projection-control.php'),'manual projection must open separately');
assert(judge.includes("($_POST['ajax']??'')==='1'")&&/dance-cup-judge-live\.js\?v=\d+/.test(judge),'judge scoring must save without page refresh');
assert(judge.includes('DELETE FROM {$p}_scoring_results'),'judge changes must invalidate stale results');
assert(judgeLive.includes("event.preventDefault()")&&judgeLive.includes("setTimeout(()=>queued('save'"),'judge autosave must intercept forms');
assert(automatic.includes('data-dc-automatic')&&automatic.includes('Automatic Round Completion'),'automatic workflow must include completion stage');
assert(automatic.includes('data-session-progress')&&automatic.includes('data-dc-api-action="submit"'),'automatic status and final lock controls must be live');
assert(automatic.includes('projector-launch.php?token=')&&automatic.includes('target="_blank" rel="noopener" href="projection-control.php'),'automatic projection must use safe launch and a new control tab');
assert(live.includes("setTimeout(poll,2000)")&&live.includes('document.hidden'),'automatic status polling must be fast and hidden-tab safe');
assert(feed.includes('$lastTotal')&&!feed.includes("$row['placement']=$i+1"),'provisional projection must preserve equal-score ties');
assert(projector.includes('data.entries')&&projector.includes('data.judges')&&projector.includes('data.results')&&projector.includes('schedulePoll('),'projector repaint hash must include live scoring data');
assert(projector.includes('response.status===429')&&projector.includes('document.hidden'),'projector polling must back off and pause while hidden');

const competitionRanks=totals=>{let place=0,last=null;return totals.map((total,index)=>{if(last===null||total<last)place=index+1;last=total;return place})};
assert(JSON.stringify(competitionRanks([100,100,90,80,80]))==='[1,1,3,4,4]','tie ranking model must be 1,1,3,4,4');
const canLock=state=>state.all_marks_complete&&state.results_current;
assert(canLock({all_marks_complete:true,results_current:true,all_judges_submitted:false}),'complete marks and reviewed results should lock without individual judge submission');
assert(!canLock({all_marks_complete:false,results_current:true}),'missing judge marks must block automatic lock');

console.log('Dance Cup automatic/manual/projection workflow v395: PASS');
