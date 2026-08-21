<?php
declare(strict_types=1);
$text=(string)file_get_contents(dirname(__DIR__).'/app/Services/BackupAutomationService.php');
$markers=['$this->ensureSettingsSchema();','private function ensureSettingsSchema','SHOW COLUMNS FROM bdc_backup_settings','ADD COLUMN server_keep_count','ADD COLUMN drive_keep_count'];
$missing=array_values(array_filter($markers,static fn(string $marker):bool=>!str_contains($text,$marker)));
if($missing){fwrite(STDERR,'Missing: '.implode(', ',$missing)."\n");exit(1);}echo "backup schema self-heal v331: PASS\n";
