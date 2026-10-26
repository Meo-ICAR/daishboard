<?php

namespace Tests\Unit;

use App\Support\NumberFormat;
use PHPUnit\Framework\TestCase;

class NumberFormatTest extends TestCase
{
    public function test_it_adds_thousands_separator_and_italian_decimals(): void
    {
        $this->assertSame('1.234.567', NumberFormat::format(1234567));
        $this->assertSame('1.234.567', NumberFormat::format('1234567'));
        $this->assertSame('1.234,50', NumberFormat::format('1234.5'));
        $this->assertSame('-9.876,12', NumberFormat::format(-9876.123));
        $this->assertSame('0', NumberFormat::format(0));
    }

    public function test_it_leaves_non_numeric_and_zero_padded_values_untouched(): void
    {
        $this->assertSame('ABC', NumberFormat::format('ABC'));
        $this->assertNull(NumberFormat::format(null));
        $this->assertSame('00123', NumberFormat::format('00123'));
    }

    public function test_identifier_and_period_columns_are_not_formatted(): void
    {
        $this->assertSame(2026, NumberFormat::formatColumn('competenza', 2026));
        $this->assertSame(98765, NumberFormat::formatColumn('id_pratica', 98765));
        $this->assertSame(2026, NumberFormat::formatColumn('anno', 2026));
        $this->assertSame('12.345', NumberFormat::formatColumn('numero_pratiche', 12345));
        $this->assertSame('1.000', NumberFormat::formatColumn('importo', '1000.00'));
    }
}
