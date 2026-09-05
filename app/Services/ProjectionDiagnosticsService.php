<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ProjectionDiagnosticsService
{
    public static function diagnose(PDO $pdo,array $input):array
    {
        $system=strtolower(trim((string)($input['event_system']??'')));
        $mode=strtolower(trim((string)($input['data_mode']??'')));
        $eventId=(int)($input['event_id']??0);
        if(!in_array($system,['jack_jill','dance_cup'],true))throw new RuntimeException('event_system must be jack_jill or dance_cup.');
        if(!in_array($mode,['test','live'],true))throw new RuntimeException('data_mode must be test or live.');
        if($eventId<1)throw new RuntimeException('event_id is required.');
        $checks=[];
        self::check($checks,'read_only','pass','Diagnostics executed with SELECT queries only; no projection state or scoring data was changed.');
        $result=$system==='jack_jill'
            ?self::jackJill($pdo,$mode,$eventId,(int)($input['round_id']??0),$checks)
            :self::danceCup($pdo,$mode,$eventId,(int)($input['competition_id']??0),$checks);
        foreach([
            'live-display/index.php','live-display/state.php','live-display/feed.php',
            'admin/dance-cup/projector.php','admin/dance-cup/projection-feed.php',
        ] as $path){
            $full=dirname(__DIR__,2).'/'.$path;
            if(($system==='jack_jill'&&str_starts_with($path,'admin/dance-cup/'))||($system==='dance_cup'&&str_starts_with($path,'live-display/')))continue;
            self::check($checks,'runtime_file_'.str_replace(['.','/'],['_','_'],$path),is_readable($full)?'pass':'fail',is_readable($full)?$path.' is readable.':$path.' is missing or unreadable.');
        }
        return self::finish($system,$mode,$eventId,$result,$checks);
    }

    private static function jackJill(PDO $pdo,string $mode,int $eventId,int $requestedRound,array &$checks):array
    {
        $test=$mode==='test';
        $events=$test?'bdc_test_events':'bdc_events';
        $rounds=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
        $entries=$test?'bdc_test_scoring_entries':'bdc_scoring_entries';
        $competitors=$test?'bdc_test_competitors':'bdc_competitors';
        $judges=$test?'bdc_test_scoring_judges':'bdc_scoring_judges';
        $results=$test?'bdc_test_scoring_results':'bdc_scoring_results';
        $pairs=$test?'bdc_test_scoring_final_pairs':'bdc_scoring_final_pairs';
        $flights=$test?'bdc_test_scoring_flight_assignments':'bdc_scoring_flight_assignments';

        [$event,$error]=self::one($pdo,"SELECT id,name,status,event_date FROM {$events} WHERE id=:id LIMIT 1",['id'=>$eventId]);
        if($error!==null){self::check($checks,'event_query','fail',$error);return [];}
        if(!$event){self::check($checks,'event_found','fail','Jack & Jill event was not found.');return [];}
        self::check($checks,'event_found','pass','Event found: '.(string)$event['name'].'.');

        [$session,$sessionError]=self::one($pdo,"SELECT id,event_id,active_event_id,current_round_id,screen_type,page_number,auto_page,page_delay_seconds,results_unlocked,state_version,effect_type,effect_version,updated_at FROM bdc_live_display_sessions WHERE data_mode=:mode AND is_enabled=1 AND (event_id=:event OR active_event_id=:active) ORDER BY (active_event_id=:preferred) DESC,id DESC LIMIT 1",['mode'=>$test?'test':'real','event'=>$eventId,'active'=>$eventId,'preferred'=>$eventId]);
        if($sessionError!==null)self::check($checks,'display_session','fail',$sessionError);
        elseif(!$session)self::check($checks,'display_session','warning','No enabled Live Display session exists for this event.');
        else self::check($checks,'display_session','pass','Enabled display session found; token is intentionally not returned.');

        [$roundRows,$roundError]=self::all($pdo,"SELECT id,event_id,round_type,division,status FROM {$rounds} WHERE event_id=:event ORDER BY id",['event'=>$eventId]);
        if($roundError!==null){self::check($checks,'round_query','fail',$roundError);return ['event'=>$event];}
        self::check($checks,'rounds_found',$roundRows?'pass':'fail',$roundRows?count($roundRows).' scoring round(s) found.':'No scoring rounds exist for this event.');
        $roundId=$requestedRound>0?$requestedRound:(int)($session['current_round_id']??0);
        if($roundId<1&&$roundRows)$roundId=(int)$roundRows[0]['id'];
        $round=null;foreach($roundRows as $candidate)if((int)$candidate['id']===$roundId){$round=$candidate;break;}
        if(!$round){self::check($checks,'round_selected','fail','The requested or active round does not belong to this event.');return ['event'=>$event,'session'=>self::safeSession($session),'rounds'=>$roundRows];}
        self::check($checks,'round_selected','pass','Round #'.$roundId.' '.(string)$round['round_type'].' selected.');

        [$entryRows,$entryError]=self::all($pdo,"SELECT se.id,se.display_name,se.bib_number,se.dance_role,se.competitor_id,c.country,c.photo_url FROM {$entries} se LEFT JOIN {$competitors} c ON c.id=se.competitor_id WHERE se.round_id=:round AND se.entry_status='active' ORDER BY se.dance_role,se.bib_number,se.id",['round'=>$roundId]);
        if($entryError!==null){self::check($checks,'roster_query','fail',$entryError);$entryRows=[];}
        $roles=['leader'=>0,'follower'=>0];$missingBib=[];$missingCountry=[];$invalidCountry=[];$missingPhoto=[];$missingProfile=[];
        foreach($entryRows as $row){
            $role=(string)($row['dance_role']??'');if(isset($roles[$role]))$roles[$role]++;
            $label=(string)($row['display_name']??('entry #'.$row['id']));
            if((int)($row['bib_number']??0)<1)$missingBib[]=$label;
            if(empty($row['competitor_id']))$missingProfile[]=$label;
            $country=trim((string)($row['country']??''));if($country==='')$missingCountry[]=$label;elseif(CountryFlagService::code($country)===null)$invalidCountry[]=$label.' ('.$country.')';
            if(trim((string)($row['photo_url']??''))==='')$missingPhoto[]=$label;
        }
        self::check($checks,'active_roster',$entryRows?'pass':'warning',$entryRows?count($entryRows).' active competitors: '.$roles['leader'].' leaders and '.$roles['follower'].' followers.':'The selected round has no active competitors.');
        self::issueList($checks,'missing_bibs',$missingBib,'Every active competitor has a bib.','Active competitors missing bibs');
        self::issueList($checks,'missing_profiles',$missingProfile,'Every active entry links to a competitor profile.','Entries missing competitor profiles');
        self::issueList($checks,'missing_countries',$missingCountry,'Every active competitor has a country.','Competitors missing countries','warning');
        self::issueList($checks,'invalid_countries',$invalidCountry,'Every populated country resolves to a flag.','Countries that cannot resolve to a flag','warning');
        self::issueList($checks,'missing_photos',$missingPhoto,'Every active competitor has a photo.','Competitors missing photos','warning');

        [$duplicates,$duplicateError]=self::all($pdo,"SELECT bib_number,COUNT(*) total FROM {$entries} WHERE round_id=:round AND entry_status='active' AND bib_number IS NOT NULL GROUP BY bib_number HAVING COUNT(*)>1",['round'=>$roundId]);
        if($duplicateError!==null)self::check($checks,'duplicate_bibs','fail',$duplicateError);
        else self::check($checks,'duplicate_bibs',$duplicates?'fail':'pass',$duplicates?'Duplicate active bibs: '.implode(', ',array_column($duplicates,'bib_number')).'.':'No duplicate active bibs.');

        [$judgeRows,$judgeError]=self::all($pdo,"SELECT sj.id,sj.judge_name,sj.judge_id,sj.is_chief,j.country,j.country_code,j.photo_url FROM {$judges} sj LEFT JOIN bdc_judges j ON j.id=sj.judge_id WHERE sj.round_id=:round ORDER BY sj.is_chief DESC,sj.judge_order,sj.id",['round'=>$roundId]);
        if($judgeError!==null){self::check($checks,'judge_query','fail',$judgeError);$judgeRows=[];}
        $judgeMissingProfile=[];$judgeMissingCountry=[];$judgeMissingPhoto=[];
        foreach($judgeRows as $row){$name=(string)($row['judge_name']??('judge #'.$row['id']));if(empty($row['judge_id']))$judgeMissingProfile[]=$name;if(trim((string)($row['country_code']??$row['country']??''))==='')$judgeMissingCountry[]=$name;if(trim((string)($row['photo_url']??''))==='')$judgeMissingPhoto[]=$name;}
        self::check($checks,'judges_assigned',$judgeRows?'pass':'warning',$judgeRows?count($judgeRows).' judge(s) assigned.':'No judges are assigned.');
        self::issueList($checks,'judge_profiles',$judgeMissingProfile,'Every judge assignment links to the Judge Database.','Judges missing directory links','warning');
        self::issueList($checks,'judge_countries',$judgeMissingCountry,'Every assigned judge has a country.','Judges missing countries','warning');
        self::issueList($checks,'judge_photos',$judgeMissingPhoto,'Every assigned judge has a photo.','Judges missing photos','warning');

        [$flightRows,$flightError]=self::all($pdo,"SELECT flight_number,COUNT(*) total FROM {$flights} WHERE round_id=:round GROUP BY flight_number ORDER BY flight_number",['round'=>$roundId]);
        if($flightError!==null)self::check($checks,'flights','warning','Flight assignments are unavailable: '.$flightError);
        elseif(!$flightRows)self::check($checks,'flights','warning','No saved Flight Rounds exist for this round.');
        else self::check($checks,'flights','pass',count($flightRows).' saved Flight Round(s) cover '.array_sum(array_map('intval',array_column($flightRows,'total'))).' assignments.');

        [$resultRow,$resultError]=self::one($pdo,"SELECT COUNT(*) total FROM {$results} WHERE round_id=:round",['round'=>$roundId]);
        if($resultError!==null)self::check($checks,'results','fail',$resultError);
        else self::check($checks,'results','pass',(int)($resultRow['total']??0).' calculated result row(s) available.');

        $pairCount=0;
        if((string)$round['round_type']==='final'){
            [$pairRow,$pairError]=self::one($pdo,"SELECT COUNT(*) total FROM {$pairs} WHERE round_id=:round",['round'=>$roundId]);
            if($pairError!==null)self::check($checks,'final_pairs','fail',$pairError);
            else{$pairCount=(int)($pairRow['total']??0);self::check($checks,'final_pairs',$pairCount?'pass':'warning',$pairCount?$pairCount.' final pair(s) available.':'Final round has no confirmed pairs.');}
        }

        $settings=['screen_format'=>'16:9','density'=>'maximum','custom_width'=>null,'custom_height'=>null];
        [$settingRow,$settingError]=self::one($pdo,"SELECT screen_format,density,custom_width,custom_height FROM bdc_projection_settings WHERE round_id=:round AND data_mode=:mode LIMIT 1",['round'=>$roundId,'mode'=>$test?'test':'real']);
        if($settingError===null&&$settingRow)$settings=array_merge($settings,$settingRow);
        elseif($settingError!==null)self::check($checks,'projection_settings','warning','Default 16:9 projection settings assumed: '.$settingError);
        $competitorPages=max(1,(int)ceil(max($roles)/15));$scorePages=max(1,(int)ceil(max($roles)/12));$judgePages=max(1,count($judgeRows));
        $screen=(string)($session['screen_type']??'holding');$expected=match($screen){'competitors','callbacks','finalists'=>((string)$round['round_type']==='final'?max(1,$pairCount):$competitorPages),'heats_scores','score_matrix'=>$scorePages,'judge_call'=>$judgePages,'flights'=>max(1,count($flightRows)),default=>1};
        $page=(int)($session['page_number']??1);
        self::check($checks,'page_bounds',$page<1||$page>$expected?'fail':'pass','Current '.$screen.' page '.$page.' of '.$expected.'.');
        if($session&&in_array($screen,['final_results','results','winners'],true)&&empty($session['results_unlocked']))self::check($checks,'result_reveal_lock','fail','A result screen is selected while Results Reveal is locked.');
        else self::check($checks,'result_reveal_lock','pass','Result reveal safety is consistent with the selected screen.');

        return ['event'=>$event,'round'=>$round,'available_rounds'=>$roundRows,'session'=>self::safeSession($session),'counts'=>['competitors'=>count($entryRows),'leaders'=>$roles['leader'],'followers'=>$roles['follower'],'judges'=>count($judgeRows),'results'=>(int)($resultRow['total']??0),'final_pairs'=>$pairCount,'flights'=>count($flightRows)],'pagination'=>['competitors'=>$competitorPages,'score_matrix'=>$scorePages,'judge_call'=>$judgePages,'current_screen_expected_pages'=>$expected],'settings'=>$settings];
    }

    private static function danceCup(PDO $pdo,string $mode,int $eventId,int $requestedCompetition,array &$checks):array
    {
        $test=$mode==='test';$prefix=$test?'bdc_test_dance_cup':'bdc_dance_cup';
        $events=$prefix.'_events';$competitions=$prefix.'_competitions';$entries=$prefix.'_entries';$judges=$prefix.'_judges';$criteria=$prefix.'_criteria';$marks=$prefix.'_marks';$results=$prefix.'_scoring_results';$projection=$prefix.'_event_projection';
        [$event,$eventError]=self::one($pdo,"SELECT id,name,event_date,status FROM {$events} WHERE id=:id LIMIT 1",['id'=>$eventId]);
        if($eventError!==null){self::check($checks,'event_query','fail',$eventError);return [];}
        if(!$event){self::check($checks,'event_found','fail','Dance Cup event was not found.');return [];}
        self::check($checks,'event_found','pass','Event found: '.(string)$event['name'].'.');
        [$state,$stateError]=self::one($pdo,"SELECT id,event_id,active_competition_id,active_entry_id,screen_type,page_number,auto_page,page_delay,results_unlocked,reveal_place,effect_type,effect_version,state_version,updated_at FROM {$projection} WHERE event_id=:event LIMIT 1",['event'=>$eventId]);
        if($stateError!==null)self::check($checks,'display_session','fail',$stateError);
        elseif(!$state)self::check($checks,'display_session','warning','No Dance Cup event projection session exists.');
        else self::check($checks,'display_session','pass','Dance Cup projection session found; token is intentionally not returned.');
        [$competitionRows,$competitionError]=self::all($pdo,"SELECT id,event_id,category_name,round_name,dance_style,competition_level,status FROM {$competitions} WHERE event_id=:event ORDER BY id",['event'=>$eventId]);
        if($competitionError!==null){self::check($checks,'competition_query','fail',$competitionError);return ['event'=>$event];}
        self::check($checks,'competitions_found',$competitionRows?'pass':'fail',$competitionRows?count($competitionRows).' Dance Cup categor'.(count($competitionRows)===1?'y':'ies').' found.':'No Dance Cup categories exist for this event.');
        $competitionId=$requestedCompetition>0?$requestedCompetition:(int)($state['active_competition_id']??0);if($competitionId<1&&$competitionRows)$competitionId=(int)$competitionRows[0]['id'];
        $competition=null;foreach($competitionRows as $candidate)if((int)$candidate['id']===$competitionId){$competition=$candidate;break;}
        if(!$competition){self::check($checks,'competition_selected','fail','The requested or active category does not belong to this event.');return ['event'=>$event,'session'=>self::safeDanceCupSession($state),'competitions'=>$competitionRows];}
        self::check($checks,'competition_selected','pass','Category #'.$competitionId.' '.(string)$competition['category_name'].' selected.');

        [$entryRows,$entryError]=self::all($pdo,"SELECT e.id,e.display_name,e.bib_number,e.competitor_id,c.country,c.photo_url FROM {$entries} e LEFT JOIN bdc_competitors c ON c.id=e.competitor_id WHERE e.competition_id=:competition AND e.status='active' ORDER BY e.bib_number,e.id",['competition'=>$competitionId]);
        if($entryError!==null){self::check($checks,'roster_query','fail',$entryError);$entryRows=[];}
        $missingBib=[];$missingCountry=[];$invalidCountry=[];$missingPhoto=[];$missingProfile=[];
        foreach($entryRows as $row){$name=(string)($row['display_name']??('entry #'.$row['id']));if((int)($row['bib_number']??0)<1)$missingBib[]=$name;if(empty($row['competitor_id']))$missingProfile[]=$name;$country=trim((string)($row['country']??''));if($country==='')$missingCountry[]=$name;elseif(CountryFlagService::code($country)===null)$invalidCountry[]=$name.' ('.$country.')';if(trim((string)($row['photo_url']??''))==='')$missingPhoto[]=$name;}
        self::check($checks,'active_roster',$entryRows?'pass':'warning',$entryRows?count($entryRows).' active contestant(s).':'The selected category has no active contestants.');
        self::issueList($checks,'missing_bibs',$missingBib,'Every contestant has a bib.','Contestants missing bibs');
        self::issueList($checks,'missing_profiles',$missingProfile,'Every contestant links to a competitor profile.','Contestants missing competitor profiles','warning');
        self::issueList($checks,'missing_countries',$missingCountry,'Every contestant has a country.','Contestants missing countries','warning');
        self::issueList($checks,'invalid_countries',$invalidCountry,'Every populated country resolves to a flag.','Countries that cannot resolve to a flag','warning');
        self::issueList($checks,'missing_photos',$missingPhoto,'Every contestant has a photo.','Contestants missing photos','warning');
        [$duplicates,$duplicateError]=self::all($pdo,"SELECT bib_number,COUNT(*) total FROM {$entries} WHERE competition_id=:competition AND status='active' GROUP BY bib_number HAVING COUNT(*)>1",['competition'=>$competitionId]);
        if($duplicateError!==null)self::check($checks,'duplicate_bibs','fail',$duplicateError);else self::check($checks,'duplicate_bibs',$duplicates?'fail':'pass',$duplicates?'Duplicate active bibs: '.implode(', ',array_column($duplicates,'bib_number')).'.':'No duplicate active bibs.');

        [$judgeRows,$judgeError]=self::all($pdo,"SELECT j.id,j.judge_name,j.judge_id,j.is_chief,d.country,d.country_code,d.photo_url FROM {$judges} j LEFT JOIN bdc_judges d ON d.id=j.judge_id WHERE j.competition_id=:competition ORDER BY j.is_chief DESC,j.judge_order,j.id",['competition'=>$competitionId]);
        if($judgeError!==null){self::check($checks,'judge_query','fail',$judgeError);$judgeRows=[];}
        self::check($checks,'judges_assigned',$judgeRows?'pass':'warning',$judgeRows?count($judgeRows).' judge(s) assigned.':'No judges are assigned.');
        [$criterionRow,$criterionError]=self::one($pdo,"SELECT COUNT(*) total FROM {$criteria} WHERE competition_id=:competition",['competition'=>$competitionId]);
        $criterionCount=(int)($criterionRow['total']??0);if($criterionError!==null)self::check($checks,'criteria','fail',$criterionError);else self::check($checks,'criteria',$criterionCount?'pass':'fail',$criterionCount?$criterionCount.' scoring criteria configured.':'No scoring criteria configured.');
        [$markRow,$markError]=self::one($pdo,"SELECT COUNT(*) total FROM {$marks} WHERE competition_id=:competition",['competition'=>$competitionId]);
        $markCount=(int)($markRow['total']??0);if($markError!==null)self::check($checks,'marks','fail',$markError);else self::check($checks,'marks','pass',$markCount.' saved mark row(s).');
        [$resultRow,$resultError]=self::one($pdo,"SELECT COUNT(*) total FROM {$results} WHERE competition_id=:competition",['competition'=>$competitionId]);
        $resultCount=(int)($resultRow['total']??0);if($resultError!==null)self::check($checks,'results','fail',$resultError);else self::check($checks,'results','pass',$resultCount.' calculated result row(s).');
        if($state&&$requestedCompetition<1&&(int)$state['active_competition_id']!==$competitionId)self::check($checks,'active_category','fail','Projection active category does not belong to the selected event category.');
        else self::check($checks,'active_category','pass','Projection category selection is consistent.');
        $activeEntry=(int)($state['active_entry_id']??0);if($activeEntry>0&&!in_array($activeEntry,array_map(static fn(array $row):int=>(int)$row['id'],$entryRows),true))self::check($checks,'active_contestant','fail','Projection active contestant is not in the active roster.');else self::check($checks,'active_contestant','pass',$activeEntry>0?'Active contestant belongs to the roster.':'No individual contestant is currently selected.');
        $screen=(string)($state['screen_type']??'holding');if($state&&in_array($screen,['results','winners','scoreboard'],true)&&empty($state['results_unlocked']))self::check($checks,'result_reveal_lock','fail','A result screen is selected while Results Reveal is locked.');else self::check($checks,'result_reveal_lock','pass','Result reveal safety is consistent with the selected screen.');
        return ['event'=>$event,'competition'=>$competition,'available_competitions'=>$competitionRows,'session'=>self::safeDanceCupSession($state),'counts'=>['contestants'=>count($entryRows),'judges'=>count($judgeRows),'criteria'=>$criterionCount,'marks'=>$markCount,'results'=>$resultCount]];
    }

    private static function safeSession(?array $session):?array
    {if(!$session)return null;return array_intersect_key($session,array_flip(['id','event_id','active_event_id','current_round_id','screen_type','page_number','auto_page','page_delay_seconds','results_unlocked','state_version','effect_type','effect_version','updated_at']));}
    private static function safeDanceCupSession(?array $session):?array
    {if(!$session)return null;return array_intersect_key($session,array_flip(['id','event_id','active_competition_id','active_entry_id','screen_type','page_number','auto_page','page_delay','results_unlocked','reveal_place','effect_type','effect_version','state_version','updated_at']));}
    private static function issueList(array &$checks,string $key,array $items,string $pass,string $fail,string $severity='fail'):void
    {self::check($checks,$key,$items?$severity:'pass',$items?$fail.': '.implode(', ',array_slice($items,0,20)).(count($items)>20?' and '.(count($items)-20).' more':'').'.':$pass,['count'=>count($items)]);}
    private static function check(array &$checks,string $key,string $status,string $message,array $details=[]):void
    {$checks[]=['key'=>$key,'status'=>$status,'message'=>$message]+($details?['details'=>$details]:[]);}
    private static function one(PDO $pdo,string $sql,array $params=[]):array
    {[$rows,$error]=self::all($pdo,$sql,$params);return [$rows[0]??null,$error];}
    private static function all(PDO $pdo,string $sql,array $params=[]):array
    {try{$query=$pdo->prepare($sql);$query->execute($params);return [$query->fetchAll(),null];}catch(Throwable $e){return [[],self::safeError($e)];}}
    private static function safeError(Throwable $e):string
    {$message=preg_replace('/\s+/',' ',trim($e->getMessage()))?:'Database query failed.';return substr($message,0,300);}
    private static function finish(string $system,string $mode,int $eventId,array $result,array $checks):array
    {$totals=['pass'=>0,'warning'=>0,'fail'=>0];foreach($checks as $check)$totals[$check['status']]++;return ['ok'=>$totals['fail']===0,'event_system'=>$system,'data_mode'=>$mode,'event_id'=>$eventId,'checked_at'=>gmdate('c'),'summary'=>$totals,'checks'=>$checks,'projection'=>$result,'limitations'=>['This read-only diagnostic validates runtime files, database state, roster metadata, page bounds and reveal safety.','It does not judge pixel alignment, animation smoothness, browser fullscreen behaviour or the physical projector image; use a screenshot or the 10 m x 5.5 m 4K display for those visual checks.']];}
}
