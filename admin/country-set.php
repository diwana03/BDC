<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\CountrySetService;

Auth::requireAdmin();
header('Content-Type: application/json; charset=utf-8');
$entity=(string)($_GET['entity']??'');$id=(int)($_GET['id']??0);
$tables=['competitor'=>'bdc_competitors','wdc'=>'bdc_wdc_identities','judge'=>'bdc_judges'];
if(!isset($tables[$entity])||$id<1){echo json_encode(['countries'=>[]]);exit;}
$q=Database::connection()->prepare("SELECT country,countries_json FROM {$tables[$entity]} WHERE id=:id LIMIT 1");
$q->execute(['id'=>$id]);$row=$q->fetch();
echo json_encode(['countries'=>$row?CountrySetService::fromRow($row):[]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
