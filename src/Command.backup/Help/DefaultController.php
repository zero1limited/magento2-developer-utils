<?php

declare(strict_types=1);

namespace Zero1\MagentoDev\Command\Help;

use Minicli\Command\CommandController;
use Minicli\App;
use Minicli\ControllerInterface;
use Minicli\Output\OutputHandler;
use Zero1\MagentoDev\CommandSummaryInterface;

class DefaultController extends CommandController
{
    /** @var  array */
    protected $command_map = [];

    /**
     * Called before `run`
     *
     * @param App $app
     * @return void
     */
    public function boot(App $app): void
    {
        parent::boot($app);
        $this->command_map = $app->commandRegistry->getCommandMap();
    }

    public function handle(): void
    {
        $this->getPrinter()->info('Available Commands');

        foreach ($this->command_map as $command => $sub) {

            $this->getPrinter()->newline();
            $this->getPrinter()->out($command, 'info_alt');

            if (is_array($sub)) {
                foreach ($sub as $subcommand) {
                    if ($subcommand !== 'default') {
                        
                        $this->getPrinter()->newline();
                        $controller = $this->getApp()->commandRegistry->getCallableController($command, $subcommand);
                        if(!$controller instanceof CommandSummaryInterface){
                            $this->getPrinter()->out(sprintf('%s%s', '    ', $subcommand));
                        }else{
                            $controller->getSummary($this->getApp());
                        }
                        
                        // $this->getPrinter()->out(sprintf('%s%s', '└──', $subcommand));
                    }
                }
            }
            $this->getPrinter()->newline();
        }

        $this->getPrinter()->newline();
        $this->getPrinter()->newline();
    }
}


    