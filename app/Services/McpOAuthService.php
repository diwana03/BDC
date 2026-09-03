<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class McpOAuthService
{
    public const READ_SCOPE='bdc.events.read';
    public const STAGE_SCOPE='bdc.events.stage';

    public static function ensure(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_mcp_oauth_clients(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,client_id VARCHAR(191) NOT NULL,client_name VARCHAR(190) NOT NULL,redirect_uris_json TEXT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE INDEX uq_bdc_mcp_client(client_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_mcp_oauth_codes(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,code_hash CHAR(64) NOT NULL,client_id VARCHAR(191) NOT NULL,user_id BIGINT UNSIGNED NOT NULL,redirect_uri VARCHAR(1000) NOT NULL,scope VARCHAR(255) NOT NULL,code_challenge VARCHAR(128) NOT NULL,expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE INDEX uq_bdc_mcp_code(code_hash),INDEX idx_bdc_mcp_code_expiry(expires_at),CONSTRAINT fk_bdc_mcp_code_user FOREIGN KEY(user_id) REFERENCES bdc_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_mcp_oauth_tokens(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,access_hash CHAR(64) NOT NULL,refresh_hash CHAR(64) NULL,client_id VARCHAR(191) NOT NULL,user_id BIGINT UNSIGNED NOT NULL,scope VARCHAR(255) NOT NULL,access_expires_at DATETIME NOT NULL,refresh_expires_at DATETIME NULL,revoked_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE INDEX uq_bdc_mcp_access(access_hash),UNIQUE INDEX uq_bdc_mcp_refresh(refresh_hash),INDEX idx_bdc_mcp_token_user(user_id,revoked_at),CONSTRAINT fk_bdc_mcp_token_user FOREIGN KEY(user_id) REFERENCES bdc_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function registerClient(PDO $pdo,array $input):array
    {
        self::ensure($pdo);$uris=$input['redirect_uris']??null;
        if(!is_array($uris)||$uris===[]||count($uris)>10)throw new RuntimeException('redirect_uris must contain between 1 and 10 HTTPS URLs.');
        $clean=[];foreach($uris as $uri){$uri=trim((string)$uri);$parts=parse_url($uri);if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['fragment']))throw new RuntimeException('Every redirect URI must be an absolute HTTPS URL without a fragment.');$clean[]=$uri;}
        $clean=array_values(array_unique($clean));$clientId='bdc_'.bin2hex(random_bytes(24));$name=substr(trim((string)($input['client_name']??'ChatGPT')),0,190)?:'ChatGPT';
        $pdo->prepare('INSERT INTO bdc_mcp_oauth_clients(client_id,client_name,redirect_uris_json) VALUES(:id,:name,:uris)')->execute(['id'=>$clientId,'name'=>$name,'uris'=>json_encode($clean,JSON_UNESCAPED_SLASHES)]);
        return ['client_id'=>$clientId,'client_name'=>$name,'redirect_uris'=>$clean,'token_endpoint_auth_method'=>'none','grant_types'=>['authorization_code','refresh_token'],'response_types'=>['code']];
    }

    public static function client(PDO $pdo,string $clientId,string $redirectUri):?array
    {
        self::ensure($pdo);$s=$pdo->prepare('SELECT * FROM bdc_mcp_oauth_clients WHERE client_id=:id');$s->execute(['id'=>$clientId]);$row=$s->fetch();if(!$row)return null;$uris=json_decode((string)$row['redirect_uris_json'],true);return is_array($uris)&&in_array($redirectUri,$uris,true)?$row:null;
    }

    public static function issueCode(PDO $pdo,string $clientId,int $userId,string $redirectUri,string $scope,string $challenge):string
    {
        if(!preg_match('/^[A-Za-z0-9\-._~]{43,128}$/',$challenge))throw new RuntimeException('A valid S256 code_challenge is required.');
        $scope=self::scope($scope);$code=self::randomToken();$pdo->prepare("INSERT INTO bdc_mcp_oauth_codes(code_hash,client_id,user_id,redirect_uri,scope,code_challenge,expires_at) VALUES(:hash,:client,:user,:redirect,:scope,:challenge,DATE_ADD(NOW(),INTERVAL 5 MINUTE))")->execute(['hash'=>hash('sha256',$code),'client'=>$clientId,'user'=>$userId,'redirect'=>$redirectUri,'scope'=>$scope,'challenge'=>$challenge]);return $code;
    }

    public static function exchangeCode(PDO $pdo,string $code,string $clientId,string $redirectUri,string $verifier):array
    {
        self::ensure($pdo);$pdo->beginTransaction();try{$s=$pdo->prepare('SELECT * FROM bdc_mcp_oauth_codes WHERE code_hash=:hash AND client_id=:client AND redirect_uri=:redirect AND used_at IS NULL AND expires_at>=NOW() FOR UPDATE');$s->execute(['hash'=>hash('sha256',$code),'client'=>$clientId,'redirect'=>$redirectUri]);$row=$s->fetch();$challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');if(!$row||!hash_equals((string)$row['code_challenge'],$challenge))throw new RuntimeException('Invalid or expired authorization code.');$pdo->prepare('UPDATE bdc_mcp_oauth_codes SET used_at=NOW() WHERE id=:id')->execute(['id'=>$row['id']]);$tokens=self::createTokens($pdo,(string)$row['client_id'],(int)$row['user_id'],(string)$row['scope']);$pdo->commit();return $tokens;}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function refresh(PDO $pdo,string $refresh,string $clientId):array
    {
        self::ensure($pdo);$pdo->beginTransaction();try{$s=$pdo->prepare('SELECT * FROM bdc_mcp_oauth_tokens WHERE refresh_hash=:hash AND client_id=:client AND revoked_at IS NULL AND refresh_expires_at>=NOW() FOR UPDATE');$s->execute(['hash'=>hash('sha256',$refresh),'client'=>$clientId]);$row=$s->fetch();if(!$row)throw new RuntimeException('Invalid or expired refresh token.');$pdo->prepare('UPDATE bdc_mcp_oauth_tokens SET revoked_at=NOW() WHERE id=:id')->execute(['id'=>$row['id']]);$tokens=self::createTokens($pdo,$clientId,(int)$row['user_id'],(string)$row['scope']);$pdo->commit();return $tokens;}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function authenticate(PDO $pdo,string $bearer,string $requiredScope):?array
    {
        self::ensure($pdo);if($bearer==='')return null;$s=$pdo->prepare('SELECT t.user_id,t.scope,u.email,u.full_name,u.role,u.status FROM bdc_mcp_oauth_tokens t JOIN bdc_users u ON u.id=t.user_id WHERE t.access_hash=:hash AND t.revoked_at IS NULL AND t.access_expires_at>=NOW() LIMIT 1');$s->execute(['hash'=>hash('sha256',$bearer)]);$row=$s->fetch();if(!$row||$row['status']!=='active'||$row['role']!=='super_admin'||!in_array($requiredScope,preg_split('/\s+/',trim((string)$row['scope']))?:[],true))return null;return $row;
    }

    private static function createTokens(PDO $pdo,string $clientId,int $userId,string $scope):array
    {$access=self::randomToken();$refresh=self::randomToken();$pdo->prepare("INSERT INTO bdc_mcp_oauth_tokens(access_hash,refresh_hash,client_id,user_id,scope,access_expires_at,refresh_expires_at) VALUES(:access,:refresh,:client,:user,:scope,DATE_ADD(NOW(),INTERVAL 1 HOUR),DATE_ADD(NOW(),INTERVAL 30 DAY))")->execute(['access'=>hash('sha256',$access),'refresh'=>hash('sha256',$refresh),'client'=>$clientId,'user'=>$userId,'scope'=>$scope]);return ['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>3600,'refresh_token'=>$refresh,'scope'=>$scope];}
    private static function scope(string $scope):string{$requested=preg_split('/\s+/',trim($scope))?:[];$allowed=[self::READ_SCOPE,self::STAGE_SCOPE];$clean=array_values(array_intersect($allowed,$requested?:$allowed));if(!in_array(self::READ_SCOPE,$clean,true))$clean[] = self::READ_SCOPE;return implode(' ',$clean);}
    private static function randomToken():string{return rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');}
}
