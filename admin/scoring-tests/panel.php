<?php
declare(strict_types=1);

// Clean Testing entry point. Load the proven isolated scoring engine directly,
// without the legacy dashboard output-capture wrapper that can fail after login.
$_GET['legacy']=1;
$_GET['test_mode']='manual';
require __DIR__.'/index.php';
