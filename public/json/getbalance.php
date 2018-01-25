<?php

use Xpcoin\BlockFileWalker\Config;
use function Xpcoin\BlockFileWalker\toInt;
use function Xpcoin\BlockFileWalker\toByteaDb;
use function Xpcoin\BlockFileWalker\addrToBin;
use function Xpcoin\BlockFileWalker\toAmountStr;

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


$addr = toByteaDb(strrev(addrToBin($q)));

$sql = '
select sum(bytea_to_amount(outdata, ?)) from txindex
where substrbytea(outdata, 1, 9) @> ARRAY[?::bytea]
';

$param1 = $addr;
$param2 = $addr . chr(0x01);
$stmt = $pdo->prepare($sql);
$stmt->bindColumn(1, $total);
$stmt->bindParam(1, $param1, PDO::PARAM_LOB);
$stmt->bindParam(2, $param2, PDO::PARAM_LOB);
$stmt->execute();
while ($_ = $stmt->fetch(PDO::FETCH_BOUND)){
    // bind
}

echo json_encode(
    [
        'address' => $q,
        'balance' => toAmountStr($total)
    ]
    , JSON_PRETTY_PRINT);
