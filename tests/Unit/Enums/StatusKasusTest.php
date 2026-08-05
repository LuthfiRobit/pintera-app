<?php

namespace Tests\Unit\Enums;

use App\Enums\StatusKasus;
use PHPUnit\Framework\TestCase;

class StatusKasusTest extends TestCase
{
    public function test_badge_tone_returns_expected_tailwind_colors(): void
    {
        $this->assertSame('amber', StatusKasus::Diajukan->badgeTone());
        $this->assertSame('blue', StatusKasus::MenungguConsent->badgeTone());
        $this->assertSame('blue', StatusKasus::Ditugaskan->badgeTone());
        $this->assertSame('green', StatusKasus::Berjalan->badgeTone());
        $this->assertSame('red', StatusKasus::Eskalasi->badgeTone());
        $this->assertSame('slate', StatusKasus::Selesai->badgeTone());
    }
}
