<?php
declare(strict_types=1);

// Legacy compatibility route. Test and Live now share the same projector engine.
$_GET['data_mode'] = 'real';
require __DIR__ . '/projection-compat.php';
