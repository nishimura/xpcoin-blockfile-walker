<?php

namespace Xpcoin\BlockFileWalker\Xp;

use Xpcoin\BlockFileWalker\Presenter;

use function Xpcoin\BlockFileWalker\toAmount;
use function Xpcoin\BlockFileWalker\walkChunk;
use function Xpcoin\BlockFileWalker\readCompactSize;

use function Xpcoin\BlockFileWalker\packStr;
use function Xpcoin\BlockFileWalker\readStr;
use function Xpcoin\BlockFileWalker\readStrRev;
use function Xpcoin\BlockFileWalker\toInt;

class DiskTxPos
{
    public $key;
    public $values;

    public function __construct($key, $data)
    {
        $this->key = $key;
        foreach ($data as $k => $v)
            $this->values[$k] = $v;

        $this->values['details'] = $this->read();
    }

    public static function getPresenter(DiskTxPos $obj)
    {
        return new Presenter\TxPos($obj);
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
            toInt($this->values['pos.nFile']),
            toInt($this->values['pos.nBlockPos']),
            toInt($this->values['pos.nTxPos']),
        ];
        return Tx::fromBinary($pos);
    }

    public function readSpents()
    {
        $ret = [];
        foreach ($this->values['vSpent'] as $i => $v){
            $pos = [
                toInt($v['nFile']),
                toInt($v['nBlockPos']),
                toInt($v['nTxPos']),
            ];
            $ret[] = Tx::fromBinary($pos);
        }
        return $ret;
    }

    public static function fromBinary($key, $value)
    {
        $prelen = strlen(packStr('tx'));
        readStr($key, $prelen);

        $keybin = readStrRev($key, 32);

        $data = [];
        $data['nVersion'] = readStrRev($value, 4);

        $diskPosBase = [
            'nFile'     => 4,
            'nBlockPos' => 4,
            'nTxPos'    => 4,
        ];

        foreach ($diskPosBase as $k => $v)
            $data['pos.' . $k] = readStrRev($value, $v);

        $size = readCompactSize($value);
        $data['vSpent'] = [];
        for ($i = 0; $i < $size; $i++){
            $data['vSpent'][$i] = [];

            foreach ($diskPosBase as $k => $v)
                $data['vSpent'][$i][$k] = readStrRev($value, $v);
        }

        $data['blockhash'] = strrev(Block::getHashFromPos(
            [toInt($data['pos.nFile']), toInt($data['pos.nBlockPos'])]));

        return new self($keybin, $data);
    }
}
