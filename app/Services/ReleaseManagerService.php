<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use PDO;

final class ReleaseManagerService
{
    public const VERSION='2.3.0-dev2';

    public static function environment():string
    {
        return strtolower((string)Config::get('app.environment','production'));
    }

    public static function environmentLabel():string
    {
        return self::environment()==='staging'?'BDC_STAGING':'PRODUCTION';
    }

    public static function isReleaseManagerAvailable():bool
    {
        return self::environment()==='staging'
            && (bool)Config::get('deployment.enabled',false);
    }

    public static function versionInfo():array
    {
        $path=dirname(__DIR__,2).'/VERSION.json';
        if(is_file($path)){
            $data=json_decode((string)file_get_contents($path),true);
            if(is_array($data))return $data;
        }
        return [
            'version'=>self::VERSION,
            'status'=>'development',
            'release_date'=>'2026-08-05',
        ];
    }

    public static function installedVersion(string $root):?array
    {
        $root=rtrim($root,'/');
        if($root===''||!is_dir($root))return null;

        $manifest=$root.'/storage/release.json';
        if(is_file($manifest)&&is_readable($manifest)){
            $data=json_decode((string)file_get_contents($manifest),true);
            if(is_array($data)&&isset($data['version']))return $data;
        }

        $versionFile=$root.'/VERSION.json';
        if(is_file($versionFile)&&is_readable($versionFile)){
            $data=json_decode((string)file_get_contents($versionFile),true);
            if(is_array($data)&&isset($data['version']))return $data;
        }
        return null;
    }

    public static function recordCurrentRelease(?int $userId=null):void
    {
        $pdo=Database::connection();
        $info=self::versionInfo();
        $environment=self::environment();

        $stmt=$pdo->prepare("
            INSERT INTO bdc_release_installations(
             version,environment,status,installed_by,installed_at,notes
            ) VALUES(
             :version,:environment,'installed',:installed_by,NOW(),:notes
            )
            ON DUPLICATE KEY UPDATE
             status='installed',
             installed_by=COALESCE(VALUES(installed_by),installed_by),
             notes=VALUES(notes),
             updated_at=NOW()
        ");
        $stmt->execute([
            'version'=>(string)($info['version']??self::VERSION),
            'environment'=>$environment,
            'installed_by'=>$userId,
            'notes'=>'Detected by BDC Release Manager',
        ]);
    }

    public static function health(PDO $pdo):array
    {
        $root=dirname(__DIR__,2);
        $checks=[];

        $checks[]=['name'=>'PHP Version','status'=>version_compare(PHP_VERSION,'8.1.0','>='),'detail'=>PHP_VERSION];

        try{
            $databaseVersion=(string)$pdo->query('SELECT VERSION()')->fetchColumn();
            $checks[]=['name'=>'Database','status'=>true,'detail'=>$databaseVersion];
        }catch(\Throwable $e){
            $checks[]=['name'=>'Database','status'=>false,'detail'=>$e->getMessage()];
        }

        foreach([
            'Storage'=>$root.'/storage',
            'Repository'=>$root.'/public/results',
            'Uploads'=>$root.'/uploads',
        ] as $name=>$path){
            $checks[]=[
                'name'=>$name,
                'status'=>is_dir($path)&&is_writable($path),
                'detail'=>$path,
            ];
        }

        $checks[]=[
            'name'=>'ZIP Extension',
            'status'=>class_exists(\ZipArchive::class),
            'detail'=>class_exists(\ZipArchive::class)?'Available':'Missing',
        ];

        $checks[]=[
            'name'=>'cURL Extension',
            'status'=>function_exists('curl_init'),
            'detail'=>function_exists('curl_init')?'Available':'Missing',
        ];

        $free=@disk_free_space($root);
        $checks[]=[
            'name'=>'Free Disk Space',
            'status'=>$free===false||$free>500*1024*1024,
            'detail'=>$free===false?'Unknown':number_format($free/1024/1024/1024,2).' GB',
        ];

        return $checks;
    }
}
