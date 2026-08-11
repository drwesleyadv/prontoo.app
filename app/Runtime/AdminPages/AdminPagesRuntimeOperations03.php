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

    public static function admin_telemetry_kpi_cards_html(bool $linked = false): string
    {
        $summary = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_comparative_summary();
        $current = (array) ($summary["current"] ?? []);
        $variations = (array) ($summary["variations"] ?? []);
        $recordSeries = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_sequence_series_20d();
$recordPrevious = array_slice(array_values($recordSeries), 0, 15);
$recordCurrent = array_slice(array_values($recordSeries), 15, 15);
$recordPreviousTotal = array_sum(array_map(static fn(array $row): int => (int) ($row["value"] ?? 0), $recordPrevious));
$recordCurrentTotal = array_sum(array_map(static fn(array $row): int => (int) ($row["value"] ?? 0), $recordCurrent));
$recordPreviousObserved = count(array_filter($recordPrevious, static fn(array $row): bool => !empty($row["observed"])));
$recordCurrentObserved = count(array_filter($recordCurrent, static fn(array $row): bool => !empty($row["observed"])));
$recordVariation = null;
if ($recordPreviousObserved === 15 && $recordCurrentObserved === 15) {
    $recordVariation = $recordPreviousTotal === 0
        ? ($recordCurrentTotal === 0 ? 0.0 : null)
        : (($recordCurrentTotal - $recordPreviousTotal) / $recordPreviousTotal) * 100;
}
$recordComparison = [
    "current_total" => $recordCurrentTotal,
    "variation_pct" => $recordVariation,
];
        $card = static function (
            string $label,
            mixed $value,
            string $iconName,
            string $note,
        ) use ($linked): string {
            return $linked
                ? \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::stat_link_card(
                    $label,
                    $value,
                    $iconName,
                    $note,
                    "admin_performance",
                )
                : \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card($label, $value, $iconName, $note);
        };
        $averageMs = isset($current["average_ms"])
            ? \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_performance_format_ms((float) $current["average_ms"])
            : "—";
        $recordVariation = isset($recordComparison["variation_pct"]) &&
            is_numeric($recordComparison["variation_pct"])
                ? (float) $recordComparison["variation_pct"]
                : null;
        return $card(
            "Visualizações",
            max(0, (int) ($current["requests"] ?? 0)),
            "route",
            \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_telemetry_variation_note(
                isset($variations["requests_pct"])
                    ? (float) $variations["requests_pct"]
                    : null,
            ),
        ) .
            $card(
                "Tempo médio das rotas",
                $averageMs,
                "speed",
                \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_telemetry_variation_note(
                    isset($variations["average_ms_pct"])
                        ? (float) $variations["average_ms_pct"]
                        : null,
                ),
            ) .
            $card(
                "Registros",
                (int) ($recordComparison["current_total"] ?? 0),
                "database",
                \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::admin_telemetry_variation_note(
                    $recordVariation,
                ),
            );
    }

    public static function page_status(): void
    
    {
        if (strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")) !== "GET") {
            throw new ProntooHttpError(405, "Método não permitido.");
        }
        if (!headers_sent()) {
            header("Content-Type: text/html; charset=utf-8");
            header("Cache-Control: no-store, max-age=0");
            header("X-Robots-Tag: noindex, nofollow");
        }
        $assetRevision = defined("PRONTOO_ASSET_REV")
            ? (string) PRONTOO_ASSET_REV
            : (string) PRONTOO_VERSION;
    $overviewCards = \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations03::admin_telemetry_kpi_cards_html();
    $statusHeader =
        '<header class="status-page-header">' .
        '<span class="status-page-icon" aria-hidden="true">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("monitor_heart") . "</span>" .
        '<div><h1>Status do Prontoo</h1><p>Janela móvel de 30 dias · 15 dias recentes comparados aos 15 imediatamente anteriores</p></div>' .
        "</header>";
    $card = $statusHeader . $overviewCards . \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_performance_card_html(true);
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="canonical" href="https://prontoo.app/status"><title>Status · Prontoo</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#238763"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="prontoo-version" content="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(PRONTOO_VERSION) .
            '"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/png" href="/public/assets/favicon-' .
            rawurlencode($assetRevision) .
            '.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,-25..200&display=swap" rel="stylesheet"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
            rawurlencode($assetRevision) .
            '&release=' .
            rawurlencode(PRONTOO_VERSION) .
            '"><script defer src="/public/assets/app.js?v=' .
            rawurlencode($assetRevision) .
            '&release=' .
            rawurlencode(PRONTOO_VERSION) .
            '"></script></head><body class="public scope-global status-public" style="--clinic-accent:#238763;--clinic-accent-dark:#105e44;--clinic-accent-soft:#dff3ea;--clinic-on-accent:#ffffff;" data-route="status" data-app-version="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(PRONTOO_VERSION) .
            '"><main id="conteudo" tabindex="-1" aria-label="Status operacional do Prontoo">' .
            $card .
            "</main></body></html>";
    
    }

    public static function page_admin_operations(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_operations");
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Painel operacional", "") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<div class="section-head admin-performance-head"><h2>Operação e financeiro</h2><p>Indicadores dos últimos 30 dias, organizados por operação e caixa da plataforma.</p></div>' .
                    \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations02::admin_global_ops_finance_html(),
                "admin-ops-finance-card",
            );
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Painel operacional", $body);
    
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
        $dbOk = false;
        try {
            $dbOk = (string) \Prontoo\Runtime\Operational\OperationalComposition::administration()->scalar('operational.admin_pages.03.page_admin_health.01', [], []) === "1";
        } catch (Throwable $e) {
            $dbOk = false;
        }
        $openErrors = (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("kpi_errors_open", 120, 'read.admin_pages.03.page_admin_health.01', [], []);
        $errors24h = (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("kpi_errors_24h", 120, 'read.admin_pages.03.page_admin_health.02', [], []);
        $locks = (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("kpi_locks", 60, 'read.admin_pages.03.page_admin_health.03', [], []);
        $scope24h = (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("kpi_scope_actionable_24h_v2_" . \Prontoo\Core\Tenant\TenantRegistry::modelClinicId(), 120, 'read.admin_pages.03.page_admin_health.04', [], []);
        $trialing = (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("kpi_trialing_model_" . \Prontoo\Core\Tenant\TenantRegistry::modelClinicId(), PRONTOO_ADMIN_CACHE_TTL, 'read.admin_pages.03.page_admin_health.05', [], []);
        $readonly = (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val("kpi_readonly_model_" . \Prontoo\Core\Tenant\TenantRegistry::modelClinicId(), PRONTOO_ADMIN_CACHE_TTL, 'read.admin_pages.03.page_admin_health.06', [], []);
        $recent = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_rows_light(["scope" => "model_excluded"], [], 80);
        $bad = 0;
        foreach ($recent as $r) {
            if (!\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::verify_audit_row($r)) {
                $bad++;
            }
        }
        $chainStatus = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_chain_integrity_status(240);
        if (empty($chainStatus["ok"])) {
            $bad++;
        }
        $severity =
            !$dbOk || $openErrors > 0 || $bad > 0 || $scope24h > 0
                ? "Atenção"
                : "Estável";
        $summary =
            '<div class="stats-grid admin-health-compact-kpis">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Estado",
                $severity,
                $severity === "Estável" ? "verified" : "crisis_alert",
                $dbOk ? "banco responde" : "banco indisponível",
            ) .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::stat_link_card(
                "Erros abertos",
                $openErrors,
                "bug_report",
                $errors24h . " nas últimas 24h",
                "admin_errors",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Bloqueios", $locks, "lock_clock", "login ativo") .
            \Prontoo\Runtime\AdminPages\AdminPagesRuntimeOperations01::stat_link_card(
                "Escopo 24h",
                $scope24h,
                "policy",
                "operações bloqueadas",
                "admin_security",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Integridade",
                $bad,
                "verified_user",
                "amostra de auditoria",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Somente leitura", $readonly, "lock", "consultórios") .
            "</div>";
        $items = [
            [
                "icon" => $dbOk ? "check_circle" : "warning",
                "time" => "Banco",
                "title" => $dbOk ? "Conexão operacional" : "Conexão com atenção",
                "body" => "Verificação leve com SELECT 1.",
                "meta" =>
                    "Diagnóstico consolidado no próprio painel de Incidentes.",
            ],
            [
                "icon" => $openErrors ? "bug_report" : "check_circle",
                "time" => "Erros",
                "title" => $openErrors . " erro(s) aberto(s)",
                "body" => $errors24h . " registro(s) nas últimas 24 horas.",
                "meta" =>
                    "Abra a central de erros apenas quando precisar investigar arquivo, linha e rota.",
                "html" =>
                    '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_errors") .
                    '">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Ver erros", "bug_report") .
                    "</a>",
            ],
            [
                "icon" => $bad ? "gpp_bad" : "verified_user",
                "time" => "Integridade",
                "title" => $bad . " anotação(ões) recentes com assinatura alterada",
                "body" =>
                    "Amostra de atividades recentes, descontando consultórios isentos do Desenvolvedor.",
                "meta" => \Prontoo\Core\Metrics\GlobalMetricScope::countNote(),
                "html" =>
                    '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_integrity") .
                    '">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Ver integridade", "verified_user") .
                    "</a>",
            ],
            [
                "icon" => $locks ? "lock_clock" : "shield",
                "time" => "Segurança",
                "title" => $locks . " bloqueio(s) de login ativo(s)",
                "body" =>
                    "Bloqueios temporários de entrada permanecem visíveis em leitura única.",
                "meta" =>
                    "Use a tela dedicada só para liberar ou auditar tentativas.",
                "html" =>
                    '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_security") .
                    '">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Ver segurança", "security") .
                    "</a>",
            ],
            [
                "icon" => "home_health",
                "time" => "Consultórios",
                "title" =>
                    $trialing .
                    " trial(s) em curso · " .
                    $readonly .
                    " em somente leitura",
                "body" => "Assinaturas e adoção ficam no painel de Consultórios.",
                "meta" =>
                    "Acompanhamento financeiro e operacional sem nova tela de incidente.",
                "html" =>
                    '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_clinics") .
                    '">' .
                    \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Ver consultórios", "home_health") .
                    "</a>",
            ],
        ];
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Incidentes", "") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($summary, "admin-health-compact-card") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                "<h2>Sinais principais</h2>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::timeline($items),
                "admin-health-events-card",
            );
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Incidentes", $body);
    
    }
}
