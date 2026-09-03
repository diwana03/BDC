<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;
use Throwable;

final class ProfileIntegrationService
{
    private const PHOTO_TYPES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

    public static function submitBatch(PDO $pdo,array $input):array
    {
        $batchKey=substr(trim((string)($input['batch_key']??'')),0,191);
        $source=substr(trim((string)($input['source_system']??'profile_api')),0,80)?:'profile_api';
        $items=$input['items']??null;
        if($batchKey===''||!preg_match('/^[A-Za-z0-9._:-]+$/',$batchKey))throw new RuntimeException('A stable batch_key is required.');
        if(!is_array($items)||$items===[]||count($items)>50)throw new RuntimeException('items must contain between 1 and 50 updates.');
        $pdo->prepare("INSERT INTO bdc_profile_integration_batches(batch_key,source_system,status,submitted_at) VALUES(:batch,:source,'receiving',NOW()) ON DUPLICATE KEY UPDATE submitted_at=NOW(),updated_at=NOW()")
            ->execute(['batch'=>$batchKey,'source'=>$source]);
        $q=$pdo->prepare('SELECT id,source_system FROM bdc_profile_integration_batches WHERE batch_key=:batch');$q->execute(['batch'=>$batchKey]);$batch=$q->fetch();
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
        $entity=strtolower(trim((string)($item['entity_type']??'')));
        if(!in_array($entity,['competitor','judge','wdc_identity'],true))throw new RuntimeException('entity_type must be competitor, judge or wdc_identity.');
        $scopeEntity=$entity==='wdc_identity'?'competitor':$entity;
        if(!ProfileIntegrationAuth::allowed($scopeEntity))throw new RuntimeException('The integration token is not permitted to submit '.$entity.' updates.');
        $sourceKey=substr(trim((string)($item['source_key']??'')),0,191);if($sourceKey==='')throw new RuntimeException('source_key is required for every item.');
        $payload=is_array($item['payload']??null)?$item['payload']:[];
        $canonical=$entity==='competitor'?self::competitorPayload($payload):($entity==='judge'?self::judgePayload($payload):self::wdcPayload($payload));
        $photo=self::stagePhoto($canonical,$batchId,$sourceKey);unset($canonical['photo_base64'],$canonical['photo_mime'],$canonical['photo_name']);
        $json=json_encode($canonical,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json===false)throw new RuntimeException('Profile payload could not be encoded.');
        $fingerprint=hash('sha256',$source."\0".$entity."\0".$sourceKey);$payloadHash=hash('sha256',$json."\0".($photo['hash']??''));
        $dupe=$pdo->prepare('SELECT id,status,batch_id FROM bdc_profile_integration_updates WHERE source_fingerprint=:fingerprint');$dupe->execute(['fingerprint'=>$fingerprint]);
        if($row=$dupe->fetch()){if($photo)@unlink(self::stagingDir().'/'.$photo['name']);return ['index'=>$index,'source_key'=>$sourceKey,'status'=>'duplicate','update_id'=>(int)$row['id']];}
        try{[$match,$target,$candidates]=$entity==='competitor'?self::matchCompetitor($pdo,$canonical):($entity==='judge'?self::matchJudge($pdo,$canonical):self::matchWdc($pdo,$canonical));}
        catch(Throwable $e){if($photo)@unlink(self::stagingDir().'/'.$photo['name']);throw$e;}
        $insert=$pdo->prepare("INSERT INTO bdc_profile_integration_updates(batch_id,entity_type,source_key,source_fingerprint,payload_hash,target_id,match_status,candidate_ids_json,payload_json,staged_photo_name,staged_photo_mime,staged_photo_hash,status) VALUES(:batch,:entity,:source_key,:fingerprint,:payload_hash,:target,:match_status,:candidates,:payload,:photo_name,:photo_mime,:photo_hash,'pending')");
        try{$insert->execute(['batch'=>$batchId,'entity'=>$entity,'source_key'=>$sourceKey,'fingerprint'=>$fingerprint,'payload_hash'=>$payloadHash,'target'=>$target?:null,'match_status'=>$match,'candidates'=>$candidates?json_encode($candidates):null,'payload'=>$json,'photo_name'=>$photo['name']??null,'photo_mime'=>$photo['mime']??null,'photo_hash'=>$photo['hash']??null]);}
        catch(Throwable $e){if($photo)@unlink(self::stagingDir().'/'.$photo['name']);$dupe->execute(['fingerprint'=>$fingerprint]);if($row=$dupe->fetch())return ['index'=>$index,'source_key'=>$sourceKey,'status'=>'duplicate','update_id'=>(int)$row['id']];throw$e;}
        return ['index'=>$index,'source_key'=>$sourceKey,'status'=>'pending','update_id'=>(int)$pdo->lastInsertId(),'match_status'=>$match,'target_id'=>$target?:null,'candidate_ids'=>$candidates];
    }

    private static function competitorPayload(array $p):array
    {
        $kind=strtolower(trim((string)($p['form_kind']??'')));if(!in_array($kind,['amateur','open'],true))throw new RuntimeException('Competitor form_kind must be amateur or open.');
        $name=trim((string)($p['full_name']??''));if($name===''||mb_strlen($name)>190)throw new RuntimeException('A valid competitor full_name is required.');
        $role=strtolower(trim((string)($p['role']??'')));$role=str_contains($role,'follow')?'follower':(str_contains($role,'lead')?'leader':$role);if(!in_array($role,['leader','follower','both'],true))throw new RuntimeException('Competitor role must be Lead, Follow or Both.');
        $styles=self::allowedList($p['styles']??[],['bachata','salsa']);if(!$styles)throw new RuntimeException('At least one competitor style is required.');
        $email=strtolower(trim((string)($p['email']??'')));if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Competitor email is invalid.');
        return ['form_kind'=>$kind,'full_name'=>$name,'bdc_id'=>strtoupper(trim((string)($p['bdc_id']??''))),'sdc_id'=>strtoupper(trim((string)($p['sdc_id']??''))),'role'=>$role,'styles'=>$styles,'email'=>$email,'phone'=>trim((string)($p['phone']??'')),'instagram'=>self::instagram((string)($p['instagram']??'')),'country'=>substr(trim((string)($p['country']??'')),0,100),'replace_fields'=>self::allowedList($p['replace_fields']??[],['full_name','email','phone','instagram','country','photo']),'photo_base64'=>(string)($p['photo_base64']??''),'photo_mime'=>(string)($p['photo_mime']??''),'photo_name'=>(string)($p['photo_name']??'')];
    }

    private static function judgePayload(array $p):array
    {
        $name=trim((string)($p['full_name']??''));if($name===''||mb_strlen($name)>160)throw new RuntimeException('A valid judge full_name is required.');
        $email=strtolower(trim((string)($p['email']??'')));if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Judge email is invalid.');
        $preferred=(string)($p['preferred_contact']??'none');if(!in_array($preferred,['email','whatsapp','either','none'],true))$preferred='none';
        $role=(string)($p['judge_role']??'regular');if(!in_array($role,['regular','chief','both'],true))$role='regular';
        return ['judge_code'=>strtoupper(trim((string)($p['judge_code']??''))),'full_name'=>$name,'display_name'=>trim((string)($p['display_name']??'')),'country'=>substr(trim((string)($p['country']??'')),0,100),'city'=>substr(trim((string)($p['city']??'')),0,120),'instagram'=>self::instagram((string)($p['instagram']??'')),'email'=>$email,'phone'=>trim((string)($p['phone']??'')),'whatsapp'=>trim((string)($p['whatsapp']??'')),'preferred_contact'=>$preferred,'dance_styles'=>self::allowedList($p['dance_styles']??[],['bachata','salsa']),'judge_role'=>$role,'qualified_divisions'=>self::allowedList($p['qualified_divisions']??[],['novice','intermediate','advanced','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open','semi_pro','pro','all_star']),'qualified_rounds'=>self::allowedList($p['qualified_rounds']??[],['heats','semifinal','final']),'languages'=>trim((string)($p['languages']??'')),'biography'=>trim((string)($p['biography']??'')),'experience'=>trim((string)($p['experience']??'')),'certification'=>trim((string)($p['certification']??'')),'photo_base64'=>(string)($p['photo_base64']??''),'photo_mime'=>(string)($p['photo_mime']??''),'photo_name'=>(string)($p['photo_name']??'')];
    }

    private static function wdcPayload(array $p):array
    {
        $operation=strtolower(trim((string)($p['operation']??'upsert')));
        if($operation==='photo_replace'){
            $allowed=['operation','wdc_id','photo_base64','photo_mime','photo_name'];
            if(array_diff(array_keys($p),$allowed))throw new RuntimeException('WDC photo replacement contains unsupported fields.');
            $wdcId=strtoupper(trim((string)($p['wdc_id']??'')));
            if(!preg_match('/^WDC-\d+$/',$wdcId))throw new RuntimeException('WDC photo replacement requires a valid WDC ID.');
            if(trim((string)($p['photo_base64']??''))==='')throw new RuntimeException('WDC photo replacement requires an original JPG, PNG or WebP image.');
            return ['operation'=>'photo_replace','wdc_id'=>$wdcId,'photo_base64'=>(string)$p['photo_base64'],'photo_mime'=>(string)($p['photo_mime']??''),'photo_name'=>(string)($p['photo_name']??'')];
        }
        if($operation!=='upsert')throw new RuntimeException('Invalid WDC operation.');
        $name=trim((string)preg_replace('/\s+/u',' ',(string)($p['display_name']??'')));if($name===''||mb_strlen($name)>190)throw new RuntimeException('A valid WDC display_name is required.');
        $type=strtolower(trim((string)($p['entry_type']??'')));if(!in_array($type,['solo','couple','duo','pro_am','team'],true))throw new RuntimeException('Invalid WDC entry_type.');
        $wdcId=strtoupper(trim((string)($p['wdc_id']??'')));if($wdcId!==''&&!preg_match('/^WDC-\d+$/',$wdcId))throw new RuntimeException('Invalid WDC ID.');
        $personId=(int)($p['person_id']??0);if($type!=='solo'&&$personId)throw new RuntimeException('Only solo WDC identities may link a shared person profile.');
        $registrations=[];$seen=[];
        foreach((array)($p['registrations']??[]) as $row){
            if(!is_array($row))throw new RuntimeException('Every WDC registration must be an object.');
            $eventKey=substr(trim((string)($row['event_key']??'')),0,191);$categoryKey=substr(trim((string)($row['category_key']??'')),0,120);
            if($eventKey===''||$categoryKey===''||!preg_match('/^[A-Za-z0-9._:-]+$/',$eventKey)||!preg_match('/^[A-Za-z0-9._:-]+$/',$categoryKey))throw new RuntimeException('Every WDC registration needs stable event_key and category_key values.');
            $dance=strtolower(trim((string)($row['dance_style']??'')));if(!in_array($dance,['bachata','salsa','other'],true))throw new RuntimeException('Invalid WDC registration dance_style.');
            $rowType=strtolower(trim((string)($row['entry_type']??$type)));if($rowType!==$type)throw new RuntimeException('WDC registration entry_type must match its identity.');
            $level=strtolower(trim((string)($row['competition_level']??'open')));if(!in_array($level,['amateur','intermediate','pro_am','professional','open'],true))throw new RuntimeException('Invalid WDC competition_level.');
            $eventName=substr(trim((string)($row['event_name']??'')),0,190);$categoryName=substr(trim((string)($row['category_name']??'')),0,190);if($eventName===''||$categoryName==='')throw new RuntimeException('WDC event_name and category_name are required.');
            $key=$eventKey."\0".$categoryKey;if(isset($seen[$key]))continue;$seen[$key]=true;
            $registrations[]=['event_key'=>$eventKey,'event_name'=>$eventName,'category_key'=>$categoryKey,'category_name'=>$categoryName,'dance_style'=>$dance,'entry_type'=>$type,'competition_level'=>$level];
        }
        if(!$registrations)throw new RuntimeException('At least one WDC registration is required.');
        return ['wdc_id'=>$wdcId,'entry_type'=>$type,'display_name'=>$name,'person_id'=>$personId,'country'=>substr(trim((string)($p['country']??'')),0,100),'photo_url'=>substr(trim((string)($p['photo_url']??'')),0,1000),'registrations'=>$registrations,'photo_base64'=>(string)($p['photo_base64']??''),'photo_mime'=>(string)($p['photo_mime']??''),'photo_name'=>(string)($p['photo_name']??'')];
    }

    private static function matchCompetitor(PDO $pdo,array $p):array
    {
        if($p['bdc_id']!==''){$q=$pdo->prepare("SELECT id FROM bdc_competitors WHERE bdc_id=:id AND status<>'archived'");$q->execute(['id'=>$p['bdc_id']]);$id=(int)($q->fetchColumn()?:0);return $id?['matched',$id,[]]:['invalid',0,[]];}
        if($p['sdc_id']!==''){$match=SdcCompetitorService::bySdcId($pdo,$p['sdc_id']);$id=(int)($match['competitor_id']??0);return $id?['matched',$id,[]]:['invalid',0,[]];}
        $name=CompetitorIdentityService::normaliseCompetitorName($p['full_name']);$q=$pdo->prepare("SELECT id,normalised_name,email,phone,instagram,dance_role FROM bdc_competitors WHERE status<>'archived' AND (normalised_name=:name OR LOWER(TRIM(email))=:email OR LOWER(TRIM(LEADING '@' FROM instagram))=:instagram OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-',''),'(',''),')','')=:phone) ORDER BY id LIMIT 30");
        $q->execute(['name'=>$name,'email'=>$p['email']?:'__none__','instagram'=>$p['instagram']?:'__none__','phone'=>self::digits($p['phone'])?:'__none__']);$rows=array_values(array_filter($q->fetchAll(),static fn(array $r):bool=>in_array((string)$r['dance_role'],[$p['role'],'both','unknown'],true)));
        $strong=array_values(array_filter($rows,static fn(array $r):bool=>($p['email']!==''&&strtolower(trim((string)$r['email']))===$p['email'])||($p['instagram']!==''&&self::instagram((string)$r['instagram'])===$p['instagram'])||(self::digits($p['phone'])!==''&&self::digits((string)$r['phone'])===self::digits($p['phone']))));
        $pool=$strong?:array_values(array_filter($rows,static fn(array $r):bool=>(string)$r['normalised_name']===$name));$ids=array_values(array_unique(array_map(static fn(array $r):int=>(int)$r['id'],$pool)));
        return count($ids)===1?['matched',$ids[0],[]]:(count($ids)>1?['ambiguous',0,$ids]:['new',0,[]]);
    }

    private static function matchJudge(PDO $pdo,array $p):array
    {
        JudgeDirectoryService::ensure($pdo);
        if($p['judge_code']!==''){$q=$pdo->prepare("SELECT id FROM bdc_judges WHERE judge_code=:code AND status='active'");$q->execute(['code'=>$p['judge_code']]);$id=(int)($q->fetchColumn()?:0);return $id?['matched',$id,[]]:['invalid',0,[]];}
        $q=$pdo->prepare("SELECT id,full_name,email,phone,instagram FROM bdc_judges WHERE status='active' AND (LOWER(TRIM(full_name))=:name OR LOWER(TRIM(email))=:email OR LOWER(TRIM(LEADING '@' FROM instagram))=:instagram OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-',''),'(',''),')','')=:phone) ORDER BY id LIMIT 30");
        $q->execute(['name'=>mb_strtolower($p['full_name']),'email'=>$p['email']?:'__none__','instagram'=>$p['instagram']?:'__none__','phone'=>self::digits($p['phone'])?:'__none__']);$rows=$q->fetchAll();$strong=array_values(array_filter($rows,static fn(array $r):bool=>($p['email']!==''&&strtolower(trim((string)$r['email']))===$p['email'])||($p['instagram']!==''&&self::instagram((string)$r['instagram'])===$p['instagram'])||(self::digits($p['phone'])!==''&&self::digits((string)$r['phone'])===self::digits($p['phone']))));$pool=$strong?:array_values(array_filter($rows,static fn(array $r):bool=>mb_strtolower(trim((string)$r['full_name']))===mb_strtolower($p['full_name'])));$ids=array_values(array_unique(array_map(static fn(array $r):int=>(int)$r['id'],$pool)));
        return count($ids)===1?['matched',$ids[0],[]]:(count($ids)>1?['ambiguous',0,$ids]:['new',0,[]]);
    }

    private static function matchWdc(PDO $pdo,array $p):array
    {
        if(($p['operation']??'upsert')==='photo_replace'){
            $q=$pdo->prepare("SELECT id FROM bdc_wdc_identities WHERE identity_code=:code AND status='active'");$q->execute(['code'=>$p['wdc_id']]);$id=(int)($q->fetchColumn()?:0);
            return $id?['matched',$id,[]]:['invalid',0,[]];
        }
        if($p['person_id']){$q=$pdo->prepare("SELECT id FROM bdc_competitors WHERE id=:id AND status<>'archived'");$q->execute(['id'=>$p['person_id']]);if(!$q->fetchColumn())throw new RuntimeException('The shared person profile does not exist.');}
        if($p['wdc_id']!==''){$q=$pdo->prepare("SELECT id FROM bdc_wdc_identities WHERE identity_code=:code AND status='active'");$q->execute(['code'=>$p['wdc_id']]);$id=(int)($q->fetchColumn()?:0);return $id?['matched',$id,[]]:['invalid',0,[]];}
        $normal=self::normaliseName($p['display_name']);
        $q=$p['entry_type']==='solo'&&$p['person_id']
            ?$pdo->prepare("SELECT id FROM bdc_wdc_identities WHERE entry_type='solo' AND status='active' AND (solo_competitor_id=:person OR normalised_name=:name) ORDER BY id")
            :$pdo->prepare("SELECT id FROM bdc_wdc_identities WHERE entry_type=:type AND normalised_name=:name AND status='active' ORDER BY id");
        $q->execute($p['entry_type']==='solo'&&$p['person_id']?['person'=>$p['person_id'],'name'=>$normal]:['type'=>$p['entry_type'],'name'=>$normal]);$ids=array_values(array_unique(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN))));
        return count($ids)===1?['matched',$ids[0],[]]:(count($ids)>1?['ambiguous',0,$ids]:['new',0,[]]);
    }

    private static function stagePhoto(array $p,int $batchId,string $sourceKey):?array
    {
        $encoded=(string)($p['photo_base64']??'');if($encoded==='')return null;$raw=base64_decode($encoded,true);if($raw===false||strlen($raw)>15*1024*1024)throw new RuntimeException('Photo is invalid or larger than 15 MB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);if(!isset(self::PHOTO_TYPES[$mime])||@getimagesizefromstring($raw)===false)throw new RuntimeException('Photo must be a valid JPG, PNG or WebP image.');
        $declared=strtolower(trim((string)($p['photo_mime']??'')));if($declared!==''&&!hash_equals($mime,$declared))throw new RuntimeException('Photo MIME type does not match its bytes.');
        $dir=self::stagingDir();if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Private photo staging is unavailable.');
        $name='batch-'.$batchId.'-'.substr(hash('sha256',$sourceKey),0,12).'-'.bin2hex(random_bytes(6)).'.'.self::PHOTO_TYPES[$mime];if(file_put_contents($dir.'/'.$name,$raw,LOCK_EX)!==strlen($raw))throw new RuntimeException('Photo could not be staged.');
        return ['name'=>$name,'mime'=>$mime,'hash'=>hash('sha256',$raw)];
    }

    public static function review(PDO $pdo,array $ids,string $decision,int $userId):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));if(!$ids)throw new RuntimeException('Select at least one pending update.');if(!in_array($decision,['approve','reject'],true))throw new RuntimeException('Invalid review decision.');
        $result=['approved'=>0,'rejected'=>0,'failed'=>0,'errors'=>[]];
        foreach($ids as $id){try{self::reviewOne($pdo,$id,$decision,$userId);$result[$decision==='approve'?'approved':'rejected']++;}catch(Throwable $e){$result['failed']++;$result['errors'][]='#'.$id.' '.$e->getMessage();}}
        return $result;
    }

    private static function reviewOne(PDO $pdo,int $id,string $decision,int $userId):void
    {
        $q=$pdo->prepare("SELECT u.*,b.batch_key,b.source_system FROM bdc_profile_integration_updates u JOIN bdc_profile_integration_batches b ON b.id=u.batch_id WHERE u.id=:id AND u.status='pending'");$q->execute(['id'=>$id]);$u=$q->fetch();if(!$u)throw new RuntimeException('Update is no longer pending.');
        if($decision==='reject'){$pdo->prepare("UPDATE bdc_profile_integration_updates SET status='rejected',staged_photo_name=NULL,staged_photo_mime=NULL,staged_photo_hash=NULL,reviewed_by=:user,reviewed_at=NOW() WHERE id=:id AND status='pending'")->execute(['user'=>$userId,'id'=>$id]);if(!empty($u['staged_photo_name']))@unlink(self::stagingDir().'/'.$u['staged_photo_name']);self::refreshBatch($pdo,(int)$u['batch_id']);Auth::audit($userId,'profile_integration_rejected',['batch_key'=>$u['batch_key'],'entity_type'=>$u['entity_type'],'source_key'=>$u['source_key']],'profile_integration_update',$id);return;}
        if(in_array($u['match_status'],['ambiguous','invalid'],true))throw new RuntimeException('Resolve the identity match before approval.');
        $payload=json_decode((string)$u['payload_json'],true);if(!is_array($payload))throw new RuntimeException('Stored payload is invalid.');
        $pdo->beginTransaction();$published=null;
        try{$published=$u['entity_type']==='competitor'?self::applyCompetitor($pdo,$u,$payload):($u['entity_type']==='judge'?self::applyJudge($pdo,$u,$payload):self::applyWdc($pdo,$u,$payload,$userId));$pdo->prepare("UPDATE bdc_profile_integration_updates SET status='approved',target_id=:target,reviewed_by=:user,reviewed_at=NOW() WHERE id=:id AND status='pending'")->execute(['target'=>$published['id'],'user'=>$userId,'id'=>$id]);$pdo->commit();if(!empty($u['staged_photo_name']))@unlink(self::stagingDir().'/'.$u['staged_photo_name']);self::refreshBatch($pdo,(int)$u['batch_id']);Auth::audit($userId,'profile_integration_approved',['batch_key'=>$u['batch_key'],'entity_type'=>$u['entity_type'],'source_key'=>$u['source_key']],'profile_integration_update',$id);}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if(!empty($published['new_photo_path']))@unlink($published['new_photo_path']);throw$e;}
    }

    private static function applyCompetitor(PDO $pdo,array $u,array $p):array
    {
        $id=(int)($u['target_id']??0);if(!$id){$created=CompetitorIdentityService::findOrCreateOfficial($pdo,$p['full_name'],$p['role']);$id=(int)$created['id'];}
        $q=$pdo->prepare('SELECT * FROM bdc_competitors WHERE id=:id');$q->execute(['id'=>$id]);$current=$q->fetch();if(!$current)throw new RuntimeException('Matched competitor no longer exists.');
        if(in_array('bachata',$p['styles'],true)&&trim((string)($current['bdc_id']??''))===''){$code=self::allocateBdcIdentity($pdo,$id);$current['bdc_id']=$code;}
        $replace=(array)($p['replace_fields']??[]);$value=static fn(string $field,string $incoming):?string=>$incoming!==''&&((string)($current[$field]??'')===''||in_array($field==='exact_name'?'full_name':$field,$replace,true))?$incoming:($current[$field]??null);
        [$photo,$path]=self::publishPhoto($u,'competitors','competitor-'.$id);if(!$photo)$photo=(string)($current['photo_url']??'');
        $pdo->prepare("UPDATE bdc_competitors SET exact_name=:name,normalised_name=:normalised,email=:email,phone=:phone,instagram=:instagram,country=:country,photo_url=:photo,original_photo_url=IF(:new_photo<>'',:new_photo2,original_photo_url),status=IF(status='archived',status,'active') WHERE id=:id")
            ->execute(['name'=>$value('exact_name',$p['full_name']),'normalised'=>CompetitorIdentityService::normaliseCompetitorName((string)$value('exact_name',$p['full_name'])),'email'=>$value('email',$p['email']),'phone'=>$value('phone',$p['phone']),'instagram'=>$value('instagram',$p['instagram']),'country'=>$value('country',$p['country']),'photo'=>$photo?:null,'new_photo'=>$photo&&$path?$photo:'','new_photo2'=>$photo&&$path?$photo:null,'id'=>$id]);
        $profile=$pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) VALUES(:id,:dance,:role,'unknown') ON DUPLICATE KEY UPDATE dance_role=VALUES(dance_role),updated_at=NOW()");
        $category=$pdo->prepare("INSERT IGNORE INTO bdc_competitor_special_categories(competitor_id,dance_style,category,source_kind,source_name) VALUES(:id,:dance,:category,'form_sync',:source)");
        foreach($p['styles'] as $dance){if($dance==='salsa'){SdcCompetitorService::save($pdo,$id,$p['role'],'unknown',['salsa_'.($p['form_kind']==='open'?'open':'rising')],'form_sync','Profile integration API / '.$u['source_key']);continue;}$profile->execute(['id'=>$id,'dance'=>$dance,'role'=>$p['role']]);$category->execute(['id'=>$id,'dance'=>$dance,'category'=>$dance.'_'.($p['form_kind']==='open'?'open':'rising'),'source'=>'Profile integration API / '.$u['source_key']]);$pdo->prepare('UPDATE bdc_competitors SET dance_role=:role WHERE id=:id')->execute(['role'=>$p['role'],'id'=>$id]);}
        CompetitorIdentityService::inspect($pdo,$id);return ['id'=>$id,'new_photo_path'=>$path];
    }

    private static function allocateBdcIdentity(PDO $pdo,int $competitorId):string
    {
        if((int)$pdo->query("SELECT GET_LOCK('bdc-bdc-identity-sequence',10)")->fetchColumn()!==1)throw new RuntimeException('Could not reserve a BDC ID. Please try again.');
        try{
            $q=$pdo->prepare("SELECT bdc_id FROM bdc_competitors WHERE id=:id AND status<>'archived' FOR UPDATE");$q->execute(['id'=>$competitorId]);$current=$q->fetchColumn();
            if($current===false)throw new RuntimeException('Matched competitor is archived or missing.');
            $code=trim((string)$current);
            if($code===''){
                $next=(int)$pdo->query("SELECT COALESCE(MAX(sequence_number),0)+1 FROM (SELECT CAST(SUBSTRING(bdc_id,5) AS UNSIGNED) sequence_number FROM bdc_competitors WHERE bdc_id LIKE 'BDC-%' UNION ALL SELECT CAST(SUBSTRING(bdc_id,5) AS UNSIGNED) FROM bdc_bdc_identity_detachment_archive WHERE bdc_id LIKE 'BDC-%') used_bdc_ids")->fetchColumn();
                $code='BDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);
                $pdo->prepare("UPDATE bdc_competitors SET bdc_id=:code,status='active' WHERE id=:id AND (bdc_id IS NULL OR bdc_id='')")->execute(['code'=>$code,'id'=>$competitorId]);
            }
            $pdo->prepare("INSERT INTO bdc_result_identities(competitor_id,council,identity_code) VALUES(:id,'bdc',:code) ON DUPLICATE KEY UPDATE identity_code=VALUES(identity_code)")->execute(['id'=>$competitorId,'code'=>$code]);
            return $code;
        }finally{$pdo->query("SELECT RELEASE_LOCK('bdc-bdc-identity-sequence')")->fetchColumn();}
    }

    private static function applyJudge(PDO $pdo,array $u,array $p):array
    {
        JudgeDirectoryService::ensure($pdo);[$photo,$path]=self::publishPhoto($u,'judges','judge-'.((int)($u['target_id']??0)?:'new'));if($photo)$p['photo_url']=$photo;
        foreach(['dance_styles','qualified_divisions','qualified_rounds'] as $field)$p[$field]=implode(',',(array)$p[$field]);
        $id=(int)($u['target_id']??0);if(!$id){$judge=JudgeDirectoryService::create($pdo,$p);$id=(int)$judge['id'];}else{$judge=JudgeDirectoryService::mergeProfile($pdo,$id,$p);$pdo->prepare('UPDATE bdc_judges SET full_name=:name WHERE id=:id')->execute(['name'=>$p['full_name'],'id'=>$id]);}
        if($photo)$pdo->prepare('UPDATE bdc_judges SET photo_url=:photo,original_photo_url=:original WHERE id=:id')->execute(['photo'=>$photo,'original'=>$photo,'id'=>$id]);
        return ['id'=>$id,'new_photo_path'=>$path];
    }

    private static function applyWdc(PDO $pdo,array $u,array $p,int $userId):array
    {
        $id=(int)($u['target_id']??0);$identity=null;
        if($id){$q=$pdo->prepare('SELECT * FROM bdc_wdc_identities WHERE id=:id FOR UPDATE');$q->execute(['id'=>$id]);$identity=$q->fetch();if(!$identity)throw new RuntimeException('Matched WDC identity no longer exists.');if(($p['operation']??'upsert')!=='photo_replace'&&$identity['entry_type']!==$p['entry_type'])throw new RuntimeException('WDC entry type changed after review submission.');}
        if(($p['operation']??'upsert')==='photo_replace'){
            if(!$identity||!hash_equals((string)$identity['identity_code'],(string)$p['wdc_id']))throw new RuntimeException('WDC identity changed after review submission.');
            [$photo,$path]=self::publishPhoto($u,'wdc','wdc-'.$id);if(!$photo||!$path)throw new RuntimeException('The approved WDC photo replacement is missing.');
            $pdo->prepare('UPDATE bdc_wdc_identities SET photo_url=:photo WHERE id=:id')->execute(['photo'=>$photo,'id'=>$id]);
            return ['id'=>$id,'new_photo_path'=>$path];
        }
        if(!$identity){$identity=CouncilResultIdentityService::wdcIdentityForEntry($pdo,$p['entry_type'],$p['display_name'],$p['person_id']?:null);$id=(int)$identity['id'];}
        $currentPerson=(int)($identity['solo_competitor_id']??0);if($currentPerson&&$p['person_id']&&$currentPerson!==$p['person_id'])throw new RuntimeException('WDC identity is already linked to another shared person profile.');
        [$publishedPhoto,$path]=self::publishPhoto($u,'wdc','wdc-'.$id);$photo=$publishedPhoto?:$p['photo_url'];$normal=self::normaliseName($p['display_name']);
        $pdo->prepare("UPDATE bdc_wdc_identities SET display_name=:name,normalised_name=:normal,solo_competitor_id=COALESCE(solo_competitor_id,NULLIF(:person,0)),country=COALESCE(NULLIF(:country,''),country),photo_url=COALESCE(NULLIF(:photo,''),photo_url),status='active' WHERE id=:id")
            ->execute(['name'=>$p['display_name'],'normal'=>$normal,'person'=>$p['person_id'],'country'=>$p['country'],'photo'=>$photo,'id'=>$id]);
        $insert=$pdo->prepare("INSERT INTO bdc_wdc_registrations(wdc_identity_id,event_key,event_name,category_key,category_name,dance_style,entry_type,competition_level,source_system,source_key,status,approved_by,approved_at) VALUES(:wdc,:event_key,:event_name,:category_key,:category_name,:dance,:entry_type,:level,:source,:source_key,'registered',:user,NOW()) ON DUPLICATE KEY UPDATE event_name=VALUES(event_name),category_name=VALUES(category_name),dance_style=VALUES(dance_style),entry_type=VALUES(entry_type),competition_level=VALUES(competition_level),source_system=VALUES(source_system),source_key=VALUES(source_key),status='registered',approved_by=VALUES(approved_by),approved_at=NOW()");
        foreach($p['registrations'] as $row)$insert->execute(['wdc'=>$id,'event_key'=>$row['event_key'],'event_name'=>$row['event_name'],'category_key'=>$row['category_key'],'category_name'=>$row['category_name'],'dance'=>$row['dance_style'],'entry_type'=>$row['entry_type'],'level'=>$row['competition_level'],'source'=>$u['source_system'],'source_key'=>$u['source_key'],'user'=>$userId?:null]);
        return ['id'=>$id,'new_photo_path'=>$path];
    }

    private static function publishPhoto(array $u,string $folder,string $prefix):array
    {
        $name=(string)($u['staged_photo_name']??'');if($name==='')return [null,null];$source=self::stagingDir().'/'.$name;if(!is_file($source)||!hash_equals((string)$u['staged_photo_hash'],hash_file('sha256',$source)))throw new RuntimeException('Staged photo is missing or changed.');
        $ext=self::PHOTO_TYPES[(string)$u['staged_photo_mime']]??null;if(!$ext)throw new RuntimeException('Staged photo type is invalid.');$dir=dirname(__DIR__,2).'/uploads/'.$folder;if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Photo destination is unavailable.');$file=$prefix.'-'.bin2hex(random_bytes(6)).'.'.$ext;$path=$dir.'/'.$file;if(!copy($source,$path))throw new RuntimeException('Photo could not be published.');return [url('uploads/'.$folder.'/'.$file),$path];
    }

    public static function assignTarget(PDO $pdo,int $updateId,int $targetId,string $entity):void
    {
        $table=$entity==='judge'?'bdc_judges':($entity==='wdc_identity'?'bdc_wdc_identities':'bdc_competitors');$q=$pdo->prepare("SELECT id FROM {$table} WHERE id=:id");$q->execute(['id'=>$targetId]);if(!$q->fetchColumn())throw new RuntimeException('Selected target does not exist.');$pdo->prepare("UPDATE bdc_profile_integration_updates SET target_id=:target,match_status='matched',candidate_ids_json=NULL WHERE id=:id AND entity_type=:entity AND status='pending'")->execute(['target'=>$targetId,'id'=>$updateId,'entity'=>$entity]);
    }

    public static function stagedPhoto(PDO $pdo,int $updateId):?array
    {
        $q=$pdo->prepare("SELECT staged_photo_name,staged_photo_mime FROM bdc_profile_integration_updates WHERE id=:id AND staged_photo_name IS NOT NULL LIMIT 1");$q->execute(['id'=>$updateId]);$row=$q->fetch();if(!$row)return null;$path=self::stagingDir().'/'.basename((string)$row['staged_photo_name']);return is_file($path)?['path'=>$path,'mime'=>(string)$row['staged_photo_mime']]:null;
    }

    public static function batchStatus(PDO $pdo,string $batchKey):?array
    {
        $q=$pdo->prepare('SELECT id,batch_key,source_system,status,submitted_at,created_at,updated_at FROM bdc_profile_integration_batches WHERE batch_key=:batch LIMIT 1');$q->execute(['batch'=>$batchKey]);$batch=$q->fetch();if(!$batch)return null;$counts=$pdo->prepare('SELECT entity_type,status,match_status,COUNT(*) count FROM bdc_profile_integration_updates WHERE batch_id=:id GROUP BY entity_type,status,match_status');$counts->execute(['id'=>$batch['id']]);$batch['counts']=$counts->fetchAll();unset($batch['id']);return $batch;
    }

    private static function refreshBatch(PDO $pdo,int $batchId):void
    {
        $q=$pdo->prepare("SELECT status,COUNT(*) total FROM bdc_profile_integration_updates WHERE batch_id=:id GROUP BY status");$q->execute(['id'=>$batchId]);$counts=[];foreach($q->fetchAll() as $r)$counts[$r['status']]=(int)$r['total'];$pending=$counts['pending']??0;$reviewed=($counts['approved']??0)+($counts['rejected']??0);$status=$pending?($reviewed?'partially_reviewed':'pending_review'):(($counts['approved']??0)?'completed':'rejected');$pdo->prepare('UPDATE bdc_profile_integration_batches SET status=:status,updated_at=NOW() WHERE id=:id')->execute(['status'=>$status,'id'=>$batchId]);
    }

    private static function stagingDir():string{return dirname(__DIR__,2).'/storage/profile-integration';}
    private static function normaliseName(string $value):string{$value=function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);return trim((string)preg_replace('/[^\pL\pN]+/u',' ',$value));}
    private static function allowedList(mixed $value,array $allowed):array{$list=is_array($value)?$value:preg_split('/\s*,\s*/',(string)$value);return array_values(array_unique(array_intersect($allowed,array_map(static fn($v):string=>strtolower(trim((string)$v)),(array)$list))));}
    private static function instagram(string $value):string{$value=trim($value);if(preg_match('#instagram\.com/([^/?]+)#i',$value,$m))$value=$m[1];return strtolower(ltrim($value,'@/'));}
    private static function digits(string $value):string{return preg_replace('/\D+/','',$value)??'';}
}
