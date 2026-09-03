const fs=require('fs');
const assert=require('assert');
const service=fs.readFileSync('app/Services/BdcMcpService.php','utf8');

assert(service.includes("directory($pdo,$dance,$role!==''?$role:null,1500)"),'division listing must pass role and limit in the eligibility directory signature order');
assert(!service.includes('directory($pdo,$dance,$division,2000)'),'division must never be passed as the directory role');
assert(service.includes('DivisionProgressionService::eligibilityFromApprovedHistory'),'listed competitors must pass the same division eligibility gate used during approval');
assert(service.includes("$row['eligible_roles']=$allowed"),'listing must publish the exact eligible roles for staging');
console.log('MCP division competitor directory checks passed');
