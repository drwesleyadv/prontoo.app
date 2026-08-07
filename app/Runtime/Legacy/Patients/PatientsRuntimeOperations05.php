<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Patients;

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
    
        $c = require_can("patients");
        $cid = (int) $c["clinic_id"];
        patient_guardians_ensure_schema();
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $cpf = only_digits((string) ($_POST["cpf"] ?? ""));
            $birth = (string) ($_POST["birth_date"] ?? "");
            $name = mb_trim((string) ($_POST["name"] ?? ""));
            if (valid_cpf($cpf) && ($name === "" || !valid_birth_date($birth))) {
                $identity = patient_identity_by_cpf($cpf, $cid);
                if ($identity) {
                    if ($name === "") {
                        $name = mb_trim((string) ($identity["full_name"] ?? ""));
                    }
                    if (!valid_birth_date($birth)) {
                        $birth = app_date_input_from_storage(
                            $identity["birth_date"] ?? "",
                        );
                    }
                }
            }
            if ($name === "") {
                flash("Informe o nome completo do paciente.", "bad");
                redirect("patients");
            }
            if (!valid_cpf($cpf)) {
                flash("Este CPF não existe.", "bad");
                redirect("patients");
            }
            if (!valid_birth_date($birth)) {
                flash("Informe nascimento plausível do paciente.", "bad");
                redirect("patients");
            }
            try {
                $existingPerson = (int) val(
                    "SELECT id FROM pi_persons WHERE cpf=? LIMIT 1",
                    [$cpf],
                );
                if ($existingPerson > 0) {
                    $active = one(
                        "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=1 LIMIT 1",
                        [$cid, $existingPerson],
                    );
                    if ($active) {
                        flash(
                            "Este paciente já está cadastrado. Deseja abrir o cadastro dele?",
                            "warn",
                        );
                        redirect("patients", [
                            "existing_patient" => (int) $active["id"],
                        ]);
                    }
                    $deleted = one(
                        "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=0 LIMIT 1",
                        [$cid, $existingPerson],
                    );
                    if ($deleted) {
                        q(
                            "UPDATE pi_patients SET active=1,registration_needs_update=1,deleted_at=NULL,deleted_by=NULL,restored_at=NOW(),restored_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?",
                            [(int) $c["user"]["id"], (int) $deleted["id"], $cid],
                        );
                        audit(
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
                        flash(
                            "Paciente recuperado com os dados já existentes. Revise e atualize o cadastro.",
                            "warn",
                        );
                        redirect("patient", ["id" => (int) $deleted["id"]]);
                    }
                }
                $pid = upsert_person($name, $cpf, $birth);
                $loc = patient_location_from_post($cid);
                $contact = patient_invoice_contact_from_post();
                q(
                    "INSERT INTO pi_patients (clinic_id,person_id,phone,email,address,address_zip,address_number,address_neighborhood,address_complement,address_state,address_city,address_city_ibge,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE phone=VALUES(phone),email=VALUES(email),address=VALUES(address),address_zip=VALUES(address_zip),address_number=VALUES(address_number),address_neighborhood=VALUES(address_neighborhood),address_complement=VALUES(address_complement),address_state=VALUES(address_state),address_city=VALUES(address_city),address_city_ibge=VALUES(address_city_ibge),notes=VALUES(notes),active=1,registration_needs_update=0,deleted_at=NULL,deleted_by=NULL,updated_at=NOW()",
                    [
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
                    ],
                );
                $link = one(
                    "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=1 LIMIT 1",
                    [$cid, $pid],
                );
                $patientLinkId = (int) ($link["id"] ?? 0);
                counter_inc("patient_writes_total");
                clinic_metric_inc($cid, "patient_writes");
                audit(
                    "paciente_salvo",
                    "paciente",
                    $patientLinkId ?: $pid,
                    array_merge($_POST, [
                        "patient_name" => $name,
                        "acao_paciente" => "cadastrou",
                    ]),
                );
                if (
                    patient_age_years($birth) !== null &&
                    patient_age_years($birth) < 18 &&
                    $patientLinkId > 0
                ) {
                    flash(
                        "Paciente menor salvo como ficha incompleta. CPF, Nome Completo e Nascimento já foram registrados; cadastre o Responsável Legal para liberar documentos e atos sensíveis.",
                        "warn",
                    );
                    redirect("patient", ["id" => $patientLinkId]);
                }
                flash("Paciente salvo.");
            } catch (Throwable $e) {
                error_log("[Prontoo patients] " . $e->getMessage());
                flash(
                    $e->getMessage() === "Este CPF não existe."
                        ? "Este CPF não existe."
                        : "Não foi possível salvar o paciente. Revise os dados informados.",
                    "bad",
                );
            }
            redirect("patients");
        }
        $search = mb_trim((string) ($_GET["q"] ?? ""));
        $filter = (string) ($_GET["f"] ?? patient_directory_filter_default());
        if (!array_key_exists($filter, patient_directory_filter_options())) {
            $filter = patient_directory_filter_default();
        }
        $searchMode = $search !== "";
        $params = [$cid];
        $where = "pp.clinic_id=? AND pp.active=1";
        if (!$searchMode) {
            $where .= patient_directory_filter_where($filter, $params, "pp", "p");
        }
        if ($searchMode) {
            $like = "%" . $search . "%";
            $digits = only_digits($search);
            $dateLike = "%" . str_replace("/", "-", $search) . "%";
            if ($digits !== "") {
                $where .=
                    " AND (p.full_name LIKE ? OR p.cpf LIKE ? OR pp.phone LIKE ? OR DATE_FORMAT(p.birth_date,'%d/%m/%Y') LIKE ? OR p.birth_date LIKE ?)";
                $params[] = $like;
                $params[] = "%" . $digits . "%";
                $params[] = "%" . $digits . "%";
                $params[] = $like;
                $params[] = $dateLike;
            } else {
                $where .=
                    " AND (p.full_name LIKE ? OR pp.email LIKE ? OR DATE_FORMAT(p.birth_date,'%d/%m/%Y') LIKE ? OR p.birth_date LIKE ?)";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $dateLike;
            }
        }
        $metrics = patient_directory_select_metrics_sql($cid);
        $order = $searchMode
            ? "p.full_name ASC, pp.id DESC"
            : patient_directory_order_sql($filter);
        $base = q(
            "SELECT pp.id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,pp.created_at,pp.updated_at,pp.registration_needs_update,p.full_name,p.cpf,p.birth_date,(SELECT COUNT(*) FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1) guardian_count $metrics FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE $where ORDER BY $order LIMIT 120",
            $params,
        )->fetchAll();
        [$todayStart, $todayEnd] = patient_today_utc_range($cid);
        [$weekStart, $weekEnd] = patient_week_utc_range($cid);
        $todayCount =
            (int) (val(
                "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND start_at>=? AND start_at<? AND status NOT IN ('cancelado','nao_compareceu')",
                [$cid, $todayStart, $todayEnd],
            ) ?? 0);
        $weekCount =
            (int) (val(
                "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND start_at>=? AND start_at<? AND status NOT IN ('cancelado','nao_compareceu')",
                [$cid, $weekStart, $weekEnd],
            ) ?? 0);
        $dropoutCount =
            (int) (val(
                "SELECT COUNT(DISTINCT patient_link_id) FROM pi_appointments WHERE clinic_id=? AND patient_link_id IS NOT NULL AND status IN ('cancelado','nao_compareceu') AND COALESCE(updated_at,created_at,start_at)>=DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$cid],
            ) ?? 0);
        $incompleteCount =
            (int) (val(
                "SELECT COUNT(*) FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.clinic_id=? AND pp.active=1 AND ((p.birth_date IS NOT NULL AND p.birth_date>DATE_SUB(CURDATE(), INTERVAL 18 YEAR) AND NOT EXISTS (SELECT 1 FROM pi_patient_guardians pg WHERE pg.clinic_id=pp.clinic_id AND pg.patient_link_id=pp.id AND pg.active=1)) OR COALESCE(pp.phone,'')='' OR COALESCE(pp.updated_at,pp.created_at,0)<DATE_SUB(NOW(), INTERVAL 180 DAY))",
                [$cid],
            ) ?? 0);
        $statHtml =
            '<section class="patient-directory-overview kpis kpi-info-strip" aria-label="Resumo de pacientes"><div class="patient-kpi-card kpi-card ' .
            ($todayCount > 0 ? "is-total" : "is-muted") .
            '">' .
            icon("today") .
            "<p><b>" .
            number_format($todayCount, 0, ",", ".") .
            '</b><span>Hoje</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($weekCount > 0 ? "is-ok" : "is-muted") .
            '">' .
            icon("calendar_month") .
            "<p><b>" .
            number_format($weekCount, 0, ",", ".") .
            '</b><span>Essa semana</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($dropoutCount > 0 ? "is-warn warn" : "is-ok") .
            '">' .
            icon("event_busy") .
            "<p><b>" .
            number_format($dropoutCount, 0, ",", ".") .
            '</b><span>Desistentes</span></p></div><div class="patient-kpi-card kpi-card ' .
            ($incompleteCount > 0 ? "is-bad warn" : "is-ok") .
            '">' .
            icon("fact_check") .
            "<p><b>" .
            number_format($incompleteCount, 0, ",", ".") .
            "</b><span>Cadastro incompleto</span></p></div></section>";
        $filterIcons = patient_directory_filter_icons();
        $normalFilters = "";
        foreach (patient_directory_filter_options() as $key => $label) {
            $paramsLink = ["f" => $key];
            $normalFilters .=
                '<a class="patient-filter-chip ' .
                (!$searchMode && $filter === $key ? "active" : "") .
                '" href="' .
                href("patients", $paramsLink) .
                '">' .
                icon($filterIcons[$key] ?? "filter_list") .
                "<span>" .
                e($label) .
                "</span></a>";
        }
        $filters = $normalFilters;
        if ($searchMode) {
            $foundCount = count($base);
            $filters =
                '<span class="patient-filter-chip active is-active patient-filter-found" aria-current="page">' .
                icon("manage_search") .
                "<span>Encontrados</span><small>" .
                number_format($foundCount, 0, ",", ".") .
                "</small></span>" .
                $normalFilters;
        }
        $rows = "";
        foreach ($base as $r) {
            $rows .= patient_directory_card($r, $cid);
        }
        $empty =
            $search !== ""
                ? "Nenhum paciente encontrado para esta busca."
                : "Nenhum paciente neste filtro.";
        $list =
            $rows !== ""
                ? '<div class="patient-directory-list ds-person-list ds-patient-list">' .
                    $rows .
                    "</div>"
                : '<div class="empty patient-directory-empty">' .
                    icon("manage_search") .
                    "<strong>" .
                    $empty .
                    "</strong><span>Altere a busca ou escolha outro filtro para ampliar os resultados.</span></div>";
        $clear =
            $search !== "" || $filter !== patient_directory_filter_default()
                ? '<a class="ghost small" href="' .
                    href("patients", ["f" => patient_directory_filter_default()]) .
                    '">' .
                    icon("close") .
                    "<span>Limpar</span></a>"
                : "";
        $existingOpen = "";
        $existingId = (int) ($_GET["existing_patient"] ?? 0);
        if ($existingId > 0) {
            $ex = one(
                "SELECT pp.id,p.full_name,p.cpf,p.birth_date FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1",
                [$existingId, $cid],
            );
            if ($ex) {
                $existingOpen =
                    '<div class="flash warn patient-existing-card"><strong>Este paciente já está cadastrado.</strong><span>Deseja abrir o cadastro de ' .
                    e($ex["full_name"]) .
                    '?</span><a class="primary small" href="' .
                    href("patient", ["id" => (int) $ex["id"]]) .
                    '">Abrir cadastro</a></div>';
            }
        }
        $suggestUrl = href("patient_suggest");
        $searchBar =
            '<section class="patient-directory-search ds-search-block"><form method="get" class="patient-search-bar" role="search" data-patient-live-search="patients"><input type="hidden" name="r" value="patients"><input type="hidden" name="f" value="' .
            e($searchMode ? patient_directory_filter_default() : $filter) .
            '"><label class="search-field"><input name="q" type="search" value="' .
            e($search) .
            '" placeholder="Nome, CPF, telefone ou nascimento" autocomplete="off" aria-label="Buscar paciente por nome, CPF, telefone ou nascimento" data-patient-live-input data-patient-suggest-url="' .
            e($suggestUrl) .
            '"></label><button class="primary small" type="submit">' .
            icon("search") .
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
        $suggest = person_autosuggest_datalist($cid);
        $patientSaveLocked = $existingOpen !== "";
        $patientCancelParams = [];
        if ($search !== "") {
            $patientCancelParams["q"] = $search;
        } elseif ($filter !== patient_directory_filter_default()) {
            $patientCancelParams["f"] = $filter;
        }
        $patientCancelUrl = href("patients", $patientCancelParams);
        $patientSaveActions =
            '<div class="form-actions"><a class="ghost" href="' .
            e($patientCancelUrl) .
            '">' .
            icon("close") .
            '<span>Cancelar</span></a><button type="submit" class="primary">' .
            icon(form_submit_icon("Salvar paciente")) .
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
            csrf_field() .
            form_row(
                "CPF",
                input(
                    "cpf",
                    "text",
                    "",
                    'required inputmode="numeric" maxlength="14" data-cpf-mask data-patient-cpf-lookup="' .
                        e(href("patient_lookup")) .
                        '" autocomplete="off"',
                ),
            ) .
            form_row(
                "Nome completo do paciente",
                input(
                    "name",
                    "text",
                    "",
                    'required autocomplete="name" list="prontoo_person_suggestions" data-person-autosuggest data-patient-cpf-name',
                ),
            ) .
            form_row(
                "Nascimento",
                input("birth_date", "date", "", "required data-patient-cpf-birth"),
            ) .
            '<div class="flash info patient-minor-form-note"><strong>Cadastro mínimo:</strong> CPF, Nome Completo e Nascimento. Se for menor de idade, a ficha será salva como incompleta até vincular Responsável Legal.</div>' .
            '<div class="two">' .
            form_row(
                "Telefone",
                input(
                    "phone",
                    "text",
                    "",
                    'required autocomplete="tel" inputmode="tel"',
                ),
            ) .
            form_row(
                "E-mail",
                input("email", "email", "", 'required autocomplete="email"'),
            ) .
            "</div>" .
            patient_address_fields($cid, []) .
            form_row("Observações importantes:", textarea("notes")) .
            $suggest .
            $patientSaveActions .
            "</form>";
        if (isset($_GET["new"]) && (string) $_GET["new"] !== "0") {
            page(
                "Novo Paciente",
                page_head("Novo Paciente", "") .
                    card($newPatientForm, "patient-new-screen-card"),
            );
            return;
        }
        $newPatientParams = ["new" => 1];
        if ($search !== "") {
            $newPatientParams["q"] = $search;
        } elseif ($filter !== patient_directory_filter_default()) {
            $newPatientParams["f"] = $filter;
        }
        $headAction =
            '<a class="primary small cmdlike" href="' .
            href("patients", $newPatientParams) .
            '">' .
            action_summary_label("Novo Paciente", "person_add") .
            "</a>";
        page(
            "Pacientes",
            page_head(
                "Pacientes",
                "Lista clínica para localizar, conferir e abrir fichas com rapidez.",
                $headAction,
            ) .
                $existingOpen .
                $statHtml .
                card($searchBar, "patient-search-card ds-search-card") .
                card(
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
    
        $s = app_parse_db_utc($startAt);
        $e = app_parse_db_utc($endAt);
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
    
        $s = app_parse_db_utc($startAt);
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
                ? app_db_utc_to_local($a["start_at"] ?? null, $cid, $context)
                : app_parse_db_utc($a["start_at"] ?? null);
        $code = patient_appointment_code($a);
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
