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
            $r = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.02.counterparty_lookup_field.01", [$id, $cid], []);
            if ($r) {
                $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
                $display =
                    (string) $r["full_name"] .
                    ($doc !== "" ? " · " . \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($doc) : "");
            }
        }
        return '<input type="hidden" name="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($hiddenName) .
            '" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($hiddenValue) .
            '" data-counterparty-id-target><input type="search" name="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($inputName) .
            '" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($display) .
            '" list="prontoo_counterparty_suggestions" placeholder="Digite nome, CPF ou CNPJ" autocomplete="off" spellcheck="false" required data-ds-lookup="counterparty" aria-label="Buscar pessoa, fornecedor ou credor" data-counterparty-document-suggest data-counterparty-suggest-url="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("counterparty_suggest")) .
            '" aria-autocomplete="list">';
    
    }

    public static function resolve_counterparty_lookup_id(
        int $cid,
        int $postedId,
        string $search = "",
    ): int 
    {
    
        if ($postedId > 0) {
            $ok = (int) \Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.02.resolve_counterparty_lookup_id.01", [$postedId, $cid], []);
            if ($ok > 0) {
                return $ok;
            }
        }
        $search = trim($search);
        if ($search === "") {
            return 0;
        }
        $clean = mb_strtolower($search, "UTF-8");
        $digits = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($search);
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.resolve_counterparty_lookup_id.02", [$cid], [])->fetchAll();
        $exact = [];
        $nameExact = [];
        $starts = [];
        foreach ($rows as $r) {
            $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
            $masked = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($doc);
            $display = mb_strtolower(
                (string) $r["full_name"] . ($masked !== "" ? " · " . $masked : ""),
                "UTF-8",
            );
            $name = mb_strtolower((string) $r["full_name"], "UTF-8");
            $docDigits = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($doc);
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
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("financial");
        $cid = (int) $c["clinic_id"];
        $doc = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_GET["doc"] ?? ($_GET["cpf"] ?? "")));
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        if (
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("counterparty_lookup_" . $cid),
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
            ($isCpf && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($doc)) ||
            ($isCnpj && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cnpj($doc)) ||
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
            ? \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.02.page_counterparty_lookup.01", [$doc], [])
            : \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.02.page_counterparty_lookup.02", [$doc], []);
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
        $link = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.02.page_counterparty_lookup.03", [$cid, (int) $p["id"]], []);
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
                "document" => \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask(
                    (string) ($p["cpf"] ?? "" ?: $p["legal_document"] ?? ""),
                ),
                "birth_date" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($p["birth_date"] ?? ""),
            ],
            JSON_UNESCAPED_UNICODE,
        );
    
    }

    public static function page_counterparty_suggest(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("financial");
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
        $digits = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($q);
        $params = [$cid];
        $searchMode = "name";
        if ($digits !== "" && mb_strlen($q, "UTF-8") >= 2) {
            $searchMode = "document";
            $params[] = $q . "%";
            $params[] = "%" . $digits . "%";
            $params[] = "%" . $digits . "%";
        } else {
            $params[] = $q . "%";
        }
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.page_counterparty_suggest.01", $params, compact('searchMode', 'limit'))->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
            $masked = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($doc);
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
    
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.counterparty_options.01", [$cid], [])->fetchAll();
        $out = ["" => "Selecione o credor"];
        foreach ($rows as $r) {
            $out[(int) $r["id"]] = $r["full_name"];
        }
        return $out;
    
    }

    public static function financial_account_options(int $cid): array
    
    {
    
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.account_options.01", [$cid], [])->fetchAll();
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
    
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.account_label_options.01", [$cid], [])->fetchAll();
        $out = ["" => $empty];
        foreach ($rows as $r) {
            $out[(int) $r["id"]] = $r["name"];
        }
        return $out;
    
    }

    public static function financial_ensure_default_accounts(int $cid, int $uid): void
    
    {
    
        if ($cid <= 0 || \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_read_only_db($cid)) {
            return;
        }
        try {
            \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
            \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_ensure_admin_safe($cid, $uid);
            $cashId =
                (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.02.ensure_default_accounts.01", [$cid], []) ?:
                0);
            if ($cashId <= 0) {
                \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.ensure_default_accounts.02", [
                        $cid,
                        "Cofre do Consultório",
                        null,
                        "caixa_interno",
                        0,
                        $uid,
                    ], []);
            }
        } catch (Throwable $e) {
            error_log("[Prontoo finance default accounts] " . $e->getMessage());
        }
    
    }

    public static function financial_account_belongs(int $cid, int $accountId): bool
    
    {
    
        return $accountId > 0 &&
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.02.account_belongs.01", [$accountId, $cid], []) ?:
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
        $doc = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($doc);
        if ($name === "") {
            return 0;
        }
        $pid = 0;
        if ($doc !== "" && in_array(strlen($doc), [11, 14], true)) {
            try {
                $pid = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::save_person_by_document($name, $doc, null);
            } catch (Throwable $e) {
                $pid = 0;
            }
        }
        if ($pid <= 0) {
            $pid =
                (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.02.counterparty_light.01", [$cid, $name], []) ?:
                0);
            if ($pid > 0) {
                \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.counterparty_light.02", [$name, $pid], []);
            } else {
                \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.counterparty_light.03", [$name], []);
                $pid = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->lastInsertId();
            }
        }
        if ($pid <= 0) {
            return 0;
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.counterparty_light.04", [$cid, $pid, "credor", $notes, $uid], []);
        return (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.02.counterparty_light.05", [$cid, $pid], []) ?:
        0);
    
    }

    public static function financial_counterparty_from_post(int $cid, int $uid): int
    
    {
    
        $id = \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::resolve_counterparty_lookup_id(
            $cid,
            (int) ($_POST["counterparty_id"] ?? 0),
            \Prontoo\Presentation\Financial\FinancialPresentationOperations01::posted_counterparty_search_value(),
        );
        if ($id > 0) {
            return $id;
        }
        return \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_counterparty_light(
            $cid,
            $uid,
            \Prontoo\Presentation\Financial\FinancialPresentationOperations01::posted_counterparty_search_value(),
            (string) ($_POST["counterparty_doc"] ?? ""),
            (string) ($_POST["counterparty_notes"] ?? ""),
        );
    
    }

    public static function financial_account_balances(int $cid): array
    
    {
    
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->ensureOperationalSchema();
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.account_balances.01", [$cid], [])->fetchAll();
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int) $r["id"];
        }
        $map = function (string $balanceSource) use ($cid): array {
    
            $out = [];
            foreach (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.02.account_balances.02", [$cid], compact('balanceSource'))->fetchAll() as $r) {
                $out[(int) $r["account_id"]] = (int) $r["total"];
            }
            return $out;
        };
        $revenues = $map('revenue');
        $expenses = $map('expense');
        $tin = $map('transfer_in');
        $tout = $map('transfer_out');
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
    
        $today = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid);
        [$todayStart, $todayEnd] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($today, $cid);
        $zone = new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone(null, $cid));
        $baseDay = new DateTimeImmutable($today . " 00:00:00", $zone);
        $d7End = (string) $baseDay
            ->modify("+8 days")
            ->setTimezone(new DateTimeZone("UTC"))
            ->getTimestamp();
        $d30End = (string) $baseDay
            ->modify("+31 days")
            ->setTimezone(new DateTimeZone("UTC"))
            ->getTimestamp();
        $bal = \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::financial_account_balances($cid);
        $rev =
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.02.dashboard_numbers.01", [$d30End, $d7End, $todayStart, $todayEnd, $todayStart, $cid], []) ?:
            [];
        $exp =
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.02.dashboard_numbers.02", [$d30End, $d7End, $todayStart, $todayEnd, $todayStart, $cid], []) ?:
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
