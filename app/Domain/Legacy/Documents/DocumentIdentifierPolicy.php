<?php
declare(strict_types=1);

namespace Prontoo\Domain\Legacy\Documents;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class DocumentIdentifierPolicy
{
    private function __construct()
    {
    }

    public static function document_identifier_consonants(): array
    
    {
    
        return str_split("BCDFGHJKLMNPQRSTVWXYZ");
    
    }

    public static function document_identifier_base(): int
    
    {
    
        return count(document_identifier_consonants());
    
    }

    public static function document_identifier_random_alphabet(): string
    
    {
    
        $letters = document_identifier_consonants();
        shuffle($letters);
        return implode("", $letters);
    
    }

    public static function document_identifier_valid_alphabet(string $alphabet): bool
    
    {
    
        $alphabet = strtoupper(trim($alphabet));
        $letters = str_split($alphabet);
        $allowed = document_identifier_consonants();
        sort($letters);
        sort($allowed);
        return strlen($alphabet) === document_identifier_base() &&
            $letters === $allowed;
    
    }

    public static function document_identifier_digits(int $sequence): array
    
    {
    
        if ($sequence <= 0) {
            throw new RuntimeException("Sequência documental inválida.");
        }
        $base = document_identifier_base();
        $n = $sequence - 1;
        $digits = [];
        do {
            $digits[] = $n % $base;
            $n = intdiv($n, $base);
        } while ($n > 0);
        $digits = array_reverse($digits);
        while (count($digits) < 3) {
            array_unshift($digits, 0);
        }
        return $digits;
    
    }

    public static function document_identifier_numeric_part(int $sequence): string
    
    {
    
        return implode(
            "",
            array_map(
                static  fn($d) => (string) $d,
                document_identifier_digits($sequence),
            ),
        );
    
    }

    public static function document_identifier_encode(int $sequence, string $alphabet): string
    
    {
    
        $alphabet = strtoupper($alphabet);
        if (!document_identifier_valid_alphabet($alphabet)) {
            throw new RuntimeException(
                "Mapa de identificação documental inválido.",
            );
        }
        $out = "";
        foreach (document_identifier_digits($sequence) as $digit) {
            $out .= $alphabet[(int) $digit];
        }
        return $out;
    
    }

    public static function document_identifier_display(?string $identifier): string
    
    {
    
        $identifier = strtoupper(mb_trim((string) $identifier));
        return preg_match('/^[A-Z]{3,12}$/', $identifier) ? $identifier : "";
    
    }

    public static function document_identifier_capacity_for_length(int $length = 3): int
    
    {
    
        $length = max(1, $length);
        return (int) (document_identifier_base() ** $length);
    
    }

    public static function document_print_header_html(?string $identifier): string
    
    {
    
        return "";
    
    }

}
