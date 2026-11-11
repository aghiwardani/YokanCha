<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public static function indonesianNumbers(): array
    {
        return [
            'local 08' => ['081234567890', '6281234567890'],
            'international plus' => ['+62 812-3456-7890', '6281234567890'],
            'international digits' => ['6281234567890', '6281234567890'],
            'without leading zero' => ['81234567890', '6281234567890'],
            'with spaces and dots' => ['0812.3456.7890', '6281234567890'],
        ];
    }

    #[DataProvider('indonesianNumbers')]
    public function test_normalizes_to_international_digits(string $input, string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    public function test_same_number_written_differently_matches(): void
    {
        $this->assertTrue(Phone::same('081234567890', '+62 812 3456 7890'));
        $this->assertFalse(Phone::same('081234567890', '081234567891'));
    }
}
