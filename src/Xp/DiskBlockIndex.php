<?php

namespace Xpcoin\BlockFileWalker\Xp;

use Xpcoin\BlockFileWalker\Uint32base;
use IO_Bit;
use Xpcoin\BlockFileWalker\Presenter;

use function Xpcoin\BlockFileWalker\packStr;
use function Xpcoin\BlockFileWalker\readStr;
use function Xpcoin\BlockFileWalker\readStrRev;
use function Xpcoin\BlockFileWalker\toInt;

class DiskBlockIndex
{
    const BLOCK_PROOF_OF_STAKE = 1 << 0;
    public $key;
    public $values;
    public function __construct($key, $data)
    {
        $this->key = $key;
        $this->values = $data;

        $this->values['details'] = $this->read();

    }

    public static function getPresenter(DiskBlockIndex $obj)
    {
        return new Presenter\BlockIndex($obj);
    }
    public function toPresenter()
    {
        return self::getPresenter($this);
    }

    public function __toString() { return $this->toString(); }
    public function toString()
    {
        return $this->toPresenter()->toString();
    }

    public function read()
    {
        $pos = [
            toInt($this->values['nFile']),
            toInt($this->values['nBlockPos']),
        ];
        return Block::fromBinary($pos);
    }


    public static function fromBinary($key, $value)
    {
        $prelen = strlen(packStr('blockindex'));
        readStr($key, $prelen);

        $keybin = readStrRev($key, 32);
        $data = [];

        $chunks = [
            'serVersion'   => 4,
            'hashNext'     => 32,
            'nFile'        => 4,
            'nBlockPos'    => 4,
            'nHeight'      => 4,
            'nMint'        => 8,
            'nMoneySupply' => 8,
            'nFlags'       => 4,
            'nStakeModifier' => 8,
        ];

        foreach ($chunks as $k => $byte){
            $data[$k] = readStrRev($value, $byte);
        }

        if (toInt($data['nFlags']) & self::BLOCK_PROOF_OF_STAKE){
            $chunks = [
                'prevoutStake.hash' => 32,
                'prevoutStake.n' => 4,
                'nStakeTime'   => 4,
                'hashProofOfStake' => 32,
            ];
            foreach ($chunks as $k => $byte){
                $data[$k] = readStrRev($value, $byte);
            }
        }

        $chunks = [
            'nVersion' => 4,
            'hashPrev' => 32,
            'hashMerkleRoot' => 32,
            'nTime'    => 4,
            'nBits'    => 4,
            'nNonce'   => 4,
            'blockHash' => 32,
        ];

        foreach ($chunks as $k => $byte){
            $data[$k] = readStrRev($value, $byte);
        }
        //var_dump(bin2hex(strrev($data['nNonce'])));exit;

        return new self($keybin, $data);
    }
}
