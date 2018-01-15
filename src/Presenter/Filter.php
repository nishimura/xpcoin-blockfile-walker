<?php

namespace Xpcoin\BlockFileWalker\Presenter;

class Filter
{
    public static function toHashLink($str)
    {
        $str = $str . '';
        $hit = false;
        if (strlen($str) == 64 &&
            preg_match('/^[0-9a-f]+$/', $str))
            $hit = true;

        if (strlen($str) == 34 && $str[0] == 'X')
            $hit = true;

        if (!$hit)
            return $str;

        return '<a href="?q=' . $str . '">' . $str . '</a>';
    }
}
