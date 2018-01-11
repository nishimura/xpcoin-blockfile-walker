<?php

namespace Xpcoin\Explorer;

use Db4Env;
use Db4;
use Curosr;

class Db
{
    private $dbenv;
    private $db;
    private $cursor;
    public function __construct($datadir)
    {
        $dbenv = new Db4Env();
        $ret = $dbenv->open($datadir,
                            DB_CREATE |
                            DB_INIT_CDB |
                            DB_INIT_MPOOL,
                            0666);
        if ($ret === false)
            throw new Exception('dbenv open error');

        $db = new Db4($dbenv);
        $ret = $db->open(null, 'blkindex.dat', 'main');

        $cursor = $db->cursor();

        $this->dbenv = $dbenv;
        $this->db = $db;
        $this->cursor = $cursor;
    }

    public function range($prefix, $limit = 3)
    {
        $key = $prefix;
        $value = 0;

        $ret = $this->cursor->get($key, $value, DB_SET_RANGE);
        $i = 0;
        while ($ret == 0 && $i < $limit){
            if (!startsWith($key, $prefix)){
                break;
            }

            yield $key => $value;

            $ret = $this->cursor->get($key, $value, DB_NEXT);

            $i++;
        }
    }

    public function __destruct()
    {
        if ($this->cursor)
            $this->cursor->close();
        if ($this->db)
            $this->db->close();

        // if ($this->dbenv)
        //     $this->dbenv->close();
    }
}
