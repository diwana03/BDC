'use strict';
const fs=require('fs');
const assert=require('assert');
const read=file=>fs.readFileSync(file,'utf8');
const index=read('admin/scoring/index.php');
const create=read('admin/scoring/create-round-action.php');
const dashboard=read('admin/scoring/active-dashboard.php');
const participants=read('admin/dance-cup/participants.php');
const legacyAutomation=read('admin/dance-cup/automation.php');
const automaticSetup=read('admin/dance-cup/automatic-setup.php');

assert(index.includes("$action==='create_round'){require __DIR__.'/create-round-action.php'"));
assert(!index.includes("in_array($action,['create_round','create_next_round','delete_scoring_workflow']"));
for(const marker of ['bdc_scoring_rounds','dance_style','scheduled_at','bdc_registration_desk_links','round_created','beginTransaction','rollBack'])assert(create.includes(marker),'Clean event creation missing '+marker);
assert(dashboard.includes('scoring_create_error'));
for(const marker of ['information_schema.TABLES','bdc_dance_cup_result_history','$historyReady','0 events_entered','current database migration completes'])assert(participants.includes(marker),'Participant fallback missing '+marker);
assert(legacyAutomation.includes("automatic-setup.php?id='.$id")&&legacyAutomation.includes('#automatic-workspace'),'Legacy Dance Cup automation URL must redirect into the complete workspace');
for(const marker of ['<!doctype html>','scoring-premium.css','$automaticWorkspace'])assert(automaticSetup.includes(marker),'Automatic workspace shell missing '+marker);
console.log('PASS emergency scoring entrypoints v441');
