'use strict';

const fs = require('fs');
const assert = require('assert');

const matrix = fs.readFileSync('live-display/final-relative-placement.php', 'utf8');
const state = fs.readFileSync('live-display/state.php', 'utf8');
const advance = fs.readFileSync('live-display/advance.php', 'utf8');
const shell = fs.readFileSync('live-display/index.php', 'utf8');
const control = fs.readFileSync('admin/live-screen/control.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(matrix.includes('$judgePageSize=8;'), 'Final Full Results must limit judge columns to eight per page');
assert(matrix.includes('array_slice($judges,($judgePage-1)*$judgePageSize,$judgePageSize)'), 'Final judge page must render the selected slice');
assert(matrix.includes('PAGE <?=$judgePage?> OF <?=$judgeTotalPages?>'), 'Audience heading must identify the active judge page');
assert(matrix.includes('.aud-couple{display:table-cell;'), 'Couple TD must retain table-cell layout');
assert(matrix.includes('<td class="aud-couple"><div class="aud-couple-row">'), 'Grid layout must live inside the Couple TD');
assert(!matrix.includes('.aud-couple{display:grid;'), 'Couple TD must never become a grid container');
assert(matrix.includes('NR = NOT RANKED BY THIS JUDGE'), 'Top-N omissions must be explained to the audience');
assert(matrix.includes('<span class="nr">NR</span>'), 'Unselected judge ranks must render as NR');
assert(matrix.includes("preg_replace('/\\s+_?TEST\\s*$/i'"), 'Redundant trailing TEST event text must be removed');
assert(matrix.includes('font-size:clamp(18px,1.08vw,26px)'), 'BIB typography must remain larger than competitor-name typography');

assert(state.includes('$s["screen_type"] === "final_results"'), 'State must report Final Full Results page count');
assert(state.includes('["scoring", "score_matrix", "heats_scores", "final_results"]'), 'Final result changes must refresh the live frame');
assert(advance.includes("'score_matrix', 'final_results', 'judges'"), 'Advance endpoint must accept Final Full Results');
assert(advance.includes("$screenType === 'final_results' || ($screenType === 'score_matrix' && $roundType === 'final')"), 'Final matrices must advance by judge count');
assert(shell.includes("'score_matrix','final_results','judges'"), 'Projector shell must auto-advance Final Full Results');
assert(control.includes('Live Score Matrix, Final Full Results and Live Contestant Scores'), 'Projection Control must describe Final Full Results paging');
assert.strictEqual(version.version, '2.3.6-dev706');
assert.strictEqual(version.build, 3412);

console.log('Final Full Results readability and pagination checks passed.');
