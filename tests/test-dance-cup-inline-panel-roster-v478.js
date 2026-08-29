const fs=require('fs'),assert=require('assert'),read=f=>fs.readFileSync(f,'utf8');
const setup=read('admin/dance-cup/automatic-setup.php');
const panel=read('app/Services/DanceCupJudgingPanelService.php');
const view=read('app/Views/admin/dance-cup-automatic-page.php');
assert(!setup.includes("Manage its judges once from Judging Panels."),'category form still blocks shared-panel judge controls');
for(const marker of ['DanceCupJudgingPanelService::addJudge','DanceCupJudgingPanelService::moveJudge','DanceCupJudgingPanelService::setChief','DanceCupJudgingPanelService::removeJudge'])assert(setup.includes(marker),'missing inline shared-panel action '+marker);
for(const marker of ['public static function moveJudge','judge_order=judge_order+10000','self::sync($pdo,$panelId,$test)'])assert(panel.includes(marker),'missing safe shared-panel reorder '+marker);
assert(view.includes('Use the Judges form below to add, reorder, choose the Chief Judge or remove judges.'),'shared-panel form guidance missing');
console.log('Dance Cup inline shared-panel roster controls v478 passed.');
