const fs=require('fs');
const assert=require('assert');
const source=fs.readFileSync('admin/scoring/judge-control.php','utf8');
for(const action of ['regenerate_copy','regenerate_open','open_whatsapp','send_email'])assert(source.includes(action),`Missing Judge link action ${action}`);
assert(source.includes('regenerateAndCopy'),'Hidden Judge links must support one-click secure copy');
assert(source.includes("if($token===''){$token=AutomaticJudgeBrowserService::regenerate"),'Email and WhatsApp must rotate a hidden token automatically');
assert(source.includes('Copy New Link')&&source.includes('Open New Link'),'Hidden links must retain Copy and Open controls');
console.log('Judge delivery controls v599 tests passed.');
