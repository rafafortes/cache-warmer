<?php
declare(strict_types=1);

namespace CacheWarmer;

class UrlFetcher {
    private array $internalUrls = [];
    private array $visitedUrls = [];
    private string $baseUrl;
    private string $sitemapUrl;

    public function __construct(string $baseUrl, string $sitemapUrl) {
        $this->baseUrl = $baseUrl;
        $this->sitemapUrl = $sitemapUrl;
    }

    public function getInternalUrls(): array {
        $this->fetchSitemapUrls();
        $queue = array_merge([$this->baseUrl], $this->internalUrls);

        while (!empty($queue)) {
            $currentUrl = array_shift($queue);

            if ($this->shouldSkipUrl($currentUrl)) {
                echo "Skipping file URL: $currentUrl\n";
                continue;
            }

            $normalizedUrl = parse_url($currentUrl, PHP_URL_PATH) . '?' . (parse_url($currentUrl, PHP_URL_QUERY) ?? '');
            if (in_array($normalizedUrl, $this->visitedUrls)) {
                continue;
            }
            $this->visitedUrls[] = $normalizedUrl;

            echo "Loading: $currentUrl\n";

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

        foreach ($matches[1] as $url) {
            $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
            $parsedUrl = parse_url($url);
            $parsedBaseUrl = parse_url($this->baseUrl);

            if (!isset($parsedUrl['host'])) {
                $url = rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
            } elseif ($parsedUrl['host'] !== $parsedBaseUrl['host']) {
                continue;
            }

            if (!in_array($url, $this->internalUrls)) {
                $this->internalUrls[] = $url;
            }
        }
    }
}

if ($argc < 3) {
    die("Usage: php UrlFetcher.php <baseUrl> <sitemapUrl>\n");
}

$startScriptTime = microtime(true);

$baseUrl = $argv[1];
$sitemapUrl = $argv[2];

$urlFetcher = new UrlFetcher($baseUrl, $sitemapUrl);
$urls = $urlFetcher->getInternalUrls();

// Display found URLs
print_r($urls);

echo "Total URLs loaded: " . count($urls) . "\n";

$endScriptTime = microtime(true);
$totalExecutionTime = $endScriptTime - $startScriptTime;
$minutes = floor($totalExecutionTime / 60);
$seconds = round($totalExecutionTime % 60, 2);
echo "Total execution time: {$minutes}m {$seconds}s\n";
