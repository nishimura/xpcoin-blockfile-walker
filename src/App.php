<?php

namespace Xpcoin\BlockFileWalker;

use Laiz\Template\Parser;
use function Xpcoin\BlockFileWalker\addrToBin7;
use PDO;

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

        if ($query[0] === 'X'){
            $p->blocks = [];
            $p->txs = $this->queryAddr($query);
            return $this;
        }

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

    private function queryAddr($query, $limit = 1000)
    {
        $file = dirname(__DIR__) . '/db/db.sqlite3';
        $pdo = new PDO('sqlite:' . $file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        $addr7 = hexdec(bin2hex(addrToBin7($query)));
        $sql = sprintf('select * from addr where hash = %d order by blockheight desc limit ' . $limit, $addr7);

        $prefix = packStr('tx');
        foreach ($pdo->query($sql) as $row){
            if (isset($row->intx))
                $tx7 = $row->intx;
            else
                $tx7 = $row->outtx;

            $tx7 = dechex($tx7);
            if (strlen($tx7) % 2 == 1)
                $tx7 = '0' . $tx7;
            $txid = hex2bin($tx7);

            $q = $prefix .$txid;
            foreach ($this->db->range($q) as $key => $value){
                $tx = Xp\DiskTxPos::fromBinary($key, $value);
                yield $tx;
            }
        }
    }
}
