<?php

namespace Xpcoin\BlockFileWalker\Presenter;

use Xpcoin\BlockFileWalker\Config;

class Filter
{
    public static function toHashLink($str)
    {
        $str = $str . '';
        $hit = false;
        if (strlen($str) == 64 &&
            preg_match('/^[0-9a-f]+$/', $str))
            $hit = true;

        if (strlen($str) == 34 && in_array($str[0], Config::$ADDRESS_PREFIX))
            $hit = true;

        if (!$hit)
            return $str;

        return '<a href="?q=' . $str . '">' . $str . '</a>';
    }

    public static function toAmount($str)
    {
        return number_format($str, 6);
    }
}
