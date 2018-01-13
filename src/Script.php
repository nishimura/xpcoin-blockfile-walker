<?php

namespace Xpcoin\BlockFileWalker;

class Script
{
    const TX_NONSTANDARD = 'non-standard';
    // 'standard' transaction types:
    const TX_PUBKEY = 'pubkey';
    const TX_PUBKEYHASH = 'pubkey-hash';
    const TX_SCRIPTHASH = 'script-hash';
    const TX_MULTISIG = 'multisig';
    const TX_NULL_DATA = 'null-data';



    const OP_0 = 0x00;
    const OP_PUSHDATA1 = 0x4c;
    const OP_PUSHDATA2 = 0x4d;
    const OP_PUSHDATA4 = 0x4e;

    const OP_RETURN = 0x6a;
    const OP_DUP = 0x76;

    const OP_EQUALVERIFY = 0x88;

    const OP_HASH160 = 0xa9;
    const OP_CHECKSIG = 0xac;
    const OP_CHECKMULTISIG = 0xae;

    const OP_SMALLDATA = 0xf9;
    const OP_SMALLINTEGER = 0xfa;
    const OP_PUBKEYS = 0xfb;
    const OP_PUBKEYHASH = 0xfd;
    const OP_PUBKEY = 0xfe;
    const OP_INVALIDOPCODE = 0xff;

    const TXOP_MAP = [
        self::TX_PUBKEY => [
            self::OP_PUBKEY,
            self::OP_CHECKSIG,
        ],
        self::TX_PUBKEYHASH => [
            self::OP_DUP,
            self::OP_HASH160,
            self::OP_PUBKEYHASH,
            self::OP_EQUALVERIFY,
            self::OP_CHECKSIG,
        ],
        self::TX_MULTISIG => [
            self::OP_SMALLINTEGER,
            self::OP_PUBKEYS,
            self::OP_SMALLINTEGER,
            self::OP_CHECKMULTISIG,
        ],
        self::TX_NULL_DATA => [
            self::OP_RETURN,
            self::OP_SMALLDATA,
        ],
    ];


    private $bin;
    public function __construct($bin)
    {
        $this->bin = $bin;
    }

    public function extractFromAddresses()
    {
        // TODO: How to do it?
        return [];



        $len = strlen($this->bin);
        for ($i = 0; $i < $len;){
            list($op, $i, $ch) = $this->getOp($i);
            if ($op == self::OP_INVALIDOPCODE)
                break;

            $parts = explode(' ', $ch);

            $len = count($parts);
            if ($len < 2)
                break;

            $ret = [];
            for ($i = 1; $i < $len; $i++){
                if (strlen($parts[$i]) != 33 &&
                    strlen($parts[$i]) != 20)
                    // TODO: other any patterns
                    break;

                $ret[] = new PubKey($parts[$i]);
            }

            return $ret;
        }

        return [];
    }

    public function toHex()
    {
        return bin2hex($this->bin);
    }

    public function toString() { return $this->toHex(); }
    public function __toString() { return $this->toHex(); }

    public function extractDestinations()
    {
        $ret = null;
        list($type, $ret) = $this->solver();
        if ($type == self::TX_NONSTANDARD)
            return [$type, $ret];

        switch ($type){
        case self::TX_PUBKEY:
        case self::TX_PUBKEYHASH:
            $ret = $this->extractDestination();
            break;

        case self::TX_NULL_DATA:
        case self::TX_MULTISIG:
            // TODO
            break;
        }

        return [$type, $ret];
    }

    private function extractDestination()
    {
        list($type, $ret) = $this->solver();
        if ($type == self::TX_PUBKEY){
            return [new PubKey($ret[0])];
        }else if ($type == self::TX_PUBKEYHASH){
            return [new PubKey($ret[0], true)];
        }

        return null;
    }

    private function solver()
    {
        $len = strlen($this->bin);
        foreach (self::TXOP_MAP as $tx => $ops){
            $i = 0;
            $j = 0;
            $ret = [];
            $type = self::TX_NONSTANDARD;
            for (;;){
                if ($j >= count($ops) && $i >= $len){
                    return [$type, $ret];
                }
                list($op, $i, $ch) = $this->getOp($i);
                if ($op == self::OP_INVALIDOPCODE)
                    break;

                if ($ops[$j] == self::OP_PUBKEYS){
                    while (strlen($ch) >= 33 && strlen($ch) <= 120){
                        $type = self::TX_MULTISIG;
                        $ret[] = $ch;
                        list($op, $i, $ch) = $this->getOp($i);
                        if ($op == self::OP_INVALIDOPCODE)
                            break;
                    }
                    $j++;
                    if (!isset($ops[$j]))
                        break;
                }
                if ($ops[$j] == self::OP_PUBKEY){
                    if (strlen($ch) < 33 || strlen($ch) > 120)
                        break;
                    $type = self::TX_PUBKEY;
                    $ret[] = $ch;
                }
                else if ($ops[$j] == self::OP_PUBKEYHASH){
                    if (strlen($ch) !== 20)
                        break;
                    $type = self::TX_PUBKEYHASH;
                    $ret[] = $ch;
                }
                else if ($ops[$j] == self::OP_SMALLINTEGER){
                    // TODO
                }
                else if ($ops[$j] == self::OP_SMALLDATA){
                    // TODO
                }
                else if ($op != $ops[$j]){
                    break;
                }
                $j++;
            }
        }

        return [self::TX_NONSTANDARD, []];
    }

    private function getOp($i, $bin = null)
    {
        if ($bin === null)
            $bin = $this->bin;

        $len = strlen($bin);
        if ($len == 0)
            return [self::OP_INVALIDOPCODE, 0, ''];
        if ($len <= $i)
            return [self::OP_INVALIDOPCODE, 0, ''];

        $ch = '';
        $op = ord($bin[$i++]);
        if ($op <= self::OP_PUSHDATA4){
            $size = self::OP_0;
            if ($op < self::OP_PUSHDATA1){
                $size = $op;
            }else if ($op == self::OP_PUSHDATA1){
                $size = ord($bin[$i++]);
            }else if ($op == self::OP_PUSHDATA2){
                $size = ord($bin[$i++])
                      | (ord($bin[$i++] << 8));
            }else if ($op == self::OP_PUSHDATA4){
                $size = ord($bin[$i++])
                      | (ord($bin[$i++]) << 8)
                      | (ord($bin[$i++]) << 16)
                      | (ord($bin[$i++]) << 24);
            }
            $ch = substr($bin, $i, $size);
            $i+= $size;
        }

        return [$op, $i, $ch];
    }
}
