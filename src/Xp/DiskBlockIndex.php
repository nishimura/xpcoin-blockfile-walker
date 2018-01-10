<?php

namespace Xpcoin\Explorer\Xp;

use Xpcoin\Explorer\Uint32base;
use IO_Bit;

use function Xpcoin\Explorer\toAmount;

class DiskBlockIndex
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
        $uint256a = [32,32,32,32,32,32,32,32];
        $uint64a = [32, 32];

        $iobit = new IO_Bit();

        $iobit->input($key);
        $iobit->getUIBits(11 * 8);
        $chunks = $uint256a;
        $bs = [];
        foreach ($chunks as $chunk)
            $bs[] = $iobit->getUIBits($chunk);
        $keyhash = new Uint32base($bs);

        $chunkBase = [
            'nVersion'      => [32],
            'hashNext'     => $uint256a,
            'nFile'        => [32],
            'nBlockPos'    => [32],
            'nHeight'      => [32],
            'nMint'        => $uint64a,
            'nMoneySupply' => $uint64a,
            'nFlags'       => [32],
            'nStakeModifier' => [32, 32],
            //'replace' => null, // TODO
        ];



        $iobit->input($value);

        $data = [];
        foreach ($chunkBase as $name => $chunks){
            $bs = [];
            foreach ($chunks as $chunk){
                $bs[] = $iobit->getUIBits($chunk);
            }

            $data[$name] = new Uint32base($bs);
        }
        return new self($keyhash->toString(), $data);
    }
}
