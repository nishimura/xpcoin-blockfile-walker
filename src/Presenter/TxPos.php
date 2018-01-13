<?php

namespace Xpcoin\BlockFileWalker\Presenter;

use Xpcoin\BlockFileWalker\Xp;

class TxPos
{
    use Printable;

    const INT_KEYS = [
        'nVersion',
        'prevout.n',
        'nSequence',
    ];
    const HEX_KEYS = [
        'txid',
        'prevout.hash',
        'scriptSig',
        'coinbase',
        'scriptPubKey',
    ];
    const TIME_KEYS = [
        'nTime',
        'nLockTime',
    ];
    const AMOUNT_KEYS = [
        'nValue',
    ];
    const REVERSE_KEYS = [
    ];


    private $data;
    public function __construct(Xp\DiskTxPos $tx)
    {
        $this->data = $tx->values;
    }
}
