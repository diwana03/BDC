<?php
declare(strict_types=1);
$roundId=(int)($_GET['round_id']??0);
$embed=($_GET['embed']??'')==='1';
header('Location: control.php?data_mode=test&round_id='.$roundId.($embed?'&embed=1':''),true,302);
exit;
