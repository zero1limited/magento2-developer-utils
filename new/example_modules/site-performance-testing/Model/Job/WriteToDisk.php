<?php
namespace MDOQ\SitePerformanceMonitoring\Model\Job;

use GuzzleHttp\Utils;
use Carbon\Carbon;
use League\Csv\Writer;
use Magento\Framework\Filesystem\DirectoryList;
use MDOQ\SitePerformanceMonitoring\Helper\Configuration;
use Magento\Framework\App\DeploymentConfig;

class WriteToDisk
{
    public function __construct(
        protected DirectoryList $directoryList,
        protected Configuration $configuration,
        protected DeploymentConfig $deploymentConfig
    ) {}

    public function execute()
    {
        $domains = $this->configuration->getAllowedDomains();
        $domains[] = 'www-mdoq-io-21820.54.mdoq.dev';
        $redisConfig = $this->deploymentConfig->get('cache/frontend/default/backend_options');
        $redis = new \Credis_Client(
            $redisConfig['server'] ?? 'redis',
            6379,
            1,
            '',
            3
        );
        $redis->connect();

        $startTime = time();
        do{
            $recordsProcessed = 0;
            foreach($domains as $domain){
                echo 'domain: '.$domain.PHP_EOL;
                $rows = [];
                $counter = 0;
                do{
                    $row = $redis->rPop($domain);
                    if($row){
                        $counter++;
                        $rows[] = json_decode($row, true);
                    }
                }while($counter < 1000 && $row);

                // Process the rows
                if(!empty($rows)){
                    $recordsProcessed += $counter;
                    $this->writeToDisk($domain, $rows);
                }
            }
        }while($recordsProcessed > 0 && (time() - $startTime) < 45);

        $redis->close();
    }

    protected function writeToDisk(string $domain, array $rows)
    {
        // is file too big
        $filePath = $this->directoryList->getPath('var').'/performance_monitoring/'.$domain.'/'.Carbon::now()->format('Y-m').'.csv';
        $dir = dirname($filePath);
        if(!is_dir($dir)){
            if(!mkdir($dir, 0777, true) && !is_dir($dir)){
                throw new \Exception('failed to create directory: '.$dir);
            }
        }
        if(is_file($filePath)){
            $fileSize = filesize($filePath);
            if($fileSize > 1073741824){ // 1GB
                return;
            }
        }

        if(!is_file($filePath)){
            $writer = Writer::createFromPath($filePath, 'w+');
            $writer->insertOne([
                // about the page loaded
                'timestamp',
                'domain',
                'path',
                'query',
                'connect_to_server',
                'ttfb',
                'download_page',
                'total',

                // about the submiter
                'user_agent',
                'real_ip',
                'remote_addr',
                'forwarded_for',
            ]);
        }else{
            $writer = Writer::createFromPath($filePath, 'a+');
        }

        $writer->setDelimiter(',');
        $writer->setEscape('');
        $writer->insertAll($rows);
    }
}