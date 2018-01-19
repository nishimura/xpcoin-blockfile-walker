<?php

namespace Xpcoin\BlockFileWalker\Xp;

use Xpcoin\BlockFileWalker\Config;
use Xpcoin\BlockFileWalker\Presenter;
use Xpcoin\BlockFileWalker\Script;
use Xpcoin\BlockFileWalker\Db;
use Xpcoin\BlockFileWalker\Exception;

use function Xpcoin\BlockFileWalker\toAmount;
use function Xpcoin\BlockFileWalker\readCompactSizeRaw;
use function Xpcoin\BlockFileWalker\readFpVector;
use function Xpcoin\BlockFileWalker\raw256toHexStr;
use function Xpcoin\BlockFileWalker\toInt;
use function Xpcoin\BlockFileWalker\toByteaDb;
use function Xpcoin\BlockFileWalker\packStr;
use function Xpcoin\BlockFileWalker\decToBin;

use PDO;

class Tx
{
    const FILE = 'blk%04d.dat';

    public $values;
    public function __construct($data)
    {
        foreach ($data as $k => $v)
            $this->values[$k] = $v;
    }

    public static function getPresenter(Tx $obj)
    {
        return new Presenter\Tx($obj);
    }
    public function toPresenter()
    {
        return self::getPresenter($this);
    }

    public function __toString() { return $this->toString(); }
    public function toString()
    {
        return $this->toPresenter()->toString();
    }

    public function isCoinStake()
    {
        return !isset($this->values['vin']['coinbase']) &&
            count($this->values['vout']) >= 2 &&
            $this->values['vout'][0]['scriptPubKey']->toString() == '';
    }

    public function readPrevVout()
    {
        if (isset($this->values['vin']['coinbase']))
            return;

        foreach ($this->values['vin'] as $i => $in){
            $vout = self::getPrevVout(
                strrev($in['prevout.hash']), toInt($in['prevout.n']));
            $this->values['vin'][$i] += $vout;
        }
    }

    public function readNextVin()
    {
        $hash = strrev($this->values['txid']);
        foreach ($this->values['vout'] as $i => $out){
            list($nexthash, $nextn) = self::getNextVin($hash, $i);
            $this->values['vout'][$i]['nextin.hash'] = $nexthash;
            $this->values['vout'][$i]['nextin.n'] = $nextn;
        }
    }

    public static $pdo;
    public static $bdb;
    public static function getNextVin($prevrevhash, $prevn)
    {
        if (self::$pdo === null)
            self::$pdo = Config::getPdo();
        if (self::$bdb === null)
            self::$bdb = new Db(Config::$datadir);

        $query = 'SELECT nexthash[%d], nextn[%d] from txindex where txhash = ?';
        $prevn++;
        $query = sprintf($query, $prevn, $prevn);
        $hash = toByteaDb($prevrevhash);
        $txprefix = packStr('tx');
        $stmt = self::$pdo->prepare($query);
        $stmt->bindColumn(1, $nexthash);
        $stmt->bindColumn(2, $nextn, PDO::PARAM_INT);
        $stmt->bindParam(1, $hash, PDO::PARAM_LOB);
        $stmt->execute();

        while ($_ = $stmt->fetch(PDO::FETCH_BOUND)){
            if ($nexthash === null || $nextn === null){
                if ($nexthash || $nextn)
                    throw new Exception('Scan Bug');
                return [null, null];
            }

            $range = $txprefix . $nexthash;
            foreach (self::$bdb->range($range, 1) as $key => $value){
                $n = decToBin($nextn);
                return [strrev(substr($key, 3)), $n];
            }
        }
        return [null, null];
    }

    public static function getPrevVout($prevhash, $prevn)
    {
        if (self::$bdb === null)
            self::$bdb = new Db(Config::$datadir);

        $txprefix = packStr('tx');
        $range = $txprefix . $prevhash;
        foreach (self::$bdb->range($range, 1) as $key => $value){
            $txpos = DiskTxPos::fromBinary($key, $value);
            return $txpos->values['details']->values['vout'][$prevn];
        }
    }

    public static function isCoinbase($vin)
    {
        if (count($vin) != 1)
            return false;

        if (!preg_match('/^[0]+$/', bin2hex($vin[0]['prevout.hash'])))
            return false;

        return true;
    }

    public static $cacheMap = [];
    public static function fromBinary($pos)
    {
        list($nFile, $nBlockPos, $nTxPos) = $pos;

        $cacheKey = "$nFile:$nBlockPos:$nTxPos";
        if (isset(self::$cacheMap[$cacheKey]))
            return self::$cacheMap[$cacheKey];

        $file = Config::$datadir . '/' . self::FILE;
        $file = sprintf($file, $nFile);

        //var_dump($file, $nBlockPos, $nTxPos);
        $fp = fopen($file, 'rb');
        fseek($fp, $nTxPos);

        $ret = self::readFp($fp);
        fclose($fp);

        self::$cacheMap[$cacheKey] = $ret;
        Config::truncateCache(self::$cacheMap);

        return $ret;
    }

    public static $cacheFpMap = []; // [pos => [$tx, $nextPos]]
    public static function readFp($fp)
    {
        $start = ftell($fp);

        if (isset(self::$cacheFpMap[$start])){
            list($tx, $nextpos) = self::$cacheFpMap[$start];
            fseek($fp, $nextpos);
            return $tx;
        }

        $data = [];

        $chunks = [
            'nVersion' => 4,
            'nTime'    => 4,
        ];
        foreach ($chunks as $k => $byte){
            $data[$k] = strrev(fread($fp, $byte));
        }

        $chunks = [
            'prevout.hash' => 32,
            'prevout.n'    => 4,
        ];
        $size = readCompactSizeRaw($fp);
        $data['vin'] = [];
        for ($i = 0; $i < $size; $i++){
            $data['vin'][$i] = [];
            foreach ($chunks as $k => $byte){
                $data['vin'][$i][$k] = strrev(fread($fp, $byte));
            }

            $data['vin'][$i]['scriptSig'] = new Script(readFpVector($fp));
            $data['vin'][$i]['nSequence'] = strrev(fread($fp, 4));
        }

        $isCoinbase = self::isCoinbase($data['vin']);

        if ($isCoinbase){
            $data['vin']['coinbase'] = $data['vin'][0]['scriptSig'];
            $data['vin']['nSequence'] = $data['vin'][0]['nSequence'];
            unset($data['vin'][0]);
        }else{
            // TODO: asm
        }

        $size = readCompactSizeRaw($fp);
        $data['vout'] = [];
        for ($i = 0; $i < $size; $i++){
            $data['vout'][$i] = [];
            $data['vout'][$i]['nValue'] = strrev(fread($fp, 8));

            $data['vout'][$i]['scriptPubKey'] = new Script(readFpVector($fp));
        }

        $data['nLockTime'] = strrev(fread($fp, 4));

        $end = ftell($fp);

        fseek($fp, $start);
        $bin = fread($fp, $end - $start);
        $hash = hash('sha256', $bin, true);
        $hash = hash('sha256', $hash, true);

        $renew = [
            'txid' => strrev($hash),
        ];
        $data = $renew + $data;

        $obj = new self($data);

        self::$cacheFpMap[$start] = [$obj, $end];
        Config::truncateCache(self::$cacheFpMap);

        return $obj;
    }
}
