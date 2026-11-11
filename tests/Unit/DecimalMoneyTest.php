<?php

namespace Tests\Unit;

use App\Support\DecimalMoney;
use PHPUnit\Framework\TestCase;

class DecimalMoneyTest extends TestCase
{
    public function test_it_applies_commission_rates_in_fixed_point(): void
    {
        $utility = DecimalMoney::cents('200.00');

        $this->assertSame('70.00', DecimalMoney::formatCents(
            DecimalMoney::commissionCents($utility, 3500)
        ));
        $this->assertSame('100.00', DecimalMoney::formatCents(
            DecimalMoney::commissionCents($utility, 5000)
        ));
    }

    public function test_it_multiplies_fractional_quantities_and_four_decimal_costs(): void
    {
        $revenue = DecimalMoney::multiplyCentsByQuantity(
            DecimalMoney::cents('19.99'),
            DecimalMoney::quantityHundredths('1.25')
        );
        $cost = DecimalMoney::multiplyCostByQuantity(
            DecimalMoney::costUnits('10.1234'),
            DecimalMoney::quantityHundredths('1.25')
        );

        $this->assertSame('24.99', DecimalMoney::formatCents($revenue));
        $this->assertSame('12.65', DecimalMoney::formatCents($cost));
        $this->assertSame('4.32', DecimalMoney::formatCents(
            DecimalMoney::commissionCents($revenue - $cost, 3500)
        ));
    }

    public function test_negative_commission_is_preserved(): void
    {
        $this->assertSame('-45.50', DecimalMoney::formatCents(
            DecimalMoney::commissionCents(DecimalMoney::cents('-130.00'), 3500)
        ));
        $this->assertSame('50.00', DecimalMoney::formatCents(
            DecimalMoney::cents('-30.00') + DecimalMoney::cents('80.00')
        ));
    }
}
