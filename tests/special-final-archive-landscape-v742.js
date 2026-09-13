const fs = require('fs');
const assert = require('assert');

const publish = fs.readFileSync('admin/scoring/special-publish.php', 'utf8');
const resultFile = fs.readFileSync('result-file.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(publish.includes("empty($_POST['client_html_ready'])"), 'publication must require the reviewed browser archive');
assert(publish.includes("fetchFinalPreview(source+'&layout=fit')"), 'publication must capture the all-judge landscape Final');
assert(publish.includes("fetchFinalPreview(source)"), 'publication must capture the readable Final');
assert(publish.includes("data-layout=\"readable\""), 'archive must expose readable pages');
assert(publish.includes("data-layout=\"fit\""), 'archive must expose the landscape all-judge view');
assert(publish.includes("new URL(location.href)"), 'archive layout controls must preserve the authorized result filename');
assert(publish.includes("u.searchParams.set('layout','fit')"), 'landscape control must select the embedded fit view');
assert(publish.includes("action\" value=\"refresh_final_archive\""), 'published special Finals must be refreshable without republishing points');
assert(publish.includes("'/.archive-backups'"), 'refresh must back up the existing published Final first');
assert(publish.includes("previous_checksum") && publish.includes("new_checksum"), 'refresh must audit old and new archive checksums');
assert(publish.includes("scoring and points were unchanged"), 'refresh confirmation must explicitly preserve result data');
assert(!publish.includes("$finalBody='<table>"), 'special publication must not rebuild a reduced Final placement table');
assert(resultFile.includes("$ext==='html'?'no-cache, must-revalidate, nosniff'"), 'refreshed HTML results must revalidate immediately');
assert(version.version === '2.3.6-dev742' && version.build === 3448, 'release metadata mismatch');

const archivedUrl = new URL('https://bachatadancecouncil.com/portal/result-file.php?file=Special-Final.html');
archivedUrl.searchParams.set('layout', 'fit');
assert.strictEqual(archivedUrl.searchParams.get('file'), 'Special-Final.html', 'landscape navigation must retain the protected filename');
assert.strictEqual(archivedUrl.searchParams.get('layout'), 'fit', 'landscape navigation must add the fit layout');
archivedUrl.searchParams.delete('layout');
assert.strictEqual(archivedUrl.searchParams.get('file'), 'Special-Final.html', 'readable navigation must retain the protected filename');
assert.strictEqual(archivedUrl.searchParams.has('layout'), false, 'readable navigation must remove only the layout selection');

console.log('Special Final archive snapshot and landscape v742 checks passed');
