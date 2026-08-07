<?php
declare(strict_types=1);

$mode=(string)($_GET['mode']??'');
if($mode==='special'){
    require __DIR__.'/special.php';
    exit;
}

require __DIR__.'/core.php';
