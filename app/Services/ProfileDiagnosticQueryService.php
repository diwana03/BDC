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
            'sdc_members'=>self::rows($pdo,"SELECT ri.competitor_id,c.bdc_id,ri.identity_code sdc_id,c.exact_name,c.photo_url IS NOT NULL AND TRIM(c.photo_url)<>'' photo_present,COALESCE(p.dance_role,'unknown') salsa_role,COALESCE(p.current_division,'unknown') salsa_division FROM bdc_result_identities ri JOIN bdc_competitors c ON c.id=ri.competitor_id LEFT JOIN bdc_competitor_discipline_profiles p ON p.competitor_id=c.id AND p.dance_style='salsa' WHERE ri.council='sdc' ORDER BY LOWER(c.exact_name),c.id LIMIT $limit",[]),
            'missing_photos'=>self::rows($pdo,"SELECT ri.competitor_id,c.bdc_id,ri.identity_code sdc_id,c.exact_name FROM bdc_result_identities ri JOIN bdc_competitors c ON c.id=ri.competitor_id WHERE ri.council='sdc' AND (c.photo_url IS NULL OR TRIM(c.photo_url)='') ORDER BY LOWER(c.exact_name),c.id LIMIT $limit",[]),
            'competitor_history'=>self::history($pdo,(int)($params['competitor_id']??0),$limit),
            'deletion_impact'=>self::deletionImpact($pdo,(int)($params['competitor_id']??0)),
            default=>throw new RuntimeException('Unknown diagnostic query. Allowed: sdc_members, missing_photos, competitor_history, deletion_impact.'),
        };
    }

    private static function history(PDO $pdo,int $id,int $limit):array
    {
        if($id<1)throw new RuntimeException('competitor_id is required.');
        return self::rows($pdo,"SELECT 'participant_result' record_type,id,event_id,dance_style,division,dance_role,placement,points_awarded points,created_at FROM bdc_participant_results WHERE competitor_id=:id UNION ALL SELECT 'point_transaction',id,event_id,dance_style,division,dance_role,placement,points,created_at FROM bdc_point_transactions WHERE competitor_id=:id2 ORDER BY created_at DESC LIMIT $limit",['id'=>$id,'id2'=>$id]);
    }

    private static function deletionImpact(PDO $pdo,int $id):array
    {
        if($id<1)throw new RuntimeException('competitor_id is required.');$q=$pdo->prepare("SELECT c.id competitor_id,c.bdc_id,c.exact_name,c.status,(SELECT COUNT(*) FROM bdc_result_identities WHERE competitor_id=c.id) identities,(SELECT COUNT(*) FROM bdc_competitor_discipline_profiles WHERE competitor_id=c.id) discipline_profiles,(SELECT COUNT(*) FROM bdc_competitor_special_categories WHERE competitor_id=c.id) special_categories,(SELECT COUNT(*) FROM bdc_participant_results WHERE competitor_id=c.id) participant_results,(SELECT COUNT(*) FROM bdc_point_transactions WHERE competitor_id=c.id) point_transactions FROM bdc_competitors c WHERE c.id=:id");$q->execute(['id'=>$id]);$row=$q->fetch();if(!$row)throw new RuntimeException('Competitor not found.');
        $row['simulation_only']=true;$row['deleted']=false;$row['requires_super_admin']=true;$row['approval_hash']=hash('sha256',(string)json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));return $row;
    }

    private static function rows(PDO $pdo,string $sql,array $params):array{$q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll();return ['rows'=>$rows,'row_count'=>count($rows),'read_only'=>true];}
}
