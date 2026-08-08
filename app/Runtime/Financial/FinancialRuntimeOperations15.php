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

final class FinancialRuntimeOperations15
{
    private function __construct()
    {
    }

    public static function financial_admin_operations_panel(int $cid, int $uid): string
    
    {
    
        $locations = financial_admin_location_select_options($cid);
        $pendingOptions = financial_expected_appointment_revenue_options($cid);
        $op = (string) ($_GET["op"] ?? "");
        if (!in_array($op, ["receber", "pagar", "transferir"], true)) {
            $op = "";
        }
        $receipt =
            '<form method="post" class="compact finance-lite-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="admin_receive"><div class="two">' .
            form_row(
                "Paciente devedor",
                patient_lookup_field($cid, "patient_link_id", "", "patient_search"),
            ) .
            select_label(
                "Pendência de agendamento",
                "expected_revenue_id",
                $pendingOptions,
                "",
            ) .
            '</div><div class="two">' .
            form_row(
                "Valor recebido",
                financial_money_input(
                    "amount",
                    "",
                    'placeholder="Use apenas para recebimento sem agendamento"',
                ),
            ) .
            select_label(
                "Entrou em qual local?",
                "to_location_id",
                $locations,
                "",
                "required",
            ) .
            '</div><div class="two">' .
            select_label(
                "Forma",
                "payment_method",
                payment_methods_options(),
                "pix",
                "required",
            ) .
            form_row(
                "Descrição",
                input(
                    "title",
                    "text",
                    "",
                    'placeholder="Ex.: recebimento sem agendamento"',
                ),
            ) .
            '</div><label class="checkline"><input type="checkbox" name="receipt_without_appointment" value="1"><span>Recebimento sem agendamento vinculado</span></label>' .
            form_row(
                "Motivo / observação",
                input(
                    "notes",
                    "text",
                    "",
                    'placeholder="Obrigatório se houver pendência e o recebimento for avulso"',
                ),
            ) .
            (function_exists("patient_autosuggest_datalist")
                ? patient_autosuggest_datalist($cid)
                : "") .
            '<p class="muted">Regra do Prontoo: se o Paciente tem pendência de agendamento, receba a pendência. Avulso só com motivo.</p>' .
            form_actions("Registrar recebimento") .
            "</form>";
        $payment =
            '<form method="post" class="compact finance-lite-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="admin_payment"><div class="two">' .
            select_label(
                "Credor",
                "counterparty_id",
                counterparty_options($cid),
                "",
                "required",
            ) .
            form_row(
                "Valor pago",
                financial_money_input("amount", "", "required"),
            ) .
            '</div><div class="two">' .
            select_label(
                "Saiu de qual local?",
                "from_location_id",
                $locations,
                "",
                "required",
            ) .
            select_label(
                "Forma",
                "payment_method",
                payment_methods_options(),
                "pix",
                "required",
            ) .
            '</div><div class="two">' .
            select_label(
                "Motivo",
                "expense_category",
                financial_expense_category_options(),
                "outras",
                "required",
            ) .
            form_row(
                "Descrição",
                input(
                    "title",
                    "text",
                    "",
                    'required placeholder="Ex.: aluguel, material, contador"',
                ),
            ) .
            "</div>" .
            form_row(
                "Observação",
                input("notes", "text", "", 'placeholder="Opcional"'),
            ) .
            '<p class="muted">Credores são cadastrados em Pessoas › Credores. Pagamentos do Administrador saem apenas de Cofre ou Banco.</p>' .
            form_actions("Registrar pagamento", "danger") .
            "</form>";
        $transfer =
            '<form method="post" class="compact finance-lite-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="admin_transfer"><div class="two">' .
            select_label(
                "Saiu de",
                "from_location_id",
                $locations,
                "",
                "required",
            ) .
            select_label("Foi para", "to_location_id", $locations, "", "required") .
            '</div><div class="two">' .
            form_row(
                "Valor transferido",
                financial_money_input("amount", "", "required"),
            ) .
            form_row(
                "Motivo",
                input(
                    "title",
                    "text",
                    "Transferência entre Cofre/Banco",
                    "required",
                ),
            ) .
            "</div>" .
            form_row(
                "Observação",
                input(
                    "notes",
                    "text",
                    "",
                    'placeholder="Ex.: depósito do Cofre no Banco"',
                ),
            ) .
            '<p class="muted">Transferências com Gaveta são tratadas no fechamento da Recepção, não por operação administrativa direta.</p>' .
            form_actions("Registrar transferência") .
            "</form>";
        if ($op !== "") {
            $defs = [
                "receber" => [
                    "Receber de Paciente",
                    "add_card",
                    "Entrada de paciente, preferencialmente vinculada a uma pendência de agendamento.",
                    $receipt,
                ],
                "pagar" => [
                    "Pagar Credor",
                    "payments",
                    "Saída para credor, despesa ou obrigação do Consultório.",
                    $payment,
                ],
                "transferir" => [
                    "Transferir Valor",
                    "swap_horiz",
                    "Movimentação interna entre Cofre e Banco.",
                    $transfer,
                ],
            ];
            $d = $defs[$op];
            $back =
                '<div class="subtle-actions"><a class="ghost small" href="' .
                href("financial", ["tab" => "operacoes"]) .
                '">' .
                icon("arrow_back") .
                "<span>Operações</span></a></div>";
            return '<div class="finance-workspace finance-operations-workspace">' .
                $back .
                card(
                    "<h2>" .
                        icon($d[1]) .
                        "<span>" .
                        e($d[0]) .
                        '</span></h2><p class="muted">' .
                        e($d[2]) .
                        "</p>" .
                        $d[3],
                    "finance-form finance-admin-operation-card finance-context-form finance-operation-screen-card",
                ) .
                "</div>";
        }
        $rows = "";
        foreach (
            [
                "receber" => [
                    "Receber de Paciente",
                    "add_card",
                    "Registrar entrada de paciente, vinculando pendência quando houver.",
                    "Abrir recebimento",
                ],
                "pagar" => [
                    "Pagar Credor",
                    "payments",
                    "Registrar saída para credor, despesa ou obrigação do Consultório.",
                    "Abrir pagamento",
                ],
                "transferir" => [
                    "Transferir Valor",
                    "swap_horiz",
                    "Mover valores internamente entre Cofre e Banco.",
                    "Abrir transferência",
                ],
            ]
            as $key => $d
        ) {
            $rows .=
                '<a class="patient-card-row ds-person-row finance-operation-row" href="' .
                href("financial", ["tab" => "operacoes", "op" => $key]) .
                '"><span class="patient-card-avatar ds-person-avatar" aria-hidden="true">' .
                icon($d[1]) .
                '</span><div class="patient-card-main ds-person-main"><div class="patient-card-title ds-person-title"><strong>' .
                e($d[0]) .
                '</strong><span class="pill">Financeiro</span></div><div class="patient-card-meta ds-person-meta"><span>' .
                e($d[2]) .
                '</span></div></div><div class="patient-card-actions ds-person-actions"><span class="ghost small">' .
                e($d[3]) .
                "</span></div></a>";
        }
        return '<div class="finance-workspace finance-operations-workspace">' .
            card(
                "<h2>" .
                    icon("payments") .
                    '<span>Operações financeiras</span></h2><p class="muted">Escolha uma operação para abrir a tela de lançamento. A lista permanece limpa e os formulários não ficam soltos na página.</p><div class="ds-directory-list finance-operation-directory">' .
                    $rows .
                    "</div>",
                "finance-list finance-operations-directory-card",
            ) .
            "</div>";
    
    }

    public static function financial_creditor_upsert_from_post(int $cid, int $uid): int
    
    {
    
        person_common_profile_schema_ready();
        $name = mb_trim((string) ($_POST["creditor_name"] ?? ""));
        $type = (string) ($_POST["creditor_legal_type"] ?? "cpf");
        $doc = only_digits((string) ($_POST["creditor_legal_document"] ?? ""));
        $birth = mb_trim((string) ($_POST["creditor_birth_date"] ?? "")) ?: null;
        if ($name === "") {
            throw new RuntimeException("Informe o nome do Credor.");
        }
        if ($type === "cnpj") {
            if (!valid_cnpj($doc)) {
                throw new RuntimeException("Informe um CNPJ válido para o Credor.");
            }
            $pid = save_person_by_document($name, $doc, null);
        } else {
            if (!valid_cpf($doc)) {
                throw new RuntimeException("Informe um CPF válido para o Credor.");
            }
            $pid = save_person_flexible($name, $doc, $birth);
        }
        $profile = person_common_profile_from_array($_POST, "creditor_");
        $profile["legal_type"] = $type === "cnpj" ? "cnpj" : "cpf";
        $profile["legal_document"] = $doc;
        person_common_profile_update($pid, $profile);
        $notes = mb_trim((string) ($_POST["creditor_notes"] ?? ""));
        q(
            "INSERT INTO pi_financial_counterparties (clinic_id,person_id,kind,notes,active,created_by,created_at) VALUES (?,?,?,?,1,?,NOW()) ON DUPLICATE KEY UPDATE notes=VALUES(notes), active=1, updated_at=NOW()",
            [$cid, $pid, "credor", $notes ?: null, $uid],
        );
        $id =
            (int) (val(
                "SELECT id FROM pi_financial_counterparties WHERE clinic_id=? AND person_id=? AND kind='credor' LIMIT 1",
                [$cid, $pid],
            ) ?:
            0);
        audit("credor_salvo", "pessoa", $id, [
            "nome" => $name,
            "documento" => $doc,
            "audit_body" => "Credor cadastrado ou atualizado no contexto Pessoas.",
        ]);
        return $id;
    
    }

    public static function financial_creditor_directory_card(array $r, int $cid): string
    
    {
    
        $name = (string) ($r["full_name"] ?? "Credor #" . ($r["id"] ?? ""));
        $doc = only_digits(
            (string) ($r["legal_document"] ?? "" ?: $r["cpf"] ?? ""),
        );
        $docLabel = $doc !== "" ? mask($doc) : "CPF/CNPJ não informado";
        $phone = phone_br((string) ($r["phone"] ?? ""));
        $phoneLabel = trim($phone) !== "" ? $phone : "Sem telefone";
        $email = mb_trim((string) ($r["email"] ?? ""));
        $city = mb_trim((string) ($r["address_city"] ?? ""));
        $state = mb_trim((string) ($r["address_state"] ?? ""));
        $cityLabel =
            $city !== ""
                ? $city . ($state !== "" ? " / " . $state : "")
                : "Endereço não informado";
        $paidTotal = (int) ($r["paid_total_cents"] ?? 0);
        $lastPaid = mb_trim((string) ($r["last_paid_at"] ?? ""));
        $lastPaidLabel =
            $lastPaid !== ""
                ? (function_exists("app_date_br")
                    ? app_date_br($lastPaid, $cid)
                    : date_br($lastPaid))
                : "Sem pagamento registrado";
        $overdue = (int) ($r["overdue_expenses"] ?? 0);
        $contactOk = $email !== "" || trim($phone) !== "";
        $searchData =
            $name .
            " " .
            $docLabel .
            " " .
            $phoneLabel .
            " " .
            $email .
            " " .
            $cityLabel .
            " " .
            $lastPaidLabel .
            " " .
            ($paidTotal > 0 ? money_br($paidTotal) : "");
        $status = $overdue > 0 ? "pendência" : "ativo";
        $statusClass = $overdue > 0 ? "warn" : "ok";
        return '<article class="patient-card-row ds-person-row ds-creditor-row creditor-status-' .
            e($statusClass) .
            '" data-creditor-row data-patient-search="' .
            e($searchData) .
            '">' .
            '<span class="patient-card-avatar ds-person-avatar" aria-hidden="true">' .
            icon("receipt_long") .
            "</span>" .
            '<div class="patient-card-main ds-person-main"><div class="patient-card-title ds-person-title"><strong>' .
            e($name) .
            '</strong><span class="pill patient-status-pill ds-status-pill ' .
            e($statusClass) .
            '">' .
            e($status) .
            "</span></div>" .
            '<div class="patient-card-meta ds-person-meta"><span>' .
            icon("badge") .
            "<span>" .
            e($docLabel) .
            "</span></span><span>" .
            icon("call") .
            "<span>" .
            e($phoneLabel) .
            "</span></span>" .
            ($email !== ""
                ? "<span>" .
                    icon("alternate_email") .
                    "<span>" .
                    e($email) .
                    "</span></span>"
                : "") .
            "<span>" .
            icon("location_on") .
            "<span>" .
            e($cityLabel) .
            "</span></span><span>" .
            icon("payments") .
            "<span>Total pago: " .
            e(money_br($paidTotal)) .
            "</span></span><span>" .
            icon("event_available") .
            "<span>Último pagamento: " .
            e($lastPaidLabel) .
            "</span></span>" .
            ($contactOk
                ? ""
                : '<span class="pill warn">' .
                    icon("priority_high") .
                    "Contato pendente</span>") .
            ($overdue > 0
                ? '<span class="pill bad">' .
                    icon("warning") .
                    e((string) $overdue) .
                    " vencida(s)</span>"
                : "") .
            "</div></div>" .
            '<div class="patient-card-actions ds-person-actions"><form method="post" class="inline" onsubmit="return confirm(&quot;Desativar este credor?&quot;)">' .
            csrf_field() .
            '<input type="hidden" name="act" value="creditor_deactivate"><input type="hidden" name="creditor_id" value="' .
            (int) $r["id"] .
            '"><button class="ghost small" type="submit">' .
            icon("visibility_off") .
            "<span>Desativar</span></button></form></div>" .
            "</article>";
    
    }
}
