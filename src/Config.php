<?php

namespace Xpcoin\BlockFileWalker;

class Config
{
    public static $datadir;
    public static $dsn;

    public static function set($file)
    {
        $config = parse_ini_file($file);
        self::$datadir = $config['datadir'];
        self::$dsn = $config['dsn'];
    }
}
