<?php
declare(strict_types=1);
namespace Pro\Wallet;

use Pro\Core\Storage;

final class WalletState
{
    private const PATH = 'cache/runtime/wallet-settings.json';

    public static function read(): array
    {
        $row = Storage::read(self::PATH, []);
        $address = (string)($row['address'] ?? '');
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address)) $address = '';
        return [
            'address' => $address,
            'integralizado_brl' => max(0.0, self::number($row['integralizado_brl'] ?? 0)),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    public static function save(string $address, float $integralizadoBrl): array
    {
        $row = [
            'address' => $address,
            'integralizado_brl' => max(0.0, $integralizadoBrl),
            'updated_at' => time(),
        ];
        Storage::write(self::PATH, $row);
        return $row;
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float)$value : 0.0;
    }
}
