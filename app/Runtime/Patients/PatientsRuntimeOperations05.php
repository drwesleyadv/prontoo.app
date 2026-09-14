<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Patients;

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

final class PatientsRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function page_patients(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("patients");
        $cid = (int) $c["clinic_id"];
        \Prontoo\Runtime\Operational\OperationalComposition::patients()->ensureSchema("patient_guardians");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $cpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($_POST["cpf"] ?? ""));
            $birth = (string) ($_POST["birth_date"] ?? "");
            $name = mb_trim((string) ($_POST["name"] ?? ""));
            if (\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf) && ($name === "" || !\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($birth))) {
                $identity = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_identity_by_cpf($cpf, $cid);
                if ($identity) {
                    if ($name === "") {
                        $name = mb_trim((string) ($identity["full_name"] ?? ""));
                    }
                    if (!\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($birth)) {
                        $birth = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(
                            $identity["birth_date"] ?? "",
                        );
                    }
                }
            }
            if ($name === "") {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe o nome completo do paciente.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patients");
            }
            if (!\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($cpf)) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Este CPF não existe.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patients");
            }
            if (!\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($birth)) {
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Informe nascimento plausível do paciente.", "bad");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patients");
            }
            try {
                $existingPerson = (int) \Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.05.page_patients.01', [$cpf], []);
                if ($existingPerson > 0) {
                    $active = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.05.page_patients.02', [$cid, $existingPerson], []);
                    if ($active) {
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "Este paciente já está cadastrado. Deseja abrir o cadastro dele?",
                            "warn",
                        );
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patients", [
                            "existing_patient" => (int) $active["id"],
                        ]);
                    }
                    $deleted = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.05.page_patients.03', [$cid, $existingPerson], []);
                    if ($deleted) {
                        \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.05.page_patients.04', [(int) $c["user"]["id"], (int) $deleted["id"], $cid], []);
                        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                            "paciente_recuperado",
                            "paciente",
                            (int) $deleted["id"],
                            [
                                "patient_name" => $name,
                                "campos" => ["Recuperação de cadastro"],
                                "audit_body" =>
                                    "Cadastro anteriormente excluído foi recuperado com os dados já existentes no banco. Atualização cadastral solicitada.",
                            ],
                        );
                        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                            "Paciente recuperado com os dados já existentes. Revise e atualize o cadastro.",
                            "warn",
                        );
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => (int) $deleted["id"]]);
                    }
                }
                $pid = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::upsert_person($name, $cpf, $birth);
                $loc = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_location_from_post($cid);
                $contact = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_invoice_contact_from_post();
                \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.05.page_patients.05', [
                        $cid,
                        $pid,
                        $contact["phone"],
                        $contact["email"],
                        $loc["address"],
                        $loc["address_zip"],
                        $loc["address_number"],
                        $loc["address_neighborhood"],
                        $loc["address_complement"],
                        $loc["address_state"],
                        $loc["address_city"],
                        $loc["address_city_ibge"],
                        mb_trim((string) ($_POST["notes"] ?? "")),
                        (int) $c["user"]["id"],
                    ], []);
                $link = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.05.page_patients.06', [$cid, $pid], []);
                $patientLinkId = (int) ($link["id"] ?? 0);
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("patient_writes_total");
                \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "patient_writes");
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                    "paciente_salvo",
                    "paciente",
                    $patientLinkId ?: $pid,
                    array_merge($_POST, [
                        "patient_name" => $name,
                        "acao_paciente" => "cadastrou",
                    ]),
                );
                if (
                    \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_age_years($birth) !== null &&
                    \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_age_years($birth) < 18 &&
                    $patientLinkId > 0
                ) {
                    \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                        "Paciente menor salvo como ficha incompleta. CPF, Nome Completo e Nascimento já foram registrados; cadastre o Responsável Legal para liberar documentos e atos sensíveis.",
                        "warn",
                    );
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patient", ["id" => $patientLinkId]);
                }
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Paciente salvo.");
            } catch (Throwable $e) {
                error_log("[Prontoo patients] " . $e->getMessage());
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash(
                    $e->getMessage() === "Este CPF não existe."
                        ? "Este CPF não existe."
                        : "Não foi possível salvar o paciente. Revise os dados informados.",
                    "bad",
                );
            }
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("patients");
        }
        $search = mb_trim((string) ($_GET["q"] ?? ""));
        $filter = (string) ($_GET["f"] ?? \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_default());
        if (!array_key_exists($filter, \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_options())) {
            $filter = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_default();
        }
        $role = (string) ($c["role"] ?? "");
        $canViewAllPatients = in_array($role, ["gerente", "medico"], true);
        if ($filter === "all" && !$canViewAllPatients) {
            $filter = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_default();
        }
        $searchMode = $search !== "";
        $params = [$cid];
        if (!$searchMode) {
            if ($filter === "today") {
                [$start, $end] = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_today_utc_range($cid);
                $params[] = $start;
                $params[] = $end;
            } elseif ($filter === "week") {
                [$start, $end] = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_week_utc_range($cid);
                $params[] = $start;
                $params[] = $end;
            }
        }
        $searchKind = "none";
        if ($searchMode) {
            $like = "%" . $search . "%";
            $digits = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($search);
            $dateLike = "%" . str_replace("/", "-", $search) . "%";
            if ($digits !== "") {
                $searchKind = "digits";
                $params[] = $like;
                $params[] = "%" . $digits . "%";
                $params[] = "%" . $digits . "%";
                $params[] = $like;
                $params[] = $dateLike;
            } else {
                $searchKind = "text";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $dateLike;
            }
        }
        [$todayStart, $todayEnd] = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_today_utc_range($cid);
        $base = \Prontoo\Runtime\Operational\OperationalComposition::patients()->result('operational.patients.05.page_patients.07', $params, compact('filter', 'searchKind', 'todayStart', 'todayEnd'))->fetchAll();
        [$weekStart, $weekEnd] = \Prontoo\Runtime\Patients\PatientsRuntimeOperations02::patient_week_utc_range($cid);
        $todayCount =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.05.page_patients.08', [$cid, $todayStart, $todayEnd], []) ?? 0);
        $weekCount =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.05.page_patients.09', [$cid, $weekStart, $weekEnd], []) ?? 0);
        $dropoutCount =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.05.page_patients.10', [$cid], []) ?? 0);
        $incompleteCount =
            (int) (\Prontoo\Runtime\Operational\OperationalComposition::patients()->scalar('operational.patients.05.page_patients.11', [$cid], []) ?? 0);
        $statHtml =
            '<section class="patient-directory-overview kpis kpi-info-strip" aria-label="Resumo de pacientes"><div class="patient-kpi-card kpi-card ' .
            ($todayCount > 0 ? "is-total" : "is-muted") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("today") .
            "<p><b>" .
            number_format($todayCount, 0, ",", ".") .
            '</b><span>Hoje</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($weekCount > 0 ? "is-ok" : "is-muted") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_month") .
            "<p><b>" .
            number_format($weekCount, 0, ",", ".") .
            '</b><span>Essa semana</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($dropoutCount > 0 ? "is-warn warn" : "is-ok") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_busy") .
            "<p><b>" .
            number_format($dropoutCount, 0, ",", ".") .
            '</b><span>Desistentes</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($incompleteCount > 0 ? "is-bad warn" : "is-ok") .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("fact_check") .
            "<p><b>" .
            number_format($incompleteCount, 0, ",", ".") .
            "</b><span>Cadastro incompleto</span></p></div></section>";
        $filterIcons = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_icons();
        $normalFilters = "";
        foreach (\Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_options() as $key => $label) {
            if ($key === "all" && !$canViewAllPatients) {
                continue;
            }
            $paramsLink = ["f" => $key];
            $normalFilters .=
                '<a class="patient-filter-chip ' .
                (!$searchMode && $filter === $key ? "active" : "") .
                '" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patients", $paramsLink) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($filterIcons[$key] ?? "filter_list") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                "</span></a>";
        }
        $resultCount = count($base);
        $filters = $normalFilters;
        if ($searchMode) {
            $foundCount = $resultCount;
            $filters =
                '<span class="patient-filter-chip active is-active patient-filter-found" aria-current="page">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("manage_search") .
                "<span>Encontrados</span><small>" .
                number_format($foundCount, 0, ",", ".") .
                "</small></span>" .
                $normalFilters;
        }
        $rows = "";
        foreach ($base as $r) {
            $rows .= \Prontoo\Runtime\Patients\PatientsRuntimeOperations03::patient_directory_card($r, $cid);
        }
        $empty =
            $search !== ""
                ? "Nenhum paciente encontrado para esta busca."
                : "Nenhum paciente neste filtro.";
        $resultCountHtml =
            '<p class="patient-directory-result-count">' .
            number_format($resultCount, 0, ",", ".") .
            " encontrados.</p>";
        $list =
            ($rows !== ""
                ? '<div class="patient-directory-list ds-person-list ds-patient-list">' .
                    $rows .
                    "</div>"
                : '<div class="empty patient-directory-empty">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("manage_search") .
                    "<strong>" .
                    $empty .
                    "</strong><span>Altere a busca ou escolha outro filtro para ampliar os resultados.</span></div>") .
            $resultCountHtml;
        $clear =
            $search !== "" || $filter !== \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_default()
                ? '<a class="ghost small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patients", ["f" => \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_default()]) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                    "<span>Limpar</span></a>"
                : "";
        $existingOpen = "";
        $existingId = (int) ($_GET["existing_patient"] ?? 0);
        if ($existingId > 0) {
            $ex = \Prontoo\Runtime\Operational\OperationalComposition::patients()->row('operational.patients.05.page_patients.12', [$existingId, $cid], []);
            if ($ex) {
                $existingOpen =
                    '<div class="flash warn patient-existing-card"><strong>Este paciente já está cadastrado.</strong><span>Deseja abrir o cadastro de ' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($ex["full_name"]) .
                    '?</span><a class="primary small" href="' .
                    \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient", ["id" => (int) $ex["id"]]) .
                    '">Abrir cadastro</a></div>';
            }
        }
        $suggestUrl = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient_suggest");
        $searchBar =
            '<section class="patient-directory-search ds-search-block"><form method="get" class="patient-search-bar" role="search" data-patient-live-search="patients"><input type="hidden" name="r" value="patients"><input type="hidden" name="f" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($searchMode ? \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_default() : $filter) .
            '"><label class="search-field"><input name="q" type="search" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($search) .
            '" placeholder="Nome, CPF, telefone ou nascimento" autocomplete="off" aria-label="Buscar paciente por nome, CPF, telefone ou nascimento" data-patient-live-input data-patient-suggest-url="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($suggestUrl) .
            '"></label><button class="primary small" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("search") .
            "<span>Busca rápida</span></button>" .
            $clear .
            "</form></section>";
        $filterList =
            '<nav class="patient-filter-chips ds-filter-list-chips" aria-label="Filtros de pacientes" data-patient-filter-nav>' .
            $filters .
            "</nav><template data-patient-filter-base>" .
            $normalFilters .
            '</template><div data-patient-live-results="patients">' .
            $list .
            "</div>";
        $suggest = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations07::person_autosuggest_datalist($cid);
        $patientSaveLocked = $existingOpen !== "";
        $patientCancelParams = [];
        if ($search !== "") {
            $patientCancelParams["q"] = $search;
        } elseif ($filter !== \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_default()) {
            $patientCancelParams["f"] = $filter;
        }
        $patientCancelUrl = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patients", $patientCancelParams);
        $patientSaveActions =
            '<div class="form-actions"><a class="ghost" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($patientCancelUrl) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
            '<span>Cancelar</span></a><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_submit_icon("Salvar paciente")) .
            "<span>Salvar paciente</span></button></div>";
        if ($patientSaveLocked) {
            $patientSaveActions = str_replace(
                '<button type="submit"',
                '<button type="submit" disabled aria-disabled="true"',
                $patientSaveActions,
            );
        }
        $newPatientForm =
            '<form method="post" class="compact patient-cpf-first-form" data-patient-existing-lock="' .
            ($patientSaveLocked ? "1" : "0") .
            '">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "CPF",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "cpf",
                    "text",
                    "",
                    'required inputmode="numeric" maxlength="14" data-cpf-mask data-patient-cpf-lookup="' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patient_lookup")) .
                        '" autocomplete="off"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome completo do paciente",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "name",
                    "text",
                    "",
                    'required autocomplete="name" list="prontoo_person_suggestions" data-person-autosuggest data-patient-cpf-name',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nascimento",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("birth_date", "date", "", "required data-patient-cpf-birth"),
            ) .
            '<div class="flash info patient-minor-form-note"><strong>Cadastro mínimo:</strong> CPF, Nome Completo e Nascimento. Se for menor de idade, a ficha será salva como incompleta até vincular Responsável Legal.</div>' .
            '<div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Telefone",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "phone",
                    "text",
                    "",
                    'required autocomplete="tel" inputmode="tel"',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "E-mail",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("email", "email", "", 'required autocomplete="email"'),
            ) .
            "</div>" .
            \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_address_fields($cid, []) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Observações importantes:", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea("notes")) .
            $suggest .
            $patientSaveActions .
            "</form>";
        if (isset($_GET["new"]) && (string) $_GET["new"] !== "0") {
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "Novo Paciente",
                \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Novo Paciente", "") .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($newPatientForm, "patient-new-screen-card"),
            );
            return;
        }
        $newPatientParams = ["new" => 1];
        if ($search !== "") {
            $newPatientParams["q"] = $search;
        } elseif ($filter !== \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_directory_filter_default()) {
            $newPatientParams["f"] = $filter;
        }
        $headAction =
            '<a class="pagehead-control pagehead-control--primary" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("patients", $newPatientParams) .
            '">' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Novo Paciente", "person_add") .
            "</a>";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Pacientes",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Pacientes",
                "Lista clínica para localizar, conferir e abrir fichas com rapidez.",
                $headAction,
            ) .
                $existingOpen .
                $statHtml .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($searchBar, "patient-search-card ds-search-card") .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                    $filterList,
                    "patient-list-card patient-directory-card ds-filter-list-block",
                ),
        );
    
    }

    public static function patient_appointment_duration_label(
        null|string|int $startAt,
        null|string|int $endAt,
    ): string 
    {
    
        $s = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_parse_db_utc($startAt);
        $e = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_parse_db_utc($endAt);
        if (!$s || !$e || $e <= $s) {
            return "—";
        }
        $minutes = (int) max(
            1,
            round(($e->getTimestamp() - $s->getTimestamp()) / 60, 0, \RoundingMode::HalfAwayFromZero),
        );
        if ($minutes < 60) {
            return $minutes . " min";
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h . "h" . ($m > 0 ? sprintf("%02d", $m) : "");
    
    }

    public static function patient_appointment_elapsed_until_now_label(
        null|string|int $startAt,
    ): string 
    {
    
        $s = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_parse_db_utc($startAt);
        if (!$s) {
            return "—";
        }
        $minutes = (int) max(1, floor((time() - $s->getTimestamp()) / 60));
        if ($minutes < 60) {
            return $minutes . " min em andamento";
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h . "h" . ($m > 0 ? sprintf("%02d", $m) : "") . " em andamento";
    
    }

    public static function patient_appointment_not_started_label(
        array $a,
        int $cid = 0,
        array $context = [],
    ): string 
    {
    
        $scheduled =
            $cid > 0
                ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($a["start_at"] ?? null, $cid, $context)
                : \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_parse_db_utc($a["start_at"] ?? null);
        $code = \Prontoo\Domain\Patients\PatientsDomainOperations01::patient_appointment_code($a);
        if ($scheduled && $scheduled->getTimestamp() > time()) {
            return "não iniciado";
        }
        if (
            in_array(
                $code,
                [
                    "agendado",
                    "confirmado",
                    "chegou",
                    "em_preparo",
                    "pronto_atendimento",
                    "reagendado",
                    "cancelado",
                    "nao_compareceu",
                ],
                true,
            )
        ) {
            return "não iniciado";
        }
        return "não registrado";
    
    }
}
