<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PingSearchEnginesCommand extends Command
{
    protected $signature = 'app:ping-search-engines';

    protected $description = 'Notify search engines about the sitemap';

    public function handle(): int
    {
        /** @var string $configUrl */
        $configUrl = config('app.url', '');
        $appUrl = rtrim($configUrl, '/');
        $sitemapUrl = $appUrl.'/sitemap.xml';

        $this->info('Sitemap URL: '.$sitemapUrl);
        $this->newLine();

        $this->pingBing($sitemapUrl);
        $this->showGoogleInstructions($sitemapUrl);

        return self::SUCCESS;
    }

    private function pingBing(string $sitemapUrl): void
    {
        $this->info('Pinging Bing...');

        try {
            $response = Http::get('https://www.bing.com/ping', [
                'sitemap' => $sitemapUrl,
            ]);

            if ($response->successful()) {
                $this->info('  Bing pinged successfully.');
            } else {
                $this->warn(sprintf('  Bing returned HTTP %d.', $response->status()));
            }
        } catch (\Exception $exception) {
            $this->error('  Bing ping failed: '.$exception->getMessage());
        }

        $this->newLine();
    }

    private function showGoogleInstructions(string $sitemapUrl): void
    {
        $this->info('Google Search Console (manual submission required):');
        $this->line('  1. Go to https://search.google.com/search-console');
        $this->line('  2. Select property: '.$sitemapUrl);
        $this->line('  3. Navigate to Sitemaps in the left menu');
        $this->line('  4. Enter: '.$sitemapUrl);
        $this->line('  5. Click Submit');
        $this->newLine();
        $this->info('Note: Google deprecated the ping endpoint in 2023.');
        $this->info('robots.txt already references the sitemap for automatic discovery.');
    }
}
