<?php

use Xpcoin\BlockFileWalker\Config;
use Xpcoin\BlockFileWalker\Db;
use Xpcoin\BlockFileWalker\Xp;
use Xpcoin\BlockFileWalker\Exception;
use function Xpcoin\BlockFileWalker\readCompactSizeRaw;
use function Xpcoin\BlockFileWalker\readStr;
use function Xpcoin\BlockFileWalker\packKey;
use function Xpcoin\BlockFileWalker\packStr;
use function Xpcoin\BlockFileWalker\toInt;
use function Xpcoin\BlockFileWalker\toByteaDb;
use function Xpcoin\BlockFileWalker\decToBin;

$dir = __DIR__;
chdir($dir);

$lockdir = $dir . '/.scanner-lock';

if (!@mkdir($lockdir)){
    //die("lock dir: $lockdir exists");
    exit(1);
}

function unlockdir()
{
    global $lockdir;
    rmdir($lockdir);
}
register_shutdown_function('unlockdir');


set_error_handler(
    function ($errno, $errstr, $errfile, $errline) {
        throw new ErrorException(
            $errstr, 0, $errno, $errfile, $errline
        );
    }
); // BDB PAGE_NOTFOUND erro => Exception


require_once  "$dir/vendor/autoload.php";

Config::set("$dir/config.ini");

$file = Config::$datadir . '/blk0001.dat';
$fp = fopen($file, 'rb');

$bdb = new Db(Config::$datadir);
$db = Config::getPdo();

$prevLastPos = null;
// TODO: fpos
foreach ($db->query('select * from posinfo') as $row){
    $prevLastPos = $row->npos;
    break;
}
if ($prevLastPos === null){
    $db->query('INSERT INTO posinfo values(1, 0)');
    $prevLastPos = 0;
}
fseek($fp, $prevLastPos);

function readInt32(&$str)
{
    return hexdec(bin2hex(strrev(readStr($str, 4))));
}

function query($query, $params){
    global $db;
    return $db->prepare($query)->execute($params);
}

$packIndex = packStr('blockindex');
$packTx = packStr('tx');

$max = 1000;
if (isset($argv[1]))
    $max = $argv[1];

$heightMap = [-1 => true];

$db->beginTransaction();

for ($i = 1; $i <= $max; $i++){
    if (feof($fp))
        break;
    $b = fread($fp, 4);
    if (strlen($b) == 0)
        break;

    $bhex = bin2hex($b);
    if ($bhex !== Config::$MESSAGE){
        throw new Exception('seek error:' . $bhex);
    }

    /*
     * start
     */


    $blocksize = hexdec(bin2hex(strrev(fread($fp, 4))));
    //var_dump($blocksize);
    $nBlockPos = ftell($fp);

    $revBlockHash = XP\Block::getHashFromFp($fp);
    $query = $packIndex . $revBlockHash;
    $hit = false;
    foreach ($bdb->range($query, 1) as $key => $value){
        $hit = true;
        readStr($value, 36); // serVersion, hashNext
        $nFile = readInt32($value);
        $_nBlockPos = readInt32($value);
        if ($nBlockPos !== $_nBlockPos){
            // not exists bdb database
            fseek($fp, $nBlockPos + $blocksize);
            continue 2;
        }

        $nHeight = readInt32($value);
        if (!isset($heightMap[$nHeight - 1])){
            $stmt = $db->query('select * from bindex where height = '
                               . ($nHeight - 1));
            $hit = false;
            foreach ($stmt as $_){
                $hit = true;
            }
            if (!$hit){
                // re-check prev
                foreach ($bdb->range($query, 1) as $key => $value){
                    $blockpos = Xp\DiskBlockIndex::fromBinary($key, $value);
                    $revPrev = strrev($blockpos->values['hashPrev']);

                    foreach ($bdb->range($packIndex . $revPrev, 1) as $key => $value){
                        $blockpos = Xp\DiskBlockIndex::fromBinary($key, $value);
                        fseek($fp, toInt($blockpos->values['nBlockPos']) - 8);
                        goto continueloop;
                    }
                }

                throw new Exception('Parse Error: ' . ($nHeight - 1));
            }

            if (!$hit)
                throw new Exception('Height Check Error: ' . ($nHeight - 1));
        }
        $heightMap[$nHeight] = true;

        $blockhashdb = toByteaDb($revBlockHash);
        $stmt = $db->prepare('INSERT INTO bindex values (?, ?)');
        $stmt->bindValue(1, $blockhashdb, PDO::PARAM_LOB);
        $stmt->bindValue(2, $nHeight, PDO::PARAM_INT);
        $stmt->execute();
        break;
    }
    if (!$hit){
        fseek($fp, $nBlockPos + $blocksize);
        continue;
    }

    $size = readCompactSizeRaw($fp);
    for ($j = 0; $j < $size; $j++){
        $nTxPos = ftell($fp);

        try {
            $tx = Xp\Tx::readFp($fp);
        }catch (Exception $e){
            echo 'Invalid data: ', $e->getMessage();
            fseek($fp, $nBlockPos + $blocksize);
            goto continueloop;
        }

        $txhash = $tx->values['txid'];
        $txhashdb = toByteaDb(strrev($txhash));

        $hit = $nHeight == 0;
        foreach ($bdb->range($packTx . strrev($txhash), 1) as $key => $value){
            $hit = true;

            readStr($value, 4); // version
            $nFile = readInt32($value);
            $_nBlockPos = readInt32($value);
            $_nTxPos = readInt32($value);

            if ($nBlockPos != $_nBlockPos ||
                $nTxPos != $_nTxPos){
                /*
                 * Non-main coinbase transaction hash is same as
                 * main coinbase transaction hash by stake.
                 * Indexed tx hash is main.
                 */
                fseek($fp, $nBlockPos + $blocksize);
                goto continueloop;

                //throw new Exception("Debug stop: $nBlockPos:$_nBlockPos, $nTxPos:$_nTxPos");
            }
        }

        if (!$hit){
            // block is indexed, tx is not indexed, not main branch
            fseek($fp, $nBlockPos + $blocksize);
            goto continueloop;
        }

        $prevOutTxs = [];
        if (isset($tx->values['vin']['coinbase'])){
            // nothing
        }else{
            foreach ($tx->values['vin'] as $k => $in){
                $prevHash = $in['prevout.hash'];
                $prevN = toInt($in['prevout.n']);

                $prevRevhash = strrev($prevHash);
                $query = $packTx . $prevRevhash;
                foreach ($bdb->range($query, 1) as $key => $value){
                    $prevtx = Xp\DiskTxPos::fromBinary($key, $value);
                    if (!isset($prevtx->values['details']->values['vout'][$prevN]))
                        break;
                    $out = $prevtx->values['details']->values['vout'][$prevN];
                    $dests = $out['scriptPubKey']->extractDestinations();
                    if (!isset($dests[1]))
                        break;

                    foreach ($dests[1] as $addr){
                        $addr = $addr->toAddressBin();
                        $revaddr = strrev($addr);
                        $prevOutTxs[] = [
                            toByteaDb($revaddr),
                            toByteaDb($prevRevhash),
                            $out['nValue'],
                            $prevN,
                            $txhashdb,
                            $k,
                        ];
                        break; // not support multisig
                    }

                    break;
                }
            }
        }

        $vout = [];
        foreach ($tx->values['vout'] as $k => $out){
            $dests = $out['scriptPubKey']->extractDestinations();
            $vout[$k] = null;
            if (isset($dests[1])){
                foreach ($dests[1] as $addr){
                    $addr = $addr->toAddressBin();
                    $revaddr = strrev($addr);
                    $vout[$k] = toByteaDb($revaddr)
                              . chr(0x01)
                              . $out['nValue'];
                    break; // not support multisig
                }
            }
        }

        // insert this tx
        $outlen = count($vout);
        $sql = sprintf(
            '
INSERT INTO txindex(txhash, height, outdata)
values(?, ?, ARRAY[%s]::bytea[])
', implode(',', array_fill(0, $outlen, '?')));
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $txhashdb, PDO::PARAM_LOB);
        $stmt->bindValue(2, $nHeight, PDO::PARAM_INT);
        $inc = 3;
        foreach ($vout as $k => $v){
            $stmt->bindValue($inc, $v, PDO::PARAM_LOB);
            $inc++;
        }
        $stmt->execute();

        // update prev tx
        foreach ($prevOutTxs as $prevOutTx){
            list($prevaddr, $prevtx, $nValue, $prevn, $nexttx, $nextn)
                = $prevOutTx;
            $prevn++; // PostgreSQL array is started 1
            $stmt =$db->prepare(sprintf('
UPDATE txindex
set outdata[%d] = ?
where txhash = ? and outdata[%d] = ?
', $prevn, $prevn));

            $check = $prevaddr . chr(0x01) . $nValue;
            $newdata = $prevaddr . chr(0x02) . $nValue
                     . $nexttx . hex2bin(sprintf('%08x', $nextn));

            $stmt->bindValue(1, $newdata, PDO::PARAM_LOB);
            $stmt->bindValue(2, $prevtx, PDO::PARAM_LOB);
            $stmt->bindValue(3, $check, PDO::PARAM_LOB);
            $stmt->execute();
            if (($c = $stmt->rowCount()) !== 1){
                var_dump($prevOutTx);
                var_dump(bin2hex($check));
                var_dump(bin2hex($newdata));
                throw new Exception("Error update check: " .
                                    bin2hex($prevtx) .
                                    '[' . $prevn . '] ' .
                                    ':' .
                                    bin2hex($prevaddr) . ':' .
                                    bin2hex($nexttx));
            }
        }
    }




    fseek($fp, $nBlockPos + $blocksize);

    if ($i % 100 == 0){
        echo '.';
    }
    if ($i % 1000 == 0){
        echo '*';

        $lastPos = ftell($fp);
        $db->query(sprintf('UPDATE posinfo set nfile = 1, npos =%d', $lastPos));
        $db->commit();
        $db->beginTransaction();

    }
    if ($i % 10000 == 0){
        echo "$i\n";
    }

    continueloop:
}

$lastPos = ftell($fp);
$db->query(sprintf('UPDATE posinfo set nfile = 1, npos =%d', $lastPos));
$db->commit();

exit;
