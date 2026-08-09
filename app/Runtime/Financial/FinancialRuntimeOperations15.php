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
    
        $locations = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_admin_location_select_options($cid);
        $pendingOptions = \Prontoo\Runtime\Financial\FinancialRuntimeOperations10::financial_expected_appointment_revenue_options($cid);
        $op = (string) ($_GET["op"] ?? "");
        if (!in_array($op, ["receber", "pagar", "transferir"], true)) {
            $op = "";
        }
        $receipt =
            '<form method="post" class="compact finance-lite-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="admin_receive"><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Paciente devedor",
                \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_lookup_field($cid, "patient_link_id", "", "patient_search"),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Pendência de agendamento",
                "expected_revenue_id",
                $pendingOptions,
                "",
            ) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Valor recebido",
                \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input(
                    "amount",
                    "",
                    'placeholder="Use apenas para recebimento sem agendamento"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Entrou em qual local?",
                "to_location_id",
                $locations,
                "",
                "required",
            ) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Forma",
                "payment_method",
                \Prontoo\Domain\Financial\FinancialDomainOperations01::payment_methods_options(),
                "pix",
                "required",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Descrição",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "title",
                    "text",
                    "",
                    'placeholder="Ex.: recebimento sem agendamento"',
                ),
            ) .
            '</div><label class="checkline"><input type="checkbox" name="receipt_without_appointment" value="1"><span>Recebimento sem agendamento vinculado</span></label>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Motivo / observação",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "notes",
                    "text",
                    "",
                    'placeholder="Obrigatório se houver pendência e o recebimento for avulso"',
                ),
            ) .
            (is_callable([\Prontoo\Runtime\Patients\PatientsRuntimeOperations02::class, 'patient_autosuggest_datalist'])
                ? \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_autosuggest_datalist($cid)
                : "") .
            '<p class="muted">Regra do Prontoo: se o Paciente tem pendência de agendamento, receba a pendência. Avulso só com motivo.</p>' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Registrar recebimento") .
            "</form>";
        $payment =
            '<form method="post" class="compact finance-lite-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="admin_payment"><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Credor",
                "counterparty_id",
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations02::counterparty_options($cid),
                "",
                "required",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Valor pago",
                \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input("amount", "", "required"),
            ) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Saiu de qual local?",
                "from_location_id",
                $locations,
                "",
                "required",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Forma",
                "payment_method",
                \Prontoo\Domain\Financial\FinancialDomainOperations01::payment_methods_options(),
                "pix",
                "required",
            ) .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Motivo",
                "expense_category",
                \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_expense_category_options(),
                "outras",
                "required",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Descrição",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "title",
                    "text",
                    "",
                    'required placeholder="Ex.: aluguel, material, contador"',
                ),
            ) .
            "</div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Observação",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("notes", "text", "", 'placeholder="Opcional"'),
            ) .
            '<p class="muted">Credores são cadastrados em Pessoas › Credores. Pagamentos do Administrador saem apenas de Cofre ou Banco.</p>' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Registrar pagamento", "danger") .
            "</form>";
        $transfer =
            '<form method="post" class="compact finance-lite-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="admin_transfer"><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Saiu de",
                "from_location_id",
                $locations,
                "",
                "required",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label("Foi para", "to_location_id", $locations, "", "required") .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Valor transferido",
                \Prontoo\Presentation\Financial\FinancialPresentationOperations01::financial_money_input("amount", "", "required"),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Motivo",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "title",
                    "text",
                    "Transferência entre Cofre/Banco",
                    "required",
                ),
            ) .
            "</div>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Observação",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "notes",
                    "text",
                    "",
                    'placeholder="Ex.: depósito do Cofre no Banco"',
                ),
            ) .
            '<p class="muted">Transferências com Gaveta são tratadas no fechamento da Recepção, não por operação administrativa direta.</p>' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions("Registrar transferência") .
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
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "operacoes"]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Operações</span></a></div>";
            return '<div class="finance-workspace finance-operations-workspace">' .
                $back .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    "<h2>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($d[1]) .
                        "<span>" .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($d[0]) .
                        '</span></h2><p class="muted">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($d[2]) .
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
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("financial", ["tab" => "operacoes", "op" => $key]) .
                '"><span class="patient-card-avatar ds-person-avatar" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($d[1]) .
                '</span><div class="patient-card-main ds-person-main"><div class="patient-card-title ds-person-title"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($d[0]) .
                '</strong><span class="pill">Financeiro</span></div><div class="patient-card-meta ds-person-meta"><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($d[2]) .
                '</span></div></div><div class="patient-card-actions ds-person-actions"><span class="ghost small">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($d[3]) .
                "</span></div></a>";
        }
        return '<div class="finance-workspace finance-operations-workspace">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                "<h2>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
                    '<span>Operações financeiras</span></h2><p class="muted">Escolha uma operação para abrir a tela de lançamento. A lista permanece limpa e os formulários não ficam soltos na página.</p><div class="ds-directory-list finance-operation-directory">' .
                    $rows .
                    "</div>",
                "finance-list finance-operations-directory-card",
            ) .
            "</div>";
    
    }

    public static function financial_creditor_upsert_from_post(int $cid, int $uid): int
    
    {
    
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_schema_ready();
        $name = mb_trim((string) ($_POST["creditor_name"] ?? ""));
        $type = (string) ($_POST["creditor_legal_type"] ?? "cpf");
        $doc = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_POST["creditor_legal_document"] ?? ""));
        $birth = mb_trim((string) ($_POST["creditor_birth_date"] ?? "")) ?: null;
        if ($name === "") {
            throw new RuntimeException("Informe o nome do Credor.");
        }
        if ($type === "cnpj") {
            if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cnpj($doc)) {
                throw new RuntimeException("Informe um CNPJ válido para o Credor.");
            }
            $pid = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::save_person_by_document($name, $doc, null);
        } else {
            if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($doc)) {
                throw new RuntimeException("Informe um CPF válido para o Credor.");
            }
            $pid = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::save_person_flexible($name, $doc, $birth);
        }
        $profile = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_from_array($_POST, "creditor_");
        $profile["legal_type"] = $type === "cnpj" ? "cnpj" : "cpf";
        $profile["legal_document"] = $doc;
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_update($pid, $profile);
        $notes = mb_trim((string) ($_POST["creditor_notes"] ?? ""));
        \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "INSERT INTO pi_financial_counterparties (clinic_id,person_id,kind,notes,active,created_by,created_at) VALUES (?,?,?,?,1,?,NOW()) ON DUPLICATE KEY UPDATE notes=VALUES(notes), active=1, updated_at=NOW()",
            [$cid, $pid, "credor", $notes ?: null, $uid],
        );
        $id =
            (int) (\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::val(
                "SELECT id FROM pi_financial_counterparties WHERE clinic_id=? AND person_id=? AND kind='credor' LIMIT 1",
                [$cid, $pid],
            ) ?:
            0);
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("credor_salvo", "pessoa", $id, [
            "nome" => $name,
            "documento" => $doc,
            "audit_body" => "Credor cadastrado ou atualizado no contexto Pessoas.",
        ]);
        return $id;
    
    }

    public static function financial_creditor_directory_card(array $r, int $cid): string
    
    {
    
        $name = (string) ($r["full_name"] ?? "Credor #" . ($r["id"] ?? ""));
        $doc = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits(
            (string) ($r["legal_document"] ?? "" ?: $r["cpf"] ?? ""),
        );
        $docLabel = $doc !== "" ? \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($doc) : "CPF/CNPJ não informado";
        $phone = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($r["phone"] ?? ""));
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
                ? (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'app_date_br'])
                    ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_date_br($lastPaid, $cid)
                    : \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($lastPaid))
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
            ($paidTotal > 0 ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($paidTotal) : "");
        $status = $overdue > 0 ? "pendência" : "ativo";
        $statusClass = $overdue > 0 ? "warn" : "ok";
        return '<article class="patient-card-row ds-person-row ds-creditor-row creditor-status-' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($statusClass) .
            '" data-creditor-row data-patient-search="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($searchData) .
            '">' .
            '<span class="patient-card-avatar ds-person-avatar" aria-hidden="true">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("receipt_long") .
            "</span>" .
            '<div class="patient-card-main ds-person-main"><div class="patient-card-title ds-person-title"><strong>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
            '</strong><span class="pill patient-status-pill ds-status-pill ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($statusClass) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($status) .
            "</span></div>" .
            '<div class="patient-card-meta ds-person-meta"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($docLabel) .
            "</span></span><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("call") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($phoneLabel) .
            "</span></span>" .
            ($email !== ""
                ? "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("alternate_email") .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($email) .
                    "</span></span>"
                : "") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("location_on") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cityLabel) .
            "</span></span><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("payments") .
            "<span>Total pago: " .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($paidTotal)) .
            "</span></span><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
            "<span>Último pagamento: " .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($lastPaidLabel) .
            "</span></span>" .
            ($contactOk
                ? ""
                : '<span class="pill warn">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("priority_high") .
                    "Contato pendente</span>") .
            ($overdue > 0
                ? '<span class="pill bad">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("warning") .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $overdue) .
                    " vencida(s)</span>"
                : "") .
            "</div></div>" .
            '<div class="patient-card-actions ds-person-actions"><form method="post" class="inline" onsubmit="return confirm(&quot;Desativar este credor?&quot;)">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="creditor_deactivate"><input type="hidden" name="creditor_id" value="' .
            (int) $r["id"] .
            '"><button class="ghost small" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("visibility_off") .
            "<span>Desativar</span></button></form></div>" .
            "</article>";
    
    }
}
