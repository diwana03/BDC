const fs = require('fs');

const control = fs.readFileSync('admin/dance-cup/projection-control.php', 'utf8');
const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

assert(control.includes("'judge_call'=>'Call Judges One by One'"), 'Dance Cup Judge Call screen control is missing');
assert(control.includes("$action==='show_judge'"), 'direct judge selection action is missing');
assert(control.includes("$action==='show_judge_page'"), 'Previous and Next judge action is missing');
assert(control.includes("screen_type='judge_call',page_number=:page,auto_page=0"), 'manual Judge Call must select one exact judge and stop automatic paging');
assert(control.includes('← Previous Judge') && control.includes('Next Judge →'), 'bounded Judge Call navigation is missing');
assert(control.includes('turn on Auto Page below for a timed introduction sequence'), 'operator Auto Page guidance is missing');
assert(projector.includes('function judgeCall(data)'), 'one-by-one Dance Cup judge renderer is missing');
assert(projector.includes("else if(type==='judge_call')judgeCall(data)"), 'Judge Call is not routed to its renderer');
assert(projector.includes("show(card,'Calling Judges',currentPage,Math.max(1,total))"), 'Judge Call page position is not shown');
assert(projector.includes('schedulePage(total,data)'), 'Judge Call does not support timed Auto Page rotation');
assert(projector.includes('.judge-call-card .profile-photo') && projector.includes('height:clamp(262px'), 'large 4:5 Judge Call portrait is missing');
assert(version.version.startsWith('2.3.6-dev') && version.build >= 3437, 'release metadata is older than dev731');

console.log('Dance Cup judge call v731 regression checks passed');
