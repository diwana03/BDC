<?php
declare(strict_types=1);
use App\Services\JudgeProfileUpdateLinkService;
return static function(PDO $pdo):void{JudgeProfileUpdateLinkService::ensure($pdo);};
