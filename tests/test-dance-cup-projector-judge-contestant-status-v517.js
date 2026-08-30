const fs = require('fs');
const assert = require('assert');

const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(feed.includes('current_mark_count'), 'feed must count marks for the current competitor');
assert(feed.includes("contestant_status"), 'feed must expose per-competitor judge status');
assert(feed.includes("final_submitted"), 'feed must expose final category submission separately');
assert(feed.includes('criteria_required'), 'feed must compare marks with required criteria');
assert(projector.includes('judges complete'), 'projector must summarize current competitor completion');
assert(projector.includes('Final submission:'), 'projector must summarize final submission independently');
assert(projector.includes('Complete for No.'), 'judge cards must identify completed current competitor');
assert(projector.includes('Final pending'), 'judge cards must identify pending final submission');
assert.strictEqual(version.version, '2.3.3-dev517');

console.log('Dance Cup projector judge contestant status v517 tests passed.');
