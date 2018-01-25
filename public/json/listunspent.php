<?php

use Xpcoin\BlockFileWalker\Config;
use Xpcoin\BlockFileWalker\Db;
use Xpcoin\BlockFileWalker\Xp;
use function Xpcoin\BlockFileWalker\toInt;
use function Xpcoin\BlockFileWalker\toByteaDb;
use function Xpcoin\BlockFileWalker\addrToBin;
use function Xpcoin\BlockFileWalker\toAmountStr;
use function Xpcoin\BlockFileWalker\packStr;

$dir = dirname(dirname(__DIR__));
chdir($dir);
require_once  "$dir/vendor/autoload.php";
Config::set("$dir/config.ini");

function error()
{
    http_response_code(400);
}
header('Content-Type: application/json');

if (!isset($_GET['q']))
    return error();
$q = $_GET['q'];

if (!strlen($q) == 34 || !in_array($q[0], Config::$ADDRESS_PREFIX))
    return error();

$pdo = Config::getPdo();
$db = new Db(Config::$datadir);


$addr = toByteaDb(strrev(addrToBin($q)));

$bestHeight = null;
foreach ($pdo->query('select height from bindex order by height desc limit 1')
         as $row){
    $bestHeight = $row->height;
}
if ($bestHeight === null)
    return error();


$sql = '
select txhash, height from txindex
join bindex using(bhash)
where substrbytea(outdata, 1, 9) @> ARRAY[?::bytea]
';

$param = $addr . chr(0x01);
$stmt = $pdo->prepare($sql);
$stmt->bindColumn(1, $txhash);
$stmt->bindColumn(2, $height);
$stmt->bindParam(1, $param, PDO::PARAM_LOB);
$stmt->execute();

$prefix = packStr('tx');
echo "[\n";
$first = true;
while ($_ = $stmt->fetch(PDO::FETCH_BOUND)){
    $q = $prefix . $txhash;
    foreach ($db->range($q) as $key => $value){
        $tx = Xp\DiskTxPos::fromBinary($key, $value)->values['details'];
        $hash = bin2hex(strrev($tx->values['txid']));
        $tx->readNextVin();

        foreach ($tx->values['vout'] as $i => $out){
            if ($out['nextin.hash'] !== null)
                continue;
            $nValue = toInt($out['nValue']);
            if ($nValue === 0)
                continue;

            if (!$first)
                echo ",\n";
            else
                $first = false;

            echo json_encode(
                [
                    'txid' => $hash,
                    'vout' => $i,
                    'amount' => toAmountStr($nValue),
                    'confirmations' => $bestHeight - $height + 1,
                ]
                /* , JSON_PRETTY_PRINT */);
        }
    }
}
echo "\n]\n";
