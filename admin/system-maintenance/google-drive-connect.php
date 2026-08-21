<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Services\BackupAutomationService;
Auth::requireSuperAdmin();
try{header('Location: '.(new BackupAutomationService(dirname(__DIR__,2)))->googleOAuthAuthorizationUrl());exit;}
catch(Throwable $e){$_SESSION['backup_oauth_error']=$e->getMessage();header('Location: ./');exit;}
