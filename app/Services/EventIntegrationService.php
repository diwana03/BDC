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
        $canonical=$system==='jack_jill'?self::jackJillPayload($pdo,$payload,$mode):self::danceCupPayload($pdo,$payload,$mode);
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
        $pdo->beginTransaction();try{$eventId=$u['event_system']==='jack_jill'?self::applyJackJill($pdo,$payload,$test,$userId):self::applyDanceCup($pdo,$payload,$test,$userId);$pdo->prepare("UPDATE bdc_event_integration_updates SET status='approved',target_event_id=:event,reviewed_by=:user,reviewed_at=NOW(),error_message=NULL WHERE id=:id AND status='pending'")->execute(['event'=>$eventId,'user'=>$userId,'id'=>$id]);$pdo->commit();self::refreshBatch($pdo,(int)$u['batch_id']);Auth::audit($userId,'event_integration_approved',['batch_key'=>$u['batch_key'],'event_system'=>$u['event_system'],'data_mode'=>$u['data_mode'],'event_id'=>$eventId],'event_integration_update',$id);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
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
