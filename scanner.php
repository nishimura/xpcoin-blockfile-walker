<?php

use Xpcoin\BlockFileWalker\Config;
use Xpcoin\BlockFileWalker\Db;
use Xpcoin\BlockFileWalker\Xp;
use function Xpcoin\BlockFileWalker\readCompactSizeRaw;
use function Xpcoin\BlockFileWalker\readStr;
use function Xpcoin\BlockFileWalker\packKey;
use function Xpcoin\BlockFileWalker\packStr;
use function Xpcoin\BlockFileWalker\toInt;
use function Xpcoin\BlockFileWalker\toIntDb;

$dir = __DIR__;
chdir($dir);

require_once  "$dir/vendor/autoload.php";

Config::set("$dir/config.ini");

$file = Config::$datadir . '/blk0001.dat';
$fp = fopen($file, 'rb');

$bdb = new Db(Config::$datadir);
$db = Config::getPdo();

$prevLastPos = 0;
// TODO: fpos
foreach ($db->query('select * from posinfo') as $row){
    $prevLastPos = $row->npos;
    break;
}
$lastPos = $prevLastPos;
fseek($fp, $prevLastPos);

function readInt32(&$str)
{
    return hexdec(bin2hex(strrev(readStr($str, 4))));
}

$packIndex = packStr('blockindex');
$packTx = packStr('tx');

$posMap = []; // for seek prev 100 block

$txMap = []; // [pos => [$tx, $nextPos]]
$diskTxPosMap = []; // [bdb key => $tx]

$max = 1000;
if (isset($argv[1]))
    $max = $argv[1];

for ($i = 1; $i <= $max; $i++){
    if (feof($fp))
        break;
    $b = fread($fp, 4);
    if (strlen($b) == 0)
        break;

    $bhex = bin2hex($b);
    if ($bhex !== Config::$MESSAGE){
        // pchMessageStart = 0xb4, 0xf8, 0xe2, 0xe5
        // testnet: 0xcd, 0xf2, 0xc0, 0xef
        throw new Exception('seek error:' . $bhex);
    }

    $db->beginTransaction();


    $blocksize = hexdec(bin2hex(strrev(fread($fp, 4))));
    //var_dump($blocksize);
    $nBlockPos = ftell($fp);

    $revhash = XP\Block::getHashFromFp($fp);
    $blockhash = strrev($revhash);
    $query = $packIndex . $revhash;
    $hit = false;
    foreach ($bdb->range($query) as $key => $value){
        $hit = true;
        readStr($value, 36);
        $nFile = readInt32($value);
        $_nBlockPos = readInt32($value);
        $nHeight = readInt32($value);

        $hash7 = toIntDb($revhash);

        try {
            $db->query(
                sprintf('INSERT INTO bindex values (%d, %d)',
                        $hash7, $nHeight));
        }catch (\Exception $e){
            echo $e->getMessage();
            $db->rollback();
            foreach ($bdb->range($packIndex . $revhash) as $_key => $_value){
                readStr($_value, 36);
                $_nFile = hexdec(bin2hex(strrev(readStr($_value, 4))));
                $_nPos = hexdec(bin2hex(strrev(readStr($_value, 4))));
                $_nHeight = hexdec(bin2hex(strrev(readStr($_value, 4))));
                echo "nFile:$_nFile, nPos:$_nPos\n";
                if ($_nPos != $nBlockPos){
                    fseek($fp, $nblockPos + $blocksize);
                    $lastPos = ftell($fp);
                    continue 2;
                }else{
                    $db->beginTransaction();
                    $db->query(sprintf('UPDATE bindex set height = %d where hash = %d',
                                       $_nHeight, $hash7));
                    $nBlockPos = $_nPos;
                }
                break;
            }
        }
        break;
    }

    if (!$hit){
        // no indexed, invalid data
        fseek($fp, $nBlockPos + $blocksize);
        $lastPos = ftell($fp);
        $db->rollback();
        continue;
    }

    $size = readCompactSizeRaw($fp);
    //var_dump($size);
    for ($j = 0; $j < $size; $j++){
        $txpos = ftell($fp);
        if (isset($txMap[$txpos])){
            list($tx, $txnextpos) = $txMap[$txpos];
            fseek($fp, $txnextpos);
        }else{
            $tx = Xp\Tx::readFp($fp);
            $txnextpos = ftell($fp);
            $txMap[$txpos] = [$tx, $txnextpos];
        }

        $txhash = $tx->values['txid'];
        $txhash7 = toIntDb(strrev($txhash));

        $hit = $nHeight == 0;
        foreach ($bdb->range($packTx . strrev($txhash)) as $key => $value){
            $hit = true;
        }
        if (!$hit){
            // block is indexed, tx is not indexed, not main branch
            fseek($fp, $nBlockPos + $blocksize);
            $lastPos = ftell($fp);
            $db->rollback();
            continue 2;
        }


        if (isset($tx->values['vin']['coinbase'])){
            // nothing
        }else{
            foreach ($tx->values['vin'] as $k => $in){
                $prevHash = $in['prevout.hash'];
                $prevN = toInt($in['prevout.n']);

                $revhash = strrev($prevHash);
                $query = $packTx . $revhash;
                foreach ($bdb->range($query) as $key => $value){
                    if (isset($diskTxPosMap[$key])){
                        $prevtx = $diskTxPosMap[$key];
                    }else{
                        $prevtx = Xp\DiskTxPos::fromBinary($key, $value);
                        $diskTxPosMap[$key] = $prevtx;
                    }

                    if (!isset($prevtx->values['details']->values['vout'][$prevN]))
                        break;
                    $out = $prevtx->values['details']->values['vout'][$prevN];
                    $dests = $out['scriptPubKey']->extractDestinations();
                    if (!isset($dests[1]))
                        break;

                    foreach ($dests[1] as $addr){
                        $addr = $addr->toAddressBin();
                        $revaddr = strrev($addr);
                        $addr7 = toIntDb($revaddr);
                        $db->query(
                            sprintf('INSERT INTO addr values (%d, %d, %d, true)',
                                    $addr7, $nHeight, $txhash7));
                    }
                    $db->query(
                        sprintf('INSERT INTO txindex values(%d, %d, %d, %d)',
                                toIntDb($revhash), $prevN, $txhash7, $k));

                    break;
                }
            }
        }

        foreach ($tx->values['vout'] as $k => $out){
            $dests = $out['scriptPubKey']->extractDestinations();
            if (isset($dests[1])){
                foreach ($dests[1] as $addr){
                    //echo $addr . "\n";
                    $addr = $addr->toAddressBin();
                    $revaddr = strrev($addr);
                    $addr7 = toIntDb($revaddr);
                    $db->query(
                        sprintf('INSERT INTO addr values (%d, %d, %d, false)',
                                $addr7, $nHeight, $txhash7));
                }
            }
        }
    }

    fseek($fp, $nBlockPos + $blocksize);
    $lastPos = ftell($fp);

    $db->query('delete from posinfo');
    $db->query(
            sprintf('INSERT INTO posinfo values (1, %d)',
                    $lastPos));
    $db->commit();

    if ($i % 1000 == 0){
        echo '.';
    }
    if ($i % 10000 == 0){
        echo "$i\n";
    }

}

exit;
