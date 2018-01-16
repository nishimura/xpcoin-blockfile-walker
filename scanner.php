<?php

use Xpcoin\BlockFileWalker\Config;
use Xpcoin\BlockFileWalker\Db;
use Xpcoin\BlockFileWalker\Xp;
use function Xpcoin\BlockFileWalker\readCompactSizeRaw;
use function Xpcoin\BlockFileWalker\readStr;
use function Xpcoin\BlockFileWalker\packKey;
use function Xpcoin\BlockFileWalker\packStr;
use function Xpcoin\BlockFileWalker\toInt;

$dir = __DIR__;
chdir($dir);

require_once  "$dir/vendor/autoload.php";

Config::set("$dir/config.ini");

$file = Config::$datadir . '/blk0001.dat';
$fp = fopen($file, 'rb');

$bdb = new Db(Config::$datadir);
$db = new PDO(Config::$dsn);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

$prevLastPos = 0;
foreach ($db->query('select * from posinfo') as $row){
    $prevLastPos = $row->npos;

    $db->query(sprintf('delete from bindex where npos > %d',
                       $row->npos));
    $db->query(sprintf('delete from addr where npos > %d',
                       $row->npos));
    break;
}
$lastPos = $prevLastPos;
fseek($fp, $prevLastPos);

function toInt56($str)
{
    return hexdec(bin2hex(readStr($str, 7)));
}
function readInt32(&$str)
{
    return hexdec(bin2hex(strrev(readStr($str, 4))));
}

$packIndex = packStr('blockindex');
$packTx = packStr('tx');

$posMap = []; // for seek prev 100 block

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
    if ($bhex !== 'b4f8e2e5' && $bhex !== 'cdf2c0ef'){
        // pchMessageStart = 0xb4, 0xf8, 0xe2, 0xe5
        // testnet: 0xcd, 0xf2, 0xc0, 0xef
        throw new Exception('seek error:' . $bhex);
    }

    $blocksize = hexdec(bin2hex(strrev(fread($fp, 4))));
    //var_dump($blocksize);
    $nBlockPos = ftell($fp);

    $revhash = XP\Block::getHashFromFp($fp);
    $blockhash = strrev($revhash);
    $query = $packIndex . $revhash;
    foreach ($bdb->range($query) as $key => $value){
        readStr($value, 36);
        $nFile = readInt32($value);
        $_nBlockPos = readInt32($value);
        $nHeight = readInt32($value);

        $hash7 = toInt56($revhash);

        try {
            $db->query(
                sprintf('INSERT INTO bindex values (%d, %d, %d, %d)',
                        $hash7, $nFile, $_nBlockPos, $nHeight));
        }catch (\Exception $e){
            echo $e->getMessage();
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
                    $db->query(sprintf('UPDATE bindex set nfile = %d, npos = %d, nheight = %d where hash = %d',
                                       $_nFile, $_nPos, $_nHeight, $hash7));
                    $nBlockPos = $_nPos;
                }
            }
        }
        break;
    }

    // TODO: insert addr which converted from script hash
    $size = readCompactSizeRaw($fp);
    //var_dump($size);
    for ($j = 0; $j < $size; $j++){
        $tx = Xp\Tx::readFp($fp);
        $txhash = $tx->values['txid'];
        $txhash7 = toInt56(strrev($txhash));

        if (isset($tx->values['vin']['coinbase'])){
            // nothing
        }else{
            foreach ($tx->values['vin'] as $k => $in){
                $prevHash = $in['prevout.hash'];
                $prevN = toInt($in['prevout.n']);

                $revhash = strrev($prevHash);
                $query = $packTx . $revhash;
                foreach ($bdb->range($query) as $key => $value){
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
                        $addr7 = toInt56($revaddr);
                        $db->query(
                            sprintf('INSERT INTO addr values (%d, %d, %d, %d, %d, null)',
                                    $addr7, $nHeight, $nFile, $nBlockPos, $txhash7));
                    }

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
                    $addr7 = toInt56($revaddr);
                    $db->query(
                        sprintf('INSERT INTO addr values (%d, %d, %d, %d, null, %d)',
                                $addr7, $nHeight, $nFile, $nBlockPos, $txhash7));
                }
            }
        }
    }


    // debug
    if (1){
    }


    fseek($fp, $nBlockPos + $blocksize);
    $lastPos = ftell($fp);

    if ($i % 1000 == 0){
        $db->query('delete from posinfo');
        $db->query(
            sprintf('INSERT INTO posinfo values (1, %d)',
                    $lastPos));
        echo '.';
    }
    if ($i % 10000 == 0){
        echo $i;
    }

}

if ($prevLastPos != $lastPos){
    $db->query('delete from posinfo');
    $db->query(
        sprintf('INSERT INTO posinfo values (1, %d)',
                $lastPos));
}

echo "\n";

exit;
