<?php

namespace Xpcoin\BlockFileWalker;

use PDO;

class Config
{
    public static $datadir;
    public static $dsn;

    public static $PUBKEY_ADDRESS;
    public static $ADDRESS_PREFIX;
    public static $MESSAGE;


    public static $CACHE_LIMIT = 3000;
    public static $CACHE_TRUNCATE = 2000;

    public static function truncateCache(&$kv){
        if (count($kv) < self::$CACHE_LIMIT)
            return;

        $i = 0;
        foreach ($kv as $k => $_){
            unset($kv[$k]);
            $i++;

            if ($i > self::$CACHE_TRUNCATE)
                break;
        }
    }

    public static function set($file)
    {
        $config = parse_ini_file($file);
        self::$datadir = $config['datadir'];
        self::$dsn = $config['dsn'];

        if ($config['testnet']){
            self::$PUBKEY_ADDRESS = 111;
            self::$ADDRESS_PREFIX = ['m', 'n'];
            self::$MESSAGE = 'cdf2c0ef';
        }else{
            self::$PUBKEY_ADDRESS = 75;
            self::$ADDRESS_PREFIX = ['X'];
            self::$MESSAGE = 'b4f8e2e5';
        }
    }

    public static function getPdo()
    {
        $db = new PDO(self::$dsn);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        return $db;
    }
}
