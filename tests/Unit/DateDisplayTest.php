<?php

namespace Tests\Unit;

use App\Support\DateDisplay;
use PHPUnit\Framework\TestCase;

class DateDisplayTest extends TestCase
{
    public function test_datetimes_are_shown_as_day_only(): void
    {
        $this->assertSame('26/10/2026', DateDisplay::format('2026-10-26 14:35:09'));
        $this->assertSame('26/10/2026', DateDisplay::format('2026-10-26T14:35:09.000000Z'));
        $this->assertSame('26/10/2026', DateDisplay::format('2026-10-26 14:35'));
        $this->assertSame('26/10/2026', DateDisplay::format('2026-10-26'));
    }

    public function test_other_values_are_left_untouched(): void
    {
        $this->assertSame('PRESTITO', DateDisplay::format('PRESTITO'));
        $this->assertSame('2026-13-45 10:00:00', DateDisplay::format('2026-13-45 10:00:00'));
        $this->assertSame(1234, DateDisplay::format(1234));
        $this->assertNull(DateDisplay::format(null));
    }
}
