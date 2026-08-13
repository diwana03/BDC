<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Services\ReleaseManagerService;

$sidebarLink = static function (string $path, string $icon, string $label, string $badge = '', int $count = 0): void { ?>
    <a href="<?= e(url($path)) ?>"><span><?= e($icon) ?></span><?= e($label) ?><?php if ($badge !== ''): ?><em><?= e($badge) ?></em><?php elseif ($count > 0): ?><i><?= $count ?></i><?php endif; ?></a>
<?php };
?>
<a class="active" href="<?= e(url('admin/')) ?>"><span>▦</span>Dashboard</a>

<details class="admin-nav-group-v203" open><summary>Live Operations</summary><div class="admin-nav-group-links-v203">
    <?php $sidebarLink('admin/live-screen/', '▣', 'Live Projection', 'TOP'); ?>
    <?php $sidebarLink('admin/events/', '▣', 'Events & Tickets'); ?>
    <?php $sidebarLink('admin/registrations/', '☷', 'Registrations'); ?>
    <?php $sidebarLink('admin/scoring/', '⌁', 'Scoring Dashboard', 'NEW'); ?>
</div></details>

<details class="admin-nav-group-v203"><summary>People</summary><div class="admin-nav-group-links-v203">
    <?php $sidebarLink('admin/competitors/', '♙', 'Competitors'); ?>
    <?php if (Auth::isSuperAdmin()) $sidebarLink('admin/competitors/identity-review.php', '◎', 'Identity Matches', '', (int)($stats['identity_matches'] ?? 0)); ?>
    <?php $sidebarLink('admin/profile-requests/', '♙', 'Profile Requests', '', (int)($stats['profile_requests'] ?? 0)); ?>
</div></details>

<details class="admin-nav-group-v203"><summary>Results &amp; Points</summary><div class="admin-nav-group-links-v203">
    <?php $sidebarLink('admin/completed-events/', '✓', 'Completed Events', '', (int)($stats['completed_events'] ?? 0)); ?>
    <?php $sidebarLink('admin/archived-events/', '▤', 'Archived Events'); ?>
    <?php $sidebarLink('admin/point-adjustments/', '＋', 'Point Adjustments', '', count($pendingPointAdjustments ?? [])); ?>
    <?php $sidebarLink('admin/results/', '♕', 'Result Repository'); ?>
    <?php $sidebarLink('admin/placements/', '▤', 'Recalculate Rankings'); ?>
</div></details>

<details class="admin-nav-group-v203"><summary>Testing &amp; System</summary><div class="admin-nav-group-links-v203">
    <?php if (Auth::isSuperAdmin()) $sidebarLink('admin/scoring-tests/select-mode.php', '⚗', 'Scoring Tests Dashboard', 'TEST'); ?>
    <?php $sidebarLink('admin/system-maintenance/', '☁', 'Backup & Recovery'); ?>
    <?php if (Auth::isSuperAdmin()) $sidebarLink('admin/storage-usage/', '◫', 'Storage Usage', 'NEW'); ?>
</div></details>

<?php if (Auth::isSuperAdmin() && ReleaseManagerService::isReleaseManagerAvailable()) $sidebarLink('admin/system-release/', '⚙', 'Release Manager', 'DEPLOY'); ?>

<?php if (Auth::isSuperAdmin()): ?>
<details class="admin-nav-group-v203"><summary>Super Admin</summary><div class="admin-nav-group-links-v203">
    <?php $sidebarLink('admin/ai-operations/', '✦', 'AI Operations', 'NEW'); ?>
    <?php $sidebarLink('admin/result-import/', '↥', 'Smart Result Import'); ?>
    <?php $sidebarLink('admin/imports/', '⇧', 'Legacy & Bulk Import'); ?>
    <?php $sidebarLink('admin/competitors/merge.php', '♧', 'Merge Duplicates'); ?>
    <?php $sidebarLink('admin/users/', '♙', 'Users & Roles'); ?>
    <?php $sidebarLink('admin/sql/', '⌘', 'SQL Console'); ?>
</div></details>
<?php endif; ?>
