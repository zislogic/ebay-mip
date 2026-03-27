<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Csv;

use Zislogic\Ebay\Mip\Exceptions\MipException;

final class CsvReader
{
    /**
     * Parse CSV content into an array of associative arrays.
     *
     * The first row is treated as headers and becomes the keys for each data row.
     *
     * @return array<int, array<string, string>>
     *
     * @throws MipException
     */
    public function parse(string $csvContent): array
    {
        $csvContent = $this->stripBom($csvContent);

        if (trim($csvContent) === '') {
            return [];
        }

        $lines = $this->splitLines($csvContent);

        if (count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv($lines[0]);

        /** @var array<int, string> $headers */
        $headers = array_map(fn (mixed $h): string => trim((string) $h), $headers);

        if ($headers === [] || ($headers === [''])) {
            throw MipException::invalidCsv('No headers found in CSV');
        }

        $rows = [];

        for ($i = 1, $count = count($lines); $i < $count; $i++) {
            $line = trim($lines[$i]);

            if ($line === '') {
                continue;
            }

            $values = str_getcsv($line);

            /** @var array<int, string> $stringValues */
            $stringValues = array_map(fn (mixed $v): string => (string) ($v ?? ''), $values);

            // Pad or trim values to match header count
            $headerCount = count($headers);
            $valueCount = count($stringValues);

            if ($valueCount < $headerCount) {
                $stringValues = array_pad($stringValues, $headerCount, '');
            } elseif ($valueCount > $headerCount) {
                $stringValues = array_slice($stringValues, 0, $headerCount);
            }

            /** @var array<string, string> $row */
            $row = array_combine($headers, $stringValues);

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Strip UTF-8 BOM from the beginning of content.
     */
    private function stripBom(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return $content;
    }

    /**
     * Split CSV content into lines, handling quoted fields that contain newlines.
     *
     * @return array<int, string>
     */
    private function splitLines(string $content): array
    {
        $lines = [];
        $currentLine = '';
        $inQuotes = false;

        for ($i = 0, $length = strlen($content); $i < $length; $i++) {
            $char = $content[$i];

            if ($char === '"') {
                $inQuotes = ! $inQuotes;
                $currentLine .= $char;
            } elseif (($char === "\n" || $char === "\r") && ! $inQuotes) {
                if ($char === "\r" && isset($content[$i + 1]) && $content[$i + 1] === "\n") {
                    $i++;
                }

                $lines[] = $currentLine;
                $currentLine = '';
            } else {
                $currentLine .= $char;
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines;
    }
}
