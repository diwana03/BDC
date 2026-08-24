'use strict';
const fs=require('fs'),assert=require('assert');
const runner=fs.readFileSync('admin/scoring-tests/system-test.php','utf8');
const runs=fs.readFileSync('app/Services/SystemTestRunService.php','utf8');
const access=fs.readFileSync('app/Services/SystemTestAccessService.php','utf8');
const migration=fs.readFileSync('database/migrations/20260824_0200_system_test_runs.php','utf8');

for(const marker of [
  "http_response_code(410)",
  'No system test was started',
  'SystemTestAccessService::canStartRun',
  "SystemTestRunService::begin($pdo,'parity'",
  "SystemTestRunService::begin($pdo,'isolated'",
  'SystemTestRunService::complete',
  "header('Location: '.url('admin/scoring-tests/system-test.php?run_id='",
  'name="run_nonce"',
  'Recent controlled runs',
  'Open</a>',
])assert(runner.includes(marker),marker+' missing from active runner');

assert((runner.match(/name="run_nonce"/g)||[]).length===2,'both run forms must carry the one-use nonce');
assert(runner.indexOf('SystemTestAccessService::canStartRun')<runner.indexOf('ScoringParityTestService::run'),'expiry must be checked before parity mutation');
assert(runner.indexOf("SystemTestRunService::begin($pdo,'isolated'")<runner.indexOf("INSERT INTO bdc_test_events"),'idempotency record must precede isolated event creation');
for(const marker of ['UNIQUE KEY uq_system_test_idempotency','report_json LONGTEXT','live_event_id','test_event_id',"status='error'",'completed_at=NOW()'])assert(runs.includes(marker),marker+' missing from durable run service');
for(const marker of ["'created'=>true","'created'=>false",'AND access_request_id=:access'])assert(runs.includes(marker),marker+' missing from replay/access isolation');
assert((runner.match(/if\(!\$startedRun\['created'\]\)/g)||[]).length===2,'duplicate requests must redirect without repeating either mutation');
assert(access.includes('time()+max(30,$minimumSeconds)'));
assert(migration.includes('SystemTestRunService::ensureSchema'));
console.log('System test POST recovery, stale-access preflight and durable run history v394 checks passed.');
