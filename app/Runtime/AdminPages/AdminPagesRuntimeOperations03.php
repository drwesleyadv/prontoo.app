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

final class AdminPagesRuntimeOperations03
{
    private function __construct()
    {
    }


    public static function page_admin_operations(): void
    {
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_operations");
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Indicadores do negócio",
                "Relatório sob demanda de adoção, operação e financeiro da plataforma.",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_ops_finance_html(),
                "admin-ops-finance-card",
            );
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Indicadores do negócio · Desenvolvedor", $body);
    }


    public static function page_admin_deleted(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_health");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "");
            $id = (int) ($_POST["id"] ?? 0);
            if ($id <= 0) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Registro não informado.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_deleted");
            }
            if ($act === "restore_patient") {
                $pat = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.admin_pages.03.page_admin_deleted.01', [$id], []);
                if ($pat) {
                    \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.03.page_admin_deleted.02', [
                            (int) ($_SESSION["uid"] ?? 0),
                            $id,
                            (int) $pat["clinic_id"],
                        ], []);
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("paciente_recuperado", "paciente", $id, [
                        "campos" => ["Restauração administrativa"],
                        "audit_body" =>
                            "Paciente excluído foi restaurado pelo Desenvolvedor. Atualização cadastral deve ser conferida pela clínica.",
                    ]);
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Paciente restaurado. A clínica deverá revisar o cadastro.",
                    );
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_deleted");
            }
            if ($act === "restore_care") {
                $care = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.admin_pages.03.page_admin_deleted.03', [$id], []);
                if ($care) {
                    \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.03.page_admin_deleted.04', [
                            (int) ($_SESSION["uid"] ?? 0),
                            $id,
                            (int) $care["clinic_id"],
                        ], []);
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                        "prontuario_alterado",
                        "paciente",
                        (int) $care["patient_link_id"],
                        [
                            "campos" => ["Restauração administrativa de anotação"],
                            "audit_body" =>
                                "Anotação excluída foi restaurada pelo Desenvolvedor.",
                        ],
                    );
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Anotação restaurada.");
                }
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_deleted");
            }
        }
        $patients = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.03.page_admin_deleted.05', [], [])->fetchAll();
        $persons = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "persons_identity",
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($patients, "person_id"),
        );
        $clinics = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "clinics_name",
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($patients, "clinic_id"),
        );
        $users = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map("users_name", \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($patients, "deleted_by"));
        $pitems = [];
        foreach ($patients as $r) {
            $ps = $persons[(int) $r["person_id"]] ?? [];
            $cl = $clinics[(int) $r["clinic_id"]] ?? [];
            $by = $users[(int) ($r["deleted_by"] ?? 0)] ?? [];
            $form =
                '<form method="post" class="inline">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="restore_patient"><input type="hidden" name="id" value="' .
                (int) $r["id"] .
                '"><button type="submit" class="small primary">Restaurar</button></form>';
            $pitems[] = [
                "icon" => "restore_from_trash",
                "time" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["deleted_at"]),
                "title" => $ps["full_name"] ?? "Paciente #" . $r["id"],
                "body" =>
                    ($cl["display_name"] ?? "Consultório") .
                    " · CPF " .
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask((string) ($ps["cpf"] ?? "")),
                "meta" => "Excluído por " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($by["name"] ?? ""),
                "html" => $form,
            ];
        }
        $care = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.admin_pages.03.page_admin_deleted.06', [], [])->fetchAll();
        $patientsMap = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "patients_scope",
            \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($care, "patient_link_id"),
        );
        $personIds = [];
        $clinicIds = [];
        foreach ($patientsMap as $pm) {
            if (!empty($pm["person_id"])) {
                $personIds[] = (int) $pm["person_id"];
            }
            if (!empty($pm["clinic_id"])) {
                $clinicIds[] = (int) $pm["clinic_id"];
            }
        }
        $personMap = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "persons_name",
            array_values(array_unique($personIds)),
        );
        $clinicMap = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map(
            "clinics_name",
            array_values(array_unique($clinicIds)),
        );
        $users2 = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::fetch_map("users_name", \Prontoo\Domain\AuditActivity\AuditRecordPolicy::int_ids($care, "deleted_by"));
        $citems = [];
        foreach ($care as $r) {
            $pm = $patientsMap[(int) $r["patient_link_id"]] ?? [];
            $ps = $personMap[(int) ($pm["person_id"] ?? 0)] ?? [];
            $cl = $clinicMap[(int) ($pm["clinic_id"] ?? 0)] ?? [];
            $by = $users2[(int) ($r["deleted_by"] ?? 0)] ?? [];
            $form =
                '<form method="post" class="inline">' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                '<input type="hidden" name="act" value="restore_care"><input type="hidden" name="id" value="' .
                (int) $r["id"] .
                '"><button type="submit" class="small primary">Restaurar</button></form>';
            $citems[] = [
                "icon" => "clinical_notes",
                "time" => \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($r["deleted_at"]),
                "title" => $r["title"] ?: ucfirst((string) $r["record_type"]),
                "body" =>
                    "Prontuário de " .
                    ($ps["full_name"] ?? "paciente #" . $r["patient_link_id"]) .
                    " · " .
                    ($cl["display_name"] ?? "Consultório"),
                "meta" => "Excluída por " . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($by["name"] ?? ""),
                "html" => $form,
            ];
        }
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Registros excluídos",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Registros excluídos",
                "Restauração administrativa de dados preservados por integridade.",
            ) .
                '<div class="two"><section class="card"><h2>Pacientes excluídos</h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($pitems, "Nenhum paciente excluído.") .
                '</section><section class="card"><h2>Anotações excluídas</h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($citems, "Nenhuma anotação excluída.") .
                "</section></div>",
        );
    
    }


    public static function page_admin_health(): void
    {
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_health");
        $health = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::platform_health_snapshot();
        $checks = (array) ($health["checks"] ?? []);
        $counts = (array) ($health["counts"] ?? []);
        $components = (array) ($health["components"] ?? []);
        $state = (string) ($health["state"] ?? "Operacional");
        $stateClass = match ($state) {
            "Crítico" => "is-critical",
            "Atenção" => "is-attention",
            default => "is-operational",
        };
        $stateCopy = match ($state) {
            "Crítico" => "Uma condição estrutural exige intervenção técnica.",
            "Atenção" => "Há exceções técnicas que merecem investigação.",
            default => "Banco, storage, integridade, segurança e contrato de versão estão sem sinais de atenção.",
        };
        $summary = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="developer-control-status"><div><span class="eyebrow">Confiabilidade</span><h2>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                '</h2><p>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($stateCopy) .
                '</p></div><span class="developer-state-badge ' .
                $stateClass .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($state === "Crítico" ? "crisis_alert" : ($state === "Atenção" ? "warning" : "verified")) .
                '<span>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($state) .
                '</span></span></div>',
            "admin-health-compact-card",
        );
        $items = [];
        if (empty(($components["database"] ?? [])["ok"])) {
            $items[] = ["icon" => "database_off", "time" => "Banco", "title" => "Conexão indisponível", "body" => "A conectividade essencial não foi confirmada.", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_diagnostics") . '">Diagnosticar</a>'];
        }
        if (empty(($components["storage"] ?? [])["ok"])) {
            $items[] = ["icon" => "folder_off", "time" => "Storage", "title" => "Escrita indisponível", "body" => "O diretório persistente não confirmou escrita.", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_diagnostics") . '">Diagnosticar</a>'];
        }
        $openErrors = (int) ($counts["open_errors"] ?? 0);
        if ($openErrors > 0) {
            $items[] = ["icon" => "bug_report", "time" => "Erros", "title" => $openErrors . " erro(s) aberto(s)", "body" => "Há incidentes técnicos sem resolução registrada.", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_errors") . '">Ver erros</a>'];
        }
        $locks = (int) ($counts["login_locks"] ?? 0);
        $scope = (int) ($counts["scope_alerts_24h"] ?? 0);
        $securityInvariantsOk = !empty($counts["security_invariants_ok"]);
        if ($locks > 0 || $scope > 0 || !$securityInvariantsOk) {
            $securityBody = $locks . " bloqueio(s) de login · " . $scope . " operação(ões) de escopo bloqueada(s).";
            if (!$securityInvariantsOk) {
                $securityBody .= " O autoteste determinístico de isolamento exige revisão.";
            }
            $items[] = ["icon" => "security", "time" => "Segurança", "title" => !$securityInvariantsOk ? "Isolamento exige revisão" : ($locks + $scope) . " sinal(is) para revisar", "body" => $securityBody, "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_security") . '">Ver segurança</a>'];
        }
        if (empty(($components["integrity"] ?? [])["ok"])) {
            $items[] = ["icon" => "gpp_bad", "time" => "Integridade", "title" => "Integridade exige revisão", "body" => "A cadeia de auditoria ou registros recentes não concluíram a verificação.", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_integrity") . '">Ver integridade</a>'];
        }
        if (empty(($components["version"] ?? [])["ok"])) {
            $issues = implode(", ", array_map("strval", (array) (($checks["version_contract"] ?? [])["issues"] ?? [])));
            $items[] = ["icon" => "deployed_code_alert", "time" => "Versão", "title" => "Contrato de versão divergente", "body" => $issues !== "" ? $issues : "A release publicada não concluiu o contrato determinístico.", "html" => '<a class="ghost small" href="' . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_diagnostics") . '">Diagnosticar</a>'];
        }
        $signals = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="section-head"><div><h2>Exceções</h2><p>Somente sinais que mudam uma decisão técnica.</p></div></div>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($items, "Nenhuma exceção técnica ativa."),
            "admin-health-events-card",
        );
        $advanced = '<details class="form-panel developer-advanced-tools"><summary><span>Ferramentas avançadas</span></summary><div class="developer-tool-grid"><a class="developer-tool-card" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_diagnostics") .
            '"><span class="developer-tool-icon">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("troubleshoot") . '</span><span><b>Diagnóstico</b><small>Ambiente, runtime e storage sob demanda.</small></span></a><a class="developer-tool-card" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_deleted") .
            '"><span class="developer-tool-icon">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("restore_from_trash") . '</span><span><b>Recuperação</b><small>Restauração administrativa de registros preservados.</small></span></a></div></details>';
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Confiabilidade", "") .
            $summary .
            $signals .
            $advanced;
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Confiabilidade · Desenvolvedor", $body);
    }

}
