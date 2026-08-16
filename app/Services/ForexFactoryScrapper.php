<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class ForexFactoryScrapper
{
    protected string $url;

    protected string $apiUrl =
        'https://api.parse.bot/scraper/0d3aa2e2-80b6-42dc-986a-d7f0845f4deb';

    public function __construct()
    {
        $this->url = config(
            'services.forexfactory.url',
            'https://www.forexfactory.com/news'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LATEST NEWS
    |--------------------------------------------------------------------------
    */

    public function getLatestNews(): array
    {
        $apiKey = config('services.forexfactory.api_key');

        if (!$apiKey) {
            throw new \RuntimeException(
                'FOREXFACTORY_API is not configured.'
            );
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->get(
                $this->apiUrl . '/get_news_latest'
            );

        if (!$response->successful()) {
            throw new \RuntimeException(
                'ForexFactory API returned HTTP ' .
                $response->status()
            );
        }

        $json = $response->json();

        \Log::info('ForexFactory latest news response', $json);

        if (
            !isset($json['status']) ||
            $json['status'] !== 'success'
        ) {
            throw new \RuntimeException(
                'ForexFactory API returned an unsuccessful response.'
            );
        }

        return $json['data']['stories'] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | CALENDAR FILTERED
    |--------------------------------------------------------------------------
    */

    public function getCalendarFiltered(
        ?string $week = null,
        string $currencies = 'USD',
        string $impactLevels = 'high,medium'
    ): array {
        $apiKey = config('services.forexfactory.api_key');
    
        if (!$apiKey) {
            throw new \RuntimeException(
                'FOREXFACTORY_API is not configured.'
            );
        }
    
        $params = [
            'currencies' => $currencies,
            'impact_levels' => $impactLevels,
        ];
    
        if ($week) {
            $params['week'] = $week;
        }
    
        $response = Http::timeout(30)
            ->withHeaders([
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->get(
                $this->apiUrl . '/get_calendar_filtered',
                $params
            );
    
        if (!$response->successful()) {
            throw new \RuntimeException(
                'ForexFactory Calendar API returned HTTP ' .
                $response->status()
            );
        }
    
        $json = $response->json();
    
        \Log::info(
            'ForexFactory calendar response',
            $json
        );
    
        if (
            !isset($json['status']) ||
            $json['status'] !== 'success'
        ) {
            throw new \RuntimeException(
                'ForexFactory Calendar API returned an unsuccessful response.'
            );
        }
    
        /*
         * IMPORTANT:
         *
         * Return the actual events array.
         *
         * The command expects:
         *
         * foreach ($events as $event)
         *
         * where every $event is an array.
         */
    
        return $json['data']['events'] ?? [];
    }
    

    /*
    |--------------------------------------------------------------------------
    | MARKET OVERVIEW
    |--------------------------------------------------------------------------
    */

    public function getMarketOverview(): array
    {
        $apiKey = config('services.forexfactory.api_key');

        if (!$apiKey) {
            throw new \RuntimeException(
                'FOREXFACTORY_API is not configured.'
            );
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->get(
                $this->apiUrl . '/get_market_overview'
            );

        if (!$response->successful()) {
            throw new \RuntimeException(
                'ForexFactory Market Overview API returned HTTP ' .
                $response->status()
            );
        }

        $json = $response->json();

        \Log::info('ForexFactory market overview response', $json);

        if (
            !isset($json['status']) ||
            $json['status'] !== 'success'
        ) {
            throw new \RuntimeException(
                'ForexFactory Market Overview API returned an unsuccessful response.'
            );
        }

        return $json['data'] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | OLD HTML SCRAPER
    |--------------------------------------------------------------------------
    |
    | Keep this. We are NOT deleting your old scraper functionality.
    |
    */

    public function scrape(): array
    {
        $this->url = config(
            'services.forexfactory.url',
            'https://www.forexfactory.com/news'
        );

        \Log::info('ForexFactory: Starting request', [
            'url' => $this->url,
        ]);

        try {
            $response = Http::withHeaders([
                'User-Agent' =>
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
                    'AppleWebKit/537.36 Chrome/139.0.0.0 Safari/537.36',

                'Accept' =>
                    'text/html,application/xhtml+xml,application/xml;' .
                    'q=0.9,image/avif,image/webp,*/*;q=0.8',

                'Accept-Language' =>
                    'en-US,en;q=0.9',

                'Referer' =>
                    'https://www.google.com/',

                'Cache-Control' =>
                    'no-cache',
            ])
            ->timeout(20)
            ->connectTimeout(10)
            ->withOptions([
                'allow_redirects' => true,
                'http_errors' => false,
            ])
            ->get($this->url);

            \Log::info('ForexFactory: Response received', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'content_type' => $response->header('Content-Type'),
                'content_length' => strlen($response->body()),
                'final_url' => $response->effectiveUri(),
            ]);

            if (!$response->successful()) {
                throw new \Exception(
                    'Forex Factory request failed: HTTP ' .
                    $response->status()
                );
            }

            return $this->parseHtml(
                $response->body()
            );

        } catch (\Throwable $e) {

            \Log::error('ForexFactory: Exception', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    protected function parseHtml(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $articles = [];

        $links = $xpath->query('//a[@href]');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            if (
                !str_contains($href, '/news/') &&
                !str_contains($href, '/news?')
            ) {
                continue;
            }

            $title = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $link->textContent
                )
            );

            if ($title === '') {
                continue;
            }

            $url = $this->normalizeUrl($href);

            $container = $this->findContainer($link);

            $containerText = $container
                ? trim(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        $container->textContent
                    )
                )
                : '';

            $source = $this->extractSource(
                $containerText
            );

            $time = $this->extractTime(
                $containerText
            );

            $articles[] = [
                'title' => $title,
                'source' => $source,
                'time' => $time,
                'url' => $url,
                'raw' => $containerText,
            ];
        }

        return $this->removeDuplicates(
            $articles
        );
    }

    protected function findContainer(
        \DOMNode $node
    ): ?\DOMNode {
        $current = $node;

        for (
            $i = 0;
            $i < 8 && $current;
            $i++
        ) {
            $current = $current->parentNode;

            if (!$current) {
                break;
            }

            $text = trim(
                $current->textContent
            );

            if (
                str_contains($text, 'ago') &&
                (
                    str_contains($text, 'From ') ||
                    str_contains($text, 'Image From ')
                )
            ) {
                return $current;
            }
        }

        return null;
    }

    protected function extractSource(
        string $text
    ): ?string {
        if (
            preg_match(
                '/(?:From|Image From)\s+([^\|]+?)\s+\|\s+/i',
                $text,
                $matches
            )
        ) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function extractTime(
        string $text
    ): ?string {
        if (
            preg_match(
                '/(\d+\s+(?:sec|min|hour|hr|day|week)s?\s+ago)/i',
                $text,
                $matches
            )
        ) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function normalizeUrl(
        string $url
    ): string {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return 'https://www.forexfactory.com/' .
            ltrim($url, '/');
    }

    protected function removeDuplicates(
        array $articles
    ): array {
        $unique = [];

        foreach ($articles as $article) {
            $key = md5(
                strtolower(
                    $article['url'] .
                    '|' .
                    $article['title']
                )
            );

            $unique[$key] = $article;
        }

        return array_values($unique);
    }
}