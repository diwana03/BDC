const fs=require('fs');
const assert=require('assert');

const editor=fs.readFileSync('admin/dance-cup/competitor-edit.php','utf8');
const migration=fs.readFileSync('database/migrations/20260903_0100_wdc_profile_details.php','utf8');

assert(editor.includes('public/assets/flags/countries.json'),'WDC editor must load the canonical country directory');
assert(editor.includes('name="country"'),'Country selector missing');
for(const field of ['city','contact_name','email','phone','whatsapp','instagram','studio_name','member_names','biography','admin_notes']){
  assert(editor.includes(`name="${field}"`),`Missing WDC editor field ${field}`);
  assert(migration.includes(`ADD COLUMN ${field}`),`Missing WDC migration column ${field}`);
}
assert(editor.includes('Official Dance Cup history protects this WDC identity from archival.'),'Official-history protection missing');
assert(!editor.includes('UPDATE bdc_wdc_registrations'),'Profile editor must not change registrations');
assert(!editor.includes('UPDATE bdc_dance_cup_result_history'),'Profile editor must not change results');
assert(!editor.includes('UPDATE bdc_wdc_championship_points'),'Profile editor must not change points');
console.log('WDC profile editor v580 tests passed.');
