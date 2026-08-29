<?php
declare(strict_types=1);
namespace App\Services;
final class CountryFlagService{
 private const ALIASES=['mainland china'=>'CN','china mainland'=>'CN','hongkong'=>'HK','korea'=>'KR','usa'=>'US','united states of america'=>'US','uk'=>'GB','uae'=>'AE','viet nam'=>'VN','czechia'=>'CZ','russia'=>'RU'];
 public static function code(?string $country):?string{
  $v=trim((string)$country);if($v==='')return null;if(preg_match('/^[A-Za-z]{2}$/',$v))return strtoupper($v);$key=mb_strtolower($v);if(isset(self::ALIASES[$key]))return self::ALIASES[$key];
  static $countries=null;if($countries===null){$countries=[];$json=@file_get_contents(dirname(__DIR__,2).'/public/assets/flags/countries.json');foreach((json_decode((string)$json,true)?:[]) as $item)if(!empty($item['name'])&&!empty($item['code']))$countries[mb_strtolower(trim((string)$item['name']))]=strtoupper((string)$item['code']);}
  return $countries[$key]??null;
 }
 public static function emoji(?string $country):string{
  $code=self::code($country);if(!$code)return '';
  $point=static function(int $value):string{
   return chr(0xF0|($value>>18)).chr(0x80|(($value>>12)&0x3F)).chr(0x80|(($value>>6)&0x3F)).chr(0x80|($value&0x3F));
  };
  return $point(127397+ord($code[0])).$point(127397+ord($code[1]));
 }
 public static function label(?string $country,bool $includeName=true):string{$v=trim((string)$country);$flag=self::emoji($v);if($includeName)return trim($flag.' '.$v);return $flag;}
}
