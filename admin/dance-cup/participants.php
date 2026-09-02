<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
Auth::requirePermission('competitors.view');
$query=$_GET;unset($query['data_mode']);
header('Location: competitors.php'.($query?'?'.http_build_query($query):''),true,301);exit;
