<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;

/** Canonical persistence boundary for SDC membership and Salsa-only fields. */
final class SdcCompetitorService
{
    public const CATEGORIES=['salsa_rising','salsa_open','salsa_invitational'];
    public const ROLES=['leader','follower','both','unknown'];

    public static function profile(PDO $pdo,int $competitorId):?array
    {
        $q=$pdo->prepare("SELECT s.*,GROUP_CONCAT(c.category ORDER BY c.category SEPARATOR ',') categories FROM bdc_sdc_competitors s LEFT JOIN bdc_sdc_competitor_categories c ON c.sdc_competitor_id=s.id WHERE s.competitor_id=:id GROUP BY s.id LIMIT 1");
        $q->execute(['id'=>$competitorId]);$row=$q->fetch();
        if(!$row)return null;$row['special_categories']=$row['categories']!==null?explode(',',(string)$row['categories']):[];return $row;
    }

    public static function bySdcId(PDO $pdo,string $sdcId):?array
    {
        $q=$pdo->prepare("SELECT s.*,c.exact_name,c.status person_status FROM bdc_sdc_competitors s JOIN bdc_competitors c ON c.id=s.competitor_id WHERE s.sdc_id=:id AND s.status='active' AND c.status<>'archived' LIMIT 1");
        $q->execute(['id'=>strtoupper(trim($sdcId))]);return $q->fetch()?:null;
    }

    public static function ensure(PDO $pdo,int $competitorId,string $role='unknown',string $division='unknown'):array
    {
        if($competitorId<1)throw new RuntimeException('A valid shared person is required for an SDC profile.');
        if(!in_array($role,self::ROLES,true))throw new RuntimeException('Invalid Salsa role.');
        if(!in_array($division,['unknown','novice','intermediate','advanced','all_star','professional'],true))throw new RuntimeException('Invalid Salsa division.');
        if($current=self::profile($pdo,$competitorId))return $current;
        if((int)$pdo->query("SELECT GET_LOCK('bdc-sdc-identity-sequence',10)")->fetchColumn()!==1)throw new RuntimeException('Could not reserve an SDC ID. Please try again.');
        try{
            if($current=self::profile($pdo,$competitorId))return $current;
            $next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(sdc_id,5) AS UNSIGNED)),0)+1 FROM bdc_sdc_competitors WHERE sdc_id LIKE 'SDC-%'")->fetchColumn();
            $code='SDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO bdc_sdc_competitors(competitor_id,sdc_id,dance_role,current_division) VALUES(:id,:code,:role,:division)")->execute(['id'=>$competitorId,'code'=>$code,'role'=>$role,'division'=>$division]);
            self::syncLegacy($pdo,$competitorId,$code,$role,$division,[]);
            return self::profile($pdo,$competitorId)??throw new RuntimeException('SDC profile creation failed.');
        }finally{$pdo->query("SELECT RELEASE_LOCK('bdc-sdc-identity-sequence')")->fetchColumn();}
    }

    public static function save(PDO $pdo,int $competitorId,string $role,string $division,array $categories,string $sourceKind='manual',string $sourceName='SDC profile service'):array
    {
        if(!in_array($role,self::ROLES,true))throw new RuntimeException('Invalid Salsa role.');
        $categories=array_values(array_unique(array_map('strtolower',$categories)));
        if(array_diff($categories,self::CATEGORIES))throw new RuntimeException('Invalid SDC category.');
        $profile=self::ensure($pdo,$competitorId,$role,$division);
        $pdo->prepare("UPDATE bdc_sdc_competitors SET dance_role=:role,current_division=:division,status='active' WHERE competitor_id=:id")->execute(['role'=>$role,'division'=>$division,'id'=>$competitorId]);
        $pdo->prepare('DELETE FROM bdc_sdc_competitor_categories WHERE sdc_competitor_id=:id')->execute(['id'=>$profile['id']]);
        $add=$pdo->prepare('INSERT INTO bdc_sdc_competitor_categories(sdc_competitor_id,category,source_kind,source_name) VALUES(:id,:category,:kind,:source)');
        foreach($categories as $category)$add->execute(['id'=>$profile['id'],'category'=>$category,'kind'=>$sourceKind,'source'=>$sourceName]);
        self::syncLegacy($pdo,$competitorId,(string)$profile['sdc_id'],$role,$division,$categories,$sourceKind,$sourceName);
        return self::profile($pdo,$competitorId)??throw new RuntimeException('SDC profile update failed.');
    }

    public static function archive(PDO $pdo,int $competitorId):void
    {
        $pdo->prepare("UPDATE bdc_sdc_competitors SET status='archived' WHERE competitor_id=:id")->execute(['id'=>$competitorId]);
        // Compatibility cleanup is Salsa-scoped and never archives the shared person.
        $pdo->prepare("DELETE FROM bdc_result_identities WHERE competitor_id=:id AND council='sdc'")->execute(['id'=>$competitorId]);
        $pdo->prepare("DELETE FROM bdc_competitor_discipline_profiles WHERE competitor_id=:id AND dance_style='salsa'")->execute(['id'=>$competitorId]);
        $pdo->prepare("DELETE FROM bdc_competitor_special_categories WHERE competitor_id=:id AND dance_style='salsa'")->execute(['id'=>$competitorId]);
    }

    /** Publication is the only scoring operation allowed to commit Salsa progression. */
    public static function syncDivisionAfterApproval(PDO $pdo,int $competitorId,string $role,string $division):void
    {
        if(!in_array($role,self::ROLES,true)||$role==='unknown')throw new RuntimeException('Invalid published Salsa role.');
        if(!in_array($division,['novice','intermediate','advanced','all_star','professional'],true))throw new RuntimeException('Invalid published Salsa division.');
        $profile=self::profile($pdo,$competitorId);
        if(!$profile||(string)$profile['status']!=='active')throw new RuntimeException('Published Salsa competitor does not have an active SDC profile.');
        $pdo->prepare("UPDATE bdc_sdc_competitors SET dance_role=:role,current_division=:division WHERE competitor_id=:id AND status='active'")->execute(['role'=>$role,'division'=>$division,'id'=>$competitorId]);
        $pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) VALUES(:id,'salsa',:role,:division) ON DUPLICATE KEY UPDATE dance_role=VALUES(dance_role),current_division=VALUES(current_division),updated_at=NOW()")->execute(['id'=>$competitorId,'role'=>$role,'division'=>$division]);
    }

    private static function syncLegacy(PDO $pdo,int $competitorId,string $code,string $role,string $division,array $categories,string $sourceKind='migration',string $sourceName='SDC canonical compatibility'):void
    {
        $pdo->prepare("INSERT INTO bdc_result_identities(competitor_id,council,identity_code) VALUES(:id,'sdc',:code) ON DUPLICATE KEY UPDATE identity_code=VALUES(identity_code)")->execute(['id'=>$competitorId,'code'=>$code]);
        $pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) VALUES(:id,'salsa',:role,:division) ON DUPLICATE KEY UPDATE dance_role=VALUES(dance_role),current_division=VALUES(current_division),updated_at=NOW()")->execute(['id'=>$competitorId,'role'=>$role,'division'=>$division]);
        $pdo->prepare("DELETE FROM bdc_competitor_special_categories WHERE competitor_id=:id AND dance_style='salsa'")->execute(['id'=>$competitorId]);
        $add=$pdo->prepare("INSERT INTO bdc_competitor_special_categories(competitor_id,dance_style,category,source_kind,source_name) VALUES(:id,'salsa',:category,:kind,:source)");
        foreach($categories as $category)$add->execute(['id'=>$competitorId,'category'=>$category,'kind'=>$sourceKind,'source'=>$sourceName]);
    }
}
