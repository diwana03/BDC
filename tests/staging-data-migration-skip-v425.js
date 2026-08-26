'use strict';

const fs = require('fs');
const assert = require('assert');

const runner = fs.readFileSync('app/Services/MigrationRunner.php', 'utf8');
const migration = fs.readFileSync('database/migrations/20260826_0500_publish_verified_form_profiles.php', 'utf8');

assert(runner.includes('STAGING_ONLY_DATA_MIGRATION_SKIPS'), 'Staging data-only skip allowlist missing');
assert(runner.includes("'20260826_0500_publish_verified_form_profiles'"), 'verified profile publication is not scoped to the Staging skip');
assert(runner.includes("Config::get('app.environment', 'production')") && runner.includes("=== 'staging'"), 'skip must require the explicit Staging environment');
assert(runner.includes("INSERT INTO bdc_schema_migrations(version,checksum)"), 'skipped Staging publication must retain an applied checksum record');
assert(runner.includes("$migration($this->pdo);"), 'ordinary and Production migrations must still execute');
assert(runner.includes('hash_equals($storedChecksum, $checksum)'), 'strict checksum validation must remain active');
assert(migration.includes("throw new RuntimeException('Verified identity is missing: '"), 'Production identity protection must remain unchanged inside immutable migration 0500');

console.log('PASS Staging-only verified profile publication skip v425');
