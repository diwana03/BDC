<?php
declare(strict_types=1);
namespace App\Services;
use RuntimeException;
final class PdfSnapshotService{
 private static function exists(string $cmd):bool{$v=shell_exec('command -v '.escapeshellarg($cmd).' 2>/dev/null');return is_string($v)&&trim($v)!=='';}
 private static function run(array $args):void{$cmd=implode(' ',array_map('escapeshellarg',$args)).' 2>&1';exec($cmd,$out,$code);if($code!==0)throw new RuntimeException("PDF renderer failed:\n".implode("\n",$out));}
 public static function snapshot(string $url,string $target):void{
  $dir=dirname($target);if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Could not create storage/results.');
  $tmp=$target.'.tmp.pdf';@unlink($tmp);
  $configured=trim((string)(getenv('BDC_PDF_BROWSER')?:''));
  foreach(array_filter([$configured,'chromium','chromium-browser','google-chrome','google-chrome-stable']) as $browser){
   if(!str_contains($browser,'/')&&!self::exists($browser))continue;
   try{
    self::run([$browser,'--headless','--disable-gpu','--no-sandbox','--disable-dev-shm-usage','--virtual-time-budget=3000','--print-to-pdf-no-header','--print-to-pdf='.$tmp,$url]);
    if(is_file($tmp)&&filesize($tmp)>1000){if(!rename($tmp,$target))throw new RuntimeException('Could not store PDF.');return;}
   }catch(\Throwable $e){@unlink($tmp);}
  }
  if(self::exists('wkhtmltopdf')){self::run(['wkhtmltopdf','--quiet','--print-media-type','--enable-local-file-access',$url,$tmp]);if(is_file($tmp)&&filesize($tmp)>1000){rename($tmp,$target);return;}}
  if(self::exists('weasyprint')){self::run(['weasyprint',$url,$tmp]);if(is_file($tmp)&&filesize($tmp)>1000){rename($tmp,$target);return;}}
  throw new RuntimeException('No PDF renderer found. Install Chromium, wkhtmltopdf or WeasyPrint, or set BDC_PDF_BROWSER.');
 }
}
