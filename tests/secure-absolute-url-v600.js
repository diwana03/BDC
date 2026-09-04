const fs=require('fs');
const assert=require('assert');
const bootstrap=fs.readFileSync('bootstrap.php','utf8');
assert(!bootstrap.includes("$relative = str_starts_with($path, '/') ? $path : url($path);"),'absolute_url must never call token-aware url() recursively');
assert(bootstrap.includes("$relative = str_starts_with($path, '/') ? $path : $base . '/' . ltrim($path, '/');"),'absolute_url must build the configured portal path directly');
for(const file of ['app/Services/AutomaticJudgeBrowserService.php','app/Services/TestAutomaticJudgeService.php','app/Services/JudgeRegistrationLinkService.php','app/Services/StageDisplayService.php','app/Services/MobileProjectionRemoteService.php']){
  assert(fs.readFileSync(file,'utf8').includes('absolute_url('),`${file} must use the repaired central absolute URL builder`);
}
console.log('Secure absolute URL v600 tests passed.');
