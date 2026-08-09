<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use RuntimeException;
use Throwable;

/**
 * Global new-competitor registration hook for Manual, Automatic and Test scoring.
 * Existing-competitor registration remains in each established controller.
 */
final class GlobalScoringRegistrationHook
{
    public static function handle(string $method,string $path,string $testMode=''):void
    {
        if($method!=='POST')return;
        if((string)($_POST['action']??'')!=='add_entry')return;
        if((string)($_POST['entry_mode']??'existing')!=='create')return;

        $isTest=preg_match('#/admin/scoring-tests(?:/index\.php)?/?$#',$path)===1;
        $isManual=preg_match('#/admin/scoring(?:/index\.php|/core\.php)?/?$#',$path)===1;
        $isAutomatic=preg_match('#/admin/scoring/automatic-round\.php/?$#',$path)===1;
        if(!$isTest&&!$isManual&&!$isAutomatic)return;

        Auth::requireAdmin();
        if(!Csrf::verify($_POST['_csrf']??null)){
            http_response_code(419);
            exit('Invalid security token.');
        }

        $roundId=(int)($_POST['round_id']??0);
        $role=(string)($_POST['dance_role']??'');
        $bib=(int)($_POST['bib_number']??0);
        $name=trim((string)($_POST['competitor_search']??''));
        if($roundId<1||!in_array($role,['leader','follower'],true)||$bib<1||$name===''){
            http_response_code(400);
            exit('Choose role, bib and competitor name.');
        }

        $pdo=Database::connection();
        $roundTable=$isTest?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
        $entryTable=$isTest?'bdc_test_scoring_entries':'bdc_scoring_entries';
        $auditTable=$isTest?'bdc_test_scoring_audit':'bdc_scoring_audit';

        $roundStmt=$pdo->prepare("SELECT division FROM {$roundTable} WHERE id=:id LIMIT 1");
        $roundStmt->execute(['id'=>$roundId]);
        $division=(string)$roundStmt->fetchColumn();
        if($division===''){
            http_response_code(404);
            exit('Scoring round not found.');
        }

        $bibStmt=$pdo->prepare("SELECT display_name FROM {$entryTable} WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND entry_status='active' LIMIT 1");
        $bibStmt->execute(['round'=>$roundId,'role'=>$role,'bib'=>$bib]);
        $taken=$bibStmt->fetchColumn();
        if($taken!==false){
            http_response_code(409);
            exit('Bib '.$bib.' is already assigned to '.$taken.' on the '.ucfirst($role).' side.');
        }

        // Special-category participation does not define the dancer's career division.
        $initialDivision=SpecialCategoryService::isSpecial($division)?'novice':$division;
        if(!in_array($initialDivision,['novice','intermediate','advanced','all_star'],true))$initialDivision='novice';

        try{
            $competitor=CompetitorIdentityService::findOrCreateOfficial($pdo,$name,$role,$initialDivision);

            if($isTest){
                CompetitorIdentityService::mirrorOfficialToTest($pdo,$competitor);
            }

            $entry=$pdo->prepare("INSERT INTO {$entryTable}(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:round,:competitor,:role,:bib,:name,'active') ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number),display_name=VALUES(display_name),entry_status='active'");
            $entry->execute([
                'round'=>$roundId,
                'competitor'=>(int)$competitor['id'],
                'role'=>$role,
                'bib'=>$bib,
                'name'=>(string)$competitor['exact_name'],
            ]);

            $audit=$pdo->prepare("INSERT INTO {$auditTable}(round_id,user_id,action,details_json) VALUES(:round,:user,'new_bdc_competitor_registered',:details)");
            $audit->execute([
                'round'=>$roundId,
                'user'=>(int)(Auth::user()['id']??0)?:null,
                'details'=>json_encode([
                    'competitor_id'=>(int)$competitor['id'],
                    'bdc_id'=>(string)$competitor['bdc_id'],
                    'role'=>$role,
                    'bib'=>$bib,
                    'official_identity_created'=>(bool)($competitor['created']??false),
                    'source'=>$isTest?'scoring_test':($isAutomatic?'automatic_scoring':'manual_scoring'),
                ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ]);

            if($isTest){
                $mode=in_array($testMode,['manual','automated'],true)?$testMode:'manual';
                $target='admin/scoring-tests/index.php?legacy=1&test_mode='.rawurlencode($mode).'&round_id='.$roundId.'&competitor_added=1';
            }elseif($isAutomatic){
                $target='admin/scoring/automatic-round.php?round_id='.$roundId.'&competitor_added=1';
            }else{
                $mode=(string)($_GET['mode']??$_POST['mode']??'manual');
                if(!in_array($mode,['manual','automated'],true))$mode='manual';
                $target='admin/scoring/index.php?mode='.rawurlencode($mode).'&round_id='.$roundId.'&competitor_added=1';
            }
            header('Location: '.url($target),true,303);
            exit;
        }catch(Throwable $e){
            http_response_code(400);
            exit($e->getMessage());
        }
    }
}
