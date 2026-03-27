<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Tests\Unit\Csv;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zislogic\Ebay\Mip\Csv\CsvReader;
use Zislogic\Ebay\Mip\Exceptions\MipException;

final class CsvReaderTest extends TestCase
{
    private CsvReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new CsvReader();
    }

    #[Test]
    public function it_parses_simple_csv(): void
    {
        $csv = "name,age,city\nAlice,30,Berlin\nBob,25,Munich\n";

        $result = $this->reader->parse($csv);

        $this->assertCount(2, $result);
        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame('30', $result[0]['age']);
        $this->assertSame('Berlin', $result[0]['city']);
        $this->assertSame('Bob', $result[1]['name']);
    }

    #[Test]
    public function it_handles_quoted_fields(): void
    {
        $csv = "name,description\n\"Smith, John\",\"A \"\"quoted\"\" value\"\n";

        $result = $this->reader->parse($csv);

        $this->assertCount(1, $result);
        $this->assertSame('Smith, John', $result[0]['name']);
        $this->assertSame('A "quoted" value', $result[0]['description']);
    }

    #[Test]
    public function it_handles_newlines_in_quoted_fields(): void
    {
        $csv = "name,note\nAlice,\"Line 1\nLine 2\"\nBob,Simple\n";

        $result = $this->reader->parse($csv);

        $this->assertCount(2, $result);
        $this->assertSame("Line 1\nLine 2", $result[0]['note']);
        $this->assertSame('Simple', $result[1]['note']);
    }

    #[Test]
    public function it_strips_utf8_bom(): void
    {
        $csv = "\xEF\xBB\xBFname,age\nAlice,30\n";

        $result = $this->reader->parse($csv);

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result[0]['name']);
    }

    #[Test]
    public function it_returns_empty_array_for_empty_content(): void
    {
        $result = $this->reader->parse('');

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_returns_empty_array_for_header_only(): void
    {
        $result = $this->reader->parse("name,age,city\n");

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_handles_windows_line_endings(): void
    {
        $csv = "name,age\r\nAlice,30\r\nBob,25\r\n";

        $result = $this->reader->parse($csv);

        $this->assertCount(2, $result);
        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame('Bob', $result[1]['name']);
    }

    #[Test]
    public function it_pads_short_rows(): void
    {
        $csv = "a,b,c\n1,2\n";

        $result = $this->reader->parse($csv);

        $this->assertCount(1, $result);
        $this->assertSame('1', $result[0]['a']);
        $this->assertSame('2', $result[0]['b']);
        $this->assertSame('', $result[0]['c']);
    }

    #[Test]
    public function it_trims_long_rows(): void
    {
        $csv = "a,b\n1,2,3\n";

        $result = $this->reader->parse($csv);

        $this->assertCount(1, $result);
        $this->assertSame('1', $result[0]['a']);
        $this->assertSame('2', $result[0]['b']);
        $this->assertArrayNotHasKey('2', $result[0]);
    }

    #[Test]
    public function it_skips_empty_lines(): void
    {
        $csv = "name,age\nAlice,30\n\nBob,25\n";

        $result = $this->reader->parse($csv);

        $this->assertCount(2, $result);
    }
}
