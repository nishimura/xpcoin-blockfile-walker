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
); // typo, BDB PAGE_NOTFOUND error => Exception


require_once  "$dir/vendor/autoload.php";

Config::set("$dir/config.ini");


$loopmax = 100;
if (isset($argv[1]))
    $loopmax = $argv[1];
$recheck = 10;
if (isset($argv[2]))
    $recheck = $argv[2];


$file = Config::$datadir . '/blk0001.dat';
$fp = fopen($file, 'rb');

$bdb = new Db(Config::$datadir);
$db = Config::getPdo();

$packIndex = packStr('blockindex');
$packTx = packStr('tx');
$packNullHash = hex2bin(str_repeat('0', 8 * 2));

$prevLastPos = null;
$prevLastFile = 1;
$prevLastHeight = 0;
// TODO: fpos
foreach ($db->query('select * from posinfo') as $row){
    $prevLastFile = $row->nfile;
    $prevLastPos = $row->npos;
    $prevLastHeight = $row->height;
    break;
}

$prevHashNext = toByteaDb(strrev(hex2bin(Config::$GENESIS_BLOCK)));
$prevHash = $packNullHash;

if ($prevLastPos === null){
    $db->query('INSERT INTO posinfo values(1, 0, 0)');
    $prevLastPos = 0;
}else{

    $first = true;
    for ($i = $prevLastHeight - $recheck; $i <= $prevLastHeight; $i++){
        $query = 'select bhash, height, nfile, npos from bindex where height = ' . $i;
        $stmt = $db->prepare($query);
        $stmt->bindColumn(1, $curHash);
        $stmt->bindColumn(2, $nHeight, PDO::PARAM_INT);
        $stmt->bindColumn(3, $nFile, PDO::PARAM_INT);
        $stmt->bindColumn(4, $nPos, PDO::PARAM_INT);
        $stmt->execute();

        $hit = false;
        while ($_ = $stmt->fetch(PDO::FETCH_BOUND)){
            $hit = true;
        }
        if (!$hit)
            throw new Exception('Error: bhash not exists: recheck ' . $i);

        $data = null;
        foreach ($bdb->range($packIndex . $curHash, 1) as $key => $value){
            $data = readDiskBlock($value);

            if ($nPos !== ($data['nBlockPos'] - 8) ||
                $nFile !== $data['nFile']){
                throw new Exception('not match pos: todo rollback');
                // todo rollback
            }

            if ($nHeight !== $data['nHeight']){
                throw new Exception('not match height: todo rollback');
            }
        }

        if (!$data)
            throw new Exception('blockindex not exists: recheck ' . $i);
        // TODO: !isset($prevHashNext) rollbackBlock
        // bdb blockindex updated

        if (!$first){
            if ($prevHash !== $data['hashPrev']){
                throw new Exception('not match prev hash: todo rollback');
            }

            if ($prevHashNext !== $curHash)
                throw new Exception('hash not match: '
                                    . bin2hex($prevHashNext) . ':'
                                    . bin2hex($curHash));

        }
        $first = false;
        $prevHash = $curHash;
        $prevHashNext = $data['hashNext'];
    }
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

function readDiskBlock($value)
{
    readStr($value, 4); // serVersion
    $hashNext = readStr($value, 8); // 8 byte from hashNext
    readStr($value, 24); // hashNext 24/32 byte

    $nFile = readInt32($value);
    $nBlockPos = readInt32($value);
    $nHeight = readInt32($value);

    readStr($value, 16); // nMint, nMoneySupply
    $nFlags = toInt(strrev(readStr($value, 4)));
    readStr($value, 8); // nStakeModifier

    if ($nFlags & Xp\DiskBlockIndex::BLOCK_PROOF_OF_STAKE){
        readStr($value, 72); // stake data
    }
    readStr($value, 4); // nVersion
    $hashPrev = readStr($value, 8); // hashPrev 8/32 byte

    return [
        'hashNext' => $hashNext,
        'nFile' => $nFile,
        'nBlockPos' => $nBlockPos,
        'nHeight' => $nHeight,
        'hashPrev' => $hashPrev,
    ];
}

function parseVin($txhashdb, $vin)
{
    global $packIndex, $packTx, $bdb;

    $prevOutTxs = [];
    foreach ($vin as $k => $in){
        $prevHash = $in['prevout.hash'];
        $prevN = toInt($in['prevout.n']);

        $prevRevhash = strrev($prevHash);
        $query = $packTx . $prevRevhash;

        $prevhashdb = toByteaDb($prevRevhash);

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
                    $prevhashdb,
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
    return $prevOutTxs;
}

function parseVout($vout)
{
    $ret = [];
    foreach ($vout as $k => $out){
        $dests = $out['scriptPubKey']->extractDestinations();
        $ret[$k] = null;
        if (isset($dests[1])){
            foreach ($dests[1] as $addr){
                $addr = $addr->toAddressBin();
                $revaddr = strrev($addr);
                $ret[$k] = toByteaDb($revaddr)
                          . chr(0x01)
                          . $out['nValue'];
                break; // not support multisig
            }
        }
    }
    return $ret;
}

$db->beginTransaction();

for ($i = 1; $i <= $loopmax; $i++){
    if (feof($fp))
        break;

    $nMessagePos = ftell($fp);

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
    $blockhashdb = toByteaDb($revBlockHash);

    //var_dump(['prevnext', bin2hex($blockhashdb),bin2hex($prevHashNext)]);

    if ($prevHashNext !== $blockhashdb)
        goto continueloop;

    $query = $packIndex . $revBlockHash;
    $hit = false;
    foreach ($bdb->range($query, 1) as $key => $value){

        $hit = true;

        $data = readDiskBlock($value);

        $hashNext = $data['hashNext'];
        $nFile = $data['nFile'];
        if ($nBlockPos !== $data['nBlockPos']){
            // not exists bdb database
            goto continueloop;
        }

        $nHeight = $data['nHeight'];
        $hashPrev = $data['hashPrev'];
        //var_dump(['prevhash', bin2hex($prevHash), bin2hex($hashPrev)]);
        if ($prevHash !== $hashPrev)
            goto continueloop;

        $stmt = $db->prepare('INSERT INTO bindex values (?, ?, ?, ?)');
        $stmt->bindValue(1, $blockhashdb, PDO::PARAM_LOB);
        $stmt->bindValue(2, $nHeight, PDO::PARAM_INT);
        $stmt->bindValue(3, $nFile, PDO::PARAM_INT);
        $stmt->bindValue(4, $nMessagePos, PDO::PARAM_INT);
        $stmt->execute();

        break;
    }
    if (!$hit)
        goto continueloop;

    $size = readCompactSizeRaw($fp);
    for ($j = 0; $j < $size; $j++){
        $nTxPos = ftell($fp);

        $tx = Xp\Tx::readFp($fp);
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
                 * bdb data is updated.
                 */
                //goto continueloop;

                throw new Exception("Debug stop: $nBlockPos:$_nBlockPos, $nTxPos:$_nTxPos");
            }
        }

        if (!$hit){
            // block is indexed, tx is not indexed, not main branch
            //goto continueloop;
            throw new Exception('transaction not found in bdb');
        }

        if (isset($tx->values['vin']['coinbase'])){
            // nothing
            $prevOutTxs = [];
        }else{
            $prevOutTxs = parseVin($txhashdb, $tx->values['vin']);
        }

        $vout = parseVout($tx->values['vout']);

        // insert this tx
        $outlen = count($vout);
        $sql = sprintf(
            '
INSERT INTO txindex(txhash, bhash, outdata)
values(?, ?, ARRAY[%s]::bytea[])
', implode(',', array_fill(0, $outlen, '?')));

        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $txhashdb, PDO::PARAM_LOB);
        $stmt->bindValue(2, $blockhashdb, PDO::PARAM_LOB);
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
            $prevn++; // PostgreSQL array is started from 1
            $sql = sprintf('
UPDATE txindex
set outdata[%d] = ?
where txhash = ? and outdata[%d] = ?
', $prevn, $prevn);

            $check = $prevaddr . chr(0x01) . $nValue;
            $newdata = $prevaddr . chr(0x02) . $nValue
                     . $nexttx . hex2bin(sprintf('%08x', $nextn));

            $stmt =$db->prepare($sql);
            $stmt->bindValue(1, $newdata, PDO::PARAM_LOB);
            $stmt->bindValue(2, $prevtx, PDO::PARAM_LOB);
            $stmt->bindValue(3, $check, PDO::PARAM_LOB);
            $stmt->execute();
            if (($c = $stmt->rowCount()) !== 1){
                throw new Exception("Error update tx[n]: " .
                                    bin2hex($prevtx)
                                    . '[' . $prevn . '] '
                                    . ' nexttx[n]:' . bin2hex($nexttx)
                                    . '[' . $nextn . ']'
                                    . "\ncheck:newdata\n" . bin2hex($check)
                                    . "\n" . bin2hex($newdata));
            }
        }
    }


    if ($i % 100 == 0){
        echo '.';
    }
    if ($i % 1000 == 0){
        echo '*';

        $lastPos = $nBlockPos + $blocksize;
        $db->query(sprintf('UPDATE posinfo set nfile = 1, npos =%d, height = %d',
                           $lastPos, $nHeight));
        $db->commit();
        $db->beginTransaction();

    }
    if ($i % 10000 == 0){
        echo "$i\n";
    }

    $prevHashNext = $hashNext;
    $prevHash = $blockhashdb;

    continueloop:
    fseek($fp, $nBlockPos + $blocksize);
}

if (isset($nHeight)){
    $lastPos = ftell($fp);
    $db->query(sprintf('UPDATE posinfo set nfile = 1, npos =%d, height = %d',
                       $lastPos, $nHeight));
    $db->commit();
} // else not updated
exit;
