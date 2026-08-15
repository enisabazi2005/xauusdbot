<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use App\Services\XauusdFilterNews;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ScrapeForexFactory extends Command
{
    protected $signature = 'forexfactory:scrape';

    protected $description = 'Fetch Forex Factory news, detect XAUUSD relevant news and send new alerts to Telegram';

    public function handle(
        XauusdFilterNews $filter,
        TelegramService $telegram
    ): int {
        $this->info('Fetching Forex Factory news...');

        /*
         * ---------------------------------------------------------
         * FETCH NEWS FROM FOREXFACTORY API
         * ---------------------------------------------------------
         */

        try {
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
                    'https://api.parse.bot/scraper/0d3aa2e2-80b6-42dc-986a-d7f0845f4deb/get_news_latest'
                );

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'ForexFactory API returned HTTP ' .
                    $response->status()
                );
            }

            $json = $response->json();

        } catch (\Throwable $e) {
            $this->error(
                'ForexFactory API error: ' . $e->getMessage()
            );

            return self::FAILURE;
        }

        /*
         * ---------------------------------------------------------
         * CHECK API RESPONSE
         * ---------------------------------------------------------
         */

        if (
            !isset($json['status']) ||
            $json['status'] !== 'success'
        ) {
            $this->error(
                'ForexFactory API returned an unsuccessful response.'
            );

            return self::FAILURE;
        }

        $stories = $json['data']['stories'] ?? [];

        $this->info(
            'Received: ' . count($stories) . ' stories.'
        );

        if (empty($stories)) {
            $this->warn('No ForexFactory news found.');

            return self::SUCCESS;
        }

        /*
         * ---------------------------------------------------------
         * NORMALIZE STORIES
         * ---------------------------------------------------------
         */

        $articles = [];

        foreach ($stories as $story) {
            $url = trim($story['url'] ?? '');
            $headline = trim($story['headline'] ?? '');

            if (!$url || !$headline) {
                continue;
            }

            $articles[] = [
                'title' => $headline,
                'url' => $url,
                'impact' => $story['impact'] ?? 'low',
                'preview' => $story['preview'] ?? '',
                'source' => 'Forex Factory',
                'time' => now()->format('Y-m-d H:i:s'),
            ];
        }

        /*
         * ---------------------------------------------------------
         * REMOVE DUPLICATE STORIES
         * ---------------------------------------------------------
         */

        $articles = collect($articles)
            ->unique('url')
            ->values()
            ->all();

        $this->info(
            'Unique stories: ' . count($articles)
        );

        if (empty($articles)) {
            $this->warn('No valid articles after normalization.');

            return self::SUCCESS;
        }

        /*
         * ---------------------------------------------------------
         * XAUUSD RELEVANCE FILTER
         * ---------------------------------------------------------
         */

        $relevant = [];

        foreach ($articles as $article) {
            $isRelevant = $filter->isRelevant($article);

            $this->line(
                ($isRelevant ? '✅' : '❌') .
                ' ' .
                $article['title']
            );

            if ($isRelevant) {
                $relevant[] = $article;
            }
        }

        /*
         * ---------------------------------------------------------
         * SUMMARY
         * ---------------------------------------------------------
         */

        $this->newLine();

        $this->info(
            'XAUUSD relevant: ' . count($relevant)
        );

        if (empty($relevant)) {
            $this->info(
                'No XAUUSD relevant news found.'
            );

            return self::SUCCESS;
        }

        /*
         * ---------------------------------------------------------
         * SEND RELEVANT NEWS TO TELEGRAM
         * ---------------------------------------------------------
         */

        $this->newLine();
        $this->info('Sending relevant news to Telegram...');

        foreach ($relevant as $article) {
            try {
                $sent = $telegram->sendNews($article);

                if ($sent) {
                    $this->info(
                        '📨 Sent: ' . $article['title']
                    );
                } else {
                    $this->error(
                        '❌ Telegram failed: ' .
                        $article['title']
                    );
                }

            } catch (\Throwable $e) {
                $this->error(
                    'Telegram error: ' . $e->getMessage()
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * DONE
         * ---------------------------------------------------------
         */

        $this->newLine();
        $this->info('ForexFactory scrape completed.');

        return self::SUCCESS;
    }
}
