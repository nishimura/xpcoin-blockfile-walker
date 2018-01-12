<?php

$dir = dirname(__DIR__);
chdir($dir);

require_once  "$dir/vendor/autoload.php";

$config = parse_ini_file("$dir/config.ini");

$q = null;
if (isset($_GET['q']))
    $q = $_GET['q'];

$app = new Xpcoin\BlockFileWalker\App($dir, $config);
$app->run($q)->show('public', 'cache');
