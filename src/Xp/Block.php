<?php

namespace Xpcoin\BlockFileWalker\Xp;

use Xpcoin\BlockFileWalker\Config;
use Xpcoin\BlockFileWalker\Presenter;

use function Xpcoin\BlockFileWalker\readCompactSizeRaw;
use function Xpcoin\BlockFileWalker\readFpVector;
use function Xpcoin\BlockFileWalker\raw256toHexStr;

use function Xpcoin\BlockFileWalker\readStr;
use function Xpcoin\BlockFileWalker\toInt;
use function Xpcoin\BlockFileWalker\toAmount;


class Block
{
    const FILE = 'blk%04d.dat';

    public $values;
    public function __construct($data)
    {
        foreach ($data as $k => $v)
            $this->values[$k] = $v;
    }

    public static function getPresenter(Block $obj)
    {
        return new Presenter\Block($obj);
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

    public static function fromBinary($pos)
    {
        list($nFile, $nBlockPos) = $pos;
        $file = Config::$datadir . '/' . self::FILE;
        $file = sprintf($file, $nFile);

        $fp = fopen($file, 'rb');
        fseek($fp, $nBlockPos);

        $data = [];

        $chunks = [
            'nVersion' => 4,
            'hashPrevBlock' => 32,
            'hashMerkleRoot' => 32,
            'nTime' => 4,
            'nBits' => 4,
            'nNonce' => 4,
        ];

        foreach ($chunks as $k => $byte){
            $data[$k] = strrev(fread($fp, $byte));
        }

        $size = readCompactSizeRaw($fp);
        $data['vtx'] = [];
        for ($i = 0; $i < $size; $i++){
            $data['vtx'][$i] = Tx::readFp($fp);
        }

        $data['vchBlockSig'] = readFpVector($fp);


        fseek($fp, $nBlockPos);
        $data['hash'] = strrev(self::getHashFromFp($fp));


        fclose($fp);
        return new self($data);
    }

    public static function getHashFromFp($fp)
    {
        $start = ftell($fp);

        $bin = fread($fp, 4 + 32 + 32 + 4 + 4 + 4);
        $hash = hash('sha256', $bin, true);
        $hash = hash('sha256', $hash, true);

        return $hash;
    }

    public static function getHashFromPos($pos)
    {
        list($nFile, $nBlockPos) = $pos;
        $file = Config::$datadir . '/' . self::FILE;
        $file = sprintf($file, $nFile);

        $fp = fopen($file, 'rb');

        fseek($fp, $nBlockPos);

        $ret = self::getHashFromFp($fp);

        fclose($fp);

        return $ret;
    }
}
