const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const service = fs.readFileSync(path.resolve(__dirname, '../app/Services/LiveDisplaySessionService.php'), 'utf8');
const method = service.match(/public static function byToken\(PDO \$pdo, string \$token\): \?array\s*\{([\s\S]*?)\n    \}/);

assert.ok(method, 'byToken method must exist');
assert.doesNotMatch(method[1], /self::ensure\(/);
assert.match(method[1], /preg_match\('\/\^\[a-f0-9\]\{48\}\$\/'/);
assert.match(method[1], /token_hash=:h AND is_enabled=1/);

console.log('Projector token hot-path schema-mutation regression checks passed.');
