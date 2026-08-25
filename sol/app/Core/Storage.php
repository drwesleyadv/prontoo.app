<?php
declare(strict_types=1);
namespace Pro\Core;

final class Storage
{
    public static function ensureBaseTree(): void
    {
        foreach (['cache', 'cache/model', 'cache/runtime', 'logs'] as $dir) {
            $path = Config::path($dir);
            if (!is_dir($path)) mkdir($path, 0755, true);
        }
    }

    public static function read(string $relative, mixed $fallback = []): mixed
    {
        $file = Config::path($relative);
        if (!is_file($file) || !is_readable($file)) return $fallback;
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') return $fallback;
        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
    }

    public static function write(string $relative, mixed $data): void
    {
        $file = Config::path($relative);
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new \RuntimeException('Falha ao codificar JSON: ' . json_last_error_msg());
        $tmp = $file . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            throw new \RuntimeException('Falha ao escrever arquivo temporário: ' . $tmp);
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Falha ao publicar arquivo JSON: ' . $file);
        }
    }

    public static function appendLog(string $relative, string $line): void
    {
        $file = Config::path($relative);
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (is_file($file) && filesize($file) > 2097152) @rename($file, $file . '.1');
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
