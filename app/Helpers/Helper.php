<?php

namespace App\Helpers;

class Helper
{
    public static function NextColKey($key = '')
    {

        if ($key == '') {
            return 'A';
        }

        return  ++$key;
    }

    public static function ucfirstlower($string)
    {
        if (empty($string)) {
            return $string;
        }

        return ucwords(strtolower(trim($string)));
    }
}
