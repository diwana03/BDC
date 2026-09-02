<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class CouncilResultIdentityService
{
    public static function councilForDance(string $danceStyle):string
    {
        return strtolower(trim($danceStyle))==='salsa'?'sdc':'bdc';
    }

    public static function identityForCompetitor(PDO $pdo,int $competitorId,string $danceStyle):array
    {
        if($competitorId<1)throw new RuntimeException('A valid competitor is required for result identity assignment.');
        $council=self::councilForDance($danceStyle);
        if($council==='sdc'){
            $profile=SdcCompetitorService::ensure($pdo,$competitorId);
            return ['id'=>(int)$profile['id'],'competitor_id'=>$competitorId,'council'=>'sdc','identity_code'=>(string)$profile['sdc_id']];
        }
        $query=$pdo->prepare("SELECT id,competitor_id,council,identity_code FROM bdc_result_identities WHERE competitor_id=:competitor AND council=:council LIMIT 1");
        $query->execute(['competitor'=>$competitorId,'council'=>$council]);
        if($row=$query->fetch())return $row;

        if($council==='bdc'){
            $source=$pdo->prepare("SELECT bdc_id FROM bdc_competitors WHERE id=:competitor AND status<>'archived' LIMIT 1");
            $source->execute(['competitor'=>$competitorId]);
            $code=(string)$source->fetchColumn();
            if($code==='')throw new RuntimeException('The Bachata competitor does not have a BDC ID.');
        }
        $insert=$pdo->prepare("INSERT INTO bdc_result_identities(competitor_id,council,identity_code) VALUES(:competitor,:council,:code)");
        $insert->execute(['competitor'=>$competitorId,'council'=>$council,'code'=>$code]);
        return ['id'=>(int)$pdo->lastInsertId(),'competitor_id'=>$competitorId,'council'=>$council,'identity_code'=>$code];
    }

    public static function wdcIdentityForEntry(PDO $pdo,string $entryType,string $displayName,?int $competitorId=null):array
    {
        $entryType=strtolower(trim($entryType));
        if(!in_array($entryType,['solo','couple','duo','pro_am','team'],true))throw new RuntimeException('Invalid WDC entry type.');
        $displayName=trim((string)preg_replace('/\s+/u',' ',$displayName));
        if($displayName==='')throw new RuntimeException('A WDC entry name is required.');
        $normal=self::normalise($displayName);
        $find=$entryType==='solo'&&$competitorId
            ?$pdo->prepare("SELECT * FROM bdc_wdc_identities WHERE entry_type='solo' AND solo_competitor_id=:competitor LIMIT 1")
            :$pdo->prepare("SELECT * FROM bdc_wdc_identities WHERE entry_type=:type AND normalised_name=:name LIMIT 1");
        $find->execute($entryType==='solo'&&$competitorId?['competitor'=>$competitorId]:['type'=>$entryType,'name'=>$normal]);
        if($row=$find->fetch())return $row;
        if((int)$pdo->query("SELECT GET_LOCK('bdc-wdc-identity-sequence',10)")->fetchColumn()!==1)throw new RuntimeException('Could not reserve a WDC ID. Please try again.');
        try{
            $find->execute($entryType==='solo'&&$competitorId?['competitor'=>$competitorId]:['type'=>$entryType,'name'=>$normal]);
            if($row=$find->fetch())return $row;
            $next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(identity_code,5) AS UNSIGNED)),0)+1 FROM bdc_wdc_identities WHERE identity_code LIKE 'WDC-%'")->fetchColumn();
            $code='WDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);
            $insert=$pdo->prepare("INSERT INTO bdc_wdc_identities(identity_code,entry_type,display_name,normalised_name,solo_competitor_id) VALUES(:code,:type,:display,:normal,:competitor)");
            $insert->execute(['code'=>$code,'type'=>$entryType,'display'=>$displayName,'normal'=>$normal,'competitor'=>$entryType==='solo'&&$competitorId?$competitorId:null]);
            return ['id'=>(int)$pdo->lastInsertId(),'identity_code'=>$code,'entry_type'=>$entryType,'display_name'=>$displayName,'normalised_name'=>$normal];
        }finally{$pdo->query("SELECT RELEASE_LOCK('bdc-wdc-identity-sequence')")->fetchColumn();}
    }

    public static function wdcOpenPoints(int $placement):float
    {
        return (float)([1=>10,2=>8,3=>6,4=>4,5=>2][$placement]??($placement>5?1:0));
    }

    private static function normalise(string $value):string
    {
        $value=function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
        return trim((string)preg_replace('/[^\pL\pN]+/u',' ',$value));
    }
}
