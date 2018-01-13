<?php

namespace Xpcoin\BlockFileWalker\Presenter;

use Xpcoin\BlockFileWalker\Xp;

class BlockIndex
{
    use Printable;
    const INT_KEYS = [
        'serVersion',
        'nMoneySupply',
        'nFlags',
        'nHeight',
        'nVersion',
        'nNonce',
    ];
    const HEX_KEYS = [
        'key',
        'hashNext',
        'nStakeModifier',
        'hashPrev',
        'hashMerkleRoot',
        'nBits',
        'blockHash',
    ];
    const TIME_KEYS = [
        'nStakeTime',
        'nTime',
    ];
    const AMOUNT_KEYS = [
        'nMint',
    ];
    const REVERSE_KEYS = [
    ];

    private $data;
    public function __construct(Xp\DiskBlockIndex $b)
    {
        $values = ['key' => $b->key];
        $values += $b->values;
        $this->data = $values;
    }
}