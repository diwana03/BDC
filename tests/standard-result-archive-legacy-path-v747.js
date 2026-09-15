const fs = require('fs');
const path = require('path');
const assert = require('assert');

const live = fs.readFileSync('admin/scoring/publish.php', 'utf8');
const test = fs.readFileSync('admin/scoring-tests/publish.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const [name, source] of [['Live publisher', live], ['Testing publisher', test]]) {
  const start = source.indexOf('function refreshPublishedArchivedHtml');
  const end = source.indexOf('\ntry{', start);
  assert(start >= 0 && end > start, `${name} active refresh function must be present`);
  const refresh = source.slice(start, end);

  assert(refresh.includes('d.storage_path,d.url'), `${name} must load the published URL`);
  assert(refresh.includes('ResultStorageService::resolve($storagePath)'), `${name} must prefer canonical storage`);
  assert(refresh.includes("parse_url($url,PHP_URL_QUERY)"), `${name} must inspect result-file.php query parameters`);
  assert(refresh.includes("$urlParameters['file']"), `${name} must use the published file parameter`);
  assert(refresh.includes('ResultStorageService::resolveFilename'), `${name} must use repository-confined filename resolution`);
  assert(!refresh.includes('file_get_contents($url)'), `${name} must not fetch or trust a remote archive URL`);
}

const repositoryFiles = new Set([
  'LIVE---4th-Asia-Open-SALSA-Jack-Jill-2026-Salsa-Open-2026-09-12-Heats.html',
  'LIVE---4th-Asia-Open-SALSA-Jack-Jill-2026-Salsa-Open-2026-09-12-Final.html',
]);

function resolveFilename(name) {
  const safe = path.posix.basename(String(name).replace(/\\/g, '/'));
  return repositoryFiles.has(safe) ? `/protected-results/production/${safe}` : null;
}

function resolveLegacyPublishedTarget(storagePath, url) {
  if (storagePath.startsWith('protected-results://')) {
    const canonical = resolveFilename(storagePath.slice(20));
    if (canonical) return canonical;
  }

  const candidates = [];
  const parsed = new URL(url, 'https://bachatadancecouncil.com/portal/');
  if (parsed.searchParams.has('file')) candidates.push(parsed.searchParams.get('file'));
  if (parsed.pathname) candidates.push(path.posix.basename(parsed.pathname));
  if (storagePath) candidates.push(path.posix.basename(storagePath.replace(/\\/g, '/')));

  for (const candidate of [...new Set(candidates)]) {
    const target = resolveFilename(candidate);
    if (target) return target;
  }
  return null;
}

const heats = 'LIVE---4th-Asia-Open-SALSA-Jack-Jill-2026-Salsa-Open-2026-09-12-Heats.html';
assert(resolveLegacyPublishedTarget(`protected-results://${heats}`, '')?.endsWith(heats), 'canonical storage must still resolve first');
assert(resolveLegacyPublishedTarget('/legacy/public/results/old-name.html', `result-file.php?file=${encodeURIComponent(heats)}`)?.endsWith(heats), 'the exact failing legacy Salsa URL must resolve');
assert(resolveLegacyPublishedTarget(`/legacy/public/results/${heats}`, '')?.endsWith(heats), 'legacy stored paths must resolve by filename');
assert.strictEqual(resolveLegacyPublishedTarget('/legacy/missing.html', 'result-file.php?file=missing.html'), null, 'missing archives must remain blocked');
assert.strictEqual(resolveLegacyPublishedTarget('/legacy/missing.html', 'result-file.php?file=..%2F..%2Fconfig%2Fconfig.php'), null, 'published query traversal must never escape the repository');

assert(version.version === '2.3.6-dev747' && version.build === 3453, 'release metadata mismatch');

console.log('Standard result archive legacy path v747 checks passed');
