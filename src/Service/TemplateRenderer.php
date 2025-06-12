<?php

namespace Zero1\MagentoDev\Service;

use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;
use Symfony\Component\Filesystem\Filesystem;

class TemplateRenderer
{
    /** @var Mustache_Engine */
    protected $renderer;

    /** @var Filesystem */
    protected $filesystem;

    public function __construct()
    { 
        $this->filesystem = new Filesystem();
    }

    public function getRenderer()
    {
        if(!$this->renderer){
            $this->renderer = new Mustache_Engine(array(
                'entity_flags' => ENT_QUOTES,
                'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__) . '/../../var/templates'),
                'helpers' => [
                    'lower_case' => function($string = null, \Mustache_LambdaHelper $render = null){
                        if($render){
                            $string = $render($string);
                        }
                        return strtolower($string);
                    }
                ]
            ));
        }
        return $this->renderer;
    }

    /**
     * @param array<string> $templates
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function renderTemplates(array $templates, $data = [])
    {
        $rendered = [];
        foreach($templates as $template){
           $rendered[$template] = $this->renderTemplate($template, $data);
        }
        return $rendered;
    }

    /**
     * Render a template with the given data.
     *
     * @param string $template The name of the template to render.
     * @param array<string, mixed> $data The data to pass to the template.
     * @return string The rendered template.
     */
    public function renderTemplate($template, $data = [])
    {
        return $this->getRenderer()->render($template, $data);
    }

    /**
     * @param string $template The name of the template to render.
     * @param string $filepath The path where the rendered template should be saved.
     * @param array<string, mixed> $data The data to pass to the template.
     * @return string The rendered template.
     */
    public function writeTemplate($template, $filepath, $data = [])
    {
        $rendered = $this->renderTemplate($template, $data);

        if(!$this->filesystem->exists(dirname($filepath))){
            $this->filesystem->mkdir(dirname($filepath), 0777);
        }
        $this->filesystem->dumpFile($filepath, $rendered);
        return $rendered;
    }

    /**
     * Write multiple templates to files.
     * @param array<string, string> $templates An associative array where keys are template names and values are file paths.
     * @param array<string, mixed> $data The data to pass to the templates.
     * @return array<string, string> An associative array where keys are template names and values are the rendered content.
     */
    public function writeTemplates(array $templates, $data = [])
    {
        $rendered = [];
        foreach($templates as $template => $output){
            $rendered[$template] = $this->writeTemplate($template, $output, $data);
        }
        return $rendered;
    }
}