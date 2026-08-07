<?php
declare(strict_types=1);

$testStorageRoot =
    sys_get_temp_dir() .
    "/prontoo-security-regression-" .
    bin2hex(random_bytes(8));
$GLOBALS["prontoo_test_mfa_mode"] = "absent";
$GLOBALS["prontoo_test_mfa_record"] = null;
$GLOBALS["prontoo_test_has_cfg"] = true;

function storage_path(string $suffix = ""): string
{
    $root = (string) $GLOBALS["testStorageRoot"];
    return $suffix === "" ? $root : $root . "/" . ltrim($suffix, "/");
}

function cfg(): array
{
    return ["secret" => str_repeat("s", 48)];
}

function has_cfg(): bool
{
    return (bool) $GLOBALS["prontoo_test_has_cfg"];
}

function val(string $sql, array $params = []): mixed
{
    if (str_contains($sql, "meta_key='app_secret'")) {
        return str_repeat("s", 48);
    }
    if (str_contains($sql, "SELECT meta_value FROM pi_meta")) {
        $mode = (string) $GLOBALS["prontoo_test_mfa_mode"];
        if ($mode === "throw") {
            throw new RuntimeException("persistência indisponível");
        }
        if ($mode === "corrupt") {
            return '{"v":';
        }
        return $mode === "active"
            ? (string) $GLOBALS["prontoo_test_mfa_record"]
            : null;
    }
    if (str_contains($sql, "GET_LOCK") || str_contains($sql, "RELEASE_LOCK")) {
        return 1;
    }
    return null;
}

function security_storage_deny_file(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    file_put_contents($dir . "/.htaccess", "Require all denied\n");
    file_put_contents($dir . "/index.html", "");
}

function security_regression_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$GLOBALS["testStorageRoot"] = $testStorageRoot;
mkdir($testStorageRoot, 0750, true);

require dirname(__DIR__) . "/app/Support/SecurityAccess.php";
require dirname(__DIR__) . "/app/Support/Telemetry.php";
require dirname(__DIR__) . "/app/Domain/Audit/AuditActivity.php";

security_regression_assert(
    mfa_enrollment_state(7) === "inactive",
    "MFA ausente não foi reconhecido como inativo.",
);
$GLOBALS["prontoo_test_has_cfg"] = false;
security_regression_assert(
    mfa_enrollment_state(7) === "unavailable",
    "Instalação indisponível não fez MFA falhar fechado.",
);
$GLOBALS["prontoo_test_has_cfg"] = true;

$testMfaSecret = mfa_totp_secret_generate();
$GLOBALS["prontoo_test_mfa_record"] = json_encode(
    [
        "v" => 1,
        "secret" => mfa_secret_encrypt($testMfaSecret),
        "recovery" => [],
        "last_counter" => -1,
    ],
    JSON_THROW_ON_ERROR,
);
$GLOBALS["prontoo_test_mfa_mode"] = "active";
security_regression_assert(
    mfa_enrollment_state(7) === "active",
    "MFA íntegro não foi reconhecido como ativo.",
);
$GLOBALS["prontoo_test_mfa_mode"] = "corrupt";
security_regression_assert(
    mfa_enrollment_state(7) === "unavailable",
    "Cadastro MFA corrompido não falhou fechado.",
);
$GLOBALS["prontoo_test_mfa_mode"] = "throw";
security_regression_assert(
    mfa_enrollment_state(7) === "unavailable",
    "Falha de persistência MFA não falhou fechado.",
);

$publicAuditContext = [
    "_audit_user_id" => 999,
    "_audit_created_at" => "2000-01-01 00:00:00",
    "_skip_runtime_context" => 1,
    "safe" => "preserved",
];
$publicOrigin = audit_trusted_origin_resolve(
    $publicAuditContext,
    null,
);
security_regression_assert(
    !$publicOrigin["has_user_id"] &&
        !$publicOrigin["skip_runtime_context"] &&
        $publicAuditContext === ["safe" => "preserved"],
    "Campos públicos conseguiram controlar a autoria da auditoria.",
);
$trustedAuditContext = ["safe" => "preserved"];
$trustedOrigin = audit_trusted_origin_resolve(
    $trustedAuditContext,
    [
        "user_id" => 41,
        "created_at" => "2026-07-27 12:00:00",
        "skip_runtime_context" => true,
    ],
);
security_regression_assert(
    $trustedOrigin["has_user_id"] &&
        $trustedOrigin["user_id"] === 41 &&
        $trustedOrigin["skip_runtime_context"] &&
        $trustedOrigin["created_at"] === "2026-07-27 12:00:00",
    "A interface interna de autoria não preservou origem confiável.",
);

$telemetryNowUs = 2000000000000000;
$telemetryWindowUs = telemetry_comparison_microseconds();
$telemetryPreviousStartUs = $telemetryNowUs - 2 * $telemetryWindowUs;
$telemetryCurrentStartUs = $telemetryNowUs - $telemetryWindowUs;
$telemetryEvent = static function (
    string $route,
    int $finishedUs,
    int $durationNs,
): array {
    $startedNs = 1000000000000;
    return telemetry_build_event(
        $route,
        $startedNs,
        $startedNs + $durationNs,
        $finishedUs - intdiv($durationNs, 1000),
        $finishedUs,
        200,
        null,
        "test",
    );
};
foreach ([
    $telemetryEvent("patient", $telemetryPreviousStartUs, 200000000),
    $telemetryEvent("landing", $telemetryCurrentStartUs - 1, 500000000),
    $telemetryEvent("patient", $telemetryCurrentStartUs, 100000000),
    $telemetryEvent("landing", $telemetryNowUs - 1, 300000000),
    $telemetryEvent(
        "old",
        $telemetryNowUs - telemetry_retention_microseconds() - 1,
        999000000,
    ),
    $telemetryEvent("future_boundary", $telemetryNowUs, 400000000),
] as $event) {
    security_regression_assert(
        telemetry_append_event($event),
        "Evento canônico de telemetria não foi persistido.",
    );
}
$telemetrySummary = telemetry_comparative_summary($telemetryNowUs);
$telemetryCurrent = (array) ($telemetrySummary["current"] ?? []);
$telemetryPrevious = (array) ($telemetrySummary["previous"] ?? []);
$telemetryVariations = (array) ($telemetrySummary["variations"] ?? []);
security_regression_assert(
    (int) ($telemetryCurrent["requests"] ?? -1) === 2 &&
        (int) ($telemetryPrevious["requests"] ?? -1) === 2,
    "Janelas móveis sobrepuseram ou perderam eventos de fronteira.",
);
security_regression_assert(
    abs((float) ($telemetryCurrent["average_ms"] ?? 0) - 200.0) < 0.000001 &&
        abs((float) ($telemetryPrevious["average_ms"] ?? 0) - 350.0) < 0.000001,
    "Média ponderada das rotas está matematicamente incorreta.",
);
security_regression_assert(
    abs((float) ($telemetryCurrent["landing_average_ms"] ?? 0) - 300.0) < 0.000001 &&
        abs((float) ($telemetryPrevious["landing_average_ms"] ?? 0) - 500.0) < 0.000001,
    "Média isolada da Landing Page está matematicamente incorreta.",
);
security_regression_assert(
    abs((float) ($telemetryVariations["requests_pct"] ?? 999) - 0.0) < 0.000001 &&
        abs((float) ($telemetryVariations["average_ms_pct"] ?? 999) + 42.857143) < 0.000001 &&
        abs((float) ($telemetryVariations["landing_average_ms_pct"] ?? 999) + 40.0) < 0.000001,
    "Variações percentuais da telemetria estão incorretas.",
);
security_regression_assert(
    telemetry_nullable_percentage_variation(10.0, null) === null &&
        telemetry_percentage_variation(0, 0) === 0.0 &&
        telemetry_percentage_variation(1, 0) === null,
    "Denominador zero foi apresentado como percentual definido.",
);
$removedTelemetryEvents = telemetry_prune($telemetryNowUs);
$telemetryLines = file(telemetry_file(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
security_regression_assert(
    $removedTelemetryEvents === 1 &&
        is_array($telemetryLines) &&
        count($telemetryLines) === 5,
    "Retenção configurada não removeu somente o evento anterior à fronteira.",
);
foreach ($telemetryLines as $line) {
    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    security_regression_assert(
        is_array($decoded) && telemetry_normalize_event($decoded) !== null,
        "telemetria.json não contém exatamente um evento JSON válido por linha.",
    );
}

$authSource = (string) file_get_contents(
    dirname(__DIR__) . "/app/Auth/AuthOnboarding.php",
);
$cronSource = (string) file_get_contents(
    dirname(__DIR__) . "/cron/maestro.php",
);
foreach (
    [
        "profile_mfa_recovery_ack",
        "profile_mfa_recovery_regenerate",
        "profile_mfa_replace_prepare",
        "profile_mfa_replace_enable",
        "profile_mfa_disable",
        "profile_mfa_recovery_codes_issued_at",
        "mfa_enrollment_state(\$uid)",
    ]
    as $required
) {
    security_regression_assert(
        str_contains($authSource, $required),
        "Ciclo MFA incompleto: " . $required,
    );
}
security_regression_assert(
    str_contains($cronSource, '$__prontooCronDeadline') &&
        !str_contains(
            $cronSource,
            "PRONTOO_MAESTRO_CRON_BUDGET_MS - \$piBudget",
        ),
    "Maestro não usa orçamento global compartilhado.",
);

echo json_encode(
    [
        "ok" => true,
        "mfa_states" => ["inactive", "active", "unavailable"],
        "audit_public_origin_rejected" => true,
        "telemetry_current_requests" => (int) $telemetryCurrent["requests"],
        "telemetry_previous_requests" => (int) $telemetryPrevious["requests"],
        "telemetry_retention_removed" => $removedTelemetryEvents,
        "mfa_lifecycle_actions" => 5,
        "maestro_shared_budget" => true,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
) . PHP_EOL;
