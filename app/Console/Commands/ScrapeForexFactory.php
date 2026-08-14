<?php

namespace App\Console\Commands;

use App\Services\ForexFactoryScrapper;
use App\Services\TelegramService;
use App\Services\XauusdFilterNews;
use Illuminate\Console\Command;

class ScrapeForexFactory extends Command
{
    protected $signature = 'forexfactory:scrape';

    protected $description = 'Scrape Forex Factory and detect XAUUSD relevant news';

    public function handle(
        ForexFactoryScrapper $scrapper,
        XauusdFilterNews $filter,
        TelegramService $telegram
    ): int {
        $this->info('Scraping Forex Factory...');

        try {
            $articles = $scrapper->scrape();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Found: ' . count($articles) . ' articles.');

        $relevant = [];

        foreach ($articles as $article) {
            $isRelevant = $filter->isRelevant($article);

            $this->line(
                ($isRelevant ? '✅' : '❌') .
                ' ' .
                ($article['title'] ?? 'Unknown')
            );

            if ($isRelevant) {
                $relevant[] = $article;
            }
        }

        $this->newLine();

        $this->info(
            'XAUUSD relevant: ' . count($relevant)
        );

        /*
         * TEMPORARILY we will NOT send everything to Telegram.
         *
         * First we want to inspect what the scraper and
         * filter are actually returning.
         */

        foreach ($relevant as $article) {
            $this->newLine();

            $this->line(
                'TITLE: ' . ($article['title'] ?? '')
            );

            $this->line(
                'SOURCE: ' . ($article['source'] ?? '')
            );

            $this->line(
                'TIME: ' . ($article['time'] ?? '')
            );

            $this->line(
                'URL: ' . ($article['url'] ?? '')
            );
        }

        return self::SUCCESS;
    }
}