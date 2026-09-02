<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

/** Single council boundary for every Jack & Jill roster surface. */
final class JackJillCompetitorEligibilityService
{
    public static function dance(string $dance):string
    {
        $dance=strtolower(trim($dance));
        if(!in_array($dance,['bachata','salsa'],true))throw new RuntimeException('Jack & Jill dance must be Bachata or Salsa. Dance Cup competitors use the WDC workflow.');
        return $dance;
    }

    public static function council(string $dance):string
    {
        return self::dance($dance)==='salsa'?'SDC':'BDC';
    }

    /** @return array<int,array<string,mixed>> */
    public static function directory(PDO $pdo,string $dance,?string $role=null,int $limit=1500):array
    {
        $dance=self::dance($dance);
        if($role!==null&&!in_array($role,['leader','follower'],true))throw new RuntimeException('Invalid Jack & Jill role.');
        $limit=max(1,min(1500,$limit));
        if($dance==='salsa'){
            $sql="SELECT c.id,s.sdc_id identity_code,s.sdc_id bdc_id,c.exact_name,s.dance_role,s.current_division,c.country,c.status,
                  (SELECT COALESCE(SUM(p.points),0) FROM bdc_point_transactions p WHERE p.competitor_id=c.id AND p.dance_style='salsa' AND p.division='novice' AND p.dance_role IN(s.dance_role,'both')) novice_points,
                  (SELECT COALESCE(SUM(p.points),0) FROM bdc_point_transactions p WHERE p.competitor_id=c.id AND p.dance_style='salsa' AND p.division='intermediate' AND p.dance_role IN(s.dance_role,'both')) intermediate_points,
                  (SELECT COALESCE(SUM(p.points),0) FROM bdc_point_transactions p WHERE p.competitor_id=c.id AND p.dance_style='salsa' AND p.division='advanced' AND p.dance_role IN(s.dance_role,'both')) advanced_points,
                  EXISTS(SELECT 1 FROM bdc_participant_results pr WHERE pr.competitor_id=c.id AND pr.dance_style='salsa' AND pr.division='intermediate' AND pr.dance_role IN(s.dance_role,'both')) competed_intermediate,
                  EXISTS(SELECT 1 FROM bdc_participant_results pr WHERE pr.competitor_id=c.id AND pr.dance_style='salsa' AND pr.division='advanced' AND pr.dance_role IN(s.dance_role,'both')) competed_advanced,
                  EXISTS(SELECT 1 FROM bdc_participant_results pr WHERE pr.competitor_id=c.id AND pr.dance_style='salsa' AND pr.division='all_star' AND pr.dance_role IN(s.dance_role,'both')) competed_all_star
                  FROM bdc_sdc_competitors s
                  JOIN bdc_competitors c ON c.id=s.competitor_id
                  WHERE s.status='active' AND c.status='active'".($role!==null?" AND s.dance_role IN(:role,'both')":'')."
                  ORDER BY LOWER(c.exact_name),c.id LIMIT {$limit}";
        }else{
            $sql="SELECT c.id,c.bdc_id identity_code,c.bdc_id,c.exact_name,c.dance_role,c.current_division,c.country,c.status,
                  (SELECT COALESCE(SUM(p.points),0) FROM bdc_point_transactions p WHERE p.competitor_id=c.id AND p.dance_style='bachata' AND p.division='novice' AND p.dance_role IN(c.dance_role,'both')) novice_points,
                  (SELECT COALESCE(SUM(p.points),0) FROM bdc_point_transactions p WHERE p.competitor_id=c.id AND p.dance_style='bachata' AND p.division='intermediate' AND p.dance_role IN(c.dance_role,'both')) intermediate_points,
                  (SELECT COALESCE(SUM(p.points),0) FROM bdc_point_transactions p WHERE p.competitor_id=c.id AND p.dance_style='bachata' AND p.division='advanced' AND p.dance_role IN(c.dance_role,'both')) advanced_points,
                  EXISTS(SELECT 1 FROM bdc_participant_results pr WHERE pr.competitor_id=c.id AND pr.dance_style='bachata' AND pr.division='intermediate' AND pr.dance_role IN(c.dance_role,'both')) competed_intermediate,
                  EXISTS(SELECT 1 FROM bdc_participant_results pr WHERE pr.competitor_id=c.id AND pr.dance_style='bachata' AND pr.division='advanced' AND pr.dance_role IN(c.dance_role,'both')) competed_advanced,
                  EXISTS(SELECT 1 FROM bdc_participant_results pr WHERE pr.competitor_id=c.id AND pr.dance_style='bachata' AND pr.division='all_star' AND pr.dance_role IN(c.dance_role,'both')) competed_all_star
                  FROM bdc_competitors c
                  WHERE c.status='active' AND c.bdc_id LIKE 'BDC-%'".($role!==null?" AND c.dance_role IN(:role,'both')":'')."
                  ORDER BY LOWER(c.exact_name),c.id LIMIT {$limit}";
        }
        $q=$pdo->prepare($sql);$q->execute($role!==null?['role'=>$role]:[]);return $q->fetchAll();
    }

    public static function find(PDO $pdo,string $dance,string $term,string $role):?array
    {
        $dance=self::dance($dance);$term=trim($term);
        if($term===''||!in_array($role,['leader','follower'],true))return null;
        if($dance==='salsa'){
            $sql="SELECT c.id,s.sdc_id identity_code,s.sdc_id bdc_id,c.exact_name,s.dance_role,s.current_division,c.country,c.status
                  FROM bdc_sdc_competitors s JOIN bdc_competitors c ON c.id=s.competitor_id
                  WHERE s.status='active' AND c.status='active' AND s.dance_role IN(:role,'both')
                    AND (UPPER(s.sdc_id)=UPPER(:code) OR LOWER(c.exact_name)=LOWER(:name))
                  ORDER BY CASE WHEN UPPER(s.sdc_id)=UPPER(:preferred) THEN 0 ELSE 1 END,c.id LIMIT 1";
        }else{
            $sql="SELECT c.id,c.bdc_id identity_code,c.bdc_id,c.exact_name,c.dance_role,c.current_division,c.country,c.status
                  FROM bdc_competitors c
                  WHERE c.status='active' AND c.bdc_id LIKE 'BDC-%' AND c.dance_role IN(:role,'both')
                    AND (UPPER(c.bdc_id)=UPPER(:code) OR LOWER(c.exact_name)=LOWER(:name))
                  ORDER BY CASE WHEN UPPER(c.bdc_id)=UPPER(:preferred) THEN 0 ELSE 1 END,c.id LIMIT 1";
        }
        $q=$pdo->prepare($sql);$q->execute(['role'=>$role,'code'=>$term,'name'=>$term,'preferred'=>$term]);return $q->fetch()?:null;
    }

    public static function requireEligible(PDO $pdo,string $dance,string $term,string $role):array
    {
        $row=self::find($pdo,$dance,$term,$role);
        if(!$row)throw new RuntimeException(self::council($dance).' competitor not found or not active for '.ucfirst(self::dance($dance)).' '.ucfirst($role).'. Add or correct the council profile before opening scoring.');
        return $row;
    }
}
