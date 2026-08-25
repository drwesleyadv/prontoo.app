<?php
declare(strict_types=1);
namespace Pro\Core;

final class HttpClient
{
    public static function get(string $url, int $timeout = 25, int $attempts = 3): ?string
    {
        for ($i=0; $i<$attempts; $i++) {
            if ($i) usleep(200000 * $i);
            $ctx = stream_context_create(['http'=>['timeout'=>$timeout,'ignore_errors'=>true,'header'=>'User-Agent: pro-scientific-panel/1.0']]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) continue;
            $status = self::status($http_response_header ?? []);
            if ($status >= 200 && $status < 300) return $raw;
            Log::warn("HTTP $status em $url");
        }
        return null;
    }

    public static function postJson(string $url, array $payload, int $timeout = 20): ?array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $ctx = stream_context_create(['http'=>['method'=>'POST','timeout'=>$timeout,'ignore_errors'=>true,'header'=>"Content-Type: application/json\r\n",'content'=>$body]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function status(array $headers): int
    {
        if (!$headers) return 0;
        return preg_match('#\s(\d{3})\s#', $headers[0], $m) ? (int)$m[1] : 0;
    }
}
