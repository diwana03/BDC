const fs=require('fs'),assert=require('assert');
const judge=fs.readFileSync('admin/dance-cup/judge-scoring.php','utf8');
for(const marker of ['SCORING CRITERIA','Review once, then start scoring.','dc-criteria-confirmation','grid-template-columns:minmax(0,1fr) auto','max-height:calc(100dvh - 16px)','font-size:.78rem','min-height:44px'])assert(judge.includes(marker),'missing compact criteria gate behavior: '+marker);
assert(!judge.includes('Your scoring sheet will open only after you accept them.'),'verbose criteria introduction must be removed');
assert(!judge.includes('save my work when needed, and review before final submission.'),'repetitive confirmation copy must be removed');
console.log('dev515 compact Dance Cup criteria gate checks passed');
