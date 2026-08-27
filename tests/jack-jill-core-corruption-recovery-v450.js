const fs=require('fs');
const crypto=require('crypto');
const core=fs.readFileSync('admin/scoring/core.php','utf8');
const assert=(condition,message)=>{if(!condition)throw new Error(message)};

assert(!core.includes('Warning: truncated output'),'scoring core contains captured tool warning text');
assert(!core.includes('tokens truncated'),'scoring core contains a truncation placeholder');
assert(core.split('\n').length>=2200,'complete Jack and Jill scoring core is missing');
for(const marker of ["action==='generate_emcee_link'",'data-final-score-state-url=','data-final-leader-id','← All rounds</a> <strong>'])assert(core.includes(marker),'restored scoring workflow missing '+marker);
const checksum=crypto.createHash('sha256').update(core).digest('hex');
assert(checksum==='b42b0406956e8f8112769bec531e2b6c13d2588862241af448b2c666aef63e6f','restored scoring core differs from the verified pre-corruption source');
console.log('Jack and Jill core corruption recovery v450 checks passed');
