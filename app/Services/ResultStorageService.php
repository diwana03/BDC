<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class ResultStorageService
{
    public static function root(): string
    {
        $app=realpath(dirname(__DIR__,2));
        if($app===false){
            throw new RuntimeException('The application directory could not be resolved.');
        }

        $normalApp=rtrim(str_replace('\\','/',$app),'/');
        $environment=self::environment($normalApp);
        $canonical=self::defaultRoot($normalApp,$environment);
        $configured=trim((string)Config::get('results.storage_path',''));
        $root=$configured!==''?$configured:$canonical;
        $root=rtrim(str_replace('\\','/',$root),'/');

        if($root==='' || $root[0]!=='/'){
            throw new RuntimeException('Results storage must use an absolute server path.');
        }
        if($root===$normalApp || str_starts_with($root,$normalApp.'/')){
            throw new RuntimeException('Results storage must be outside the Production or Staging application directory.');
        }

        self::assertEnvironmentRepository($normalApp,$root,$environment);

        if(!is_dir($root) && !mkdir($root,0750,true) && !is_dir($root)){
            throw new RuntimeException('Could not create protected results storage.');
        }

        $resolvedRoot=realpath($root);
        if($resolvedRoot===false || !is_dir($resolvedRoot) || !is_writable($resolvedRoot)){
            throw new RuntimeException('Protected results storage is unavailable or not writable.');
        }

        $normalRoot=rtrim(str_replace('\\','/',$resolvedRoot),'/');
        if($normalRoot===$normalApp || str_starts_with($normalRoot,$normalApp.'/')){
            throw new RuntimeException('Results storage resolves inside the application directory.');
        }

        self::assertEnvironmentRepository($normalApp,$normalRoot,$environment);

        return $normalRoot;
    }

    public static function path(string $name): string
    {
        $safe=basename(str_replace('\\','/',$name));
        if($safe==='' || $safe==='.' || $safe==='..') throw new RuntimeException('Invalid result filename.');
        return self::root().'/'.$safe;
    }

    public static function relative(string $name): string
    {
        return 'protected-results://'.basename($name);
    }

    public static function publicUrl(string $name): string
    {
        return url('result-file.php?file='.rawurlencode(basename($name)));
    }

    public static function resolve(string $storagePath): ?string
    {
        if(!str_starts_with($storagePath,'protected-results://')) return null;
        return self::resolveFilename(substr($storagePath,20));
    }

    /**
     * Resolve an existing BDC result by filename only inside the repository for
     * the currently running environment. This is intentionally environment-local:
     * Production can never fall back to Staging storage and Staging can never
     * fall back to Production storage.
     */
    public static function resolveFilename(string $name): ?string
    {
        $safe=basename(str_replace('\\','/',$name));
        if($safe==='' || $safe==='.' || $safe==='..')return null;
        $file=self::path($safe);
        return is_file($file)?$file:null;
    }

    public static function environmentName(): string
    {
        $app=realpath(dirname(__DIR__,2));
        if($app===false)throw new RuntimeException('The application directory could not be resolved.');
        return self::environment(rtrim(str_replace('\\','/',$app),'/'));
    }

    private static function defaultRoot(string $normalApp,string $environment): string
    {
        $marker='/public_html/';
        $position=strpos($normalApp,$marker);
        if($position===false){
            throw new RuntimeException('Results storage is not configured and the protected account directory could not be derived.');
        }

        $accountRoot=substr($normalApp,0,$position);
        if($accountRoot==='' || $accountRoot==='/'){
            throw new RuntimeException('The protected account directory could not be derived safely.');
        }

        return $accountRoot.'/.bdc-results/'.$environment;
    }

    private static function environment(string $normalApp): string
    {
        $configured=strtolower(trim((string)Config::get('app.environment','')));
        if(in_array($configured,['production','staging'],true)){
            return $configured;
        }

        $basePath=strtolower((string)Config::get('app.base_path',''));
        if(str_contains($basePath,'staging'))return 'staging';
        if(str_contains(strtolower($normalApp),'staging'))return 'staging';
        return 'production';
    }

    private static function assertEnvironmentRepository(string $normalApp,string $normalRoot,string $environment): void
    {
        $root=strtolower(rtrim(str_replace('\\','/',$normalRoot),'/'));
        $expectedSuffix='/.bdc-results/'.$environment;
        if(!str_ends_with($root,$expectedSuffix)){
            throw new RuntimeException(ucfirst($environment).' must use its own '.$expectedSuffix.' results repository.');
        }

        $opposite=$environment==='staging'?'production':'staging';
        if(str_ends_with($root,'/.bdc-results/'.$opposite)){
            throw new RuntimeException(ucfirst($environment).' cannot use the '.$opposite.' results repository.');
        }

        $app=strtolower(rtrim(str_replace('\\','/',$normalApp),'/'));
        if($root===$app||str_starts_with($root,$app.'/')){
            throw new RuntimeException('Results storage resolves inside the application directory.');
        }
    }
}
