<?php
declare(strict_types=1);

namespace Prontoo\Domain\Financial;

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

final class FinancialDomainOperations01
{
    private function __construct()
    {
    }

    public static function parse_money_cents(string $v): int
    
    {
    
        $v = mb_trim($v);
        if ($v === "") {
            return 0;
        }
        $v = str_replace(["R$", " "], "", $v);
        if (str_contains($v, ",")) {
            if (
                preg_match(
                    '/^(?:\d+|\d{1,3}(?:\.\d{3})+),(\d{1,2})$/',
                    $v,
                    $match,
                ) !== 1
            ) {
                throw new RuntimeException(
                    "Informe o valor em reais com no máximo duas casas decimais.",
                );
            }
            [$whole, $fraction] = explode(",", $v, 2);
            $whole = str_replace(".", "", $whole);
        } elseif (preg_match('/^\d+(?:\.(\d{1,2}))?$/', $v, $match) === 1) {
            [$whole, $fraction] = array_pad(explode(".", $v, 2), 2, "");
        } else {
            throw new RuntimeException(
                "Informe o valor em reais com no máximo duas casas decimais.",
            );
        }
        $whole = ltrim($whole, "0");
        $whole = $whole === "" ? "0" : $whole;
        $fraction = str_pad($fraction, 2, "0");
        $maxWhole = (string) intdiv(PRONTOO_FINANCIAL_MAX_CENTS, 100);
        if (
            strlen($whole) > strlen($maxWhole) ||
            (strlen($whole) === strlen($maxWhole) && strcmp($whole, $maxWhole) > 0)
        ) {
            throw new RuntimeException(
                "O valor informado ultrapassa o limite financeiro seguro.",
            );
        }
        $cents = ((int) $whole * 100) + (int) $fraction;
        return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_amount_cents($cents);
    
    }

    public static function financial_assert_amount_cents(
        int $value,
        string $label = "Valor",
    ): int 
    {
    
        if ($value < 0 || $value > PRONTOO_FINANCIAL_MAX_CENTS) {
            throw new RuntimeException(
                $label . " ultrapassa o domínio monetário seguro.",
            );
        }
        return $value;
    
    }

    public static function financial_assert_balance_cents(
        int $value,
        string $label = "Saldo",
    ): int 
    {
    
        if (
            $value < -PRONTOO_FINANCIAL_MAX_CENTS ||
            $value > PRONTOO_FINANCIAL_MAX_CENTS
        ) {
            throw new RuntimeException(
                $label . " ultrapassa o domínio monetário seguro.",
            );
        }
        return $value;
    
    }

    public static function financial_checked_add(
        int $left,
        int $right,
        string $label = "Saldo",
    ): int 
    {
    
        return \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_balance_cents($left + $right, $label);
    
    }

    public static function financial_movement_delta_for_location(
        int $amount,
        ?int $from,
        ?int $to,
        int $locationId,
    ): int 
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_assert_amount_cents($amount);
        return ($to === $locationId ? $amount : 0) -
            ($from === $locationId ? $amount : 0);
    
    }

    public static function financial_validate_movement_topology(
        string $type,
        ?int $from,
        ?int $to,
    ): void 
    {
    
        $from = ($from ?? 0) > 0 ? $from : null;
        $to = ($to ?? 0) > 0 ? $to : null;
        $valid = match ($type) {
            "receipt", "cash_opening" => $from === null && $to !== null,
            "payment", "refund" => $from !== null && $to === null,
            "transfer", "deposit", "cash_closing" =>
                $from !== null && $to !== null && $from !== $to,
            "adjustment" => ($from === null) !== ($to === null),
            default => false,
        };
        if (!$valid) {
            throw new RuntimeException(
                "Origem e destino não correspondem à natureza do movimento financeiro.",
            );
        }
    
    }

    public static function financial_closing_equation(
        int $expected,
        int $declared,
        int $kept,
        int $transferred,
        int $difference,
    ): bool 
    {
    
        return $declared === $kept + $transferred &&
            $difference === $declared - $expected;
    
    }

    public static function payment_methods_options(): array
    
    {
    
        return [
            "pix" => "PIX",
            "dinheiro" => "Dinheiro",
            "debito" => "Cartão de débito",
            "credito" => "Cartão de crédito",
            "transferencia" => "Transferência bancária",
            "boleto" => "Boleto bancário",
            "cheque" => "Cheque",
            "outro" => "Outro",
        ];
    
    }

    public static function payment_method_type_options(): array
    
    {
    
        return [
            "pix" => "PIX",
            "dinheiro" => "Dinheiro",
            "cartao_debito" => "Cartão de débito",
            "cartao_credito" => "Cartão de crédito",
            "transferencia" => "Transferência bancária",
            "boleto" => "Boleto bancário",
            "cheque" => "Cheque",
            "outro" => "Outro",
        ];
    
    }

    public static function normalize_payment_method(string $v): string
    
    {
    
        $v = strtolower(trim($v));
        return array_key_exists($v, \Prontoo\Domain\Financial\FinancialDomainOperations01::payment_methods_options()) ? $v : "";
    
    }

    public static function financial_goal_base_options(): array
    
    {
    
        return [
            "efetivada" => "Receita Efetivada",
            "prevista" => "Receita Prevista",
        ];
    
    }

    public static function financial_goal_base_label(string $base): string
    
    {
    
        $opts = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_goal_base_options();
        return $opts[$base] ?? $opts["efetivada"];
    
    }

    public static function financial_expense_category_options(): array
    
    {
    
        return [
            "administrativa" => "Despesas administrativas",
            "pessoal" => "Pessoal e encargos",
            "servicos" => "Serviços tomados",
            "insumos" => "Materiais e insumos",
            "aluguel_condominio" => "Aluguel e condomínio",
            "tributos" => "Tributos e taxas",
            "financeira" => "Despesas financeiras",
            "marketing" => "Marketing e captação",
            "manutencao" => "Manutenção e tecnologia",
            "outras" => "Outras despesas",
        ];
    
    }

    public static function financial_account_type_options(): array
    
    {
    
        return [
            "conta_corrente" => "Conta corrente",
            "conta_poupanca" => "Conta poupança",
            "conta_pagamento" => "Conta de pagamento",
            "caixa_interno" => "Em espécie",
            "investimento" => "Conta de investimento",
        ];
    
    }

    public static function financial_date_or_null(string $date, bool $end = false): ?string
    
    {
    
        $date = trim($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        return $date . ($end ? " 23:59:59" : " 12:00:00");
    
    }

    public static function financial_account_icon(array $account): string
    
    {
    
        return ($account["account_type"] ?? "") === "caixa_interno"
            ? "payments"
            : "account_balance";
    
    }

    public static function financial_location_type_options(): array
    
    {
    
        return [
            "pos" => "Gaveta",
            "admin_safe" => "Cofre do Consultório",
            "bank_account" => "Conta Bancária",
        ];
    
    }

    public static function financial_location_type_label(string $type): string
    
    {
    
        $o = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_location_type_options();
        return $o[$type] ?? "Local financeiro";
    
    }

    public static function financial_cashier_roles(): array
    
    {
    
        return ["recepcionista"];
    
    }

    public static function financial_is_cashier(array $c): bool
    
    {
    
        return ($c["scope"] ?? "") === "clinic" &&
            in_array((string) ($c["role"] ?? ""), \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_cashier_roles(), true);
    
    }

    public static function financial_human_session_status(string $status): string
    
    {
    
        return match ($status) {
            "open" => "Aberta",
            "kept_closed" => "Mantido fechado",
            "opening_pending_review" => "Abertura da Gaveta aguardando autorização",
            "opening_rejected" => "Abertura da Gaveta recusada",
            "closed_pending_review" => "Fechada, aguardando conferência",
            "approved" => "Conferida",
            "rejected" => "Devolvida para correção",
            default => "Em análise",
        };
    
    }

    public static function financial_human_movement_type(string $type): string
    
    {
    
        return match ($type) {
            "receipt" => "Recebi",
            "payment" => "Paguei",
            "transfer" => "Transferência",
            "deposit" => "Depósito bancário",
            "cash_opening" => "Saldo inicial",
            "cash_closing" => "Fechamento",
            "adjustment" => "Ajuste",
            "refund" => "Devolução",
            default => "Movimentação",
        };
    
    }

    public static function financial_human_movement_status(string $status): string
    
    {
    
        return match ($status) {
            "confirmed" => "Confirmado",
            "pending_review" => "Aguardando conferência",
            "cancelled" => "Cancelado",
            "rejected" => "Rejeitado",
            default => $status !== ""
                ? ucfirst(str_replace("_", " ", $status))
                : "Em análise",
        };
    
    }

    public static function financial_movement_icon(string $type): string
    
    {
    
        return match ($type) {
            "receipt" => "add_card",
            "payment" => "payments",
            "refund" => "keyboard_return",
            "transfer" => "sync_alt",
            "deposit" => "account_balance",
            "cash_opening" => "lock_open",
            "cash_closing" => "lock",
            default => "receipt_long",
        };
    
    }

    public static function financial_operational_schema_ready(): void
    
    {
    
        return;
    
    }

    public static function financial_drawer_daily_totals_empty(): array
    
    {
    
        return [
            "sessions" => 0,
            "opening" => 0,
            "receipts" => 0,
            "payments" => 0,
            "withdrawn" => 0,
            "transfers" => 0,
            "declared" => 0,
            "kept" => 0,
            "open_count" => 0,
            "pending_count" => 0,
        ];
    
    }

    public static function financial_appointment_payment_state(array $a): array
    
    {
    
        $amount = (int) ($a["payment_amount_cents"] ?? 0);
        $status = mb_strtolower(mb_trim((string) ($a["payment_status"] ?? "")));
        $method = \Prontoo\Domain\Financial\FinancialDomainOperations01::normalize_payment_method((string) ($a["payment_method"] ?? ""));
        $paid =
            !empty($a["payment_confirmed_at"]) ||
            in_array($status, ["efetivada", "recebido", "pago"], true);
        $forgiven = in_array($status, ["isento", "cortesia", "sem_cobranca"], true);
        if ($amount <= 0 || $forgiven) {
            return [
                "code" => "sem_cobranca",
                "label" => $forgiven ? "Cortesia / isento" : "Sem cobrança",
                "class" => "neutral",
                "icon" => "money_off",
                "amount" => $amount,
                "method" => $method,
            ];
        }
        if ($paid) {
            return [
                "code" => "recebido",
                "label" => "Recebido",
                "class" => "ok",
                "icon" => "check_circle",
                "amount" => $amount,
                "method" => $method,
            ];
        }
        $journey = is_callable([\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::class, 'appointment_status_code'])
            ? \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a)
            : (string) ($a["status"] ?? "");
        if (in_array($journey, ["atendimento_concluido", "finalizado"], true)) {
            return [
                "code" => "aguardando_pagamento",
                "label" => "Aguardando pagamento",
                "class" => "warn",
                "icon" => "payments",
                "amount" => $amount,
                "method" => $method,
            ];
        }
        return [
            "code" => "previsto",
            "label" => "Valor previsto",
            "class" => "info",
            "icon" => "payments",
            "amount" => $amount,
            "method" => $method,
        ];
    
    }

    public static function financial_cashier_receipt_method_options(): array
    
    {
    
        return [
            "dinheiro" => "Dinheiro",
            "pix" => "PIX",
            "debito" => "Cartão de débito",
            "credito" => "Cartão de crédito",
            "transferencia" => "Transferência bancária",
            "boleto" => "Boleto bancário",
            "cheque" => "Cheque",
            "outro" => "Outro",
        ];
    
    }
}
