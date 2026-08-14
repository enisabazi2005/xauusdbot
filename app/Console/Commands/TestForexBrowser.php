<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TestForexBrowser extends Command
{
    protected $signature = 'forexfactory:test-browser';

    protected $description = 'Test Forex Factory using Puppeteer';

    public function handle(): int
    {
        $url = 'https://www.forexfactory.com/news';

        $this->info("Opening: {$url}");

        $node = 'C:\\Program Files\\nodejs\\node.exe';

        $script = <<<'JS'
const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({
        headless: false,

        executablePath:
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',

        userDataDir:
            'C:\\temp\\chrome-test',

        dumpio: true,

        args: [
            '--no-first-run',
            '--no-default-browser-check'
        ]
    });

    console.log('PUPPETEER LAUNCHED');

    const page = await browser.newPage();

    await page.goto(
        'https://www.forexfactory.com/news',
        {
            waitUntil: 'domcontentloaded',
            timeout: 120000
        }
    );

    console.log('TITLE:', await page.title());
    console.log('URL:', page.url());

    const html = await page.content();

    console.log('HTML_LENGTH:', html.length);

    console.log('HTML_START:');
    console.log(html.substring(0, 1000));

    await new Promise(resolve => setTimeout(resolve, 10000));

    await browser.close();
})();
JS;

        $process = new Process([
            $node,
            '-e',
            $script,
        ]);

        $process->setWorkingDirectory(base_path());

        $process->setTimeout(180);

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->error('Puppeteer process failed.');

            $this->error(
                $process->getErrorOutput()
            );

            return self::FAILURE;
        }

        $this->info('Puppeteer finished successfully.');

        return self::SUCCESS;
    }
}
