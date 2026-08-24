'use strict';
const fs=require('fs'),assert=require('assert');
const runner=fs.readFileSync('admin/scoring-tests/system-test.php','utf8');
const dashboard=fs.readFileSync('admin/scoring-tests/index.php','utf8');
const checks={
  'Super Admin guard':runner.includes('Auth::isSuperAdmin()'),
  'isolated event table':runner.includes('INSERT INTO bdc_test_events'),
  'isolated round table':runner.includes('INSERT INTO bdc_test_scoring_rounds'),
  'shared scoring service':runner.includes('ScoringCalculationService::calculateHeats'),
  'automatic judge service':runner.includes('TestAutomaticJudgeService::generateAndSubmitAll'),
  'no live event inserts':!runner.includes('INSERT INTO bdc_events'),
  'no live round inserts':!runner.includes('INSERT INTO bdc_scoring_rounds'),
  'archive restricted by system name':runner.includes("name LIKE 'BDC SYSTEM TEST - DO NOT PUBLISH - %'"),
  'dashboard entry point':dashboard.includes('href="system-test.php"'),
  'dashboard-approved scoped access':runner.includes('SystemTestAccessService::verifyApproved'),
  'approved access remains test-only':!runner.includes('bdc_scoring_')&&!runner.includes('bdc_events SET'),
};
for(const [label,passed] of Object.entries(checks))assert(passed,label+' failed');
console.log('System test runner v391 isolation checks passed.');
