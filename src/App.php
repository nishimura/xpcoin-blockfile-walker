<?php

namespace Xpcoin\BlockFileWalker;

use Laiz\Template\Parser;
use function Xpcoin\BlockFileWalker\addrToBin7;
use PDO;

class App
{
    private $rootdir;
    private $params;
    private $db;

    public function __construct($dir)
    {
        $this->rootdir = $dir;

        $this->params = new \stdClass;

        $this->db = new Db(Config::$datadir);
    }

    public function run($query = null)
    {
        $p = $this->params;
        $p->query = $query;

        if ($query === null){
            $p->blocks = $this->queryHeight();
            $p->txs = [];
            return $this;
        }
        if (in_array($query[0], Config::$ADDRESS_PREFIX)){
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

    private function getPdo()
    {
        $pdo = new PDO(Config::$dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        return $pdo;
    }
    private function queryHeight($limit = 100)
    {
        $pdo = $this->getPdo();
        $sql = 'select * from bindex order by height desc limit ' . $limit;

        $prefix = packStr('blockindex');
        foreach ($pdo->query($sql) as $row){
            $hash7 = $row->hash;
            $hash7 = dechex($hash7);
            if (strlen($hash7) % 2 == 1)
                $hash7 = '0' . $hash7;
            $hashid = hex2bin($hash7);

            $q = $prefix .$hashid;
            foreach ($this->db->range($q) as $key => $value){
                $block = Xp\DiskBlockIndex::fromBinary($key, $value);
                yield $block;
            }
        }
    }


    private function queryAddr($query, $limit = 100)
    {
        $pdo = $this->getPdo();

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
