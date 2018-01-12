<?php

namespace Xpcoin\BlockFileWalker\Xp;

use Xpcoin\BlockFileWalker\Uint32base;
use IO_Bit;

use function Xpcoin\BlockFileWalker\toAmount;
use function Xpcoin\BlockFileWalker\walkChunk;

class DiskBlockIndex
{
    const BLOCK_PROOF_OF_STAKE = 1 << 0;
    public $key;
    public $values;
    public function __construct($key, $data)
    {
        $this->key = $key;
        foreach ($data as $k => $v)
            $this->values[$k] = $v;

        $this->values['details'] = $this->read();

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

            case 'nStakeTime':
            case 'nTime':
                $show = date('Y-m-d H:i:s', $v->toInt());
                break;

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
            $this->values['nFile']->toInt(),
            $this->values['nBlockPos']->toInt(),
        ];
        return Block::fromBinary($pos);
    }


    public static function fromBinary($key, $value)
    {
        $iobit = new IO_Bit();

        $iobit->input($key);
        $iobit->getUIBits(11 * 8);
        $chunks = [32,32,32,32,32,32,32,32];
        $bs = [];
        foreach ($chunks as $chunk)
            $bs[] = $iobit->getUIBits($chunk);
        $keyhash = new Uint32base($bs);

        $chunkBase = [
            'nVersion'     => [32],
            'hashNext'     => [32,32,32,32,32,32,32,32],
            'nFile'        => [32],
            'nBlockPos'    => [32],
            'nHeight'      => [32],
            'nMint'        => [32, 32],
            'nMoneySupply' => [32, 32],
            'nFlags'       => [32],
            'nStakeModifier' => [32, 32],
        ];

        $iobit->input($value);

        $data = [];
        $data += walkChunk($iobit, $chunkBase);

        if ($data['nFlags']->toInt() & self::BLOCK_PROOF_OF_STAKE){
            $chunkBase = [
                'prevoutStake.hash' => [32,32,32,32,32,32,32,32],
                'prevoutStake.n' => [32],
                'nStakeTime'   => [32],
                'hashProofOfStake' => [32,32,32,32,32,32,32,32],
            ];
            $data += walkChunk($iobit, $chunkBase);
        }

        $chunkBase = [
            'nVersion' => [32],
            'hashPrev' => [32,32,32,32,32,32,32,32],
            'hashMerkleRoot' => [32,32,32,32,32,32,32,32],
            'nTime'    => [32],
            'nBits'    => [32],
            'nNonce'   => [32],
            'blockHash' => [32,32,32,32,32,32,32,32],
        ];
        $data += walkChunk($iobit, $chunkBase);


        return new self($keyhash->toString(), $data);
    }
}
