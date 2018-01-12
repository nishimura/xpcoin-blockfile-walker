<?php

$dir = __DIR__;
chdir($dir);

require_once  "$dir/vendor/autoload.php";

$config = parse_ini_file("$dir/config.ini");

$q = null;
if (isset($argv[1]))
    $q = $argv[1];

$app = new Xpcoin\BlockFileWalker\App($dir, $config);

$params = $app->run($q)->getParams();
foreach ($params as $k => $v){
    if (is_array($v) || is_object($v)){
        echo "$k\n";
        foreach ($v as $_k => $_v)
            echo "  $_k: $_v\n";
    }else{
        echo "$k: $v\n";
    }
}
echo "\n";
