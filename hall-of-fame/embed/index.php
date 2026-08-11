<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Database;
use App\Services\HallOfFameService;
use App\Services\SpecialCategoryService;

$limit=max(1,min(9,(int)($_GET['limit']??6)));
$specialOnly=isset($_GET['special']) ? (bool)$_GET['special'] : true;
$filters=$specialOnly?array_keys(SpecialCategoryService::categories()):null;
$items=[];
try{$items=HallOfFameService::latest(Database::connection(),$limit,$filters);}catch(Throwable $e){$items=[];}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>BDC Hall of Fame</title>
<style>
*{box-sizing:border-box}body{margin:0;background:transparent;color:#17191d;font-family:Arial,Helvetica,sans-serif}.hof{padding:8px}.hof-head{display:flex;justify-content:space-between;align-items:end;gap:14px;flex-wrap:wrap;margin-bottom:18px}.eyebrow{font-size:12px;letter-spacing:.14em;text-transform:uppercase;font-weight:800;color:#c9102f}.hof h2{margin:4px 0 0;font-family:Georgia,'Times New Roman',serif;font-size:34px;font-weight:500;letter-spacing:.04em}.hof-sub{color:#69707a;font-size:14px;margin-top:6px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.card{background:#fff;border:1px solid #e2e5e9;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.07)}.card-top{padding:18px 18px 14px;background:linear-gradient(135deg,#121212,#341018);color:#fff;border-bottom:4px solid #d10f32}.date{font-size:11px;letter-spacing:.09em;text-transform:uppercase;opacity:.75}.event{font-family:Georgia,'Times New Roman',serif;font-size:21px;line-height:1.2;margin:7px 0 4px}.category{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;background:#fff;color:#181818;padding:5px 8px;border-radius:999px}.podium{padding:8px 16px 14px}.place{padding:12px 0;border-bottom:1px solid #eceef1}.place:last-child{border-bottom:0}.place-title{display:flex;align-items:center;gap:8px;font-weight:800;font-size:13px;margin-bottom:8px}.people{display:grid;grid-template-columns:1fr 1fr;gap:8px}.person{display:flex;align-items:center;gap:8px;min-width:0}.person img{width:38px;height:38px;border-radius:50%;object-fit:cover;background:#eef0f3;border:1px solid #e0e3e7}.person div{min-width:0}.name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bdc{font-size:10px;color:#7b8189;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.empty{color:#9aa0a6;font-size:12px}.actions{display:flex;justify-content:flex-end;margin-top:16px}.view{display:inline-block;text-decoration:none;background:#17191d;color:#fff;padding:10px 14px;border-radius:9px;font-size:13px;font-weight:700}.none{padding:28px;background:#fff;border:1px solid #e2e5e9;border-radius:14px;text-align:center;color:#747a82}@media(max-width:900px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.grid{grid-template-columns:1fr}.hof h2{font-size:28px}.people{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<section class="hof" aria-label="BDC Hall of Fame">
 <div class="hof-head">
  <div><div class="eyebrow">Bachata Dance Council</div><h2>Hall of Fame</h2><div class="hof-sub"><?= $specialOnly?'Special category champions and podium finishers.':'Recent official BDC champions and podium finishers.' ?></div></div>
 </div>
 <?php if($items):?><div class="grid">
 <?php foreach($items as $item):?>
  <article class="card">
   <div class="card-top"><div class="date"><?=e(date('d M Y',strtotime((string)$item['event_date'])))?></div><div class="event"><?=e((string)$item['name'])?></div><span class="category"><?=e((string)$item['category_label'])?></span></div>
   <div class="podium">
    <?php foreach([1=>['🥇','Champion'],2=>['🥈','1st Runner Up'],3=>['🥉','2nd Runner Up']] as $place=>$meta):$pair=$item['placements'][$place]??['leader'=>null,'follower'=>null];?>
     <div class="place"><div class="place-title"><span><?=$meta[0]?></span><span><?=$meta[1]?></span></div><div class="people">
      <?php foreach(['leader','follower'] as $role):$person=$pair[$role]??null;?>
       <?php if($person):$photo=$person['photo_url']?:url('/public/assets/img/default-competitor.svg');?>
        <div class="person"><img src="<?=e($photo)?>" alt=""><div><div class="name"><?=e((string)$person['exact_name'])?></div><div class="bdc"><?=e((string)($person['bdc_id']??''))?></div></div></div>
       <?php else:?><div class="empty">Not recorded</div><?php endif;?>
      <?php endforeach;?>
     </div></div>
    <?php endforeach;?>
   </div>
  </article>
 <?php endforeach;?>
 </div><?php else:?><div class="none">Hall of Fame results will appear here after official results are published.</div><?php endif;?>
 <div class="actions"><a class="view" href="<?=e(url('/#hall-of-fame'))?>" target="_top">View Full Hall of Fame</a></div>
</section>
</body>
</html>
