<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class ResultStorageService
{
    public static function root(): string
    {
        $configured=trim((string)Config::get('results.storage_path',''));
        if($configured==='') throw new RuntimeException('Results storage is not configured outside the application directory.');

        $root=rtrim(str_replace('\\','/',$configured),'/');
        $app=realpath(dirname(__DIR__,2));
        if($app!==false){
            $normalApp=rtrim(str_replace('\\','/',$app),'/');
            if($root===$normalApp || str_starts_with($root,$normalApp.'/')){
                throw new RuntimeException('Results storage must be outside the Production or Staging application directory.');
            }
        }
        if(!is_dir($root) && !mkdir($root,0750,true) && !is_dir($root)){
            throw new RuntimeException('Could not create protected results storage.');
        }
        return $root;
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
        $file=self::path(substr($storagePath,20));
        return is_file($file)?$file:null;
    }
}
