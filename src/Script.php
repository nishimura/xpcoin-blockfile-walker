<?php

namespace Xpcoin\Explorer;

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

    public function toHex()
    {
        $len = strlen($this->bin);
        $ret = '';
        for ($i = 0; $i < $len; $i++){
            $ret .= bin2hex($this->bin[$i]);
        }
        return $ret;
    }

    public function __toString() { return $this->toHex(); }

    public function extractDestinations()
    {
        $ret = null;
        list($type, $ret) = $this->solver();
        if ($type === false)
            return [$type, $ret];

        switch ($type){
        case self::TX_PUBKEY:
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
        }

        return null;
    }

    private function solver()
    {
        $type = self::TX_NONSTANDARD;
        $len = strlen($this->bin);
        $ret = [];
        foreach (self::TXOP_MAP as $tx => $ops){
            $j = 0;
            for ($i = 0; $i < $len;){
                list($op, $i, $ch) = $this->getOp($i);
                if ($op == self::OP_INVALIDOPCODE)
                    continue 2;

                if ($ops[$j] == self::OP_PUBKEYS){
                    // TODO:
                }
                if ($ops[$j] == self::OP_PUBKEY){
                    $type = self::TX_PUBKEY;
                    $ret[] = $ch;
                }
                else if ($ops[$j] == self::OP_PUBKEYHASH){
                    // TODO
                }
                else if ($ops[$j] == self::OP_SMALLINTEGER){
                    // TODO
                }
                else if ($ops[$j] == self::OP_SMALLDATA){
                    // TODO
                }
                else if (/* TODO */ 1){
                    break;
                }
                $j++;
            }
        }

        return [$type, $ret];
    }

    private function getOp($i)
    {
        $bin = $this->bin;

        $len = strlen($bin);
        if ($len == 0)
            return [self::OP_INVALIDOPCODE, 0];
        if ($len <= $i)
            return [self::OP_INVALIDOPCODE, 0];

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
