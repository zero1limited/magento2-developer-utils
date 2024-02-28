<?php

namespace Zero1\MagentoDev\Service;
use mikehaertl\shellcommand\Command;
use Symfony\Component\Console\Output\NullOutput;
use Zero1\MagentoDev\Service\Git\UnconfiguredException;
use Symfony\Component\Console\Output\OutputInterface;

class Git
{
    /** @var bool */
    protected $configured;

    /** @var null|OutputInterface */
    protected $output;

    public function __construct()
    {
        $this->output = new NullOutput();
    }

    public function setOutput($output)
    {
        $this->output = $output;
    }

    public function initializeRepository(
        $directoryPath,
        $repository,
        $additions = '.',
        $commitMessage = 'Initial commit',
        $branch = 'master'
    ){
        $originalDir = getcwd();
        if(!is_dir($directoryPath)){
            throw new \InvalidArgumentException(sprintf(
                'Directory path not valid, directory path: %s, current directory: %s',
                $directoryPath,
                $originalDir
            ));
        }
        chdir($directoryPath);
        $this->checkConfigured();

        try{
            $this->execCommand(new Command('git init'));

            $this->execCommand(new Command('git add '.$additions));

            $this->execCommand(new Command('git commit -m "'.$commitMessage.'"'));

            $this->execCommand(new Command('git branch -M '.$branch));

            $this->execCommand(new Command('git remote add origin '.$repository));

            $this->execCommand(new Command('git push -u origin '.$branch));
        }catch(\Exception $e){
            chdir($originalDir);
            throw $e;
        }
        chdir($originalDir);
    }

    public function addSubmodule($repository, $directoryPath, $moduleName, $branch = 'master')
    {
        $this->execCommand(new Command('git submodule add --name '.$moduleName.' --branch '.$branch.' '.$repository.' '.$directoryPath));
    }

    /**
     * @param mikehaertl\shellcommand\Command $command
     * @return string
     * @throws \Exception
     */
    protected function execCommand($command, $outputOutput = true)
    {
        $command->execute();
        if($command->getExitCode() != 0){
            throw new \Exception(sprintf(
                'There was an error running: %s'.PHP_EOL.'Output: %s'.PHP_EOL.'Error: %s'.PHP_EOL.'Exit Code: %s',
                $command->getCommand(),
                $command->getOutput(),
                $command->getError(),
                $command->getExitCode()
            ), $command->getExitCode());
        }
        if($outputOutput && $command->getOutput()){
            $this->output->writeln(
                $command->getOutput()
            );
        }
        return $command->getOutput();
    }

    protected function checkConfigured()
    {
        if(!$this->configured){
            try{
                $userName = $this->execCommand(new Command('git config --global --get user.name'));
            }catch(\Exception $e){
                $userName = '';
            }
            try{
                $userEmail = $this->execCommand(new Command('git config --global --get user.email'));
            }catch(\Exception $e){
                $userEmail = '';
            }
            
            if(!$userEmail || $userEmail){
                throw new UnconfiguredException($userName, $userEmail);
            }
        }
        return true;
    }

    public function configure($userName, $userEmail)
    {
        $this->configured = true;
        $this->execCommand(new Command('git config --global user.name '.$userName));
        $this->execCommand(new Command('git config --global user.email '.$userEmail));
    }
}