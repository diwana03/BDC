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

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) \App\Core\Config::get('app.base_path', '/portal'), '/');
    return $base . '/' . ltrim($path, '/');
}

/*
 * Scoring Tests v2 sandbox route.
 * The dashboard menu keeps its existing URL, but the normal GET entry now opens
 * the production-parity sandbox. `?legacy=1` remains available temporarily for
 * side-by-side validation of the previous isolated test dashboard.
 */
$bdcBootstrapMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$bdcBootstrapPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
if (
    $bdcBootstrapMethod === 'GET'
    && empty($_GET['legacy'])
    && preg_match('#/admin/scoring-tests(?:/index\.php)?/?$#', $bdcBootstrapPath) === 1
) {
    header('Location: ' . url('admin/scoring-tests/sandbox.php'));
    exit;
}

/*
 * Admin navigation safety layer.
 *
 * Admin pages historically use several different layouts. To make navigation
 * consistent without rewriting every screen, GET HTML pages receive a tiny
 * client-side enhancement that adds Back and Dashboard controls to the existing
 * top bar. JSON/AJAX, judging iframes, downloads and other non-page endpoints are
 * deliberately excluded. The callback performs no database work and leaves
 * non-HTML output untouched.
 */
$bdcRequestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$bdcRequestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$bdcAdminHtmlCandidate = $bdcRequestMethod === 'GET'
    && preg_match('#/admin(?:/|$)#', $bdcRequestPath) === 1
    && preg_match('#/(?:autosave|judge-control|judge-score|progress|status|download|export|stream|api)(?:\.php)?$#i', $bdcRequestPath) !== 1;

if ($bdcAdminHtmlCandidate) {
    $bdcAdminDashboardUrl = url('admin/');
    $bdcAutomaticParityUrl = url('admin/scoring-tests/automatic-parity.php');
    ob_start(static function (string $html) use ($bdcAdminDashboardUrl, $bdcAutomaticParityUrl): string {
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
        return preg_replace('/<\/body>/i', $navigation . '</body>', $html, 1) ?? $html;
    });
}

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
