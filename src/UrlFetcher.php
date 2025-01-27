<?php
declare(strict_types=1);

namespace CacheWarmer;

class UrlFetcher {
    private array $internalUrls = [];
    private array $visitedUrls = [];
    private array $skippedUrls = [];
    private array $debugInfo = [];
    private string $baseUrl;
    private string $sitemapUrl;
    private bool $debugMode;

    public function __construct(string $baseUrl, string $sitemapUrl, bool $debugMode = false) {
        $this->baseUrl = $baseUrl;
        $this->sitemapUrl = $sitemapUrl;
        $this->debugMode = $debugMode;
    }

    public function getInternalUrls(): array {
        $this->internalUrls[] = $this->baseUrl;
        $this->fetchSitemapUrls();
        $qtyUrls = 0;

        while (!empty($this->internalUrls)) {
            $currentUrl = array_shift($this->internalUrls);

            if ($this->shouldSkipUrl($currentUrl)) {
                echo "Skipping file URL: $currentUrl\n";
                continue;
            }

            $normalizedUrl = parse_url($currentUrl, PHP_URL_PATH) . '?' . (parse_url($currentUrl, PHP_URL_QUERY) ?? '');
            if (in_array($normalizedUrl, $this->visitedUrls)) {
                $this->skippedUrls[] = $currentUrl;
                continue;
            }
            $this->visitedUrls[] = $normalizedUrl;

            echo "Loading URL #".$qtyUrls.": $currentUrl\n";
            $qtyUrls++;

            $this->fetchUrlContent($currentUrl);
        }

        return $this->internalUrls;
    }

    private function fetchSitemapUrls(): void {
        $sitemapContent = @file_get_contents($this->sitemapUrl);
        if ($sitemapContent) {
            $xml = simplexml_load_string($sitemapContent);
            foreach ($xml->url as $url) {
                $this->internalUrls[] = (string)$url->loc;
            }
        }
    }

    private function shouldSkipUrl(string $url): bool {
        return (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|svg|bmp|tiff|ico|pdf)$/i', $url);
    }

    private function fetchUrlContent(string $url): void {
        $startTime = microtime(true);

        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $html = @file_get_contents($url, false, $context);

        $endTime = microtime(true);
        $elapsedTime = round(($endTime - $startTime) * 1000, 2);

        $responseCode = isset($http_response_header[0]) ? $http_response_header[0] : 'No response';
        echo "Response: $responseCode | Time: {$elapsedTime}ms\n";

        if ($html === false) {
            return;
        }

        preg_match_all('/<a\s+href=["\']([^"\']+)["\']/i', $html, $matches);

        if ($this->debugMode) {
            $this->debugInfo[$url] = $matches[1];
        }

        foreach ($matches[1] as $foundUrl) {
            $foundUrl = html_entity_decode($foundUrl, ENT_QUOTES, 'UTF-8');
            $parsedUrl = parse_url($foundUrl);
            $parsedBaseUrl = parse_url($this->baseUrl);

            if (!isset($parsedUrl['host'])) {
                $foundUrl = rtrim($this->baseUrl, '/') . '/' . ltrim($foundUrl, '/');
            } elseif ($parsedUrl['host'] !== $parsedBaseUrl['host']) {
                continue;
            }

            if (!in_array($foundUrl, $this->internalUrls)) {
                $this->internalUrls[] = $foundUrl;
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
    die("Usage: php UrlFetcher.php <baseUrl> <sitemapUrl> [--debug]\n");
}

$startScriptTime = microtime(true);

$baseUrl = $argv[1];
$sitemapUrl = $argv[2];
$debugMode = isset($argv[3]) && $argv[3] === '--debug';

$urlFetcher = new UrlFetcher($baseUrl, $sitemapUrl, $debugMode);
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
