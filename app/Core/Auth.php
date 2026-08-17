<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $pdo=Database::connection();
        $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
        $emailHash=hash('sha256',strtolower(trim($email)));
        $cleanup=$pdo->prepare('DELETE FROM bdc_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        $cleanup->execute();
        $limit=$pdo->prepare('SELECT COUNT(*) FROM bdc_login_attempts WHERE ip_address=:ip AND email_hash=:email AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $limit->execute(['ip'=>$ip,'email'=>$emailHash]);
        if((int)$limit->fetchColumn()>=5){self::audit(null,'login_rate_limited',[]);return false;}
        $stmt = Database::connection()->prepare('SELECT id,email,password_hash,full_name,role,status FROM bdc_users WHERE email=:email LIMIT 1');
        $stmt->execute(['email'=>strtolower(trim($email))]);
        $user=$stmt->fetch();
        if (!$user || $user['status'] !== 'active' || !password_verify($password,$user['password_hash'])) {
            $pdo->prepare('INSERT INTO bdc_login_attempts(ip_address,email_hash) VALUES(:ip,:email)')->execute(['ip'=>$ip,'email'=>$emailHash]);
            self::audit(null,'login_failed',['email_hash'=>$emailHash]); return false;
        }
        if (in_array((string)$user['role'],['super_admin','admin','master_scorer','scorer'],true) && !self::trustedDevice((int)$user['id'])) {
            $code=(string)random_int(100000,999999);
            $pdo->prepare('DELETE FROM bdc_two_factor_codes WHERE user_id=:u OR expires_at<NOW()')->execute(['u'=>$user['id']]);
            $pdo->prepare('INSERT INTO bdc_two_factor_codes(user_id,code_hash,expires_at) VALUES(:u,:h,DATE_ADD(NOW(),INTERVAL 10 MINUTE))')->execute(['u'=>$user['id'],'h'=>hash('sha256',$code)]);
            $_SESSION['pending_2fa_user']=['id'=>(int)$user['id'],'email'=>$user['email'],'full_name'=>$user['full_name'],'role'=>$user['role']];
            @mail((string)$user['email'],'BDC login verification code',"Your BDC verification code is {$code}. It expires in 10 minutes.","From: no-reply@bachatadancecouncil.com\r\nContent-Type: text/plain; charset=UTF-8");
            self::audit((int)$user['id'],'two_factor_code_sent',[]);return true;
        }
        self::completeLogin($user);
        $pdo->prepare('DELETE FROM bdc_login_attempts WHERE ip_address=:ip AND email_hash=:email')->execute(['ip'=>$ip,'email'=>$emailHash]);
        Database::connection()->prepare('UPDATE bdc_users SET last_login_at=NOW() WHERE id=:id')->execute(['id'=>$user['id']]);
        self::audit((int)$user['id'],'login_success',[]); return true;
    }

    public static function pendingTwoFactor(): bool { return !empty($_SESSION['pending_2fa_user']); }
    public static function verifyTwoFactor(string $code,bool $remember):bool
    {
        $user=$_SESSION['pending_2fa_user']??null;if(!$user||!preg_match('/^\d{6}$/',$code))return false;
        $pdo=Database::connection();$s=$pdo->prepare('SELECT * FROM bdc_two_factor_codes WHERE user_id=:u AND used_at IS NULL AND expires_at>=NOW() ORDER BY id DESC LIMIT 1');$s->execute(['u'=>$user['id']]);$row=$s->fetch();
        if(!$row||!hash_equals((string)$row['code_hash'],hash('sha256',$code))){if($row)$pdo->prepare('UPDATE bdc_two_factor_codes SET attempts=attempts+1 WHERE id=:id')->execute(['id'=>$row['id']]);return false;}
        $pdo->prepare('UPDATE bdc_two_factor_codes SET used_at=NOW() WHERE id=:id')->execute(['id'=>$row['id']]);unset($_SESSION['pending_2fa_user']);self::completeLogin($user);
        if($remember)self::rememberDevice((int)$user['id']);self::audit((int)$user['id'],'two_factor_verified',['remembered'=>$remember]);return true;
    }
    private static function completeLogin(array $user):void{session_regenerate_id(true);$_SESSION['user']=['id'=>(int)$user['id'],'email'=>$user['email'],'full_name'=>$user['full_name'],'role'=>$user['role']];$_SESSION['last_activity']=time();}
    private static function trustedDevice(int $userId):bool
    {
        $cookie=(string)($_COOKIE['bdc_trusted_device']??'');if(!str_contains($cookie,':'))return false;[$selector,$token]=explode(':',$cookie,2);
        $s=Database::connection()->prepare('SELECT * FROM bdc_trusted_devices WHERE user_id=:u AND selector=:s AND revoked_at IS NULL AND expires_at>=NOW()');$s->execute(['u'=>$userId,'s'=>$selector]);$row=$s->fetch();
        $valid=$row&&hash_equals((string)$row['token_hash'],hash('sha256',$token))&&hash_equals((string)$row['user_agent_hash'],hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??'')));
        if($valid)Database::connection()->prepare('UPDATE bdc_trusted_devices SET last_used_at=NOW() WHERE id=:id')->execute(['id'=>$row['id']]);return(bool)$valid;
    }
    private static function rememberDevice(int $userId):void
    {
        $selector=bin2hex(random_bytes(12));$token=bin2hex(random_bytes(32));Database::connection()->prepare('INSERT INTO bdc_trusted_devices(user_id,selector,token_hash,user_agent_hash,expires_at) VALUES(:u,:s,:t,:a,DATE_ADD(NOW(),INTERVAL 30 DAY))')->execute(['u'=>$userId,'s'=>$selector,'t'=>hash('sha256',$token),'a'=>hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??''))]);
        setcookie('bdc_trusted_device',$selector.':'.$token,['expires'=>time()+2592000,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
    }

    public static function check(): bool
    {
        if (empty($_SESSION['user'])) return false;
        $timeout=(int)Config::get('security.session_timeout_minutes',120)*60;
        if (!empty($_SESSION['last_activity']) && time()-(int)$_SESSION['last_activity']>$timeout) { self::logout(); return false; }
        $_SESSION['last_activity']=time(); return true;
    }

    public static function requireAdmin(): void
    {
        if (!self::check() || !in_array((string)($_SESSION['user']['role']??''),['admin','super_admin','master_scorer','scorer'],true)) {
            header('Location: '.url('admin/')); exit;
        }
    }

    public static function isSuperAdmin(): bool
    {
        return self::check() && (string)(self::user()['role'] ?? '') === 'super_admin';
    }

    public static function canViewPastScores(): bool
    {
        return self::check() && in_array((string)(self::user()['role'] ?? ''),['super_admin','admin','master_scorer'],true);
    }

    public static function canOverrideCompletedScores(): bool
    {
        return self::check() && in_array((string)(self::user()['role'] ?? ''),['super_admin','master_scorer','scorer'],true);
    }

    public static function requireSuperAdmin(): void
    {
        self::requireAdmin();
        if (!self::isSuperAdmin()) {
            http_response_code(403);
            exit('Only the Super Admin can manage admin users.');
        }
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) return false;
        $user=self::user();
        if (($user['role']??'')==='super_admin') return true;
        if (!in_array(($user['role']??''),['admin','master_scorer','scorer'],true)) return false;
        try {
            $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM bdc_user_permissions WHERE user_id=:uid AND permission_key=:p AND allowed=1');
            $stmt->execute(['uid'=>$user['id'],'p'=>$permission]);
            if ((int)$stmt->fetchColumn()>0) return true;
            // Safe default for existing admins during upgrade.
            return in_array($permission,['competitors.view','competitors.edit','transactions.edit','points.adjust.request','leaderboard.view','registrations.manage'],true);
        } catch (\Throwable) {
            return in_array($permission,['competitors.view','competitors.edit','transactions.edit','points.adjust.request','leaderboard.view','registrations.manage'],true);
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireAdmin();
        if (!self::can($permission)) { http_response_code(403); exit('You do not have permission to access this page.'); }
    }

    public static function user(): ?array { return self::check()?$_SESSION['user']:null; }
    public static function logout(): void { $id=$_SESSION['user']['id']??null; if($id) self::audit((int)$id,'logout',[]); $_SESSION=[]; session_destroy(); }

    public static function audit(?int $userId,string $action,array $details,string $entityType='authentication',?int $entityId=null): void
    {
        try {
            $stmt=Database::connection()->prepare('INSERT INTO bdc_audit_logs(user_id,action,entity_type,entity_id,details_json,ip_address,created_at) VALUES(:uid,:a,:et,:eid,:d,:ip,NOW())');
            $stmt->execute(['uid'=>$userId,'a'=>$action,'et'=>$entityType,'eid'=>$entityId,'d'=>json_encode($details,JSON_UNESCAPED_UNICODE),'ip'=>$_SERVER['REMOTE_ADDR']??null]);
        } catch (\Throwable) {}
    }
}
