<?php

declare(strict_types=1);

namespace Vnuswilliams\Subscription\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class InstallSubscriptionPackage extends Command
{
    protected $signature = 'subscription:install';

    protected $description = 'Publishes the subscription configuration and migrations, replacing existing package files.';

    public function handle(): int
    {
        $this->replaceConfig();
        $this->replaceMigrations();

        $this->info('Subscription configuration and migrations have been regenerated.');

        return self::SUCCESS;
    }

    private function replaceConfig(): void
    {
        $source = __DIR__ . '/../../../config/subscriptions.php';
        $destination = config_path('subscriptions.php');

        File::ensureDirectoryExists(dirname($destination));

        if (File::exists($destination)) {
            File::delete($destination);
            $this->line('Deleted existing config/subscriptions.php file.');
        }

        File::copy($source, $destination);
        $this->line('Published config/subscriptions.php file.');
    }

    private function replaceMigrations(): void
    {
        $sourceDirectory = __DIR__ . '/../../../database/migrations';
        $destinationDirectory = database_path('migrations');

        File::ensureDirectoryExists($destinationDirectory);

        foreach (File::files($sourceDirectory) as $migration) {
            $destination = $destinationDirectory . DIRECTORY_SEPARATOR . $migration->getFilename();

            if (File::exists($destination)) {
                File::delete($destination);
                $this->line("Deleted existing database/migrations/{$migration->getFilename()} file.");
            }

            File::copy($migration->getPathname(), $destination);
            $this->line("Published database/migrations/{$migration->getFilename()} file.");
        }
    }
}
