<?php

use function Xpcoin\BlockFileWalker\raw256toHexStr;


$dir = dirname(__DIR__);
chdir($dir);


require_once  "$dir/vendor/autoload.php";

$config = parse_ini_file("$dir/config.ini");

$q = null;
if (isset($_GET['q']))
    $q = $_GET['q'];

$app = new Xpcoin\BlockFileWalker\App($dir, $config);
//$app->run($q)->show('public', 'cache');
$params = $app->run($q)->getParams();





function blockDetails($details){
    $ret = new \stdClass;
    foreach ($details->values as $k => $v){
        switch ($k){
            
        }
        $ret->$k = $v;
    }

    return $ret;
}

function blocksView($blocks){
    foreach ($blocks as $block){
        $block->details = blockDetails($block->values['details']);
        unset($block->values['details']);
        yield $block;
    }
}
function txsView($txs){
    foreach ($txs as $tx){
        yield $tx;
    }
}

$params->blocks = blocksView($params->blocks);
$params->txs = txsView($params->txs);

$app->show('public', 'cache', $params);
