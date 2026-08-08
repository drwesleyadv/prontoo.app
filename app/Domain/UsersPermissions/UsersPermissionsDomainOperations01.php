<?php
declare(strict_types=1);

namespace Prontoo\Domain\UsersPermissions;

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

final class UsersPermissionsDomainOperations01
{
    private function __construct()
    {
    }

    public static function single_active_role_cleanup_for_user(int $uid, ?int $cid = null): void
    
    {
    
        return;
    
    }

    public static function clinic_minimum_roles_violation_message(): string
    
    {
    
        return "Esta alteração não é possível: todo consultório precisa manter pelo menos um Profissional e um Administrativo ativos.";
    
    }

    public static function permission_operations(): array
    
    {
    
        return [
            "view" => "Visualizar",
            "add" => "Adicionar",
            "edit" => "Alterar",
            "delete" => "Excluir",
        ];
    
    }

    public static function permission_default_ops_for_role(string $role, string $module): array
    
    {
    
        $isManager = $role === "gerente";
        return match ($module) {
            "painel" => ["view" => true],
            "leads" => ["view" => true, "add" => true, "edit" => true],
            "appointments" => [
                "view" => true,
                "add" => true,
                "edit" => true,
                "delete" => in_array(
                    $role,
                    ["recepcionista", "medico", "gerente"],
                    true,
                ),
            ],
            "operations" => ["view" => $isManager],
            "financial" => [
                "view" => $isManager,
                "add" => $isManager,
                "edit" => $isManager,
            ],
            "procedures" => [
                "view" => $isManager,
                "add" => $isManager,
                "edit" => $isManager,
            ],
            "patients" => [
                "view" => true,
                "add" => true,
                "edit" => true,
                "delete" => in_array($role, ["medico", "gerente"], true),
            ],
            "tasks" => ["view" => true, "add" => true, "edit" => true],
            "documents" => ["view" => true, "add" => true, "edit" => true],
            "notices" => ["view" => true, "add" => true],
            "users" => [
                "view" => $isManager,
                "add" => $isManager,
                "edit" => $isManager,
                "delete" => $isManager,
            ],
            "permissions" => ["view" => $isManager, "edit" => $isManager],
            "audit" => ["view" => true],
            "settings" => [
                "view" => in_array($role, ["medico", "gerente"], true),
                "edit" => in_array($role, ["medico", "gerente"], true),
            ],
            default => ["view" => false],
        };
    
    }
}
