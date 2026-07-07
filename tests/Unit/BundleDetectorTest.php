<?php

namespace Tests\Unit;

use App\Support\BundleDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BundleDetectorTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_it_detects_bundles(string $title, bool $expected): void
    {
        $this->assertSame($expected, BundleDetector::isBundle($title));
    }

    public static function cases(): array
    {
        return [
            ['SOME BY MI - Retinol Intense Trial Kit', true],
            ['COSRX - All About Snail Kit (4pcs)', true],
            ['Mielle - Rosemary Mint Strengthening Bundle', true],
            ['Beauty of Joseon - Radiance & Nourishing Duo', true],
            ['SKIN1004 - Centella Travel Kit (5pcs)', true],
            ['Glow Routine (4-Step Set)', true],
            ['Anua Heartleaf 77% Soothing Toner', false],
            ['Medicube Zero Pore Pad', false],
            ['PDRN Repair Ampoule', false],
        ];
    }

    public function test_tags_and_type_are_considered(): void
    {
        $this->assertTrue(BundleDetector::isBundle('Radiance Collection', 'Gift Set', []));
        $this->assertTrue(BundleDetector::isBundle('Radiance Collection', null, ['Bundle', 'Skincare']));
        $this->assertFalse(BundleDetector::isBundle('Radiance Serum', 'Serum', ['Brightening']));
    }
}
