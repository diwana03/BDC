const fs=require('fs'),assert=require('assert');
const feed=fs.readFileSync('admin/dance-cup/projection-feed.php','utf8');
const service=fs.readFileSync('app/Services/DanceCupScoringService.php','utf8');
assert(service.includes('PRIMARY KEY(competition_id,entry_id,judge_id,criterion_id)'),'Dance Cup marks must use the known composite key');
assert(feed.includes('COUNT(m.criterion_id) mark_count'),'projector must count an existing marks-table column');
assert(feed.includes('(COUNT(m.criterion_id)>0) DESC'),'live scoreboard must rank saved marks before unscored contestants');
assert(!feed.includes('COUNT(m.id)'),'projector must not query the nonexistent marks id column');
console.log('dev516 Dance Cup projector live-mark refresh checks passed');
