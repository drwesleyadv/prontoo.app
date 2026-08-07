<?php
declare(strict_types=1);

namespace Prontoo\Core\Architecture;

final class CompatibilitySourceResolver
{
    private function __construct()
    {
    }

    public static function paths(string $root, string $relative): array
    {
        $root = mb_rtrim(str_replace('\\', '/', $root), '/');
        $relative = mb_ltrim(str_replace('\\', '/', $relative), '/');
        $paths = [$relative => true];
        $file = $root . '/' . $relative;
        if (!is_file($file)) {
            return array_keys($paths);
        }
        $source = (string) file_get_contents($file);
        preg_match_all(
            '~\\\\Prontoo\\\\(?:Core|Domain|Application|Infrastructure|Presentation|Runtime|Install)\\\\[A-Za-z0-9_\\\\]+(?=::[A-Za-z_][A-Za-z0-9_]*\\s*\\()~',
            $source,
            $matches,
        );
        foreach (array_unique((array) ($matches[0] ?? [])) as $class) {
            $class = mb_ltrim((string) $class, '\\');
            if (!str_starts_with($class, 'Prontoo\\')) {
                continue;
            }
            $classRelative = 'app/' . str_replace('\\', '/', substr($class, strlen('Prontoo\\'))) . '.php';
            if (is_file($root . '/' . $classRelative)) {
                $paths[$classRelative] = true;
            }
        }
        return array_keys($paths);
    }

    public static function content(string $root, string $relative): string
    {
        $chunks = [];
        foreach (self::paths($root, $relative) as $path) {
            $file = mb_rtrim($root, '/') . '/' . mb_ltrim($path, '/');
            if (is_file($file)) {
                $chunks[] = (string) file_get_contents($file);
            }
        }
        return implode("\n", $chunks);
    }
}
