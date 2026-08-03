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

    /**
     * Return the dancer's effective division using mandatory thresholds first,
     * then any valid voluntary move already recorded in current_division.
     */
    public static function effectiveDivision(
        float $novicePoints,
        float $intermediatePoints,
        float $advancedPoints,
        ?string $committedDivision,
        bool $noviceManualOut=false,
        bool $intermediateManualOut=false
    ):string{
        $committed=self::normaliseDivision($committedDivision);

        // Mandatory exits.
        if($advancedPoints>=40.0){
            $mandatory='all_star';
        }elseif($intermediatePoints>=30.0){
            $mandatory='advanced';
        }elseif($novicePoints>=25.0){
            $mandatory='intermediate';
        }else{
            $mandatory='novice';
        }

        // Existing manual-out flags remain supported as irreversible moves.
        if($intermediateManualOut && $intermediatePoints>=25.0){
            $committed=self::higher($committed,'advanced');
        }
        if($noviceManualOut && $novicePoints>=20.0){
            $committed=self::higher($committed,'intermediate');
        }

        // A voluntary move is valid only after the minimum is reached.
        if($committed==='intermediate' && $novicePoints<20.0){
            $committed='novice';
        }elseif($committed==='advanced' && $intermediatePoints<25.0){
            $committed=$novicePoints>=20.0?'intermediate':'novice';
        }elseif($committed==='all_star' && $advancedPoints<40.0){
            $committed=$intermediatePoints>=25.0?'advanced':($novicePoints>=20.0?'intermediate':'novice');
        }elseif($committed==='unknown'){
            $committed='novice';
        }

        // Never allow a stored division to move a dancer below a mandatory exit.
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
        return self::effectiveDivision(
            $novicePoints,
            $intermediatePoints,
            $advancedPoints,
            $committedDivision,
            $noviceManualOut,
            $intermediateManualOut
        )===self::normaliseDivision($division);
    }

    public static function statusLabel(
        string $selectedDivision,
        string $effectiveDivision
    ):string{
        $selected=self::normaliseDivision($selectedDivision);
        $effective=self::normaliseDivision($effectiveDivision);

        if($selected===$effective)return 'In Division';
        if((self::ORDER[$effective]??0)>(self::ORDER[$selected]??0)){
            return 'Moved to '.self::label($effective);
        }
        return 'Not Yet Eligible';
    }

    public static function selectedPoints(
        string $division,
        float $novicePoints,
        float $intermediatePoints,
        float $advancedPoints
    ):float{
        return match(self::normaliseDivision($division)){
            'novice'=>$novicePoints,
            'intermediate'=>$intermediatePoints,
            'advanced'=>$advancedPoints,
            default=>0.0,
        };
    }

    public static function label(string $division):string
    {
        return match(self::normaliseDivision($division)){
            'all_star'=>'All Star',
            'unknown'=>'Unknown',
            default=>ucfirst(self::normaliseDivision($division)),
        };
    }

    private static function higher(string $a,string $b):string
    {
        $a=self::normaliseDivision($a);
        $b=self::normaliseDivision($b);
        return (self::ORDER[$a]??0)>=(self::ORDER[$b]??0)?$a:$b;
    }
}
