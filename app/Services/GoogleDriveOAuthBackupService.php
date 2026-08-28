<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class GoogleDriveOAuthBackupService
{
    private string $root;
    private string $folderId;
    private array $client;
    private string $tokenPath;

    public function __construct(string $root,string $folderId='')
    {
        $this->root=rtrim($root,'/');
        $this->folderId=GoogleDriveBackupService::normaliseFolderId($folderId);
        $clientPath=$this->root.'/storage/private/google-drive-oauth-client.json';
        $this->tokenPath=$this->root.'/storage/private/google-drive-oauth-token.json';
        if(!is_file($clientPath))throw new RuntimeException('Upload the Google OAuth client JSON before connecting Google Drive.');
        $decoded=json_decode((string)file_get_contents($clientPath),true);
        $client=$decoded['web']??$decoded['installed']??null;
        if(!is_array($client)||empty($client['client_id'])||empty($client['client_secret']))throw new RuntimeException('Invalid Google OAuth client JSON.');
        $this->client=$client;
        if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is required for Google Drive backup.');
    }

    public static function clientConfigured(string $root):bool{return is_file(rtrim($root,'/').'/storage/private/google-drive-oauth-client.json');}
    public static function connected(string $root):bool
    {
        $path=rtrim($root,'/').'/storage/private/google-drive-oauth-token.json';
        if(!is_file($path))return false;$data=json_decode((string)file_get_contents($path),true);
        return is_array($data)&&!empty($data['refresh_token']);
    }
    public static function account(string $root):string
    {
        $path=rtrim($root,'/').'/storage/private/google-drive-oauth-token.json';
        $data=is_file($path)?json_decode((string)file_get_contents($path),true):null;
        return is_array($data)?(string)($data['account_email']??''):'';
    }

    public function redirectUri():string
    {
        return rtrim((string)Config::get('app.url','https://bachatadancecouncil.com/portal'),'/').'/admin/system-maintenance/google-drive-callback.php';
    }

    public function popupRedirectUri():string
    {
        $parts=parse_url((string)Config::get('app.url','https://bachatadancecouncil.com/portal'));
        $scheme=(string)($parts['scheme']??'https');$host=(string)($parts['host']??'bachatadancecouncil.com');
        $port=isset($parts['port'])?':'.(int)$parts['port']:'';
        return $scheme.'://'.$host.$port;
    }

    public function popupConfig():array
    {
        $state=bin2hex(random_bytes(24));$_SESSION['bdc_google_oauth_state']=$state;
        return ['client_id'=>(string)$this->client['client_id'],'state'=>$state,'scope'=>'https://www.googleapis.com/auth/drive.file'];
    }

    public function authorizationUrl():string
    {
        $state=bin2hex(random_bytes(24));$_SESSION['bdc_google_oauth_state']=$state;
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id'=>$this->client['client_id'],'redirect_uri'=>$this->redirectUri(),'response_type'=>'code',
            // Keep Google's one-time authorization code out of the callback URL.
            // Some shared-hosting ModSecurity rules reject long OAuth codes in
            // query strings before PHP can validate them. The callback relays
            // this fragment to the same-origin endpoint as an encoded JSON body.
            'response_mode'=>'fragment',
            'scope'=>'https://www.googleapis.com/auth/drive.file','access_type'=>'offline','prompt'=>'consent',
            'include_granted_scopes'=>'true','state'=>$state,
        ]);
    }

    public function complete(string $code,string $state,?string $redirectUri=null):array
    {
        $expected=(string)($_SESSION['bdc_google_oauth_state']??'');unset($_SESSION['bdc_google_oauth_state']);
        if($expected===''||$state===''||!hash_equals($expected,$state))throw new RuntimeException('Google authorization state is invalid or expired. Start Connect Google Drive again.');
        $token=$this->tokenRequest(['code'=>$code,'client_id'=>$this->client['client_id'],'client_secret'=>$this->client['client_secret'],'redirect_uri'=>$redirectUri??$this->redirectUri(),'grant_type'=>'authorization_code']);
        if(empty($token['refresh_token']))throw new RuntimeException('Google did not issue an offline refresh token. Remove BDC Backup System from your Google Account connections and connect again.');
        $token['expires_at']=time()+(int)($token['expires_in']??3600)-60;
        $about=$this->api('GET','https://www.googleapis.com/drive/v3/about?fields=user(displayName,emailAddress)',null,(string)$token['access_token']);
        $token['account_email']=(string)($about['user']['emailAddress']??'');
        $this->writeToken($token);
        return ['account_email'=>$token['account_email']];
    }

    public function ensureManagedFolder(string $preferredName='BDC_Backup'):array
    {
        if($this->folderId!==''){
            try{return $this->folder();}catch(\Throwable){}
        }
        $data=$this->api('POST','https://www.googleapis.com/drive/v3/files?fields=id,name,webViewLink,parents,trashed',json_encode(['name'=>$preferredName,'mimeType'=>'application/vnd.google-apps.folder'],JSON_UNESCAPED_SLASHES));
        $this->folderId=(string)($data['id']??'');if($this->folderId==='')throw new RuntimeException('Google Drive did not return the new backup folder ID.');
        return $data;
    }

    public function folderId():string{return $this->folderId;}

    public function folderUrl():string
    {
        return $this->folderId!==''?'https://drive.google.com/drive/folders/'.rawurlencode($this->folderId):'';
    }

    private function folder():array
    {
        if($this->folderId==='')throw new RuntimeException('Connect Google Drive to create the BDC backup folder.');
        $data=$this->api('GET','https://www.googleapis.com/drive/v3/files/'.rawurlencode($this->folderId).'?supportsAllDrives=true&fields=id,name,mimeType,webViewLink,parents,trashed,capabilities(canAddChildren)');
        if(!empty($data['trashed']))throw new RuntimeException('The saved Google Drive backup folder is in Trash.');
        if(($data['mimeType']??'')!=='application/vnd.google-apps.folder')throw new RuntimeException('The selected Google Drive item is not a folder.');
        return $data;
    }

    public function testConnection():array
    {
        $data=$this->folder();
        return ['folder_id'=>$this->folderId,'folder_name'=>$data['name']??'BDC_Backup','account'=>self::account($this->root),'write_access'=>true];
    }

    public function upload(string $localPath,string $remoteName):array
    {
        if(!is_file($localPath))throw new RuntimeException('Backup file was not found for Google Drive upload.');
        $this->folder();
        $token=$this->accessToken();
        $metadata=json_encode(['name'=>$remoteName,'parents'=>[$this->folderId],'appProperties'=>['source'=>'bdc_backup']],JSON_UNESCAPED_SLASHES);
        if($metadata===false)throw new RuntimeException('Could not prepare Google Drive upload metadata.');
        $location='';
        $ch=curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,size,createdTime,webViewLink,parents,trashed');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>[
            'Authorization: Bearer '.$token,'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Type: application/octet-stream','X-Upload-Content-Length: '.(string)filesize($localPath),
        ],CURLOPT_POSTFIELDS=>$metadata,CURLOPT_HEADERFUNCTION=>static function($curl,string $header)use(&$location):int{
            if(stripos($header,'Location:')===0)$location=trim(substr($header,9));return strlen($header);
        },CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>120]);
        $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        if($response===false||$status<200||$status>=300)throw new RuntimeException($this->providerError((string)$response,$status,'Google Drive could not start the backup upload',$error));
        if($location==='')throw new RuntimeException('Google Drive did not return a resumable upload URL.');
        $stream=fopen($localPath,'rb');if($stream===false)throw new RuntimeException('Backup file could not be opened for Google Drive upload.');
        $ch=curl_init($location);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_UPLOAD=>true,CURLOPT_INFILE=>$stream,CURLOPT_INFILESIZE=>(int)filesize($localPath),CURLOPT_HTTPHEADER=>['Content-Type: application/octet-stream'],CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>3600]);
        $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);fclose($stream);
        if($response===false||$status<200||$status>=300)throw new RuntimeException($this->providerError((string)$response,$status,'Google Drive backup upload failed',$error));
        $uploaded=json_decode((string)$response,true);if(!is_array($uploaded))throw new RuntimeException('Google Drive returned an invalid upload response.');
        return $this->attachAndVerifyFile((string)($uploaded['id']??''));
    }

    private function providerError(string $response,int $status,string $fallback,string $curlError=''):string
    {
        $data=json_decode($response,true);$message=is_array($data)?(string)($data['error']['message']??$data['error_description']??''):'';
        return $fallback.' (HTTP '.$status.'): '.($message!==''?$message:($curlError!==''?$curlError:'Unknown error'));
    }

    public function attachAndVerifyFile(string $fileId):array
    {
        if($fileId==='')throw new RuntimeException('Google Drive did not return a backup file ID.');
        $this->folder();
        $file=$this->file($fileId);
        if(!in_array($this->folderId,(array)($file['parents']??[]),true)){
            $file=$this->api('PATCH','https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'?supportsAllDrives=true&addParents='.rawurlencode($this->folderId).'&fields=id,name,size,createdTime,webViewLink,parents,trashed','{}');
        }
        if(!in_array($this->folderId,(array)($file['parents']??[]),true))throw new RuntimeException('Google Drive uploaded the backup but did not attach it to the BDC_Backup folder.');
        return $file;
    }

    private function file(string $fileId):array
    {
        $file=$this->api('GET','https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'?supportsAllDrives=true&fields=id,name,size,createdTime,webViewLink,parents,trashed');
        if(!empty($file['trashed']))throw new RuntimeException('The Google Drive backup file is in Trash.');
        if((string)($file['id']??'')==='')throw new RuntimeException('Google Drive could not verify the uploaded backup file.');
        return $file;
    }

    public function delete(string $fileId):void
    {
        if($fileId==='')return;$this->api('DELETE','https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId),null);
    }

    private function accessToken():string
    {
        $token=is_file($this->tokenPath)?json_decode((string)file_get_contents($this->tokenPath),true):null;
        if(!is_array($token)||empty($token['refresh_token']))throw new RuntimeException('Google Drive is not connected.');
        if(!empty($token['access_token'])&&(int)($token['expires_at']??0)>time()+60)return (string)$token['access_token'];
        $fresh=$this->tokenRequest(['client_id'=>$this->client['client_id'],'client_secret'=>$this->client['client_secret'],'refresh_token'=>$token['refresh_token'],'grant_type'=>'refresh_token']);
        $token=array_merge($token,$fresh);$token['expires_at']=time()+(int)($fresh['expires_in']??3600)-60;$this->writeToken($token);
        return (string)$token['access_token'];
    }

    private function tokenRequest(array $fields):array
    {
        return $this->rawJson('POST','https://oauth2.googleapis.com/token',http_build_query($fields),['Content-Type: application/x-www-form-urlencoded']);
    }

    private function api(string $method,string $url,?string $body=null,?string $token=null,array $headers=[]):array
    {
        $headers[]='Authorization: Bearer '.($token??$this->accessToken());
        if($body!==null&&!array_filter($headers,static fn(string $h):bool=>str_starts_with(strtolower($h),'content-type:'))) $headers[]='Content-Type: application/json';
        return $this->rawJson($method,$url,$body,$headers);
    }

    private function rawJson(string $method,string $url,?string $body,array $headers):array
    {
        $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>1800]);
        if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);$response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        $data=json_decode((string)$response,true);$providerError='';
        if(is_array($data)){$providerError=is_array($data['error']??null)?(string)($data['error']['message']??''):(string)($data['error']??$data['error_description']??'');}
        if($response===false||$status<200||$status>=300)throw new RuntimeException('Google Drive request failed (HTTP '.$status.'): '.($providerError!==''?$providerError:($error!==''?$error:'Unknown error')));
        return is_array($data)?$data:[];
    }

    private function writeToken(array $token):void
    {
        $dir=dirname($this->tokenPath);if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Could not create secure Google credential storage.');
        $temp=$this->tokenPath.'.tmp';if(file_put_contents($temp,json_encode($token,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX)===false)throw new RuntimeException('Could not store the Google OAuth token.');
        @chmod($temp,0600);if(!rename($temp,$this->tokenPath))throw new RuntimeException('Could not activate the Google OAuth token.');@chmod($this->tokenPath,0600);
    }
}
