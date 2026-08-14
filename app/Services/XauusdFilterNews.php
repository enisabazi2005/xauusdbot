<?php

namespace App\Services;

class XauusdFilterNews
{
    /**
     * Direct Gold/XAUUSD keywords.
     */
    protected array $goldKeywords = [
        'gold',
        'xau',
        'xauusd',
        'xau/usd',
        'bullion',
        'precious metal',
        'precious metals',
        'gold price',
        'gold prices',
        'gold futures',
        'gold demand',
        'gold supply',
        'gold buying',
        'gold holdings',
    ];

    /**
     * US / USD macro keywords that can materially
     * affect XAUUSD.
     */
    protected array $usdKeywords = [
        'usd',
        'u.s. dollar',
        'us dollar',
        'dollar',
        'federal reserve',
        'fed',
        'fomc',
        'powell',
        'fed chair',
        'interest rate',
        'interest rates',
        'rate cut',
        'rate hike',
        'rate cuts',
        'rate hikes',
        'us inflation',
        'u.s. inflation',
        'cpi',
        'core cpi',
        'pce',
        'core pce',
        'ppi',
        'nfp',
        'nonfarm payroll',
        'non-farm payroll',
        'payrolls',
        'unemployment',
        'us jobs',
        'u.s. jobs',
        'us gdp',
        'u.s. gdp',
        'retail sales',
        'consumer confidence',
        'consumer sentiment',
        'ism',
        'treasury yield',
        'treasury yields',
        'bond yield',
        'bond yields',
        '10-year yield',
        '10 year yield',
        '2-year yield',
        '2 year yield',
    ];

    /**
     * Geopolitical keywords.
     *
     * These matter because they can directly move
     * Gold as a safe-haven asset.
     */
    protected array $geopoliticalKeywords = [
        'war',
        'warfare',
        'missile',
        'missiles',
        'military strike',
        'military strikes',
        'airstrike',
        'airstrikes',
        'iran',
        'israel',
        'gaza',
        'ukraine',
        'russia',
        'hormuz',
        'strait of hormuz',
        'red sea',
        'sanctions',
        'tariff',
        'tariffs',
        'trade war',
        'ceasefire',
        'nuclear',
        'invasion',
        'attack',
        'attacks',
    ];

    /**
     * Oil keywords are NOT enough by themselves.
     *
     * Oil becomes relevant when combined with inflation,
     * US/Fed, geopolitical or gold context.
     */
    protected array $oilKeywords = [
        'oil',
        'crude',
        'brent',
        'wti',
        'opec',
        'opec+',
        'oil prices',
        'oil price',
        'oil supply',
        'oil production',
        'oil exports',
    ];

    /**
     * Currencies/topics we explicitly don't want
     * unless the article also contains strong XAUUSD context.
     */
    protected array $irrelevantCurrencyKeywords = [
        'aud',
        'australian dollar',
        'australia',
        'rba',
        'cad',
        'canadian dollar',
        'bank of canada',
        'boc',
        'nzd',
        'new zealand dollar',
        'rbnz',
        'gbp',
        'british pound',
        'bank of england',
        'boe',
        'eur',
        'euro',
        'ecb',
        'jpy',
        'japanese yen',
        'boj',
        'chf',
        'swiss franc',
        'snb',
    ];

    public function isRelevant(array $article): bool
    {
        $text = strtolower(
            ($article['title'] ?? '') . ' ' .
            ($article['raw'] ?? '') . ' ' .
            ($article['source'] ?? '')
        );

        /*
         * 1. Direct Gold/XAUUSD article = YES.
         */
        if ($this->containsAny($text, $this->goldKeywords)) {
            return true;
        }

        /*
         * 2. USD macro article = YES.
         *
         * But don't allow random mentions of "dollar"
         * to trigger everything.
         */
        if ($this->isStrongUsdNews($text)) {
            return true;
        }

        /*
         * 3. Geopolitical event = potentially relevant.
         *
         * We only accept major geopolitical events when
         * there is US / Middle East / global-market context.
         */
        if ($this->isRelevantGeopoliticalNews($text)) {
            return true;
        }

        /*
         * 4. Oil is only relevant when combined with
         * inflation/Fed/US/gold/geopolitical context.
         */
        if ($this->isRelevantOilNews($text)) {
            return true;
        }

        return false;
    }

    protected function isStrongUsdNews(string $text): bool
    {
        $strongUsdTerms = [
            'federal reserve',
            'fomc',
            'powell',
            'fed chair',
            'us inflation',
            'u.s. inflation',
            'core cpi',
            'cpi',
            'pce',
            'core pce',
            'ppi',
            'nfp',
            'nonfarm payroll',
            'non-farm payroll',
            'us jobs',
            'u.s. jobs',
            'unemployment',
            'us gdp',
            'u.s. gdp',
            'retail sales',
            'consumer confidence',
            'consumer sentiment',
            'treasury yield',
            'treasury yields',
            '10-year yield',
            '10 year yield',
            '2-year yield',
            '2 year yield',
            'interest rate',
            'interest rates',
            'rate cut',
            'rate hike',
        ];

        return $this->containsAny($text, $strongUsdTerms);
    }

    protected function isRelevantGeopoliticalNews(string $text): bool
    {
        if (! $this->containsAny($text, $this->geopoliticalKeywords)) {
            return false;
        }

        /*
         * Major geopolitical events can affect Gold.
         * We particularly care about US / Middle East / Russia.
         */
        $contextKeywords = [
            'us',
            'u.s.',
            'united states',
            'america',
            'american',
            'iran',
            'israel',
            'russia',
            'ukraine',
            'middle east',
            'trump',
            'pentagon',
            'white house',
            'military',
            'hormuz',
            'red sea',
            'nato',
        ];

        return $this->containsAny($text, $contextKeywords);
    }

    protected function isRelevantOilNews(string $text): bool
    {
        if (! $this->containsAny($text, $this->oilKeywords)) {
            return false;
        }

        /*
         * Oil by itself isn't enough.
         */
        $contextKeywords = [
            'inflation',
            'cpi',
            'pce',
            'fed',
            'federal reserve',
            'interest rate',
            'us',
            'u.s.',
            'dollar',
            'gold',
            'iran',
            'israel',
            'hormuz',
            'war',
            'sanctions',
            'opec',
        ];

        return $this->containsAny($text, $contextKeywords);
    }

    protected function containsAny(
        string $text,
        array $keywords
    ): bool {
        foreach ($keywords as $keyword) {
            if (str_contains($text, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}