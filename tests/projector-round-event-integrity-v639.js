const fs = require('node:fs');
const assert = require('node:assert/strict');

const service = fs.readFileSync('app/Services/LiveDisplaySessionService.php', 'utf8');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');

assert.match(service, /\$requestedRoundId = \(int\) \(\$v\["round_id"\] \?\? 0\);/);
assert.match(service, /SELECT event_id FROM \{\$roundTable\} WHERE id=:r LIMIT 1/);
assert.match(service, /bdc_live_display_session_events WHERE session_id=:s AND event_id=:e LIMIT 1/);
assert.match(service, /"ae" => \$activeEventId/);
assert.match(service, /"r" => \$requestedRoundId \?: null/);
assert.doesNotMatch(service, /"ae" => \$eventId,\s*\n\s*"r" => \(int\) \(\$v\["round_id"\]/);

assert.match(feed, /\$roundEventId = \(int\) \$r\["event_id"\];/);
assert.match(feed, /\$roundBelongsToSession = \$roundEventId === \(int\) \$session\["event_id"\];/);
assert.match(feed, /bdc_live_display_session_events WHERE session_id=:s AND event_id=:e LIMIT 1/);
assert.match(feed, /\$activeEventId = \$roundEventId;/);
assert.match(feed, /\$eventStmt->execute\(\["id" => \$activeEventId\]\);/);

console.log('Projector selected-round event integrity v639: PASS');
