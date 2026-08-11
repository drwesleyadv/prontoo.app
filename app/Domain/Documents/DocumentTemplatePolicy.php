<?php
declare(strict_types=1);

namespace Prontoo\Domain\Documents;

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

final class DocumentTemplatePolicy
{
    private function __construct()
    {
    }

    public static function document_template_visibility(array $c): array
    
    {
    
        $role = (string) ($c["role"] ?? "");
        $uid = (int) ($c["user"]["id"] ?? 0);
        if ($role === "gerente") {
            return ["mode" => "manager", "parameters" => []];
        }
        if ($role === "medico") {
            return [
                "mode" => "doctor",
                "parameters" => [$uid],
            ];
        }
        return ["mode" => "role", "parameters" => [$uid, $role]];
    
    }

    public static function can_edit_document_template(array $c, array $tpl): bool
    
    {
    
        $role = (string) ($c["role"] ?? "");
        if ($role === "gerente") {
            return true;
        }
        return (string) ($tpl["owner_role"] ?? "") === $role;
    
    }

    public static function document_can_issue_template(array $c, array $tpl): bool
    
    {
    
        $role = (string) ($c["role"] ?? "");
        $type = (string) ($tpl["type_key"] ?? "");
        $ownerRole = (string) ($tpl["owner_role"] ?? "");
        if (!in_array($type, \Prontoo\Domain\Documents\DocumentTypePolicy::document_allowed_types_for_role($role), true)) {
            return false;
        }
        if ($role === "gerente") {
            return $ownerRole === "gerente" ||
                in_array($type, \Prontoo\Domain\Documents\DocumentTypePolicy::document_allowed_types_for_role("gerente"), true);
        }
        if ($role === "medico") {
            return $ownerRole === "medico" ||
                (int) ($tpl["owner_user_id"] ?? 0) ===
                    (int) ($c["user"]["id"] ?? 0);
        }
        return $ownerRole === $role ||
            (int) ($tpl["owner_user_id"] ?? 0) === (int) ($c["user"]["id"] ?? 0);
    
    }

}
