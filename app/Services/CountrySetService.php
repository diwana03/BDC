<?php
declare(strict_types=1);

namespace App\Services;

final class CountrySetService
{
    public const MAX_COUNTRIES=5;

    /** @return list<string> */
    public static function normalize(array $values):array
    {
        $countries=[];$keys=[];
        foreach(array_slice($values,0,self::MAX_COUNTRIES) as $value){
            $country=CountryFlagService::canonicalName(is_scalar($value)?(string)$value:'');
            $key=mb_strtolower($country);
            if($country===''||isset($keys[$key]))continue;
            $countries[]=$country;$keys[$key]=true;
        }
        return $countries;
    }

    /** @return list<string> */
    public static function fromRow(array $row):array
    {
        $stored=json_decode((string)($row['countries_json']??''),true);
        $values=is_array($stored)?$stored:[];
        array_unshift($values,(string)($row['country']??''));
        return self::normalize($values);
    }

    /** @return list<string> */
    public static function fromRequest(array $input):array
    {
        $values=[];
        for($i=1;$i<=self::MAX_COUNTRIES;$i++)$values[]=$input['country_'.$i]??($i===1?($input['country']??''):'');
        return self::normalize($values);
    }

    public static function json(array $countries):?string
    {
        $countries=self::normalize($countries);
        return $countries?json_encode($countries,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;
    }

    /** @return array{country:?string,countries_json:?string} */
    public static function databaseValues(array $countries):array
    {
        $countries=self::normalize($countries);
        return ['country'=>$countries[0]??null,'countries_json'=>self::json($countries)];
    }
}
