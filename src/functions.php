<?php

namespace Xpcoin\BlockFileWalker;
use StephenHill\Base58;

function startsWith($haystack, $needle){
    return (strpos($haystack, $needle) === 0);
}

function toAmount($bin)
{
    return toInt($bin) / 1000000.0;
}

function reverse8($str){
    return implode('', array_reverse(str_split($str, 2)));
}

function packStr($str){
    return pack('C', strlen($str)) . $str;
}

function packKey($key, $suffix = null){
    $ret = packStr($key);

    if ($suffix === null)
        return $ret;

    if (strlen($suffix) % 2 == 1)
        $suffix = '0' . $suffix;
    return $ret . strrev(hex2bin($suffix));
}

function readStr(&$str, $byte)
{
    $ret = substr($str, 0, $byte);
    $str = substr($str, $byte);
    return $ret;
}
function readStrRev(&$str, $byte){
    return strrev(readStr($str, $byte));
}


function toInt($bin)
{
    return intval(bin2hex($bin), 16);
}

function raw256toHexStr($arr)
{
    $x16 = '';
    foreach ($arr as $v){
        $x16 = dechex($v) . $x16;
    }
    return $x16;
}

function walkChunk($iobit, $chunkBase)
{
    $data = [];
    foreach ($chunkBase as $name => $chunks){
        $bs = [];

        foreach ($chunks as $chunk){
            $bs[] = $iobit->getUIBits($chunk);
        }

        $data[$name] = new Uint32base($bs);
    }
    return $data;
}

// TODO: refactoring $iobit $fp interface
function walkChunkRaw($fp, $chunkBase)
{
    $label = [
        4 => 'V',
        8 => 'P',
    ];
    $data = [];
    foreach ($chunkBase as $name => $chunks){
        $bs = [];

        foreach ($chunks as $chunk){
            $byte = $chunk/8;
            $bs[] = unpack($label[$byte], fread($fp, $byte))[1];
        }

        if (count($chunks) == 1)
            $bs = $bs[0];

        $data[$name] = $bs;
    }
    return $data;
}

// from serialize.h
function readCompactSize(&$str)
{
    $ret = 0;
    $size = ord(readStrRev($str, 1));
    if ($size < 253)
        return $size;

    if ($size == 253)
        return hexdec(bin2hex(readStrRev($str, 2)));
    if ($size == 254)
        return hexdec(bin2hex(readStrRev($str, 4)));

    return hexdec(bin2hex(readStrRev($str, 8)));
}

function readCompactSizeRaw($fp)
{
    $ret = 0;
    $size = fread($fp, 1);
    $size = ord($size);
    if ($size < 253)
        return $size;

    if ($size == 253)
        return hexdec(bin2hex(strrev(fread($fp, 2))));
    if ($size == 254)
        return hexdec(bin2hex(strrev(fread($fp, 4))));

    return hexdec(bin2hex(strrev(fread($fp, 8))));
}

function readFpVector($fp)
{
    $size = readCompactSizeRaw($fp);
    if ($size <= 0)
        return '';

    if ($size > 2147483647){
        $pos = ftell($fp);
        throw new Exception('read size over: size=' . $size . ', pos=' . $pos);
    }

    return fread($fp, $size);
}

function addrToBin($addr)
{
    $base58 = new Base58();
    $bin = $base58->decode($addr);
    return $bin;
}

function toByteaDb($str)
{
    return readStr($str, 8);
}

function decToBin($dec)
{
    $hex = dechex($dec);
    if (strlen($hex) % 2 == 1)
        $hex = '0' . $hex;
    return hex2bin($hex);
}
