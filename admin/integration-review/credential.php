<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Services\ProfileIntegrationAuth;

Auth::requireSuperAdmin();
if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){
    http_response_code(419);
    exit('Invalid security token.');
}

try{
    $credential=ProfileIntegrationAuth::rotateCredential();
    Auth::audit((int)(Auth::user()['id']??0),'profile_integration_credential_rotated',['fingerprint'=>$credential['fingerprint']],'profile_integration_credential');
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="bdc-profile-integration-credential.txt"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo "BDC_PROFILE_INTEGRATION_SECRET=".$credential['secret']."\n";
    echo "BDC_PROFILE_INTEGRATION_SCOPES=competitors:submit,judges:submit\n";
    echo "BDC_EVENT_INTEGRATION_SCOPES=events:read,events:submit\n";
    echo "BDC_PROFILE_INTEGRATION_FINGERPRINT=".$credential['fingerprint']."\n";
}catch(Throwable $e){
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $e->getMessage();
}
