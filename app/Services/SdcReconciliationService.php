<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;

final class SdcReconciliationService
{
    private const CATEGORIES=['salsa_rising','salsa_open','salsa_invitational'];
    private const ROLES=['leader','follower','both','unknown'];

    public static function directory(PDO $pdo,string $search,int $page,int $perPage):array
    {
        $page=max(1,$page);$perPage=max(1,min(200,$perPage));$offset=($page-1)*$perPage;
        $search=trim($search);$where="c.status<>'archived'";$params=[];
        if($search!==''){$where.=" AND (LOWER(c.exact_name) LIKE LOWER(:search_name) OR LOWER(c.normalised_name) LIKE LOWER(:search_normal) OR LOWER(COALESCE(c.bdc_id,'')) LIKE LOWER(:search_bdc) OR LOWER(COALESCE(sdc.sdc_id,'')) LIKE LOWER(:search_sdc))";$value='%'.$search.'%';$params=['search_name'=>$value,'search_normal'=>$value,'search_bdc'=>$value,'search_sdc'=>$value];}
        $count=$pdo->prepare("SELECT COUNT(*) FROM bdc_competitors c LEFT JOIN bdc_sdc_competitors sdc ON sdc.competitor_id=c.id AND sdc.status='active' WHERE $where");$count->execute($params);
        $sql="SELECT c.id,c.bdc_id,sdc.sdc_id,c.exact_name,c.normalised_name,c.status,COALESCE(sdc.dance_role,'unknown') salsa_role,COALESCE(sdc.current_division,'unknown') salsa_division,c.photo_url,GROUP_CONCAT(DISTINCT sc.category ORDER BY sc.category SEPARATOR ',') salsa_categories FROM bdc_competitors c LEFT JOIN bdc_sdc_competitors sdc ON sdc.competitor_id=c.id AND sdc.status='active' LEFT JOIN bdc_sdc_competitor_categories sc ON sc.sdc_competitor_id=sdc.id WHERE $where GROUP BY c.id,c.bdc_id,sdc.sdc_id,c.exact_name,c.normalised_name,c.status,sdc.dance_role,sdc.current_division,c.photo_url ORDER BY LOWER(c.exact_name),c.id LIMIT $perPage OFFSET $offset";
        $query=$pdo->prepare($sql);$query->execute($params);$items=[];
        foreach($query->fetchAll() as $row){$items[]=['competitor_id'=>(int)$row['id'],'bdc_id'=>(string)$row['bdc_id'],'sdc_id'=>$row['sdc_id']?:null,'exact_name'=>(string)$row['exact_name'],'normalised_name'=>(string)$row['normalised_name'],'role'=>(string)$row['salsa_role'],'status'=>(string)$row['status'],'salsa_division'=>(string)$row['salsa_division'],'salsa_categories'=>$row['salsa_categories']!==null?explode(',',(string)$row['salsa_categories']):[],'photo_present'=>trim((string)($row['photo_url']??''))!==''];}
        return ['page'=>$page,'per_page'=>$perPage,'total'=>(int)$count->fetchColumn(),'items'=>$items];
    }

    public static function reconcile(PDO $pdo,array $input):array
    {
        if(($input['dry_run']??true)===false)throw new RuntimeException('This API is test-only. Database changes require Super Admin approval.');
        $members=self::validateMembers($input['members']??null);$plan=self::plan($pdo,$members);
        return ['dry_run'=>true,'applied'=>false,'requires_super_admin'=>true,'plan_hash'=>$plan['confirm_hash'],'plan'=>$plan];
    }

    private static function validateMembers(mixed $raw):array
    {
        if(!is_array($raw)||count($raw)>200)throw new RuntimeException('members must be an array of no more than 200 exact directory targets.');$seen=[];$members=[];
        foreach($raw as $item){if(!is_array($item))throw new RuntimeException('Every member must be an object.');$id=(int)($item['target_id']??0);if($id<1||isset($seen[$id]))throw new RuntimeException('Every member needs one unique positive target_id.');$seen[$id]=true;$role=strtolower(trim((string)($item['role']??'unknown')));if(!in_array($role,self::ROLES,true))throw new RuntimeException('Invalid Salsa role for target '.$id.'.');$categories=array_values(array_unique(array_map(static fn($v):string=>strtolower(trim((string)$v)),is_array($item['categories']??null)?$item['categories']:[])));foreach($categories as $category)if(!in_array($category,self::CATEGORIES,true))throw new RuntimeException('Invalid Salsa category for target '.$id.'.');sort($categories);$members[]=['target_id'=>$id,'role'=>$role,'categories'=>$categories];}
        usort($members,static fn(array $a,array $b):int=>$a['target_id']<=>$b['target_id']);return $members;
    }

    private static function plan(PDO $pdo,array $members):array
    {
        $ids=array_column($members,'target_id');$found=[];$missingPhotos=[];$novice=0;
        if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT c.id,c.bdc_id,c.exact_name,c.photo_url,s.current_division FROM bdc_competitors c LEFT JOIN bdc_sdc_competitors s ON s.competitor_id=c.id AND s.status='active' WHERE c.status<>'archived' AND c.id IN ($marks) ORDER BY c.id");$q->execute($ids);foreach($q->fetchAll() as $row){$id=(int)$row['id'];$found[$id]=true;if(trim((string)($row['photo_url']??''))==='')$missingPhotos[]=['competitor_id'=>$id,'bdc_id'=>$row['bdc_id'],'exact_name'=>$row['exact_name']];if((string)$row['current_division']==='novice')$novice++;}}
        $unmatched=array_values(array_filter($ids,static fn(int $id):bool=>!isset($found[$id])));
        $existing=$pdo->query("SELECT s.competitor_id,c.bdc_id,c.exact_name FROM bdc_sdc_competitors s JOIN bdc_competitors c ON c.id=s.competitor_id WHERE s.status='active' ORDER BY s.competitor_id")->fetchAll();$wanted=array_fill_keys($ids,true);$remove=[];$removeRows=[];foreach($existing as $row)if(!isset($wanted[(int)$row['competitor_id']])){$remove[]=(int)$row['competitor_id'];$removeRows[]=['competitor_id'=>(int)$row['competitor_id'],'bdc_id'=>$row['bdc_id'],'exact_name'=>$row['exact_name']];}
        $protected=[];if($remove){$marks=implode(',',array_fill(0,count($remove),'?'));$q=$pdo->prepare("SELECT c.id competitor_id,c.bdc_id,c.exact_name,(SELECT COUNT(*) FROM bdc_participant_results pr WHERE pr.competitor_id=c.id AND pr.dance_style='salsa') participant_results,(SELECT COUNT(*) FROM bdc_point_transactions pt WHERE pt.competitor_id=c.id AND pt.dance_style='salsa') point_transactions FROM bdc_competitors c WHERE c.id IN ($marks) HAVING participant_results>0 OR point_transactions>0 ORDER BY c.id");$q->execute($remove);foreach($q->fetchAll() as $row)$protected[]=['competitor_id'=>(int)$row['competitor_id'],'bdc_id'=>$row['bdc_id'],'exact_name'=>$row['exact_name'],'participant_results'=>(int)$row['participant_results'],'point_transactions'=>(int)$row['point_transactions']];}
        $state=['members'=>$members,'existing_sdc'=>$existing,'unmatched'=>$unmatched,'protected'=>$protected];$hash=hash('sha256',(string)json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        return ['keep_or_add_count'=>count($members),'remove_count'=>count($remove),'remove_ids'=>$remove,'removals'=>$removeRows,'novice_tags_removed'=>$novice,'missing_photos'=>$missingPhotos,'unmatched_target_ids'=>$unmatched,'protected_removals'=>$protected,'confirm_hash'=>$hash];
    }
}
