<?php
declare(strict_types=1);

namespace Prontoo\Presentation\PublicWeb;

final class PublicWebEscapeOperation
{
    private function __construct()
    {
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
