<?php
declare(strict_types=1);

namespace Prontoo\Runtime\UsersPermissions;

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

final class UsersPermissionsRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function collaborator_permission_matrix_base(string $role, int $cid): array
    
    {
    
        $requestKey = $cid . "|" . $role;
        if (
            !isset($GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"]) ||
            !is_array($GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"])
        ) {
            $GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"] = [];
        }
        if (
            array_key_exists(
                $requestKey,
                $GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"],
            )
        ) {
            return $GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"][$requestKey];
        }
        $allowed = [];
        foreach (
            q(
                "SELECT action_key,allowed FROM pi_permissions WHERE clinic_id=? AND role_code=?",
                [$cid, $role],
            )->fetchAll()
            as $r
        ) {
            $allowed[(string) $r["action_key"]] = (int) $r["allowed"] === 1;
        }
        $defs = permission_module_defs();
        $actions = actions();
        $out = [];
        foreach ($defs as $key => $m) {
            $roleCanSee = in_array($role, $actions[$key]["roles"] ?? [], true);
            $can =
                (!empty($allowed[$key]) && $roleCanSee) ||
                ($role === "gerente" && $roleCanSee);
            $defaults = permission_default_ops_for_role($role, $key);
            $row = ["key" => $key, "label" => $m["label"], "icon" => $m["icon"]];
            foreach (permission_operations() as $op => $label) {
                $row[$op] =
                    $can &&
                    permission_operation_supported($key, $op) &&
                    !empty($defaults[$op]);
            }
            $out[] = $row;
        }
        $GLOBALS["PRONTOO_PERMISSION_BASE_MATRIX_CACHE"][$requestKey] = $out;
        return $out;
    
    }

    public static function collaborator_permission_matrix(string $role, int $cid): array
    
    {
    
        $rules = permission_rules_for_role($role, $cid);
        $defs = permission_module_defs();
        $out = [];
        foreach ($defs as $key => $m) {
            $row = ["key" => $key, "label" => $m["label"], "icon" => $m["icon"]];
            foreach (permission_operations() as $op => $label) {
                $row[$op] =
                    permission_operation_supported($key, $op) &&
                    !empty($rules[$key][$op]);
            }
            $out[] = $row;
        }
        return $out;
    
    }

    public static function collaborator_permission_table(string $role, int $cid): string
    
    {
    
        return collaborator_permission_summary([$role], $cid);
    
    }

    public static function collaborator_permission_summary(array $roles, int $cid): string
    
    {
    
        $roles = array_values(
            array_unique(array_filter(array_map("strval", $roles))),
        );
        if (!$roles) {
            return '<div class="empty">Nenhum cargo ativo para calcular permissões.</div>';
        }
        $ops = permission_operations();
        $defs = permission_module_defs();
        $sources = [];
        foreach ($roles as $role) {
            foreach (collaborator_permission_matrix($role, $cid) as $row) {
                foreach ($ops as $op => $label) {
                    if (!empty($row[$op])) {
                        $sources[$row["key"]][$op][] = $role;
                    }
                }
            }
        }
        $h =
            '<div class="collab-permission-summary compact-permission-summary"><div class="permission-source-note compact"><strong>Permissões efetivas</strong><span>' .
            e(implode(", ", role_labels_from_codes($roles, $cid))) .
            '</span></div><div class="permission-module-grid compact">';
        foreach ($defs as $module => $meta) {
            $chips = "";
            foreach ($ops as $op => $label) {
                if (!permission_operation_supported($module, $op)) {
                    continue;
                }
                $grants = $sources[$module][$op] ?? [];
                if (!$grants) {
                    continue;
                }
                $chips .=
                    '<span class="permission-chip yes" title="Liberado por ' .
                    e(implode(", ", role_labels_from_codes($grants, $cid))) .
                    '">' .
                    e($label) .
                    "</span>";
            }
            if ($chips === "") {
                $chips = '<span class="permission-chip no">Sem acesso</span>';
            }
            $h .=
                '<article class="permission-module-card compact"><header>' .
                icon($meta["icon"]) .
                "<strong>" .
                e($meta["label"]) .
                '</strong></header><div class="permission-chip-list">' .
                $chips .
                "</div></article>";
        }
        return $h . "</div></div>";
    
    }
}
