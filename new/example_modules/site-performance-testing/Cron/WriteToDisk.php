<?php
namespace MDOQ\SitePerformanceMonitoring\Cron;

use MDOQ\SitePerformanceMonitoring\Model\Job\WriteToDisk as Job;

class WriteToDisk
{
    public function __construct(
        protected Job $job,
    ) {}

    public function execute()
    {
        $this->job->execute();
    }
}