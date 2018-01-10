<?php

namespace Xpcoin\Explorer;

class Uint32base
{
    private $uint32s;
    public function __construct($uint32s, $key = null)
    {
        if ($key !== null){
            $arr = [];
            $len = count($uint32s);
            for ($i = 0; $i < $len; $i++){
                if (isset($uint32s['i' . $i]))
                    $arr[] = $uint32s['i' . $i];
            }
            $uint32s = $arr;
        }
        $this->uint32s = $uint32s;
    }

    public function getUint32s()
    {
        return $this->uint32s;
    }

    public function toString()
    {
        $ret = '';
        foreach ($this->uint32s as $uint32)
            $ret .= sprintf('%08x', $uint32);
        return reverse8($ret);
    }

    public function __toString() { return $this->toString(); }

    public function toInt()
    {
        if (count($this->uint32s) > 2)
            throw new Exception('may overflow'); // 64bit pc

        return intval('' . $this, 16);
    }
}
