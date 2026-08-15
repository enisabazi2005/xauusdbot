<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestForexBrowser extends Command
{
    protected $signature = 'forexfactory:test-browser';

    protected $description = 'Test ForexFactory API';

    public function handle(): int
    {
        $this->info('Testing ForexFactory API...');

        $apiKey = config('services.forexfactory.api_key');

        if (!$apiKey) {
            $this->error('FOREXFACTORY_API is missing from .env');

            return self::FAILURE;
        }

        $endpoint =
            'https://api.parse.bot/scraper/0d3aa2e2-80b6-42dc-986a-d7f0845f4deb/get_news_latest';

        $this->info("Endpoint: {$endpoint}");

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                ])
                ->get($endpoint);

            $this->info(
                'HTTP Status: ' . $response->status()
            );

            if (!$response->successful()) {
                $this->error('API request failed.');

                $this->line(
                    $response->body()
                );

                return self::FAILURE;
            }

            $json = $response->json();

            $this->info('API request successful!');

            $this->newLine();

            $this->line(
                json_encode(
                    $json,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                )
            );

            return self::SUCCESS;

        } catch (\Throwable $e) {

            $this->error(
                'API error: ' . $e->getMessage()
            );

            return self::FAILURE;
        }
    }
}
