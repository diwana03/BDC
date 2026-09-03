<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class BdcMcpService
{
    public static function tools():array{return [
        ['name'=>'list_event_rounds','title'=>'List BDC event rounds','description'=>'List draft Jack & Jill event rounds and their exact event and round IDs. Use before staging roster additions.','inputSchema'=>['type'=>'object','properties'=>['data_mode'=>['type'=>'string','enum'=>['test','live']],'name_query'=>['type'=>'string']],'required'=>['data_mode'],'additionalProperties'=>false],'securitySchemes'=>[['type'=>'oauth2','scopes'=>[McpOAuthService::READ_SCOPE]]],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'openWorldHint'=>false]],
        ['name'=>'list_division_competitors','title'=>'List eligible division competitors','description'=>'List council identities eligible for an exact Salsa or Bachata division, optionally filtered by role or name.','inputSchema'=>['type'=>'object','properties'=>['dance_style'=>['type'=>'string','enum'=>['bachata','salsa']],'division'=>['type'=>'string','enum'=>['novice','intermediate','advanced','all_star','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open']],'role'=>['type'=>'string','enum'=>['leader','follower']],'query'=>['type'=>'string']],'required'=>['dance_style','division'],'additionalProperties'=>false],'securitySchemes'=>[['type'=>'oauth2','scopes'=>[McpOAuthService::READ_SCOPE]]],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'openWorldHint'=>false]],
        ['name'=>'stage_competitor_additions','title'=>'Stage competitors for approval','description'=>'Stage exact competitors for an existing draft Jack & Jill round. This does not alter the roster; a Super Admin must approve the proposal in Integration Review.','inputSchema'=>['type'=>'object','properties'=>['data_mode'=>['type'=>'string','enum'=>['test','live']],'target_event_id'=>['type'=>'integer','minimum'=>1],'target_round_id'=>['type'=>'integer','minimum'=>1],'competitors'=>['type'=>'array','minItems'=>1,'maxItems'=>250,'items'=>['type'=>'object','properties'=>['identity_code'=>['type'=>'string'],'role'=>['type'=>'string','enum'=>['leader','follower']],'bib_number'=>['type'=>'integer','minimum'=>1]],'required'=>['identity_code','role','bib_number'],'additionalProperties'=>false]],'source_key'=>['type'=>'string']],'required'=>['data_mode','target_event_id','target_round_id','competitors'],'additionalProperties'=>false],'securitySchemes'=>[['type'=>'oauth2','scopes'=>[McpOAuthService::READ_SCOPE,McpOAuthService::STAGE_SCOPE]]],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false]],
        ['name'=>'get_staged_batch_status','title'=>'Get staged batch status','description'=>'Check validation and Super Admin review status for a previously staged BDC batch.','inputSchema'=>['type'=>'object','properties'=>['batch_key'=>['type'=>'string']],'required'=>['batch_key'],'additionalProperties'=>false],'securitySchemes'=>[['type'=>'oauth2','scopes'=>[McpOAuthService::READ_SCOPE]]],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'openWorldHint'=>false]],
    ];}

    public static function call(PDO $pdo,string $name,array $a):array
    {
        return match($name){'list_event_rounds'=>self::rounds($pdo,$a),'list_division_competitors'=>self::competitors($pdo,$a),'stage_competitor_additions'=>self::stage($pdo,$a),'get_staged_batch_status'=>self::status($pdo,$a),default=>throw new RuntimeException('Unknown tool.')};
    }
    private static function rounds(PDO $pdo,array $a):array
    {$mode=self::mode($a);$events=$mode==='test'?'bdc_test_events':'bdc_events';$rounds=$mode==='test'?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$needle=trim((string)($a['name_query']??''));$sql="SELECT e.id event_id,e.name event_name,e.event_date,e.status event_status,r.id round_id,r.round_type,r.division,r.status round_status,COALESCE(r.dance_style,CASE WHEN r.division LIKE 'salsa_%' THEN 'salsa' ELSE 'bachata' END) dance_style FROM {$events} e JOIN {$rounds} r ON r.event_id=e.id WHERE e.status='draft' AND r.status='draft'";$params=[];if($needle!==''){$sql.=' AND e.name LIKE :name';$params['name']='%'.$needle.'%';}$sql.=' ORDER BY e.event_date,e.id,r.id LIMIT 200';$s=$pdo->prepare($sql);$s->execute($params);return ['data_mode'=>$mode,'rounds'=>$s->fetchAll()];}
    private static function competitors(PDO $pdo,array $a):array
    {
        $dance=strtolower(trim((string)($a['dance_style']??'')));$division=strtolower(trim((string)($a['division']??'')));
        if(!in_array($dance,['bachata','salsa'],true))throw new RuntimeException('dance_style is required.');
        $role=strtolower(trim((string)($a['role']??'')));if($role!==''&&!in_array($role,['leader','follower'],true))throw new RuntimeException('role must be leader or follower.');
        $query=trim((string)($a['query']??''));$rows=JackJillCompetitorEligibilityService::directory($pdo,$dance,$role!==''?$role:null,1500);
        $registered=null;
        if(SpecialCategoryService::isSpecial($division)){
            if($dance==='salsa'){
                $category=$pdo->prepare("SELECT DISTINCT s.competitor_id FROM bdc_sdc_competitor_categories c JOIN bdc_sdc_competitors s ON s.id=c.sdc_competitor_id WHERE c.category=:category AND s.status='active'");
            }else{
                $category=$pdo->prepare("SELECT DISTINCT competitor_id FROM bdc_competitor_special_categories WHERE dance_style='bachata' AND category=:category");
            }
            $category->execute(['category'=>$division]);$registered=array_fill_keys(array_map('intval',$category->fetchAll(PDO::FETCH_COLUMN)),true);
        }
        $lower=static fn(string $v):string=>function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);$needle=$lower($query);$eligible=[];
        foreach($rows as $row){
            if($registered!==null&&!isset($registered[(int)$row['id']]))continue;
            if($needle!==''&&!str_contains($lower((string)($row['identity_code']??'').' '.(string)($row['exact_name']??'')),$needle))continue;
            $profileRole=strtolower((string)($row['dance_role']??''));$roles=$role!==''?[$role]:($profileRole==='both'?['leader','follower']:[$profileRole]);$allowed=[];
            foreach($roles as $candidateRole){
                if(!in_array($candidateRole,['leader','follower'],true))continue;
                $check=DivisionProgressionService::eligibilityFromApprovedHistory($pdo,(int)$row['id'],$candidateRole,$dance,$division);
                if(!empty($check['eligible']))$allowed[]=$candidateRole;
            }
            if(!$allowed)continue;$row['eligible_roles']=$allowed;$eligible[]=$row;
        }
        return ['dance_style'=>$dance,'division'=>$division,'count'=>count($eligible),'competitors'=>array_slice($eligible,0,500)];
    }
    private static function stage(PDO $pdo,array $a):array
    {
        $mode=self::mode($a);$event=(int)($a['target_event_id']??0);$round=(int)($a['target_round_id']??0);$competitors=$a['competitors']??null;
        if($event<1||$round<1||!is_array($competitors)||$competitors===[])throw new RuntimeException('Exact target_event_id, target_round_id and competitors are required.');
        $normalized=[];
        foreach($competitors as $competitor){
            if(!is_array($competitor))throw new RuntimeException('Every competitor selection must be an object.');
            $normalized[]=['council_id'=>(string)($competitor['identity_code']??''),'role'=>(string)($competitor['role']??''),'bib'=>$competitor['bib_number']??0];
        }
        $source=substr(trim((string)($a['source_key']??'')),0,191);if($source==='')$source='round-'.$mode.'-'.$event.'-'.$round.'-'.substr(hash('sha256',json_encode($normalized)),0,16);$batch='chatgpt-'.gmdate('Ymd').'-'.substr(hash('sha256',$source),0,20);return EventIntegrationService::submitBatch($pdo,['batch_key'=>$batch,'source_system'=>'chatgpt_mcp','items'=>[['event_system'=>'jack_jill','data_mode'=>$mode,'source_key'=>$source,'operation'=>'add_competitors','payload'=>['target_event_id'=>$event,'target_round_id'=>$round,'competitors'=>$normalized]]]]);
    }
    private static function status(PDO $pdo,array $a):array{$key=trim((string)($a['batch_key']??''));if($key==='')throw new RuntimeException('batch_key is required.');$row=EventIntegrationService::batchStatus($pdo,$key);if(!$row)throw new RuntimeException('Batch not found.');return ['batch'=>$row];}
    private static function mode(array $a):string{$mode=strtolower(trim((string)($a['data_mode']??'')));if(!in_array($mode,['test','live'],true))throw new RuntimeException('data_mode must be test or live.');return $mode;}
}
