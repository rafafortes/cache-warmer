<?php
declare(strict_types=1);

namespace CacheWarmer;

class UrlFetcher {
    private array $internalUrls = [];
    private array $visitedUrls = [];
    private array $skippedUrls = [];
    private array $debugInfo = [];
    private array $blacklistPatterns = [];
    private string $baseUrl;
    private string $sitemapUrl;
    private bool $debugMode;
    private int $maxThreads;
    private int $qtyUrls = 0;

    public function __construct(string $baseUrl, string $sitemapUrl, bool $debugMode = false, int $maxThreads = 1) {
        $this->baseUrl = $baseUrl;
        $this->sitemapUrl = $sitemapUrl;
        $this->debugMode = $debugMode;
        $this->maxThreads = $maxThreads;
        $this->loadBlacklist();
        $this->loadInitialUrls();
    }

    private function loadBlacklist(): void {
        $blacklistFile = __DIR__ . '/blacklist';
        if (file_exists($blacklistFile)) {
            $this->blacklistPatterns = array_filter(array_map('trim', file($blacklistFile)));
            echo "Blacklist loaded with " . count($this->blacklistPatterns) . " patterns.\n";
        } else {
            echo "Blacklist file not found, proceeding without it.\n";
        }
    }

    private function loadInitialUrls(): void {
        $urlsFile = __DIR__ . '/urls';
        if (file_exists($urlsFile)) {
            $urls = array_filter(array_map('trim', file($urlsFile)));
            $this->internalUrls = array_merge([$this->baseUrl], $urls);
            echo "Loaded " . count($urls) . " additional URLs from 'urls' file.\n";
        } else {
            echo "The 'urls' file was not found. Proceeding with only the base URL.\n";
            $this->internalUrls[] = $this->baseUrl;
        }
    }

    public function getInternalUrls(): array {
        $this->fetchSitemapUrls();

        while (!empty($this->internalUrls)) {
            $batch = array_splice($this->internalUrls, 0, $this->maxThreads);
            $this->fetchMultipleUrls($batch);
        }

        return $this->visitedUrls;
    }

    private function fetchSitemapUrls(): void {
        $sitemapContent = @file_get_contents($this->sitemapUrl);
        if ($sitemapContent) {
            $xml = simplexml_load_string($sitemapContent);
            foreach ($xml->url as $url) {
                $this->addToQueue((string)$url->loc);
            }
        }
    }

    private function shouldSkipUrl(string $url): bool {
        return (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|svg|bmp|tiff|ico|pdf)$/i', $url);
    }

    private function isBlacklisted(string $url): bool {
        foreach ($this->blacklistPatterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    private function fetchMultipleUrls(array $urls): void {
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $startTimes = [];

        foreach ($urls as $url) {
            if ($this->shouldSkipUrl($url) || $this->isBlacklisted($url) || in_array($url, $this->visitedUrls)) {
                $this->skippedUrls[] = $url;
                echo "Skipping URL: $url\n";
                continue;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$url] = $ch;
            $startTimes[$url] = microtime(true);
        }

        do {
            $status = curl_multi_exec($multiHandle, $active);
            curl_multi_select($multiHandle);
        } while ($active && $status == CURLM_OK);

        foreach ($curlHandles as $url => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $endTime = microtime(true);
            $elapsedTime = round(($endTime - $startTimes[$url]) * 1000, 2);

            echo "Loading URL #{$this->qtyUrls}: $url | Response Code: $httpCode | Time: {$elapsedTime}ms\n";
            $this->qtyUrls++;

            if ($response && $httpCode === 200) {
                $this->visitedUrls[] = $url;
                $this->extractUrlsFromContent($url, $response);
            } else {
                $this->skippedUrls[] = $url;
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);
    }

    private function extractUrlsFromContent(string $sourceUrl, string $html): void {
        preg_match_all('/<a\s+href=["\']([^"\']+)["\']/i', $html, $matches);

        if ($this->debugMode) {
            $this->debugInfo[$sourceUrl] = $matches[1];
        }

        foreach ($matches[1] as $foundUrl) {
            $foundUrl = html_entity_decode($foundUrl, ENT_QUOTES, 'UTF-8');
            $this->addToQueue($foundUrl);
        }
    }

    private function addToQueue(string $url): void {
        $parsedBaseUrl = parse_url($this->baseUrl);
        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['host']) || $parsedUrl['host'] === $parsedBaseUrl['host']) {
            if (!in_array($url, $this->visitedUrls) && !in_array($url, $this->internalUrls) && !$this->isBlacklisted($url)) {
                $this->internalUrls[] = $url;
            }
        }
    }

    public function getSkippedUrls(): array {
        return $this->skippedUrls;
    }

    public function getDebugInfo(): array {
        return $this->debugInfo;
    }
}

if ($argc < 3) {
    die("Usage: php UrlFetcher.php <baseUrl> <sitemapUrl> [--debug] [<threads>]\n");
}

$startScriptTime = microtime(true);

$baseUrl = $argv[1];
$sitemapUrl = $argv[2];
$debugMode = in_array('--debug', $argv);
$threads = $argc > 4 ? (int)$argv[4] : 1;

$urlFetcher = new UrlFetcher($baseUrl, $sitemapUrl, $debugMode, $threads);
$urls = $urlFetcher->getInternalUrls();

// Display found URLs
print_r($urls);

echo "Total URLs loaded: " . count($urls) . "\n";

// Display skipped URLs
$skippedUrls = $urlFetcher->getSkippedUrls();
echo "Skipped duplicate URLs: " . count($skippedUrls) . "\n";
print_r($skippedUrls);

if ($debugMode) {
    echo "\nDebug Information:\n";
    print_r($urlFetcher->getDebugInfo());
}

$endScriptTime = microtime(true);
$totalExecutionTime = $endScriptTime - $startScriptTime;
$minutes = floor($totalExecutionTime / 60);
$seconds = round($totalExecutionTime % 60, 2);
echo "Total execution time: {$minutes}m {$seconds}s\n";
