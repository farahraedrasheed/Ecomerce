<?php

namespace App\Support;

use Carbon\Carbon;

class CardValidator
{
    public static function digitsOnly(string $number): string
    {
        return preg_replace('/\D/', '', $number);
    }

    public static function passesLuhnCheck(string $number): bool
    {
        if (! preg_match('/^\d{13,19}$/', $number)) {
            return false;
        }

        $sum = 0;
        $alternate = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }

    public static function isExpired(string $expiry): bool
    {
        [$month, $year] = explode('/', $expiry);

        return Carbon::createFromDate(2000 + (int) $year, (int) $month, 1)->endOfMonth()->isPast();
    }

    public static function detectBrand(string $number): string
    {
        if (str_starts_with($number, '4')) {
            return 'Visa';
        }

        if (preg_match('/^5[1-5]/', $number)) {
            return 'Mastercard';
        }

        if (preg_match('/^3[47]/', $number)) {
            return 'Amex';
        }

        return 'Card';
    }
}
