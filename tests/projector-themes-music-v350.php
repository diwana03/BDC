<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$files=[
    'service'=>file_get_contents($root.'/app/Services/LiveDisplaySessionService.php'),
    'state'=>file_get_contents($root.'/live-display/state.php'),
    'display'=>file_get_contents($root.'/live-display/index.php'),
    'control'=>file_get_contents($root.'/admin/live-screen/projector-control-v350.js'),
    'upload'=>file_get_contents($root.'/admin/live-screen/music-upload.php'),
    'themes'=>file_get_contents($root.'/public/css/projector-themes-v350.css'),
];
$checks=[
    'four allow-listed themes'=>str_contains($files['service'],"'midnight_burgundy','obsidian_gold','ivory_burgundy','pearl_sapphire'"),
    'theme persisted in shared session'=>str_contains($files['service'],'SET screen_theme=:theme'),
    'music state exposed to projector'=>str_contains($files['state'],'"music_url"')&&str_contains($files['state'],'"music_version"'),
    'persistent looping audio shell'=>str_contains($files['display'],"music.loop=true")&&str_contains($files['display'],'syncMusic(s)'),
    'autoplay recovery gate'=>str_contains($files['display'],'START PROJECTOR MUSIC'),
    'custom upload formats and size cap'=>str_contains($files['upload'],"'audio/mpeg'=>'mp3'")&&str_contains($files['upload'],'60*1024*1024'),
    'separate music controls'=>str_contains($files['control'],'Projector Music · Separate Module')&&str_contains($files['control'],'music_clear'),
    'two dark and two light previews'=>substr_count($files['control'],"'Dark'")===2&&substr_count($files['control'],"'Light'")===2,
    'four theme definitions'=>substr_count($files['themes'],'data-projector-theme=')===3&&str_contains($files['themes'],':root{--bdc-bg'),
];
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: {$label}\n");exit(1);}}
echo "OK: shared projector themes and isolated music controls v350\n";
