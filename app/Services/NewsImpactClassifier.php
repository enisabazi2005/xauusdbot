<?php

namespace App\Services;

class NewsImpactClassifier
{
    public function classify(array $article): string
    {
        $title = strtolower(
            trim($article['title'] ?? '')
        );

        $preview = strtolower(
            trim($article['preview'] ?? '')
        );

        $text = $title . ' ' . $preview;

        /*
         * ---------------------------------------------------------
         * HIGH IMPACT
         * ---------------------------------------------------------
         */

        $highKeywords = [
            'fomc',
            'federal reserve',
            'fed decision',
            'fed rate',
            'interest rate decision',
            'rate decision',
            'powell',
            'nfp',
            'nonfarm payroll',
            'non-farm payroll',
            'payrolls',
            'cpi',
            'core cpi',
            'pce',
            'core pce',
            'ppi',
            'us gdp',
            'u.s. gdp',
            'unemployment rate',
            'retail sales',
            'gold',
            'xauusd',
            'xau/usd',
            'iran',
            'israel',
            'hormuz',
            'strait of hormuz',
            'war',
            'military strike',
            'airstrike',
            'missile',
            'nuclear',
            'invasion',
        ];

        if ($this->containsAny($text, $highKeywords)) {
            return 'high';
        }

        /*
         * ---------------------------------------------------------
         * MEDIUM IMPACT
         * ---------------------------------------------------------
         */

        $mediumKeywords = [
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
            'dollar',
            'usd',
            'tariff',
            'tariffs',
            'trade war',
            'oil',
            'crude',
            'brent',
            'wti',
            'opec',
            'sanctions',
            'ceasefire',
        ];

        if ($this->containsAny($text, $mediumKeywords)) {
            return 'medium';
        }

        return 'low';
    }

    protected function containsAny(
        string $text,
        array $keywords
    ): bool {
        foreach ($keywords as $keyword) {
            if (str_contains(
                $text,
                strtolower($keyword)
            )) {
                return true;
            }
        }

        return false;
    }
}