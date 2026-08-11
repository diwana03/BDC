<?php
declare(strict_types=1);
namespace App\Services;
final class CountryFlagService{
 private const MAP=['singapore'=>'SG','thailand'=>'TH','china'=>'CN','mainland china'=>'CN','hong kong'=>'HK','hongkong'=>'HK','taiwan'=>'TW','japan'=>'JP','south korea'=>'KR','korea'=>'KR','indonesia'=>'ID','malaysia'=>'MY','vietnam'=>'VN','philippines'=>'PH','india'=>'IN','australia'=>'AU','new zealand'=>'NZ','united states'=>'US','usa'=>'US','united kingdom'=>'GB','uk'=>'GB','spain'=>'ES','france'=>'FR','italy'=>'IT','germany'=>'DE','netherlands'=>'NL','portugal'=>'PT','morocco'=>'MA','kazakhstan'=>'KZ','peru'=>'PE','colombia'=>'CO','costa rica'=>'CR','mexico'=>'MX','canada'=>'CA','brazil'=>'BR','argentina'=>'AR','cuba'=>'CU'];
 public static function code(?string $country):?string{$v=trim((string)$country);if($v==='')return null;if(preg_match('/^[A-Za-z]{2}$/',$v))return strtoupper($v);return self::MAP[mb_strtolower($v)]??null;}
 public static function emoji(?string $country):string{$code=self::code($country);if(!$code)return '';return mb_chr(127397+ord($code[0])).mb_chr(127397+ord($code[1]));}
 public static function label(?string $country,bool $includeName=true):string{$v=trim((string)$country);$flag=self::emoji($v);if($includeName)return trim($flag.' '.$v);return $flag;}
}