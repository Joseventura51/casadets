<?php

namespace App\Support;

/**
 * Fixed-point helpers for monetary calculations. No binary floating point is used.
 */
final class DecimalMoney
{
    public static function scaledInt(int|float|string|null $value, int $scale): int
    {
        $raw = trim((string) ($value ?? '0'));
        if ($raw === '') {
            return 0;
        }
        if (!preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/', $raw, $parts)) {
            throw new \InvalidArgumentException('Invalid decimal value.');
        }

        $negative = ($parts[1] ?? '') === '-';
        $whole = (int) $parts[2];
        $fraction = $parts[3] ?? '';
        $kept = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $result = $whole * (10 ** $scale) + (int) ($kept === '' ? '0' : $kept);

        if (strlen($fraction) > $scale && (int) $fraction[$scale] >= 5) {
            $result++;
        }

        return $negative ? -$result : $result;
    }

    public static function roundedDivision(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new \InvalidArgumentException('Denominator must be positive.');
        }
        $sign = $numerator < 0 ? -1 : 1;
        $absolute = abs($numerator);
        $quotient = intdiv($absolute, $denominator);
        $remainder = $absolute % $denominator;
        if ($remainder * 2 >= $denominator) {
            $quotient++;
        }
        return $sign * $quotient;
    }

    public static function cents(int|float|string|null $value): int
    {
        return self::scaledInt($value, 2);
    }

    public static function quantityHundredths(int|float|string|null $value): int
    {
        return self::scaledInt($value, 2);
    }

    public static function costUnits(int|float|string|null $value): int
    {
        return self::scaledInt($value, 4);
    }

    public static function multiplyCentsByQuantity(int $cents, int $quantityHundredths): int
    {
        return self::roundedDivision($cents * $quantityHundredths, 100);
    }

    public static function multiplyCostByQuantity(int $costUnits, int $quantityHundredths): int
    {
        return self::roundedDivision($costUnits * $quantityHundredths, 10000);
    }

    public static function commissionCents(int $utilityCents, int $rateHundredths): int
    {
        return self::roundedDivision($utilityCents * $rateHundredths, 10000);
    }

    public static function formatCents(int $cents): string
    {
        return self::formatScaled($cents, 2);
    }

    public static function formatScaled(int $value, int $scale): string
    {
        $sign = $value < 0 ? '-' : '';
        $absolute = abs($value);
        $base = 10 ** $scale;
        return $sign . intdiv($absolute, $base) . '.' . str_pad((string) ($absolute % $base), $scale, '0', STR_PAD_LEFT);
    }
}
