<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class ScoringFlightService
{
    private static function tables(bool $test): array
    {
        $prefix=$test?'bdc_test_scoring_':'bdc_scoring_';
        return ['rounds'=>$prefix.'rounds','entries'=>$prefix.'entries','pairs'=>$prefix.'final_pairs','judges'=>$prefix.'judges','marks'=>$prefix.'marks','final_marks'=>$prefix.'final_marks','sessions'=>$prefix.'judge_sessions','audit'=>$prefix.'audit','settings'=>$prefix.'flight_settings','assignments'=>$prefix.'flight_assignments'];
    }

    public static function ensure(PDO $pdo,bool $test): void
    {
        $t=self::tables($test);
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$t['settings']}(round_id BIGINT UNSIGNED PRIMARY KEY,flight_size INT UNSIGNED NOT NULL DEFAULT 10,flight_count INT UNSIGNED NOT NULL DEFAULT 0,active_flight INT UNSIGNED NOT NULL DEFAULT 1,is_confirmed TINYINT(1) NOT NULL DEFAULT 0,confirmed_at DATETIME NULL,confirmed_by BIGINT UNSIGNED NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$t['assignments']}(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,round_id BIGINT UNSIGNED NOT NULL,subject_type ENUM('entry','pair') NOT NULL,subject_id BIGINT UNSIGNED NOT NULL,dance_role VARCHAR(16) NULL,flight_number INT UNSIGNED NOT NULL,position_number INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE INDEX uq_scoring_flight_subject(round_id,subject_type,subject_id),INDEX idx_scoring_flight_number(round_id,flight_number,position_number)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function scoringStarted(PDO $pdo,int $roundId,bool $test): bool
    {
        $t=self::tables($test);
        foreach([$t['marks'],$t['final_marks']] as $table){$q=$pdo->prepare("SELECT 1 FROM {$table} WHERE round_id=:r LIMIT 1");$q->execute(['r'=>$roundId]);if($q->fetchColumn())return true;}
        $q=$pdo->prepare("SELECT 1 FROM {$t['sessions']} WHERE round_id=:r AND status IN('scoring','submitted') LIMIT 1");$q->execute(['r'=>$roundId]);return (bool)$q->fetchColumn();
    }

    public static function generate(PDO $pdo,int $roundId,bool $test,int $flightSize,int $userId,bool $override=false,string $reason=''): array
    {
        self::ensure($pdo,$test);$t=self::tables($test);$flightSize=max(1,min(50,$flightSize));
        $roundStmt=$pdo->prepare("SELECT round_type,status FROM {$t['rounds']} WHERE id=:r");$roundStmt->execute(['r'=>$roundId]);$round=$roundStmt->fetch();if(!$round)throw new RuntimeException('Scoring round not found.');
        $started=self::scoringStarted($pdo,$roundId,$test);
        if($started&&!$override)throw new RuntimeException('Flight assignments are locked because scoring has started.');
        if($started&&($reason=trim($reason))==='')throw new RuntimeException('Enter a reason before rebuilding locked flights.');
        if($started)ScoringBackupService::create($pdo,$roundId,$test,$userId,'manual','flight_rebuild','Safety checkpoint before rebuilding flights · '.date('H:i:s'));
        $subjects=[];$isFinal=(string)$round['round_type']==='final';
        if($isFinal){
            $q=$pdo->prepare("SELECT p.id subject_id,'pair' subject_type,NULL dance_role,l.bib_number primary_bib,f.bib_number secondary_bib FROM {$t['pairs']} p JOIN {$t['entries']} l ON l.id=p.leader_entry_id LEFT JOIN {$t['entries']} f ON f.id=p.follower_entry_id WHERE p.round_id=:r AND p.pairing_status='confirmed' ORDER BY l.bib_number IS NULL,l.bib_number,f.bib_number IS NULL,f.bib_number,p.id");$q->execute(['r'=>$roundId]);$rows=$q->fetchAll();foreach($rows as $index=>$row){$row['flight_number']=intdiv($index,$flightSize)+1;$row['position_number']=($index%$flightSize)+1;$subjects[]=$row;}
        }else{
            $q=$pdo->prepare("SELECT id subject_id,'entry' subject_type,dance_role,bib_number primary_bib,NULL secondary_bib FROM {$t['entries']} WHERE round_id=:r AND entry_status='active' ORDER BY dance_role,bib_number IS NULL,bib_number,id");
            $q->execute(['r'=>$roundId]);
            $byRole=['leader'=>[],'follower'=>[]];
            foreach($q->fetchAll() as $row)$byRole[(string)$row['dance_role']][]=$row;
            $largestRole=max(array_map('count',$byRole));
            $balancedFlightCount=max(1,(int)ceil($largestRole/$flightSize));
            foreach($byRole as $rows){
                $roleCount=count($rows);
                $base=intdiv($roleCount,$balancedFlightCount);
                $remainder=$roleCount%$balancedFlightCount;
                $offset=0;
                for($flightNumber=1;$flightNumber<=$balancedFlightCount;$flightNumber++){
                    $roundSize=$base+($flightNumber<=$remainder?1:0);
                    foreach(array_slice($rows,$offset,$roundSize) as $position=>$row){
                        $row['flight_number']=$flightNumber;
                        $row['position_number']=$position+1;
                        $subjects[]=$row;
                    }
                    $offset+=$roundSize;
                }
            }
        }
        if(!$subjects)throw new RuntimeException($isFinal?'Confirm Final couples before creating flights.':'Add active competitors before creating flights.');
        $flightCount=max(array_map(static fn(array $x):int=>(int)$x['flight_number'],$subjects));
        $pdo->beginTransaction();try{$pdo->prepare("DELETE FROM {$t['assignments']} WHERE round_id=:r")->execute(['r'=>$roundId]);$add=$pdo->prepare("INSERT INTO {$t['assignments']}(round_id,subject_type,subject_id,dance_role,flight_number,position_number) VALUES(:r,:type,:subject,:role,:flight,:position)");foreach($subjects as $row)$add->execute(['r'=>$roundId,'type'=>$row['subject_type'],'subject'=>$row['subject_id'],'role'=>$row['dance_role'],'flight'=>$row['flight_number'],'position'=>$row['position_number']]);$pdo->prepare("INSERT INTO {$t['settings']}(round_id,flight_size,flight_count,active_flight,is_confirmed,confirmed_at,confirmed_by) VALUES(:r,:size,:count,1,1,NOW(),:user) ON DUPLICATE KEY UPDATE flight_size=VALUES(flight_size),flight_count=VALUES(flight_count),active_flight=LEAST(GREATEST(active_flight,1),VALUES(flight_count)),is_confirmed=1,confirmed_at=NOW(),confirmed_by=VALUES(confirmed_by)")->execute(['r'=>$roundId,'size'=>$flightSize,'count'=>$flightCount,'user'=>$userId?:null]);$pdo->prepare("INSERT INTO {$t['audit']}(round_id,user_id,action,details_json) VALUES(:round,:user,'flight_assignments_generated',:details)")->execute(['round'=>$roundId,'user'=>$userId?:null,'details'=>json_encode(['flight_size'=>$flightSize,'flight_count'=>$flightCount,'subjects'=>count($subjects),'round_type'=>$round['round_type'],'override'=>$started,'reason'=>$reason],JSON_UNESCAPED_SLASHES)]);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return self::summary($pdo,$roundId,$test);
    }

    public static function setActive(PDO $pdo,int $roundId,bool $test,int $flight): array
    {
        self::ensure($pdo,$test);$t=self::tables($test);$summary=self::summary($pdo,$roundId,$test);$count=(int)$summary['flight_count'];if($flight<1||$flight>$count)throw new RuntimeException('Selected flight is not available.');$pdo->prepare("UPDATE {$t['settings']} SET active_flight=:f WHERE round_id=:r")->execute(['f'=>$flight,'r'=>$roundId]);return self::summary($pdo,$roundId,$test);
    }

    public static function summary(PDO $pdo,int $roundId,bool $test): array
    {
        self::ensure($pdo,$test);$t=self::tables($test);$q=$pdo->prepare("SELECT * FROM {$t['settings']} WHERE round_id=:r");$q->execute(['r'=>$roundId]);$settings=$q->fetch()?:['flight_size'=>10,'flight_count'=>0,'active_flight'=>1,'is_confirmed'=>0];$a=$pdo->prepare("SELECT flight_number,dance_role,COUNT(*) total,MIN(position_number) first_position,MAX(position_number) last_position FROM {$t['assignments']} WHERE round_id=:r GROUP BY flight_number,dance_role ORDER BY flight_number,dance_role");$a->execute(['r'=>$roundId]);$flights=[];foreach($a->fetchAll() as $row){$n=(int)$row['flight_number'];$flights[$n]['number']=$n;$flights[$n]['roles'][(string)($row['dance_role']??'couple')]=(int)$row['total'];}return $settings+['flights'=>array_values($flights),'locked'=>self::scoringStarted($pdo,$roundId,$test)];
    }

    public static function assignmentMap(PDO $pdo,int $roundId,bool $test,string $subjectType): array
    {
        self::ensure($pdo,$test);$t=self::tables($test);$q=$pdo->prepare("SELECT subject_id,flight_number,position_number FROM {$t['assignments']} WHERE round_id=:r AND subject_type=:type");$q->execute(['r'=>$roundId,'type'=>$subjectType]);$map=[];foreach($q->fetchAll() as $row)$map[(int)$row['subject_id']]=['flight'=>(int)$row['flight_number'],'position'=>(int)$row['position_number']];return $map;
    }
}
