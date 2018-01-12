<?php

namespace Xpcoin\BlockFileWalker;

use StephenHill\Base58;

class PubKey
{
    const PUBKEY_ADDRESS = 75;

    private $bin;
    public function __construct($bin)
    {
        $this->bin = $bin;
    }

    public function getId($raw = true)
    {
        return self::binToId($this->bin);
    }

    public function __toString()
    {
        return self::binToAddress($this->bin);
    }

    public static function binToId($bin)
    {
        // Hash160
        $bin = hash('sha256', $bin, true);
        $bin = hash('ripemd160', $bin, true);
        return $bin;
    }

    public static function binToAddress($bin)
    {
        $bin = self::binToId($bin);
        $bin = pack('C', self::PUBKEY_ADDRESS) . $bin;

        $checksum = hash('sha256', $bin, true);
        $checksum = hash('sha256', $checksum, true);

        $bin .= substr($checksum, 0, 4);

        $base58 = new Base58();
        return $base58->encode($bin);
    }
}
