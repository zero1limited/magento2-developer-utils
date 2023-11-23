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

class Debug extends Command
{
    protected static $defaultName = 'debug:debug';

    /** @var ComposerService */
    protected $composerService;

    public function __construct(
        ComposerService $composerService
    ) {
        $this->composerService = $composerService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('For Debug')
            ->setHelp('for debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // $this->composerService->addRepository('aaaa', [
        //     'type' => 'path', 
        //     'url' => 'extensions/zero1/smile-elasticsuite-concreate-category-product-rewrites',
        //     'options' => [
        //         'symlink' => true,
        //     ]
        // ]);

        $this->composerService->removeRepository('aaaa');
        die('yo');
    }
}