<?php
declare(strict_types=1);

namespace Prontoo\Domain\Legacy\Documents;

final class DocumentsCompatibilityOperations01
{
    private function __construct()
    {
    }

    public static function cpfBr(string $cpf): string
    {
        $digits = \only_digits($cpf);
        return strlen($digits) === 11
            ? substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2)
            : $cpf;
    }
}
