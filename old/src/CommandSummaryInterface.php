<?php

declare(strict_types=1);

namespace Zero1\MagentoDev;
use Minicli\App;

interface CommandSummaryInterface
{
    /**
     * Return a summary of the command
     * 
     * @param App $app
     *
     * @return void
     */
    public function getSummary(App $app): void;
}
