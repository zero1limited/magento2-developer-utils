<?php

namespace Zero1\MagentoDev\Service;
use mikehaertl\shellcommand\Command;

class Git
{
    public function initializeRepository(
        $directoryPath,
        $repository,
        $additions = '.',
        $commitMessage = 'Initial commit',
        $branch = 'master'
    ){
        $originalDir = getcwd();
        chdir($directoryPath);

        try{
            echo $this->execCommand(new Command('git init'));

            echo $this->execCommand(new Command('git add '.$additions));

            echo $this->execCommand(new Command('git commit -m "'.$commitMessage.'"'));

            echo $this->execCommand(new Command('git branch -M '.$branch));

            echo $this->execCommand(new Command('git remote add origin '.$repository));

            echo $this->execCommand(new Command('git push -u origin '.$branch));
        }catch(\Exception $e){
            chdir($originalDir);
            throw $e;
        }
        chdir($originalDir);
    }

    public function addSubmodule($repository, $directoryPath, $moduleName, $branch = 'master')
    {
        echo $this->execCommand(new Command('git submodule add --name '.$moduleName.' --branch '.$branch.' '.$repository.' '.$directoryPath));
    }

    /**
     * @param mikehaertl\shellcommand\Command $command
     * @return string
     * @throws \Exception
     */
    protected function execCommand($command)
    {
        $command->execute();
        if($command->getExitCode() != 0){
            throw new \Exception($command->getOutput().PHP_EOL.$command->getError(), $command->getExitCode());
        }
        return $command->getOutput();
    }
}