<?php

namespace Xpcoin\BlockFileWalker\Presenter;

use Xpcoin\BlockFileWalker\Xp;

class Tx
{
    use Printable;

    const INT_KEYS = [
        'nVersion',
        'prevout.n',
        'nSequence',
        'nextin.n',
    ];
    const HEX_KEYS = [
        'txid',
        'prevout.hash',
        'scriptSig',
        'coinbase',
        'scriptPubKey',
        'nextin.hash',
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
    public function __construct(Xp\Tx $tx)
    {
        $this->data = $tx->values;
    }
}
