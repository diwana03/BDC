<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;

final class ProfileDiagnosticQueryService
{
    public static function run(PDO $pdo,string $query,array $params):array
    {
        $limit=max(1,min(500,(int)($params['limit']??200)));
        return match($query){
            'sdc_members'=>self::rows($pdo,"SELECT s.competitor_id,c.bdc_id,s.sdc_id,c.exact_name,c.photo_url IS NOT NULL AND TRIM(c.photo_url)<>'' photo_present,s.dance_role salsa_role,s.current_division salsa_division FROM bdc_sdc_competitors s JOIN bdc_competitors c ON c.id=s.competitor_id WHERE s.status='active' ORDER BY LOWER(c.exact_name),c.id LIMIT $limit",[]),
            'wdc_members'=>self::rows($pdo,"SELECT w.id,w.identity_code,w.entry_type,w.display_name,w.country,w.solo_competitor_id,COALESCE(NULLIF(w.photo_url,''),NULLIF(c.photo_url,'')) IS NOT NULL photo_present,COUNT(r.id) registration_count,GROUP_CONCAT(CONCAT(r.event_key,':',r.category_key) ORDER BY r.event_key,r.category_key SEPARATOR ',') registrations FROM bdc_wdc_identities w LEFT JOIN bdc_competitors c ON c.id=w.solo_competitor_id LEFT JOIN bdc_wdc_registrations r ON r.wdc_identity_id=w.id AND r.status='registered' WHERE w.status='active' GROUP BY w.id,w.identity_code,w.entry_type,w.display_name,w.country,w.solo_competitor_id,w.photo_url,c.photo_url ORDER BY LOWER(w.display_name),w.id LIMIT $limit",[]),
            'person_match'=>self::personMatch($pdo,$params,$limit),
            'missing_photos'=>self::rows($pdo,"SELECT s.competitor_id,c.bdc_id,s.sdc_id,c.exact_name FROM bdc_sdc_competitors s JOIN bdc_competitors c ON c.id=s.competitor_id WHERE s.status='active' AND (c.photo_url IS NULL OR TRIM(c.photo_url)='') ORDER BY LOWER(c.exact_name),c.id LIMIT $limit",[]),
            'competitor_history'=>self::history($pdo,(int)($params['competitor_id']??0),$limit),
            'deletion_impact'=>self::deletionImpact($pdo,(int)($params['competitor_id']??0)),
            default=>throw new RuntimeException('Unknown diagnostic query. Allowed: sdc_members, wdc_members, person_match, missing_photos, competitor_history, deletion_impact.'),
        };
    }

    private static function history(PDO $pdo,int $id,int $limit):array
    {
        if($id<1)throw new RuntimeException('competitor_id is required.');
        return self::rows($pdo,"SELECT 'participant_result' record_type,id,event_id,dance_style,division,dance_role,placement,points_awarded points,created_at FROM bdc_participant_results WHERE competitor_id=:id UNION ALL SELECT 'point_transaction',id,event_id,dance_style,division,dance_role,placement,points,created_at FROM bdc_point_transactions WHERE competitor_id=:id2 ORDER BY created_at DESC LIMIT $limit",['id'=>$id,'id2'=>$id]);
    }

    private static function deletionImpact(PDO $pdo,int $id):array
    {
        if($id<1)throw new RuntimeException('competitor_id is required.');$q=$pdo->prepare("SELECT c.id competitor_id,c.bdc_id,c.exact_name,c.status,(SELECT COUNT(*) FROM bdc_result_identities WHERE competitor_id=c.id AND council='bdc') bdc_identities,(SELECT COUNT(*) FROM bdc_sdc_competitors WHERE competitor_id=c.id AND status='active') sdc_profiles,(SELECT COUNT(*) FROM bdc_participant_results WHERE competitor_id=c.id) participant_results,(SELECT COUNT(*) FROM bdc_point_transactions WHERE competitor_id=c.id) point_transactions FROM bdc_competitors c WHERE c.id=:id");$q->execute(['id'=>$id]);$row=$q->fetch();if(!$row)throw new RuntimeException('Competitor not found.');
        $row['simulation_only']=true;$row['deleted']=false;$row['requires_super_admin']=true;$row['approval_hash']=hash('sha256',(string)json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));return $row;
    }

    private static function personMatch(PDO $pdo,array $params,int $limit):array
    {
        $name=trim((string)($params['exact_name']??''));$email=strtolower(trim((string)($params['email']??'')));$instagram=strtolower(ltrim(trim((string)($params['instagram']??'')),'@'));
        if($name===''&&$email===''&&$instagram==='')throw new RuntimeException('person_match needs exact_name, email or instagram.');
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('person_match email is invalid.');
        $normal=self::normalise($name);$q=$pdo->prepare("SELECT c.id competitor_id,c.bdc_id,s.sdc_id,c.exact_name,c.photo_url IS NOT NULL AND TRIM(c.photo_url)<>'' photo_present,(c.normalised_name=:normal_match) name_match,(LOWER(TRIM(COALESCE(c.email,'')))=:email_match AND :email_enabled<>'') email_match,(LOWER(TRIM(LEADING '@' FROM COALESCE(c.instagram,'')))=:instagram_match AND :instagram_enabled<>'') instagram_match FROM bdc_competitors c LEFT JOIN bdc_sdc_competitors s ON s.competitor_id=c.id AND s.status='active' WHERE c.status<>'archived' AND ((:normal_enabled<>'' AND c.normalised_name=:normal_where) OR (:email_where_enabled<>'' AND LOWER(TRIM(COALESCE(c.email,'')))=:email_where) OR (:instagram_where_enabled<>'' AND LOWER(TRIM(LEADING '@' FROM COALESCE(c.instagram,'')))=:instagram_where)) ORDER BY email_match DESC,instagram_match DESC,name_match DESC,c.id LIMIT $limit");
        $q->execute(['normal_match'=>$normal?:'__none__','email_match'=>$email?:'__none__','email_enabled'=>$email,'instagram_match'=>$instagram?:'__none__','instagram_enabled'=>$instagram,'normal_enabled'=>$normal,'normal_where'=>$normal?:'__none__','email_where_enabled'=>$email,'email_where'=>$email?:'__none__','instagram_where_enabled'=>$instagram,'instagram_where'=>$instagram?:'__none__']);$rows=$q->fetchAll();return ['rows'=>$rows,'row_count'=>count($rows),'read_only'=>true];
    }

    private static function normalise(string $value):string{$value=function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);return trim((string)preg_replace('/[^\pL\pN]+/u',' ',$value));}

    private static function rows(PDO $pdo,string $sql,array $params):array{$q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll();return ['rows'=>$rows,'row_count'=>count($rows),'read_only'=>true];}
}
