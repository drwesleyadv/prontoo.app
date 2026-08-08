<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AdminPages;

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

final class AdminPagesRuntimeOperations07
{
    private function __construct()
    {
    }

    public static function admin_clinic_detail_page(int $id): void
    
    {
    
        $clinic = one(
            "SELECT c.*,ou.name AS owner_name,ou.email AS owner_email,ou.active AS owner_active,ou.last_login_at AS owner_last_login_at,ou.created_at AS owner_created_at,op.full_name AS owner_person_name,op.cpf AS owner_cpf,op.birth_date AS owner_birth_date,op.phone AS owner_phone,op.email AS owner_person_email,op.address AS owner_address,op.address_number AS owner_address_number,op.address_neighborhood AS owner_address_neighborhood,op.address_complement AS owner_address_complement,op.address_city AS owner_address_city,op.address_state AS owner_address_state,mu.name AS manager_name,mu.email AS manager_email FROM pi_clinics c JOIN pi_users ou ON ou.id=c.owner_user_id JOIN pi_persons op ON op.id=ou.person_id LEFT JOIN pi_users mu ON mu.id=c.manager_user_id WHERE c.id=?",
            [$id],
        );
        if (!$clinic) {
            flash("Consultório não encontrado.", "bad");
            redirect("admin_clinics");
        }
    
        $billing = billing_state($clinic);
        $status = (string) ($billing["status"] ?? "active");
        $currentStatus = in_array($status, ["active", "read_only", "exempt"], true)
            ? $status
            : (!empty($billing["exempt"])
                ? "exempt"
                : (!empty($billing["read_only"])
                    ? "read_only"
                    : "active"));
        $statusLabel = (int) $clinic["active"] !== 1
            ? "Inativo"
            : ([
                "active" => "Ativo",
                "read_only" => "Somente leitura",
                "exempt" => "Isento",
                "trial" => "Período gratuito",
                "cancelled" => "Cancelado",
                "suspended" => "Suspenso",
            ][$status] ?? ucfirst($status));
        $statusIcon = !empty($billing["exempt"])
            ? "workspace_premium"
            : (!empty($billing["read_only"])
                ? "lock"
                : ((int) $clinic["active"] === 1
                    ? "verified"
                    : "pause_circle"));
        $statusClass = !empty($billing["exempt"])
            ? "is-exempt"
            : (!empty($billing["read_only"])
                ? "is-critical"
                : ((int) $clinic["active"] === 1
                    ? "is-stable"
                    : "is-muted"));
    
        $team = (int) val(
            "SELECT COUNT(*) FROM pi_user_roles WHERE clinic_id=? AND active=1",
            [$id],
        );
        $professionals = (int) val(
            "SELECT COUNT(*) FROM pi_user_roles WHERE clinic_id=? AND role_code='medico' AND active=1",
            [$id],
        );
        $roleRows = q(
            "SELECT role_code FROM pi_user_roles WHERE clinic_id=? AND user_id=? AND active=1 ORDER BY role_code",
            [$id, (int) $clinic["owner_user_id"]],
        )->fetchAll();
        $roleLabels = [];
        foreach ($roleRows as $roleRow) {
            $code = (string) ($roleRow["role_code"] ?? "");
            $roleLabels[] = PRONTOO_ROLES[$code] ?? $code;
        }
        $rolesText = $roleLabels ? implode(" · ", array_unique($roleLabels)) : "Sem cargo ativo";
    
        $clinicDocument = mb_trim((string) ($clinic["legal_document"] ?? ""));
        if ($clinicDocument !== "" && (string) ($clinic["legal_type"] ?? "") === "cpf") {
            $clinicDocument = cpf_br($clinicDocument);
        } elseif (preg_match('/^\d{14}$/', $clinicDocument)) {
            $clinicDocument = preg_replace(
                '/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/',
                '$1.$2.$3/$4-$5',
                $clinicDocument,
            ) ?: $clinicDocument;
        }
        $addressParts = array_filter([
            mb_trim((string) ($clinic["address_line"] ?? "")),
            mb_trim((string) ($clinic["address_city"] ?? "")),
            mb_trim((string) ($clinic["address_state"] ?? "")),
        ]);
        $clinicAddress = implode(" · ", $addressParts);
        $ownerAddressParts = array_filter([
            mb_trim((string) ($clinic["owner_address"] ?? "")) .
                (mb_trim((string) ($clinic["owner_address_number"] ?? "")) !== ""
                    ? ", " . mb_trim((string) $clinic["owner_address_number"])
                    : ""),
            mb_trim((string) ($clinic["owner_address_neighborhood"] ?? "")),
            mb_trim((string) ($clinic["owner_address_city"] ?? "")),
            mb_trim((string) ($clinic["owner_address_state"] ?? "")),
        ]);
        $ownerAddress = implode(" · ", $ownerAddressParts);
        $ownerEmail = mb_trim((string) ($clinic["owner_person_email"] ?? "")) ?:
            mb_trim((string) ($clinic["owner_email"] ?? ""));
        $ownerName = mb_trim((string) ($clinic["owner_person_name"] ?? "")) ?:
            mb_trim((string) ($clinic["owner_name"] ?? ""));
        $dueLabel = !empty($billing["exempt"])
            ? "Isento"
            : (mb_trim((string) ($billing["paid_until"] ?? "")) !== ""
                ? date_br($billing["paid_until"])
                : (!empty($billing["trial_active"])
                    ? date_br($billing["trial_ends_at"] ?? "")
                    : "Sem vencimento"));
    
        $hero =
            '<div class="admin-clinic-detail-toolbar"><a class="ghost small cmdlike" href="' .
            e(href("admin_clinics")) .
            '">' .
            icon("arrow_back") .
            '<span>Voltar aos consultórios</span></a></div><section class="admin-clinic-detail-hero ' .
            e($statusClass) .
            '"><span class="admin-clinic-detail-hero-icon">' .
            icon("home_health") .
            '</span><div class="admin-clinic-detail-hero-copy"><span class="eyebrow">Consultório #' .
            $id .
            '</span><h2>' .
            e((string) $clinic["display_name"]) .
            '</h2><p>' .
            e((string) $clinic["legal_name"]) .
            '</p></div><span class="clinic-attention-chip ' .
            e($statusClass) .
            '">' .
            icon($statusIcon) .
            '<b>' .
            e($statusLabel) .
            '</b></span></section>';
    
        $clinicData =
            '<div class="section-head"><div><span class="eyebrow">Cadastro</span><h2>Dados do consultório</h2></div></div><div class="admin-clinic-detail-grid">' .
            admin_clinic_detail_item("badge", "Razão social", (string) $clinic["legal_name"]) .
            admin_clinic_detail_item("storefront", "Nome de exibição", (string) $clinic["display_name"]) .
            admin_clinic_detail_item("id_card", strtoupper((string) $clinic["legal_type"]), $clinicDocument) .
            admin_clinic_detail_item("call", "Telefone", phone_br((string) ($clinic["phone"] ?? ""))) .
            admin_clinic_detail_item("stethoscope", "Área profissional", (string) $clinic["responsible_profession"]) .
            admin_clinic_detail_item("location_on", "Endereço", $clinicAddress) .
            admin_clinic_detail_item("schedule", "Fuso horário", (string) $clinic["timezone"]) .
            admin_clinic_detail_item("event", "Criado em", date_br($clinic["created_at"] ?? "")) .
            admin_clinic_detail_item("groups", "Equipe ativa", (string) $team, $professionals . " profissional(is)") .
            admin_clinic_detail_item(
                "checklist",
                "Configuração inicial",
                (int) $clinic["onboarding_done"] === 1 ? "Concluída" : "Pendente",
            ) .
            '</div>';
    
        $responsibleData =
            '<div class="section-head"><div><span class="eyebrow">Responsável</span><h2>Responsável pelo consultório</h2></div></div><div class="admin-clinic-detail-grid">' .
            admin_clinic_detail_item("person", "Nome", $ownerName) .
            admin_clinic_detail_item("fingerprint", "CPF", cpf_br((string) ($clinic["owner_cpf"] ?? ""))) .
            admin_clinic_detail_item("cake", "Data de nascimento", date_br($clinic["owner_birth_date"] ?? "")) .
            admin_clinic_detail_item("mail", "E-mail", $ownerEmail) .
            admin_clinic_detail_item("call", "Telefone", phone_br((string) ($clinic["owner_phone"] ?? ""))) .
            admin_clinic_detail_item("work", "Cargos no consultório", $rolesText) .
            admin_clinic_detail_item(
                "verified_user",
                "Conta",
                (int) $clinic["owner_active"] === 1 ? "Ativa" : "Inativa",
                mb_trim((string) ($clinic["owner_last_login_at"] ?? "")) !== ""
                    ? "Último acesso: " . dt_br($clinic["owner_last_login_at"])
                    : "Ainda não acessou",
            ) .
            admin_clinic_detail_item("home", "Endereço do responsável", $ownerAddress) .
            '</div>';
        if ((int) $clinic["manager_user_id"] !== (int) $clinic["owner_user_id"]) {
            $responsibleData .=
                '<div class="admin-clinic-manager-note">' .
                icon("manage_accounts") .
                '<div><small>Gestor cadastrado</small><b>' .
                e((string) ($clinic["manager_name"] ?? "")) .
                '</b><span>' .
                e((string) ($clinic["manager_email"] ?? "")) .
                '</span></div></div>';
        }
    
        $hidden = '<input type="hidden" name="id" value="' .
            $id .
            '"><input type="hidden" name="return_clinic_id" value="' .
            $id .
            '">';
        $operationActions =
            '<div class="admin-clinic-action-block"><div class="admin-clinic-action-copy">' .
            icon("power_settings_new") .
            '<div><b>Operação do consultório</b><span>Ative ou desative o acesso operacional sem apagar o cadastro.</span></div></div><form method="post">' .
            csrf_field() .
            $hidden .
            '<button class="' .
            ((int) $clinic["active"] === 1 ? "danger-soft" : "primary") .
            '" type="submit">' .
            icon((int) $clinic["active"] === 1 ? "toggle_off" : "toggle_on") .
            '<span>' .
            e((int) $clinic["active"] === 1 ? "Desativar consultório" : "Ativar consultório") .
            '</span></button></form></div>';
        $subscriptionActions =
            '<div class="admin-clinic-action-block"><div class="admin-clinic-action-copy">' .
            icon("verified") .
            '<div><b>Ações rápidas da assinatura</b><span>Atualize imediatamente o estado comercial do consultório.</span></div></div><div class="admin-clinic-action-buttons"><form method="post">' .
            csrf_field() .
            $hidden .
            '<input type="hidden" name="act" value="activate_subscription"><button class="primary" type="submit">' .
            icon("verified") .
            '<span>Definir como Ativo</span></button></form><form method="post" onsubmit="return confirm(&quot;Colocar este consultório em Somente leitura?&quot;)">' .
            csrf_field() .
            $hidden .
            '<input type="hidden" name="act" value="deactivate_subscription"><button class="danger-soft" type="submit">' .
            icon("lock") .
            '<span>Somente leitura</span></button></form></div></div>';
        $billingForm =
            '<div class="admin-clinic-billing-panel"><div class="admin-clinic-action-copy">' .
            icon("tune") .
            '<div><b>Status e vencimento</b><span>Defina o estado vigente e a data de validade da assinatura.</span></div></div><form method="post" class="compact admin-clinic-billing-form">' .
            csrf_field() .
            $hidden .
            '<input type="hidden" name="act" value="billing"><div class="admin-clinic-billing-fields">' .
            select_label(
                "Status",
                "subscription_status",
                [
                    "active" => "Ativo",
                    "read_only" => "Somente leitura",
                    "exempt" => "Isento",
                ],
                $currentStatus,
            ) .
            form_row(
                "Vencimento",
                input(
                    "paid_until",
                    "date",
                    app_date_input_from_storage($billing["paid_until"] ?? ""),
                ),
            ) .
            '</div><div class="admin-clinic-billing-footer"><span>' .
            icon("event_available") .
            '<span>Vencimento atual: <b>' .
            e($dueLabel) .
            '</b></span></span><button class="primary" type="submit">' .
            icon("save") .
            '<span>Salvar assinatura</span></button></div></form></div>';
    
        $actions =
            '<div class="section-head"><div><span class="eyebrow">Gestão</span><h2>Ações do Desenvolvedor</h2><p>As mesmas ações do antigo modal, agora organizadas em uma tela própria.</p></div></div><div class="admin-clinic-actions-stack">' .
            $operationActions .
            $subscriptionActions .
            $billingForm .
            '</div>';
    
        $body =
            page_head("Consultório", "") .
            $hero .
            '<div class="admin-clinic-detail-columns">' .
            card($clinicData, "admin-clinic-detail-card") .
            card($responsibleData, "admin-clinic-detail-card") .
            '</div>' .
            card($actions, "admin-clinic-actions-card");
        page("Consultório · " . (string) $clinic["display_name"], $body);
    
    }

    public static function admin_clinic_people_counts_by_cpf(array $clinicIds): array
    
    {
    
        $clinicIds = array_values(
            array_unique(array_filter(array_map("intval", $clinicIds))),
        );
        if (!$clinicIds) {
            return [];
        }
        sort($clinicIds, SORT_NUMERIC);
        $clinicIds = array_slice($clinicIds, 0, 300);
        $loader = static function () use ($clinicIds): array {
    
            $out = [];
            foreach ($clinicIds as $clinicId) {
                $out[$clinicId] = ["professionals" => 0, "collaborators" => 0];
            }
            $placeholders = implode(",", array_fill(0, count($clinicIds), "?"));
            try {
                $rows = q(
                    "SELECT ur.clinic_id,COUNT(DISTINCT CASE WHEN ur.role_code='medico' THEN NULLIF(p.cpf,'') END) AS professionals,COUNT(DISTINCT NULLIF(p.cpf,'')) AS collaborators FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 JOIN pi_persons p ON p.id=u.person_id WHERE ur.clinic_id IN ($placeholders) AND ur.active=1 GROUP BY ur.clinic_id",
                    $clinicIds,
                )->fetchAll();
                foreach ($rows as $row) {
                    $clinicId = (int) ($row["clinic_id"] ?? 0);
                    if (isset($out[$clinicId])) {
                        $out[$clinicId] = [
                            "professionals" =>
                                (int) ($row["professionals"] ?? 0),
                            "collaborators" =>
                                (int) ($row["collaborators"] ?? 0),
                        ];
                    }
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo admin clinic CPF counts] " . $e->getMessage(),
                );
            }
            return $out;
        };
        if (function_exists("server_json_cache_remember")) {
            return server_json_cache_remember(
                "dashboard",
                server_json_cache_safe_key("admin_clinic_people_by_cpf", [
                    $clinicIds,
                ]),
                server_json_cache_ttl("dashboard"),
                $loader,
                [
                    "table:pi_user_roles",
                    "table:pi_users",
                    "table:pi_persons",
                    "admin:clinic_counts",
                ],
            );
        }
        return $loader();
    
    }
}
