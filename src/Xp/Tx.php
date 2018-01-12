<?php

namespace Xpcoin\BlockFileWalker\Xp;

use Xpcoin\BlockFileWalker\App;
use Xpcoin\BlockFileWalker\Script;

use function Xpcoin\BlockFileWalker\toAmount;
use function Xpcoin\BlockFileWalker\walkChunkRaw;
use function Xpcoin\BlockFileWalker\readCompactSizeRaw;
use function Xpcoin\BlockFileWalker\readVcharRaw;
use function Xpcoin\BlockFileWalker\raw256toHexStr;

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
                if (!isset($v[0])){
                    $show = "\n";
                    // coinbase
                    foreach ($v as $_k => $_v){
                        $show .= sprintf("  %16s: %s\n",
                                         $_k,
                                         $_v);
                    }
                    break;
                }

                $show = "\n";
                foreach ($v as $_k => $_v){
                    $show .= "$k: $_k\n";
                    $show .= sprintf("  %16s: %s\n",
                                     'prevout.hash',
                                     raw256toHexStr($_v['prevout.hash']));
                    foreach (['prevout.n', 'scriptSig', 'nSequence'] as $__k){
                        $show .= sprintf("  %16s: %s\n",
                                         $__k,
                                         $_v[$__k]);
                    }
                }
                break;

            case 'vout':
                $show = "\n";
                foreach ($v as $_k => $_v){
                    $show .= sprintf("  %16s: %s\n",
                                     'nValue',
                                     toAmount($_v['nValue']));
                    $show .= sprintf("  %16s: %s\n",
                                     'scriptPubKey',
                                     $_v['scriptPubKey']);
                    list($t, $ds) = $_v['scriptPubKey']->extractDestinations();
                    $show .= sprintf("  %16s: %s\n",
                                     'addresses',
                                     $t);

                    foreach ($ds as $d){
                        $show .= sprintf("%20s%s\n", '', $d);
                    }
                }
                break;

            case 'nTime':
            case 'nLockTime':
                $show = date('Y-m-d H:i:s', $v);
                break;

            default:
                break;
            }
            if ($k == 'txid')
                $ret .= sprintf("  %s: %s\n", $k, $show);
            else
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

        $ret = self::readFp($fp);
        fclose($fp);
        return $ret;
    }

    public static function readFp($fp)
    {
        $start = ftell($fp);
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

            $data['vin'][$i]['scriptSig'] = new Script(readVcharRaw($fp));
            $data['vin'][$i]['nSequence'] = unpack('V', fread($fp, 4))[1];
        }

        $isCoinbase = self::isCoinbase($data['vin']);

        if ($isCoinbase){
            $data['vin']['coinbase'] = $data['vin'][0]['scriptSig'];
            $data['vin']['nSequence'] = $data['vin'][0]['nSequence'];
            unset($data['vin'][0]);
        }else{
            // TODO: asm
        }


        $outBase = [
            'nValue' => [64],
        ];
        $size = readCompactSizeRaw($fp);
        $data['vout'] = [];
        for ($i = 0; $i < $size; $i++){
            $data['vout'][$i] = [];
            $data['vout'][$i] += walkChunkRaw($fp, $outBase);

            $data['vout'][$i]['scriptPubKey'] = new Script(readVcharRaw($fp));
        }

        $chunkBase = [
            'nLockTime' => [32],
        ];
        $data['nLockTime'] = unpack('V', fread($fp, 4))[1];

        $end = ftell($fp);

        fseek($fp, $start);
        $bin = fread($fp, $end - $start);
        $hash = hash('sha256', $bin, true);
        $hash = hash('sha256', $hash, true);

        $hash = strrev($hash);
        $renew = [
            'txid' => bin2hex($hash)
        ];
        $data = $renew + $data;

        return new self($data);
    }
}
