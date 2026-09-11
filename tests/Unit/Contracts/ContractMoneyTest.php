<?php

namespace Tests\Unit\Contracts;

use App\Services\ContractMoney;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ContractMoneyTest extends TestCase
{
    public function test_brazilian_and_canonical_values_share_the_same_cents(): void
    {
        $this->assertSame(123456, ContractMoney::toCents('R$ 1.234,56'));
        $this->assertSame(123456, ContractMoney::toCents('1234.56'));
        $this->assertSame(123456, ContractMoney::toCents('1.234,56'));
        $this->assertSame(0, ContractMoney::toCents('0,00'));
        $this->assertSame('R$ 1.234,56', ContractMoney::centsToBr(123456));
    }

    public function test_invalid_or_negative_values_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ContractMoney::toCents('-1,00');
    }

    public function test_decimal_conversion_does_not_shift_typed_cents(): void
    {
        $this->assertSame('12.30', ContractMoney::toDecimal('12,30'));
        $this->assertSame('0.05', ContractMoney::toDecimal('0,05'));
        $this->assertSame('0.00', ContractMoney::toDecimal('0'));
    }
}
