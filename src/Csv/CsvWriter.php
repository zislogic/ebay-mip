<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Csv;

final class CsvWriter
{
    /**
     * Generate a CSV string from headers and rows.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    public function generate(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);

        $content = stream_get_contents($stream);
        fclose($stream);

        return $content !== false ? $content : '';
    }
}
