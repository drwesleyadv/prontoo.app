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
    
        $clinic = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.admin_pages.07.admin_clinic_detail_page.01', [$id], []);
        if (!$clinic) {
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Consultório não encontrado.", "bad");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_clinics");
        }
    
        $billing = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::billing_state($clinic);
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
    
        $team = (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.07.admin_clinic_detail_page.02', [$id], []);
        $professionals = (int) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.07.admin_clinic_detail_page.03', [$id], []);
        $roleRows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.07.admin_clinic_detail_page.04', [$id, (int) $clinic["owner_user_id"]], [])->fetchAll();
        $roleLabels = [];
        foreach ($roleRows as $roleRow) {
            $code = (string) ($roleRow["role_code"] ?? "");
            $roleLabels[] = PRONTOO_ROLES[$code] ?? $code;
        }
        $rolesText = $roleLabels ? implode(" · ", array_unique($roleLabels)) : "Sem cargo ativo";
    
        $clinicDocument = mb_trim((string) ($clinic["legal_document"] ?? ""));
        if ($clinicDocument !== "" && (string) ($clinic["legal_type"] ?? "") === "cpf") {
            $clinicDocument = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cpf_br($clinicDocument);
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
                ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($billing["paid_until"])
                : (!empty($billing["trial_active"])
                    ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($billing["trial_ends_at"] ?? "")
                    : "Sem vencimento"));
    
        $hero =
            '<div class="admin-clinic-detail-toolbar"><a class="pagehead-control pagehead-control--secondary" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_clinics")) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            '<span>Voltar aos consultórios</span></a></div><section class="admin-clinic-detail-hero ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($statusClass) .
            '"><span class="admin-clinic-detail-hero-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("home_health") .
            '</span><div class="admin-clinic-detail-hero-copy"><span class="eyebrow">Consultório #' .
            $id .
            '</span><h2>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $clinic["display_name"]) .
            '</h2><p>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $clinic["legal_name"]) .
            '</p></div><span class="clinic-attention-chip ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($statusClass) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($statusIcon) .
            '<b>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($statusLabel) .
            '</b></span></section>';
    
        $clinicData =
            '<div class="section-head"><div><span class="eyebrow">Cadastro</span><h2>Dados do consultório</h2></div></div><div class="admin-clinic-detail-grid">' .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("badge", "Razão social", (string) $clinic["legal_name"]) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("storefront", "Nome de exibição", (string) $clinic["display_name"]) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("id_card", strtoupper((string) $clinic["legal_type"]), $clinicDocument) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("call", "Telefone", \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($clinic["phone"] ?? ""))) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("stethoscope", "Área profissional", (string) $clinic["responsible_profession"]) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("location_on", "Endereço", $clinicAddress) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("schedule", "Fuso horário", (string) $clinic["timezone"]) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("event", "Criado em", \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($clinic["created_at"] ?? "")) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("groups", "Equipe ativa", (string) $team, $professionals . " profissional(is)") .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item(
                "checklist",
                "Configuração inicial",
                (int) $clinic["onboarding_done"] === 1 ? "Concluída" : "Pendente",
            ) .
            '</div>';
    
        $responsibleData =
            '<div class="section-head"><div><span class="eyebrow">Responsável</span><h2>Responsável pelo consultório</h2></div></div><div class="admin-clinic-detail-grid">' .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("person", "Nome", $ownerName) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("fingerprint", "CPF", \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cpf_br((string) ($clinic["owner_cpf"] ?? ""))) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("cake", "Data de nascimento", \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($clinic["owner_birth_date"] ?? "")) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("mail", "E-mail", $ownerEmail) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("call", "Telefone", \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br((string) ($clinic["owner_phone"] ?? ""))) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("work", "Cargos no consultório", $rolesText) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item(
                "verified_user",
                "Conta",
                (int) $clinic["owner_active"] === 1 ? "Ativa" : "Inativa",
                mb_trim((string) ($clinic["owner_last_login_at"] ?? "")) !== ""
                    ? "Último acesso: " . \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($clinic["owner_last_login_at"])
                    : "Ainda não acessou",
            ) .
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_clinic_detail_item("home", "Endereço do responsável", $ownerAddress) .
            '</div>';
        if ((int) $clinic["manager_user_id"] !== (int) $clinic["owner_user_id"]) {
            $responsibleData .=
                '<div class="admin-clinic-manager-note">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("manage_accounts") .
                '<div><small>Gestor cadastrado</small><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($clinic["manager_name"] ?? "")) .
                '</b><span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($clinic["manager_email"] ?? "")) .
                '</span></div></div>';
        }
    
        $hidden = '<input type="hidden" name="id" value="' .
            $id .
            '"><input type="hidden" name="return_clinic_id" value="' .
            $id .
            '">';
        $operationActions =
            '<div class="admin-clinic-action-block"><div class="admin-clinic-action-copy">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("power_settings_new") .
            '<div><b>Operação do consultório</b><span>Ative ou desative o acesso operacional sem apagar o cadastro.</span></div></div><form method="post">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            $hidden .
            '<button class="' .
            ((int) $clinic["active"] === 1 ? "danger-soft" : "primary") .
            '" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon((int) $clinic["active"] === 1 ? "toggle_off" : "toggle_on") .
            '<span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((int) $clinic["active"] === 1 ? "Desativar consultório" : "Ativar consultório") .
            '</span></button></form></div>';
        $subscriptionActions =
            '<div class="admin-clinic-action-block"><div class="admin-clinic-action-copy">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified") .
            '<div><b>Ações rápidas da assinatura</b><span>Atualize imediatamente o estado comercial do consultório.</span></div></div><div class="admin-clinic-action-buttons"><form method="post">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            $hidden .
            '<input type="hidden" name="act" value="activate_subscription"><button class="primary" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified") .
            '<span>Definir como Ativo</span></button></form><form method="post" onsubmit="return confirm(&quot;Colocar este consultório em Somente leitura?&quot;)">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            $hidden .
            '<input type="hidden" name="act" value="deactivate_subscription"><button class="danger-soft" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("lock") .
            '<span>Somente leitura</span></button></form></div></div>';
        $billingForm =
            '<div class="admin-clinic-billing-panel"><div class="admin-clinic-action-copy">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("tune") .
            '<div><b>Status e vencimento</b><span>Defina o estado vigente e a data de validade da assinatura.</span></div></div><form method="post" class="compact admin-clinic-billing-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            $hidden .
            '<input type="hidden" name="act" value="billing"><div class="admin-clinic-billing-fields">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Status",
                "subscription_status",
                [
                    "active" => "Ativo",
                    "read_only" => "Somente leitura",
                    "exempt" => "Isento",
                ],
                $currentStatus,
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Vencimento",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "paid_until",
                    "date",
                    \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($billing["paid_until"] ?? ""),
                ),
            ) .
            '</div><div class="admin-clinic-billing-footer"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
            '<span>Vencimento atual: <b>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($dueLabel) .
            '</b></span></span><button class="primary" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
            '<span>Salvar assinatura</span></button></div></form></div>';
    
        $actions =
            '<div class="section-head"><div><span class="eyebrow">Gestão</span><h2>Ações do Desenvolvedor</h2><p>As mesmas ações do antigo modal, agora organizadas em uma tela própria.</p></div></div><div class="admin-clinic-actions-stack">' .
            $operationActions .
            $subscriptionActions .
            $billingForm .
            '</div>';
    
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Consultório", "") .
            $hero .
            '<div class="admin-clinic-detail-columns">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($clinicData, "admin-clinic-detail-card") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($responsibleData, "admin-clinic-detail-card") .
            '</div>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($actions, "admin-clinic-actions-card");
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Consultório · " . (string) $clinic["display_name"], $body);
    
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
            try {
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.07.admin_clinic_people_counts_by_cpf.01', $clinicIds, ['itemCount' => count($clinicIds)])->fetchAll();
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
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "dashboard",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("admin_clinic_people_by_cpf", [
                    $clinicIds,
                ]),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("dashboard"),
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
