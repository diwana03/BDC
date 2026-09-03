const fs=require('fs');const assert=require('assert');
const file=fs.readFileSync('admin/scoring-tests/index.php','utf8');
assert(file.split('\n').length>2200,'Test scoring dashboard is unexpectedly truncated');
for(const bad of ['Warning: truncated output','Total output lines:','…17649 tokens truncated…'])assert(!file.includes(bad),'Repository contains tool truncation marker: '+bad);
for(const token of ['update_bib','remove_entry','save_judges','save_scores','resolve_callback_tie','create_next_round','Final Dashboard','Archived Rounds','archive-round.php','Restore'])assert(file.includes(token),'Restored Test workflow missing '+token);
console.log('Test scoring source integrity v585 checks passed');
