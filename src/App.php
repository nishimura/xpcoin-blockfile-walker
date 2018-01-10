<?php

namespace Xpcoin\Explorer;

use Laiz\Template\Parser;

class App
{
    const ACTION_INDEX = 'index';
    const ACTION_TX = 'tx';
    const ACTION_BLOCK = 'block';
    const ACTION_SEARCH = 'search';

    private $rootdir;
    private $config;
    private $action;
    private $params;

    public function __construct($dir, $config)
    {
        $this->rootdir = $dir;
        $this->config = $config;

        $this->params = new \stdClass;
        $this->params->title = $this->config['title'];
    }

    public function run($query = null)
    {
        $p = $this->params;
        $p->query = $query;

        // TODO
        $q = packKey('blockindex', $query);

        $p->rows = $this->query($q);

        //$this->params->subtitle = 'index';
        return $this;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function show($public, $cache)
    {
        switch ($this->action){
        case self::ACTION_TX:
        case self::ACTION_BLOCK:
        case self::ACTION_SEARCH:
            $file = $this->action . '.html';

        case self::ACTION_INDEX:
        default:
            $file = self::ACTION_INDEX . '.html';
            break;
        }

        $tmpl = new Parser($public, $cache);
        $tmpl->addBehavior('l',
                           'Xpcoin\Explorer\Presenter\Filter::toHashLink',
                           true);
        $tmpl->setFile($file)->show($this->params);
    }


    private function query($query)
    {
        $ret = [];
        $db = new Db($this->config['datadir']);
        foreach ($db->range($query) as $key => $value){
            $block = Xp\DiskBlockIndex::fromBinary($key, $value);
            $ret[] = $block;
        }
        return $ret;
    }
}
