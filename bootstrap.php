<?php
declare(strict_types=1);

if (defined('BDC_PORTAL_BOOTSTRAPPED')) {
    return;
}
define('BDC_PORTAL_BOOTSTRAPPED', true);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$configPath = __DIR__ . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit(
        'Portal configuration is not complete. Open <a href="./setup.php">setup.php</a>.'
    );
}

\App\Core\Config::load($configPath);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

session_name((string) \App\Core\Config::get('app.session_name', 'bdc_portal_session'));
session_set_cookie_params([
    'httponly' => true,
    'secure' => (bool) \App\Core\Config::get('security.secure_cookies', true),
    'samesite' => 'Lax',
    'path' => (string) \App\Core\Config::get('app.base_path', '/portal'),
]);
session_start();

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) \App\Core\Config::get('app.base_path', '/portal'), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

/*
 * One official BDC identity across every HTML surface. This runs centrally so
 * judge links, Test, Live, Dance Cup, administration, print views and audience
 * projection cannot drift into unbranded one-off templates.
 */
$bdcOfficialLogoUrl = url('public/assets/bdc-logo.png');
$bdcBrandingScriptUrl = url('public/js/bdc-global-branding.js?v=345');
$bdcCopyLinkScriptUrl = url('public/js/bdc-copy-link-v345.js?v=345');
ob_start(static function (string $html) use ($bdcOfficialLogoUrl, $bdcBrandingScriptUrl, $bdcCopyLinkScriptUrl): string {
    if ($html === '' || stripos($html, '</body>') === false || str_contains($html, 'data-bdc-global-branding-loader')) {
        return $html;
    }
    $contentType = '';
    foreach (headers_list() as $headerLine) {
        if (stripos($headerLine, 'Content-Type:') === 0) {
            $contentType = trim(substr($headerLine, strlen('Content-Type:')));
            break;
        }
    }
    if ($contentType !== '' && stripos($contentType, 'text/html') === false) {
        return $html;
    }
    $logo = json_encode($bdcOfficialLogoUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $loader = '<script data-bdc-global-branding-loader>window.BDC_OFFICIAL_LOGO_URL=' . $logo . ';</script>'
        . '<script defer src="' . e($bdcBrandingScriptUrl) . '"></script>'
        . '<script defer src="' . e($bdcCopyLinkScriptUrl) . '"></script>';
    return preg_replace('/<\/body>/i', $loader . '</body>', $html, 1) ?? $html;
});

$bdcBootstrapMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$bdcBootstrapPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$bdcIsScoringTestIndex = preg_match('#/admin/scoring-tests(?:/(?:index|panel)\.php)?/?$#', $bdcBootstrapPath) === 1;
$bdcTestMode = (string)($_GET['test_mode'] ?? $_POST['test_mode'] ?? $_SESSION['bdc_test_scoring_mode'] ?? '');
if (!in_array($bdcTestMode, ['manual','automated'], true)) {
    $bdcTestMode = '';
}
if ($bdcTestMode !== '') {
    $_SESSION['bdc_test_scoring_mode'] = $bdcTestMode;
}

/* Global new-competitor creation for Manual, Automatic and Test scoring. */
\App\Services\GlobalScoringRegistrationHook::handle($bdcBootstrapMethod,$bdcBootstrapPath,$bdcTestMode);

/*
 * Safe Scoring Tests competitor generator.
 * Display-only fields such as photos are never part of scoring identity.
 */
if (
    $bdcBootstrapMethod === 'POST'
    && (string)($_POST['action'] ?? '') === 'generate_test_competitors'
    && $bdcIsScoringTestIndex
) {
    \App\Core\Auth::requireAdmin();
    if (!\App\Core\Csrf::verify($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }
    $roundId=(int)($_POST['round_id'] ?? 0);
    $finalistCoupleCount=max(0,(int)($_POST['finalist_couple_count'] ?? 0));
    $leaderCount=$finalistCoupleCount>0?$finalistCoupleCount:(int)($_POST['leader_count'] ?? 10);
    $followerCount=$finalistCoupleCount>0?$finalistCoupleCount:(int)($_POST['follower_count'] ?? 10);
    \App\Services\TestCompetitorGeneratorService::generate(
        \App\Core\Database::connection(),
        $roundId,
        $leaderCount,
        $followerCount,
        (int)(\App\Core\Auth::user()['id'] ?? 0)
    );
    $modeQuery=$bdcTestMode!==''?'&test_mode='.rawurlencode($bdcTestMode):'';
    header('Location: '.url('admin/scoring-tests/panel.php?round_id='.$roundId.'&competitors_generated=1'.$modeQuery),true,303);
    exit;
}

/* Special-category round creation stays on the established Test Dashboard. */
if (
    $bdcBootstrapMethod === 'POST'
    && (string)($_POST['action'] ?? '') === 'create_round'
    && $bdcIsScoringTestIndex
    && \App\Services\SpecialCategoryService::isSpecial((string)($_POST['division'] ?? ''))
) {
    \App\Core\Auth::requireAdmin();
    if (!\App\Core\Csrf::verify($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }
    $pdo=\App\Core\Database::connection();
    \App\Services\SpecialCategoryService::ensureSchema($pdo);
    $userId=(int)(\App\Core\Auth::user()['id'] ?? 0);
    $eventId=(int)($_POST['event_id'] ?? 0);
    $name=trim((string)($_POST['new_event_name'] ?? ''));
    $date=trim((string)($_POST['new_event_date'] ?? ''));
    $division=(string)$_POST['division'];
    $roundType=(string)($_POST['round_type'] ?? 'heats');
    if(!in_array($roundType,['heats','final'],true)){http_response_code(400);exit('Invalid round type.');}
    if($eventId>0 && $name!==''){http_response_code(400);exit('Select an existing event or create a new event, not both.');}
    if($eventId<1){
        if($name===''){http_response_code(400);exit('Select an existing event or enter a new event name.');}
        if($date!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){http_response_code(400);exit('Enter the event date as YYYY-MM-DD.');}
        $base=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$name),'-')) ?: 'event';
        $slug=$base;$n=2;$check=$pdo->prepare('SELECT COUNT(*) FROM bdc_test_events WHERE slug=:s');
        while(true){$check->execute(['s'=>$slug]);if(!(int)$check->fetchColumn())break;$slug=$base.'-'.$n++;}
        $pdo->prepare("INSERT INTO bdc_test_events(name,normalised_name,slug,event_date,status) VALUES(:n,:nn,:s,NULLIF(:d,''),'draft')")
            ->execute(['n'=>$name,'nn'=>strtolower($name),'s'=>$slug,'d'=>$date]);
        $eventId=(int)$pdo->lastInsertId();
    }
    $s=$pdo->prepare("SELECT id FROM bdc_test_scoring_rounds WHERE event_id=:e AND division=:d AND round_type=:t AND status<>'archived' ORDER BY id DESC LIMIT 1");
    $s->execute(['e'=>$eventId,'d'=>$division,'t'=>$roundType]);
    $roundId=(int)$s->fetchColumn();
    if($roundId<1){
        $pdo->prepare("INSERT INTO bdc_test_scoring_rounds(event_id,round_type,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by) VALUES(:e,:t,:d,10,10,10.00,4.50,4.30,4.20,:u)")
            ->execute(['e'=>$eventId,'t'=>$roundType,'d'=>$division,'u'=>$userId?:null]);
        $roundId=(int)$pdo->lastInsertId();
    }
    $modeQuery=$bdcTestMode!==''?'&test_mode='.rawurlencode($bdcTestMode):'';
    header('Location: '.url('admin/scoring-tests/panel.php?round_id='.$roundId.'&special_created=1'.$modeQuery),true,303);
    exit;
}

/* Special categories bypass participant-count point tiers; this controls callbacks only. */
if (
    $bdcBootstrapMethod === 'POST'
    && (string)($_POST['action'] ?? '') === 'settings'
    && $bdcIsScoringTestIndex
) {
    $roundId=(int)($_POST['round_id'] ?? 0);
    if($roundId>0){
        $pdo=\App\Core\Database::connection();
        $s=$pdo->prepare('SELECT division FROM bdc_test_scoring_rounds WHERE id=:r');
        $s->execute(['r'=>$roundId]);
        $division=(string)$s->fetchColumn();
        if(\App\Services\SpecialCategoryService::isSpecial($division)){
            \App\Core\Auth::requireAdmin();
            if (!\App\Core\Csrf::verify($_POST['_csrf'] ?? null)) {
                http_response_code(419);
                exit('Invalid security token.');
            }
            $yes=max(1,min(100,(int)($_POST['special_yes_count'] ?? 10)));
            $pdo->prepare('UPDATE bdc_test_scoring_rounds SET yes_count=:y,callback_count=:y,tier_manual_override=1 WHERE id=:r')
                ->execute(['y'=>$yes,'r'=>$roundId]);
            $modeQuery=$bdcTestMode!==''?'&test_mode='.rawurlencode($bdcTestMode):'';
            header('Location: '.url('admin/scoring-tests/panel.php?round_id='.$roundId.'&special_settings=1'.$modeQuery),true,303);
            exit;
        }
    }
}

/*
 * Shared BDC Heats engine gate.
 */
if (
    $bdcBootstrapMethod === 'POST'
    && (string)($_POST['action'] ?? '') === 'generate_results'
    && preg_match('#/admin/(?:scoring|scoring-tests)(?:/index\.php)?/?$#', $bdcBootstrapPath) === 1
) {
    \App\Core\Auth::requireAdmin();
    if (!\App\Core\Csrf::verify($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }
    $roundId=(int)($_POST['round_id'] ?? 0);
    if($roundId<1){http_response_code(400);exit('Invalid scoring round.');}
    $isTest=str_contains($bdcBootstrapPath,'/scoring-tests');
    $scope=$isTest
        ? \App\Services\ScoringCalculationService::TEST
        : \App\Services\ScoringCalculationService::PRODUCTION;
    \App\Services\ScoringCalculationService::calculateHeats(
        \App\Core\Database::connection(),
        $roundId,
        $scope,
        (int)(\App\Core\Auth::user()['id'] ?? 0)
    );
    $modeQuery=$isTest && $bdcTestMode!==''?'&test_mode='.rawurlencode($bdcTestMode):'';
    $target=$isTest
        ? url('admin/scoring-tests/panel.php?round_id='.$roundId.'&shared_engine=1'.$modeQuery)
        : url('admin/scoring/?round_id='.$roundId.'&shared_engine=1');
    header('Location: '.$target,true,303);
    exit;
}

/* Scoring Tests without an explicit mode still starts at mode selection. */
if (
    $bdcBootstrapMethod === 'GET'
    && empty($_GET['legacy'])
    && $bdcIsScoringTestIndex
) {
    header('Location: ' . url('admin/scoring-tests/select-mode.php'), true, 303);
    exit;
}

/*
 * Admin navigation safety layer plus direct Scoring Tests mode enhancement.
 */
$bdcRequestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$bdcRequestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$bdcAdminHtmlCandidate = $bdcRequestMethod === 'GET'
    && preg_match('#/admin(?:/|$)#', $bdcRequestPath) === 1
    && preg_match('#/(?:autosave|judge-control|judge-score|progress|status|download|export|stream|api)(?:\.php)?$#i', $bdcRequestPath) !== 1;

if ($bdcAdminHtmlCandidate) {
    $bdcAdminDashboardUrl = url('admin/');
    $bdcAutomaticParityUrl = url('admin/scoring-tests/automatic-parity.php');
    $bdcScoringTestEnhancement='';
    if($bdcIsScoringTestIndex && $bdcTestMode!==''){
        $cfg=json_encode([
            'mode'=>$bdcTestMode,
            'automaticEndpoint'=>url('admin/scoring-tests/automatic-inline.php'),
            'actionEndpoint'=>url('admin/scoring-tests/automatic-inline.php'),
            'dataEndpoint'=>url('admin/scoring-tests/mode-data.php'),
        ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        $scriptSrc=e(url('public/js/scoring-tests-mode-v2412.js'));
        $bdcScoringTestEnhancement='<script>window.BDC_SCORING_TEST_MODE='.$cfg.';</script><script src="'.$scriptSrc.'"></script>';
    }
    ob_start(static function (string $html) use ($bdcAdminDashboardUrl, $bdcAutomaticParityUrl, $bdcScoringTestEnhancement): string {
        if ($html === '' || stripos($html, '</body>') === false) {
            return $html;
        }

        $contentType = '';
        foreach (headers_list() as $headerLine) {
            if (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = trim(substr($headerLine, strlen('Content-Type:')));
                break;
            }
        }
        if ($contentType !== '' && stripos($contentType, 'text/html') === false) {
            return $html;
        }

        if (!\App\Core\Auth::check()) {
            return $html;
        }

        $dashboardJson = json_encode($bdcAdminDashboardUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $parityJson = json_encode($bdcAutomaticParityUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $navigation = <<<'HTML'
<style id="bdc-universal-admin-nav-style">
.bdc-universal-admin-nav{display:flex;align-items:center;gap:.45rem;margin-left:auto}
.bdc-universal-admin-nav .bdc-admin-nav-btn{display:inline-flex;align-items:center;justify-content:center;gap:.32rem;min-height:34px;padding:.38rem .72rem;border:1px solid rgba(255,255,255,.72);border-radius:7px;background:transparent;color:#fff!important;text-decoration:none;font-size:.85rem;font-weight:700;line-height:1;cursor:pointer;white-space:nowrap}
.bdc-universal-admin-nav .bdc-admin-nav-btn:hover{background:rgba(255,255,255,.12)}
.bdc-universal-admin-nav .bdc-admin-nav-dashboard{background:#fff;color:#20242a!important;border-color:#fff}
.bdc-universal-admin-nav .bdc-admin-nav-dashboard:hover{background:#f0f1f3}
.bdc-universal-admin-nav .bdc-admin-nav-test{background:#ffc107;color:#111!important;border-color:#ffc107}
.bdc-universal-admin-nav .bdc-admin-nav-test:hover{background:#ffca2c}
.bdc-universal-admin-nav-floating{position:fixed;top:14px;right:18px;z-index:30000;padding:.42rem;background:#20242a;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.24)}
.admin-topbar-actions-v203 .bdc-universal-admin-nav{margin-right:.35rem}
@media(max-width:760px){.bdc-universal-admin-nav .bdc-admin-nav-btn{padding:.34rem .55rem;font-size:.78rem}.bdc-universal-admin-nav-floating{top:8px;right:8px}}
</style>
<script id="bdc-universal-admin-nav-script">
(function(){
  if(window.__bdcUniversalAdminNavLoaded)return;
  window.__bdcUniversalAdminNavLoaded=true;
  if(window.self!==window.top)return;
  var dashboard=__BDC_DASHBOARD_URL__;
  var parity=__BDC_PARITY_URL__;
  var cleanPath=window.location.pathname.replace(/\/+$/,'');
  var cleanDashboard=(new URL(dashboard,window.location.origin)).pathname.replace(/\/+$/,'');
  var parityPath=(new URL(parity,window.location.origin)).pathname.replace(/\/+$/,'');
  var onDashboard=cleanPath===cleanDashboard;
  var onScoringTests=/\/admin\/scoring-tests(?:\/index\.php)?$/.test(cleanPath);
  var onParity=cleanPath===parityPath;

  function button(label,kind){
    var el=document.createElement(kind==='back'?'button':'a');
    el.className='bdc-admin-nav-btn '+(kind==='dashboard'?'bdc-admin-nav-dashboard':(kind==='test'?'bdc-admin-nav-test':''));
    if(kind==='back'){
      el.type='button';
      el.textContent='← Back';
      el.addEventListener('click',function(){
        if(window.history.length>1)window.history.back();
        else window.location.href=dashboard;
      });
    }else if(kind==='test'){
      el.href=parity;
      el.textContent='⚙ Automatic Parity Test';
    }else{
      el.href=dashboard;
      el.textContent='⌂ Dashboard';
    }
    return el;
  }

  var controls=document.createElement('div');
  controls.className='bdc-universal-admin-nav';
  controls.setAttribute('data-bdc-admin-navigation','1');
  controls.appendChild(button('Back','back'));
  if((onScoringTests||onParity) && !onParity)controls.appendChild(button('Automatic Parity Test','test'));
  if(!onDashboard)controls.appendChild(button('Dashboard','dashboard'));

  var modern=document.querySelector('.admin-topbar-actions-v203');
  if(modern){modern.insertBefore(controls,modern.firstChild);return;}

  var navbar=document.querySelector('nav.navbar .container-fluid');
  if(navbar){
    var oldActions=navbar.querySelector('.bdc-admin-existing-actions');
    if(!oldActions){
      oldActions=document.createElement('div');
      oldActions.className='bdc-admin-existing-actions d-flex align-items-center gap-2';
      Array.prototype.slice.call(navbar.children).forEach(function(child,index){
        if(index>0 && child!==controls)oldActions.appendChild(child);
      });
      if(oldActions.children.length)navbar.appendChild(oldActions);
    }
    navbar.appendChild(controls);
    return;
  }

  controls.classList.add('bdc-universal-admin-nav-floating');
  document.body.appendChild(controls);
})();
</script>
HTML;
        $navigation = str_replace(
            ['__BDC_DASHBOARD_URL__','__BDC_PARITY_URL__'],
            [(string)$dashboardJson,(string)$parityJson],
            $navigation
        );
        return preg_replace('/<\/body>/i', $navigation . $bdcScoringTestEnhancement . '</body>', $html, 1) ?? $html;
    });
}

if (!function_exists('country_flag_url')) {
function country_flag_url(?string $country): ?string
    {
        $country = trim((string) $country);
        if ($country === '') return null;
        $aliases = ['usa'=>'us','united states of america'=>'us','uk'=>'gb','united kingdom'=>'gb','south korea'=>'kr','korea'=>'kr','north korea'=>'kp','russia'=>'ru','mainland china'=>'cn','china mainland'=>'cn','hong kong'=>'hk','taiwan'=>'tw','uae'=>'ae','vietnam'=>'vn','viet nam'=>'vn','czech republic'=>'cz'];
        $key = mb_strtolower($country);
        $code = $aliases[$key] ?? null;
        if ($code === null && preg_match('/^[a-z]{2}$/i', $country)) $code = strtolower($country);
        static $countries = null;
        if ($code === null) {
            if ($countries === null) {
                $countries = [];
                $json = @file_get_contents(__DIR__ . '/public/assets/flags/countries.json');
                foreach ((json_decode((string) $json, true) ?: []) as $item) {
                    if (!empty($item['name']) && !empty($item['code'])) $countries[mb_strtolower((string) $item['name'])] = strtolower((string) $item['code']);
                }
            }
            $code = $countries[$key] ?? null;
        }
        if ($code === null || !is_file(__DIR__ . '/public/assets/flags/' . $code . '.svg')) return null;
        return url('public/assets/flags/' . $code . '.svg');
    }
}
