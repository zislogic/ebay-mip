<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Feed;

use Zislogic\Ebay\Mip\Csv\CsvWriter;
use Zislogic\Ebay\Mip\Exceptions\MipException;

/**
 * Builds a MIP product-combined CSV feed from structured config.
 *
 * Fields are defined in two categories:
 *   - SingleFields: appear exactly once per row (e.g. SKU, Title)
 *   - MultiFields:  repeat with a numbered suffix (e.g. "Attribute Name 1", "Attribute Name 2")
 *                   defined as ['header' => 'Attribute Name %d', 'max' => 45]
 *
 * Usage:
 *   $builder->setValue('sku', 'SKU-001');
 *   $builder->setValue('attribute_name', 'Brand');   // auto-increments to Attribute Name 1
 *   $builder->setValue('attribute_name', 'Color');   // auto-increments to Attribute Name 2
 *   $builder->newRow();
 *   $csv = $builder->build();
 */
final class ProductFeedBuilder
{
    /** @var array<string, array{header: string, index: int}> */
    private array $singleFields = [];

    /** @var array<string, array{header: string, max: int, counter: int, index: int}> */
    private array $multiFields = [];

    /** @var array<string, string> all columns seen across all rows (fieldKey => header label) */
    private array $seenColumns = [];

    /** @var array<int, array<string, string>> collected rows */
    private array $rows = [];

    /** @var array<string, string> current row being built */
    private array $currentRow = [];

    /**
     * @param array<string, mixed> $config  The product_feed config array with SingleFields/MultiFields
     */
    public function __construct(private readonly array $config)
    {
        $this->setupFields();
    }

    /**
     * Set a field value on the current row.
     *
     * For multi-fields (e.g. 'attribute_name'), each call auto-increments the counter.
     * Array values are pipe-joined: ['Red', 'Blue'] → 'Red|Blue'.
     *
     * @param string|array<int, string>|null $value
     *
     * @throws MipException
     */
    public function setValue(string $fieldKey, string|array|null $value): void
    {
        if (isset($this->multiFields[$fieldKey])) {
            $this->setMultiValue($fieldKey, $value);
            return;
        }

        if (isset($this->singleFields[$fieldKey])) {
            $column = $this->singleFields[$fieldKey]['header'];
            $this->seenColumns[$fieldKey] = $column;
            $this->currentRow[$fieldKey] = $this->flatten($value);
            return;
        }

        throw MipException::unknownFeedField($fieldKey);
    }

    /**
     * Finalise the current row and start a new one. Resets multi-field counters.
     */
    public function newRow(): void
    {
        $this->rows[] = $this->currentRow;
        $this->currentRow = [];
        $this->resetCounters();
    }

    /**
     * Build and return the complete CSV string.
     */
    public function build(): string
    {
        $orderedColumns = $this->orderColumns();

        $headers = array_values($orderedColumns);
        $rows = [];

        foreach ($this->rows as $row) {
            $csvRow = [];
            foreach (array_keys($orderedColumns) as $key) {
                $csvRow[] = $row[$key] ?? '';
            }
            $rows[] = $csvRow;
        }

        return (new CsvWriter())->generate($headers, $rows);
    }

    /**
     * Return the number of rows added so far.
     */
    public function rowCount(): int
    {
        return count($this->rows);
    }

    private function setupFields(): void
    {
        /** @var array<int, string> $singles */
        $singles = $this->config['SingleFields'] ?? [];
        foreach ($singles as $index => $header) {
            $key = $this->toKey($header);
            $this->singleFields[$key] = ['header' => $header, 'index' => $index];
        }

        /** @var array<int, array{header: string, max: int}> $multis */
        $multis = $this->config['MultiFields'] ?? [];
        $offset = count($singles);
        foreach ($multis as $index => $definition) {
            $key = $this->toKey(str_replace('%d', '', $definition['header']));
            $this->multiFields[$key] = [
                'header' => $definition['header'],
                'max' => $definition['max'],
                'counter' => 1,
                'index' => $offset + $index,
            ];
        }
    }

    private function resetCounters(): void
    {
        foreach ($this->multiFields as $key => $_) {
            $this->multiFields[$key]['counter'] = 1;
        }
    }

    /** @param string|array<int, string>|null $value */
    private function setMultiValue(string $fieldKey, string|array|null $value): void
    {
        $field = $this->multiFields[$fieldKey];

        if ($field['counter'] > $field['max']) {
            throw MipException::feedFieldMaxExceeded($fieldKey, $field['max']);
        }

        $numberedKey = $fieldKey . '_' . $field['counter'];
        $header = sprintf($field['header'], $field['counter']);

        $this->seenColumns[$numberedKey] = $header;
        $this->currentRow[$numberedKey] = $this->flatten($value);

        $this->multiFields[$fieldKey]['counter']++;
    }

    /**
     * Order columns: single fields first (by index), then multi-fields grouped by type.
     *
     * @return array<string, string>  fieldKey => header label
     */
    private function orderColumns(): array
    {
        $singles = [];
        $multis = [];

        foreach ($this->seenColumns as $key => $header) {
            // Multi-field keys end in _N
            $parts = explode('_', $key);
            $suffix = end($parts);

            if (is_numeric($suffix)) {
                $baseKey = implode('_', array_slice($parts, 0, -1));
                if (isset($this->multiFields[$baseKey])) {
                    $multis[$baseKey][$key] = $header;
                    continue;
                }
            }

            $index = $this->singleFields[$key]['index'] ?? PHP_INT_MAX;
            $singles[$index] = [$key => $header];
        }

        ksort($singles);

        $ordered = [];
        foreach ($singles as $pair) {
            foreach ($pair as $k => $h) {
                $ordered[$k] = $h;
            }
        }

        // Append multi-fields grouped by type, sorted numerically within each group
        foreach ($this->multiFields as $baseKey => $definition) {
            if (!isset($multis[$baseKey])) {
                continue;
            }
            uksort($multis[$baseKey], static function (string $a, string $b): int {
                $numA = (int) substr((string) strrchr($a, '_'), 1);
                $numB = (int) substr((string) strrchr($b, '_'), 1);
                return $numA <=> $numB;
            });
            foreach ($multis[$baseKey] as $k => $h) {
                $ordered[$k] = $h;
            }
        }

        return $ordered;
    }

    /** @param string|array<int, string>|null $value */
    private function flatten(string|array|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return implode('|', $value);
        }

        return $value;
    }

    private function toKey(string $header): string
    {
        return strtolower(str_replace([' ', '%d'], ['_', ''], trim($header)));
    }
}
