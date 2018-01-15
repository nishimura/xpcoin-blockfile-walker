<?php

namespace Xpcoin\BlockFileWalker;

use StephenHill\Base58;

class PubKey
{
    const PUBKEY_ADDRESS = 75;

    private $bin;
    private $isHash;
    public function __construct($bin, $isHash = false)
    {
        $this->bin = $bin;
        $this->isHash = $isHash;
    }

    public function getId($raw = true)
    {
        return self::binToId($this->bin);
    }

    public function toString()
    {
        return self::binToAddress($this->bin, $this->isHash);
    }
    public function __toString()
    {
        return $this->toString();
    }

    public function toAddressBin()
    {
        return self::binToAddressBin($this->bin, $this->isHash);
    }

    public static function binToKeyId($bin)
    {
        // Hash160
        $bin = hash('sha256', $bin, true);
        $bin = hash('ripemd160', $bin, true);
        return $bin;
    }

    public static function binToAddressBin($bin, $hash = false)
    {
        if (!$hash)
            $bin = self::binToKeyId($bin);
        $bin = pack('C', self::PUBKEY_ADDRESS) . $bin;

        $checksum = hash('sha256', $bin, true);
        $checksum = hash('sha256', $checksum, true);

        $bin .= substr($checksum, 0, 4);
        return $bin;
    }

    public static function binToAddress($bin, $hash = false)
    {
        $bin = self::binToAddressBin($bin, $hash);

        $base58 = new Base58();
        return $base58->encode($bin);
    }
}
