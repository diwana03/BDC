<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class HtmlSnapshotToken
{
    private const SETTING_KEY='html_snapshot_secret';

    private static function secret(PDO $pdo):string
    {
        $stmt=$pdo->prepare("SELECT setting_value FROM bdc_settings WHERE setting_key=:key LIMIT 1");
        $stmt->execute(['key'=>self::SETTING_KEY]);
        $secret=(string)$stmt->fetchColumn();

        if($secret===''){
            $secret=bin2hex(random_bytes(32));
            $pdo->prepare("
                INSERT INTO bdc_settings(setting_key,setting_value)
                VALUES(:key,:value)
                ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)
            ")->execute([
                'key'=>self::SETTING_KEY,
                'value'=>$secret,
            ]);
        }

        return $secret;
    }

    public static function issue(PDO $pdo,string $type,int $roundId,int $ttl=300):array
    {
        $expires=time()+max(60,$ttl);
        $payload=$type.'|'.$roundId.'|'.$expires;
        $token=hash_hmac('sha256',$payload,self::secret($pdo));

        return [
            'round_id'=>$roundId,
            'snapshot_type'=>$type,
            'snapshot_expires'=>$expires,
            'snapshot_token'=>$token,
        ];
    }

    public static function verify(PDO $pdo,string $type,int $roundId,array $query):bool
    {
        $expires=(int)($query['snapshot_expires']??0);
        $token=(string)($query['snapshot_token']??'');

        if($token==='' || $expires<time() || $expires>time()+900){
            return false;
        }

        $payload=$type.'|'.$roundId.'|'.$expires;
        $expected=hash_hmac('sha256',$payload,self::secret($pdo));

        return hash_equals($expected,$token);
    }
}
