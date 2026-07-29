<?php
declare(strict_types=1);

namespace Prontoo\Domain\Identity;

final class IdentityDocumentValidator
{
    public static function cpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }
        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $digits[$index] * ($position + 1 - $index);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $digits[$position] !== $digit) {
                return false;
            }
        }
        return true;
    }

    public static function cnpj(string $digits): bool
    {
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }
        $calculate = static function (int $length) use ($digits): int {
            $weights = $length === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $digits[$index] * $weights[$index];
            }
            $remainder = $sum % 11;
            return $remainder < 2 ? 0 : 11 - $remainder;
        };
        return (int) $digits[12] === $calculate(12)
            && (int) $digits[13] === $calculate(13);
    }

    public static function birthDate(null|string|int $birth): bool
    {
        $raw = trim((string) $birth);
        if ($raw === '') {
            return false;
        }
        $ymd = preg_match('/^-?\d+$/', $raw)
            ? gmdate('Y-m-d', (int) $raw)
            : $raw;
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $matches)) {
            return false;
        }
        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if (!checkdate($month, $day, $year)) {
            return false;
        }
        $date = new \DateTimeImmutable($ymd . ' 12:00:00', new \DateTimeZone('UTC'));
        $today = new \DateTimeImmutable(gmdate('Y-m-d') . ' 23:59:59', new \DateTimeZone('UTC'));
        $oldest = $today->modify('-120 years')->setTime(0, 0, 0);
        return $date <= $today && $date >= $oldest;
    }
}
