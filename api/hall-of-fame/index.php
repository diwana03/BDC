<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Database;
use App\Services\HallOfFameService;
use App\Services\SpecialCategoryService;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=120');
header('X-Content-Type-Options: nosniff');

$limit=max(1,min(24,(int)($_GET['limit']??6)));
$category=strtolower(trim((string)($_GET['category']??'')));
$specialOnly=!empty($_GET['special_only']);
$filters=null;
if($category!=='')$filters=[$category];
elseif($specialOnly)$filters=array_keys(SpecialCategoryService::categories());

try{
    $items=HallOfFameService::latest(Database::connection(),$limit,$filters);
    $public=[];
    foreach($items as $item){
        $placements=[];
        foreach([1,2,3] as $place){
            $pair=$item['placements'][$place]??['leader'=>null,'follower'=>null];
            $placements[(string)$place]=[];
            foreach(['leader','follower'] as $role){
                $person=$pair[$role]??null;
                $placements[(string)$place][$role]=$person?[
                    'bdc_id'=>(string)($person['bdc_id']??''),
                    'name'=>(string)($person['exact_name']??''),
                    'country'=>(string)($person['country']??''),
                    'photo_url'=>(string)($person['photo_url']??''),
                    'profile_url'=>url('/competitor/?id='.(int)$person['competitor_id']),
                ]:null;
            }
        }
        $public[]=[
            'event_id'=>(int)$item['event_id'],
            'event_name'=>(string)$item['name'],
            'event_date'=>(string)$item['event_date'],
            'venue'=>(string)($item['venue']??''),
            'location'=>(string)($item['location']??''),
            'category'=>(string)$item['division'],
            'category_label'=>(string)$item['category_label'],
            'placements'=>$placements,
        ];
    }
    echo json_encode(['ok'=>true,'generated_at'=>gmdate('c'),'count'=>count($public),'items'=>$public],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Hall of Fame is temporarily unavailable.'],JSON_UNESCAPED_SLASHES);
}
