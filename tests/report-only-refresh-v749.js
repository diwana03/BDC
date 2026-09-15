const fs=require('fs'),assert=require('assert'),vm=require('vm');
(async()=>{
for(const file of ['admin/scoring-tests/publish.php','admin/scoring/publish.php']){
 const src=fs.readFileSync(file,'utf8');
 const start=src.indexOf('async function generateAllArchivedHtml()');
 const end=src.indexOf('\nif(approvalForm&&',start);
 const code=src.slice(start,end);
 for(const refresh of [true,false]){
  const fetched=[],uploaded=[];
  const context={refreshArchiveButton:refresh?{}:null,htmlGenerationStatus:{},
   fetchPreviewHtml:async url=>{fetched.push(url);return '<html>complete report</html>';},
   makeArchivedHtml:html=>html,uploadArchivedHtml:async category=>uploaded.push(category)};
  vm.createContext(context);await vm.runInContext(code+'\ngenerateAllArchivedHtml()',context);
  assert.deepStrictEqual(uploaded,refresh?['heats','finals']:['heats','finals','points']);
  assert.strictEqual(fetched.filter(x=>x.includes('publication-report.php')).length,refresh?0:1);
  assert.strictEqual(fetched.filter(x=>x.includes('layout=fit')).length,2);
 }
 const directUploaded=[];
 const direct={refreshArchiveButton:{},htmlGenerationStatus:{},fetchPreviewHtml:async()=>'<html>Final</html>',makeArchivedHtml:x=>x,uploadArchivedHtml:async c=>directUploaded.push(c)};
 vm.createContext(direct);
 await vm.runInContext(code.replace('<?=$heatsId?>','0')+'\ngenerateAllArchivedHtml()',direct);
 assert.deepStrictEqual(directUploaded,['finals'],'direct-to-Final refresh must not require a Heats document');
 // Failure must stop before later uploads, preserving the server readiness gate.
 const failed=[];const context={refreshArchiveButton:{},htmlGenerationStatus:{},fetchPreviewHtml:async()=>{throw Error('preview failed');},makeArchivedHtml:x=>x,uploadArchivedHtml:async x=>failed.push(x)};
 vm.createContext(context);await assert.rejects(vm.runInContext(code+'\ngenerateAllArchivedHtml()',context),/preview failed/);assert.strictEqual(failed.length,0);
 const backend=src.slice(src.indexOf('function refreshPublishedArchivedHtml'),src.indexOf('\ntry{',src.indexOf('function refreshPublishedArchivedHtml')));
 assert(backend.includes("foreach(['heats','finals'] as $category)"));
 assert(!backend.includes("'points'"),'Points files must never enter the replacement set');
 assert(backend.includes('report_url=:report_url')&&backend.includes("$documents['finals']"),'old main report pointer must become the detailed Final');
 assert(backend.includes('$pdo->rollBack()')&&backend.includes('copy($backupPath'),'metadata and file restoration must remain available');
}
for(const file of ['special-publish.php','special-publish-salsa.php','publish-salsa.php']){
 const src=fs.readFileSync('admin/scoring/'+file,'utf8');
 assert(src.includes("$_SERVER['REQUEST_METHOD']==='GET'&&$publication&&$publication['status']==='published'"));
 assert(src.includes("Location: publish.php?round_id="));
}
const listing=fs.readFileSync('app/Support/published_report_archive_list.php','utf8');
assert(listing.includes("p.status='published'"));
assert(!listing.includes('LIMIT '),'past reports must not be limited to the latest events');
console.log('Report-only refresh v749 executable JS and integration checks passed');
})().catch(e=>{console.error(e);process.exitCode=1;});
