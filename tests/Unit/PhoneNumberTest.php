<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('numbers')]
    public function test_it_normalizes_to_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toE164($input));
    }

    public static function numbers(): array
    {
        return [
            'local trunk zero' => ['050 123 4567', '+971501234567'],
            'dashes' => ['052-987-6543', '+971529876543'],
            'already e164' => ['+971501234567', '+971501234567'],
            'double zero intl' => ['00971501234567', '+971501234567'],
            'bare national' => ['501234567', '+971501234567'],
            'with country no plus' => ['971501234567', '+971501234567'],
            'spaces' => [' 0501234567 ', '+971501234567'],
        ];
    }

    public function test_whatsapp_strips_plus(): void
    {
        $this->assertSame('971501234567', PhoneNumber::forWhatsApp('050 123 4567'));
    }
}
