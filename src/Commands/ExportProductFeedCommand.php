<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Commands;

use Illuminate\Console\Command;
use Zislogic\Ebay\Connector\Services\EbayTokenManager;
use Zislogic\Ebay\Mip\Services\ProductFeedExportService;
use Zislogic\Ebay\Mip\Sftp\MipSftpClient;

final class ExportProductFeedCommand extends Command
{
    protected $signature = 'ebay:mip-export-product-feed
        {--credential= : eBay credential ID}';

    protected $description = 'Build and upload product-combined feed CSV to eBay MIP SFTP';

    public function handle(EbayTokenManager $tokenManager): int
    {
        $credentialId = (int) $this->option('credential');

        if ($credentialId === 0) {
            $this->error('Please provide a credential ID with --credential');

            return self::FAILURE;
        }

        $this->info("Exporting product feed for credential #{$credentialId}...");

        try {
            /** @var array<string, mixed> $config */
            $config = config('ebay-mip');

            $sftp = MipSftpClient::fromCredential($credentialId, $config, $tokenManager);
            $service = new ProductFeedExportService($sftp, $config);

            $result = $service->export($credentialId);

            $sftp->disconnect();

            if ($result['count'] === 0) {
                $this->warn('No inventory items found for this credential.');

                return self::SUCCESS;
            }

            $this->info("Exported {$result['count']} item(s) to {$result['filename']}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Export failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
