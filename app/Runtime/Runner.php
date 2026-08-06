<?php
declare(strict_types=1);
function prontoo_route_map(): array
{

    return [
        "home",
        "status",
        "login",
        "login_telemetry_wave",
        "login_autotest",
        "mfa",
        "mobile_web_access",
        "goal_status",
        "signup",
        "logout",
        "switch",
        "profile",
        "global_reauth",
        "onboarding",
        "painel",
        "operations",
        "maestro",
        "leads",
        "appointments",
        "patients",
        "patient",
        "patient_lookup",
        "lead_lookup",
        "lead_patient_lookup",
        "patient_suggest",
        "person_lookup",
        "counterparty_lookup",
        "counterparty_suggest",
        "procedures",
        "financial",
        "creditors",
        "tasks",
        "documents",
        "document_view",
        "document_print",
        "document_pdf",
        "document_pdf_file",
        "notices",
        "users",
        "user",
        "permissions",
        "audit",
        "settings",
        "admin_painel",
        "admin_stats",
        "admin_clinics",
        "admin_onboarding",
        "admin_users",
        "admin_people",
        "admin_operations",
        "admin_global_notices",
        "admin_alerts",
        "admin_maintenance",
        "admin_health",
        "admin_performance",
        "admin_deleted",
        "admin_errors",
        "admin_diagnostics",
        "admin_integrity",
        "admin_security",
        "admin_settings",
        "admin_payment_proof",
        "admin_audit",
    ];
}
function prontoo_public_runtime_routes(): array
{

    return [
        "status",
        "login",
        "login_telemetry_wave",
        "login_autotest",
        "mfa",
        "mobile_web_access",
        "signup",
        "logout",
    ];
}
function prontoo_json_runtime_routes(): array
{

    return [
        "login_telemetry_wave",
        "patient_lookup",
        "patient_suggest",
        "person_lookup",
        "lead_lookup",
        "lead_patient_lookup",
        "counterparty_lookup",
        "counterparty_suggest",
        "goal_status",
    ];
}
function prontoo_route_wants_json(string $route): bool
{

    $accept = strtolower((string) ($_SERVER["HTTP_ACCEPT"] ?? ""));
    return in_array($route, prontoo_json_runtime_routes(), true) ||
        str_contains($accept, "application/json");
}
function prontoo_json_response(array $payload, int $status = 200): void
{

    if (!headers_sent()) {
        http_response_code($status);
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("X-Robots-Tag: noindex, nofollow");
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
function prontoo_json_failure_message(
    string $route,
    int $status,
    Throwable $e,
): string {

    if ($status < 500) {
        return $e->getMessage();
    }
    if (
        in_array(
            $route,
            ["patient_lookup", "patient_suggest", "person_lookup"],
            true,
        )
    ) {
        return "Não foi possível verificar os dados do paciente agora. Tente novamente em instantes.";
    }
    if (in_array($route, ["lead_lookup", "lead_patient_lookup"], true)) {
        return "Não foi possível verificar os dados do interessado agora. Tente novamente em instantes.";
    }
    if (
        in_array($route, ["counterparty_lookup", "counterparty_suggest"], true)
    ) {
        return "Não foi possível verificar os dados financeiros agora. Tente novamente em instantes.";
    }
    return "Não foi possível concluir esta verificação agora. Tente novamente em instantes.";
}
function prontoo_route_is_public_light(string $route): bool
{

    if (in_array($route, ["login", "login_telemetry_wave", "login_autotest", "mfa", "logout"], true)) {
        return true;
    }
    return in_array(
        $route,
        ["mobile_web_access", "signup", "status"],
        true,
    ) && strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")) === "GET";
}
function prontoo_schema_boot_marker_path(): string
{

    $dir = storage_path("cache");
    if (!is_dir($dir)) {
        prontoo_fs_mkdir($dir);
    }
    return $dir .
        "/prontoo_schema_boot_" .
        hash(
            "sha256",
            (defined("PRONTOO_SCHEMA_REV") ? PRONTOO_SCHEMA_REV : "schema") .
                "|" .
                (defined("PRONTOO_VERSION") ? PRONTOO_VERSION : "version"),
        ) .
        ".json";
}
function prontoo_schema_boot_marker_valid(int $ttlSeconds = 0): bool
{

    if ($ttlSeconds <= 0) {
        $ttlSeconds = defined("PRONTOO_RUNTIME_DEEP_BOOT_TTL_SECONDS")
            ? (int) PRONTOO_RUNTIME_DEEP_BOOT_TTL_SECONDS
            : 43200;
    }
    $file = prontoo_schema_boot_marker_path();
    if (!is_file($file) || time() - filemtime($file) > $ttlSeconds) {
        return false;
    }
    $raw = prontoo_fs_read($file, false);
    $json = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($json) &&
        !empty($json["ok"]) &&
        ($json["rev"] ?? "") ===
            (defined("PRONTOO_SCHEMA_REV") ? PRONTOO_SCHEMA_REV : "");
}
function prontoo_schema_boot_mark_ok(string $mode): void
{

    $payload = json_encode(
        [
            "ok" => true,
            "mode" => $mode,
            "rev" => PRONTOO_SCHEMA_REV,
            "version" => PRONTOO_VERSION,
            "at" => date("c"),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
    if (is_string($payload)) {
        prontoo_fs_write(prontoo_schema_boot_marker_path(), $payload);
    }
}
function prontoo_boot_database_for_route(string $route): void
{

    if (!has_cfg()) {
        return;
    }
    $publicLight = prontoo_route_is_public_light($route);
    $forceDeep =
        PHP_SAPI === "cli" &&
        getenv("PRONTOO_FORCE_DEEP_BOOT") === "1";
    if ($publicLight && !$forceDeep) {
        if (class_exists("\Prontoo\Core\Integrity\PiIntegrity")) {
            \Prontoo\Core\Integrity\PiIntegrity::bootIndexLightcheck();
        }
        return;
    }
    $mustDeep =
        $forceDeep ||
        !prontoo_schema_boot_marker_valid(
            defined("PRONTOO_RUNTIME_DEEP_BOOT_TTL_SECONDS")
                ? (int) PRONTOO_RUNTIME_DEEP_BOOT_TTL_SECONDS
                : 43200,
        );
    if ($mustDeep) {
        prontoo_run_runtime_maintenance_cycle("route_deep");
        return;
    }
    if (class_exists("\Prontoo\Core\Integrity\PiIntegrity")) {
        \Prontoo\Core\Integrity\PiIntegrity::bootIndexLightcheck();
    }
}
function prontoo_run_runtime_maintenance_cycle(
    string $mode = "route_deep",
    int $uid = 0,
): array {

    $startedAt = microtime(true);
    $result = ["ok" => false, "mode" => $mode, "uid" => $uid, "steps" => []];
    $lockDir = storage_path("cache/locks");
    if (!is_dir($lockDir) &&
        !@mkdir($lockDir, 0750, true) &&
        !is_dir($lockDir)) {
        return $result + [
            "ran" => false,
            "reason" => "maintenance_lock_unavailable",
        ];
    }
    $lockHandle = @fopen(
        $lockDir . "/runtime-maintenance-" . hash("sha256", PRONTOO_SCHEMA_REV) . ".lock",
        "c+",
    );
    if (!is_resource($lockHandle) ||
        !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        if (is_resource($lockHandle)) {
            fclose($lockHandle);
        }
        return $result + [
            "ok" => true,
            "ran" => false,
            "reason" => "maintenance_in_progress",
        ];
    }
    try {
        if (
            in_array($mode, ["route_deep", "post_password_login"], true) &&
            prontoo_schema_boot_marker_valid()
        ) {
            return $result + [
                "ok" => true,
                "ran" => false,
                "reason" => "maintenance_already_completed",
            ];
        }
        prontoo_load_full_runtime_modules();
        ensure_runtime_schema_minimum();
        $result["steps"][] = "schema_contract";

        if (function_exists("maestro_ensure_schema")) {
            maestro_ensure_schema();
            $result["steps"][] = "maestro_contract";
        }
        if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
            \Prontoo\Core\Integrity\PiIntegrity::bootIndexAutotest();
            $result["steps"][] = "integrity_autotest";
        }

        runtime_self_check();
        $result["steps"][] = "runtime_self_check";

        if (function_exists("document_pdf_cleanup_due")) {
            document_pdf_cleanup_due();
            $result["steps"][] = "pdf_cleanup";
        }

        prontoo_schema_boot_mark_ok($mode);
        $result["ok"] = true;
        $result["ran"] = true;
        $result["duration_ms"] = (int) round(
            (microtime(true) - $startedAt) * 1000,
        );
       , \RoundingMode::HalfAwayFromZero return $result;
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function prontoo_login_post_password_maintenance(int $uid): array
{

    static $done = false;
    if ($done) {
        return [
            "ok" => true,
            "ran" => false,
            "reason" => "already_ran_request",
        ];
    }
    $done = true;
    if (prontoo_schema_boot_marker_valid()) {
        return [
            "ok" => true,
            "ran" => false,
            "reason" => "runtime_marker_fresh",
            "uid" => $uid,
            "steps" => [],
        ];
    }
    try {
        $result = prontoo_run_runtime_maintenance_cycle(
            "post_password_login",
            $uid,
        );
        if (function_exists("audit")) {
            try {
                audit("login_manutencao_pos_senha", "plataforma", $uid, [
                    "duration_ms" => (int) ($result["duration_ms"] ?? 0),
                    "steps" => $result["steps"] ?? [],
                    "audit_body" =>
                        "Manutenção pesada do runtime executada após confirmação da senha, antes de liberar a sessão autenticada.",
                ]);
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo post password maintenance audit] " .
                        $e->getMessage(),
                );
            }
        }
        return $result + ["ran" => true];
    } catch (Throwable $e) {
        error_log("[Prontoo post password maintenance] " . $e->getMessage());
        if (function_exists("audit")) {
            try {
                audit("login_manutencao_pos_senha_falhou", "plataforma", $uid, [
                    "erro_hash" => hash("sha256", $e->getMessage()),
                    "audit_body" =>
                        "A manutenção pesada pós-senha falhou antes da liberação da sessão autenticada.",
                ]);
            } catch (Throwable $auditError) {
                error_log(
                    "[Prontoo post password maintenance fail audit] " .
                        $auditError->getMessage(),
                );
            }
        }
        throw $e;
    }
}
function prontoo_flush_integrity_before_render(): void
{

    try {
        if (function_exists("pdo") && has_cfg()) {
            $pdo = pdo();
            if (
                $pdo instanceof PDO &&
                !$pdo->inTransaction() &&
                class_exists("\Prontoo\Core\Integrity\PiIntegrity") &&
                method_exists(
                    "\Prontoo\Core\Integrity\PiIntegrity",
                    "flushFastEvents",
                )
            ) {
                \Prontoo\Core\Integrity\PiIntegrity::flushFastEvents();
            }
        }
    } catch (Throwable $e) {
        error_log("[Prontoo render integrity flush] " . $e->getMessage());
    }
}
function prontoo_run(bool $installMode = false): void
{

    try {
        boot_security();
        guard_request();
        $r = route();
        telemetry_route_identify($r);
        $publicTelemetry = $r === "login_telemetry_wave";
        $publicStatus = $r === "status" || $publicTelemetry;
        $publicHome = false;
        headers_secure($publicStatus);
        if (!has_cfg() && !$installMode && !$publicHome) {
            throw new ProntooHttpError(
                503,
                is_file(storage_path("install.lock"))
                    ? "Instalação existente detectada, mas app/config.php não foi encontrado. O instalador está bloqueado: restaure o arquivo de configuração da instalação atual."
                    : "Configuração ausente. A instalação desta publicação está encerrada e não pode ser iniciada por HTTP; restaure app/config.php a partir do ambiente comissionado.",
            );
        }
        prontoo_boot_database_for_route($r);
        $isPost = ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST";
        if ($isPost) {
            if ($r !== "login_autotest") {
                check_csrf();
            } elseif (empty($_SESSION["csrf"])) {
                csrf();
            }
        }
        $map = prontoo_route_map();
        if (
            $r === "onboarding" &&
            (int) ($_SESSION["clinic_id"] ?? 0) > 0 &&
            function_exists("ensure_clinic_trial_active")
        ) {
            ensure_clinic_trial_active((int) $_SESSION["clinic_id"], true);
        }
        $cNow = $publicStatus || $publicHome || $r === "logout" ? [] : ctx();
        if (!$publicTelemetry) {
            enforce_read_only($cNow, $r);
        }
        if ($r !== "logout") {
            if (!$publicTelemetry) {
                enforce_action_integrity($cNow, $r);
            }
        }
        if ($isPost) {
            if (
                function_exists(
                    "server_json_cache_schedule_invalidation_for_write",
                )
            ) {
                server_json_cache_schedule_invalidation_for_write(
                    $r,
                    (string) ($_POST["act"] ?? ""),
                );
            }
            if ((string) ($_POST["act"] ?? "") === "onboarding_tip_dismiss") {
                onboarding_tip_dismiss();
            }
        }
        if (
            !$publicStatus &&
            !$publicHome &&
            function_exists("maintenance_active") &&
            maintenance_active() &&
            (!$cNow || ($cNow["scope"] ?? "") !== "global") &&
            !in_array(
                $r,
                ["login", "login_autotest", "mfa", "logout"],
                true,
            )
        ) {
            if (prontoo_route_wants_json($r)) {
                prontoo_json_response(
                    [
                        "ok" => false,
                        "found" => false,
                        "message" =>
                            "Sistema temporariamente em manutenção. Tente novamente em instantes.",
                    ],
                    503,
                );
                return;
            }
            prontoo_load_route_modules("admin_health");
            page_maintenance_notice();
            return;
        }
        if (
            $cNow &&
            ($cNow["scope"] ?? "") === "clinic" &&
            function_exists("onboarding_pending") &&
            onboarding_pending($cNow) &&
            !in_array($r, ["onboarding", "logout", "switch", "settings"], true)
        ) {
            if (prontoo_route_wants_json($r)) {
                prontoo_json_response(
                    [
                        "ok" => false,
                        "found" => false,
                        "message" =>
                            "Finalize a configuração inicial do consultório antes de usar esta busca.",
                    ],
                    409,
                );
                return;
            }
            redirect("onboarding");
        }
        if (
            $cNow &&
            ($cNow["scope"] ?? "") === "clinic" &&
            function_exists("financial_cashier_requires_attention_light") &&
            financial_cashier_requires_attention_light($cNow) &&
            !in_array(
                $r,
                ["financial", "logout", "switch", "goal_status", "notices"],
                true,
            )
        ) {
            if (prontoo_route_wants_json($r)) {
                prontoo_json_response(
                    [
                        "ok" => false,
                        "found" => false,
                        "message" =>
                            "Há uma pendência operacional no financeiro antes desta busca.",
                    ],
                    409,
                );
                return;
            }
            redirect("financial");
        }
        prontoo_load_route_modules($r);
        if ($r !== "logout" && !$publicStatus) {
            prontoo_flush_integrity_before_render();
        }
        $fn = in_array($r, $map, true) ? "page_" . $r : "page_home";
        if (!function_exists($fn)) {
            throw new RuntimeException("Rota sem função de página: " . $fn);
        }
        $fn();
    } catch (Throwable $e) {
        if (
            function_exists("financial_cash_debug_request_active") &&
            financial_cash_debug_request_active() &&
            function_exists("financial_cash_debug_failure_page")
        ) {
            financial_cash_debug_failure_page($e);
        }
        $status = $e instanceof ProntooHttpError ? (int) $e->status : 500;
        $routeForError = function_exists("route") ? route() : "";
        if (
            function_exists("prontoo_route_wants_json") &&
            prontoo_route_wants_json($routeForError)
        ) {
            error_log(
                "[Prontoo json route failure] " .
                    $routeForError .
                    " | " .
                    $e->getMessage() .
                    " in " .
                    $e->getFile() .
                    ":" .
                    $e->getLine(),
            );
            prontoo_json_response(
                [
                    "ok" => false,
                    "found" => false,
                    "message" => prontoo_json_failure_message(
                        $routeForError,
                        $status,
                        $e,
                    ),
                ],
                $status,
            );
            return;
        }
        app_fail($e);
    }
}
function prontoo_patient_tab_active_rows(int $clinicId, int $patientId): array
{
    return \Prontoo\Infrastructure\Patients\PatientTabReadRepository::activeTabs($clinicId, $patientId);
}
function prontoo_patient_tab_label_by_id(int $tabId): string
{
    return \Prontoo\Infrastructure\Patients\PatientTabReadRepository::labelById($tabId);
}
function prontoo_patient_tab_icon_picker(array $options, string $current): string
{
    return \Prontoo\Presentation\Patients\PatientTabView::iconPicker(
        $options,
        $current,
        static fn(string $value): string => e($value),
        static fn(string $name): string => icon($name),
    );
}
function prontoo_onboarding_tip_render(
    array $tip,
    string $key,
    string $return,
    string $csrfField,
): string {
    return \Prontoo\Presentation\Auth\OnboardingTipView::render(
        $tip,
        $key,
        $return,
        $csrfField,
        static fn(string $value): string => e($value),
        static fn(string $name): string => icon($name),
    );
}
function prontoo_patient_read_service(): \Prontoo\Application\Patients\PatientReadService
{
    static $service = null;
    if (!$service instanceof \Prontoo\Application\Patients\PatientReadService) {
        $service = new \Prontoo\Application\Patients\PatientReadService(
            new \Prontoo\Infrastructure\Patients\PdoPatientReadRepository(),
        );
    }
    return $service;
}
function prontoo_patient_appointment_registration_block_reason(
    int $clinicId,
    int $patientId,
): ?string {
    return prontoo_patient_read_service()->appointmentRegistrationBlockReason(
        $clinicId,
        $patientId,
        static fn(array $patient): bool => patient_invoice_registration_complete($patient),
        static fn(array $patient): string => patient_invoice_registration_alert_message($patient),
    );
}
function prontoo_patient_legal_guardians(int $clinicId, int $patientId): array
{
    return prontoo_patient_read_service()->legalGuardians(
        $clinicId,
        $patientId,
        static fn(string $cpf): string => only_digits($cpf),
        static fn(string $relationship): string => normalize_guardian_relationship($relationship),
    );
}
function prontoo_patient_has_legal_guardian(int $clinicId, int $patientId): bool
{
    return prontoo_patient_read_service()->hasLegalGuardian($clinicId, $patientId);
}
function prontoo_patient_reception_history_read_service(): \Prontoo\Application\Patients\PatientReceptionHistoryReadService
{
    static $service = null;
    if (!$service instanceof \Prontoo\Application\Patients\PatientReceptionHistoryReadService) {
        $service = new \Prontoo\Application\Patients\PatientReceptionHistoryReadService(
            new \Prontoo\Infrastructure\Patients\PdoPatientReceptionHistoryReadRepository(),
        );
    }
    return $service;
}
function prontoo_patient_reception_history_read_model(
    int $clinicId,
    int $patientId,
    int $personId,
    string $phoneDigits,
): array {
    return prontoo_patient_reception_history_read_service()->read(
        $clinicId,
        $patientId,
        $personId,
        $phoneDigits,
    );
}
function prontoo_patient_tab_command_service(): \Prontoo\Application\Patients\PatientTabCommandService
{
    static $service = null;
    if (!$service instanceof \Prontoo\Application\Patients\PatientTabCommandService) {
        $service = new \Prontoo\Application\Patients\PatientTabCommandService(
            new \Prontoo\Infrastructure\Patients\PdoPatientTabCommandRepository(),
        );
    }
    return $service;
}
function prontoo_create_patient_tab_command(
    int $clinicId,
    int $patientId,
    string $label,
    string $iconName,
    int $userId,
): array {
    return prontoo_patient_tab_command_service()->create(
        $clinicId,
        $patientId,
        $label,
        $iconName,
        $userId,
    );
}

function prontoo_patient_contact_edit_form(array $patient, int $clinicId): string
{
    return \Prontoo\Presentation\Patients\PatientContactView::editForm(
        $patient,
        $clinicId,
        static fn(string $label, string $iconName): string => action_summary_label($label, $iconName),
        static fn(): string => csrf_field(),
        static fn(string $name, string $type, mixed $value, string $attributes): string => input(
            $name,
            $type,
            $value,
            $attributes,
        ),
        static fn(string $label, string $control): string => form_row($label, $control),
        static fn(int $cid, array $value): string => patient_address_fields($cid, $value),
        static fn(string $label): string => form_actions($label),
    );
}

function prontoo_patient_contact_command_service(): \Prontoo\Application\Patients\PatientContactCommandService
{
    static $service = null;
    if (!$service instanceof \Prontoo\Application\Patients\PatientContactCommandService) {
        $service = new \Prontoo\Application\Patients\PatientContactCommandService(
            new \Prontoo\Infrastructure\Patients\PdoPatientContactCommandRepository(),
        );
    }
    return $service;
}
function prontoo_update_patient_contact_command(
    int $clinicId,
    int $patientId,
    int $userId,
    array $contact,
): array {
    return prontoo_patient_contact_command_service()->update(
        $clinicId,
        $patientId,
        $userId,
        $contact,
    );
}

function prontoo_patient_revenue_receipt_service(): \Prontoo\Application\Financial\PatientRevenueReceiptService
{
    static $service = null;
    if (!$service instanceof \Prontoo\Application\Financial\PatientRevenueReceiptService) {
        $service = new \Prontoo\Application\Financial\PatientRevenueReceiptService(
            new \Prontoo\Infrastructure\Financial\PdoPatientRevenueReceiptRepository(),
        );
    }
    return $service;
}
function prontoo_receive_patient_revenue_command(
    int $clinicId,
    int $patientId,
    int $revenueId,
    int $userId,
    string $role,
): array {
    return prontoo_patient_revenue_receipt_service()->receive(
        $clinicId,
        $patientId,
        $revenueId,
        $userId,
        $role,
    );
}
