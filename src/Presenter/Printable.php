<?php

namespace Xpcoin\BlockFileWalker\Presenter;

use Xpcoin\BlockFileWalker\Exception;
use Xpcoin\BlockFileWalker\DiskBlockIndex;
use function Xpcoin\BlockFileWalker\toInt;
use function Xpcoin\BlockFileWalker\toAmount;

trait Printable
{
    /*
     * needs:
     *
     * const INT_KEYS = [];
     * const HEX_KEYS = [];
     * const TIME_KEYS = [];
     * const AMOUNT_KEYS = [];
     * const NOREVERSE_KEYS = [];
     */

    public function toString()
    {
        $ret = '***** '
             . basename(str_replace('\\', '/', get_class($this)))
             . " *****\n";
        return $ret . self::_toString($this->data);
    }

    private static function _toString($data, $tab = 0)
    {
        $tab = str_repeat(' ', $tab * 4);
        $ret = '';
        foreach ($data as $k => $v){
            $out = null;

            if (is_object($v)){
                $out = self::getStrValue($data, $k);
                if ($out === null)
                    continue;
                $out = preg_replace('/^/m', '    ', $out);
                $ret .= $out;
                continue;
            }

            if (is_array($v)){
                $ret .= sprintf($tab . "%s: \n", $k);
                $ret .= self::_toString($v, $tab + 1);
                continue;
            }

            $out = self::getStrValue($data, $k);
            if ($out !== null)
                $ret .= sprintf($tab . "%s: %s\n", $k, $out);
        }
        return $ret;
    }
    public function __toString() { return $this->toString(); }

    public static function getStrValue($data, $k)
    {
        $v = $data[$k];
        if (is_object($v)){
            if (is_callable([$v, 'getPresenter'])){
                $p = $v::getPresenter($v);
                return $p->toString();
            }else if (is_callable([$v, 'toString'])){
                $ret = $v->toString();
                if (strlen($ret) == 0)
                    return null;

                $ret = sprintf("%s: %s\n", $k, $ret);
                if ($k == 'scriptPubKey'){
                    // scriptPubKey
                    // TODO: refactoring to move presenter
                    $addrs = $v->extractDestinations();
                    $ret .= sprintf("  %s", $addrs[0]);
                    foreach ($addrs[1] as $addr)
                        $ret .= sprintf(", %s", $addr);
                    $ret .= "\n";
                }
                if ($k == 'scriptSig'){
                    // scriptSig
                    // TODO: refactoring to move presenter
                    // $addrs = $v->extractFromAddresses();
                    // foreach ($addrs as $addr){
                    //     $ret .= "  $addr\n";
                    // }
                }

                return $ret;
            }
            return null;
        }

        if (is_array($v)){
            return $v;
        }


        if (in_array($k, self::REVERSE_KEYS))
            $v = strrev($v);

        if (in_array($k, self::INT_KEYS))
            return toInt($v);
        else if (in_array($k, self::HEX_KEYS))
            return bin2hex($v);
        else if (in_array($k, self::TIME_KEYS))
            return date('Y-m-d H:i:s', toInt($v));
        else if (in_array($k, self::AMOUNT_KEYS))
            return toAmount($v);

        return null;
    }

    public function __isset($k) { return isset($this->data[$k]); }
    public function __unset($k) { unset($this->data[$k]); }
    public function __set($_, $__) { throw new Exception('unsupported'); }
    public function __get($k) { return self::getStrValue($this->data, $k); }
}
