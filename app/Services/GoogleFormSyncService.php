<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class GoogleFormSyncService
{
    private const DIVISIONS = [
        'open' => ['bachata'=>'bachata_open','salsa'=>'salsa_open'],
        'amateur' => ['bachata'=>'bachata_rising','salsa'=>'salsa_rising'],
    ];

    public static function canonicalPayload(array $input):array
    {
        $formKind=strtolower(trim((string)($input['form_kind']??'')));
        if(!isset(self::DIVISIONS[$formKind]))throw new RuntimeException('form_kind must be open or amateur.');
        $name=trim((string)($input['full_name']??''));
        if($name==='')throw new RuntimeException('full_name is required.');
        $role=self::normaliseRole((string)($input['role']??''));
        $styles=self::normaliseStyles($input['styles']??[]);
        if(!$styles)throw new RuntimeException('At least one dance style is required.');
        $email=strtolower(trim((string)($input['email']??'')));
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$email='';
        return [
            'source_system'=>substr(trim((string)($input['source_system']??'google_forms')),0,80)?:'google_forms',
            'source_key'=>substr(trim((string)($input['source_key']??'')),0,191),
            'source_row'=>max(0,(int)($input['source_row']??0)),
            'form_kind'=>$formKind,
            'full_name'=>$name,
            'email'=>$email,
            'phone'=>trim((string)($input['phone']??'')),
            'instagram'=>self::normaliseInstagram((string)($input['instagram']??'')),
            'country'=>trim((string)($input['country']??'')),
            'role'=>$role,
            'styles'=>$styles,
            'photo_base64'=>(string)($input['photo_base64']??''),
            'photo_mime'=>strtolower(trim((string)($input['photo_mime']??''))),
        ];
    }

    public static function process(PDO $pdo,array $input):array
    {
        $data=self::canonicalPayload($input);
        if($data['source_key']==='')throw new RuntimeException('source_key is required.');
        $hash=hash('sha256',json_encode(self::hashable($data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $existing=self::existingSubmission($pdo,$data['source_system'],$data['source_key'],$hash);
        if($existing&&$existing['status']!=='failed')return ['status'=>'duplicate','submission_id'=>(int)$existing['id'],'competitor_id'=>$existing['competitor_id']?(int)$existing['competitor_id']:null];

        $payloadJson=json_encode(self::hashable($data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $pdo->beginTransaction();
        try{
            if($existing){
                $submissionId=(int)$existing['id'];
                $pdo->prepare("UPDATE bdc_form_sync_submissions SET status='processing',error_message=NULL,payload_json=:payload,updated_at=NOW() WHERE id=:id")
                    ->execute(['payload'=>$payloadJson,'id'=>$submissionId]);
            }else{
                $insert=$pdo->prepare("INSERT INTO bdc_form_sync_submissions(source_system,source_key,payload_hash,form_kind,source_row,participant_name,status,payload_json) VALUES(:system,:source_key,:hash,:kind,NULLIF(:row,0),:name,'processing',:payload)");
                $insert->execute(['system'=>$data['source_system'],'source_key'=>$data['source_key'],'hash'=>$hash,'kind'=>$data['form_kind'],'row'=>$data['source_row'],'name'=>$data['full_name'],'payload'=>$payloadJson]);
                $submissionId=(int)$pdo->lastInsertId();
            }
            $candidates=self::findCandidates($pdo,$data);
            $decision=self::resolveIdentity($data,$candidates);
            if($decision['status']==='pending_review'){
                $ids=array_map(static fn(array $c):(int)$c['id'],$candidates);
                $pdo->prepare("UPDATE bdc_form_sync_submissions SET status='pending_review',candidate_ids_json=:ids,processed_at=NOW() WHERE id=:id")
                    ->execute(['ids'=>json_encode($ids),'id'=>$submissionId]);
                $pdo->commit();
                return ['status'=>'pending_review','submission_id'=>$submissionId,'candidate_ids'=>$ids];
            }

            $competitorId=$decision['competitor_id'];
            if(!$competitorId){
                $primaryStyle=in_array('bachata',$data['styles'],true)?'bachata':$data['styles'][0];
                $created=CompetitorIdentityService::findOrCreateOfficial($pdo,$data['full_name'],$data['role'],self::DIVISIONS[$data['form_kind']][$primaryStyle]);
                $competitorId=(int)$created['id'];
            }
            $photo=self::storePhoto($data,$submissionId);
            self::updateCompetitor($pdo,$competitorId,$data,$photo);
            foreach($data['styles'] as $style)self::upsertProfile($pdo,$competitorId,$style,$data['role'],self::DIVISIONS[$data['form_kind']][$style]);
            $pdo->prepare("UPDATE bdc_form_sync_submissions SET status='completed',competitor_id=:cid,processed_at=NOW() WHERE id=:id")
                ->execute(['cid'=>$competitorId,'id'=>$submissionId]);
            $pdo->commit();
            return ['status'=>'completed','submission_id'=>$submissionId,'competitor_id'=>$competitorId,'styles'=>$data['styles']];
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            try{
                $failed=$pdo->prepare("INSERT INTO bdc_form_sync_submissions(source_system,source_key,payload_hash,form_kind,source_row,participant_name,status,error_message,payload_json,processed_at) VALUES(:system,:source_key,:hash,:kind,NULLIF(:row,0),:name,'failed',:error,:payload,NOW()) ON DUPLICATE KEY UPDATE status='failed',error_message=VALUES(error_message),processed_at=NOW()");
                $failed->execute(['system'=>$data['source_system'],'source_key'=>$data['source_key'],'hash'=>$hash,'kind'=>$data['form_kind'],'row'=>$data['source_row'],'name'=>$data['full_name'],'error'=>substr($e->getMessage(),0,2000),'payload'=>$payloadJson]);
            }catch(Throwable $ignored){error_log('BDC form sync failure could not be recorded: '.$ignored->getMessage());}
            throw $e;
        }
    }

    public static function resolveIdentity(array $data,array $candidates):array
    {
        if(!$candidates)return ['status'=>'new','competitor_id'=>null];
        $strong=[];
        foreach($candidates as $candidate){
            $matches=0;
            if($data['email']!==''&&strtolower(trim((string)($candidate['email']??'')))===$data['email'])$matches++;
            if(self::digits($data['phone'])!==''&&self::digits((string)($candidate['phone']??''))===self::digits($data['phone']))$matches++;
            if($data['instagram']!==''&&self::normaliseInstagram((string)($candidate['instagram']??''))===$data['instagram'])$matches++;
            if($matches>0)$strong[]=(int)$candidate['id'];
        }
        $strong=array_values(array_unique($strong));
        if(count($strong)===1)return ['status'=>'existing','competitor_id'=>$strong[0]];
        if(count($strong)>1)return ['status'=>'pending_review','competitor_id'=>null];
        $exact=array_values(array_filter($candidates,static fn(array $candidate):bool=>(string)($candidate['normalised_name']??'')===CompetitorIdentityService::normaliseCompetitorName($data['full_name'])));
        if(count($exact)===1){
            $candidate=$exact[0];
            $conflict=($data['email']!==''&&trim((string)($candidate['email']??''))!==''&&strtolower(trim((string)$candidate['email']))!==$data['email'])
                ||(self::digits($data['phone'])!==''&&self::digits((string)($candidate['phone']??''))!==''&&self::digits((string)$candidate['phone'])!==self::digits($data['phone']))
                ||($data['instagram']!==''&&self::normaliseInstagram((string)($candidate['instagram']??''))!==''&&self::normaliseInstagram((string)$candidate['instagram'])!==$data['instagram']);
            if(!$conflict)return ['status'=>'existing','competitor_id'=>(int)$candidate['id']];
        }
        return ['status'=>'pending_review','competitor_id'=>null];
    }

    private static function findCandidates(PDO $pdo,array $data):array
    {
        $name=CompetitorIdentityService::normaliseCompetitorName($data['full_name']);
        $email=$data['email'];$phone=self::digits($data['phone']);$instagram=$data['instagram'];
        $sql="SELECT id,bdc_id,exact_name,normalised_name,email,phone,instagram,country FROM bdc_competitors WHERE status<>'archived' AND (normalised_name=:name";
        $params=['name'=>$name];
        if($email!==''){$sql.=' OR LOWER(TRIM(email))=:email';$params['email']=$email;}
        if($phone!==''){$sql.=" OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-',''),'(',''),')','')=:phone";$params['phone']=$phone;}
        if($instagram!==''){$sql.=" OR LOWER(TRIM(LEADING '@' FROM instagram))=:instagram";$params['instagram']=$instagram;}
        $sql.=') ORDER BY id LIMIT 20';
        $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function updateCompetitor(PDO $pdo,int $id,array $data,?string $photo):void
    {
        $stmt=$pdo->prepare("UPDATE bdc_competitors SET email=COALESCE(NULLIF(:email,''),email),phone=COALESCE(NULLIF(:phone,''),phone),instagram=COALESCE(NULLIF(:instagram,''),instagram),country=COALESCE(NULLIF(:country,''),country),photo_url=COALESCE(NULLIF(:photo,''),photo_url),status=IF(status='archived',status,'active') WHERE id=:id");
        $stmt->execute(['email'=>$data['email'],'phone'=>$data['phone'],'instagram'=>$data['instagram'],'country'=>$data['country'],'photo'=>$photo??'','id'=>$id]);
    }

    private static function upsertProfile(PDO $pdo,int $id,string $style,string $role,string $division):void
    {
        $stmt=$pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) VALUES(:id,:style,:role,:division) ON DUPLICATE KEY UPDATE dance_role=VALUES(dance_role),current_division=VALUES(current_division)");
        $stmt->execute(['id'=>$id,'style'=>$style,'role'=>$role,'division'=>$division]);
        if($style==='bachata')$pdo->prepare('UPDATE bdc_competitors SET dance_role=:role,current_division=:division WHERE id=:id')->execute(['role'=>$role,'division'=>$division,'id'=>$id]);
    }

    private static function storePhoto(array $data,int $submissionId):?string
    {
        if($data['photo_base64']==='')return null;
        $raw=base64_decode($data['photo_base64'],true);
        if($raw===false||strlen($raw)>15*1024*1024)throw new RuntimeException('Photo is invalid or larger than 15 MB.');
        if(!function_exists('imagecreatefromstring')||!function_exists('imagejpeg'))throw new RuntimeException('The server GD image extension is required for form-sync photos.');
        $source=@imagecreatefromstring($raw);if(!$source)throw new RuntimeException('Photo format could not be decoded.');
        if($data['photo_mime']==='image/jpeg'&&function_exists('exif_read_data')){
            $tmp=tempnam(sys_get_temp_dir(),'bdc-photo-');file_put_contents($tmp,$raw);$exif=@exif_read_data($tmp);@unlink($tmp);
            $orientation=(int)($exif['Orientation']??1);
            if($orientation===3)$source=imagerotate($source,180,0);
            elseif($orientation===6)$source=imagerotate($source,-90,0);
            elseif($orientation===8)$source=imagerotate($source,90,0);
        }
        $width=imagesx($source);$height=imagesy($source);$side=min($width,$height);$x=(int)(($width-$side)/2);$y=(int)(($height-$side)/2);
        $target=imagecreatetruecolor(800,800);$white=imagecolorallocate($target,255,255,255);imagefill($target,0,0,$white);
        if(!imagecopyresampled($target,$source,0,0,$x,$y,800,800,$side,$side)){imagedestroy($source);imagedestroy($target);throw new RuntimeException('Photo crop failed.');}
        $dir=dirname(__DIR__,2).'/uploads/competitors';if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Photo storage is unavailable.');
        $name='form-sync-'.$submissionId.'-'.bin2hex(random_bytes(5)).'.jpg';$path=$dir.'/'.$name;
        $ok=imagejpeg($target,$path,90);imagedestroy($source);imagedestroy($target);if(!$ok)throw new RuntimeException('Photo storage failed.');
        return url('uploads/competitors/'.$name);
    }

    private static function existingSubmission(PDO $pdo,string $system,string $sourceKey,string $hash):?array
    {
        $stmt=$pdo->prepare('SELECT id,competitor_id,status FROM bdc_form_sync_submissions WHERE source_system=:system AND (source_key=:source_key OR payload_hash=:hash) LIMIT 1');
        $stmt->execute(['system'=>$system,'source_key'=>$sourceKey,'hash'=>$hash]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:null;
    }

    private static function hashable(array $data):array{$copy=$data;unset($copy['photo_base64']);$copy['photo_hash']=$data['photo_base64']!==''?hash('sha256',$data['photo_base64']):'';return $copy;}
    private static function normaliseRole(string $role):string{$role=strtolower(trim($role));if(str_contains($role,'follow'))return 'follower';if(str_contains($role,'lead'))return 'leader';if($role==='both')return 'both';throw new RuntimeException('Role must be Lead or Follow.');}
    private static function normaliseStyles(mixed $styles):array{$list=is_array($styles)?$styles:preg_split('/[,+\/]/',(string)$styles);$out=[];foreach((array)$list as $style){$style=strtolower(trim((string)$style));if(str_contains($style,'bachata'))$out[]='bachata';if(str_contains($style,'salsa'))$out[]='salsa';}return array_values(array_unique($out));}
    private static function normaliseInstagram(string $value):string{$value=trim($value);if(preg_match('#instagram\.com/([^/?]+)#i',$value,$m))$value=$m[1];return strtolower(ltrim(trim($value),'@'));}
    private static function digits(string $value):string{return preg_replace('/\D+/','',$value)??'';}
}
