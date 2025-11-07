<?php
namespace MDOQ\SitePerformanceMonitoring\Model;

use GuzzleHttp\Utils;
use Carbon\Carbon;
use Magento\Framework\Filesystem\DirectoryList;
use MDOQ\SitePerformanceMonitoring\Helper\Configuration;
use Magento\Framework\App\DeploymentConfig;

class Logger
{
    public function __construct(
        protected DirectoryList $directoryList,
        protected Configuration $configuration,
        protected DeploymentConfig $deploymentConfig
    ) {}

    /**
     * @param \Magento\Framework\App\Request\Http $request
     */
    public function log($request)
    {
        $input = file_get_contents('php://input');
        if(!$input){
            return;
        }
        try{
            $input = Utils::jsonDecode($input, true);
        }catch(\Throwable $e){
            return;
        }

        // some validation
        if(!isset($input['page']) || !is_string($input['page'])){
            return;
        }
        if(!isset($input['timing'])){
            return;
        }

        $url = $input['page'];
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }
        $domain = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        // is domain allowed
        $allowedDomains = $this->configuration->getAllowedDomains();
        if(!in_array($domain, $allowedDomains)){
            return;
        }

        // https://developer.mozilla.org/en-US/docs/Web/API/Performance_API/Resource_timing
        // connect to server: performance.timing.requestStart - performance.timing.connectStart
        // ttfb: performance.timing.responseStart - performance.timing.requestStart
        // download page: performance.timing.responseEnd - performance.timing.responseStart
        $requestStart = $input['timing']['requestStart'] ?? null;
        $connectStart = $input['timing']['connectStart'] ?? null;
        $responseStart = $input['timing']['responseStart'] ?? null;
        $responseEnd = $input['timing']['responseEnd'] ?? null;
        if($requestStart === null || $connectStart === null || $responseStart === null || $responseEnd === null){
            return;
        }

        $connectToServer = max(0, $requestStart - $connectStart); // "Request sent"
        $ttfb = max(0, $responseStart - $requestStart);           // "Waiting for server response"
        $downloadPage = max(0, $responseEnd - $responseStart);    // "Content Download"
        $total = max(0, $responseEnd - $connectStart);            // Total I think

        // log it
        $data = [
            // about the page loaded
            'timestamp' => Carbon::now()->toIso8601String(),
            'domain' => $domain ?? '',
            'path' => $path ?? '',
            'query' => $query ?? '',
            'connect_to_server' => $connectToServer,
            'ttfb' => $ttfb,
            'download_page' => $downloadPage,
            'total' => $total,

            // about the submiter
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'real_ip' => $_SERVER['HTTP_X_REAL_IP'] ?? '',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
            'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        ];

        $redisConfig = $this->deploymentConfig->get('cache/frontend/default/backend_options');
        $redis = new \Credis_Client(
            $redisConfig['server'] ?? 'redis',
            6379,
            1,
            '',
            3
        );
        $redis->connect();
        $redis->rPush(
            $domain,
            json_encode($data)
        );
        $redis->close();
    }
}