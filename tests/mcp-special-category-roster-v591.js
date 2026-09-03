const fs=require('fs');
const assert=require('assert');
const service=fs.readFileSync('app/Services/BdcMcpService.php','utf8');

assert(service.includes('SpecialCategoryService::isSpecial($division)'),'special event divisions must use registered category membership');
assert(service.includes('bdc_competitor_special_categories'),'Bachata Rising/Open must come from the canonical Bachata category table');
assert(service.includes('bdc_sdc_competitor_categories'),'Salsa Rising/Open must come from the canonical SDC category table');
assert(service.includes("if($registered!==null&&!isset($registered[(int)$row['id']]))continue"),'unregistered active identities must be excluded');
assert(service.indexOf("if($registered!==null")<service.indexOf('eligibilityFromApprovedHistory'),'category membership must be checked before approval eligibility');
console.log('MCP registered special-category roster checks passed');
