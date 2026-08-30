const fs=require('fs'),assert=require('assert');
const bootstrap=fs.readFileSync('bootstrap.php','utf8');
assert(bootstrap.includes("function absolute_url(string $path = '')"),'global absolute URL helper is missing');
assert(bootstrap.includes("Config::get('app.url', '')"),'absolute URLs must prefer the configured public origin');
assert(bootstrap.includes("$_SERVER['HTTP_HOST']"),'absolute URLs must fall back to the current production host');
assert(bootstrap.includes("(token|invite|access)"),'all token-bearing URLs must automatically become absolute');
for(const file of ['app/Services/AutomaticJudgeBrowserService.php','app/Services/TestAutomaticJudgeService.php','app/Services/StageDisplayService.php','app/Services/RandomPairingService.php','app/Services/MobileProjectionRemoteService.php','app/Services/JudgeRegistrationLinkService.php','app/Services/JudgeProfileUpdateLinkService.php']){
 const source=fs.readFileSync(file,'utf8');assert(source.includes('absolute_url('),file+' must return a full shareable URL');
}
const automatic=fs.readFileSync('admin/dance-cup/automatic-setup.php','utf8');
const panels=fs.readFileSync('admin/dance-cup/panels.php','utf8');
assert(automatic.includes("url('admin/dance-cup/judge-scoring.php?token="),'Dance Cup category links must pass through the global absolute-token rule');
assert(panels.includes("url('admin/dance-cup/judge-scoring.php?token="),'Dance Cup panel links must pass through the global absolute-token rule');
console.log('dev510 global absolute public link checks passed');
