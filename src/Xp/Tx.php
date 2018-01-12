<?php

namespace Xpcoin\Explorer\Xp;

use Xpcoin\Explorer\Uint32base;
use IO_Bit;

use Xpcoin\Explorer\App;
use Xpcoin\Explorer\Script;

use function Xpcoin\Explorer\toAmount;
use function Xpcoin\Explorer\walkChunkRaw;
use function Xpcoin\Explorer\readCompactSizeRaw;
use function Xpcoin\Explorer\readScriptRaw;
use function Xpcoin\Explorer\raw256toHexStr;

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
                if (!is_array($v)) // coinbase
                    break;

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

            $data['vin'][$i]['scriptSig'] = new Script(readScriptRaw($fp));
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

            $data['vout'][$i]['scriptPubKey'] = new Script(readScriptRaw($fp));
        }

        $chunkBase = [
            'nLockTime' => [32],
        ];
        $data['nLockTime'] = unpack('V', fread($fp, 4))[1];

        fclose($fp);
        return new self($data);
    }
}
