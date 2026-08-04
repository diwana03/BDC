<?php
declare(strict_types=1);
namespace App\Services;
final class SimplePdf{
 private array $lines=[];
 public function line(string $text,int $size=10,bool $bold=false):void{$this->lines[]=[$text,$size,$bold];}
 public function output(string $filename):never{
  $stream="BT\n";$y=800;
  foreach($this->lines as [$text,$size,$bold]){
   if($y<45)break;$font=$bold?'F2':'F1';$safe=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$text);
   $stream.="/$font $size Tf\n1 0 0 1 40 $y Tm\n($safe) Tj\n";$y-=($size+6);
  }
  $stream.="ET";
  $objects=[
   '<< /Type /Catalog /Pages 2 0 R >>',
   '<< /Type /Pages /Kids [ 6 0 R ] /Count 1 >>',
   '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
   '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
   "<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream",
   '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents 5 0 R >>'
  ];
  $pdf="%PDF-1.4\n";$off=[0];
  foreach($objects as $i=>$o){$off[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n$o\nendobj\n";}
  $xref=strlen($pdf);$pdf.="xref\n0 7\n0000000000 65535 f \n";
  for($i=1;$i<=6;$i++)$pdf.=sprintf("%010d 00000 n \n",$off[$i]);
  $pdf.="trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
  header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="'.$filename.'"');echo $pdf;exit;
 }
}