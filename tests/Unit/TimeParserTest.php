<?php

namespace Tests\Unit;

use App\Services\TimeParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimeParserTest extends TestCase
{
    #[DataProvider('acceptedFormats')]
    public function test_accepts_the_formats_listed_in_the_spec(string $input, int $hour, int $minute): void
    {
        $this->assertSame(['hour' => $hour, 'minute' => $minute], TimeParser::parse($input));
    }

    public static function acceptedFormats(): array
    {
        return [
            ['7', 7, 0],
            ['07:00', 7, 0],
            ['7:30', 7, 30],
            ['19h', 19, 0],
            ['19h30', 19, 30],
        ];
    }

    #[DataProvider('rejectedFormats')]
    public function test_rejects_anything_else(string $input): void
    {
        $this->assertNull(TimeParser::parse($input));
    }

    public static function rejectedFormats(): array
    {
        return [
            ['tomorrow'],
            ['25:00'],
            ['7:60'],
            ['08:30am'],
            [''],
            ['almoço'],
        ];
    }

    public function test_format_pads_to_two_digits(): void
    {
        $this->assertSame('07:05', TimeParser::format(7, 5));
    }
}
