<?php
declare(strict_types=1);

namespace App\Services;

final class ScoringReportLabelService
{
    public static function councilDivision(array $round): string
    {
        $dance=strtolower(trim((string)($round['dance_style']??'bachata')));
        $division=strtolower(trim((string)($round['division']??'')));
        $division=preg_replace('/^(bachata|salsa)_/','',$division)??$division;
        $level=match($division){
            'rising'=>'INTERMEDIATE',
            'novice'=>'NOVICE',
            'intermediate'=>'INTERMEDIATE',
            'advanced'=>'ADVANCED',
            'open'=>'OPEN',
            'invitational'=>'INVITATIONAL',
            default=>strtoupper(str_replace('_',' ',$division?:'OPEN')),
        };
        return ($dance==='salsa'?'SDC':'BDC').' '.$level;
    }
}
