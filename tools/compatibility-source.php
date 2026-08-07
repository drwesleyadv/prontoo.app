<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Architecture/CompatibilitySourceResolver.php';

function compatibility_source(string $root, string $relative): string
{
    return \Prontoo\Core\Architecture\CompatibilitySourceResolver::content($root, $relative);
}
