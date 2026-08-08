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
            $rows = q(
                "SELECT kind,title,amount_cents,happened_at,account_name,extra_name FROM (
     SELECT 'revenue' kind, r.title title, r.amount_cents amount_cents, r.received_at happened_at, COALESCE(a.name,'sem conta') account_name, NULL extra_name
     FROM pi_financial_revenues r LEFT JOIN pi_financial_accounts a ON a.id=r.account_id AND a.clinic_id=r.clinic_id
     WHERE r.clinic_id=? AND r.status='efetivada' AND r.received_at IS NOT NULL AND r.received_at>=?
     UNION ALL
     SELECT 'expense' kind, e.title title, e.amount_cents amount_cents, e.paid_at happened_at, COALESCE(a.name,'sem conta') account_name, COALESCE(p.full_name,'sem credor') extra_name
     FROM pi_financial_expenses e LEFT JOIN pi_financial_accounts a ON a.id=e.account_id AND a.clinic_id=e.clinic_id LEFT JOIN pi_financial_counterparties fc ON fc.id=e.counterparty_id AND fc.clinic_id=e.clinic_id LEFT JOIN pi_persons p ON p.id=fc.person_id
     WHERE e.clinic_id=? AND e.status='paga' AND e.paid_at IS NOT NULL AND e.paid_at>=?
     UNION ALL
     SELECT 'transfer' kind, 'Transferência entre contas' title, t.amount_cents amount_cents, t.transfer_at happened_at, COALESCE(af.name,'origem') account_name, COALESCE(atc.name,'destino') extra_name
     FROM pi_financial_transfers t LEFT JOIN pi_financial_accounts af ON af.id=t.account_from_id AND af.clinic_id=t.clinic_id LEFT JOIN pi_financial_accounts atc ON atc.id=t.account_to_id AND atc.clinic_id=t.clinic_id
     WHERE t.clinic_id=? AND t.transfer_at>=?
     ) ops ORDER BY happened_at DESC LIMIT 20",
                [$cid, $from, $cid, $from, $cid, $from],
            )->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo finance timeline] " . $e->getMessage());
            return '<div class="empty">Não foi possível carregar a timeline financeira.</div>';
        }
        $items = [];
        foreach ($rows as $r) {
            $kind = (string) $r["kind"];
            $amount = money_br((int) $r["amount_cents"]);
            $account = (string) ($r["account_name"] ?? "");
            $extra = (string) ($r["extra_name"] ?? "");
            if ($kind === "revenue") {
                $items[] = [
                    "icon" => "add_card",
                    "time" => dt_br($r["happened_at"]),
                    "title" => "Receita recebida",
                    "body" => (string) $r["title"],
                    "meta" => $amount . " · " . $account,
                    "class" => "finance-op revenue",
                ];
            } elseif ($kind === "expense") {
                $items[] = [
                    "icon" => "receipt_long",
                    "time" => dt_br($r["happened_at"]),
                    "title" => "Despesa paga",
                    "body" => (string) $r["title"],
                    "meta" => $amount . " · " . $extra . " · " . $account,
                    "class" => "finance-op expense",
                ];
            } else {
                $items[] = [
                    "icon" => "sync_alt",
                    "time" => dt_br($r["happened_at"]),
                    "title" => "Transferência de saldo",
                    "body" => $account . " → " . $extra,
                    "meta" => $amount . " · não altera receita nem despesa",
                    "class" => "finance-op transfer",
                ];
            }
        }
        return timeline($items, "Nenhuma operação efetivada nos últimos 7 dias.");
    
    }

    public static function financial_today(int $cid = 0): string
    
    {
    
        return app_today_in_timezone($cid);
    
    }

    public static function financial_ensure_admin_safe(int $cid, int $uid = 0): int
    
    {
    
        if ($cid <= 0) {
            return 0;
        }
        financial_operational_schema_ready();
        $id =
            (int) (val(
                "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='admin_safe' AND active=1 ORDER BY id ASC LIMIT 1",
                [$cid],
            ) ?:
            0);
        if ($id > 0) {
            return $id;
        }
        if (clinic_read_only_db($cid)) {
            return 0;
        }
        q(
            "INSERT INTO pi_financial_locations (clinic_id,location_type,name,user_id,account_id,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())",
            [$cid, "admin_safe", "Cofre do Consultório", null, null, $uid ?: null],
        );
        return db_last_insert_id();
    
    }

    public static function financial_ensure_cashier_location(int $cid, int $uid): int
    
    {
    
        return financial_cashier_location_for_user($cid, $uid);
    
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
        financial_operational_schema_ready();
        $id =
            (int) (val(
                "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='bank_account' AND account_id=? AND active=1 ORDER BY id ASC LIMIT 1",
                [$cid, $accountId],
            ) ?:
            0);
        if ($id > 0) {
            return $id;
        }
        $acc = one(
            "SELECT id,name FROM pi_financial_accounts WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
            [$accountId, $cid],
        );
        if (!$acc) {
            return 0;
        }
        q(
            "INSERT INTO pi_financial_locations (clinic_id,location_type,name,user_id,account_id,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())",
            [
                $cid,
                "bank_account",
                (string) $acc["name"],
                null,
                $accountId,
                $uid ?: null,
            ],
        );
        return db_last_insert_id();
    
    }

    public static function financial_cashier_user_options(int $cid, bool $withEmpty = true): array
    
    {
    
        $out = $withEmpty ? ["" => "Escolha o colaborador"] : [];
        if ($cid <= 0) {
            return $out;
        }
        $rows = q(
            "SELECT DISTINCT u.id,u.name FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.clinic_id=? AND ur.active=1 AND ur.role_code IN ('recepcionista') ORDER BY u.name LIMIT 300",
            [$cid],
        )->fetchAll();
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
        $rows = q(
            "SELECT id,name FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND active=1 ORDER BY name,id LIMIT 200",
            [$cid],
        )->fetchAll();
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
        $rows = q(
            "SELECT l.id,l.name FROM pi_financial_locations l JOIN pi_financial_location_users lu ON lu.location_id=l.id AND lu.clinic_id=l.clinic_id AND lu.user_id=? AND lu.active=1 WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,l.id LIMIT 20",
            [$uid, $cid],
        )->fetchAll();
        if ($rows) {
            return $rows;
        }
        return q(
            "SELECT id,name FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND user_id=? AND active=1 ORDER BY id ASC LIMIT 20",
            [$cid, $uid],
        )->fetchAll();
    
    }

    public static function financial_cashier_location_for_user(int $cid, int $uid): int
    
    {
    
        $rows = financial_cashier_assigned_locations($cid, $uid);
        return $rows ? (int) $rows[0]["id"] : 0;
    
    }

    public static function financial_cashier_drawer_name(int $cid, int $uid): string
    
    {
    
        $rows = financial_cashier_assigned_locations($cid, $uid);
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
            (int) (val(
                "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND name=? AND active=1 LIMIT 1",
                [$cid, $name],
            ) ?:
            0);
        if ($exists > 0) {
            return $exists;
        }
        q(
            "INSERT INTO pi_financial_locations (clinic_id,location_type,name,user_id,account_id,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())",
            [$cid, "pos", $name, null, null, $uid ?: null],
        );
        $id = db_last_insert_id();
        audit("gaveta_criada", "financeiro", $id, [
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
        $drawer = one(
            "SELECT id,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND location_type='pos' AND active=1 LIMIT 1",
            [$drawerId, $cid],
        );
        if (!$drawer) {
            throw new RuntimeException("Gaveta não encontrada.");
        }
        $duplicate =
            (int) (val(
                "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND active=1 AND name=? AND id<>? LIMIT 1",
                [$cid, $name, $drawerId],
            ) ?:
            0);
        if ($duplicate > 0) {
            throw new RuntimeException("Já existe uma Gaveta ativa com este nome.");
        }
        if ($name === (string) $drawer["name"]) {
            return;
        }
        q(
            "UPDATE pi_financial_locations SET name=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'",
            [$name, $drawerId, $cid],
        );
        audit("gaveta_renomeada", "financeiro", $drawerId, [
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
        if (!financial_location_belongs($cid, $drawerId)) {
            throw new RuntimeException("Gaveta inválida.");
        }
        $loc = one(
            "SELECT id,location_type,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
            [$drawerId, $cid],
        );
        if (!$loc || (string) $loc["location_type"] !== "pos") {
            throw new RuntimeException("Escolha uma Gaveta válida.");
        }
        if (!clinic_user_exists($cid, $cashierUid, financial_cashier_roles())) {
            throw new RuntimeException(
                "Escolha um colaborador ativo do Atendimento.",
            );
        }
        db_tx(function () use ($cid, $drawerId, $cashierUid, $adminUid): void {
    
            q(
                "UPDATE pi_financial_location_users SET active=0, updated_at=NOW() WHERE clinic_id=? AND user_id=? AND active=1",
                [$cid, $cashierUid],
            );
            q(
                "INSERT INTO pi_financial_location_users (clinic_id,location_id,user_id,active,created_by,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE active=1, updated_at=NOW(), created_by=VALUES(created_by)",
                [$cid, $drawerId, $cashierUid, 1, $adminUid ?: null],
            );
        });
        audit("gaveta_vinculada", "financeiro", $drawerId, [
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
        $link = one(
            "SELECT * FROM pi_financial_location_users WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
            [$linkId, $cid],
        );
        if (!$link) {
            throw new RuntimeException("Vínculo não encontrado.");
        }
        q(
            "UPDATE pi_financial_location_users SET active=0, updated_at=NOW() WHERE id=? AND clinic_id=?",
            [$linkId, $cid],
        );
        audit("gaveta_desvinculada", "financeiro", (int) $link["location_id"], [
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
        $open = one(
            "SELECT s.id,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id=? AND s.status='open' LIMIT 1",
            [$cid, $drawerId],
        );
        if ($open) {
            throw new RuntimeException(
                "Esta Gaveta está aberta por " .
                    first_name((string) ($open["user_name"] ?? "Atendimento")) .
                    ". Feche o caixa antes de desativar.",
            );
        }
        q(
            "UPDATE pi_financial_locations SET active=0, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'",
            [$drawerId, $cid],
        );
        q(
            "UPDATE pi_financial_location_users SET active=0, updated_at=NOW() WHERE clinic_id=? AND location_id=?",
            [$cid, $drawerId],
        );
        audit("gaveta_desativada", "financeiro", $drawerId, [
            "audit_body" => "Administrativo desativou Gaveta do Atendimento.",
        ]);
    
    }

    public static function financial_drawer_row(int $cid, int $drawerId): ?array
    
    {
    
        if ($cid <= 0 || $drawerId <= 0) {
            return null;
        }
        return one(
            "SELECT * FROM pi_financial_locations WHERE id=? AND clinic_id=? AND location_type='pos' AND active=1 LIMIT 1",
            [$drawerId, $cid],
        );
    
    }

    public static function financial_drawer_auto_unlock_if_due(int $cid, int $drawerId): ?array
    
    {
    
        $d = financial_drawer_row($cid, $drawerId);
        if (!$d) {
            return null;
        }
        return financial_drawer_auto_unlock_row_if_due($cid, $d);
    
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
            $unlock = app_parse_db_utc($unlockAt);
            $now = app_now_utc();
            $lockedDay = (string) ($d["drawer_locked_business_date"] ?? "");
            $today = financial_today($cid);
            if (
                $unlock &&
                $unlock <= $now &&
                ($lockedDay === "" || $today > $lockedDay)
            ) {
                q(
                    "UPDATE pi_financial_locations SET drawer_lock_status='unlocked', drawer_unlocked_at=NOW(), updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'",
                    [$drawerId, $cid],
                );
                audit(
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
                $d["drawer_unlocked_at"] = app_now_utc()->format(
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
            return "Trancada até " . dt_br($unlock);
        }
        return "Trancada para conferência";
    
    }

    public static function financial_drawer_guard_can_use(int $cid, int $drawerId): void
    
    {
    
        $d = financial_drawer_auto_unlock_if_due($cid, $drawerId);
        if (!$d) {
            throw new RuntimeException("Gaveta inválida.");
        }
        if ((string) ($d["drawer_lock_status"] ?? "unlocked") === "locked") {
            $lockedDay = (string) ($d["drawer_locked_business_date"] ?? "");
            $unlock = mb_trim((string) ($d["drawer_unlock_at"] ?? ""));
            if ($unlock !== "") {
                throw new RuntimeException(
                    "Esta Gaveta está trancada para conferência da Gerência até " .
                        dt_br($unlock) .
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
            $drawer = financial_drawer_row($cid, $drawerId);
            $name = $drawer ? (string) $drawer["name"] : "Gaveta";
            q(
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                [
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
                ],
            );
            if (function_exists("counter_inc")) {
                counter_inc("notices_total");
            }
            if (function_exists("clinic_metric_inc")) {
                clinic_metric_inc($cid, "notices");
            }
        } catch (Throwable $e) {
            error_log("[Prontoo gaveta aviso] " . $e->getMessage());
        }
    
    }
}
