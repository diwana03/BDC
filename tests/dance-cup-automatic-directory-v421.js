'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const read = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8');
const assert = (condition, message) => { if (!condition) throw new Error(message); };

const setup = read('admin/dance-cup/automatic-setup.php');
const manual = read('admin/dance-cup/category.php');
const endpoint = read('admin/dance-cup/directory-search.php');
const directory = read('public/js/dance-cup-directory.js');

assert(setup.includes("$prefix=$test?'bdc_test_dance_cup':'bdc_dance_cup'"), 'Automatic setup must retain Test and Live table isolation.');
assert(setup.includes("$_POST['competitor_id']") && setup.includes("$_POST['judge_id']"), 'Automatic setup must receive canonical directory IDs.');
assert(setup.includes("FROM bdc_competitors WHERE id=:id AND status<>'archived'"), 'Selected competitors must be revalidated against the BDC Database.');
assert(setup.includes("FROM bdc_judges WHERE id=:id AND status='active'"), 'Selected judges must be revalidated against the Judge Database.');
assert(setup.includes('INSERT INTO {$prefix}_entries(competition_id,competitor_id,bib_number,display_name)'), 'Automatic entries must persist the competitor profile link.');
assert(setup.includes('INSERT INTO {$prefix}_judges(competition_id,judge_id,judge_name,judge_order,is_chief)'), 'Automatic judges must persist the judge profile link.');
assert(setup.includes('This BDC competitor is already assigned') && setup.includes('This Judge Database profile is already assigned'), 'Canonical duplicates must be blocked before insert.');
assert(setup.includes('data-directory-type="competitor"') && setup.includes('data-directory-type="judge"'), 'Both Automatic roster fields must activate type-ahead.');
assert(setup.includes('dance-cup-directory.js?v=426') && manual.includes('dance-cup-directory.js?v=426'), 'Automatic and Manual must share the current directory client.');
assert(setup.includes('class="navbar-brand"') && setup.includes('data-bdc-nav-compact="1"'), 'Automatic setup must render the compact shared BDC header navigation.');
assert(endpoint.includes("$type === 'judge'") && endpoint.includes('FROM bdc_competitors'), 'The authenticated endpoint must search both canonical directories.');
assert(directory.includes("new URL('directory-search.php',location.href)") && directory.includes("hidden.value=''"), 'Typing must query the active endpoint and clear stale selected IDs.');
assert(directory.includes('BDC Directory search could not load. Please retry.'), 'Directory failures must show a neutral retry message for competitors and judges.');

console.log('Dance Cup Automatic database directory and BDC header v421: PASS');
