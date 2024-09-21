<?php 
/**
 * Luminova Framework
 * The Luminova Framework offers high-performance HMVC (Hierarchical Model-View-Controller) 
 * and MVC (Model-View-Controller) architectures designed for robust web applications. 
 * 
 * It combines the strengths of both architectures to enhance modularity, maintainability, 
 * and scalability, enabling developers to build efficient and dynamic web solutions.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link http://luminova.ng
 */
namespace Peterujah\BrokenLink;

use \DOMDocument;
use \RuntimeException;

class Scanner
{
     /**
     * Count of extracted URLs.
     * 
     * @var int $counts
     */
    private static int $counts = 0;

    /**
     * Maximum number of URLs to scan.
     * 
     * @var int $maxScan
     */
    private int $maxScan = 0;

    /**
     * Path to store the scan result files.
     * 
     * @var string|null $filePath
     */
    private string|null $filePath = null;

    /**
     * Maximum memory usage threshold (in bytes).
     * 
     * @var int $memoryThreshold
     */
    private static int $memoryThreshold = 0;

    /**
     * URL to start the scan.
     * 
     * @var string $url
     */
    private string $url = '';

    /**
     * Scan Hostname
     * 
     * @var string $host
     */
    private string $host = '';

    /**
     * Array of error messages encountered during the scan.
     * 
     * @var array $errors
     */
    private static array $errors = [];

    /**
     * List of broken URLs.
     * 
     * @var array $broken
     */
    private static array $broken = [];

    /**
     * List of extracted URLs.
     * 
     * @var array $urls
     */
    private static array $urls = [];

    /**
     * List of visited URLs.
     * 
     * @var array $visited
     */
    private static array $visited = [];

    /**
     * Scan is completed or not.
     * 
     * @var bool $completed
     */
    private static bool $completed = false;

    /**
     * Scan has started or not.
     * 
     * @var bool $started
     */
    private static bool $started = false;

    /**
     * The waiting start time.
     * 
     * @var float|null $startTime
     */
    private float|null $startTime = null;

    /**
     * Is cli mode.
     * 
     * @var bool $cli
     */
    private bool $cli = false;

    /**
     * Constructor to initialize the URL to start scanning.
     * 
     * @param string $url URL to start the scan (e.g, `https://luminova.ng/docs/`).
     * @param string $host The scan URL hostname (e.g, `luminova.ng`).
     * @param int $maxScan Maximum number of scans (default: 0).
     */
    public function __construct(string $url, string $host, int $maxScan = 0)
    {
        $this->url = $url;
        $this->host = 'https://' . $host;
        $this->maxScan = $maxScan;
    }

    /**
     * Check if scan is completed.
     * 
     * @return bool Return true if scan is completed, false otherwise.
     */
    public function isCompleted(): bool 
    {
        return self::$completed;
    }

    /**
     * Returns the list of broken URLs.
     * 
     * @return array List of broken URLs.
     */
    public function getBrokenLinks(): array 
    {
        return self::$broken;
    }

    /**
     * Returns the list of visited URLs.
     * 
     * @return array List of visited URLs.
     */
    public function getVisitedUrls(): array 
    {
        return self::$visited;
    }

    /**
     * Returns the error messages encountered during the scan.
     * 
     * @return array List of errors.
     */
    public function getErrors(): array 
    {
        return self::$errors;
    }

    /**
     * Returns the list of extracted URLs.
     * 
     * @return array List of URLs.
     */
    public function getUrls(): array 
    {
        return self::$urls;
    }

    /**
     * Set the file path to save scanned urls.
     * 
     * @param string $path File path to save scanned urls.
     * 
     * @return self Return instance of class.
     */
    public function setPath(string $path): self 
    {
        $this->filePath = $path;
        return $this;
    }

    /**
     * Set the to true if on cli to show scanning results.
     * 
     * @param bool $cli Weather is on cli.
     * 
     * @return self Return instance of class.
     */
    public function cli(bool $cli): self 
    {
        $this->cli = $cli;
        return $this;
    }

    /**
     * Starts the link scanning process.
     * 
     * @return bool Returns true if scan completes successfully, false otherwise.
     * @throws RuntimeException If an invalid URL is provided.
     */
    public function start(): bool  
    {
        if (!$this->url) {
            self::outputError('Invalid start url, the start url cannot be empty.');
            return false;
        }

        set_time_limit(300);
        $totalMemory = ini_get('memory_limit');

        if ($totalMemory === '-1') {
            $this->error('No memory limit is enforced', 'no_memory_assigned');
            return false;
        }

        self::$visited = [];
        self::$broken = [];
        self::$urls = [];
        self::$errors = [];
        self::$completed = false;

        self::$memoryThreshold = round(self::toBytes($totalMemory) * 0.7);

        $urls = self::scan($this->url);
        
        if ($urls === false || $urls === []) {
            self::$completed = true;
            self::$started = false;

            if($this->cli){
                echo "Failed unable to scan url: {$this->url} or no link was found.\n";
            }
            return false;
        }

        self::$completed = true;
        self::$started = false;

        if ($this->filePath !== null && !is_dir($this->filePath)) {
            mkdir($this->filePath, 0777, true);
        }

        if(self::$broken !== []){
            file_put_contents($this->filePath . '/broken-links.txt', implode("\n", self::$broken));
        }

        if(self::$urls !== []){
            file_put_contents($this->filePath . '/scanned-links.txt', implode("\n", array_values(self::$urls)));
        }

        if(self::$visited !== []){
            file_put_contents($this->filePath . '/visited-links.txt', implode("\n", array_keys(self::$visited)));
        }

        if(self::$errors !== []){
            file_put_contents($this->filePath . '/scan-errors.txt', json_encode(self::$errors));
        }

        if($this->cli){
            echo "Scan completed successfully!\nTotal number of scans (" . self::$counts . ")\n";
        }

        @gc_mem_caches();
        return true;
    }

    /**
     * Waits for a scan to complete or until a specified timeout is reached.
     *
     * @param int $timeout The maximum number of seconds to wait. If 0, it waits indefinitely till scan is completed.
     * @param callable|null $onComplete Callback to be called when the scan completes.
     * 
     * @throws RuntimeException if the timeout is exceeded before completion.
     */
    public function wait(int $timeout, ?callable $onComplete = null): void 
    {
        if(self::$started){
            self::outputError('Scan has started already you cannot call with method while scan is running.');
            return;
        }

        $this->start();
        $this->startTime = microtime(true);

        while (!self::$completed) {
            if ($this->waitForCompletion($timeout)) {
                break;
            }

            usleep(1000);
        }

        if (self::$completed && $onComplete !== null && is_callable($onComplete)) {
            $onComplete($this);
        }
    }

    /**
     * Check for scan completion.
     *
     * @param int $timeout The maximum number of seconds to wait. If 0.
     * 
     * @throws RuntimeException if the timeout is exceeded before completion.
     */
    private function waitForCompletion(int $timeout): bool 
    {
        if(self::$completed){
            self::$started = false;
            return true;
        }

        if ($timeout > 0 && (microtime(true) - $this->startTime) >= $timeout) {
            self::$completed = false;
            self::$started = false;
            self::outputError('Maximum wait timeout reached.');
            return true;
        }

        return false;
    }

    /**
     * Converts memory size strings (e.g., '128M') to bytes.
     * 
     * @param string $units Memory size string.
     * @return int Converted memory size in bytes.
     */
    private static function toBytes(string $units): int
    {
        $units = strtoupper(trim($units));
        $unit = substr($units, -1);
        $value = (int) substr($units, 0, -1);

        switch ($unit) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
            break;
            case 'B':
            default:
        }

        return $value;
    }

    /**
     * Converts a relative URL to an absolute URL using the base domain.
     * 
     * @param string $url The relative URL to convert.
     * @return string The absolute URL.
     */
    private function toUrl(string $url): string
    {
        if (parse_url($url, PHP_URL_SCHEME) !== null || $this->host === '') {
            return $url;
        }

        $mainDomain = rtrim($this->host, '/') . '/';
        $relativeUrl = preg_replace('#\./#', '', $url);

        while (strpos($relativeUrl, '../') !== false) {
            $mainDomain = preg_replace('#/[^/]+/$#', '/', $mainDomain);
            $relativeUrl = preg_replace('#^\.\./#', '', $relativeUrl);
        }

        return $mainDomain . ltrim($relativeUrl, '/');
    }

    /**
     * Determines if a URL is valid and not a fragment.
     * 
     * @param string $href The URL to validate.
     * @return bool Returns true if the URL is valid, false otherwise.
     */
    private static function isAcceptable(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return false;
        }

        return preg_match('/^[a-zA-Z][a-zA-Z\d+\-.]*:\/\//', $href) !== false;
    }

    /**
     * Fetches a URL's content and status code.
     * 
     * @param string $url The URL to fetch.
     * @return array|bool Returns an array with the document and status or false if an error occurs.
     */
    private function browser(string $url): array|bool
    {
        $url = $this->toUrl($url);
        $ch = curl_init($url);

        if (!$ch) {
            self::outputError('[Error] Failed to initialize cURL connection.');
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false
        ]);

        $document = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCode = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        self::$visited[$url] = true;

        if ($document === false || $errorCode !== 0) {
            $this->error('[Error] ' . ($errorCode ? $error : 'Empty response.'), $url);
            return false;
        }

        if ($statusCode === 404) {
            if($this->cli){
                echo "[Broken] url: $url.\n";
                return false;
            }
            return false;
        }

        return [
            'document' => $document,
            'status' => $statusCode
        ];
    }

    /**
     * Logs errors encountered during the scanning process.
     * 
     * @param string $message Error message.
     * @param string|null $context Optional context for the error.
     * 
     * @return void
     */
    private function error(string $message, string|null $context = null): void 
    {
        self::$errors['messages'][] = [
            'message' => $message,
            'context' => $context
        ];

        if($this->cli){
            echo $message . "\n";
        }
    }

    /**
     * Output error message or throw an exception in none cli.
     * 
     * @param string $message Error message.
     * 
     * @return void
     */
    private function outputError(string $message): void 
    {
        if($this->cli){
            echo $message . "\n";
            return;
        }

        throw new RuntimeException($message);
    }

    /**
     * Scans a URL and extracts its links.
     * 
     * @param string $url The URL to scan.
     * @return array|bool Returns an array of URLs or false if an error occurs.
     */
    private function scan(string $url): array|bool
    {
        self::$started = true;
        if ($this->maxScan !== 0 && self::$counts >= $this->maxScan) {
            $this->error('Maximum scan limit exceeded.', 'memory_limit');
            return self::$urls;
        }

        if (memory_get_usage() >= self::$memoryThreshold) {
            $this->error('Memory usage exceeded limit. Stopping extraction.', 'memory_limit');
            return self::$urls;
        }

        if($this->cli){
            echo "Starting scan for broken links on: $url\n";
        }

        $restrict = ($this->host === 'https://') ? $this->url : $this->host;

        if(!str_starts_with($url, $restrict)){
            if($this->cli){
                echo "Skipping url: $url\n";
            }
            return self::$urls;
        }
        
        $deepScans = [];
        $html = self::browser($url);
    
        if ($html === false || $html['status'] === 404) {
            self::$broken[] = $url;
            return self::$urls;
        }
    
        $dom = new DOMDocument();
        @$dom->loadHTML($html['document']);

        /**
         * @var DOMNodeList
        */
        $links = $dom->getElementsByTagName('a');
        
        foreach ($links as $link) {
            if (memory_get_usage() >= self::$memoryThreshold) {
                $this->error('Memory usage exceeded limit. Stopping extraction.', 'memory_limit');
                return self::$urls;
            }
    
            if ($this->maxScan !== 0 && self::$counts >= $this->maxScan) {
                $this->error('Maximin scan limit exceeded.', 'memory_limit');
                return self::$urls;
            }

            if (!$link->hasAttribute('href')) {
                continue;
            }
    
            $href = $link->getAttribute('href');

            if (!self::isAcceptable($href)) {
                continue;
            }

            $href = rtrim(self::toUrl($href), '/');

            if(!str_starts_with($url, $restrict) || !filter_var($href, FILTER_VALIDATE_URL)){
                if($this->cli){
                    echo "Skipping URL: $href\n";
                }
                continue;
            }

            if (!array_key_exists($href, self::$urls)) {
                self::$counts++;
                $deepScans[$href] = $href;
                self::$urls[$href] = $href;
                echo "Extracted URL: $href\n";
            }
        }

        return $this->deepScan($deepScans);
    }

    /**
     * Performs a deep scan on a list of URLs, extracting links recursively until the maximum scan limit or memory threshold is reached.
     * 
     * @param array $urls List of URLs to scan.
     * @return array The cumulative list of all extracted URLs.
     */
    private function deepScan(array $urls): array
    {
        $subUrls = [];
        foreach ($urls as $link) {
            if ($this->maxScan !== 0 && self::$counts >= $this->maxScan) {
                $this->error('Maximin scan limit exceeded.', 'memory_limit');
                return self::$urls;
            }

            if(!isset(self::$visited[$link])){
                $subUrls = self::scan($link);

                if($subUrls !== false){
                    self::$urls = array_merge(self::$urls, $subUrls);
                }
            }
        }

        return self::$urls;
    }
}
