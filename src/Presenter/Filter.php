<?php

namespace Xpcoin\BlockFileWalker\Presenter;

class Filter
{
    public static function toHashLink($str)
    {
        if (strlen($str) !== 64)
            return $str;

        if (!preg_match('/^[0-9a-f]+$/', $str))
            return $str;

        return '<a href="?q=' . $str . '">' . $str . '</a>';
    }
}
