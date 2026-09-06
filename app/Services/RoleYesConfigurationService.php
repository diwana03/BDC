<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class RoleYesConfigurationService
{
    public static function ensure(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_role_yes_settings (
            round_id BIGINT UNSIGNED NOT NULL,
            dance_role VARCHAR(16) NOT NULL,
            yes_count INT UNSIGNED NOT NULL,
            locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            locked_by BIGINT UNSIGNED NULL,
            PRIMARY KEY(round_id,dance_role),
            CONSTRAINT fk_bdc_role_yes_round FOREIGN KEY(round_id) REFERENCES bdc_scoring_rounds(id) ON DELETE CASCADE,
            INDEX idx_bdc_role_yes_locked_by(locked_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** @return array{tier:int,yes:int} */
    public static function recommended(int $count,int $fallbackYes=10):array
    {
        $count=max(0,$count);
        if($count>=31)return ['tier'=>3,'yes'=>15];
        if($count>=16)return ['tier'=>2,'yes'=>10];
        if($count>=5)return ['tier'=>1,'yes'=>5];
        return ['tier'=>0,'yes'=>min(max(1,$fallbackYes),max(1,$count))];
    }

    /** @return array{leader:int,follower:int} */
    public static function counts(PDO $pdo,int $roundId):array
    {
        $stmt=$pdo->prepare("SELECT dance_role,COUNT(*) total FROM bdc_scoring_entries WHERE round_id=:round AND entry_status='active' GROUP BY dance_role");
        $stmt->execute(['round'=>$roundId]);$counts=['leader'=>0,'follower'=>0];
        foreach($stmt->fetchAll() as $row){$role=(string)$row['dance_role'];if(isset($counts[$role]))$counts[$role]=(int)$row['total'];}
        return $counts;
    }

    /** @return array<string,array{count:int,tier:int,yes:int,saved:bool}> */
    public static function resolve(PDO $pdo,int $roundId,int $legacyYes):array
    {
        self::ensure($pdo);$counts=self::counts($pdo,$roundId);$saved=[];
        $stmt=$pdo->prepare('SELECT dance_role,yes_count FROM bdc_scoring_role_yes_settings WHERE round_id=:round');$stmt->execute(['round'=>$roundId]);
        foreach($stmt->fetchAll() as $row)$saved[(string)$row['dance_role']]=(int)$row['yes_count'];
        $result=[];
        foreach(['leader','follower'] as $role){
            $recommend=self::recommended($counts[$role],$legacyYes);
            $has=array_key_exists($role,$saved);
            // Backward compatibility: old rounds keep their shared yes_count until role settings are explicitly saved.
            $yes=$has?max(1,$saved[$role]):max(1,$legacyYes);
            $result[$role]=['count'=>$counts[$role],'tier'=>$recommend['tier'],'yes'=>$yes,'saved'=>$has];
        }
        return $result;
    }

    public static function saveAndLock(PDO $pdo,int $roundId,int $leaderYes,int $followerYes,?int $userId):void
    {
        self::ensure($pdo);
        foreach([$leaderYes,$followerYes] as $yes)if(!in_array($yes,[5,10,15],true))throw new \RuntimeException('Select 5, 10 or 15 YES for each role.');
        $stmt=$pdo->prepare("INSERT INTO bdc_scoring_role_yes_settings(round_id,dance_role,yes_count,locked_at,locked_by) VALUES(:round,:role,:yes,NOW(),:user) ON DUPLICATE KEY UPDATE yes_count=VALUES(yes_count),locked_at=NOW(),locked_by=VALUES(locked_by)");
        $stmt->execute(['round'=>$roundId,'role'=>'leader','yes'=>$leaderYes,'user'=>$userId]);
        $stmt->execute(['round'=>$roundId,'role'=>'follower','yes'=>$followerYes,'user'=>$userId]);
    }
}
