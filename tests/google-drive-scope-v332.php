<?php
declare(strict_types=1);

$text=(string)file_get_contents(dirname(__DIR__).'/app/Services/GoogleDriveBackupService.php');
$required=[
    "'scope'=>'https://www.googleapis.com/auth/drive'",
    'supportsAllDrives=true',
    "'parents'=>[$this->folderId]",
    'normaliseFolderId',
];
$missing=array_values(array_filter($required,static fn(string $marker):bool=>!str_contains($text,$marker)));
if(str_contains($text,"'scope'=>'https://www.googleapis.com/auth/drive.file'"))$missing[]='obsolete drive.file scope is still active';
if($missing){fwrite(STDERR,'Google Drive scope validation failed: '.implode(', ',$missing)."\n");exit(1);}
echo "google drive scope v332: PASS\n";
