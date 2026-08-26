<?php
declare(strict_types=1);
namespace Pro\Core;

final class HttpClient
{
    public static function get(string $url, int $timeout = 20, int $attempts = 3, array $headers = []): ?string
    {
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($attempt > 0) usleep(120000 * $attempt);
            $result = self::request('GET', $url, null, $timeout, $headers);
            if ($result !== null) return $result;
        }
        return null;
    }

    public static function postJson(string $url, array $payload, int $timeout = 15, array $headers = []): ?array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) return null;
        $raw = self::request('POST', $url, $body, $timeout, $headers);
        if ($raw === null) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function request(string $method, string $url, ?string $body, int $timeout, array $extraHeaders): ?string
    {
        $headers = ['Accept: application/json', 'User-Agent: sol-dashboard/2.1'];
        foreach ($extraHeaders as $header) {
            if (is_string($header) && $header !== '') $headers[] = $header;
        }
        if ($body !== null) $headers[] = 'Content-Type: application/json';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) return null;
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => min(6, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => $method,
            ]);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if (is_string($raw) && $status >= 200 && $status < 300) return $raw;
            Log::warn('HTTP ' . $status . ' em ' . self::logUrl($url));
            return null;
        }

        $headerText = implode("\r\n", $headers) . "\r\n";
        $context = stream_context_create(['http' => [
            'method' => $method,
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => $headerText,
            'content' => $body ?? '',
        ]]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) return null;
        $status = self::status($http_response_header ?? []);
        if ($status >= 200 && $status < 300) return $raw;
        Log::warn('HTTP ' . $status . ' em ' . self::logUrl($url));
        return null;
    }

    private static function status(array $headers): int
    {
        if (!$headers) return 0;
        return preg_match('#\\s(\\d{3})\\s#', (string)$headers[0], $match) ? (int)$match[1] : 0;
    }

    private static function logUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) return '[url-invalida]';
        $scheme = (string)($parts['scheme'] ?? 'https');
        $host = (string)($parts['host'] ?? 'desconhecido');
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = (string)($parts['path'] ?? '/');
        return $scheme . '://' . $host . $port . $path;
    }
}
