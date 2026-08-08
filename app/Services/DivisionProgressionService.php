<?php
declare(strict_types=1);

namespace App\Services;

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
}
