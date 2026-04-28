<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Zislogic\Ebay\Connector\EbayConnectorServiceProvider;
use Zislogic\Ebay\Connector\Models\EbayCredential;
use Zislogic\Ebay\Mip\EbayMipServiceProvider;
use Zislogic\Ebay\Model\Fulfillment\EbayFulfillmentServiceProvider;
use Zislogic\Ebay\Model\Fulfillment\Models\FulfillmentOrder;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load ebay-connector migrations first (for ebay_credentials table)
        $connectorMigrationsPath = dirname(__DIR__).'/vendor/zislogic/ebay-connector/database/migrations';

        if (is_dir($connectorMigrationsPath)) {
            $this->loadMigrationsFrom($connectorMigrationsPath);
        }

        // Load fulfillment domain migrations (fulfillment_orders, fulfillment_order_lines)
        $fulfillmentMigrationsPath = dirname(__DIR__).'/vendor/zislogic/ebay-model-fulfillment/database/migrations';

        if (is_dir($fulfillmentMigrationsPath)) {
            $this->loadMigrationsFrom($fulfillmentMigrationsPath);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            EbayConnectorServiceProvider::class,
            EbayFulfillmentServiceProvider::class,
            EbayMipServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');

        $app['config']->set('ebay.environment', 'sandbox');
        $app['config']->set('ebay.credentials.sandbox', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect_uri' => 'http://localhost/ebay/oauth/callback',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createCredential(array $attributes = []): EbayCredential
    {
        return EbayCredential::query()->create(array_merge([
            'name' => 'Test Seller',
            'environment' => 'sandbox',
            'ebay_user_id' => 'test-seller-'.uniqid(),
            'refresh_token' => 'test-refresh-token-'.uniqid(),
            'refresh_token_expires_at' => now()->addDays(30),
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createOrder(int $credentialId, array $attributes = []): FulfillmentOrder
    {
        return FulfillmentOrder::query()->create(array_merge([
            'ebay_credential_id' => $credentialId,
            'order_id' => 'ORD-'.uniqid(),
            'buyer_user_id' => 'buyer-'.uniqid(),
            'buyer_email' => 'buyer@example.com',
            'buyer_name' => 'Test Buyer',
            'order_status' => 'NOT_STARTED',
            'payment_status' => 'PAID',
            'currency' => 'EUR',
            'total_price' => '99.99',
            'ship_to_name' => 'Test Buyer',
            'ship_to_street1' => 'Test Street 1',
            'ship_to_city' => 'Berlin',
            'ship_to_zip' => '10115',
            'ship_to_country' => 'DE',
            'ordered_at' => now()->subDay(),
            'imported_at' => now(),
        ], $attributes));
    }

    protected function getFixturePath(string $filename): string
    {
        return __DIR__.'/fixtures/'.$filename;
    }

    protected function getFixtureContent(string $filename): string
    {
        $path = $this->getFixturePath($filename);

        $content = file_get_contents($path);

        if ($content === false) {
            $this->fail("Failed to read fixture: {$path}");
        }

        return $content;
    }
}
