<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class ScoringRosterCheckpointService
{
    public static function ensure(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_roster_checkpoints(
            data_mode ENUM('real','test') NOT NULL,
            round_id BIGINT UNSIGNED NOT NULL,
            status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
            snapshot_hash CHAR(64) NULL,
            saved_at DATETIME NULL,
            saved_by BIGINT UNSIGNED NULL,
            submitted_at DATETIME NULL,
            submitted_by BIGINT UNSIGNED NULL,
            reopened_at DATETIME NULL,
            reopened_by BIGINT UNSIGNED NULL,
            reopen_reason VARCHAR(500) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(data_mode,round_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function state(PDO $pdo,int $roundId,bool $test=false):array
    {
        self::ensure($pdo);
        $stmt=$pdo->prepare('SELECT * FROM bdc_scoring_roster_checkpoints WHERE data_mode=:mode AND round_id=:round LIMIT 1');
        $stmt->execute(['mode'=>$test?'test':'real','round'=>$roundId]);
        return $stmt->fetch()?:['data_mode'=>$test?'test':'real','round_id'=>$roundId,'status'=>'draft','saved_at'=>null,'submitted_at'=>null];
    }

    public static function assertEditable(PDO $pdo,int $roundId,bool $test=false):void
    {
        if((string)self::state($pdo,$roundId,$test)['status']==='submitted'){
            throw new RuntimeException('Competitors are submitted and locked. Reopen the competitor roster before changing entries or bibs.');
        }
    }

    public static function checkpoint(PDO $pdo,int $roundId,int $userId,string $action,bool $test=false,string $reason=''):array
    {
        self::ensure($pdo);
        $mode=$test?'test':'real';
        if($action==='reopen'){
            $reason=trim($reason);
            if($reason==='')throw new RuntimeException('Enter a reason to reopen the submitted competitors.');
            $pdo->prepare("INSERT INTO bdc_scoring_roster_checkpoints(data_mode,round_id,status,reopened_at,reopened_by,reopen_reason) VALUES(:mode,:round,'draft',NOW(),:user,:reason) ON DUPLICATE KEY UPDATE status='draft',reopened_at=NOW(),reopened_by=VALUES(reopened_by),reopen_reason=VALUES(reopen_reason)")
                ->execute(['mode'=>$mode,'round'=>$roundId,'user'=>$userId?:null,'reason'=>$reason]);
            return self::state($pdo,$roundId,$test);
        }
        self::assertEditable($pdo,$roundId,$test);
        if(!in_array($action,['save','submit'],true))throw new RuntimeException('Invalid competitor checkpoint action.');
        $entryTable=$test?'bdc_test_scoring_entries':'bdc_scoring_entries';
        $stmt=$pdo->prepare("SELECT competitor_id,dance_role,bib_number,display_name FROM {$entryTable} WHERE round_id=:round AND entry_status='active' ORDER BY dance_role,bib_number,competitor_id");
        $stmt->execute(['round'=>$roundId]);$entries=$stmt->fetchAll();
        if($action==='submit'){
            foreach(['leader','follower'] as $role){
                if(!array_filter($entries,static fn(array $entry):bool=>(string)$entry['dance_role']===$role))throw new RuntimeException('Add at least one '.ucfirst($role).' before submitting competitors.');
            }
            $identities=[];$bibs=[];
            foreach($entries as $entry){
                $competitor=(int)$entry['competitor_id'];$role=(string)$entry['dance_role'];$bib=(int)$entry['bib_number'];
                if(isset($identities[$competitor]))throw new RuntimeException($entry['display_name'].' appears more than once in this round. Remove the duplicate before submission.');
                if($bib<1)throw new RuntimeException($entry['display_name'].' needs a valid bib number.');
                if(isset($bibs[$role][$bib]))throw new RuntimeException('Bib '.$bib.' is duplicated in the '.ucfirst($role).' roster.');
                $identities[$competitor]=true;$bibs[$role][$bib]=true;
            }
        }
        $hash=hash('sha256',json_encode($entries,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $status=$action==='submit'?'submitted':'draft';
        $sql="INSERT INTO bdc_scoring_roster_checkpoints(data_mode,round_id,status,snapshot_hash,saved_at,saved_by,submitted_at,submitted_by) VALUES(:mode,:round,:status,:hash,NOW(),:user,".($action==='submit'?'NOW()':'NULL').",".($action==='submit'?':submitter':'NULL').") ON DUPLICATE KEY UPDATE status=VALUES(status),snapshot_hash=VALUES(snapshot_hash),saved_at=NOW(),saved_by=VALUES(saved_by),submitted_at=VALUES(submitted_at),submitted_by=VALUES(submitted_by)";
        $params=['mode'=>$mode,'round'=>$roundId,'status'=>$status,'hash'=>$hash,'user'=>$userId?:null];
        if($action==='submit')$params['submitter']=$userId?:null;
        $pdo->prepare($sql)->execute($params);
        return self::state($pdo,$roundId,$test);
    }
}
