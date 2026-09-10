<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Parse and format contract amounts without using floating-point arithmetic.
 */
final class ContractMoney
{
    public static function toCents(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value * 100;
        }

        if (is_float($value)) {
            if (! is_finite($value) || $value < 0) {
                throw new InvalidArgumentException('Invalid monetary value.');
            }

            $value = number_format($value, 2, '.', '');
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Invalid monetary value.');
        }

        $value = trim(str_replace(["\u{00A0}", ' '], '', $value));
        $value = preg_replace('/^R\$/', '', $value);

        if ($value === '' || ! preg_match('/^\d[\d.,]*$/', $value)) {
            throw new InvalidArgumentException('Invalid monetary value.');
        }

        if (str_contains($value, ',')) {
            if (substr_count($value, ',') !== 1) {
                throw new InvalidArgumentException('Invalid monetary value.');
            }

            [$integer, $decimal] = explode(',', $value, 2);
            if ($integer === '' || ! preg_match('/^\d+(?:\.\d{3})*$/', $integer)) {
                throw new InvalidArgumentException('Invalid monetary value.');
            }

            $integer = str_replace('.', '', $integer);
        } else {
            $decimal = '';
            $integer = $value;

            if (substr_count($value, '.') > 1) {
                if (! preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value)) {
                    throw new InvalidArgumentException('Invalid monetary value.');
                }

                $integer = str_replace('.', '', $value);
            } elseif (str_contains($value, '.')) {
                [$integer, $decimal] = explode('.', $value, 2);

                // A single three-digit group is the Brazilian thousands form
                // (1.234), while canonical decimals have at most two places.
                if (strlen($decimal) === 3 && strlen($integer) <= 3) {
                    $integer .= $decimal;
                    $decimal = '';
                }
            }
        }

        if (! preg_match('/^\d+$/', $integer) || ! preg_match('/^\d{0,2}$/', $decimal)) {
            throw new InvalidArgumentException('Invalid monetary value.');
        }

        return ((int) $integer * 100) + (int) str_pad($decimal, 2, '0');
    }

    public static function toDecimal(mixed $value): ?string
    {
        $cents = self::toCents($value);

        return $cents === null ? null : self::centsToDecimal($cents);
    }

    public static function centsToDecimal(int $cents): string
    {
        if ($cents < 0) {
            return '-'.self::centsToDecimal(abs($cents));
        }

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function centsToBr(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    public static function signedToCents(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        $negative = str_starts_with(trim($value), '-');
        $unsigned = ltrim(trim($value), '+-');
        $cents = self::toCents($unsigned);

        return $cents === null ? null : ($negative ? -$cents : $cents);
    }

    public static function signedCentsToBr(int $cents): string
    {
        return $cents < 0 ? '-'.self::centsToBr(abs($cents)) : self::centsToBr($cents);
    }
}
