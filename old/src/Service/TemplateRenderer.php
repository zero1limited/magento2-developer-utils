<?php

namespace Zero1\MagentoDev\Service;

class TemplateRenderer
{
    protected $renderer;

    public function getRenderer()
    {
        if(!$this->renderer){
            // $this->renderer = new Mustache_Engine(array(
            //     'entity_flags' => ENT_QUOTES,
            //     'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . '/../../var/templates'),
            //     'helpers' => [
            //         'lower_case' => function($string = null, ?\Mustache_LambdaHelper $render = null){
            //             if($render){
            //                 $string = $render($string);
            //             }
            //             return strtolower($string);
            //         }
            //     ]
            // ));
        }
        return $this->renderer;
    }
}