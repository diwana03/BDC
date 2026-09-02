<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;
use Throwable;

final class ApiChangeProposalService
{
    private const ACTIONS=['competitor.update','competitor.archive','judge.update','judge.deactivate','sdc.update','sdc.remove'];
    private const COMPETITOR_FIELDS=['exact_name','country','email','phone','instagram','status'];
    private const JUDGE_FIELDS=['full_name','display_name','country','email','phone','instagram','status'];
    private const SDC_CATEGORIES=['salsa_rising','salsa_open','salsa_invitational'];

    public static function submit(PDO $pdo,array $input):array
    {
        $source=substr(trim((string)($input['source_system']??'profile_api')),0,80)?:'profile_api';
        $actions=$input['actions']??null;
        if(!is_array($actions)||$actions===[]||count($actions)>100)throw new RuntimeException('actions must contain between 1 and 100 proposals.');
        $result=[];
        foreach($actions as $index=>$action){
            try{$result[]=self::stage($pdo,$source,is_array($action)?$action:[],$index);}
            catch(Throwable $e){$result[]=['index'=>$index,'status'=>'failed','error'=>$e->getMessage()];}
        }
        return ['status'=>'pending_super_admin_review','actions'=>$result];
    }

    private static function stage(PDO $pdo,string $source,array $action,int $index):array
    {
        $key=substr(trim((string)($action['proposal_key']??'')),0,191);
        $type=strtolower(trim((string)($action['action_type']??'')));
        $target=(int)($action['target_id']??0);
        if($key===''||!preg_match('/^[A-Za-z0-9._:-]+$/',$key))throw new RuntimeException('A stable proposal_key is required.');
        if(!in_array($type,self::ACTIONS,true))throw new RuntimeException('Unsupported action_type. Raw SQL is never accepted.');
        if($target<1)throw new RuntimeException('A valid target_id is required.');
        $payload=self::payload($type,is_array($action['payload']??null)?$action['payload']:[]);
        $before=self::snapshot($pdo,$type,$target,false);
        $beforeJson=self::encode($before);$payloadJson=self::encode($payload);$hash=hash('sha256',$type."\0".$target."\0".$beforeJson);
        $existing=$pdo->prepare('SELECT id,status FROM bdc_api_change_proposals WHERE proposal_key=:key');$existing->execute(['key'=>$key]);
        if($row=$existing->fetch())return ['index'=>$index,'proposal_key'=>$key,'proposal_id'=>(int)$row['id'],'status'=>'duplicate','review_status'=>$row['status']];
        $q=$pdo->prepare("INSERT INTO bdc_api_change_proposals(proposal_key,source_system,action_type,target_id,payload_json,before_json,state_hash,status) VALUES(:key,:source,:type,:target,:payload,:before,:hash,'pending')");
        $q->execute(['key'=>$key,'source'=>$source,'type'=>$type,'target'=>$target,'payload'=>$payloadJson,'before'=>$beforeJson,'hash'=>$hash]);
        return ['index'=>$index,'proposal_key'=>$key,'proposal_id'=>(int)$pdo->lastInsertId(),'status'=>'pending'];
    }

    private static function payload(string $type,array $payload):array
    {
        if(str_ends_with($type,'.archive')||str_ends_with($type,'.deactivate')||str_ends_with($type,'.remove'))return [];
        if($type==='sdc.update'){
            $allowed=['dance_role','categories'];if(array_diff(array_keys($payload),$allowed))throw new RuntimeException('SDC payload contains unsupported fields.');
            $role=strtolower(trim((string)($payload['dance_role']??'')));if($role!==''&&!in_array($role,['leader','follower','both'],true))throw new RuntimeException('Invalid SDC dance_role.');
            $categories=array_values(array_unique(array_map('strtolower',(array)($payload['categories']??[]))));
            if(array_diff($categories,self::SDC_CATEGORIES))throw new RuntimeException('Invalid SDC category. Novice is not permitted.');
            if($role==='')unset($payload['dance_role']);else$payload['dance_role']=$role;$payload['categories']=$categories;return $payload;
        }
        $fields=str_starts_with($type,'judge.')?self::JUDGE_FIELDS:self::COMPETITOR_FIELDS;
        if(array_diff(array_keys($payload),$fields))throw new RuntimeException('Payload contains unsupported fields.');
        if(!$payload)throw new RuntimeException('At least one field is required.');
        $clean=[];foreach($payload as $field=>$value){$value=trim((string)$value);if(mb_strlen($value)>500)throw new RuntimeException($field.' is too long.');$clean[$field]=$value;}
        if(isset($clean['email'])&&$clean['email']!==''&&!filter_var($clean['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid email.');
        if(isset($clean['status'])){$valid=str_starts_with($type,'judge.')?['active','inactive']:['active','pending','archived'];if(!in_array($clean['status'],$valid,true))throw new RuntimeException('Invalid status.');}
        return $clean;
    }

    private static function snapshot(PDO $pdo,string $type,int $target,bool $lock):array
    {
        $table=str_starts_with($type,'judge.')?'bdc_judges':'bdc_competitors';$suffix=$lock?' FOR UPDATE':'';
        $q=$pdo->prepare("SELECT * FROM {$table} WHERE id=:id{$suffix}");$q->execute(['id'=>$target]);$row=$q->fetch();if(!$row)throw new RuntimeException('Target record does not exist.');
        if(str_starts_with($type,'sdc.')){
            $profile=SdcCompetitorService::profile($pdo,$target);
            return ['competitor'=>array_intersect_key($row,array_flip(['id','exact_name','status'])),'profile'=>$profile?array_intersect_key($profile,array_flip(['sdc_id','dance_role','current_division','status'])):null,'categories'=>$profile['special_categories']??[]];
        }
        $fields=str_starts_with($type,'judge.')?array_merge(['id'],self::JUDGE_FIELDS):array_merge(['id'],self::COMPETITOR_FIELDS);
        return array_intersect_key($row,array_flip($fields));
    }

    public static function review(PDO $pdo,array $ids,string $decision,int $userId):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids))));if(!$ids)throw new RuntimeException('Select at least one proposal.');
        if(!in_array($decision,['approve','reject'],true))throw new RuntimeException('Invalid review decision.');
        $result=['approved'=>0,'rejected'=>0,'failed'=>0,'errors'=>[]];
        foreach($ids as $id){try{self::reviewOne($pdo,$id,$decision,$userId);$result[$decision==='approve'?'approved':'rejected']++;}catch(Throwable $e){$result['failed']++;$result['errors'][]='#'.$id.' '.$e->getMessage();}}
        return $result;
    }

    private static function reviewOne(PDO $pdo,int $id,string $decision,int $userId):void
    {
        $pdo->beginTransaction();
        try{
            $q=$pdo->prepare("SELECT * FROM bdc_api_change_proposals WHERE id=:id AND status='pending' FOR UPDATE");$q->execute(['id'=>$id]);$proposal=$q->fetch();if(!$proposal)throw new RuntimeException('Proposal is no longer pending.');
            if($decision==='reject'){$pdo->prepare("UPDATE bdc_api_change_proposals SET status='rejected',reviewed_by=:user,reviewed_at=NOW() WHERE id=:id")->execute(['user'=>$userId,'id'=>$id]);$pdo->commit();Auth::audit($userId,'api_change_rejected',['proposal_key'=>$proposal['proposal_key'],'action_type'=>$proposal['action_type']],'api_change_proposal',$id);return;}
            $current=self::snapshot($pdo,(string)$proposal['action_type'],(int)$proposal['target_id'],true);$currentJson=self::encode($current);$hash=hash('sha256',$proposal['action_type']."\0".$proposal['target_id']."\0".$currentJson);
            if(!hash_equals((string)$proposal['state_hash'],$hash))throw new RuntimeException('Live data changed after submission. Reject and resubmit the proposal.');
            $payload=json_decode((string)$proposal['payload_json'],true);if(!is_array($payload))throw new RuntimeException('Stored payload is invalid.');
            self::apply($pdo,(string)$proposal['action_type'],(int)$proposal['target_id'],$payload);
            $pdo->prepare("UPDATE bdc_api_change_proposals SET status='approved',reviewed_by=:user,reviewed_at=NOW(),failure_message=NULL WHERE id=:id")->execute(['user'=>$userId,'id'=>$id]);$pdo->commit();
            Auth::audit($userId,'api_change_approved',['proposal_key'=>$proposal['proposal_key'],'action_type'=>$proposal['action_type'],'before'=>json_decode((string)$proposal['before_json'],true),'payload'=>$payload],'api_change_proposal',$id);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function apply(PDO $pdo,string $type,int $id,array $payload):void
    {
        if($type==='competitor.archive'){$pdo->prepare("UPDATE bdc_competitors SET status='archived' WHERE id=:id")->execute(['id'=>$id]);return;}
        if($type==='judge.deactivate'){$pdo->prepare("UPDATE bdc_judges SET status='inactive' WHERE id=:id")->execute(['id'=>$id]);return;}
        if($type==='sdc.remove'){
            $history=$pdo->prepare("SELECT (SELECT COUNT(*) FROM bdc_participant_results WHERE competitor_id=:a AND dance_style='salsa')+(SELECT COUNT(*) FROM bdc_point_transactions WHERE competitor_id=:b AND dance_style='salsa')");$history->execute(['a'=>$id,'b'=>$id]);if((int)$history->fetchColumn()>0)throw new RuntimeException('Official Salsa history protects this SDC profile.');
            $competitor=$pdo->prepare('SELECT bdc_id,exact_name FROM bdc_competitors WHERE id=:id');$competitor->execute(['id'=>$id]);$person=$competitor->fetch();
            $identity=$pdo->prepare("SELECT * FROM bdc_result_identities WHERE competitor_id=:id AND council='sdc'");$identity->execute(['id'=>$id]);$identities=$identity->fetchAll();
            $profile=$pdo->prepare("SELECT * FROM bdc_competitor_discipline_profiles WHERE competitor_id=:id AND dance_style='salsa'");$profile->execute(['id'=>$id]);$profiles=$profile->fetchAll();
            $categories=$pdo->prepare("SELECT * FROM bdc_competitor_special_categories WHERE competitor_id=:id AND dance_style='salsa' ORDER BY id");$categories->execute(['id'=>$id]);$categoryRows=$categories->fetchAll();
            $pdo->prepare("INSERT INTO bdc_sdc_association_removal_archive(competitor_id,bdc_id,exact_name,identity_json,profile_json,categories_json,approval_note) VALUES(:id,:bdc,:name,:identity,:profile,:categories,'Approved in Super Admin API Changes panel') ON DUPLICATE KEY UPDATE bdc_id=VALUES(bdc_id),exact_name=VALUES(exact_name),identity_json=VALUES(identity_json),profile_json=VALUES(profile_json),categories_json=VALUES(categories_json),approval_note=VALUES(approval_note),removed_at=NOW()")
                ->execute(['id'=>$id,'bdc'=>(string)($person['bdc_id']??''),'name'=>(string)($person['exact_name']??''),'identity'=>self::encode($identities),'profile'=>self::encode($profiles),'categories'=>self::encode($categoryRows)]);
            SdcCompetitorService::archive($pdo,$id);return;
        }
        if($type==='sdc.update'){
            $current=SdcCompetitorService::profile($pdo,$id);$role=(string)($payload['dance_role']??($current['dance_role']??'both'));$division=(string)($current['current_division']??'unknown');SdcCompetitorService::save($pdo,$id,$role,$division,(array)($payload['categories']??($current['special_categories']??[])),'api_approved','API Changes panel');return;
        }
        $table=str_starts_with($type,'judge.')?'bdc_judges':'bdc_competitors';$allowed=str_starts_with($type,'judge.')?self::JUDGE_FIELDS:self::COMPETITOR_FIELDS;$sets=[];$params=['id'=>$id];foreach($payload as $field=>$value){if(!in_array($field,$allowed,true))throw new RuntimeException('Stored field is unsupported.');$sets[]="{$field}=:{$field}";$params[$field]=$value===''?null:$value;}if(!$sets)throw new RuntimeException('No fields to update.');
        $pdo->prepare("UPDATE {$table} SET ".implode(',',$sets).' WHERE id=:id')->execute($params);
    }

    private static function encode(array $value):string{$json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);if($json===false)throw new RuntimeException('Data could not be encoded.');return $json;}
}
