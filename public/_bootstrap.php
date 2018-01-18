<?php

use Xpcoin\BlockFileWalker\Config;
use function Xpcoin\BlockFileWalker\toInt;
use function Xpcoin\BlockFileWalker\toAmount;


$dir = dirname(__DIR__);
chdir($dir);


require_once  "$dir/vendor/autoload.php";

Config::set("$dir/config.ini");

$q = null;
$full = null;
if (isset($_GET['q']))
    $q = $_GET['q'];
if (isset($_GET['full']))
    $full = $_GET['full'];

$app = new Xpcoin\BlockFileWalker\App($dir);
//$app->run($q)->show('public', 'cache');
$params = $app->run($q, $full)->getParams();





function blocksView($blocks){
    foreach ($blocks as $block){
        $o = new \stdClass;
        $p = $block->toPresenter();
        $cols = [
            'hash', 'nHeight', 'nMint', 'nMoneySupply', 'nFlags',
            'nVersion', 'hashNext', 'hashPrev', 'nTime', 'nBits', 'nNonce',
            'nStakeModifier',
            'prevoutStake.hash', 'prevoutStake.n', 'nStakeTime',
            'hashProofOfStake',
        ];
        $o->data = new \stdClass();
        foreach ($cols as $col){
            if (isset($p->$col))
                $o->data->$col = $p->$col;
        }
        $o->isCoinStake = $p->details->isCoinStake;
        if ($o->isCoinStake)
            $o->minedCss = 'staked';
        else
            $o->minedCss = 'mined';

        $o->txs = $p->details->vtx;

        yield $o;
    }
}

function txsView($txs){
    $amount = 0;
    foreach ($txs as $tx){
        $o = new \stdClass;
        $o->blockhash = $tx->toPresenter()->blockhash;
        $tx->values['details']->readNextVin();
        $tx->values['details']->readPrevVout();
        $p = $tx->values['details']->toPresenter();
        $cols = [
            'txid', 'nVersion', 'nTime',
            'prevout.hash', 'prevout.n',
        ];
        $o->data = new \stdClass();
        foreach ($cols as $col){
            if (isset($p->$col))
                $o->data->$col = $p->$col;
        }

        // TODO: refactoring, move to presenter
        if (isset($p->vin['coinbase'])){
            $o->data->coinbase = $p->vin['coinbase'];
            $o->data->nSequence = bin2hex($p->vin['nSequence']);
            if ($o->data->nSequence == 'ffffffff') // default
                unset($o->data->nSequence);
        }else{
            $o->vin = [];
            foreach ($p->vin as $in){
                $obj = new \stdClass();
                $obj->data = new \stdClass();
                $obj->prevout = new \stdClass();

                $obj->data->prevoutHash = bin2hex($in['prevout.hash']);
                $obj->data->prevoutN = toInt($in['prevout.n']);
                $obj->data->scriptSig = $in['scriptSig'];
                $obj->data->nSequence = bin2hex($in['nSequence']);
                if ($obj->data->nSequence == 'ffffffff') // default
                    unset($obj->data->nSequence);

                $dests = $in['scriptPubKey']->extractDestinations();
                $obj->prevout->nValue = toAmount($in['nValue']);
                if ($dests){
                    $obj->prevout->type = $dests[0];
                    $obj->prevout->addrs = $dests[1];
                }

                $o->vin[] = $obj;
            }

        }

        $o->vout = [];
        foreach ($p->vout as $out){
            $obj = new \stdClass();
            $obj->nValue = toAmount($out['nValue']);
            $amount += $obj->nValue;
            $obj->scriptPubKey = $out['scriptPubKey']->toString();

            $obj->addrs = [];
            $dests = $out['scriptPubKey']->extractDestinations();
            if ($dests){
                $obj->type = $dests[0];
                $obj->addrs = $dests[1];
            }

            if (strlen($out['nextin.hash'])){
                $obj->nextHash = bin2hex($out['nextin.hash']);
                $obj->nextN = toInt($out['nextin.n']);
            }

            $o->vout[] = $obj;
        }

        yield $o;
    }

    global $params;
    if (isset($params->address)){
        $ret = new \stdClass();
        $ret->totalAmount = $amount;
        yield $ret;
    }
}

$params->blocks = blocksView($params->blocks);
$params->txs = txsView($params->txs);

$app->show('public', 'cache', $params);
