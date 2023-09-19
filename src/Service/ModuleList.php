<?php

namespace Zero1\MagentoDev\Service;

class ModuleList
{
    protected $modules;

    public function find($name)
    {
        if(!$this->modules){
            $this->scan();
        }
        echo __METHOD__.' '.$name.PHP_EOL;
    }

    public function scan()
    {
        $this->modules = [];

        echo getcwd().PHP_EOL;
    }
}