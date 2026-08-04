<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ScoringTestEngine
{
    public static function randomHeatsMarks(array $competitors,array $judges):array
    {
        $marks=[];
        foreach($competitors as $competitor){
            foreach($judges as $judge){
                $roll=random_int(1,100);
                $value=$roll<=35?'YES':($roll<=55?'A1':($roll<=72?'A2':($roll<=85?'A3':'')));
                $marks[(int)$competitor['test_id']][(int)$judge['test_id']]=$value;
            }
        }
        return $marks;
    }

    public static function calculateHeats(
        array $competitors,
        array $judges,
        array $marks,
        int $callbackCount
    ):array{
        $weights=['YES'=>10.0,'A1'=>4.5,'A2'=>4.3,'A3'=>4.2,''=>0.0];
        $chiefId=0;
        foreach($judges as $judge){
            if(!empty($judge['is_chief'])){$chiefId=(int)$judge['test_id'];break;}
        }

        $rows=[];
        foreach($competitors as $competitor){
            $total=0.0;
            $chief=0.0;
            $yes=0;
            foreach($judges as $judge){$scope=(string)($judge['scoring_scope']??'all');if($scope!=='all'&&$scope!==$competitor['role'])continue;$value=strtoupper(trim((string)($marks[(int)$competitor['test_id']][(int)$judge['test_id']]??'')));if(!array_key_exists($value,$weights))$value='';$score=$weights[$value];$total+=$score;if($value==='YES')$yes++;if((int)$judge['test_id']===$chiefId)$chief=$score;}
            $rows[]=[
                'test_id'=>(int)$competitor['test_id'],
                'name'=>$competitor['name'],
                'role'=>$competitor['role'],
                'total_score'=>$total,
                'chief_score'=>$chief,
                'yes_count'=>$yes,
            ];
        }

        $result=[];
        foreach(['leader','follower'] as $role){
            $roleRows=array_values(array_filter($rows,fn(array $r):bool=>$r['role']===$role));
            usort($roleRows,function(array $a,array $b):int{
                if(abs($a['total_score']-$b['total_score'])>0.0001)return $b['total_score']<=>$a['total_score'];
                if(abs($a['chief_score']-$b['chief_score'])>0.0001)return $b['chief_score']<=>$a['chief_score'];
                if($a['yes_count']!==$b['yes_count'])return $b['yes_count']<=>$a['yes_count'];
                return $a['test_id']<=>$b['test_id'];
            });

            foreach($roleRows as $index=>&$row){
                $row['rank']=$index+1;
                $row['status']=$row['rank']<=$callbackCount?'Callback':($row['rank']<=$callbackCount+3?'Alternate':'Eliminated');
            }
            unset($row);
            $result=array_merge($result,$roleRows);
        }
        return $result;
    }

    public static function randomFinalMarks(array $pairs,array $judges):array
    {
        $marks=[];
        $pairIds=array_column($pairs,'test_id');
        foreach($judges as $judge){
            $ranks=range(1,count($pairs));
            shuffle($ranks);
            foreach($pairIds as $index=>$pairId){
                $marks[(int)$pairId][(int)$judge['test_id']]=$ranks[$index];
            }
        }
        return $marks;
    }

    public static function validateFinalMarks(array $pairs,array $judges,array $marks):void
    {
        $pairCount=count($pairs);
        if($pairCount<2)throw new RuntimeException('At least two Final couples are required.');
        if(count($judges)<3)throw new RuntimeException('At least three judges are required.');

        foreach($judges as $judge){
            $used=[];
            foreach($pairs as $pair){
                $rank=(int)($marks[(int)$pair['test_id']][(int)$judge['test_id']]??0);
                if($rank<1||$rank>$pairCount){
                    throw new RuntimeException('Every judge must rank every couple from 1 to '.$pairCount.'.');
                }
                if(isset($used[$rank])){
                    throw new RuntimeException('Judge '.$judge['name'].' used rank '.$rank.' more than once.');
                }
                $used[$rank]=true;
            }
        }
    }
}
