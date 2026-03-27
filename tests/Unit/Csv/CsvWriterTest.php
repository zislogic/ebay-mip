<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Tests\Unit\Csv;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zislogic\Ebay\Mip\Csv\CsvWriter;

final class CsvWriterTest extends TestCase
{
    private CsvWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new CsvWriter();
    }

    #[Test]
    public function it_generates_csv_with_headers_and_rows(): void
    {
        $headers = ['Order ID', 'Line Item ID', 'Status'];
        $rows = [
            ['ORD-001', 'LI-001', 'SHIPPED'],
            ['ORD-002', 'LI-002', 'ACK'],
        ];

        $csv = $this->writer->generate($headers, $rows);

        $lines = explode("\n", trim($csv));
        $this->assertCount(3, $lines);
        $this->assertSame('"Order ID","Line Item ID",Status', $lines[0]);
        $this->assertSame('ORD-001,LI-001,SHIPPED', $lines[1]);
        $this->assertSame('ORD-002,LI-002,ACK', $lines[2]);
    }

    #[Test]
    public function it_handles_empty_rows(): void
    {
        $csv = $this->writer->generate(['a', 'b'], []);

        $lines = explode("\n", trim($csv));
        $this->assertCount(1, $lines);
    }

    #[Test]
    public function it_quotes_fields_with_commas(): void
    {
        $headers = ['name', 'value'];
        $rows = [
            ['Smith, John', 'test'],
        ];

        $csv = $this->writer->generate($headers, $rows);

        $this->assertStringContainsString('"Smith, John"', $csv);
    }

    #[Test]
    public function it_roundtrips_with_csv_reader(): void
    {
        $reader = new \Zislogic\Ebay\Mip\Csv\CsvReader();
        $headers = ['Order ID', 'Carrier', 'Tracking'];
        $rows = [
            ['ORD-001', 'DHL', 'TRACK123'],
            ['ORD-002', 'DPD', 'TRACK456'],
        ];

        $csv = $this->writer->generate($headers, $rows);
        $parsed = $reader->parse($csv);

        $this->assertCount(2, $parsed);
        $this->assertSame('ORD-001', $parsed[0]['Order ID']);
        $this->assertSame('DHL', $parsed[0]['Carrier']);
        $this->assertSame('ORD-002', $parsed[1]['Order ID']);
    }
}
