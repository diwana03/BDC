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
         * Release Manager workflow rule:
         * when the build physically running on BDC_STAGING matches a discovered
         * release candidate, that candidate has already reached Staging and must
         * not be offered for another Staging deployment.
         *
         * If storage/release.json contains a commit SHA, the SHA must match.
         * Older/direct/cron Staging installs may only expose VERSION.json; in that
         * case the newest candidate with the exact installed version is used.
         *
         * This reconciliation happens only when reading the actual current
         * Staging application root. A GitHub push alone cannot trigger it unless
         * the Staging files themselves report the new version.
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

            // Any older physical marker is no longer the build currently running.
            $reset=$pdo->prepare("UPDATE bdc_release_candidates
                SET status='new',staging_tested_sha=NULL,passed_at=NULL,approved_at=NULL,approved_by=NULL
                WHERE status='current_staging' AND version<>:version");
            $reset->execute(['version'=>$version]);

            // Exact manifest SHA wins. VERSION.json fallback is required for the
            // existing cron/direct Staging workflow that predates release.json.
            $commit=trim((string)($installed['commit_sha']??''));
            if(preg_match('/^[a-f0-9]{40}$/',$commit)){
                $stmt=$pdo->prepare("SELECT id,status,commit_sha FROM bdc_release_candidates WHERE commit_sha=:sha LIMIT 1");
                $stmt->execute(['sha'=>$commit]);
            }else{
                $stmt=$pdo->prepare("SELECT id,status,commit_sha FROM bdc_release_candidates WHERE version=:version ORDER BY discovered_at DESC,id DESC LIMIT 1");
                $stmt->execute(['version'=>$version]);
            }
            $release=$stmt->fetch();
            if(!$release)return;

            $status=(string)$release['status'];
            if(in_array($status,['passed','approved','production','queued','testing'],true))return;

            /*
             * The running Staging build is the proof that this version reached
             * Staging. Mark the exact candidate as passed so the existing Release
             * Manager flow exposes Production instead of asking to deploy the
             * same version to Staging again.
             */
            $pdo->prepare("UPDATE bdc_release_candidates
                SET status='passed',
                    staging_tested_sha=commit_sha,
                    staged_at=COALESCE(staged_at,NOW()),
                    passed_at=COALESCE(passed_at,NOW()),
                    approved_at=NULL,
                    approved_by=NULL
                WHERE id=:id")
                ->execute(['id'=>$release['id']]);
        }catch(\Throwable){
            // Installed-version detection must remain read-safe during setup.
        }
    }

    private static function resultStorageHealthPath():string
    {
        try{return ResultStorageService::root();}catch(\Throwable){return '(results storage not configured outside application)';}
    }
}
