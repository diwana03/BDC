'use strict';

const fs = require('fs');
const crypto = require('crypto');
const assert = require('assert');

const runner = fs.readFileSync('app/Services/MigrationRunner.php', 'utf8');
const migration = fs.readFileSync('database/migrations/20260826_0500_publish_verified_form_profiles.php');
const stable = crypto.createHash('sha256').update(crypto.createHash('sha256').update(migration).digest('hex')).digest('hex');

assert.strictEqual(stable, '064f1c3a9332383b301663ad43000a088af5e1f45d2bc364c8a572c69902dfd8', 'immutable 0500 migration changed');
assert(runner.includes("'20260826_0500_publish_verified_form_profiles' => ["), '0500 compatibility scope missing');
assert(runner.includes("'7460868867a0824456439f7fd386b626880f2294c61bbada19d702a01e3dc068'"), 'exact Staging dev414 checksum missing');
assert(runner.includes('hash_equals($storedChecksum, $checksum)'), 'strict migration checksum verification was removed');
assert(runner.includes('return in_array($storedChecksum, $known, true);'), 'unknown stored checksums must still fail closed');

console.log('PASS verified-profile Staging checksum compatibility v424');
