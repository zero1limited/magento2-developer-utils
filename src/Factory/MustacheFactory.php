<?php

namespace Zero1\MagentoDev\Factory;

use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;

class MustacheFactory
{
    public function build()
    {
        return new Mustache_Engine(array(
            'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . '/../../var/templates'),
        ));
    }
}