<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip;

use Illuminate\Support\ServiceProvider;
use Zislogic\Ebay\Mip\Commands\ExportFulfillmentCommand;
use Zislogic\Ebay\Mip\Commands\ExportInventoryFeedCommand;
use Zislogic\Ebay\Mip\Commands\ExportProductFeedCommand;
use Zislogic\Ebay\Mip\Commands\ImportOrdersCommand;

final class EbayMipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ebay-mip.php', 'ebay-mip');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/ebay-mip.php' => config_path('ebay-mip.php'),
        ], 'ebay-mip-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'ebay-mip-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportOrdersCommand::class,
                ExportFulfillmentCommand::class,
                ExportProductFeedCommand::class,
                ExportInventoryFeedCommand::class,
            ]);
        }
    }
}
