<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;
use Throwable;

final class EventIntegrationService
{
    private const JJ_DIVISIONS=['novice','intermediate','advanced','all_star','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open'];

    public static function submitBatch(PDO $pdo,array $input):array
    {
        if(!ProfileIntegrationAuth::allowedScope('events:submit'))throw new RuntimeException('The integration token is not permitted to submit event setups.');
        $batchKey=substr(trim((string)($input['batch_key']??'')),0,191);
        $source=substr(trim((string)($input['source_system']??'event_api')),0,80)?:'event_api';
        $items=$input['items']??null;
        if($batchKey===''||!preg_match('/^[A-Za-z0-9._:-]+$/',$batchKey))throw new RuntimeException('A stable batch_key is required.');
        if(!is_array($items)||$items===[]||count($items)>20)throw new RuntimeException('items must contain between 1 and 20 event setup packages.');
        $pdo->prepare("INSERT INTO bdc_event_integration_batches(batch_key,source_system,status,submitted_at) VALUES(:batch,:source,'receiving',NOW()) ON DUPLICATE KEY UPDATE submitted_at=NOW(),updated_at=NOW()")
            ->execute(['batch'=>$batchKey,'source'=>$source]);
        $q=$pdo->prepare('SELECT id,source_system FROM bdc_event_integration_batches WHERE batch_key=:batch');$q->execute(['batch'=>$batchKey]);$batch=$q->fetch();
        if(!$batch||!hash_equals((string)$batch['source_system'],$source))throw new RuntimeException('This batch_key belongs to another source system.');
        $results=[];
        foreach($items as $index=>$item){
            try{$results[]=self::stageItem($pdo,(int)$batch['id'],$source,is_array($item)?$item:[],(int)$index);}
            catch(Throwable $e){$results[]=['index'=>$index,'status'=>'failed','error'=>$e->getMessage()];}
        }
        self::refreshBatch($pdo,(int)$batch['id']);
        return ['batch_key'=>$batchKey,'batch_id'=>(int)$batch['id'],'status'=>'pending_review','items'=>$results];
    }

    private static function stageItem(PDO $pdo,int $batchId,string $source,array $item,int $index):array
    {
        $system=strtolower(trim((string)($item['event_system']??'')));
        if(!in_array($system,['jack_jill','dance_cup'],true))throw new RuntimeException('event_system must be jack_jill or dance_cup.');
        $mode=strtolower(trim((string)($item['data_mode']??'live')));
        if(!in_array($mode,['test','live'],true))throw new RuntimeException('data_mode must be test or live.');
        $sourceKey=substr(trim((string)($item['source_key']??'')),0,191);
        if($sourceKey==='')throw new RuntimeException('source_key is required for every event package.');
        $payload=is_array($item['payload']??null)?$item['payload']:[];
        $operation=strtolower(trim((string)($item['operation']??'create_event')));
        if(!in_array($operation,['create_event','add_competitors','sync_competitors','remove_competitors','update_bibs','edit_event'],true))throw new RuntimeException('Unsupported event integration operation.');
        if(in_array($operation,['add_competitors','sync_competitors','remove_competitors','update_bibs','edit_event'],true)&&$system!=='jack_jill')throw new RuntimeException('Existing-event operations are currently supported only for Jack & Jill.');
        $canonical=match($operation){
            'add_competitors'=>self::existingJackJillCompetitorsPayload($pdo,$payload,$mode),
            'sync_competitors'=>self::existingJackJillRosterSyncPayload($pdo,$payload,$mode),
            'remove_competitors'=>self::existingJackJillRosterChangePayload($pdo,$payload,$mode,true),
            'update_bibs'=>self::existingJackJillRosterChangePayload($pdo,$payload,$mode,false),
            'edit_event'=>self::existingJackJillEditPayload($pdo,$payload,$mode),
            default=>$system==='jack_jill'?self::jackJillPayload($pdo,$payload,$mode):self::danceCupPayload($pdo,$payload,$mode),
        };
        $canonical['operation']=$operation;
        $json=json_encode($canonical,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if($json===false)throw new RuntimeException('Event payload could not be encoded.');
        $fingerprint=hash('sha256',$source."\0".$system."\0".$mode."\0".$sourceKey);
        $dupe=$pdo->prepare('SELECT id,status FROM bdc_event_integration_updates WHERE source_fingerprint=:fingerprint');$dupe->execute(['fingerprint'=>$fingerprint]);
        if($row=$dupe->fetch())return ['index'=>$index,'source_key'=>$sourceKey,'status'=>'duplicate','update_id'=>(int)$row['id']];
        $insert=$pdo->prepare("INSERT INTO bdc_event_integration_updates(batch_id,event_system,data_mode,source_key,source_fingerprint,payload_hash,payload_json,validation_status,status) VALUES(:batch,:system,:mode,:source_key,:fingerprint,:payload_hash,:payload,'ready','pending')");
        $insert->execute(['batch'=>$batchId,'system'=>$system,'mode'=>$mode,'source_key'=>$sourceKey,'fingerprint'=>$fingerprint,'payload_hash'=>hash('sha256',$json),'payload'=>$json]);
        return ['index'=>$index,'source_key'=>$sourceKey,'status'=>'pending','update_id'=>(int)$pdo->lastInsertId(),'validation_status'=>'ready'];
    }

    private static function event(array $payload):array
    {
        $event=is_array($payload['event']??null)?$payload['event']:[];
        $name=trim((string)($event['name']??''));
        $date=trim((string)($event['event_date']??''));
        if($name===''||self::length($name)>190)throw new RuntimeException('A valid event name is required.');
        if($date!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('event_date must use YYYY-MM-DD.');
        return ['name'=>$name,'event_date'=>$date,'venue'=>substr(trim((string)($event['venue']??'')),0,190),'country'=>substr(trim((string)($event['country']??'')),0,100)];
    }

    private static function jackJillPayload(PDO $pdo,array $payload,string $mode):array
    {
        $event=self::event($payload);$rounds=$payload['rounds']??null;
        if(!is_array($rounds)||$rounds===[]||count($rounds)>50)throw new RuntimeException('Jack & Jill payload needs 1 to 50 categories/rounds.');
        $clean=[];$keys=[];
        foreach($rounds as $round){
            if(!is_array($round))throw new RuntimeException('Every Jack & Jill round must be an object.');
            $key=substr(trim((string)($round['round_key']??'')),0,100);if($key===''||isset($keys[$key]))throw new RuntimeException('Every round needs a unique round_key.');$keys[$key]=true;
            $dance=strtolower(trim((string)($round['dance_style']??'')));if(!in_array($dance,['bachata','salsa'],true))throw new RuntimeException('Jack & Jill dance_style must be bachata or salsa.');
            $division=strtolower(trim((string)($round['division']??'')));if(!in_array($division,self::JJ_DIVISIONS,true))throw new RuntimeException('Invalid Jack & Jill division.');
            if(str_starts_with($division,'bachata_')&&$dance!=='bachata'||str_starts_with($division,'salsa_')&&$dance!=='salsa')throw new RuntimeException('Division does not match its dance style.');
            $type=strtolower(trim((string)($round['round_type']??'heats')));if(!in_array($type,['heats','final'],true))throw new RuntimeException('Initial Jack & Jill round_type must be heats or final.');
            $scoring=strtolower(trim((string)($round['scoring_mode']??'manual')));if(!in_array($scoring,['manual','automated'],true))throw new RuntimeException('Jack & Jill scoring_mode must be manual or automated.');
            $scheduled=trim((string)($round['scheduled_at']??''));if($scheduled!==''&&!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/',$scheduled))throw new RuntimeException('scheduled_at must use YYYY-MM-DD HH:MM[:SS].');if(strlen($scheduled)===16)$scheduled.=':00';
            $competitors=self::competitors($pdo,(array)($round['competitors']??[]),$mode,true,$dance,$division);
            $judges=self::judges($pdo,(array)($round['judges']??[]),true);
            foreach(['leader','follower'] as $role)if(count(array_filter($judges,static fn(array $j):bool=>in_array($j['scope'],['all',$role],true)))<3)throw new RuntimeException(ucfirst($role).' panel must have at least 3 judges.');
            $clean[]=['round_key'=>$key,'dance_style'=>$dance,'division'=>$division,'round_type'=>$type,'scoring_mode'=>$scoring,'scheduled_at'=>$scheduled,'yes_count'=>self::boundedInt($round['yes_count']??10,1,100,'yes_count'),'callback_count'=>self::boundedInt($round['callback_count']??10,1,100,'callback_count'),'competitors'=>$competitors,'judges'=>$judges];
        }
        return ['event'=>$event,'rounds'=>$clean];
    }

    private static function danceCupPayload(PDO $pdo,array $payload,string $mode):array
    {
        $event=self::event($payload);$scoring=strtolower(trim((string)(($payload['event']['scoring_mode']??'manual'))));if(!in_array($scoring,['manual','automatic'],true))throw new RuntimeException('Dance Cup scoring_mode must be manual or automatic.');$event['scoring_mode']=$scoring;
        $categories=$payload['categories']??null;if(!is_array($categories)||$categories===[]||count($categories)>50)throw new RuntimeException('Dance Cup payload needs 1 to 50 categories.');
        $clean=[];$keys=[];
        foreach($categories as $category){
            if(!is_array($category))throw new RuntimeException('Every Dance Cup category must be an object.');
            $key=substr(trim((string)($category['category_key']??'')),0,100);if($key===''||isset($keys[$key]))throw new RuntimeException('Every category needs a unique category_key.');$keys[$key]=true;
            $name=trim((string)($category['name']??''));if($name===''||self::length($name)>190)throw new RuntimeException('Every Dance Cup category needs a valid name.');
            $entryType=strtolower(trim((string)($category['entry_type']??'solo')));if(!in_array($entryType,['solo','couple','duo','pro_am','team'],true))throw new RuntimeException('Invalid Dance Cup entry_type.');
            $dance=strtolower(trim((string)($category['dance_style']??'bachata')));if(!in_array($dance,['salsa','bachata','cha_cha','other'],true))throw new RuntimeException('Invalid Dance Cup dance_style.');
            $level=strtolower(trim((string)($category['competition_level']??'open')));if(!in_array($level,['amateur','intermediate','pro_am','professional','open'],true))throw new RuntimeException('Invalid Dance Cup competition_level.');
            $gender=strtolower(trim((string)($category['gender_eligibility']??'mixed')));if(!in_array($gender,['mixed','female_only','male_only'],true))throw new RuntimeException('Invalid Dance Cup gender_eligibility.');
            $performance=strtolower(trim((string)($category['performance_type']??'showcase')));if(!in_array($performance,['showcase','classic','cabaret','shines','just_dance'],true))throw new RuntimeException('Invalid Dance Cup performance_type.');
            $roundName=strtolower(trim((string)($category['round_name']??'final')));if(!in_array($roundName,['qualifier','quarterfinal','semifinal','final'],true))throw new RuntimeException('Invalid Dance Cup round_name.');
            $criteria=[];$maximum=0.0;$seen=[];foreach((array)($category['criteria']??DanceCupScoringService::defaultCriteria($entryType)) as $criterion){$cn=trim((string)($criterion['name']??''));$max=(float)($criterion['max']??0);$ck=self::lower($cn);if($cn===''||$max<=0||isset($seen[$ck]))throw new RuntimeException('Dance Cup criteria need unique names and positive maximums.');$seen[$ck]=true;$maximum+=$max;$criteria[]=['name'=>$cn,'max'=>$max];}if(!$criteria||$maximum>1000)throw new RuntimeException('Dance Cup criteria total must be between 1 and 1000.');
            $competitors=self::competitors($pdo,(array)($category['competitors']??[]),$mode,false,null,null,$entryType);$judges=self::judges($pdo,(array)($category['judges']??[]),false);
            if(!$judges)throw new RuntimeException('Each Dance Cup category needs at least one judge.');
            $clean[]=['category_key'=>$key,'name'=>$name,'entry_type'=>$entryType,'dance_style'=>$dance,'competition_level'=>$level,'gender_eligibility'=>$gender,'performance_type'=>$performance,'round_name'=>$roundName,'criteria'=>$criteria,'maximum_score'=>$maximum,'competitors'=>$competitors,'judges'=>$judges];
        }
        return ['event'=>$event,'categories'=>$clean];
    }

    private static function competitors(PDO $pdo,array $rows,string $mode,bool $roles,?string $dance=null,?string $division=null,?string $entryType=null):array
    {
        if(!$rows)throw new RuntimeException('Select at least one competitor.');
        $clean=[];$seen=[];$bibs=[];
        foreach($rows as $row){
            if(!is_array($row))throw new RuntimeException('Every competitor selection must be an object.');
            $code=strtoupper(trim((string)($row['council_id']??$row['bdc_id']??$row['wdc_id']??'')));
            $role=$roles?strtolower(trim((string)($row['role']??''))):'';
            if($roles&&!in_array($role,['leader','follower'],true))throw new RuntimeException('Jack & Jill competitor role must be leader or follower.');
            $identity=$code."\0".$role;if(isset($seen[$identity]))throw new RuntimeException('The same competitor cannot be selected twice in one category.');$seen[$identity]=true;
            $bib=self::boundedInt($row['bib']??0,1,999999,'bib');$bibKey=($roles?$role:'all')."\0".$bib;if(isset($bibs[$bibKey]))throw new RuntimeException('Competitor bibs must be unique within each role/category.');$bibs[$bibKey]=true;
            if($roles){
                $dance=JackJillCompetitorEligibilityService::dance((string)$dance);$expected=$dance==='salsa'?'SDC':'BDC';
                if(!preg_match('/^'.$expected.'-\d+$/',$code))throw new RuntimeException(ucfirst($dance).' Jack & Jill requires an '.$expected.' ID.');
                $profile=JackJillCompetitorEligibilityService::requireEligible($pdo,$dance,$code,$role);
                $eligibility=DivisionProgressionService::eligibilityFromApprovedHistory($pdo,(int)$profile['id'],$role,$dance,(string)$division);
                if(!$eligibility['eligible'])throw new RuntimeException('Cannot add '.$profile['exact_name'].': '.$eligibility['reason']);
                if($mode==='test')CompetitorIdentityService::mirrorOfficialToTest($pdo,$profile);
                $clean[]=['competitor_id'=>(int)$profile['id'],'bdc_id'=>$code,'council_id'=>$code,'name'=>(string)$profile['exact_name'],'role'=>$role,'bib'=>$bib];
            }else{
                if(!preg_match('/^WDC-\d+$/',$code))throw new RuntimeException('Dance Cup competitors require a WDC ID.');
                $q=$pdo->prepare("SELECT id,display_name,solo_competitor_id FROM bdc_wdc_identities WHERE identity_code=:code AND entry_type=:type AND status='active' LIMIT 1");$q->execute(['code'=>$code,'type'=>$entryType]);$profile=$q->fetch();
                if(!$profile)throw new RuntimeException($code.' was not found as an active WDC '.str_replace('_',' ',(string)$entryType).' identity.');
                $clean[]=['competitor_id'=>(int)($profile['solo_competitor_id']??0)?:null,'wdc_identity_id'=>(int)$profile['id'],'wdc_id'=>$code,'name'=>(string)$profile['display_name'],'role'=>'','bib'=>$bib];
            }
        }
        return $clean;
    }

    private static function judges(PDO $pdo,array $rows,bool $scopes):array
    {
        if(!$rows)return[];JudgeDirectoryService::ensure($pdo);$clean=[];$seen=[];$chiefs=0;
        foreach($rows as $index=>$row){if(!is_array($row))throw new RuntimeException('Every judge selection must be an object.');$code=strtoupper(trim((string)($row['judge_code']??'')));if($code===''||isset($seen[$code]))throw new RuntimeException('Every judge needs a unique judge_code.');$seen[$code]=true;$q=$pdo->prepare("SELECT id,full_name,display_name FROM bdc_judges WHERE judge_code=:code AND status='active' LIMIT 1");$q->execute(['code'=>$code]);$profile=$q->fetch();if(!$profile)throw new RuntimeException($code.' was not found in the active Judge Database.');$scope=$scopes?strtolower(trim((string)($row['scope']??'all'))):'all';if(!in_array($scope,['all','leader','follower'],true))throw new RuntimeException('Judge scope must be all, leader or follower.');$chief=!empty($row['chief']);if($chief)$chiefs++;$clean[]=['judge_id'=>(int)$profile['id'],'judge_code'=>$code,'name'=>(string)($profile['display_name']?:$profile['full_name']),'order'=>$index+1,'chief'=>$chief,'scope'=>$scope];}
        if($clean&&$chiefs!==1)throw new RuntimeException('Select exactly one Chief Judge.');return $clean;
    }

    private static function existingJackJillCompetitorsPayload(PDO $pdo,array $payload,string $mode):array
    {
        $eventId=self::boundedInt($payload['target_event_id']??0,1,PHP_INT_MAX,'target_event_id');
        $roundId=self::boundedInt($payload['target_round_id']??0,1,PHP_INT_MAX,'target_round_id');
        $target=self::requireDraftJackJillRound($pdo,$eventId,$roundId,$mode,false);
        $competitors=self::competitors($pdo,(array)($payload['competitors']??[]),$mode,true,(string)$target['dance_style'],(string)$target['division']);
        return [
            'operation'=>'add_competitors',
            'target_event_id'=>$eventId,
            'target_round_id'=>$roundId,
            'event_name'=>(string)$target['event_name'],
            'dance_style'=>(string)$target['dance_style'],
            'division'=>(string)$target['division'],
            'round_type'=>(string)$target['round_type'],
            'competitors'=>$competitors,
        ];
    }

    private static function existingJackJillRosterSyncPayload(PDO $pdo,array $payload,string $mode):array
    {
        $eventId=self::boundedInt($payload['target_event_id']??0,1,PHP_INT_MAX,'target_event_id');$roundId=self::boundedInt($payload['target_round_id']??0,1,PHP_INT_MAX,'target_round_id');$target=self::requireDraftJackJillRound($pdo,$eventId,$roundId,$mode,false);if(self::jackJillRoundHasScoring($pdo,$roundId,$mode))throw new RuntimeException('Roster and bib synchronization is locked because scoring has started.');$competitors=self::competitors($pdo,(array)($payload['competitors']??[]),$mode,true,(string)$target['dance_style'],(string)$target['division']);$current=self::activeJackJillRoster($pdo,$roundId,$mode);$desired=array_fill_keys(array_map(static fn(array $row):string=>(int)$row['competitor_id']."\0".$row['role'],$competitors),true);foreach($current as $row)if(!isset($desired[(int)$row['competitor_id']."\0".$row['dance_role']]))throw new RuntimeException('Roster synchronization cannot remove an existing active competitor.');return ['operation'=>'sync_competitors','target_event_id'=>$eventId,'target_round_id'=>$roundId,'event_name'=>(string)$target['event_name'],'dance_style'=>(string)$target['dance_style'],'division'=>(string)$target['division'],'round_type'=>(string)$target['round_type'],'before_roster_hash'=>self::rosterHash($current),'competitors'=>$competitors];
    }

    private static function activeJackJillRoster(PDO $pdo,int $roundId,string $mode):array
    {
        $entries=$mode==='test'?'bdc_test_scoring_entries':'bdc_scoring_entries';$q=$pdo->prepare("SELECT id,competitor_id,dance_role,bib_number,display_name FROM {$entries} WHERE round_id=:round AND entry_status='active' ORDER BY competitor_id,dance_role,id");$q->execute(['round'=>$roundId]);return $q->fetchAll();
    }

    private static function rosterHash(array $rows):string{return hash('sha256',(string)json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}

    private static function existingJackJillRosterChangePayload(PDO $pdo,array $payload,string $mode,bool $removal):array
    {
        $eventId=self::boundedInt($payload['target_event_id']??0,1,PHP_INT_MAX,'target_event_id');$roundId=self::boundedInt($payload['target_round_id']??0,1,PHP_INT_MAX,'target_round_id');$target=self::requireDraftJackJillRound($pdo,$eventId,$roundId,$mode,false);if(self::jackJillRoundHasScoring($pdo,$roundId,$mode))throw new RuntimeException('Roster changes are locked because scoring has started.');$current=self::activeJackJillRoster($pdo,$roundId,$mode);$byId=[];foreach($current as $row)$byId[(int)$row['id']]=$row;$selected=[];
        if($removal){$ids=array_values(array_unique(array_map('intval',(array)($payload['entry_ids']??[]))));if(!$ids)throw new RuntimeException('Select at least one active competitor entry to remove.');foreach($ids as $id){if(!isset($byId[$id]))throw new RuntimeException('Entry #'.$id.' is not active in the selected round.');$selected[]=$byId[$id];}}
        else{$updates=(array)($payload['updates']??[]);if(!$updates)throw new RuntimeException('Select at least one active competitor bib to amend.');$seen=[];$proposed=[];foreach($current as $row)$proposed[$row['dance_role']."\0".(int)$row['bib_number']]=(int)$row['id'];foreach($updates as $update){$id=self::boundedInt($update['entry_id']??0,1,PHP_INT_MAX,'entry_id');$bib=self::boundedInt($update['bib']??0,1,999999,'bib');if(isset($seen[$id]))throw new RuntimeException('Each entry can be amended only once.');$seen[$id]=true;if(!isset($byId[$id]))throw new RuntimeException('Entry #'.$id.' is not active in the selected round.');$row=$byId[$id];unset($proposed[$row['dance_role']."\0".(int)$row['bib_number']]);$selected[]=['id'=>$id,'competitor_id'=>(int)$row['competitor_id'],'dance_role'=>$row['dance_role'],'display_name'=>$row['display_name'],'old_bib'=>(int)$row['bib_number'],'bib'=>$bib];}foreach($selected as $row){$key=$row['dance_role']."\0".$row['bib'];if(isset($proposed[$key]))throw new RuntimeException('Bib '.$row['bib'].' would be duplicated for '.$row['dance_role'].' entries.');$proposed[$key]=(int)$row['id'];}}
        return ['operation'=>$removal?'remove_competitors':'update_bibs','target_event_id'=>$eventId,'target_round_id'=>$roundId,'event_name'=>(string)$target['event_name'],'dance_style'=>(string)$target['dance_style'],'division'=>(string)$target['division'],'round_type'=>(string)$target['round_type'],'before_roster_hash'=>self::rosterHash($current),($removal?'competitors':'updates')=>$selected];
    }

    private static function existingJackJillEditPayload(PDO $pdo,array $payload,string $mode):array
    {
        $eventId=self::boundedInt($payload['target_event_id']??0,1,PHP_INT_MAX,'target_event_id');
        $roundId=(int)($payload['target_round_id']??0);$changes=is_array($payload['changes']??null)?$payload['changes']:[];
        if(!$changes)throw new RuntimeException('Select at least one event or round field to edit.');
        $eventFields=['event_name','event_date','location','venue','event_status'];$roundFields=['scheduled_at','dance_style','division','round_type','scoring_mode'];
        foreach(array_keys($changes) as $field)if(!in_array($field,array_merge($eventFields,$roundFields),true))throw new RuntimeException('Unsupported event edit field: '.$field.'.');
        foreach($roundFields as $field)if(array_key_exists($field,$changes)&&$roundId<1)throw new RuntimeException('target_round_id is required when changing round fields.');
        $current=self::jackJillEditSnapshot($pdo,$eventId,$roundId,$mode,false);
        $clean=[];
        if(array_key_exists('event_name',$changes)){$value=trim((string)$changes['event_name']);if($value===''||self::length($value)>190)throw new RuntimeException('event_name must contain 1 to 190 characters.');$clean['event_name']=$value;}
        if(array_key_exists('event_date',$changes)){$value=trim((string)($changes['event_date']??''));if($value!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value))throw new RuntimeException('event_date must use YYYY-MM-DD or null.');$clean['event_date']=$value;}
        foreach(['location'=>190,'venue'=>255] as $field=>$max)if(array_key_exists($field,$changes)){$value=trim((string)($changes[$field]??''));if(self::length($value)>$max)throw new RuntimeException($field.' is too long.');$clean[$field]=$value;}
        if(array_key_exists('event_status',$changes)){$value=strtolower(trim((string)$changes['event_status']));if(!in_array($value,['draft','published','completed','cancelled'],true))throw new RuntimeException('event_status is invalid.');$clean['event_status']=$value;}
        if(array_key_exists('scheduled_at',$changes)){$value=trim((string)($changes['scheduled_at']??''));if($value!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2})?$/',$value))throw new RuntimeException('scheduled_at must use YYYY-MM-DD HH:MM[:SS] or null.');$clean['scheduled_at']=$value===''?'':str_replace('T',' ',$value).(strlen($value)===16?':00':'');}
        if(array_key_exists('dance_style',$changes)){$value=strtolower(trim((string)$changes['dance_style']));if(!in_array($value,['bachata','salsa'],true))throw new RuntimeException('dance_style is invalid.');$clean['dance_style']=$value;}
        if(array_key_exists('division',$changes)){$value=strtolower(trim((string)$changes['division']));if(!in_array($value,self::JJ_DIVISIONS,true))throw new RuntimeException('division is invalid.');$clean['division']=$value;}
        if(array_key_exists('round_type',$changes)){$value=strtolower(trim((string)$changes['round_type']));if(!in_array($value,['heats','semifinal','final'],true))throw new RuntimeException('round_type is invalid.');$clean['round_type']=$value;}
        if(array_key_exists('scoring_mode',$changes)){$value=strtolower(trim((string)$changes['scoring_mode']));if(!in_array($value,['manual','automated'],true))throw new RuntimeException('scoring_mode is invalid.');$clean['scoring_mode']=$value;}
        if(!$clean)throw new RuntimeException('No valid changes were supplied.');
        if($roundId>0){$dance=(string)($clean['dance_style']??$current['round']['dance_style']);$division=(string)($clean['division']??$current['round']['division']);if(str_starts_with($division,'salsa_')&&$dance!=='salsa'||str_starts_with($division,'bachata_')&&$dance!=='bachata')throw new RuntimeException('The selected division does not match its dance style.');}
        $structural=array_intersect(array_keys($clean),['dance_style','division','round_type','scoring_mode']);if($structural&&self::jackJillRoundHasScoring($pdo,$roundId,$mode))throw new RuntimeException('Structural round fields are locked because scoring has started.');if($structural)self::assertUniqueJackJillRoundConfiguration($pdo,$eventId,$roundId,$mode,$clean,$current);
        return ['operation'=>'edit_event','target_event_id'=>$eventId,'target_round_id'=>$roundId?:null,'event_name'=>(string)$current['event']['name'],'before'=>$current,'changes'=>$clean];
    }

    private static function jackJillEditSnapshot(PDO $pdo,int $eventId,int $roundId,string $mode,bool $lock):array
    {
        $events=$mode==='test'?'bdc_test_events':'bdc_events';$rounds=$mode==='test'?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
        $q=$pdo->prepare("SELECT id,name,event_date,location,venue,status FROM {$events} WHERE id=:id LIMIT 1".($lock?' FOR UPDATE':''));$q->execute(['id'=>$eventId]);$event=$q->fetch();if(!$event)throw new RuntimeException('The target Jack & Jill event was not found in the selected data mode.');
        $round=null;if($roundId>0){$q=$pdo->prepare("SELECT id,event_id,scheduled_at,dance_style,division,round_type,scoring_mode,status FROM {$rounds} WHERE id=:id AND event_id=:event LIMIT 1".($lock?' FOR UPDATE':''));$q->execute(['id'=>$roundId,'event'=>$eventId]);$round=$q->fetch();if(!$round)throw new RuntimeException('The target round does not belong to this event.');}
        return ['event'=>$event,'round'=>$round];
    }

    private static function jackJillRoundHasScoring(PDO $pdo,int $roundId,string $mode):bool
    {
        if($roundId<1)return false;$prefix=$mode==='test'?'bdc_test_':'bdc_';foreach(["{$prefix}scoring_marks","{$prefix}scoring_final_marks"] as $table){$q=$pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE round_id=:round");$q->execute(['round'=>$roundId]);if((int)$q->fetchColumn()>0)return true;}return false;
    }

    private static function assertUniqueJackJillRoundConfiguration(PDO $pdo,int $eventId,int $roundId,string $mode,array $changes,array $snapshot):void
    {
        $rounds=$mode==='test'?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$before=(array)($snapshot['round']??[]);$q=$pdo->prepare("SELECT id FROM {$rounds} WHERE event_id=:event AND dance_style=:dance AND division=:division AND round_type=:type AND scoring_mode=:scoring AND status<>'archived' AND id<>:id LIMIT 1");$q->execute(['event'=>$eventId,'dance'=>$changes['dance_style']??$before['dance_style'],'division'=>$changes['division']??$before['division'],'type'=>$changes['round_type']??$before['round_type'],'scoring'=>$changes['scoring_mode']??$before['scoring_mode'],'id'=>$roundId]);if($q->fetchColumn())throw new RuntimeException('This event already has the selected dance, division, round type and scoring mode.');
    }

    private static function requireDraftJackJillRound(PDO $pdo,int $eventId,int $roundId,string $mode,bool $lock):array
    {
        $events=$mode==='test'?'bdc_test_events':'bdc_events';
        $rounds=$mode==='test'?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
        $sql="SELECT r.id,r.event_id,r.dance_style,r.division,r.round_type,r.status AS round_status,e.name AS event_name,e.status AS event_status FROM {$rounds} r JOIN {$events} e ON e.id=r.event_id WHERE e.id=:event AND r.id=:round LIMIT 1".($lock?' FOR UPDATE':'');
        $q=$pdo->prepare($sql);$q->execute(['event'=>$eventId,'round'=>$roundId]);$target=$q->fetch();
        if(!$target)throw new RuntimeException('The target Jack & Jill event round was not found in the selected data mode.');
        if((string)$target['event_status']!=='draft'||(string)$target['round_status']!=='draft')throw new RuntimeException('Competitors can be added only to a draft event and draft round.');
        $dance=JackJillCompetitorEligibilityService::dance((string)$target['dance_style']);
        $division=strtolower(trim((string)$target['division']));
        if(!in_array($division,self::JJ_DIVISIONS,true))throw new RuntimeException('The target round has an unsupported division.');
        if(str_starts_with($division,'salsa_')&&$dance!=='salsa'||str_starts_with($division,'bachata_')&&$dance!=='bachata')throw new RuntimeException('The target round division does not match its dance style.');
        $target['dance_style']=$dance;$target['division']=$division;
        return $target;
    }

    private static function boundedInt(mixed $value,int $min,int $max,string $field):int{$value=filter_var($value,FILTER_VALIDATE_INT);if($value===false||$value<$min||$value>$max)throw new RuntimeException($field.' must be between '.$min.' and '.$max.'.');return(int)$value;}

    public static function review(PDO $pdo,array $ids,string $decision,int $userId):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));if(!$ids)throw new RuntimeException('Select at least one pending event setup.');if(!in_array($decision,['approve','reject'],true))throw new RuntimeException('Invalid review decision.');
        $result=['approved'=>0,'rejected'=>0,'failed'=>0,'errors'=>[]];foreach($ids as $id){try{self::reviewOne($pdo,$id,$decision,$userId);$result[$decision==='approve'?'approved':'rejected']++;}catch(Throwable $e){$result['failed']++;$result['errors'][]='#'.$id.' '.$e->getMessage();}}return$result;
    }

    private static function reviewOne(PDO $pdo,int $id,string $decision,int $userId):void
    {
        $q=$pdo->prepare("SELECT u.*,b.batch_key FROM bdc_event_integration_updates u JOIN bdc_event_integration_batches b ON b.id=u.batch_id WHERE u.id=:id AND u.status='pending'");$q->execute(['id'=>$id]);$u=$q->fetch();if(!$u)throw new RuntimeException('Event setup is no longer pending.');
        if($decision==='reject'){$pdo->prepare("UPDATE bdc_event_integration_updates SET status='rejected',reviewed_by=:user,reviewed_at=NOW() WHERE id=:id AND status='pending'")->execute(['user'=>$userId,'id'=>$id]);self::refreshBatch($pdo,(int)$u['batch_id']);Auth::audit($userId,'event_integration_rejected',['batch_key'=>$u['batch_key'],'event_system'=>$u['event_system'],'data_mode'=>$u['data_mode']],'event_integration_update',$id);return;}
        if($u['validation_status']!=='ready')throw new RuntimeException('Resolve package validation before approval.');$payload=json_decode((string)$u['payload_json'],true);if(!is_array($payload))throw new RuntimeException('Stored event payload is invalid.');$test=$u['data_mode']==='test';
        if($u['event_system']==='dance_cup')DanceCupScoringService::ensureWorkspaceTables($pdo,$test);
        $pdo->beginTransaction();try{$operation=(string)($payload['operation']??'create_event');$eventId=match($operation){'add_competitors'=>self::applyExistingJackJillCompetitors($pdo,$payload,$test),'sync_competitors'=>self::applyExistingJackJillRosterSync($pdo,$payload,$test),'remove_competitors'=>self::applyExistingJackJillRosterChange($pdo,$payload,$test,true),'update_bibs'=>self::applyExistingJackJillRosterChange($pdo,$payload,$test,false),'edit_event'=>self::applyExistingJackJillEdit($pdo,$payload,$test),default=>$u['event_system']==='jack_jill'?self::applyJackJill($pdo,$payload,$test,$userId):self::applyDanceCup($pdo,$payload,$test,$userId)};$pdo->prepare("UPDATE bdc_event_integration_updates SET status='approved',target_event_id=:event,reviewed_by=:user,reviewed_at=NOW(),error_message=NULL WHERE id=:id AND status='pending'")->execute(['event'=>$eventId,'user'=>$userId,'id'=>$id]);$pdo->commit();self::refreshBatch($pdo,(int)$u['batch_id']);Auth::audit($userId,'event_integration_approved',['batch_key'=>$u['batch_key'],'event_system'=>$u['event_system'],'data_mode'=>$u['data_mode'],'operation'=>$operation,'event_id'=>$eventId],'event_integration_update',$id);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private static function applyExistingJackJillEdit(PDO $pdo,array $payload,bool $test):int
    {
        $mode=$test?'test':'live';$eventId=(int)$payload['target_event_id'];$roundId=(int)($payload['target_round_id']??0);$current=self::jackJillEditSnapshot($pdo,$eventId,$roundId,$mode,true);$before=$payload['before']??null;if(!is_array($before)||json_encode($before)!==json_encode($current))throw new RuntimeException('The event or round changed after this edit was submitted. Submit a fresh package.');$changes=(array)($payload['changes']??[]);$events=$test?'bdc_test_events':'bdc_events';$rounds=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
        $structural=array_intersect(array_keys($changes),['dance_style','division','round_type','scoring_mode']);if($structural&&self::jackJillRoundHasScoring($pdo,$roundId,$mode))throw new RuntimeException('Structural round fields are locked because scoring has started.');if($structural)self::assertUniqueJackJillRoundConfiguration($pdo,$eventId,$roundId,$mode,$changes,$current);
        $eventSet=[];$eventParams=['id'=>$eventId];foreach(['event_name'=>'name','event_date'=>'event_date','location'=>'location','venue'=>'venue','event_status'=>'status'] as $input=>$column)if(array_key_exists($input,$changes)){$nullable=in_array($input,['event_date','location','venue'],true);$eventSet[]=$column.'='.($nullable?"NULLIF(:{$input},'')":':'.$input);$eventParams[$input]=$changes[$input];}if(array_key_exists('event_name',$changes)){$eventSet[]='normalised_name=:normalised_name';$eventParams['normalised_name']=self::lower((string)$changes['event_name']);}if($eventSet){$eventSet[]='updated_at=NOW()';$pdo->prepare("UPDATE {$events} SET ".implode(',',$eventSet).' WHERE id=:id')->execute($eventParams);}
        $roundSet=[];$roundParams=['id'=>$roundId];foreach(['scheduled_at','dance_style','division','round_type','scoring_mode'] as $field)if(array_key_exists($field,$changes)){$roundSet[]=$field.'='.($field==='scheduled_at'?"NULLIF(:{$field},'')":':'.$field);$roundParams[$field]=$changes[$field];}if($roundSet){$roundSet[]='updated_at=NOW()';$pdo->prepare("UPDATE {$rounds} SET ".implode(',',$roundSet).' WHERE id=:id')->execute($roundParams);}
        return $eventId;
    }

    private static function applyExistingJackJillCompetitors(PDO $pdo,array $payload,bool $test):int
    {
        $mode=$test?'test':'live';$eventId=(int)$payload['target_event_id'];$roundId=(int)$payload['target_round_id'];
        $target=self::requireDraftJackJillRound($pdo,$eventId,$roundId,$mode,true);
        if((string)$target['dance_style']!==(string)$payload['dance_style']||(string)$target['division']!==(string)$payload['division'])throw new RuntimeException('The target round changed after this update was submitted. Submit a fresh package.');
        $entries=$test?'bdc_test_scoring_entries':'bdc_scoring_entries';
        foreach((array)$payload['competitors'] as $competitor){
            $profile=JackJillCompetitorEligibilityService::requireEligible($pdo,(string)$target['dance_style'],(string)$competitor['council_id'],(string)$competitor['role']);
            $eligibility=DivisionProgressionService::eligibilityFromApprovedHistory($pdo,(int)$profile['id'],(string)$competitor['role'],(string)$target['dance_style'],(string)$target['division']);
            if(!$eligibility['eligible'])throw new RuntimeException('Cannot add '.$profile['exact_name'].': '.$eligibility['reason']);
            if($test)CompetitorIdentityService::mirrorOfficialToTest($pdo,$profile);
            ScoringEntryLifecycleService::restoreOrInsert($pdo,$entries,$roundId,(int)$profile['id'],(string)$competitor['role'],(int)$competitor['bib'],(string)$profile['exact_name']);
        }
        return $eventId;
    }

    private static function applyExistingJackJillRosterSync(PDO $pdo,array $payload,bool $test):int
    {
        $mode=$test?'test':'live';$eventId=(int)$payload['target_event_id'];$roundId=(int)$payload['target_round_id'];$target=self::requireDraftJackJillRound($pdo,$eventId,$roundId,$mode,true);if(self::jackJillRoundHasScoring($pdo,$roundId,$mode))throw new RuntimeException('Roster and bib synchronization is locked because scoring has started.');if((string)$target['dance_style']!==(string)$payload['dance_style']||(string)$target['division']!==(string)$payload['division'])throw new RuntimeException('The target round changed after this synchronization was submitted. Submit a fresh package.');$current=self::activeJackJillRoster($pdo,$roundId,$mode);if(!hash_equals((string)$payload['before_roster_hash'],self::rosterHash($current)))throw new RuntimeException('The active roster changed after this synchronization was submitted. Submit a fresh package.');$entries=$test?'bdc_test_scoring_entries':'bdc_scoring_entries';$existing=[];foreach($current as $row)$existing[(int)$row['competitor_id']."\0".$row['dance_role']]=$row;$pdo->prepare("UPDATE {$entries} SET bib_number=1000000+id WHERE round_id=:round AND entry_status='active'")->execute(['round'=>$roundId]);$update=$pdo->prepare("UPDATE {$entries} SET bib_number=:bib,display_name=:name,updated_at=NOW() WHERE id=:id");foreach((array)$payload['competitors'] as $competitor){$profile=JackJillCompetitorEligibilityService::requireEligible($pdo,(string)$target['dance_style'],(string)$competitor['council_id'],(string)$competitor['role']);$eligibility=DivisionProgressionService::eligibilityFromApprovedHistory($pdo,(int)$profile['id'],(string)$competitor['role'],(string)$target['dance_style'],(string)$target['division']);if(!$eligibility['eligible'])throw new RuntimeException('Cannot synchronize '.$profile['exact_name'].': '.$eligibility['reason']);if($test)CompetitorIdentityService::mirrorOfficialToTest($pdo,$profile);$key=(int)$profile['id']."\0".$competitor['role'];if(isset($existing[$key]))$update->execute(['bib'=>(int)$competitor['bib'],'name'=>(string)$profile['exact_name'],'id'=>(int)$existing[$key]['id']]);else ScoringEntryLifecycleService::restoreOrInsert($pdo,$entries,$roundId,(int)$profile['id'],(string)$competitor['role'],(int)$competitor['bib'],(string)$profile['exact_name']);}return $eventId;
    }

    private static function applyExistingJackJillRosterChange(PDO $pdo,array $payload,bool $test,bool $removal):int
    {
        $mode=$test?'test':'live';$eventId=(int)$payload['target_event_id'];$roundId=(int)$payload['target_round_id'];$target=self::requireDraftJackJillRound($pdo,$eventId,$roundId,$mode,true);if(self::jackJillRoundHasScoring($pdo,$roundId,$mode))throw new RuntimeException('Roster changes are locked because scoring has started.');if((string)$target['dance_style']!==(string)$payload['dance_style']||(string)$target['division']!==(string)$payload['division'])throw new RuntimeException('The target round changed after this roster change was submitted. Submit a fresh package.');$current=self::activeJackJillRoster($pdo,$roundId,$mode);if(!hash_equals((string)$payload['before_roster_hash'],self::rosterHash($current)))throw new RuntimeException('The active roster changed after this roster change was submitted. Submit a fresh package.');$entries=$test?'bdc_test_scoring_entries':'bdc_scoring_entries';
        if($removal){$update=$pdo->prepare("UPDATE {$entries} SET entry_status='withdrawn',updated_at=NOW() WHERE id=:id AND round_id=:round AND entry_status='active'");foreach((array)$payload['competitors'] as $row){$update->execute(['id'=>(int)$row['id'],'round'=>$roundId]);if($update->rowCount()!==1)throw new RuntimeException('An entry selected for removal is no longer active.');}}
        else{$updates=(array)$payload['updates'];$temporary=$pdo->prepare("UPDATE {$entries} SET bib_number=1000000+id WHERE id=:id AND round_id=:round AND entry_status='active'");foreach($updates as $row){$temporary->execute(['id'=>(int)$row['id'],'round'=>$roundId]);if($temporary->rowCount()!==1)throw new RuntimeException('An entry selected for a bib amendment is no longer active.');}$update=$pdo->prepare("UPDATE {$entries} SET bib_number=:bib,updated_at=NOW() WHERE id=:id AND round_id=:round AND entry_status='active'");foreach($updates as $row)$update->execute(['bib'=>(int)$row['bib'],'id'=>(int)$row['id'],'round'=>$roundId]);}
        return $eventId;
    }

    private static function applyJackJill(PDO $pdo,array $payload,bool $test,int $userId):int
    {
        $events=$test?'bdc_test_events':'bdc_events';$rounds=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$entries=$test?'bdc_test_scoring_entries':'bdc_scoring_entries';$judges=$test?'bdc_test_scoring_judges':'bdc_scoring_judges';$event=$payload['event'];$slug=self::uniqueSlug($pdo,$events,$event['name']);
        $pdo->prepare("INSERT INTO {$events}(name,normalised_name,slug,event_date,location,venue,status) VALUES(:name,:normalised,:slug,NULLIF(:date,''),:location,:venue,'draft')")->execute(['name'=>$event['name'],'normalised'=>self::lower($event['name']),'slug'=>$slug,'date'=>$event['event_date'],'location'=>$event['country'],'venue'=>$event['venue']]);$eventId=(int)$pdo->lastInsertId();
        $insertRound=$pdo->prepare("INSERT INTO {$rounds}(event_id,dance_style,round_type,scheduled_at,scoring_mode,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,status,created_by) VALUES(:event,:dance,:type,NULLIF(:scheduled,''),:scoring,:division,:yes_count,:callback_count,10.00,4.50,4.30,4.20,'draft',:user)");
        $insertEntry=$pdo->prepare("INSERT INTO {$entries}(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:round,:competitor,:role,:bib,:name,'active')");$insertJudge=$pdo->prepare("INSERT INTO {$judges}(judge_id,round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:judge,:round,:name,:position,:chief,:scope)");
        foreach($payload['rounds'] as $round){$insertRound->execute(['event'=>$eventId,'dance'=>$round['dance_style'],'type'=>$round['round_type'],'scheduled'=>$round['scheduled_at'],'scoring'=>$round['scoring_mode'],'division'=>$round['division'],'yes_count'=>$round['yes_count'],'callback_count'=>$round['callback_count'],'user'=>$userId?:null]);$roundId=(int)$pdo->lastInsertId();foreach($round['competitors'] as $c)$insertEntry->execute(['round'=>$roundId,'competitor'=>$c['competitor_id'],'role'=>$c['role'],'bib'=>$c['bib'],'name'=>$c['name']]);$chief=0;foreach($round['judges'] as $j){$insertJudge->execute(['judge'=>$j['judge_id'],'round'=>$roundId,'name'=>$j['name'],'position'=>$j['order'],'chief'=>$j['chief']?1:0,'scope'=>$j['scope']]);if($j['chief'])$chief=(int)$pdo->lastInsertId();}$pdo->prepare("UPDATE {$rounds} SET chief_judge_id=:chief WHERE id=:round")->execute(['chief'=>$chief,'round'=>$roundId]);}
        return$eventId;
    }

    private static function applyDanceCup(PDO $pdo,array $payload,bool $test,int $userId):int
    {
        $tables=DanceCupScoringService::tables($test);$prefix=$test?'bdc_test_dance_cup':'bdc_dance_cup';$event=$payload['event'];$pdo->prepare("INSERT INTO {$tables['events']}(name,event_date,venue,country,scoring_mode,status,created_by) VALUES(:name,NULLIF(:date,''),:venue,:country,:mode,'draft',:user)")->execute(['name'=>$event['name'],'date'=>$event['event_date'],'venue'=>$event['venue'],'country'=>$event['country'],'mode'=>$event['scoring_mode'],'user'=>$userId?:null]);$eventId=(int)$pdo->lastInsertId();
        $insertCategory=$pdo->prepare("INSERT INTO {$tables['competitions']}(event_id,category_name,entry_type,dance_style,competition_level,gender_eligibility,performance_type,round_name,scoring_mode,maximum_score,status,created_by) VALUES(:event,:name,:entry_type,:dance,:level,:gender,:performance,:round_name,:mode,:maximum,'draft',:user)");$insertCriterion=$pdo->prepare("INSERT INTO {$tables['criteria']}(competition_id,criterion_name,maximum_points,sort_order) VALUES(:competition,:name,:maximum,:sort)");$insertEntry=$pdo->prepare("INSERT INTO {$prefix}_entries(competition_id,competitor_id,wdc_identity_id,bib_number,display_name,status) VALUES(:competition,:competitor,:wdc,:bib,:name,'active')");$insertJudge=$pdo->prepare("INSERT INTO {$prefix}_judges(competition_id,judge_id,judge_name,judge_order,is_chief) VALUES(:competition,:judge,:name,:position,:chief)");$insertSession=$pdo->prepare("INSERT INTO {$prefix}_judge_sessions(competition_id,judge_assignment_id,access_token) VALUES(:competition,:judge,:token)");
        $firstCompetition=0;foreach($payload['categories'] as $category){$insertCategory->execute(['event'=>$eventId,'name'=>$category['name'],'entry_type'=>$category['entry_type'],'dance'=>$category['dance_style'],'level'=>$category['competition_level'],'gender'=>$category['gender_eligibility'],'performance'=>$category['performance_type'],'round_name'=>$category['round_name'],'mode'=>$event['scoring_mode'],'maximum'=>$category['maximum_score'],'user'=>$userId?:null]);$competitionId=(int)$pdo->lastInsertId();if(!$firstCompetition)$firstCompetition=$competitionId;foreach($category['criteria'] as $index=>$criterion)$insertCriterion->execute(['competition'=>$competitionId,'name'=>$criterion['name'],'maximum'=>$criterion['max'],'sort'=>$index+1]);foreach($category['competitors'] as $c)$insertEntry->execute(['competition'=>$competitionId,'competitor'=>$c['competitor_id'],'wdc'=>$c['wdc_identity_id'],'bib'=>$c['bib'],'name'=>$c['name']]);foreach($category['judges'] as $j){$insertJudge->execute(['competition'=>$competitionId,'judge'=>$j['judge_id'],'name'=>$j['name'],'position'=>$j['order'],'chief'=>$j['chief']?1:0]);if($event['scoring_mode']==='automatic')$insertSession->execute(['competition'=>$competitionId,'judge'=>(int)$pdo->lastInsertId(),'token'=>bin2hex(random_bytes(32))]);}}
        if($firstCompetition)$pdo->prepare("INSERT INTO {$prefix}_event_projection(event_id,active_competition_id,access_token) VALUES(:event,:competition,:token)")->execute(['event'=>$eventId,'competition'=>$firstCompetition,'token'=>bin2hex(random_bytes(32))]);return$eventId;
    }

    private static function uniqueSlug(PDO $pdo,string $table,string $name):string{$base=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$name),'-'))?:'event';$slug=$base;$n=2;$q=$pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE slug=:slug");while(true){$q->execute(['slug'=>$slug]);if(!(int)$q->fetchColumn())return$slug;$slug=$base.'-'.$n++;}}
    private static function lower(string $value):string{return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);}
    private static function length(string $value):int{return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value);}
    public static function batchStatus(PDO $pdo,string $batchKey):?array{$q=$pdo->prepare('SELECT id,batch_key,source_system,status,submitted_at,created_at,updated_at FROM bdc_event_integration_batches WHERE batch_key=:batch LIMIT 1');$q->execute(['batch'=>$batchKey]);$batch=$q->fetch();if(!$batch)return null;$counts=$pdo->prepare('SELECT event_system,data_mode,status,validation_status,COUNT(*) count FROM bdc_event_integration_updates WHERE batch_id=:id GROUP BY event_system,data_mode,status,validation_status');$counts->execute(['id'=>$batch['id']]);$batch['counts']=$counts->fetchAll();unset($batch['id']);return$batch;}
    private static function refreshBatch(PDO $pdo,int $batchId):void{$q=$pdo->prepare('SELECT status,COUNT(*) total FROM bdc_event_integration_updates WHERE batch_id=:id GROUP BY status');$q->execute(['id'=>$batchId]);$counts=[];foreach($q->fetchAll() as $r)$counts[$r['status']]=(int)$r['total'];$pending=$counts['pending']??0;$reviewed=($counts['approved']??0)+($counts['rejected']??0);$status=$pending?($reviewed?'partially_reviewed':'pending_review'):(($counts['approved']??0)?'completed':'rejected');$pdo->prepare('UPDATE bdc_event_integration_batches SET status=:status,updated_at=NOW() WHERE id=:id')->execute(['status'=>$status,'id'=>$batchId]);}
}
