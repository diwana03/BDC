<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\JudgeDirectoryService;
use App\Services\CountryFlagService;

Auth::requireAdmin();
header('Content-Type: application/json; charset=utf-8');

$q=trim((string)($_GET['q']??''));

try{
    $rows=JudgeDirectoryService::search(Database::connection(),$q);
    foreach($rows as &$r){
        // Country is optional in the canonical judge directory. Keep the
        // autocomplete row valid even for legacy/profile records that have
        // neither country nor country_code populated.
        $country=trim((string)($r['country']??''));
        $countryCode=trim((string)($r['country_code']??''));
        $r['country']=$country;
        $r['country_code']=$countryCode;
        $r['flag']=CountryFlagService::emoji($countryCode!==''?$countryCode:($country!==''?$country:null));
        $r['display_name']=trim((string)($r['display_name']??''));
        $r['full_name']=trim((string)($r['full_name']??''));
        $r['judge_code']=trim((string)($r['judge_code']??''));
    }
    unset($r);
    echo json_encode(['ok'=>true,'judges'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
