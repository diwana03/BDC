<?php
declare(strict_types=1);

namespace App\Services;

final class CountryFlagService
{
    private const ALIASES=[
        'mainland china'=>'CN','china mainland'=>'CN','hongkong'=>'HK','korea'=>'KR',
        'usa'=>'US','united states'=>'US','uk'=>'GB','uae'=>'AE','viet nam'=>'VN',
        'czechia'=>'CZ','russia'=>'RU',
    ];
    private const CITY_COUNTRIES=[
        'bangkok'=>'TH','seoul'=>'KR','tokyo'=>'JP','kyoto'=>'JP','kobe'=>'JP',
        'melbourne'=>'AU','sydney'=>'AU','auckland'=>'NZ','jakarta'=>'ID',
        'manila'=>'PH','ho chi minh'=>'VN','ho chi minh city'=>'VN','ekaterinburg'=>'RU',
    ];

    /** @return array{by_name:array<string,array{name:string,code:string}>,by_code:array<string,string>} */
    private static function countries():array
    {
        static $countries=null;
        if($countries!==null)return $countries;
        $byName=[];$byCode=[];
        $json=@file_get_contents(dirname(__DIR__,2).'/public/assets/flags/countries.json');
        foreach((json_decode((string)$json,true)?:[]) as $item){
            if(empty($item['name'])||empty($item['code']))continue;
            $name=trim((string)$item['name']);$code=strtoupper((string)$item['code']);
            $byName[mb_strtolower($name)]=['name'=>$name,'code'=>$code];$byCode[$code]=$name;
        }
        return $countries=['by_name'=>$byName,'by_code'=>$byCode];
    }

    public static function canonicalName(?string $country):string
    {
        $value=trim((string)$country);
        if($value==='')return '';
        $countries=self::countries();
        if(preg_match('/^[A-Za-z]{2}$/',$value)===1){
            return $countries['by_code'][strtoupper($value)]??$value;
        }
        $key=mb_strtolower(preg_replace('/\s+/u',' ',$value)??$value);
        if(isset($countries['by_name'][$key]))return $countries['by_name'][$key]['name'];
        if(isset(self::ALIASES[$key]))return $countries['by_code'][self::ALIASES[$key]]??$value;
        if(isset(self::CITY_COUNTRIES[$key]))return $countries['by_code'][self::CITY_COUNTRIES[$key]]??$value;

        $codes=[];
        $parts=preg_split('/\s*[,\/]\s*/u',$value)?:[];
        foreach($parts as $part){
            $partKey=mb_strtolower(trim($part));
            if(isset($countries['by_name'][$partKey]))$codes[$countries['by_name'][$partKey]['code']]=true;
            elseif(isset(self::ALIASES[$partKey]))$codes[self::ALIASES[$partKey]]=true;
        }
        if(count($codes)===1){
            $code=(string)array_key_first($codes);
            return $countries['by_code'][$code]??$value;
        }
        if(count($codes)>1)return $value;

        foreach($countries['by_name'] as $nameKey=>$item){
            if(preg_match('/(?:^|[^\pL])'.preg_quote($nameKey,'/').'(?:$|[^\pL])/ui',$key)===1)$codes[$item['code']]=true;
        }
        foreach(self::ALIASES as $alias=>$code){
            if(preg_match('/(?:^|[^\pL])'.preg_quote($alias,'/').'(?:$|[^\pL])/ui',$key)===1)$codes[$code]=true;
        }
        if(count($codes)===1){
            $code=(string)array_key_first($codes);
            return $countries['by_code'][$code]??$value;
        }
        return $value;
    }

    public static function code(?string $country):?string
    {
        $canonical=self::canonicalName($country);
        if($canonical==='')return null;
        $countries=self::countries();
        $key=mb_strtolower($canonical);
        if(isset($countries['by_name'][$key]))return $countries['by_name'][$key]['code'];
        if(isset(self::ALIASES[$key]))return self::ALIASES[$key];
        return null;
    }

    public static function emoji(?string $country):string
    {
        $code=self::code($country);if(!$code)return '';
        $point=static function(int $value):string{
            return chr(0xF0|($value>>18)).chr(0x80|(($value>>12)&0x3F)).chr(0x80|(($value>>6)&0x3F)).chr(0x80|($value&0x3F));
        };
        return $point(127397+ord($code[0])).$point(127397+ord($code[1]));
    }

    public static function label(?string $country,bool $includeName=true):string
    {
        $name=self::canonicalName($country);$flag=self::emoji($name);
        return $includeName?trim($flag.' '.$name):$flag;
    }
}
