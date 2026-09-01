const fs = require('fs');
const assert = require('assert');

const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(feed.includes('current_mark_count'), 'feed must count marks for the current competitor');
assert(feed.includes("contestant_status"), 'feed must expose per-competitor judge status');
assert(feed.includes("final_submitted"), 'feed must expose final category submission separately');
assert(feed.includes('criteria_required'), 'feed must compare marks with required criteria');
assert(feed.includes('progress_entry'), 'feed must expose the first contestant still awaiting judges');
assert(feed.includes('completed<count($judges)'), 'feed must advance after every judge completes a contestant');
assert(projector.includes('judges complete'), 'projector must summarize current competitor completion');
assert(projector.includes('Final submission:'), 'projector must summarize final submission independently');
assert(projector.includes('Completed · No.'), 'judge cards must identify completed current competitor');
assert(projector.includes('Final pending'), 'judge cards must identify pending final submission');
assert(projector.includes('data.progress_entry||data.active_entry'), 'progress screen must use automatic contestant progression');
assert(Number(version.version.match(/^2\.3\.3-dev(\d+)$/)?.[1]||0)>=536);

console.log('Dance Cup projector judge contestant status v517 tests passed.');
