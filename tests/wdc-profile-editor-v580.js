const fs=require('fs');
const assert=require('assert');

const editor=fs.readFileSync('admin/dance-cup/competitor-edit.php','utf8');
const migration=fs.readFileSync('database/migrations/20260903_0100_wdc_profile_details.php','utf8');

assert(editor.includes('public/assets/flags/countries.json'),'WDC editor must load the canonical country directory');
assert(editor.includes('name="country"'),'Country selector missing');
for(const field of ['city','contact_name','email','phone','whatsapp','instagram','studio_name','member_names','biography','photo_consent','admin_notes']){
  assert(editor.includes(`name="${field}"`),`Missing WDC editor field ${field}`);
  assert(migration.includes(`ADD COLUMN ${field}`),`Missing WDC migration column ${field}`);
}
assert(editor.includes('Official Dance Cup history protects this WDC identity from archival.'),'Official-history protection missing');
assert(editor.includes("Auth::isSuperAdmin()"),'Entry type unlock must be restricted to Super Admin');
assert(editor.includes('Unlock entry type'),'Super Admin lock/unlock control missing');
assert(editor.includes('Championship category registrations'),'Dance Cup category field mapping missing');
assert(editor.includes("UPDATE bdc_wdc_registrations SET entry_type=:type WHERE wdc_identity_id=:id"),'Unlocked entry-type correction must keep the identity registration classification consistent');
assert(!editor.includes('SET category_key'),'Profile editor must not change registered categories');
assert(!editor.includes('UPDATE bdc_dance_cup_result_history'),'Profile editor must not change results');
assert(!editor.includes('UPDATE bdc_wdc_championship_points'),'Profile editor must not change points');
console.log('WDC profile editor v580 tests passed.');
