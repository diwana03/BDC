<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class GoogleDriveBackupService
{
    private array $credentials;
    private string $folderId;

    public function __construct(string $credentialsPath,string $folderId)
    {
        if(!is_file($credentialsPath))throw new RuntimeException('Google service-account JSON file was not found.');
        $decoded=json_decode((string)file_get_contents($credentialsPath),true);
        if(!is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])){
            throw new RuntimeException('Invalid Google service-account JSON credentials.');
        }
        $this->credentials=$decoded;
        $this->folderId=self::normaliseFolderId($folderId);
        if($this->folderId==='')throw new RuntimeException('Google Drive folder ID is required.');
        if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is required for Google Drive backup.');
        if(!function_exists('openssl_sign'))throw new RuntimeException('PHP OpenSSL extension is required for Google Drive backup.');
    }

    public static function normaliseFolderId(string $value):string
    {
        $value=trim($value);
        if($value==='')return '';
        if(preg_match('~/folders/([A-Za-z0-9_-]+)~',$value,$match))return $match[1];
        if(preg_match('/^[A-Za-z0-9_-]+$/',$value))return $value;
        throw new RuntimeException('Google Drive folder ID or folder URL is invalid.');
    }

    private static function apiError(string $response,int $status,string $fallback):string
    {
        $payload=json_decode($response,true);
        $message=$payload['error']['message']??$payload['error_description']??'';
        return $fallback.' (HTTP '.$status.')'.($message!==''?': '.$message:'');
    }

    private static function base64Url(string $data):string
    {
        return rtrim(strtr(base64_encode($data),'+/','-_'),'=');
    }

    private function accessToken():string
    {
        $now=time();
        $header=['alg'=>'RS256','typ'=>'JWT'];
        if(!empty($this->credentials['private_key_id']))$header['kid']=$this->credentials['private_key_id'];
        $claims=[
            'iss'=>$this->credentials['client_email'],
            'scope'=>'https://www.googleapis.com/auth/drive.file',
            'aud'=>'https://oauth2.googleapis.com/token',
            'iat'=>$now,
            'exp'=>$now+3600,
        ];
        $unsigned=self::base64Url(json_encode($header,JSON_UNESCAPED_SLASHES)).'.'.self::base64Url(json_encode($claims,JSON_UNESCAPED_SLASHES));
        $signature='';
        if(!openssl_sign($unsigned,$signature,$this->credentials['private_key'],OPENSSL_ALGO_SHA256)){
            throw new RuntimeException('Could not sign the Google OAuth service-account request.');
        }
        $jwt=$unsigned.'.'.self::base64Url($signature);
        $response=$this->request(
            'POST',
            'https://oauth2.googleapis.com/token',
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query([
                'grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'=>$jwt,
            ]),
            false
        );
        $payload=json_decode($response,true);
        if(!is_array($payload) || empty($payload['access_token'])){
            throw new RuntimeException('Google OAuth token request failed.');
        }
        return (string)$payload['access_token'];
    }

    private function request(string $method,string $url,array $headers=[],?string $body=null,bool $authenticated=true):string
    {
        if($authenticated)$headers[]='Authorization: Bearer '.$this->accessToken();
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CUSTOMREQUEST=>$method,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_CONNECTTIMEOUT=>20,
            CURLOPT_TIMEOUT=>600,
        ]);
        if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $response=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $error=curl_error($ch);
        curl_close($ch);
        if($response===false || $status<200 || $status>=300){
            throw new RuntimeException('Google Drive request failed (HTTP '.$status.'): '.($error?:substr((string)$response,0,500)));
        }
        return (string)$response;
    }

    public function testConnection():array
    {
        $token=$this->accessToken();
        $url='https://www.googleapis.com/drive/v3/files/'.rawurlencode($this->folderId).'?supportsAllDrives=true&fields=id,name,mimeType,driveId,capabilities(canAddChildren)';
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token],CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>60]);
        $response=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $error=curl_error($ch);
        curl_close($ch);
        if($response===false || $status<200 || $status>=300){
            throw new RuntimeException(self::apiError((string)$response,$status,'Google Drive folder could not be accessed').($error!==''?' — '.$error:'').' Service account: '.$this->credentials['client_email']);
        }
        $data=json_decode((string)$response,true);
        if(($data['mimeType']??'')!=='application/vnd.google-apps.folder')throw new RuntimeException('The selected Google Drive item is not a folder.');
        if(isset($data['capabilities']['canAddChildren']) && !$data['capabilities']['canAddChildren'])throw new RuntimeException('The service account can see the folder but cannot add backup files.');
        return ['folder_id'=>$this->folderId,'folder_name'=>$data['name']??'Google Drive folder','service_account'=>$this->credentials['client_email'],'shared_drive'=>!empty($data['driveId']),'write_access'=>true];
    }

    public function upload(string $localPath,string $remoteName):array
    {
        if(!is_file($localPath))throw new RuntimeException('Backup file was not found for Google Drive upload.');
        $token=$this->accessToken();
        $boundary='bdc_backup_'.bin2hex(random_bytes(12));
        $metadata=json_encode([
            'name'=>$remoteName,
            'parents'=>[$this->folderId],
            'appProperties'=>['source'=>'bdc_backup'],
        ],JSON_UNESCAPED_SLASHES);
        $data=(string)file_get_contents($localPath);
        $body="--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$metadata}\r\n";
        $body.="--{$boundary}\r\nContent-Type: application/octet-stream\r\n\r\n{$data}\r\n";
        $body.="--{$boundary}--\r\n";
        $url='https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,size,createdTime,webViewLink';
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>[
                'Authorization: Bearer '.$token,
                'Content-Type: multipart/related; boundary='.$boundary,
                'Content-Length: '.strlen($body),
            ],
            CURLOPT_POSTFIELDS=>$body,
            CURLOPT_CONNECTTIMEOUT=>20,
            CURLOPT_TIMEOUT=>1800,
        ]);
        $response=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $error=curl_error($ch);
        curl_close($ch);
        if($response===false || $status<200 || $status>=300){
            throw new RuntimeException('Google Drive upload failed (HTTP '.$status.'): '.($error?:substr((string)$response,0,500)));
        }
        $payload=json_decode((string)$response,true);
        if(!is_array($payload) || empty($payload['id']))throw new RuntimeException('Google Drive did not return a file ID.');
        return $payload;
    }

    public function delete(string $fileId):void
    {
        if($fileId==='')return;
        $token=$this->accessToken();
        $ch=curl_init('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'?supportsAllDrives=true');
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CUSTOMREQUEST=>'DELETE',
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token],
            CURLOPT_CONNECTTIMEOUT=>20,
            CURLOPT_TIMEOUT=>120,
        ]);
        $response=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if(!in_array($status,[204,404],true)){
            throw new RuntimeException('Could not delete old Google Drive backup (HTTP '.$status.').');
        }
    }
}
