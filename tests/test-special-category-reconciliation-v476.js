const fs=require('fs'),assert=require('assert');
const service=fs.readFileSync('app/Services/SpecialCategoryReconciliationService.php','utf8');
const page=fs.readFileSync('admin/competitors/special-category-reconciliation.php','utf8');
const restore=fs.readFileSync('database/migrations/20260829_0210_restore_legacy_categories_for_review.php','utf8');
for(const marker of ["PROTECTED_SOURCES=['manual','data_entry','audit','backup','recovery']","status='published'",'approved_by IS NOT NULL',"source==='legacy_profile'",'createDatabaseBackup','classification'])assert(service.includes(marker),'missing reconciliation safeguard '+marker);
for(const marker of ['QUARANTINE TEST CATEGORIES','category_ids[]','SUPER ADMIN · PREVIEW FIRST','Cleanup locked'])assert(page.includes(marker),'missing preview workflow '+marker);
assert(restore.includes('INSERT IGNORE INTO bdc_competitor_special_categories'),'dev475 quarantine is not restored for preview');
assert(!/DELETE\s+FROM\s+bdc_competitor_special_categories/i.test(restore),'review migration must not delete categories');
console.log('Special Category evidence reconciliation v476 checks passed.');
