const fs=require('fs');
const path=require('path');
const read=file=>fs.readFileSync(path.resolve(__dirname,'..',file),'utf8');
const service=read('app/Services/MobileProjectionRemoteService.php');
const session=read('app/Services/LiveDisplaySessionService.php');
const control=read('admin/live-screen/control.php');
const remote=read('projection-remote/index.php');

for(const marker of ['DATE_ADD(NOW(),INTERVAL 12 HOUR)','token_hash','hash(\'sha256\',$token)','session_id','event_id','data_mode'])if(!service.includes(marker))throw new Error(`Secure remote marker missing: ${marker}`);
for(const action of ['previous_page','next_page','auto_on','auto_off','hearts','balloons','heart_smiles','finger_hearts'])if(!remote.includes(action)||!service.includes(action))throw new Error(`Mobile command missing: ${action}`);
for(const screen of ['holding','judges','competitors','scoring','score_matrix','matching'])if(!service.includes(`'${screen}'`))throw new Error(`Safe screen missing: ${screen}`);
for(const forbidden of ['unlock_results','lock_results','music_play','music_pause','music_clear','final_results','winner podium','screen_theme','generate_live'])if(remote.toLowerCase().includes(forbidden))throw new Error(`Forbidden mobile capability exposed: ${forbidden}`);
if(!service.includes("This projector screen is protected from the mobile remote"))throw new Error('Server-side protected screen rejection missing');
if(!service.includes('Return to a mobile-safe screen before using page controls'))throw new Error('Protected-screen page control rejection missing');
if(!service.includes('assertActiveEvent'))throw new Error('Festival cross-event protection missing');
if(!session.includes('int $sessionId=0')||!session.includes('$sessionId>0?self::byId'))throw new Error('Exact projector-session targeting missing');
if(!control.includes('generate_mobile_remote')||!control.includes('Copy Mobile Link')||!control.includes('Open Mobile Remote'))throw new Error('Projection dashboard mobile-link controls missing');
if(!control.includes('MobileProjectionRemoteService::activeLink'))throw new Error('Test/Live active mobile link retrieval missing');
console.log('OK: secure event-specific mobile Projection Remote has safe controls and Test/Live parity');
