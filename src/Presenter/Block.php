<?php

namespace Xpcoin\BlockFileWalker\Presenter;

use Xpcoin\BlockFileWalker\Xp;

class Block
{
    use Printable;

    const INT_KEYS = [
        'nVersion'
    ];
    const HEX_KEYS = [
        'hashPrevBlock',
        'hashMerkleRoot',
        'nBits',
        'vchBlockSig',
        'nNonce',
    ];
    const TIME_KEYS = [
        'nTime',
    ];
    const AMOUNT_KEYS = [
    ];
    const REVERSE_KEYS = [
    ];


    private $data;
    public function __construct(Xp\Block $block)
    {
        $this->data = $block->values;
    }
}
