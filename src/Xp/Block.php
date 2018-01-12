<?php

namespace Xpcoin\BlockFileWalker\Xp;

use Xpcoin\BlockFileWalker\App;

use function Xpcoin\BlockFileWalker\walkChunkRaw;
use function Xpcoin\BlockFileWalker\readCompactSizeRaw;
use function Xpcoin\BlockFileWalker\readVcharRaw;
use function Xpcoin\BlockFileWalker\raw256toHexStr;

class Block
{
    const FILE = 'blk%04d.dat';

    public $values;
    public function __construct($data)
    {
        foreach ($data as $k => $v)
            $this->values[$k] = $v;
    }

    public function __toString() { return $this->toString(); }
    public function toString()
    {
        $ret = '';

        foreach ($this->values as $k => $v){
            $show = $v;
            switch ($k){

            case 'vtx':
                $show = "\n";
                foreach ($v as $_v){
                    $show .= $_v->toString();
                }
                break;

            case 'hashPrevBlock':
            case 'hashMerkleRoot':
                $show = raw256toHexStr($v);
                break;

            case 'nTime':
            case 'nLockTime':
                $show = date('Y-m-d H:i:s', $v);
                break;

            case 'vchBlockSig':
                $show = bin2hex($v);
                break;

            default:
                break;
            }
            $ret .= sprintf("  %14s: %s\n", $k, $show);
        }
        $ret .= "\n";

        return $ret;
    }

    public static function fromBinary($pos)
    {
        list($nFile, $nBlockPos) = $pos;
        $file = App::$datadir . '/' . self::FILE;
        $file = sprintf($file, $nFile);

        $fp = fopen($file, 'rb');
        fseek($fp, $nBlockPos);

        $data = [];

        $chunkBase = [
            'nVersion' => [32],
            'hashPrevBlock' => [32,32,32,32,32,32,32,32],
            'hashMerkleRoot' => [32,32,32,32,32,32,32,32],
            'nTime' => [32],
            'nBits' => [32],
            'nNonce' => [32],
        ];
        $data += walkChunkRaw($fp, $chunkBase);


        $size = readCompactSizeRaw($fp);
        $data['vtx'] = [];
        for ($i = 0; $i < $size; $i++){
            $data['vtx'][$i] = Tx::readFp($fp);
        }

        $data['vchBlockSig'] = readVcharRaw($fp);


        fclose($fp);
        return new self($data);
    }
}
