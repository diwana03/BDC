<?php
use App\Services\ReleaseManagerService;
$bdcEnvironment = ReleaseManagerService::environment();
$bdcVersion = ReleaseManagerService::versionInfo();
$bdcEnvironmentClass =
    $bdcEnvironment === "staging"
        ? "admin-env-staging"
        : "admin-env-production";
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | BDC Admin</title><link rel="stylesheet" href="<?= e(
    url("public/assets/css/app.css?v=203"),
) ?>"><link rel="stylesheet" href="<?= e(
    url("public/assets/css/bdc-brand-theme.css?v=4"),
) ?>"></head><body class="admin-v203 <?= e(
    $bdcEnvironmentClass,
) ?>"><div class="admin-environment-banner" style="padding:8px 12px;text-align:center;font-weight:800;letter-spacing:.08em;"><?= e(
    ReleaseManagerService::environmentLabel(),
) ?> · VERSION <?= e(
     (string) ($bdcVersion["version"] ?? ReleaseManagerService::VERSION),
 ) ?></div><div class="admin-layout-v203"><header class="admin-topbar-v203"><div class="admin-topbar-brand-v203"><span class="admin-topbar-logo-v203"><img src="<?= e(
    url("public/assets/img/bdc-logo.png"),
) ?>" alt="BDC"></span><span><strong>BDC Admin Dashboard</strong><small>Bachata Dance Council · Competition Portal</small></span></div><div class="admin-topbar-actions-v203"><a class="admin-topbar-home-v203" href="https://bachatadancecouncil.com/">⌂ BDC Home</a><span class="admin-topbar-divider-v203"></span><span class="admin-topbar-user-v203"><b>Hello, <?= e(
    App\Core\Auth::user()["full_name"] ?? "Admin",
) ?></b><small><?= App\Core\Auth::isSuperAdmin()
    ? "Super Admin"
    : "Admin" ?></small></span><a class="admin-topbar-logout-v203" href="<?= e(
    url("admin/?logout=1"),
) ?>">Logout</a></div></header><aside class="admin-sidebar-v203"><nav><a class="active" href="<?= e(
    url("admin/"),
) ?>"><span>▦</span>Dashboard</a><a href="<?= e(
    url("admin/competitors/"),
) ?>"><span>♙</span>Competitors</a><?php if (
    App\Core\Auth::isSuperAdmin()
): ?><a href="<?= e(
    url("admin/competitors/identity-review.php"),
) ?>"><span>◎</span>Identity Matches<?php if (
    !empty($stats["identity_matches"])
): ?><i><?= (int) $stats[
    "identity_matches"
] ?></i><?php endif; ?></a><?php endif; ?><a href="<?= e(
    url("admin/events/"),
) ?>"><span>▣</span>Events &amp; Tickets</a><a href="<?= e(
    url("admin/completed-events/"),
) ?>"><span>✓</span>Completed Events<?php if (
    !empty($stats["completed_events"])
): ?><i><?= (int) $stats[
    "completed_events"
] ?></i><?php endif; ?></a><a href="<?= e(
    url("admin/archived-events/"),
) ?>"><span>▤</span>Archived Events</a><a href="<?= e(
    url("admin/registrations/"),
) ?>"><span>☷</span>Registrations</a><a href="<?= e(
    url("admin/scoring/"),
) ?>"><span>⌁</span>Scoring Dashboard <em>NEW</em></a><a href="<?= e(
    url("admin/point-adjustments/"),
) ?>"><span>＋</span>Point Adjustments<?php if (
    !empty($pendingPointAdjustments)
): ?><i><?= count($pendingPointAdjustments) ?></i><?php endif; ?></a><?php if (
    App\Core\Auth::isSuperAdmin()
): ?><a href="<?= e(
    url("admin/scoring-tests/"),
) ?>"><span>⚗</span>Scoring Tests Dashboard <em>TEST</em></a><?php endif; ?><a href="<?= e(
    url("admin/results/"),
) ?>"><span>♕</span>Result Repository</a><a href="<?= e(
    url("admin/placements/"),
) ?>"><span>▤</span>Recalculate Rankings</a><a href="<?= e(
    url("admin/profile-requests/"),
) ?>"><span>♙</span>Profile Requests<?php if (
    (int) $stats["profile_requests"] > 0
): ?><i><?= (int) $stats[
    "profile_requests"
] ?></i><?php endif; ?></a><a href="<?= e(
    url("admin/system-maintenance/"),
) ?>"><span>☁</span>Backup &amp; Recovery</a><?php
if (App\Core\Auth::isSuperAdmin()): ?><a href="<?= e(
    url("admin/storage-usage/"),
) ?>"><span>◫</span>Storage Usage <em>NEW</em></a><?php endif;
if (
    App\Core\Auth::isSuperAdmin() &&
    ReleaseManagerService::isReleaseManagerAvailable()
): ?><a href="<?= e(
    url("admin/system-release/"),
) ?>"><span>⚙</span>Release Manager</a><?php endif;
if (
    App\Core\Auth::isSuperAdmin()
): ?><div class="admin-sidebar-label-v203">Super Admin</div><a href="<?= e(
    url("admin/ai-operations/"),
) ?>"><span>✦</span>AI Operations <em>NEW</em></a><a href="<?= e(
    url("admin/result-import/"),
) ?>"><span>↥</span>Smart Result Import</a><a href="<?= e(
    url("admin/imports/"),
) ?>"><span>⇧</span>Legacy &amp; Bulk Import</a><a href="<?= e(
    url("admin/competitors/merge.php"),
) ?>"><span>♧</span>Merge Duplicates</a><a href="<?= e(
    url("admin/users/"),
) ?>"><span>♙</span>Users &amp; Roles</a><a href="<?= e(
    url("admin/sql/"),
) ?>"><span>⌘</span>SQL Console</a><?php endif;
?></nav><div class="admin-system-card-v203"><strong>ⓘ BDC System</strong><span>Version <?= e(
    (string) ($bdcVersion["version"] ?? ReleaseManagerService::VERSION),
) ?></span><small>© <?= date(
    "Y",
) ?> Bachata Dance Council</small></div></aside><main class="admin-main-v203"><?php
 if (
     App\Core\Auth::isSuperAdmin() &&
     !empty($pendingPointAdjustments)
 ): ?><div class="card border-danger shadow-sm mb-4"><div class="card-header bg-danger text-white"><strong>Action Required: Pending Point Adjustments</strong></div></div><?php endif;
 if (
     !empty($pendingCompetitionApprovals)
 ): ?><div class="card border-warning shadow-sm mb-4"><div class="card-header bg-warning-subtle"><strong>Pending Competition Publication Approvals · <?= count(
    $pendingCompetitionApprovals,
) ?></strong></div><div class="card-body"><?php foreach (
    $pendingCompetitionApprovals
    as $approval
): ?><div class="d-flex justify-content-between border-bottom py-2"><span><strong><?= e(
    $approval["event_name"],
) ?></strong> · <?= e(
    ucfirst($approval["division"]),
) ?></span><a class="btn btn-warning btn-sm" href="<?= e(
    url("admin/scoring/publish.php?round_id=" . $approval["final_round_id"]),
) ?>">Review Approval</a></div><?php endforeach; ?></div></div><?php endif;
 ?><section class="admin-page-heading-v203"><div><h1>Dashboard</h1><p>Welcome back. Here is what is happening with your competition portal.</p></div><span class="admin-date-chip-v203"><?= date(
    "j M Y",
) ?></span></section><section class="admin-metric-grid-v203"><a class="admin-metric-card-v203 metric-orange" href="<?= e(
    url("admin/competitors/"),
) ?>"><div class="metric-icon-v203">♙</div><div><span>Total Competitors</span><strong><?= (int) $stats[
    "competitors"
] ?></strong><small>Active records</small></div></a><a class="admin-metric-card-v203 metric-blue" href="<?= e(
    url("admin/events/"),
) ?>"><div class="metric-icon-v203">▣</div><div><span>Total Events</span><strong><?= (int) $stats[
    "events"
] ?></strong><small>All event records</small></div></a><a class="admin-metric-card-v203 metric-green" href="<?= e(
    url("admin/completed-events/"),
) ?>"><div class="metric-icon-v203">✓</div><div><span>Completed Events</span><strong><?= (int) ($stats[
    "completed_events"
] ??
    0) ?></strong><small>Open for amendment if required</small></div></a><a class="admin-metric-card-v203 metric-gold" href="<?= e(
    url("admin/profile-requests/"),
) ?>"><div class="metric-icon-v203">◷</div><div><span>Pending Requests</span><strong><?= (int) $stats[
    "profile_requests"
] ?></strong><small>Requires action</small></div></a></section><section class="admin-card-v203 admin-quick-actions-v203"><h2>Quick Actions</h2><div><a href="<?= e(
    url("admin/competitors/"),
) ?>">＋ Add Competitor</a><a href="<?= e(
    url("admin/events/"),
) ?>">＋ Create Event</a><a href="<?= e(
    url("admin/scoring/"),
) ?>">⌁ Scoring Dashboard</a><a href="<?= e(
    url("admin/completed-events/"),
) ?>">✓ Completed Events</a><a href="<?= e(
    url("admin/archived-events/"),
) ?>">▤ Archived Events</a><a href="<?= e(
    url("admin/ai-operations/"),
) ?>">✦ AI Operations</a><a href="<?= e(
    url("admin/result-import/"),
) ?>">↥ Import Results</a></div></section><section id="archive-events" class="admin-card-v203"><form method="post" action="<?= e(
    url("admin/scoring/archive-event.php"),
) ?>" id="bulkArchiveForm" onsubmit="const n=document.querySelectorAll('.archive-event-check:checked').length;return n>0&&confirm('Archive '+n+' selected completed event'+(n===1?'':'s')+'? All history will be preserved.');"><input type="hidden" name="_csrf" value="<?= e(
    App\Core\Csrf::token(),
) ?>"><input type="hidden" name="return_to" value="dashboard"><div class="admin-card-head-v203"><div><h2>Archive Completed Events</h2><small>Select one or more completed events and archive them together.</small></div><button class="btn btn-primary btn-sm" id="archiveSelectedButton" disabled>Archive Selected</button></div><div class="admin-table-wrap-v203"><table><thead><tr><th style="width:42px"><input type="checkbox" id="archiveSelectAll" aria-label="Select all events"></th><th>Event</th><th>Date</th><th>Rounds</th></tr></thead><tbody><?php if (
    empty($activeArchiveEvents)
): ?><tr><td colspan="4" class="empty-v203">No completed events available to archive.</td></tr><?php else:foreach (
        $activeArchiveEvents
        as $event
    ): ?><tr><td><input class="archive-event-check" type="checkbox" name="event_ids[]" value="<?= (int) $event[
    "id"
] ?>" aria-label="Select <?= e($event["name"]) ?>"></td><td><?= e(
    $event["name"],
) ?></td><td><?= e(
    $event["event_date"] ?: "Date pending",
) ?></td><td><?= (int) $event[
    "scoring_rounds"
] ?></td></tr><?php endforeach;endif; ?></tbody></table></div></form></section><script>(function(){const all=document.getElementById('archiveSelectAll'),checks=[...document.querySelectorAll('.archive-event-check')],button=document.getElementById('archiveSelectedButton');if(!all||!button)return;function sync(){const selected=checks.filter(c=>c.checked).length;all.checked=checks.length>0&&selected===checks.length;all.indeterminate=selected>0&&selected<checks.length;button.disabled=selected===0;button.textContent=selected?'Archive Selected ('+selected+')':'Archive Selected'}all.addEventListener('change',()=>{checks.forEach(c=>c.checked=all.checked);sync()});checks.forEach(c=>c.addEventListener('change',sync));sync()})();</script><footer class="admin-footer-v203"><span>© <?= date(
    "Y",
) ?> Bachata Dance Council.</span><span>BDC Admin Portal v<?= e(
     (string) ($bdcVersion["version"] ?? ReleaseManagerService::VERSION),
 ) ?></span></footer></main></div></body></html>
