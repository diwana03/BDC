const fs=require('fs');
const page=fs.readFileSync('app/Views/admin/dance-cup-automatic-page.php','utf8');
const workspace=fs.readFileSync('app/Views/admin/dance-cup-automatic-workspace.php','utf8');
const assert=(condition,message)=>{if(!condition)throw new Error(message)};

assert(!page.includes('href="#projection-controls">Live Projection</a>'),'top navigation must not duplicate Projection');
assert(!workspace.includes('>Projection Controls</a>'),'scoring rules must not duplicate Projection');
assert((workspace.match(/id="projection-controls"/g)||[]).length===1,'exactly one Step 6 Projection section is required');
assert((workspace.match(/Open Projection Control/g)||[]).length===1,'Projection Control action must appear once');
assert((workspace.match(/Open Projector Screen/g)||[]).length===1,'Projector Screen action must appear once');
for(const marker of ['Current screen:','Auto cycle','Regenerate Projector Link'])assert(workspace.includes(marker),'single Projection section missing '+marker);
const navigationEnd=workspace.indexOf('</nav>');
const projectionSection=workspace.indexOf('id="projection-controls"');
const scoringRules=workspace.indexOf('SCORING RULES');
assert(navigationEnd>=0&&projectionSection>navigationEnd&&projectionSection<scoringRules,'single Projection card must be visible directly below workflow steps');
assert(workspace.includes('<a href="projection-control.php?id=<?=$id?><?=$suffix?>" target="_blank" rel="noopener"><span>6</span> Projection</a>'),'Step 6 must open Projection Control directly');
console.log('Dance Cup Automatic projection consolidation regression passed');
