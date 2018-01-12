<?php

namespace Xpcoin\Explorer\Xp;

use Xpcoin\Explorer\Uint32base;
use IO_Bit;

use Xpcoin\Explorer\App;

use function Xpcoin\Explorer\toAmount;
use function Xpcoin\Explorer\walkChunkRaw;
use function Xpcoin\Explorer\readCompactSizeRaw;
use function Xpcoin\Explorer\readScriptRaw;

class Tx
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

            case 'vin':
            case 'vout':
                $show = "\n";
                foreach ($v as $_k => $_v){
                    // TODO: refactoring
                    if (is_array($_v)){
                        $show .= "$k: $_k\n";
                        foreach ($_v as $__k => $__v){
                            if ($__k == 'nValue'){
                                $__v = toAmount($__v[0]);
                            }else if ($__k == 'prevout.hash'){
                                $x16 = '';
                                foreach ($__v as $___v){
                                    $x16 = dechex($___v) . $x16;
                                }
                                $__v = $x16;
                            }else if ($__k == 'prevout.n'){
                                $__v = $__v[0];
                            }else{
                                // not implements
                                //
                            }
                            $show .= sprintf("  %16s: %s\n", $__k, $__v);
                        }
                    }else{
                        $show .= "$_k: $_v\n";
                    }
                }
                break;

            case 'nTime':
            case 'nLockTime':
                $show = date('Y-m-d H:i:s', $v[0]);
                break;

            case is_array($v) && count($v) == 1:
                $show = $v[0];
                break;

            default:
                break;
            }
            if (is_array($show)){
                var_dump($k, $v);exit;
            }
            $ret .= sprintf("  %14s: %s\n", $k, $show);
        }
        $ret .= "\n";

        return $ret;
    }

    public static function isCoinbase($vin)
    {
        if (count($vin) != 1)
            return false;

        foreach ($vin[0]['prevout.hash'] as $v){
            if ($v != 0)
                return false;
        }
        return true;
    }

    public static function fromBinary($pos)
    {
        list($nFile, $nBlockPos, $nTxPos) = $pos;
        $file = App::$datadir . '/' . self::FILE;
        $file = sprintf($file, $nFile);

        //var_dump($file, $nBlockPos, $nTxPos);
        $fp = fopen($file, 'rb');
        fseek($fp, $nTxPos);

        $data = [];

        $chunkBase = [
            'nVersion' => [32],
            'nTime'    => [32],
        ];
        $data += walkChunkRaw($fp, $chunkBase);

        $inBase = [
            'prevout.hash' => [32,32,32,32,32,32,32,32],
            'prevout.n'    => [32],
        ];
        $size = readCompactSizeRaw($fp);
        $data['vin'] = [];
        for ($i = 0; $i < $size; $i++){
            $data['vin'][$i] = [];
            $data['vin'][$i] += walkChunkRaw($fp, $inBase);

            $data['vin'][$i]['scriptSig'] = readScriptRaw($fp);
            $data['vin'][$i]['nSequence'] = ord(fread($fp, 4));
        }

        $isCoinbase = self::isCoinbase($data['vin']);

        if ($isCoinbase){
            $data['vin']['coinbase'] = $data['vin'][0]['scriptSig'];
            $data['vin']['nSequence'] = $data['vin'][0]['nSequence'];
            unset($data['vin'][0]);
        }else{
            // TODO
        }


        $outBase = [
            'nValue' => [64],
        ];
        $size = readCompactSizeRaw($fp);
        $data['vout'] = [];
        for ($i = 0; $i < $size; $i++){
            $data['vout'][$i] = [];
            $data['vout'][$i] += walkChunkRaw($fp, $outBase);

            $data['vout'][$i]['scriptPubKey'] = readScriptRaw($fp);
        }

        $chunkBase = [
            'nLockTime' => [32],
        ];
        $data['nLockTime'] = unpack('V', fread($fp, 4))[1];

        fclose($fp);
        return new self($data);
    }
}
