<?php

namespace Tests\Unit;

use App\Models\Size;
use PHPUnit\Framework\TestCase;

class SizeTest extends TestCase
{
    public function test_volume_is_guessed_from_common_labels(): void
    {
        $this->assertSame(250, Size::volumeFromName('250 ml'));
        $this->assertSame(250, Size::volumeFromName('250ml'));
        $this->assertSame(1000, Size::volumeFromName('1 liter'));
        $this->assertSame(1000, Size::volumeFromName('1L'));
        $this->assertSame(1500, Size::volumeFromName('1.5 liter'));
        $this->assertSame(1500, Size::volumeFromName('1,5 liter'));
        $this->assertSame(1000, Size::volumeFromName('1.000 ml'));
        $this->assertSame(0, Size::volumeFromName('Jumbo'));
    }
}
