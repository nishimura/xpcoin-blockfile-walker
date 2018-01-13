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

        $ret = '';
        $ret .= "key: $this->key\n";

        foreach ($this->values as $k => $v){
            $show = $v;
            switch ($k){
            case 'pos.nBlockPos':
            case 'pos.nTxPos':
            case 'pos.nFile':
                continue 2;
                break;

            case 'nMint':
                $show = toAmount($v->toInt());
                break;

            case 'nHeight':
            case 'nVersion':
                $show = $v->toInt();
                break;

            case is_array($v):
                $show = '';
                foreach ($v as $_k => $_v){
                    $show .= "\n $k: $_k\n";
                    foreach ($_v as $__k => $__v)
                        $show .= sprintf("  %16s: %s\n", $__k, $__v);
                }

            default:
                break;
            }
            if ($k == 'details')
                $ret .= sprintf("  ===== %s =====:\n%s\n", $k, $show);
            else
                $ret .= sprintf("  %14s: %s\n", $k, $show);

        }
        $ret .= "\n";

        return $ret;
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

        return new self($keybin, $data);
    }
}
