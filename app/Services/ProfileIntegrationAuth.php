<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class ProfileIntegrationAuth
{
    public static function verify(string $raw,string $context=''):bool
    {
        $secret=self::secret();
        if(strlen($secret)<32)return false;
        $timestamp=trim((string)($_SERVER['HTTP_X_BDC_TIMESTAMP']??''));
        $signature=strtolower(trim((string)($_SERVER['HTTP_X_BDC_SIGNATURE']??'')));
        if(!preg_match('/^\d{10}$/',$timestamp)||abs(time()-(int)$timestamp)>300||!preg_match('/^[a-f0-9]{64}$/',$signature))return false;
        $message=$context===''?'v1.'.$timestamp.'.'.$raw:'v1.'.$timestamp.'.'.$context.'.'.$raw;
        $expected=hash_hmac('sha256',$message,$secret);
        return hash_equals($expected,$signature);
    }

    public static function allowed(string $entity):bool
    {
        return self::allowedScope($entity==='judge'?'judges:submit':'competitors:submit');
    }

    public static function allowedScope(string $scope):bool
    {
        $eventScope=str_starts_with($scope,'events:');
        $configured=$eventScope
            ? trim((string)(getenv('BDC_EVENT_INTEGRATION_SCOPES')?:Config::get('integration.event_api_scopes','events:read,events:submit')))
            : trim((string)(getenv('BDC_PROFILE_INTEGRATION_SCOPES')?:Config::get('integration.profile_api_scopes','competitors:submit,judges:submit')));
        $scopes=array_map('trim',explode(',',$configured));
        return in_array($scope,$scopes,true);
    }

    public static function allowedAnyScope(array $scopes):bool
    {
        foreach($scopes as $scope)if(self::allowedScope((string)$scope))return true;
        return false;
    }

    public static function credentialStatus():array
    {
        $environmentSecret=trim((string)getenv('BDC_PROFILE_INTEGRATION_SECRET'));
        if(strlen($environmentSecret)>=32){
            return ['configured'=>true,'source'=>'environment','fingerprint'=>substr(hash('sha256',$environmentSecret),0,12),'rotatable'=>false];
        }
        $path=self::secretPath();
        $secret=$path!==''&&is_file($path)&&is_readable($path)?trim((string)file_get_contents($path)):'';
        if(strlen($secret)>=32)return ['configured'=>true,'source'=>'protected file','fingerprint'=>substr(hash('sha256',$secret),0,12),'rotatable'=>true];
        $legacy=trim((string)getenv('BDC_GOOGLE_FORM_SYNC_SECRET'));
        return ['configured'=>strlen($legacy)>=32,'source'=>strlen($legacy)>=32?'legacy form-sync environment':'protected file','fingerprint'=>strlen($legacy)>=32?substr(hash('sha256',$legacy),0,12):'','rotatable'=>true];
    }

    public static function rotateCredential():array
    {
        $environmentSecret=trim((string)getenv('BDC_PROFILE_INTEGRATION_SECRET'));
        if($environmentSecret!=='')throw new RuntimeException('The integration credential is managed by the server environment and cannot be rotated here.');
        $path=self::secretPath();
        if($path==='')throw new RuntimeException('A protected integration credential path is not configured.');
        $directory=dirname($path);
        if(!is_dir($directory)||!is_writable($directory))throw new RuntimeException('The protected credential directory is unavailable or not writable.');
        $applicationRoot=realpath(dirname(__DIR__,2));
        $secretRoot=realpath($directory);
        if($applicationRoot===false||$secretRoot===false||$secretRoot===$applicationRoot||str_starts_with($secretRoot,$applicationRoot.DIRECTORY_SEPARATOR))throw new RuntimeException('The integration credential must be stored outside the public application directory.');
        $secret=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');
        $temporary=$directory.'/.profile-integration-'.bin2hex(random_bytes(8)).'.tmp';
        if(file_put_contents($temporary,$secret."\n",LOCK_EX)===false)throw new RuntimeException('The integration credential could not be written.');
        @chmod($temporary,0600);
        if(!@rename($temporary,$path)){@unlink($temporary);throw new RuntimeException('The integration credential could not be activated.');}
        @chmod($path,0600);
        return ['secret'=>$secret,'fingerprint'=>substr(hash('sha256',$secret),0,12)];
    }

    private static function secret():string
    {
        $secret=trim((string)getenv('BDC_PROFILE_INTEGRATION_SECRET'));
        if(strlen($secret)>=32)return $secret;
        $path=self::secretPath();
        $secret=$path!==''&&is_file($path)&&is_readable($path)?trim((string)file_get_contents($path)):'';
        if(strlen($secret)>=32)return $secret;
        return trim((string)getenv('BDC_GOOGLE_FORM_SYNC_SECRET'));
    }

    private static function secretPath():string
    {
        $configured=trim((string)Config::get('integration.profile_api_secret_file',''));
        if($configured!=='')return $configured;
        $databaseSecret=trim((string)Config::get('database.password_file',''));
        return $databaseSecret!==''?dirname($databaseSecret).'/profile-integration-secret':'';
    }
}
