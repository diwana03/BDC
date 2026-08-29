<?php
declare(strict_types=1);
use App\Services\JudgeRegistrationLinkService;
return static function(PDO $pdo):void{JudgeRegistrationLinkService::ensure($pdo);};
