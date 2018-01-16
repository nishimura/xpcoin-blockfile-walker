<?php

namespace Xpcoin\BlockFileWalker;

class Config
{
    public static $datadir;
    public static $dsn;

    public static $PUBKEY_ADDRESS;
    public static $ADDRESS_PREFIX;
    public static $MESSAGE;

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
}
