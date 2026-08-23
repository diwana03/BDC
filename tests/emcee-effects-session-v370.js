const fs=require('fs');
const path=require('path');
const read=file=>fs.readFileSync(path.resolve(__dirname,'..',file),'utf8');
const service=read('app/Services/LiveDisplaySessionService.php');
const presenter=read('pairing-presenter/index.php');
const control=read('admin/live-screen/control.php');
const display=read('live-display/index.php');
const state=read('live-display/state.php');
const actions=['hearts','balloons','heart_smiles','finger_hearts'];

if(!service.includes('bdc_live_display_session_events se'))throw new Error('Festival projector membership lookup is missing');
if(!service.includes('(s.active_event_id=:active) DESC,(s.group_name IS NOT NULL) DESC'))throw new Error('Active Festival projector must win an ambiguous event lookup');
if(!service.includes("WHERE id=:id AND data_mode=:m AND is_enabled=1"))throw new Error('Effects must update the resolved projector session ID');
if(!service.includes("return self::byId($pdo,(int)$session['id'],$test)"))throw new Error('Effect response must return the updated resolved session');
if(!service.includes('SET active_event_id=:ae,current_round_id=:r'))throw new Error('Screen updates must target and activate the resolved Festival event');
for(const action of actions){
  if(!presenter.includes(`value=\"<?=$effectAction?>\"`) || !presenter.includes(`'${action}'`))throw new Error(`${action} missing from Emcee controls`);
  if(!control.includes(`data-effect=\"${action}\"`))throw new Error(`${action} missing from J&J Projection Control`);
  if(!display.includes(`'${action}'`))throw new Error(`${action} missing from projector renderer`);
  if(!service.includes(`'${action}'`))throw new Error(`${action} missing from backend validation`);
}
if(!state.includes('"effect_type" => $s["effect_type"]')||!state.includes('"effect_version" => (int)'))throw new Error('Projector state does not publish effect data');
if(!display.includes("['hearts','balloons','heart_smiles','finger_hearts'].includes(s.effect_type)"))throw new Error('Four-effect renderer dispatch missing');
if(!presenter.includes('Random Matching Method: Secure Fisher–Yates Shuffle'))throw new Error('Approved Emcee algorithm heading missing');
if(!presenter.includes('Leaders remain in bib order while Followers are securely and randomly shuffled'))throw new Error('Approved Emcee algorithm explanation missing');
if(presenter.includes('using PHP random_int()')||presenter.includes('Pairing remains Draft until Chief Judge/Admin confirms it'))throw new Error('Technical or non-Emcee note remains');

console.log('OK: all four Emcee effects reach standalone/Festival projectors and J&J controls');
