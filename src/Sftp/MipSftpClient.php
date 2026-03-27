<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Sftp;

use phpseclib3\Net\SFTP;
use Zislogic\Ebay\Connector\Models\EbayCredential;
use Zislogic\Ebay\Connector\Services\EbayTokenManager;
use Zislogic\Ebay\Mip\Exceptions\MipException;

class MipSftpClient
{
    private ?SFTP $sftp = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
    ) {}

    /**
     * Create an SFTP client from an eBay credential.
     *
     * @param array<string, mixed> $config
     *
     * @throws MipException
     */
    public static function fromCredential(
        int $credentialId,
        array $config,
        EbayTokenManager $tokenManager,
    ): self {
        /** @var EbayCredential $credential */
        $credential = EbayCredential::query()->findOrFail($credentialId);

        $accessToken = $tokenManager->getSellerAccessToken($credentialId);

        /** @var array<string, mixed> $sftpConfig */
        $sftpConfig = $config['sftp'][$credential->environment] ?? [];

        $host = (string) ($sftpConfig['host'] ?? 'mip.ebay.com');
        $port = (int) ($sftpConfig['port'] ?? 22);

        return new self(
            host: $host,
            port: $port,
            username: $credential->ebay_user_id,
            password: $accessToken,
        );
    }

    /**
     * Connect and authenticate to the SFTP server.
     *
     * @throws MipException
     */
    public function connect(): void
    {
        if ($this->sftp !== null) {
            return;
        }

        try {
            $sftp = new SFTP($this->host, $this->port);
        } catch (\Throwable $e) {
            throw MipException::connectionFailed($this->host, $e->getMessage());
        }

        if (! $sftp->login($this->username, $this->password)) {
            throw MipException::authenticationFailed($this->host, $this->username);
        }

        $this->sftp = $sftp;
    }

    /**
     * List files in a directory.
     *
     * @return array<int, array{filename: string, size: int, mtime: int}>
     *
     * @throws MipException
     */
    public function listFiles(string $path): array
    {
        $this->ensureConnected();

        /** @var SFTP $sftp */
        $sftp = $this->sftp;

        $rawList = $sftp->rawlist($path);

        if ($rawList === false) {
            throw MipException::transferFailed($path, 'Failed to list directory');
        }

        $files = [];

        /** @var array<string, mixed> $rawList */
        foreach ($rawList as $filename => $attrs) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            if (! is_array($attrs)) {
                continue;
            }

            /** @var int $type */
            $type = $attrs['type'] ?? 0;

            // Skip directories (type 2)
            if ($type === 2) {
                continue;
            }

            $files[] = [
                'filename' => (string) $filename,
                'size' => (int) ($attrs['size'] ?? 0),
                'mtime' => (int) ($attrs['mtime'] ?? 0),
            ];
        }

        // Sort by mtime descending (newest first)
        usort($files, fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return $files;
    }

    /**
     * Download a file and return its contents.
     *
     * @throws MipException
     */
    public function downloadFile(string $remotePath): string
    {
        $this->ensureConnected();

        /** @var SFTP $sftp */
        $sftp = $this->sftp;

        /** @var string|false $contents */
        $contents = $sftp->get($remotePath);

        if (! is_string($contents) || $contents === '') {
            throw MipException::transferFailed($remotePath, 'Failed to download file');
        }

        return $contents;
    }

    /**
     * Upload content to a remote file.
     *
     * @throws MipException
     */
    public function uploadFile(string $remotePath, string $contents): void
    {
        $this->ensureConnected();

        /** @var SFTP $sftp */
        $sftp = $this->sftp;

        $result = $sftp->put($remotePath, $contents);

        if ($result === false) {
            throw MipException::transferFailed($remotePath, 'Failed to upload file');
        }
    }

    /**
     * Disconnect from the SFTP server.
     */
    public function disconnect(): void
    {
        if ($this->sftp !== null) {
            $this->sftp->disconnect();
            $this->sftp = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->sftp !== null && $this->sftp->isConnected();
    }

    /**
     * Ensure we have an active SFTP connection (lazy connect).
     *
     * @throws MipException
     */
    private function ensureConnected(): void
    {
        if ($this->sftp === null || ! $this->sftp->isConnected()) {
            $this->sftp = null;
            $this->connect();
        }
    }
}
