<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Legacy\Financial;

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

final class FinancialPresentationOperations01
{
    private function __construct()
    {
    }

    public static function posted_counterparty_search_value(): string
    
    {
    
        foreach ($_POST as $k => $v) {
            if (is_string($k) && str_starts_with($k, "counterparty_search")) {
                return mb_trim((string) $v);
            }
        }
        return mb_trim((string) ($_POST["counterparty_search"] ?? ""));
    
    }

    public static function financial_status_pill(
        string $status,
        ?string $date = null,
        string $type = "revenue",
    ): string 
    {
    
        $label = match ($status) {
            "efetivada" => "Recebida",
            "paga" => "Paga",
            "cancelada" => "Cancelada",
            default => $type === "expense" ? "A pagar" : "Prevista",
        };
        $cls = "pill";
        if ($status === "cancelada") {
            $cls .= " muted";
        } elseif (in_array($status, ["efetivada", "paga"], true)) {
            $cls .= " ok";
        } elseif ($date && strtotime($date) < strtotime("today")) {
            $label = $type === "expense" ? "Vencida" : "Atrasada";
            $cls .= " bad";
        }
        return '<span class="' . $cls . '">' . e($label) . "</span>";
    
    }

    public static function financial_report_line(string $label, int $value): string
    
    {
    
        return '<article class="finance-row mini"><span>' .
            e($label) .
            "</span><b>" .
            money_br($value) .
            "</b></article>";
    
    }

    public static function financial_money_input(
        string $name,
        string $value = "",
        string $extra = "",
    ): string 
    {
    
        return input($name, "text", $value, 'inputmode="decimal" ' . $extra);
    
    }

    public static function financial_daily_consolidation_blockers_html(array $state): string
    
    {
    
        $blockers = $state["blockers"] ?? [];
        if (!$blockers) {
            return "";
        }
        $h = '<div class="finance-conference-blocking-list">';
        foreach ($blockers as $b) {
            $h .=
                "<div><b>" .
                icon("lock") .
                "<span>Pendente</span></b><span>" .
                e((string) $b) .
                "</span></div>";
        }
        return $h . "</div>";
    
    }

    public static function financial_daily_drawer_closure_blocking_html(array $state): string
    
    {
    
        $rows = $state["blocking_rows"] ?? [];
        if (!$rows) {
            return "";
        }
        $h = '<div class="finance-conference-blocking-list">';
        foreach ($rows as $r) {
            $status = (string) ($r["status"] ?? "not_started");
            $label =
                $status === "not_started"
                    ? "Aguardando abertura/fechamento"
                    : financial_human_session_status($status);
            $h .=
                "<div><b>" .
                e((string) ($r["drawer_name"] ?? "Gaveta")) .
                "</b><span>" .
                e((string) ($r["user_name"] ?? "Colaborador")) .
                " · " .
                e($label) .
                "</span></div>";
        }
        return $h . "</div>";
    
    }

    public static function financial_cash_exception_debug(
        Throwable $e,
        array $c,
        string $act = "",
    ): string 
    {
    
        $lines = [];
        $lines[] = "PRONTOO_CAIXA_DEBUG";
        $lines[] = "timestamp_utc=" . gmdate("c");
        $lines[] = "route=" . (function_exists("route") ? route() : "indefinida");
        $lines[] = "http_method=" . (string) ($_SERVER["REQUEST_METHOD"] ?? "");
        $lines[] =
            "action=" . ($act !== "" ? $act : (string) ($_POST["act"] ?? ""));
        $lines[] = "clinic_id=" . (string) ($c["clinic_id"] ?? "");
        $lines[] = "user_id=" . (string) ($c["user"]["id"] ?? "");
        $lines[] =
            "role=" . (string) ($c["role"] ?? ($_SESSION["role_code"] ?? ""));
        $lines[] = "exception_class=" . get_class($e);
        $lines[] = "exception_code=" . (string) $e->getCode();
        $lines[] = "message=" . $e->getMessage();
        $lines[] = "file=" . $e->getFile();
        $lines[] = "line=" . (string) $e->getLine();
        if ($e->getPrevious()) {
            $p = $e->getPrevious();
            $lines[] = "previous_class=" . get_class($p);
            $lines[] = "previous_code=" . (string) $p->getCode();
            $lines[] = "previous_message=" . $p->getMessage();
            $lines[] = "previous_file=" . $p->getFile();
            $lines[] = "previous_line=" . (string) $p->getLine();
        }
        $post = [];
        foreach ($_POST as $k => $v) {
            $key = (string) $k;
            if (in_array($key, ["csrf", "_token", "password", "senha"], true)) {
                continue;
            }
            $post[$key] = is_scalar($v) ? (string) $v : gettype($v);
        }
        $lines[] =
            "post=" .
            json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lines[] = "php_version=" . PHP_VERSION;
        $lines[] = "trace=";
        $trace = explode("\n", $e->getTraceAsString());
        $lines = array_merge($lines, array_slice($trace, 0, 12));
        return implode("\n", $lines);
    
    }

    public static function financial_cash_store_debug(
        Throwable $e,
        array $c,
        string $act = "",
    ): void 
    {
    
        $debug = financial_cash_exception_debug($e, $c, $act);
        $_SESSION["financial_cash_debug_error"] = $debug;
        $_SESSION["financial_cash_error_at"] = gmdate("d/m/Y H:i:s") . " UTC";
        error_log(
            "[Prontoo caixa atendimento] " . str_replace("\n", " | ", $debug),
        );
    
    }

    public static function financial_cash_debug_request_active(): bool
    
    {
    
        if ((string) ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
            return false;
        }
        if (function_exists("route") && route() !== "financial") {
            return false;
        }
        $act = (string) ($_POST["act"] ?? "");
        return in_array(
            $act,
            [
                "cash_open",
                "cash_keep_closed",
                "cash_receipt",
                "cash_payment",
                "cash_close",
            ],
            true,
        );
    
    }

    public static function financial_admin_balance_kpis_html(array $pos): string
    
    {
    
        return '<div class="kpis finance-kpis finance-balance-kpis" aria-label="Saldos financeiros do consultório"><article class="finance-balance-card finance-balance-total">' .
            icon("savings") .
            "<p><b>" .
            money_br((int) $pos["total_cents"]) .
            '</b><span>Total disponível</span></p></article><article class="finance-balance-card finance-balance-drawers">' .
            icon("point_of_sale") .
            "<p><b>" .
            money_br((int) $pos["pos_cents"]) .
            '</b><span>Gavetas</span></p></article><article class="finance-balance-card finance-balance-safes">' .
            icon("account_balance_wallet") .
            "<p><b>" .
            money_br((int) $pos["safe_cents"]) .
            '</b><span>Cofre</span></p></article><article class="finance-balance-card finance-balance-banks">' .
            icon("account_balance") .
            "<p><b>" .
            money_br((int) $pos["bank_cents"]) .
            "</b><span>Bancos</span></p></article></div>";
    
    }
}
