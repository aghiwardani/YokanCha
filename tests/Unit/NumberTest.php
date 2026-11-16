<?php

namespace Tests\Unit;

use App\Support\Number;
use PHPUnit\Framework\TestCase;

class NumberTest extends TestCase
{
    public function test_indonesian_amounts_become_plain_numbers(): void
    {
        $this->assertSame('32000', Number::normalize('32.000'));
        $this->assertSame('2000', Number::normalize('2.000'));
        $this->assertSame('1250000', Number::normalize('1.250.000'));
        $this->assertSame('2.5', Number::normalize('2,5'));
        $this->assertSame('1250.75', Number::normalize('1.250,75'));
    }

    public function test_plain_numbers_are_left_alone(): void
    {
        $this->assertSame('2.5', Number::normalize('2.5'));
        $this->assertSame('32000', Number::normalize('32000'));
        $this->assertSame('', Number::normalize(''));
        $this->assertNull(Number::normalize(null));
        $this->assertSame('abc', Number::normalize('abc'));
    }

    public function test_nested_recipe_grids_are_normalized(): void
    {
        $this->assertSame(
            ['250 ml' => [3 => '2.5', 4 => '1000']],
            Number::normalizeAll(['250 ml' => [3 => '2,5', 4 => '1.000']]),
        );
    }
}
