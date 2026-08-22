<?php
declare(strict_types=1);

$service=(string)file_get_contents(dirname(__DIR__).'/app/Services/ScoringJudgeAssignmentService.php');
foreach([
    '$chiefCleanKey=0;',
    '$key===$chiefCleanKey?1:0',
    "SET chief_judge_id=:chief",
] as $marker){
    if(!str_contains($service,$marker)){
        fwrite(STDERR,"Chief Judge selection regression: missing {$marker}.\n");
        exit(1);
    }
}
if(str_contains($service,"\$chiefCleanKey='0';")){
    fwrite(STDERR,"Chief Judge selection regression: J1 key must be numeric for strict comparison.\n");
    exit(1);
}
echo "Chief Judge selection v349: PASS\n";
