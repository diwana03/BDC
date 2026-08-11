<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
\App\Core\Auth::requireAdmin();
header('Location: '.url('admin/scoring-tests/?legacy=1&sandbox_recovery=1'));
exit;
