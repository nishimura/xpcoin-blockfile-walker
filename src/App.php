<?php

namespace Xpcoin\BlockFileWalker;

use Laiz\Template\Parser;

class App
{
    public static $datadir;

    private $rootdir;
    private $config;
    private $params;
    private $db;

    public function __construct($dir, $config)
    {
        $this->rootdir = $dir;
        $this->config = $config;

        $this->params = new \stdClass;

        $this->db = new Db($this->config['datadir']);

        self::$datadir = $config['datadir'];
    }

    public function run($query = null)
    {
        $p = $this->params;
        $p->query = $query;

        $p->blocks = $this->query(
            Xp\DiskBlockIndex::class, packKey('blockindex', $query));
        $p->txs = $this->query(
            Xp\DiskTxPos::class, packKey('tx', $query));

        return $this;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function show($public, $cache, $params = null)
    {
        if ($params === null)
            $params = $this->params;

        $tmpl = new Parser($public, $cache);
        $tmpl->addBehavior('l',
                           'Xpcoin\BlockFileWalker\Presenter\Filter::toHashLink',
                           true);
        $tmpl->setFile('index.html')->show($params);
    }


    private function query($cls, $query)
    {
        $ret = [];
        $f = [$cls, 'fromBinary'];
        foreach ($this->db->range($query) as $key => $value){
            $block = $f($key, $value);
            $ret[] = $block;
        }
        return $ret;
    }
}
