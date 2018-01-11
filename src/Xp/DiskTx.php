<?php

namespace Xpcoin\Explorer\Xp;

use Xpcoin\Explorer\Uint32base;
use IO_Bit;

use function Xpcoin\Explorer\toAmount;
use function Xpcoin\Explorer\walkChunk;
use function Xpcoin\Explorer\readCompactSize;

class DiskTx
{
    public $key;
    public $values;
    public function __construct($key, $data)
    {
        $this->key = $key;
        foreach ($data as $k => $v)
            $this->values[$k] = $v;
    }

    public function __toString() { return $this->toString(); }
    public function toString()
    {
        $ret = '';
        $ret .= "key: $this->key\n";

        foreach ($this->values as $k => $v){
            $show = $v;
            switch ($k){
            case 'nMint':
                $show = toAmount($v->toInt());
                break;

            case 'nHeight':
            case 'nVersion':
                $show = $v->toInt();
                break;

            default:
                break;
            }
            $ret .= sprintf("  %14s: %s\n", $k, $show);
        }
        $ret .= "\n";

        return $ret;
    }

    public static function fromBinary($key, $value)
    {
        $uint64a = [32, 32];

        $iobit = new IO_Bit();

        $iobit->input($key);
        $iobit->getUIBits(3 * 8);
        $chunks = [32,32,32,32,32,32,32,32];
        $bs = [];
        foreach ($chunks as $chunk)
            $bs[] = $iobit->getUIBits($chunk);
        $keyhash = new Uint32base($bs);


        $iobit->input($value);

        $data = [];
        $chunkBase = [
            'nVersion'  => [32],
        ];
        $data += walkChunk($iobit, $chunkBase);

        $diskPosBase = [
            'nFile'     => [32],
            'nBlockPos' => [32],
            'nTxPos'    => [32],
        ];

        $posBase = [];
        foreach ($diskPosBase as $k => $v)
            $posBase['pos.' . $k] = $v;

        $data += walkChunk($iobit, $posBase);

        $size = readCompactSize($iobit);
        $data['vSpent.vector_size'] = $size;
        for ($i = 0; $i < $size; $i++){
            $posBase = [];
            foreach ($diskPosBase as $k => $v)
                $posBase["vSpent.$i." . $k] = $v;

            $data += walkChunk($iobit, $posBase);
        }

        return new self($keyhash->toString(), $data);
    }
}
