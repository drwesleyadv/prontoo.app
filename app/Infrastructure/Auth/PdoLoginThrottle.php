<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Auth;

use RuntimeException;

final class PdoLoginThrottle
{
    private function __construct()
    {
    }

    public static function waitSeconds(array $buckets): int
    {
        [$pair, $subject, $ip] = self::buckets($buckets);
        $now = time();
        $row = one(
            "SELECT MAX(GREATEST(0,locked_until-?)) AS wait_seconds FROM pi_login_locks WHERE (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?)",
            [
                $now,
                $pair[0],
                $pair[1],
                $subject[0],
                $subject[1],
                $ip[0],
                $ip[1],
            ],
        );
        return $row ? max(0, (int) ($row['wait_seconds'] ?? 0)) : 0;
    }

    public static function registerFailure(array $buckets): int
    {
        [$pair, $subject, $ip] = self::buckets($buckets);
        q(
            "INSERT INTO pi_login_locks (subject_hash,ip_hash,fail_count,locked_until,updated_at)
             VALUES
               (?,?,1,UNIX_TIMESTAMP()+2,NOW()),
               (?,?,1,0,NOW()),
               (?,?,1,0,NOW())
             ON DUPLICATE KEY UPDATE
               locked_until=CASE
                 WHEN subject_hash=? AND ip_hash=? THEN
                   UNIX_TIMESTAMP()+CAST(
                     LEAST(900,2*POW(2,LEAST(10,GREATEST(0,fail_count))))
                     AS UNSIGNED
                   )
                 WHEN subject_hash=? AND ip_hash=? THEN
                   CASE
                     WHEN fail_count+1>=5 THEN UNIX_TIMESTAMP()+CAST(
                       LEAST(86400,60*POW(2,LEAST(10,GREATEST(0,fail_count+1-5))))
                       AS UNSIGNED
                     )
                     ELSE COALESCE(locked_until,0)
                   END
                 ELSE
                   CASE
                     WHEN fail_count+1>=20 THEN UNIX_TIMESTAMP()+CAST(
                       LEAST(3600,60*POW(2,LEAST(10,GREATEST(0,fail_count+1-20))))
                       AS UNSIGNED
                     )
                     ELSE COALESCE(locked_until,0)
                   END
               END,
               fail_count=LEAST(100000,fail_count+1),
               updated_at=NOW()",
            [
                $pair[0],
                $pair[1],
                $subject[0],
                $subject[1],
                $ip[0],
                $ip[1],
                $pair[0],
                $pair[1],
                $subject[0],
                $subject[1],
            ],
        );
        return max(1, self::waitSeconds($buckets));
    }

    public static function clear(array $buckets): void
    {
        [$pair, $subject] = self::buckets($buckets);
        q(
            "DELETE FROM pi_login_locks WHERE (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?)",
            [$pair[0], $pair[1], $subject[0], $subject[1]],
        );
    }

    private static function buckets(array $buckets): array
    {
        if (count($buckets) !== 3) {
            throw new RuntimeException('Buckets de autenticação inválidos.');
        }
        foreach ($buckets as $bucket) {
            if (!is_array($bucket) || count($bucket) !== 2) {
                throw new RuntimeException('Bucket de autenticação inválido.');
            }
            foreach ($bucket as $hash) {
                if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                    throw new RuntimeException('Hash de autenticação inválido.');
                }
            }
        }
        return array_values($buckets);
    }
}
