<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

/**
 * Copies an official competitor into disposable test storage without assuming
 * optional profile fields (including photos) exist in the test schema.
 */
final class TestCompetitorCopyService
{
    public static function copy(PDO $pdo,array $competitor):void
    {
        $target=$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bdc_test_competitors'")->fetchAll(PDO::FETCH_COLUMN);
        $allowed=array_fill_keys(array_map('strval',$target),true);
        $copy=[];
        foreach($competitor as $column=>$value){
            if(isset($allowed[(string)$column]))$copy[(string)$column]=$value;
        }
        // A photo is profile decoration only. Its absence must never block testing.
        if(isset($allowed['original_photo_url']) && !array_key_exists('original_photo_url',$copy))$copy['original_photo_url']=null;
        if(!$copy || !isset($copy['id'],$copy['bdc_id'],$copy['exact_name'])){
            throw new RuntimeException('Competitor identity fields are unavailable for the test copy.');
        }
        $columns=array_keys($copy);
        $quoted=array_map(static fn(string $c):string=>'`'.str_replace('`','',$c).'`',$columns);
        $params=array_map(static fn(string $c):string=>':'.$c,$columns);
        $updates=[];
        foreach(['exact_name','dance_role','country','status'] as $column){
            if(isset($copy[$column]))$updates[]='`'.$column.'`=VALUES(`'.$column.'`)';
        }
        $sql='INSERT INTO bdc_test_competitors('.implode(',',$quoted).') VALUES('.implode(',',$params).')';
        if($updates)$sql.=' ON DUPLICATE KEY UPDATE '.implode(',',$updates);
        $stmt=$pdo->prepare($sql);
        $stmt->execute($copy);
    }
}
