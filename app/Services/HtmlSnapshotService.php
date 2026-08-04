<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class HtmlSnapshotService
{
    private static function fetch(string $url):string
    {
        if(function_exists('curl_init')){
            $ch=curl_init($url);
            curl_setopt_array($ch,[
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_FOLLOWLOCATION=>true,
                CURLOPT_CONNECTTIMEOUT=>15,
                CURLOPT_TIMEOUT=>90,
                CURLOPT_HTTPHEADER=>[
                    'Accept: text/html,application/xhtml+xml',
                    'X-BDC-Snapshot: 1',
                ],
            ]);

            $html=curl_exec($ch);
            $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
            $error=curl_error($ch);
            curl_close($ch);

            if($html===false || $status<200 || $status>=300){
                throw new RuntimeException(
                    'Could not render repository HTML snapshot (HTTP '.$status.'). '.
                    ($error?:'The preview page could not be opened.')
                );
            }

            return (string)$html;
        }

        $context=stream_context_create([
            'http'=>[
                'timeout'=>90,
                'header'=>"Accept: text/html\r\nX-BDC-Snapshot: 1\r\n",
                'ignore_errors'=>true,
            ],
        ]);

        $html=@file_get_contents($url,false,$context);
        if($html===false){
            throw new RuntimeException('Could not open the reviewed result page for HTML archiving.');
        }

        return (string)$html;
    }

    private static function ensureAbsoluteLinks(string $html,string $baseUrl):string
    {
        if(stripos($html,'<base ')===false){
            $baseTag='<base href="'.htmlspecialchars($baseUrl,ENT_QUOTES,'UTF-8').'">';
            if(stripos($html,'<head>')!==false){
                $html=preg_replace('/<head>/i',"<head>\n".$baseTag,$html,1)??$html;
            }else{
                $html=$baseTag."\n".$html;
            }
        }

        return $html;
    }

    private static function removeInteractiveControls(string $html):string
    {
        $html=preg_replace('/<nav\b[^>]*>.*?<\/nav>/is','',$html)??$html;
        $html=preg_replace('/<button\b[^>]*>.*?<\/button>/is','',$html)??$html;
        $html=preg_replace('/<form\b[^>]*>.*?<\/form>/is','',$html)??$html;
        $html=preg_replace('/<[^>]+class="[^"]*\bno-print\b[^"]*"[^>]*>.*?<\/[^>]+>/is','',$html)??$html;

        $archiveStyle=<<<'HTML'
<style>
@media print {
  body { background:#fff !important; }
}
.repository-archive-banner{
  font-family:Arial,sans-serif;
  background:#111827;
  color:#fff;
  padding:10px 16px;
  font-size:13px;
  display:flex;
  justify-content:space-between;
  gap:12px;
  align-items:center;
}
.repository-archive-banner strong{font-size:14px}
</style>
HTML;

        $banner='<div class="repository-archive-banner"><strong>BDC Official Archived Result</strong><span>Read-only repository snapshot</span></div>';

        if(stripos($html,'</head>')!==false){
            $html=preg_replace('/<\/head>/i',$archiveStyle."\n</head>",$html,1)??$html;
        }else{
            $html=$archiveStyle."\n".$html;
        }

        if(stripos($html,'<body')!==false){
            $html=preg_replace('/(<body\b[^>]*>)/i','$1'.$banner,$html,1)??$html;
        }else{
            $html=$banner.$html;
        }

        return $html;
    }

    public static function archive(string $url,string $targetPath,string $baseUrl):array
    {
        $html=self::fetch($url);

        if(trim($html)==='' || stripos($html,'<html')===false){
            throw new RuntimeException('The reviewed result page did not return valid HTML.');
        }

        $html=self::ensureAbsoluteLinks($html,$baseUrl);
        $html=self::removeInteractiveControls($html);

        $directory=dirname($targetPath);
        if(!is_dir($directory) && !mkdir($directory,0755,true) && !is_dir($directory)){
            throw new RuntimeException('Could not create the repository results folder.');
        }

        $temporary=$targetPath.'.tmp';
        if(file_put_contents($temporary,$html,LOCK_EX)===false){
            throw new RuntimeException('Could not write the HTML repository snapshot.');
        }

        if(!rename($temporary,$targetPath)){
            @unlink($temporary);
            throw new RuntimeException('Could not finalize the HTML repository snapshot.');
        }

        return [
            'size'=>filesize($targetPath)?:0,
            'checksum'=>hash_file('sha256',$targetPath)?:'',
        ];
    }
}
