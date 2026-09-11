<?php

namespace Tests\Unit;

use App\Support\Masks;
use Tests\TestCase;

class MasksTest extends TestCase
{
    public function test_it_masks_a_string_longer_than_eight_characters(): void
    {
        $this->assertSame('3513******0001', Masks::partial('3513126804000001'));
    }

    public function test_it_fully_masks_a_string_of_eight_characters_or_fewer(): void
    {
        $this->assertSame('******', Masks::partial('1234567'));
        $this->assertSame('******', Masks::partial('12345678'));
    }

    public function test_it_uses_exactly_six_stars_regardless_of_original_length(): void
    {
        $this->assertSame('1234******9012', Masks::partial('123456789012'));
        $this->assertSame('1234******9012', Masks::partial('1234567890123456789012'));
    }
}
