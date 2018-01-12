<?php

namespace Xpcoin\Explorer;

function startsWith($haystack, $needle){
    return (strpos($haystack, $needle) === 0);
}

function toAmount($base)
{
    return $base / 1000000;
}

function reverse8($str){
    return implode('', array_reverse(str_split($str, 2)));
}

function packStr($str){
    return pack('C', strlen($str)) . $str;
}

function pack8Str($str){
    $len = strlen($str);
    $pad = $len % 2;
    $str = str_pad($str, $len + $pad, '0', STR_PAD_LEFT);
    $ret = reverse8($str);
    return $ret;
}

function packKey($key, $suffix = null){
    $ret = packStr($key);

    if ($suffix === null)
        return $ret;

    $len = strlen($ret);

    // TODO: refactoring
    $blocklen = 0;
    $block = $suffix;
    $block = pack8str($block);
    $blocklen = strlen($block);
    $pad = $blocklen % 8;
    $block = str_pad($block, $blocklen + (8 - $pad) % 8, '0');
    $int32s = str_split($block, 8);
    foreach ($int32s as $k => $v)
        $int32s[$k] = intval($v, 16);

    $blockbin = pack(str_repeat('N', count($int32s)), ...$int32s);
    $blockbin = substr($blockbin, 0, $blocklen / 2);


    return $ret . $blockbin;
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

        $data[$name] = $bs;
    }
    return $data;
}

// from serialize.h
function readCompactSize($iobit)
{
    $ret = 0;
    $size = $iobit->getUIBits(8);
    if ($size < 253)
        return $size;

    if ($size == 253)
        return $iobit->getUIBits(16);
    if ($size == 254)
        return $iobit->getUIBits(32);

    return $iobit->getUIBits(64);
}

function readCompactSizeRaw($fp)
{
    $ret = 0;
    $size = fread($fp, 1);
    $size = ord($size);
    if ($size < 253)
        return $size;

    if ($size == 253)
        return ord(fread($fp, 2));
    if ($size == 254)
        return ord(freed($fp, 4));

    return ord(freed($fp, 8));
}

function readScriptRaw($fp)
{
    $size = readCompactSizeRaw($fp);
    $ret = '';
    for ($i = 0; $i < $size; $i++){
        $ret .= bin2hex(fread($fp, 1));
    }
    return $ret;
}
