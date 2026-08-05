<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Services\StagingDatabaseSyncService;
try{
    $scheduled=in_array('--scheduled',$argv??[],true);
    if($scheduled&&!StagingDatabaseSyncService::isDue()){echo "Staging database sync is not due.\n";exit(0);}
    $result=StagingDatabaseSyncService::sync(0);echo $result['message']."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
