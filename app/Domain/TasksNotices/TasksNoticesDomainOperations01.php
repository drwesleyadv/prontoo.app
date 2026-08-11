<?php
declare(strict_types=1);

namespace Prontoo\Domain\TasksNotices;

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

final class TasksNoticesDomainOperations01
{
    private function __construct()
    {
    }

    public static function notice_target_parameters(array $c): array
    
    {
    
        $role = (string) ($c["role"] ?? "");
        $uid = (int) ($c["user"]["id"] ?? 0);
        return [$role, $uid];
    
    }

    public static function workflow_notice_body(string $body, string $action = ""): string
    
    {
    
        $body = trim($body);
        $action = trim($action);
        if ($action !== "") {
            $body .= "\n\nPróximo passo: " . $action;
        }
        $body .=
            "\n\nEste é um recado automático do fluxo de cuidado. Arquive quando não precisar mais dele.";
        return $body;
    
    }
}
