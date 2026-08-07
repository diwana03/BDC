<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use PDO;

final class ReleaseManagerService
{
    public const VERSION='2.3.0';

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
            'status'=>'production',
            'release_date'=>'2026-08-05',
        ];
    }

    public static function installedVersion(string $root):?array
    {
        $root=rtrim($root,'/');
        if($root===''||!is_dir($root))return null;

        $data=null;
        $manifest=$root.'/storage/release.json';
        if(is_file($manifest)&&is_readable($manifest)){
            $candidate=json_decode((string)file_get_contents($manifest),true);
            if(is_array($candidate)&&isset($candidate['version']))$data=$candidate;
        }

        if($data===null){
            $versionFile=$root.'/VERSION.json';
            if(is_file($versionFile)&&is_readable($versionFile)){
                $candidate=json_decode((string)file_get_contents($versionFile),true);
                if(is_array($candidate)&&isset($candidate['version']))$data=$candidate;
            }
        }
        if($data===null)return null;

        /*
         * The code physically running on BDC_STAGING is not automatically
         * "Tested on Staging". A Git/cron/manual file update can make VERSION.json
         * current without any Release Manager deployment job having run.
         *
         * Reconcile only the display/state distinction here:
         *   - current_staging = physically installed, but not worker-validated
         *   - passed/approved = preserved only when the deployment pipeline set it
         *
         * This prevents the currently installed build from also appearing as
         * "Available / Deploy to Staging" while keeping Production locked.
         */
        if(self::environment()==='staging' && self::isCurrentApplicationRoot($root)){
            self::reconcileCurrentStagingState($data);
        }

        return $data;
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
            'Repository'=>self::resultStorageHealthPath(),
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

    private static function isCurrentApplicationRoot(string $root):bool
    {
        $current=realpath(dirname(__DIR__,2));
        $given=realpath($root);
        return $current!==false&&$given!==false&&hash_equals($current,$given);
    }

    private static function reconcileCurrentStagingState(array $installed):void
    {
        $version=trim((string)($installed['version']??''));
        if($version==='')return;

        try{
            $pdo=Database::connection();

            // A previously detected physical Staging build is no longer current.
            $reset=$pdo->prepare("UPDATE bdc_release_candidates
                SET status='new'
                WHERE status='current_staging' AND version<>:version");
            $reset->execute(['version'=>$version]);

            // Prefer exact manifest SHA when available. Fall back to the newest
            // candidate with the exact installed version only for display/state.
            // The fallback NEVER supplies staging_tested_sha or Production proof.
            $commit=trim((string)($installed['commit_sha']??''));
            if(preg_match('/^[a-f0-9]{40}$/',$commit)){
                $stmt=$pdo->prepare("SELECT id,status FROM bdc_release_candidates WHERE commit_sha=:sha LIMIT 1");
                $stmt->execute(['sha'=>$commit]);
            }else{
                $stmt=$pdo->prepare("SELECT id,status FROM bdc_release_candidates WHERE version=:version ORDER BY discovered_at DESC,id DESC LIMIT 1");
                $stmt->execute(['version'=>$version]);
            }
            $release=$stmt->fetch();
            if(!$release)return;

            $status=(string)$release['status'];
            if(in_array($status,['passed','approved','production','queued','testing'],true))return;

            $pdo->prepare("UPDATE bdc_release_candidates
                SET status='current_staging',
                    staging_tested_sha=NULL,
                    passed_at=NULL,
                    approved_at=NULL,
                    approved_by=NULL
                WHERE id=:id")
                ->execute(['id'=>$release['id']]);
        }catch(\Throwable){
            // Installed-version detection must remain read-safe even if the
            // release tables are unavailable during setup or migration.
        }
    }

    private static function resultStorageHealthPath():string
    {
        try{return ResultStorageService::root();}catch(\Throwable){return '(results storage not configured outside application)';}
    }
}
