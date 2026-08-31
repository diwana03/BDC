<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Config;

final class ProfileIntegrationAuth
{
    public static function verify(string $raw,string $context=''):bool
    {
        $secret=trim((string)(getenv('BDC_PROFILE_INTEGRATION_SECRET')?:getenv('BDC_GOOGLE_FORM_SYNC_SECRET')));
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
        $configured=trim((string)(getenv('BDC_PROFILE_INTEGRATION_SCOPES')?:Config::get('integration.profile_api_scopes','competitors:submit,judges:submit')));
        $scopes=array_map('trim',explode(',',$configured));
        return in_array($entity==='judge'?'judges:submit':'competitors:submit',$scopes,true);
    }
}
