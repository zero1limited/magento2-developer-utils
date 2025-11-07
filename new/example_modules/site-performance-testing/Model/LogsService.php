<?php
namespace MDOQ\SitePerformanceMonitoring\Model;

use GuzzleHttp\Utils;
use Carbon\Carbon;
use League\Csv\Writer;
use Magento\Framework\Filesystem\DirectoryList;
use MDOQ\SitePerformanceMonitoring\Helper\Configuration;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use League\Csv\Reader;

class LogsService
{
    public function __construct(
        protected DirectoryList $directoryList,
        protected Configuration $configuration,
        protected FileDriver $fileDriver
    ) {}

    protected function getBaseDirectory()
    {
        return $this->directoryList->getPath('var') . '/performance_monitoring/';
    }

    public function getDomains()
    {
        if(!$this->fileDriver->isDirectory($this->getBaseDirectory())){
            return [];
        }
        $domains = [];
        $dir = $this->fileDriver->readDirectory($this->getBaseDirectory());
        foreach($dir as $domain){
            if($this->fileDriver->isDirectory($domain)){
                $domains[] = basename($domain); 
            }
        }
        return $domains;
    }

    public function getMonths($domain)
    {
        $domains = $this->getDomains();
        if(!in_array($domain, $domains)){
            return [];
        }
        $months = [];
        $dir = $this->fileDriver->readDirectory($this->getBaseDirectory() . $domain);
        foreach($dir as $file){
            if($this->fileDriver->isFile($file)){
                $month = basename($file, '.csv');
                $months[] = $month;
            }
        }
        return $months;
    }

    public function getOptionArray()
    {
        $options = [];
        foreach($this->getDomains() as $domain){
            $options[$domain] = [];
            $months = $this->getMonths($domain);
            foreach($months as $month){
                $options[$domain][$month] = $month;
            }
        }
        return $options;
    }

    public function getLogs($domain, $month)
    {
        $filePath = $this->getBaseDirectory() . $domain . '/' . $month . '.csv';
        if(!$this->fileDriver->isFile($filePath)){
            return [];
        }
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);
        $csv->setDelimiter(',');
        return $csv;
    }

    protected function isRowValid(array $row): bool
    {
        return isset(
            $row['timestamp'], 
            $row['domain'], 
            $row['path'], 
            $row['query'], 
            $row['connect_to_server'], 
            $row['ttfb'], 
            $row['download_page'], 
            $row['total'],
            $row['user_agent'], 
            $row['real_ip'], 
            $row['remote_addr'], 
            $row['forwarded_for']
            ) && is_numeric($row['total']);
    }

    public function getCachedVsNonCachedByDay($domain, $month)
    {
        $logs = $this->getLogs($domain, $month);
        
        $result = [];
        $d = Carbon::createFromFormat('Y-m', $month);
        $day = $d->clone()->startOfMonth();
        while($day->isSameMonth($d)){
            $result[$day->format('d')] = [0, 0];
            $day->addDay();
        }
        
        foreach ($logs as $row) {
            if(!$this->isRowValid($row)){
                continue;
            }
            $d = Carbon::parse($row['timestamp'])->format('d');
            $time = $row['total'];
            if($time < 300){
                // cached
                $result[$d][0]++;
            }else{
                // no cache
                $result[$d][1]++;
            }
            if(count($result[$d]) == 2){
                $result[$d][] = 0;
            }
        }

        foreach($result as $d => &$splits){
            if(count($splits) == 3){
                $t = array_sum($splits);
                $splits[0] = ($splits[0] > 0)? (floor(($splits[0] / $t) * 100)): 0;
                $splits[1] = 100 - $splits[0];
                unset($splits[2]);
            }
        }

        return $result;
    }

    public function getRequestTimeSplits($domain, $month)
    {
        $logs = $this->getLogs($domain, $month);
        
        $result = [];
        $d = Carbon::createFromFormat('Y-m', $month);
        $day = $d->clone()->startOfMonth();
        while($day->isSameMonth($d)){
            $result[$day->format('d')] = [0, 0, 0, 0, 0, 0, 0 ,0 ,0 ,0];
            $day->addDay();
        }
        
        foreach ($logs as $row) {
            if(!isset($row['timestamp']) || !isset($row['total']) || !is_numeric($row['total'])){
                continue; // skip invalid rows
            }
            $d = Carbon::parse($row['timestamp']);
            $time = $row['total'];
            switch($time){
                case $time < 100:
                    $index = 0; // 0-100ms
                    break;
                case $time < 200:
                    $index = 1; // 100-200ms
                    break;
                case $time < 300:
                    $index = 2; // 200-300ms
                    break;
                case $time < 400:
                    $index = 3; // 300-400ms
                    break;
                case $time < 500:
                    $index = 4; // 400-500ms            
                    break;
                case $time < 600:
                    $index = 5; // 500-600ms
                    break;
                case $time < 700:
                    $index = 6; // 600-700ms
                    break;
                case $time < 800:
                    $index = 7; // 700-800ms
                    break;
                case $time < 900:
                    $index = 8; // 800-900ms
                    break;
                default:
                    $index = 9; // 900ms+
                    break;
            }
            $result[$d->format('d')][$index] += 1;
        }
        return $result;
    }

    public function getResponseTimeVsRequestCount($domain, $month)
    {
        $logs = $this->getLogs($domain, $month);
        
        $result = [];
        $d = Carbon::createFromFormat('Y-m', $month);
        $day = $d->clone()->startOfMonth();
        while($day->isSameMonth($d)){
            $result[$day->format('d')] = [
                'rquest_count' => 0,
                'response_time' => 0,
            ];
            $day->addDay();
        }
        
        foreach ($logs as $row) {
            if(!isset($row['timestamp']) || !isset($row['total']) || !is_numeric($row['total'])){
                continue; // skip invalid rows
            }
            $d = Carbon::parse($row['timestamp']);
            $time = $row['total'];
            $result[$d->format('d')]['rquest_count']++;
            $result[$d->format('d')]['response_time'] += max(0, $time);
        }

        foreach ($result as $day => $data) {
            if ($data['rquest_count'] > 0) {
                $count = $data['rquest_count'];
                $time = $data['response_time'];
                $result[$day] = [
                    floor($time / $count), // average response time
                    $count // request count
                ];
            } else {
                $result[$day] = [0,0];
            }
        }
        return $result;
    }

    public function getRequestBreakdown($domain, $month)
    {
        $logs = $this->getLogs($domain, $month); 
        $result = [
            'connect_to_server' => 0,
            'ttfb' => 0,
            'download_page' => 0,
        ];
        $counter = 0;
        foreach ($logs as $row) {
            if(!$this->isRowValid($row)){
                continue;
            }
            $result['connect_to_server'] += $row['connect_to_server'];
            $result['ttfb'] += $row['ttfb'];
            $result['download_page'] += $row['download_page'];
            $counter++;
        }
        $result['connect_to_server'] = $result['connect_to_server'] > 0 ? floor($result['connect_to_server'] / $counter) : 0;
        $result['ttfb'] = $result['ttfb'] > 0 ? floor($result['ttfb'] / $counter) : 0;
        $result['download_page'] = $result['download_page'] > 0 ? floor($result['download_page'] / $counter) : 0;
        
        return [
            'Connect to Server' => $result['connect_to_server'],
            'TTFB' => $result['ttfb'],
            'Download Page' => $result['download_page'],
        ];
    }

    public function getCachedVsNonCached($domain, $month)
    {
        $logs = $this->getLogs($domain, $month);
        
        $result = [
            'cached' => 0,
            'non_cached' => 0,
        ];
        foreach ($logs as $row) {
            $time = $row['total'];
            switch($time){
                case $time < 100:
                case $time < 200:
                case $time < 300:
                    $result['cached'] += 1; // cached
                    break;
                default:
                    $result['non_cached'] += 1; // non-cached
                    break;
            }
        }
        return [
            'Cached' => $result['cached'],
            'Non-Cached' => $result['non_cached'],
        ];
    }

    public function getSlowestAverageResponseTime($domain, $month)
    {
        $result = [];
        $logs = $this->getLogs($domain, $month);
        foreach ($logs as $row) {
            if(!is_numeric($row['total'] ?? '')){
                continue; // skip invalid total time
            }
            $url = $row['path'].(!empty($row['query']) ? '?'.$row['query'] : '');
            if (!isset($result[$url])) {
                $result[$url] = [
                    'url' => $url,
                    'count' => 0,
                    'total_time' => 0,
                ];
            }
            $result[$url]['count']++;
            $result[$url]['total_time'] += $row['total'];
        }
        foreach ($result as $url => &$data) {
            if ($data['count'] > 0) {
                $data['average_time'] = floor($data['total_time'] / $data['count']);
            } else {
                $data['average_time'] = 0;
            }
        }
        usort($result, function ($a, $b) {
            return $b['average_time'] <=> $a['average_time'];
        });
        return array_slice($result, 0, 10); // return top 10 slowest average response times
    }

    public function getSlowestResponseTime($domain, $month)
    {
        $result = [];
        $logs = $this->getLogs($domain, $month);
        foreach ($logs as $row) {
            if(!is_numeric($row['total'] ?? '')){
                continue; // skip invalid total time
            }
            $url = ($row['path'] ?? '').(!empty($row['query']) ? '?'.$row['query'] : '');
            if (!isset($result[$url])) {
                $result[$url] = [
                    'url' => $url,
                    'time' => $row['total'],
                ];
            }elseif ($result[$url]['time'] < $row['total']) {
                $result[$url]['time'] = $row['total']; // keep the slowest time
            }
        }
        usort($result, function ($a, $b) {
            return $b['time'] <=> $a['time'];
        });
        return array_slice($result, 0, 10); // return top 10 slowest
    }

    public const MAGENTO_QUERY_PARAMS = [];

    public function getUsedQueryParams($domain, $month)
    {
        $result = [];
        $logs = $this->getLogs($domain, $month);
        foreach ($logs as $row) {
            if(empty($row['query'])){
                continue;
            }
            $q = explode('&', $row['query']);
            foreach($q as $kAndV){
                $kv = explode('=', $kAndV);
                $k = $kv[0] ?? '';
                $v = $kv[1] ?? '';
                if(!isset($result[$k])){
                    $result[$k] = [
                        'count' => 0,
                        'magento' => in_array($k, self::MAGENTO_QUERY_PARAMS),
                        'query' => $k
                    ];
                }
                $result[$k]['count']++;
            }
        }
        usort($result, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        return $result;
    }
}