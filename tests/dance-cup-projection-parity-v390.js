const assert = require('assert');

const files = {
  service: process.env.DC_SERVICE || '',
  category: process.env.DC_CATEGORY || '',
  control: process.env.DC_CONTROL || '',
  launch: process.env.DC_LAUNCH || '',
  projector: process.env.DC_PROJECTOR || '',
  feed: process.env.DC_FEED || ''
};

assert(files.service.includes("page_number") && files.service.includes("auto_page") && files.service.includes("page_delay"), 'projection paging columns missing');
assert(files.service.includes("$prefix = $test ? 'bdc_test_dance_cup' : 'bdc_dance_cup'"), 'Testing/Live table isolation missing');
assert(/projection-control\.php[^"'<>]*["'][^>]*target="_blank"[^>]*rel="noopener"/.test(files.category), 'score-sheet projection must open in a new tab');
assert(files.control.includes('projector-launch.php?token='), 'safe projector launch URL missing');
assert(files.control.includes("update_page") && files.control.includes('Screen Paging'), 'remote paging control missing');
assert(files.control.includes("screen_type='holding'") && files.control.includes("active_entry_id=NULL"), 'category switch must force Holding');
assert(files.launch.includes("strlen($token)!==64") && files.launch.includes("WHERE access_token=:token") && files.launch.includes("screen_type='holding'") && files.launch.includes("projector.php?token="), 'safe launch gate incomplete');
assert(files.launch.includes("data_mode") && files.launch.includes("test"), 'safe launch Test/Live isolation missing');
for (const label of ['Holding Screen','Contestant Call','All Contestants','Judges','Scoring Progress','Live Scoreboard','Winner Podium']) {
  assert(files.projector.includes(label), label + ' screen missing');
}
assert(files.projector.includes('currentPage>=pageTotal?1:currentPage+1'), 'automatic page wrap missing');
assert(files.projector.includes('document.hidden'), 'hidden-tab polling pause missing');
assert(files.projector.includes('60000'), 'HTTP 429 backoff missing');
assert(files.projector.includes('setInterval(poll,5000)'), 'projector polling interval missing');
assert(files.projector.includes('data.results') && files.projector.includes('data.judges') && files.projector.includes('data.entries'), 'live data repaint hash incomplete');
assert(!/confetti|fireworks/i.test(files.projector), 'automatic podium effects must remain absent');
assert(files.feed.includes('active_competition_id') && files.feed.includes("'entries'") && files.feed.includes("'judges'") && files.feed.includes("'results'"), 'projection feed integration incomplete');

console.log('Dance Cup projection parity v390: PASS');
