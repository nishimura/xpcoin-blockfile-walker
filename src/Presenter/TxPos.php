<?php

namespace Xpcoin\BlockFileWalker\Presenter;

use Xpcoin\BlockFileWalker\Xp;

class TxPos
{
    use Printable;

    const INT_KEYS = [
        'nVersion',
    ];
    const HEX_KEYS = [
        'blockhash',
    ];
    const TIME_KEYS = [
    ];
    const AMOUNT_KEYS = [
    ];
    const REVERSE_KEYS = [
    ];


    private $data;
    public function __construct(Xp\DiskTxPos $tx)
    {
        $this->data = $tx->values;
    }
}
