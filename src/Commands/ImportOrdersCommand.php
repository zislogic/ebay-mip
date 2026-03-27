<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Commands;

use Illuminate\Console\Command;
use Zislogic\Ebay\Connector\Services\EbayTokenManager;
use Zislogic\Ebay\Mip\Csv\CsvReader;
use Zislogic\Ebay\Mip\Services\OrderImportService;
use Zislogic\Ebay\Mip\Sftp\MipSftpClient;

final class ImportOrdersCommand extends Command
{
    protected $signature = 'ebay:mip-import-orders
        {--credential= : eBay credential ID}
        {--type=latest : Report type: latest or eod}';

    protected $description = 'Download and import order reports from eBay MIP SFTP';

    public function handle(EbayTokenManager $tokenManager): int
    {
        $credentialId = (int) $this->option('credential');

        /** @var string $type */
        $type = $this->option('type') ?? 'latest';

        if ($credentialId === 0) {
            $this->error('Please provide a credential ID with --credential');

            return self::FAILURE;
        }

        $this->info("Importing MIP orders for credential #{$credentialId} (type: {$type})...");

        try {
            /** @var array<string, mixed> $config */
            $config = config('ebay-mip');

            $sftp = MipSftpClient::fromCredential($credentialId, $config, $tokenManager);
            $csvReader = new CsvReader();

            $service = new OrderImportService($sftp, $csvReader, $config);

            $count = $service->importLatest($credentialId, $type);

            $sftp->disconnect();

            if ($count === 0) {
                $this->warn('No orders found to import.');

                return self::SUCCESS;
            }

            $this->info("Successfully imported {$count} order(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Import failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
