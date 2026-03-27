<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Exceptions;

use Exception;

final class MipException extends Exception
{
    public static function connectionFailed(string $host, string $detail = ''): self
    {
        $message = "Failed to connect to MIP SFTP server at {$host}";

        if ($detail !== '') {
            $message .= ': ' . $detail;
        }

        return new self($message);
    }

    public static function authenticationFailed(string $host, string $username): self
    {
        return new self("SFTP authentication failed for user '{$username}' at {$host}");
    }

    public static function transferFailed(string $path, string $detail = ''): self
    {
        $message = "SFTP transfer failed for path: {$path}";

        if ($detail !== '') {
            $message .= ': ' . $detail;
        }

        return new self($message);
    }

    public static function fileNotFound(string $path): self
    {
        return new self("File not found on MIP SFTP: {$path}");
    }

    public static function invalidCsv(string $detail = ''): self
    {
        $message = 'Invalid CSV format';

        if ($detail !== '') {
            $message .= ': ' . $detail;
        }

        return new self($message);
    }

    public static function missingColumn(string $columnName): self
    {
        return new self("Required CSV column missing: {$columnName}");
    }
}
