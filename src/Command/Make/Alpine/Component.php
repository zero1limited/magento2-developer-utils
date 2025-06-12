<?php

namespace Zero1\MagentoDev\Command\Make\Alpine;

use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Zero1\MagentoDev\Service\Composer as ComposerService;
use Zero1\MagentoDev\Service\Git as GitService;
use Symfony\Component\Filesystem\Filesystem;
use mikehaertl\shellcommand\Command as ShellCommand;
use Zero1\MagentoDev\Service\Git\UnconfiguredException;
use Zero1\MagentoDev\Service\TemplateRenderer;
use Zero1\MagentoDev\Service\Module as ModuleService;

class Component extends Command
{
    protected static $defaultName = 'make:alpine:component';

    /** @var Filesystem */
    protected $filesystem;

    public function __construct(
        protected TemplateRenderer $templateRenderer,
        protected ModuleService $moduleService
    ) {
        $this->filesystem = new Filesystem();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Add an alpine component to a module')
            ->setHelp('This command will create an Alpine component for a Magento module');

        $this->addOption('module', null, InputOption::VALUE_REQUIRED, 'name of the module (Magento module name, MyCompany_MyModule)');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'name of the component', null);
        $this->addOption('area', null, InputOption::VALUE_REQUIRED, 'The area for the template (frontend or backend)', null);
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'the relative path of the component', null);
        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $context = [];

        $moduleName = $input->getOption('module');
        if(!$moduleName){
            $question = new Question('Please enter the name of the module (MyCompany_MyModule): ', null);
            $moduleName = trim($helper->ask($input, $output, $question));
        }
        if(!$moduleName){
            $output->writeln('You need to specify a Magento module name');
            return 1;
        }
        try{
            $module = $this->moduleService->locate($moduleName);
        }catch(\Exception $e){
            $output->writeln('Error: '.$e->getMessage());
            return $e->getCode() > 0? $e->getCode() : 1;
        }

        $name = $input->getOption('name');
        if(!$name){
            $question = new Question('Please enter the name of the component ("MyComponent"): ', null);
            $name = trim($helper->ask($input, $output, $question));
        }
        if(!$name){
            $output->writeln('You need to specify a name for the component');
            return 1;
        }

        $area = $input->getOption('area');
        if(!$area || !in_array($area, ['frontend', 'adminhtml'])){
            $question = new Question('Please enter the area for the component template (frontend or adminhtml): ', null);
            $area = trim($helper->ask($input, $output, $question));
        }
        if(!$area || !in_array($area, ['frontend', 'adminhtml'])){
            $output->writeln('You need to specify an area for the component template');
            return 1;
        }

        $templatePrefix = $module->getBaseDirectory().'/view/'.$area.'/templates/';

        $path = $input->getOption('path');
        if(!$path){
            $question = new Question('Please enter the path for the component template relative to '.$templatePrefix.' (eg foo.phtml): ', null);
            $path = trim($helper->ask($input, $output, $question));
        }
        if(!$path){
            $output->writeln('You need to specify a path for the component');
            return 1;
        }

        $overwrite = (bool)$input->getOption('overwrite');

        $context['magento_module_name'] = $moduleName;
        $context['component_name'] = $name;

        // actually create the component
        $outputPath = $templatePrefix.$path;
        if(!$this->filesystem->exists(dirname($outputPath))){
            $this->filesystem->mkdir(dirname($outputPath), 0777);
        }

        if($this->filesystem->exists($outputPath) && !$overwrite){
            $output->writeln('Template already exists: '.$outputPath);
            $question = new ConfirmationQuestion('Overwrite [Y/n]?', true);
            if (!$helper->ask($input, $output, $question)) {
                return 1;
            }
        }

        $this->templateRenderer->writeTemplate(
            'Alpine/component.phtml.mustache', 
            $outputPath, 
            $context
        );
        $output->writeln('Written: '.$outputPath);
        $output->writeln('You can include this component, with layout xml: ');
        $blockName = strtolower($module->getName().'_'.$name);
        $templateName = $module->getName().'::'.$path;
        $output->writeln('<block name="'.$blockName.'" template="'.$templateName.'" />');


        $output->writeln('OK');
        
        return 0;
    }

    protected function getComposerPackageNameFromMagentoModuleName($magentoModuleName)
    {
        $magentoModuleName = strtolower(preg_replace('/((?!_).{1})([A-Z])/', '$1-$2', $magentoModuleName));
        list($company, $package) = explode('_', $magentoModuleName, 2);

        return $company.'/'.$package;
    }

    protected function getExtensionsDirectory($composerPackage)
    {
        return 'extensions/'.$composerPackage;
    }

    protected function getAppCodeDirectory($magentoModuleName)
    {
        return 'app/code/'.str_replace('_', '/', $magentoModuleName);
    }
}