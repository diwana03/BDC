<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class DivisionProgressionService
{
    public const ORDER=[
        'unknown'=>0,
        'novice'=>1,
        'intermediate'=>2,
        'advanced'=>3,
        'all_star'=>4,
    ];

    public static function normaliseDivision(?string $division):string
    {
        $division=strtolower(trim((string)$division));
        return array_key_exists($division,self::ORDER)?$division:'unknown';
    }

    public static function effectiveDivision(
        float $novicePoints,
        float $intermediatePoints,
        float $advancedPoints,
        ?string $committedDivision,
        bool $noviceManualOut=false,
        bool $intermediateManualOut=false
    ):string{
        $committed=self::normaliseDivision($committedDivision);
        if($advancedPoints>40.0){$mandatory='all_star';}
        elseif($intermediatePoints>30.0){$mandatory='advanced';}
        elseif($novicePoints>25.0){$mandatory='intermediate';}
        else{$mandatory='novice';}

        if($intermediateManualOut && $intermediatePoints>=25.0){$committed=self::higher($committed,'advanced');}
        if($noviceManualOut && $novicePoints>=20.0){$committed=self::higher($committed,'intermediate');}

        if($committed==='intermediate' && $novicePoints<20.0){$committed='novice';}
        elseif($committed==='advanced' && $intermediatePoints<25.0){$committed=$novicePoints>=20.0?'intermediate':'novice';}
        elseif($committed==='all_star' && $advancedPoints<40.0){$committed=$intermediatePoints>=25.0?'advanced':($novicePoints>=20.0?'intermediate':'novice');}
        elseif($committed==='unknown'){$committed='novice';}
        return self::higher($mandatory,$committed);
    }

    public static function isEligibleFor(
        string $division,
        float $novicePoints,
        float $intermediatePoints,
        float $advancedPoints,
        ?string $committedDivision,
        bool $noviceManualOut=false,
        bool $intermediateManualOut=false
    ):bool{
        if(SpecialCategoryService::isSpecial($division))return true;
        return self::effectiveDivision($novicePoints,$intermediatePoints,$advancedPoints,$committedDivision,$noviceManualOut,$intermediateManualOut)===self::normaliseDivision($division);
    }

    public static function eligibilityFor(
        string $division,
        float $novicePoints,
        float $intermediatePoints,
        float $advancedPoints,
        ?string $committedDivision,
        bool $competedIntermediate=false,
        bool $competedAdvanced=false,
        bool $competedAllStar=false
    ):array{
        if(SpecialCategoryService::isSpecial($division)){
            $special=SpecialCategoryService::entryEligibility($division);
            return ['eligible'=>(bool)$special['eligible'],'reason'=>(string)$special['reason'],'promoted_to'=>null];
        }

        $division=self::normaliseDivision($division);
        $hasIntermediateHistory=$competedIntermediate||$competedAdvanced||$competedAllStar;
        $hasAdvancedHistory=$competedAdvanced||$competedAllStar;
        $hasAllStarHistory=$competedAllStar;

        if($division==='novice'){
            if($novicePoints>25.0)return ['eligible'=>false,'reason'=>'this dancer has more than 25 Novice points.','promoted_to'=>'intermediate'];
            if($hasIntermediateHistory)return ['eligible'=>false,'reason'=>'this dancer has already competed in Intermediate or above and cannot return to Novice.','promoted_to'=>'intermediate'];
            return ['eligible'=>true,'reason'=>'eligible for Novice.','promoted_to'=>null];
        }
        if($division==='intermediate'){
            if($intermediatePoints>30.0)return ['eligible'=>false,'reason'=>'this dancer has more than 30 Intermediate points.','promoted_to'=>'advanced'];
            if($hasAdvancedHistory)return ['eligible'=>false,'reason'=>'this dancer has already competed in Advanced or above and cannot return to Intermediate.','promoted_to'=>'advanced'];
            if($novicePoints>=20.0||$hasIntermediateHistory)return ['eligible'=>true,'reason'=>'eligible for Intermediate.','promoted_to'=>null];
            return ['eligible'=>false,'reason'=>'this dancer has fewer than 20 Novice points and no recorded Intermediate competition history.','promoted_to'=>null];
        }
        if($division==='advanced'){
            if($advancedPoints>40.0)return ['eligible'=>false,'reason'=>'this dancer has more than 40 Advanced points.','promoted_to'=>'all_star'];
            if($hasAllStarHistory)return ['eligible'=>false,'reason'=>'this dancer has already competed in All Star and cannot return to Advanced.','promoted_to'=>'all_star'];
            if($intermediatePoints>=25.0||$hasAdvancedHistory)return ['eligible'=>true,'reason'=>'eligible for Advanced.','promoted_to'=>null];
            return ['eligible'=>false,'reason'=>'this dancer has fewer than 25 Intermediate points and no recorded Advanced competition history.','promoted_to'=>null];
        }
        if($division==='all_star'){
            if($advancedPoints>=40.0||$hasAllStarHistory)return ['eligible'=>true,'reason'=>'eligible for All Star.','promoted_to'=>null];
            return ['eligible'=>false,'reason'=>'this dancer has fewer than 40 Advanced points and no recorded All Star competition history.','promoted_to'=>null];
        }
        return ['eligible'=>false,'reason'=>'the selected division is not valid for BDC eligibility.','promoted_to'=>null];
    }

    /**
     * Career state is derived only from approved result and point ledgers.
     * Event entries, draft rounds, Test rounds and website category requests
     * never appear in these ledgers and therefore cannot promote a dancer.
     *
     * @return array<string,mixed>
     */
    public static function approvedCareerState(PDO $pdo,int $competitorId,string $danceRole,string $danceStyle='bachata'):array
    {
        if(!in_array($danceRole,['leader','follower','both'],true))$danceRole='unknown';
        if(!in_array($danceStyle,['bachata','salsa'],true))$danceStyle='bachata';

        $competitorStmt=$pdo->prepare("SELECT current_division,novice_manual_out,intermediate_manual_out FROM bdc_competitors WHERE id=:id LIMIT 1");
        $competitorStmt->execute(['id'=>$competitorId]);
        $competitor=$competitorStmt->fetch()?:[];

        $profileStmt=$pdo->prepare("SELECT current_division FROM bdc_competitor_discipline_profiles WHERE competitor_id=:id AND dance_style=:dance LIMIT 1");
        $profileStmt->execute(['id'=>$competitorId,'dance'=>$danceStyle]);
        $profileDivision=(string)($profileStmt->fetchColumn()?:'');
        $committed=$danceStyle==='bachata'
            ?(string)($competitor['current_division']??'unknown')
            :($profileDivision?:'unknown');

        $pointsStmt=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN division='novice' THEN points ELSE 0 END),0) novice_points,COALESCE(SUM(CASE WHEN division='intermediate' THEN points ELSE 0 END),0) intermediate_points,COALESCE(SUM(CASE WHEN division='advanced' THEN points ELSE 0 END),0) advanced_points FROM bdc_point_transactions WHERE competitor_id=:competitor AND dance_style=:dance AND dance_role IN(:role,'both')");
        $pointsStmt->execute(['competitor'=>$competitorId,'dance'=>$danceStyle,'role'=>$danceRole]);
        $points=$pointsStmt->fetch()?:[];

        $historyStmt=$pdo->prepare("SELECT MAX(CASE WHEN division='intermediate' THEN 1 ELSE 0 END) competed_intermediate,MAX(CASE WHEN division='advanced' THEN 1 ELSE 0 END) competed_advanced,MAX(CASE WHEN division='all_star' THEN 1 ELSE 0 END) competed_all_star FROM bdc_participant_results WHERE competitor_id=:competitor AND dance_style=:dance AND dance_role IN(:role,'both')");
        $historyStmt->execute(['competitor'=>$competitorId,'dance'=>$danceStyle,'role'=>$danceRole]);
        $history=$historyStmt->fetch()?:[];

        return [
            'novice_points'=>(float)($points['novice_points']??0),
            'intermediate_points'=>(float)($points['intermediate_points']??0),
            'advanced_points'=>(float)($points['advanced_points']??0),
            'committed_division'=>$committed,
            'competed_intermediate'=>!empty($history['competed_intermediate']),
            'competed_advanced'=>!empty($history['competed_advanced']),
            'competed_all_star'=>!empty($history['competed_all_star']),
            'novice_manual_out'=>$danceStyle==='bachata'&&!empty($competitor['novice_manual_out']),
            'intermediate_manual_out'=>$danceStyle==='bachata'&&!empty($competitor['intermediate_manual_out']),
        ];
    }

    public static function eligibilityFromApprovedHistory(PDO $pdo,int $competitorId,string $danceRole,string $danceStyle,string $enteredDivision):array
    {
        if(SpecialCategoryService::isSpecial($enteredDivision)){
            $special=SpecialCategoryService::entryEligibility($enteredDivision);
            return ['eligible'=>(bool)$special['eligible'],'reason'=>(string)$special['reason'],'promoted_to'=>null];
        }
        $state=self::approvedCareerState($pdo,$competitorId,$danceRole,$danceStyle);
        return self::eligibilityFor(
            $enteredDivision,
            $state['novice_points'],
            $state['intermediate_points'],
            $state['advanced_points'],
            $state['committed_division'],
            $state['competed_intermediate'],
            $state['competed_advanced'],
            $state['competed_all_star']
        );
    }

    /**
     * Resolve a permanent career division from approved official history.
     * Special event categories are deliberately ignored as committed state.
     */
    public static function approvedPermanentDivision(PDO $pdo,int $competitorId,string $danceRole,string $danceStyle='bachata'):string
    {
        if(in_array($danceRole,['both','unknown'],true)){
            $leader=self::approvedPermanentDivision($pdo,$competitorId,'leader',$danceStyle);
            $follower=self::approvedPermanentDivision($pdo,$competitorId,'follower',$danceStyle);
            if($leader===$follower)return $leader;
            foreach(['professional','pro','semi_pro'] as $legacyDivision){
                if($leader===$legacyDivision||$follower===$legacyDivision)return $legacyDivision;
            }
            return self::higher($leader,$follower);
        }
        $state=self::approvedCareerState($pdo,$competitorId,$danceRole,$danceStyle);
        $committed=SpecialCategoryService::isSpecial((string)$state['committed_division'])
            ?'unknown'
            :(string)$state['committed_division'];
        if(in_array($committed,['semi_pro','pro','professional'],true))return $committed;
        $division=self::effectiveDivision(
            $state['novice_points'],
            $state['intermediate_points'],
            $state['advanced_points'],
            $committed,
            $state['novice_manual_out'],
            $state['intermediate_manual_out']
        );
        if($state['competed_all_star'])return self::higher($division,'all_star');
        if($state['competed_advanced'])return self::higher($division,'advanced');
        if($state['competed_intermediate'])return self::higher($division,'intermediate');
        return $division;
    }

    /** Persist visible career state only after Super Admin publication. */
    public static function syncProfileAfterApproval(PDO $pdo,int $competitorId,string $danceRole,string $danceStyle):string
    {
        if(!in_array($danceStyle,['bachata','salsa'],true))throw new \RuntimeException('Invalid approved dance style.');
        $division=self::approvedPermanentDivision($pdo,$competitorId,$danceRole,$danceStyle);
        $stmt=$pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) VALUES(:competitor,:dance,:role,:division) ON DUPLICATE KEY UPDATE dance_role=VALUES(dance_role),current_division=VALUES(current_division),updated_at=NOW()");
        $stmt->execute(['competitor'=>$competitorId,'dance'=>$danceStyle,'role'=>$danceRole,'division'=>$division]);
        if($danceStyle==='bachata')$pdo->prepare('UPDATE bdc_competitors SET current_division=:division WHERE id=:competitor')->execute(['division'=>$division,'competitor'=>$competitorId]);
        return $division;
    }

    /**
     * Repair only invalid legacy special-category assignments. Normal career
     * divisions and event/result category history are never rewritten.
     *
     * @return array{profiles:int,competitors:int,test_competitors:int}
     */
    public static function repairLegacySpecialCategoryAssignments(PDO $pdo):array
    {
        $counts=['profiles'=>0,'competitors'=>0,'test_competitors'=>0];
        $special=array_keys(SpecialCategoryService::categories());
        $placeholders=implode(',',array_fill(0,count($special),'?'));

        if(self::tableExists($pdo,'bdc_competitor_discipline_profiles')){
            $stmt=$pdo->prepare("SELECT competitor_id,dance_style,dance_role FROM bdc_competitor_discipline_profiles WHERE current_division IN ($placeholders) ORDER BY competitor_id,dance_style");
            $stmt->execute($special);
            $update=$pdo->prepare('UPDATE bdc_competitor_discipline_profiles SET current_division=:division WHERE competitor_id=:competitor AND dance_style=:dance');
            foreach($stmt->fetchAll() as $row){
                $division=self::approvedPermanentDivision($pdo,(int)$row['competitor_id'],(string)$row['dance_role'],(string)$row['dance_style']);
                $update->execute(['division'=>$division,'competitor'=>$row['competitor_id'],'dance'=>$row['dance_style']]);
                $counts['profiles']+=$update->rowCount();
            }
        }

        if(self::tableExists($pdo,'bdc_competitors')){
            $stmt=$pdo->prepare("SELECT id,dance_role FROM bdc_competitors WHERE current_division IN ($placeholders) ORDER BY id");
            $stmt->execute($special);
            $update=$pdo->prepare('UPDATE bdc_competitors SET current_division=:division WHERE id=:id');
            foreach($stmt->fetchAll() as $row){
                $division=self::approvedPermanentDivision($pdo,(int)$row['id'],(string)$row['dance_role'],'bachata');
                $update->execute(['division'=>$division,'id'=>$row['id']]);
                $counts['competitors']+=$update->rowCount();
            }
        }

        if(self::tableExists($pdo,'bdc_test_competitors')){
            $stmt=$pdo->prepare("SELECT id,bdc_id FROM bdc_test_competitors WHERE current_division IN ($placeholders) ORDER BY id");
            $stmt->execute($special);
            $official=$pdo->prepare("SELECT current_division FROM bdc_competitors WHERE id=:id OR (bdc_id IS NOT NULL AND bdc_id=:bdc) ORDER BY CASE WHEN id=:preferred THEN 0 ELSE 1 END,id LIMIT 1");
            $update=$pdo->prepare('UPDATE bdc_test_competitors SET current_division=:division WHERE id=:id');
            foreach($stmt->fetchAll() as $row){
                $official->execute(['id'=>$row['id'],'bdc'=>$row['bdc_id'],'preferred'=>$row['id']]);
                $division=(string)($official->fetchColumn()?:'novice');
                if(!in_array($division,['novice','intermediate','advanced','semi_pro','pro','professional','all_star'],true))$division='novice';
                $update->execute(['division'=>$division,'id'=>$row['id']]);
                $counts['test_competitors']+=$update->rowCount();
            }
        }
        return $counts;
    }

    /** Unapproved registration never selects a permanent career division. */
    public static function initialDivisionForUnapprovedEntry():string
    {
        return 'novice';
    }

    public static function statusLabel(string $selectedDivision,string $effectiveDivision):string
    {
        if(SpecialCategoryService::isSpecial($selectedDivision))return SpecialCategoryService::label($selectedDivision);
        $selected=self::normaliseDivision($selectedDivision);
        $effective=self::normaliseDivision($effectiveDivision);
        if($selected===$effective)return 'In Division';
        if((self::ORDER[$effective]??0)>(self::ORDER[$selected]??0))return 'Promoted to '.self::label($effective);
        return 'Not Yet Eligible';
    }

    public static function selectedPoints(string $division,float $novicePoints,float $intermediatePoints,float $advancedPoints):float
    {
        return match(self::normaliseDivision($division)){
            'novice'=>$novicePoints,
            'intermediate'=>$intermediatePoints,
            'advanced'=>$advancedPoints,
            default=>0.0,
        };
    }

    public static function label(string $division):string
    {
        if(SpecialCategoryService::isSpecial($division))return SpecialCategoryService::label($division);
        return match(self::normaliseDivision($division)){
            'all_star'=>'All Star',
            'unknown'=>'Unknown',
            default=>ucfirst(self::normaliseDivision($division)),
        };
    }

    private static function higher(string $a,string $b):string
    {
        $a=self::normaliseDivision($a);$b=self::normaliseDivision($b);
        return (self::ORDER[$a]??0)>=(self::ORDER[$b]??0)?$a:$b;
    }

    private static function tableExists(PDO $pdo,string $table):bool
    {
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
        $stmt->execute(['table'=>$table]);
        return (int)$stmt->fetchColumn()===1;
    }
}
