<?php

namespace Xpcoin\BlockFileWalker\Presenter;

use Xpcoin\BlockFileWalker\Xp;

class BlockIndex
{
    use Printable;
    const INT_KEYS = [
        'serVersion',
        'nFlags',
        'nHeight',
        'nVersion',
        'nNonce',
        'nFile',
        'nBlockPos',
        'prevoutStake.n',
    ];
    const HEX_KEYS = [
        'hash',
        'hashNext',
        'nStakeModifier',
        'hashPrev',
        'hashMerkleRoot',
        'nBits',
        'blockHash',
        'prevoutStake.hash',
        'hashProofOfStake',
    ];
    const TIME_KEYS = [
        'nStakeTime',
        'nTime',
    ];
    const AMOUNT_KEYS = [
        'nMint',
        'nMoneySupply',
    ];
    const REVERSE_KEYS = [
    ];

    private $data;
    public function __construct(Xp\DiskBlockIndex $b)
    {
        $values = ['hash' => $b->hash];
        $values += $b->values;
        $this->data = $values;
    }
}
