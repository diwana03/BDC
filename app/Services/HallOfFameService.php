<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class HallOfFameService
{
    public static function latest(PDO $pdo,int $limit=6,?array $divisions=null):array
    {
        $limit=max(1,min(24,$limit));
        $allowed=['novice','intermediate','advanced','all_star','bachata_rising','bachata_open','bachata_invitational','unknown'];
        $filters=[];
        foreach((array)$divisions as $division){
            $division=strtolower(trim((string)$division));
            if(in_array($division,$allowed,true))$filters[]=$division;
        }
        $filters=array_values(array_unique($filters));

        $where="e.status IN ('published','completed') AND e.event_date IS NOT NULL AND CAST(pr.placement AS UNSIGNED) BETWEEN 1 AND 3";
        $params=[];
        if($filters){
            $ph=[];
            foreach($filters as $i=>$division){$key='division_'.$i;$ph[]=':'.$key;$params[$key]=$division;}
            $where.=' AND pr.division IN ('.implode(',',$ph).')';
        }

        $groupStmt=$pdo->prepare("SELECT pr.event_id,pr.division,e.name,e.event_date,e.location,e.venue
            FROM bdc_participant_results pr
            JOIN bdc_events e ON e.id=pr.event_id
            WHERE $where
            GROUP BY pr.event_id,pr.division,e.name,e.event_date,e.location,e.venue
            ORDER BY e.event_date DESC,pr.event_id DESC
            LIMIT $limit");
        $groupStmt->execute($params);
        $groups=$groupStmt->fetchAll();

        $resultStmt=$pdo->prepare("SELECT pr.placement,pr.dance_role,pr.partner_name,c.id competitor_id,c.bdc_id,c.exact_name,c.country,c.photo_url
            FROM bdc_participant_results pr
            JOIN bdc_competitors c ON c.id=pr.competitor_id
            WHERE pr.event_id=:event_id AND pr.division=:division AND CAST(pr.placement AS UNSIGNED) BETWEEN 1 AND 3
            ORDER BY CAST(pr.placement AS UNSIGNED),FIELD(pr.dance_role,'leader','follower'),pr.id");

        $items=[];
        foreach($groups as $group){
            $resultStmt->execute(['event_id'=>$group['event_id'],'division'=>$group['division']]);
            $placements=[];
            foreach($resultStmt->fetchAll() as $row){
                $place=(int)$row['placement'];
                if(!isset($placements[$place]))$placements[$place]=['leader'=>null,'follower'=>null];
                $role=(string)$row['dance_role'];
                if(in_array($role,['leader','follower'],true) && $placements[$place][$role]===null){
                    $placements[$place][$role]=$row;
                }
            }
            $group['category_label']=self::label((string)$group['division']);
            $group['placements']=$placements;
            $items[]=$group;
        }
        return $items;
    }

    public static function special(PDO $pdo,int $limit=6):array
    {
        return self::latest($pdo,$limit,array_keys(SpecialCategoryService::categories()));
    }

    public static function label(string $division):string
    {
        if(SpecialCategoryService::isSpecial($division))return SpecialCategoryService::label($division);
        return match($division){
            'novice'=>'Novice',
            'intermediate'=>'Intermediate',
            'advanced'=>'Advanced',
            'all_star'=>'All Star',
            default=>ucwords(str_replace('_',' ',$division)),
        };
    }
}
