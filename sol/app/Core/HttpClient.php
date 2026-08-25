<?php
declare(strict_types=1);
namespace Pro\Core;

final class HttpClient
{
    public static function get(string $url, int $timeout = 20, int $attempts = 3): ?string
    {
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($attempt > 0) usleep(120000 * $attempt);
            $result = self::request('GET', $url, null, $timeout);
            if ($result !== null) return $result;
        }
        return null;
    }

    public static function postJson(string $url, array $payload, int $timeout = 15): ?array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) return null;
        $raw = self::request('POST', $url, $body, $timeout);
        if ($raw === null) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function request(string $method, string $url, ?string $body, int $timeout): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) return null;
            $headers = ['Accept: application/json', 'User-Agent: sol-dashboard/2.0'];
            if ($body !== null) $headers[] = 'Content-Type: application/json';
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
            Log::warn('HTTP ' . $status . ' em ' . $url);
            return null;
        }

        $headers = "Accept: application/json\r\nUser-Agent: sol-dashboard/2.0\r\n";
        if ($body !== null) $headers .= "Content-Type: application/json\r\n";
        $context = stream_context_create(['http' => [
            'method' => $method,
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => $headers,
            'content' => $body ?? '',
        ]]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) return null;
        $status = self::status($http_response_header ?? []);
        if ($status >= 200 && $status < 300) return $raw;
        Log::warn('HTTP ' . $status . ' em ' . $url);
        return null;
    }

    private static function status(array $headers): int
    {
        if (!$headers) return 0;
        return preg_match('#\\s(\\d{3})\\s#', (string)$headers[0], $match) ? (int)$match[1] : 0;
    }
}
