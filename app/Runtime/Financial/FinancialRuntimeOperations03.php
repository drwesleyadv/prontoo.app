<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Financial;

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

final class FinancialRuntimeOperations03
{
    private function __construct()
    {
    }

    public static function financial_recent_operations_timeline(int $cid): string
    
    {
    
        $from = date("Y-m-d H:i:s", strtotime("-7 days"));
        try {
            $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.recent_operations_timeline.01", [$cid, $from, $cid, $from, $cid, $from], [])->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo finance timeline] " . $e->getMessage());
            return '<div class="empty">Não foi possível carregar a timeline financeira.</div>';
        }
        $items = [];
        foreach ($rows as $r) {
            $kind = (string) $r["kind"];
            $amount = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br((int) $r["amount_cents"]);
            $account = (string) ($r["account_name"] ?? "");
            $extra = (string) ($r["extra_name"] ?? "");
            if ($kind === "revenue") {
                $items[] = [
                    "icon" => "add_card",
                    "time" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["happened_at"]),
                    "title" => "Receita recebida",
                    "body" => (string) $r["title"],
                    "meta" => $amount . " · " . $account,
                    "class" => "finance-op revenue",
                ];
            } elseif ($kind === "expense") {
                $items[] = [
                    "icon" => "receipt_long",
                    "time" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["happened_at"]),
                    "title" => "Despesa paga",
                    "body" => (string) $r["title"],
                    "meta" => $amount . " · " . $extra . " · " . $account,
                    "class" => "finance-op expense",
                ];
            } else {
                $items[] = [
                    "icon" => "sync_alt",
                    "time" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["happened_at"]),
                    "title" => "Transferência de saldo",
                    "body" => $account . " → " . $extra,
                    "meta" => $amount . " · não altera receita nem despesa",
                    "class" => "finance-op transfer",
                ];
            }
        }
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($items, "Nenhuma operação efetivada nos últimos 7 dias.");
    
    }

    public static function financial_today(int $cid = 0): string
    
    {
    
        return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid);
    
    }

    public static function financial_ensure_admin_safe(int $cid, int $uid = 0): int
    
    {
    
        if ($cid <= 0) {
            return 0;
        }
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $id =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.03.ensure_admin_safe.01", [$cid], []) ?:
            0);
        if ($id > 0) {
            return $id;
        }
        if (\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_read_only_db($cid)) {
            return 0;
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.ensure_admin_safe.02", [$cid, "admin_safe", "Cofre do Consultório", null, null, $uid ?: null], []);
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->lastInsertId();
    
    }

    public static function financial_ensure_cashier_location(int $cid, int $uid): int
    
    {
    
        return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_location_for_user($cid, $uid);
    
    }

    public static function financial_ensure_bank_location(
        int $cid,
        int $accountId,
        int $uid = 0,
    ): int 
    {
    
        if ($cid <= 0 || $accountId <= 0) {
            return 0;
        }
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $id =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.03.ensure_bank_location.01", [$cid, $accountId], []) ?:
            0);
        if ($id > 0) {
            return $id;
        }
        $acc = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.03.ensure_bank_location.02", [$accountId, $cid], []);
        if (!$acc) {
            return 0;
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.ensure_bank_location.03", [
                $cid,
                "bank_account",
                (string) $acc["name"],
                null,
                $accountId,
                $uid ?: null,
            ], []);
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->lastInsertId();
    
    }

    public static function financial_cashier_user_options(int $cid, bool $withEmpty = true): array
    
    {
    
        $out = $withEmpty ? ["" => "Escolha o colaborador"] : [];
        if ($cid <= 0) {
            return $out;
        }
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.cashier_user_options.01", [$cid], [])->fetchAll();
        foreach ($rows as $r) {
            $out[(int) $r["id"]] = (string) $r["name"];
        }
        return $out;
    
    }

    public static function financial_drawer_location_options(
        int $cid,
        bool $withEmpty = true,
    ): array 
    {
    
        $out = $withEmpty ? ["" => "Escolha a gaveta"] : [];
        if ($cid <= 0) {
            return $out;
        }
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.drawer_location_options.01", [$cid], [])->fetchAll();
        foreach ($rows as $r) {
            $out[(int) $r["id"]] = (string) $r["name"];
        }
        return $out;
    
    }

    public static function financial_cashier_assigned_locations(int $cid, int $uid): array
    
    {
    
        if ($cid <= 0 || $uid <= 0) {
            return [];
        }
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.cashier_assigned_locations.01", [$uid, $cid], [])->fetchAll();
        if ($rows) {
            return $rows;
        }
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.cashier_assigned_locations.02", [$cid, $uid], [])->fetchAll();
    
    }

    public static function financial_cashier_location_for_user(int $cid, int $uid): int
    
    {
    
        $rows = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_assigned_locations($cid, $uid);
        return $rows ? (int) $rows[0]["id"] : 0;
    
    }

    public static function financial_cashier_drawer_name(int $cid, int $uid): string
    
    {
    
        $rows = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_cashier_assigned_locations($cid, $uid);
        return $rows ? (string) $rows[0]["name"] : "";
    
    }

    public static function financial_create_drawer(int $cid, int $uid, string $name): int
    
    {
    
        $name = trim($name);
        if ($cid <= 0) {
            throw new RuntimeException("Consultório inválido.");
        }
        if ($name === "") {
            throw new RuntimeException("Informe o nome da Gaveta.");
        }
        if (mb_strlen($name) > 120) {
            $name = mb_substr($name, 0, 120);
        }
        $exists =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.03.create_drawer.01", [$cid, $name], []) ?:
            0);
        if ($exists > 0) {
            return $exists;
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.create_drawer.02", [$cid, "pos", $name, null, null, $uid ?: null], []);
        $id = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->lastInsertId();
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("gaveta_criada", "financeiro", $id, [
            "nome" => $name,
            "audit_body" =>
                "Administrativo criou Gaveta para uso do Caixa do Atendimento.",
        ]);
        return $id;
    
    }

    public static function financial_rename_drawer(
        int $cid,
        int $drawerId,
        int $adminUid,
        string $name,
    ): void 
    {
    
        $name = trim($name);
        if ($cid <= 0 || $drawerId <= 0) {
            throw new RuntimeException("Gaveta inválida.");
        }
        if ($name === "") {
            throw new RuntimeException("Informe o novo nome da Gaveta.");
        }
        if (mb_strlen($name) > 120) {
            $name = mb_substr($name, 0, 120);
        }
        $drawer = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.03.rename_drawer.01", [$drawerId, $cid], []);
        if (!$drawer) {
            throw new RuntimeException("Gaveta não encontrada.");
        }
        $duplicate =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.03.rename_drawer.02", [$cid, $name, $drawerId], []) ?:
            0);
        if ($duplicate > 0) {
            throw new RuntimeException("Já existe uma Gaveta ativa com este nome.");
        }
        if ($name === (string) $drawer["name"]) {
            return;
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.rename_drawer.03", [$name, $drawerId, $cid], []);
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("gaveta_renomeada", "financeiro", $drawerId, [
            "nome_anterior" => (string) $drawer["name"],
            "nome_novo" => $name,
            "audit_body" =>
                "Administrativo renomeou Gaveta do Caixa do Atendimento.",
        ]);
    
    }

    public static function financial_link_drawer_user(
        int $cid,
        int $drawerId,
        int $cashierUid,
        int $adminUid,
    ): void 
    {
    
        if ($cid <= 0 || $drawerId <= 0 || $cashierUid <= 0) {
            throw new RuntimeException(
                "Escolha a Gaveta e o colaborador do Atendimento.",
            );
        }
        if (!\Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_location_belongs($cid, $drawerId)) {
            throw new RuntimeException("Gaveta inválida.");
        }
        $loc = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.03.link_drawer_user.01", [$drawerId, $cid], []);
        if (!$loc || (string) $loc["location_type"] !== "pos") {
            throw new RuntimeException("Escolha uma Gaveta válida.");
        }
        if (!\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_user_exists($cid, $cashierUid, \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_cashier_roles())) {
            throw new RuntimeException(
                "Escolha um colaborador ativo do Atendimento.",
            );
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->atomic(function () use ($cid, $drawerId, $cashierUid, $adminUid): void {
    
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.link_drawer_user.02", [$cid, $cashierUid], []);
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.link_drawer_user.03", [$cid, $drawerId, $cashierUid, 1, $adminUid ?: null], []);
        });
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("gaveta_vinculada", "financeiro", $drawerId, [
            "colaborador" => $cashierUid,
            "audit_body" =>
                "Administrativo vinculou colaborador do Atendimento à Gaveta.",
        ]);
    
    }

    public static function financial_unlink_drawer_user(
        int $cid,
        int $linkId,
        int $adminUid,
    ): void 
    {
    
        if ($cid <= 0 || $linkId <= 0) {
            throw new RuntimeException("Vínculo inválido.");
        }
        $link = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.03.unlink_drawer_user.01", [$linkId, $cid], []);
        if (!$link) {
            throw new RuntimeException("Vínculo não encontrado.");
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.unlink_drawer_user.02", [$linkId, $cid], []);
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("gaveta_desvinculada", "financeiro", (int) $link["location_id"], [
            "colaborador" => (int) $link["user_id"],
            "audit_body" =>
                "Administrativo removeu vínculo de colaborador com Gaveta.",
        ]);
    
    }

    public static function financial_deactivate_drawer(
        int $cid,
        int $drawerId,
        int $adminUid,
    ): void 
    {
    
        if ($cid <= 0 || $drawerId <= 0) {
            throw new RuntimeException("Gaveta inválida.");
        }
        $open = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.03.deactivate_drawer.01", [$cid, $drawerId], []);
        if ($open) {
            throw new RuntimeException(
                "Esta Gaveta está aberta por " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name((string) ($open["user_name"] ?? "Atendimento")) .
                    ". Feche o caixa antes de desativar.",
            );
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.deactivate_drawer.02", [$drawerId, $cid], []);
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.deactivate_drawer.03", [$cid, $drawerId], []);
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("gaveta_desativada", "financeiro", $drawerId, [
            "audit_body" => "Administrativo desativou Gaveta do Atendimento.",
        ]);
    
    }

    public static function financial_drawer_row(int $cid, int $drawerId): ?array
    
    {
    
        if ($cid <= 0 || $drawerId <= 0) {
            return null;
        }
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.03.drawer_row.01", [$drawerId, $cid], []);
    
    }

    public static function financial_drawer_auto_unlock_if_due(int $cid, int $drawerId): ?array
    
    {
    
        $d = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_row($cid, $drawerId);
        if (!$d) {
            return null;
        }
        return \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_auto_unlock_row_if_due($cid, $d);
    
    }

    public static function financial_drawer_auto_unlock_row_if_due(int $cid, array $d): array
    
    {
    
        $drawerId = (int) ($d["id"] ?? 0);
        if ($cid <= 0 || $drawerId <= 0) {
            return $d;
        }
        $status = (string) ($d["drawer_lock_status"] ?? "unlocked");
        $unlockAt = mb_trim((string) ($d["drawer_unlock_at"] ?? ""));
        if ($status === "locked" && $unlockAt !== "") {
            $unlock = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_parse_db_utc($unlockAt);
            $now = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_now_utc();
            $lockedDay = (string) ($d["drawer_locked_business_date"] ?? "");
            $today = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
            if (
                $unlock &&
                $unlock <= $now &&
                ($lockedDay === "" || $today > $lockedDay)
            ) {
                \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.drawer_auto_unlock_row_if_due.01", [$drawerId, $cid], []);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                    "gaveta_destrancada_automaticamente",
                    "financeiro",
                    $drawerId,
                    [
                        "locked_business_date" => $lockedDay,
                        "unlock_at" => $unlockAt,
                        "audit_body" =>
                            "Gaveta destrancada automaticamente após horário definido pela Gerência.",
                    ],
                );
                $d["drawer_lock_status"] = "unlocked";
                $d["drawer_unlocked_at"] = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_now_utc()->format(
                    "Y-m-d H:i:s",
                );
            }
        }
        return $d;
    
    }

    public static function financial_drawer_lock_label(array $drawer, int $cid): string
    
    {
    
        $status = (string) ($drawer["drawer_lock_status"] ?? "unlocked");
        if ($status !== "locked") {
            return "Destrancada";
        }
        $unlock = mb_trim((string) ($drawer["drawer_unlock_at"] ?? ""));
        if ($unlock !== "") {
            return "Trancada até " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($unlock);
        }
        return "Trancada para conferência";
    
    }

    public static function financial_drawer_guard_can_use(int $cid, int $drawerId): void
    
    {
    
        $d = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_auto_unlock_if_due($cid, $drawerId);
        if (!$d) {
            throw new RuntimeException("Gaveta inválida.");
        }
        if ((string) ($d["drawer_lock_status"] ?? "unlocked") === "locked") {
            $lockedDay = (string) ($d["drawer_locked_business_date"] ?? "");
            $unlock = mb_trim((string) ($d["drawer_unlock_at"] ?? ""));
            if ($unlock !== "") {
                throw new RuntimeException(
                    "Esta Gaveta está trancada para conferência da Gerência até " .
                        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($unlock) .
                        ". Somente depois deste horário ela poderá ser aberta novamente.",
                );
            }
            throw new RuntimeException(
                "Esta Gaveta está trancada para conferência da Gerência. Aguarde a conferência e o agendamento do destravamento.",
            );
        }
    
    }

    public static function financial_notify_drawer_locked(
        int $cid,
        int $drawerId,
        int $sessionId,
        int $uid,
    ): void 
    {
    
        try {
            $drawer = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_row($cid, $drawerId);
            $name = $drawer ? (string) $drawer["name"] : "Gaveta";
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.03.notify_drawer_locked.01", [
                    $cid,
                    "Gaveta trancada para conferência",
                    "A Gaveta " .
                    $name .
                    " foi trancada após o fechamento do último caixa. Confira os fechamentos, confirme a destinação das retiradas e escolha o horário de destravamento para o dia seguinte.",
                    1,
                    "role",
                    "gerente",
                    null,
                    $uid ?: null,
                ], []);
            if (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'counter_inc'])) {
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("notices_total");
            }
            if (is_callable([\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::class, 'clinic_metric_inc'])) {
                \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "notices");
            }
        } catch (Throwable $e) {
            error_log("[Prontoo gaveta aviso] " . $e->getMessage());
        }
    
    }
}
