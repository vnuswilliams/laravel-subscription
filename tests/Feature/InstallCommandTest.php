<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('regenerates the published configuration and package migrations', function (): void {
    File::ensureDirectoryExists(config_path());
    File::ensureDirectoryExists(database_path('migrations'));

    File::put(config_path('subscriptions.php'), '<?php return [\'stale\' => true];');

    foreach (File::files(__DIR__ . '/../../database/migrations') as $migration) {
        File::put(database_path('migrations/' . $migration->getFilename()), '<?php // stale migration');
    }

    $this->artisan('subscription:install')
        ->expectsOutput('Subscription configuration and migrations have been regenerated.')
        ->assertSuccessful();

    expect(File::get(config_path('subscriptions.php')))
        ->toBe(File::get(__DIR__ . '/../../config/subscriptions.php'));

    foreach (File::files(__DIR__ . '/../../database/migrations') as $migration) {
        expect(File::get(database_path('migrations/' . $migration->getFilename())))
            ->toBe(File::get($migration->getPathname()));
    }
});
