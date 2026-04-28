<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Commands;

use Illuminate\Console\Command;
use Zislogic\Ebay\Connector\Services\EbayTokenManager;
use Zislogic\Ebay\Mip\Csv\CsvWriter;
use Zislogic\Ebay\Mip\Services\FulfillmentExportService;
use Zislogic\Ebay\Mip\Sftp\MipSftpClient;

final class ExportFulfillmentCommand extends Command
{
    protected $signature = 'ebay:mip-export-fulfillment
        {--credential= : eBay credential ID}';

    protected $description = 'Upload order fulfillment CSV to eBay MIP SFTP';

    public function handle(EbayTokenManager $tokenManager): int
    {
        $credentialId = (int) $this->option('credential');

        if ($credentialId === 0) {
            $this->error('Please provide a credential ID with --credential');

            return self::FAILURE;
        }

        $this->info("Exporting fulfillment for credential #{$credentialId}...");

        try {
            /** @var array<string, mixed> $config */
            $config = config('ebay-mip');

            $sftp = MipSftpClient::fromCredential($credentialId, $config, $tokenManager);
            $csvWriter = new CsvWriter;

            $service = new FulfillmentExportService($sftp, $csvWriter, $config);

            $result = $service->export($credentialId);

            $sftp->disconnect();

            if ($result['count'] === 0) {
                $this->warn('No fulfilled orders pending export.');

                return self::SUCCESS;
            }

            $this->info("Exported {$result['count']} line(s) to {$result['filename']}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Export failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
