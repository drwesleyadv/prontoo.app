<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Financial;

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

final class FinancialRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function counterparty_lookup_field(
        int $cid,
        string $hiddenName = "counterparty_id",
        string $hiddenValue = "",
        string $inputName = "counterparty_search",
    ): string 
    {
    
        $display = "";
        $id = (int) $hiddenValue;
        if ($id > 0) {
            $r = one(
                "SELECT p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.id=? AND fc.clinic_id=? AND fc.active=1 LIMIT 1",
                [$id, $cid],
            );
            if ($r) {
                $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
                $display =
                    (string) $r["full_name"] .
                    ($doc !== "" ? " · " . mask($doc) : "");
            }
        }
        return '<input type="hidden" name="' .
            e($hiddenName) .
            '" value="' .
            e($hiddenValue) .
            '" data-counterparty-id-target><input type="search" name="' .
            e($inputName) .
            '" value="' .
            e($display) .
            '" list="prontoo_counterparty_suggestions" placeholder="Digite nome, CPF ou CNPJ" autocomplete="off" spellcheck="false" required data-ds-lookup="counterparty" aria-label="Buscar pessoa, fornecedor ou credor" data-counterparty-document-suggest data-counterparty-suggest-url="' .
            e(href("counterparty_suggest")) .
            '" aria-autocomplete="list">';
    
    }

    public static function resolve_counterparty_lookup_id(
        int $cid,
        int $postedId,
        string $search = "",
    ): int 
    {
    
        if ($postedId > 0) {
            $ok = (int) val(
                "SELECT id FROM pi_financial_counterparties WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
                [$postedId, $cid],
            );
            if ($ok > 0) {
                return $ok;
            }
        }
        $search = trim($search);
        if ($search === "") {
            return 0;
        }
        $clean = mb_strtolower($search, "UTF-8");
        $digits = only_digits($search);
        $rows = q(
            "SELECT fc.id,p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.active=1 ORDER BY p.full_name ASC LIMIT 1000",
            [$cid],
        )->fetchAll();
        $exact = [];
        $nameExact = [];
        $starts = [];
        foreach ($rows as $r) {
            $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
            $masked = mask($doc);
            $display = mb_strtolower(
                (string) $r["full_name"] . ($masked !== "" ? " · " . $masked : ""),
                "UTF-8",
            );
            $name = mb_strtolower((string) $r["full_name"], "UTF-8");
            $docDigits = only_digits($doc);
            if (
                $display === $clean ||
                ($digits !== "" && $docDigits !== "" && $digits === $docDigits)
            ) {
                $exact[] = (int) $r["id"];
            }
            if ($name === $clean) {
                $nameExact[] = (int) $r["id"];
            }
            if ($search !== "" && str_starts_with($name, $clean)) {
                $starts[] = (int) $r["id"];
            }
        }
        if (count($exact) === 1) {
            return $exact[0];
        }
        if (count($nameExact) === 1) {
            return $nameExact[0];
        }
        if (count($starts) === 1) {
            return $starts[0];
        }
        return 0;
    
    }

    public static function page_counterparty_lookup(): void
    
    {
    
        $c = require_can("financial");
        $cid = (int) $c["clinic_id"];
        $doc = only_digits((string) ($_GET["doc"] ?? ($_GET["cpf"] ?? "")));
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        if (
            security_rate_limit(
                security_client_bucket("counterparty_lookup_" . $cid),
                40,
                300,
            )
        ) {
            http_response_code(429);
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => "Aguarde alguns instantes.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $isCpf = strlen($doc) === 11;
        $isCnpj = strlen($doc) === 14;
        if (
            ($isCpf && !valid_cpf($doc)) ||
            ($isCnpj && !valid_cnpj($doc)) ||
            (!$isCpf && !$isCnpj)
        ) {
            echo json_encode(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => "Informe CPF ou CNPJ válido.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $p = $isCpf
            ? one(
                "SELECT id,full_name,cpf,birth_date,legal_document FROM pi_persons WHERE cpf=? LIMIT 1",
                [$doc],
            )
            : one(
                "SELECT id,full_name,cpf,birth_date,legal_document FROM pi_persons WHERE legal_document=? LIMIT 1",
                [$doc],
            );
        if (!$p) {
            echo json_encode(
                [
                    "ok" => true,
                    "found" => false,
                    "message" => "Documento válido. Continue o cadastro do credor.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        $link = one(
            "SELECT id FROM pi_financial_counterparties WHERE clinic_id=? AND person_id=? AND kind='credor' AND active=1 LIMIT 1",
            [$cid, (int) $p["id"]],
        );
        if (!$link) {
            echo json_encode(
                [
                    "ok" => true,
                    "found" => false,
                    "message" => "Documento válido. Continue o cadastro do credor.",
                ],
                JSON_UNESCAPED_UNICODE,
            );
            return;
        }
        echo json_encode(
            [
                "ok" => true,
                "found" => true,
                "already_counterparty" => true,
                "counterparty_id" => (int) $link["id"],
                "message" =>
                    "Este credor já está cadastrado. Os dados foram recuperados.",
                "name" => (string) ($p["full_name"] ?? ""),
                "document" => mask(
                    (string) ($p["cpf"] ?? "" ?: $p["legal_document"] ?? ""),
                ),
                "birth_date" => app_date_input_from_storage($p["birth_date"] ?? ""),
            ],
            JSON_UNESCAPED_UNICODE,
        );
    
    }

    public static function page_counterparty_suggest(): void
    
    {
    
        $c = require_can("financial");
        $cid = (int) $c["clinic_id"];
        $q = mb_trim((string) ($_GET["q"] ?? ""));
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        if ($q === "") {
            echo json_encode(["ok" => true, "items" => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        $limit = max(1, min(80, (int) ($_GET["limit"] ?? 12)));
        $digits = only_digits($q);
        $params = [$cid];
        $where = "fc.clinic_id=? AND fc.active=1";
        if ($digits !== "" && mb_strlen($q, "UTF-8") >= 2) {
            $where .=
                " AND (p.full_name LIKE ? OR p.cpf LIKE ? OR p.legal_document LIKE ?)";
            $params[] = $q . "%";
            $params[] = "%" . $digits . "%";
            $params[] = "%" . $digits . "%";
        } else {
            $where .= " AND p.full_name LIKE ?";
            $params[] = $q . "%";
        }
        $rows = q(
            "SELECT fc.id,p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE $where ORDER BY p.full_name ASC, fc.id DESC LIMIT " .
                (int) $limit,
            $params,
        )->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
            $masked = mask($doc);
            $items[] = [
                "id" => (int) $r["id"],
                "name" => (string) $r["full_name"],
                "document" => $masked,
                "value" =>
                    (string) $r["full_name"] .
                    ($masked !== "" ? " · " . $masked : ""),
            ];
        }
        echo json_encode(["ok" => true, "items" => $items], JSON_UNESCAPED_UNICODE);
        return;
    
    }

    public static function counterparty_options(int $cid): array
    
    {
    
        $rows = q(
            "SELECT fc.id,p.full_name FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.active=1 ORDER BY p.full_name LIMIT 300",
            [$cid],
        )->fetchAll();
        $out = ["" => "Selecione o credor"];
        foreach ($rows as $r) {
            $out[(int) $r["id"]] = $r["full_name"];
        }
        return $out;
    
    }

    public static function financial_account_options(int $cid): array
    
    {
    
        $rows = q(
            "SELECT id,name FROM pi_financial_accounts WHERE clinic_id=? AND active=1 ORDER BY name LIMIT 200",
            [$cid],
        )->fetchAll();
        $out = ["" => "Caixa geral"];
        foreach ($rows as $r) {
            $out[(int) $r["id"]] = $r["name"];
        }
        return $out;
    
    }

    public static function financial_account_label_options(
        int $cid,
        string $empty = "Escolha a conta",
    ): array 
    {
    
        $rows = q(
            "SELECT id,name FROM pi_financial_accounts WHERE clinic_id=? AND active=1 ORDER BY FIELD(account_type,'caixa_interno','conta_corrente','conta_pagamento','conta_poupanca','investimento'), name LIMIT 200",
            [$cid],
        )->fetchAll();
        $out = ["" => $empty];
        foreach ($rows as $r) {
            $out[(int) $r["id"]] = $r["name"];
        }
        return $out;
    
    }

    public static function financial_ensure_default_accounts(int $cid, int $uid): void
    
    {
    
        if ($cid <= 0 || clinic_read_only_db($cid)) {
            return;
        }
        try {
            financial_operational_schema_ready();
            financial_ensure_admin_safe($cid, $uid);
            $cashId =
                (int) (val(
                    "SELECT id FROM pi_financial_accounts WHERE clinic_id=? AND account_type='caixa_interno' AND LOWER(name)=LOWER('Cofre do Consultório') LIMIT 1",
                    [$cid],
                ) ?:
                0);
            if ($cashId <= 0) {
                q(
                    "INSERT INTO pi_financial_accounts (clinic_id,name,bank_name,account_type,opening_balance_cents,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())",
                    [
                        $cid,
                        "Cofre do Consultório",
                        null,
                        "caixa_interno",
                        0,
                        $uid,
                    ],
                );
            }
        } catch (Throwable $e) {
            error_log("[Prontoo finance default accounts] " . $e->getMessage());
        }
    
    }

    public static function financial_account_belongs(int $cid, int $accountId): bool
    
    {
    
        return $accountId > 0 &&
            (int) (val(
                "SELECT id FROM pi_financial_accounts WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
                [$accountId, $cid],
            ) ?:
                0) > 0;
    
    }

    public static function financial_counterparty_light(
        int $cid,
        int $uid,
        string $name,
        string $doc = "",
        string $notes = "",
    ): int 
    {
    
        $name = trim(preg_split("/\s+·\s+/", trim($name), 2)[0] ?? $name);
        $doc = only_digits($doc);
        if ($name === "") {
            return 0;
        }
        $pid = 0;
        if ($doc !== "" && in_array(strlen($doc), [11, 14], true)) {
            try {
                $pid = save_person_by_document($name, $doc, null);
            } catch (Throwable $e) {
                $pid = 0;
            }
        }
        if ($pid <= 0) {
            $pid =
                (int) (val(
                    "SELECT p.id FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND LOWER(p.full_name)=LOWER(?) AND p.cpf IS NULL AND p.legal_document IS NULL ORDER BY fc.id ASC LIMIT 1",
                    [$cid, $name],
                ) ?:
                0);
            if ($pid > 0) {
                q(
                    "UPDATE pi_persons SET full_name=?, updated_at=NOW() WHERE id=?",
                    [$name, $pid],
                );
            } else {
                q(
                    "INSERT INTO pi_persons (full_name,cpf,birth_date,created_at) VALUES (?,NULL,NULL,NOW())",
                    [$name],
                );
                $pid = db_last_insert_id();
            }
        }
        if ($pid <= 0) {
            return 0;
        }
        q(
            "INSERT INTO pi_financial_counterparties (clinic_id,person_id,kind,notes,created_by,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE notes=COALESCE(NULLIF(VALUES(notes),''),notes), active=1, updated_at=NOW()",
            [$cid, $pid, "credor", $notes, $uid],
        );
        return (int) (val(
            "SELECT id FROM pi_financial_counterparties WHERE clinic_id=? AND person_id=? AND kind='credor' LIMIT 1",
            [$cid, $pid],
        ) ?:
        0);
    
    }

    public static function financial_counterparty_from_post(int $cid, int $uid): int
    
    {
    
        $id = resolve_counterparty_lookup_id(
            $cid,
            (int) ($_POST["counterparty_id"] ?? 0),
            posted_counterparty_search_value(),
        );
        if ($id > 0) {
            return $id;
        }
        return financial_counterparty_light(
            $cid,
            $uid,
            posted_counterparty_search_value(),
            (string) ($_POST["counterparty_doc"] ?? ""),
            (string) ($_POST["counterparty_notes"] ?? ""),
        );
    
    }

    public static function financial_account_balances(int $cid): array
    
    {
    
        ensure_financial_operational_schema();
        $rows = q(
            "SELECT id,name,account_type,bank_name,opening_balance_cents,active FROM pi_financial_accounts WHERE clinic_id=? ORDER BY active DESC, FIELD(account_type,'caixa_interno','conta_corrente','conta_pagamento','conta_poupanca','investimento'), name LIMIT 200",
            [$cid],
        )->fetchAll();
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int) $r["id"];
        }
        $map = function (string $sql) use ($cid): array {
    
            $out = [];
            foreach (q($sql, [$cid])->fetchAll() as $r) {
                $out[(int) $r["account_id"]] = (int) $r["total"];
            }
            return $out;
        };
        $revenues = $map(
            "SELECT account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND account_id IS NOT NULL GROUP BY account_id",
        );
        $expenses = $map(
            "SELECT account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_expenses WHERE clinic_id=? AND status='paga' AND account_id IS NOT NULL GROUP BY account_id",
        );
        $tin = $map(
            "SELECT account_to_id account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_transfers WHERE clinic_id=? GROUP BY account_to_id",
        );
        $tout = $map(
            "SELECT account_from_id account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_transfers WHERE clinic_id=? GROUP BY account_from_id",
        );
        $out = [];
        $total = 0;
        foreach ($rows as $r) {
            $id = (int) $r["id"];
            $balance =
                (int) $r["opening_balance_cents"] +
                ($revenues[$id] ?? 0) -
                ($expenses[$id] ?? 0) +
                ($tin[$id] ?? 0) -
                ($tout[$id] ?? 0);
            $r["balance_cents"] = $balance;
            $r["received_cents"] = $revenues[$id] ?? 0;
            $r["paid_cents"] = $expenses[$id] ?? 0;
            $r["transfer_in_cents"] = $tin[$id] ?? 0;
            $r["transfer_out_cents"] = $tout[$id] ?? 0;
            if ((int) $r["active"] === 1) {
                $total += $balance;
            }
            $out[] = $r;
        }
        return ["rows" => $out, "total_cents" => $total];
    
    }

    public static function financial_dashboard_numbers(int $cid): array
    
    {
    
        $today = app_today_in_timezone($cid);
        [$todayStart, $todayEnd] = app_local_day_utc_range($today, $cid);
        $zone = new DateTimeZone(app_context_timezone(null, $cid));
        $baseDay = new DateTimeImmutable($today . " 00:00:00", $zone);
        $d7End = (string) $baseDay
            ->modify("+8 days")
            ->setTimezone(new DateTimeZone("UTC"))
            ->getTimestamp();
        $d30End = (string) $baseDay
            ->modify("+31 days")
            ->setTimezone(new DateTimeZone("UTC"))
            ->getTimestamp();
        $bal = financial_account_balances($cid);
        $rev =
            one(
                "SELECT COALESCE(SUM(CASE WHEN status='prevista' AND expected_at IS NOT NULL AND expected_at<? THEN amount_cents ELSE 0 END),0) receive30, COALESCE(SUM(CASE WHEN status='prevista' AND expected_at IS NOT NULL AND expected_at<? THEN amount_cents ELSE 0 END),0) receive7, COALESCE(SUM(CASE WHEN status='prevista' AND expected_at>=? AND expected_at<? THEN amount_cents ELSE 0 END),0) receiveToday, COALESCE(SUM(CASE WHEN status='prevista' AND expected_at<? THEN amount_cents ELSE 0 END),0) overRec FROM pi_financial_revenues WHERE clinic_id=?",
                [$d30End, $d7End, $todayStart, $todayEnd, $todayStart, $cid],
            ) ?:
            [];
        $exp =
            one(
                "SELECT COALESCE(SUM(CASE WHEN status='prevista' AND due_at IS NOT NULL AND due_at<? THEN amount_cents ELSE 0 END),0) pay30, COALESCE(SUM(CASE WHEN status='prevista' AND due_at IS NOT NULL AND due_at<? THEN amount_cents ELSE 0 END),0) pay7, COALESCE(SUM(CASE WHEN status='prevista' AND due_at>=? AND due_at<? THEN amount_cents ELSE 0 END),0) payToday, COALESCE(SUM(CASE WHEN status='prevista' AND due_at<? THEN amount_cents ELSE 0 END),0) overPay FROM pi_financial_expenses WHERE clinic_id=?",
                [$d30End, $d7End, $todayStart, $todayEnd, $todayStart, $cid],
            ) ?:
            [];
        $receive30 = (int) ($rev["receive30"] ?? 0);
        $receive7 = (int) ($rev["receive7"] ?? 0);
        $receiveToday = (int) ($rev["receiveToday"] ?? 0);
        $overRec = (int) ($rev["overRec"] ?? 0);
        $pay30 = (int) ($exp["pay30"] ?? 0);
        $pay7 = (int) ($exp["pay7"] ?? 0);
        $payToday = (int) ($exp["payToday"] ?? 0);
        $overPay = (int) ($exp["overPay"] ?? 0);
        $projected = (int) $bal["total_cents"] + $receive30 - $pay30;
        $health = "Boa";
        $healthClass = "good";
        $healthMsg =
            "O saldo atual cobre as despesas previstas dos próximos 30 dias.";
        if ((int) $bal["total_cents"] + $receive7 < $pay7 || $overPay > 0) {
            $health = "Crítica";
            $healthClass = "bad";
            $healthMsg =
                "Há risco no caixa: despesas próximas ou vencidas superam a cobertura disponível.";
        } elseif ((int) $bal["total_cents"] + $receive30 < $pay30 || $overRec > 0) {
            $health = "Atenção";
            $healthClass = "warn";
            $healthMsg =
                "Revise recebimentos previstos e despesas dos próximos 30 dias.";
        }
        return compact(
            "bal",
            "receive30",
            "pay30",
            "receive7",
            "pay7",
            "receiveToday",
            "payToday",
            "overRec",
            "overPay",
            "projected",
            "health",
            "healthClass",
            "healthMsg",
        );
    
    }
}
