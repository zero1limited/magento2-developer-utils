<?php

namespace Zero1\MagentoDev\Command\Debug;

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

class Debug extends Command
{
    protected static $defaultName = 'debug:debug';

    /** @var ComposerService */
    protected $composerService;

    /** @var GitService */
    protected $gitService;

    public function __construct(
        ComposerService $composerService,
        GitService $gitService
    ) {
        $this->composerService = $composerService;
        $this->gitService = $gitService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('For Debug')
            ->setHelp('for debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // $this->gitService->setOutput($output);
        // $this->composerService->addRepository('aaaa', [
        //     'type' => 'path', 
        //     'url' => 'extensions/zero1/smile-elasticsuite-concreate-category-product-rewrites',
        //     'options' => [
        //         'symlink' => true,
        //     ]
        // ]);

        // $this->composerService->removeRepository('aaaa');
        // die('yo');

        try{
            
        // $this->gitService->initializeRepository(
        //     'extensions/zero1/admin-user-password-requirements',
        //     'git@github.com:zero1limited/magento2-admin-user-password-requirements.git'
        // );
            $this->gitService->configure('goo', 'boo');
        }catch(\Exception $e){
            echo $e->getMessage().PHP_EOL;
            return $e->getCode();
        }
        return 0;
    }
}