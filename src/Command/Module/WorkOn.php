<?php

namespace Zero1\MagentoDev\Command\Module;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Zero1\MagentoDev\Service\ModuleList;

class WorkOn extends Command
{
    protected static $defaultName = 'module:work-on';

    protected ModuleList $moduleList;

    public function __construct(
        ModuleList $moduleList
    ) {
        $this->moduleList = $moduleList;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Setup a module to be worked on locally')
            ->setHelp('This command allows you to specify a module to work on locally');

        $this->addArgument('name', InputArgument::REQUIRED, 'name of the module (composer package name, or Magento module name.)');
        $this->addOption('source', null, InputOption::VALUE_OPTIONAL, 'source', null);
        $this->addOption('branch', null, InputOption::VALUE_OPTIONAL, 'branch', null);
        $this->addOption('source-ref', null, InputOption::VALUE_OPTIONAL, 'source-ref', null);


    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        // find the module
        $module = $this->moduleList->find($input->getArgument('name'));

        if(!$module || !$module->hasSourceSpecified()){
            // prompt for source 
        }

        // checkout at a local submodule
        // git submodule add --force --name ModuleName git@github.com:acme/magento2-module.git extensions/acme/magento2-module

        // update composer.json
        /*
        "acme-magento2-module": {
            "type": "path",
            "url": "extensions/acme/magento2-module",
            "options": {
                "symlink": true
            }
        }
        */

        // checkout a branch if needed
        

        // composer require acme/magento2-module:@dev
        

        // return this if there was no problem running the command
        return 0;

        // or return this if some error happened during the execution
        // return 1;
    }
}