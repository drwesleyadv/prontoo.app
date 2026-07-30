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

$telemetryEvent = [
    "ts" => time(),
    "route" => "logout",
    "release" => "1.7.27.3",
    "elapsed_ms" => 12.5,
    "query_ms" => 2.0,
    "queries" => 1,
    "success" => 1,
];
$deferredId = maestro_deferred_enqueue("telemetry", [
    "event" => $telemetryEvent,
    "include_page_metric" => true,
]);
security_regression_assert(
    is_string($deferredId) && $deferredId !== "",
    "Não foi possível criar envelope de telemetria.",
);
$deferredFile =
    maestro_deferred_storage_dir() . "/" . $deferredId . ".json";
$deferredRaw = file_get_contents($deferredFile);
security_regression_assert(
    is_string($deferredRaw) && $deferredRaw !== "",
    "Envelope de telemetria não foi persistido.",
);
$partialEvent = $telemetryEvent;
$partialEvent["deferred_id"] = $deferredId;
security_regression_assert(
    telemetry_append_route_performance_metric($partialEvent),
    "Não foi possível simular interrupção após a métrica de rota.",
);
$firstPass = maestro_process_deferred_work(5000, 10);
security_regression_assert(
    (int) ($firstPass["processed"] ?? 0) === 1,
    "Retomada idempotente após materialização parcial falhou.",
);
file_put_contents($deferredFile, $deferredRaw);
$replayPass = maestro_process_deferred_work(5000, 10);
security_regression_assert(
    (int) ($replayPass["processed"] ?? 0) === 1,
    "Replay idempotente da telemetria não foi concluído.",
);
$routePayload = json_decode(
    (string) file_get_contents(telemetry_route_perf_file()),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$pageEvents = telemetry_read_events();
$pageDeferredIds = [];
foreach ($pageEvents as $pageEvent) {
    if (!is_array($pageEvent)) {
        continue;
    }
    $pageDeferredId = (string) ($pageEvent["deferred_id"] ?? "");
    if ($pageDeferredId !== "") {
        $pageDeferredIds[$pageDeferredId] = true;
    }
}
$routeCount = 0;
foreach ((array) ($routePayload["daily_requests"] ?? []) as $row) {
    $routeCount += (int) ($row["count"] ?? 0);
}
security_regression_assert(
    $routeCount === 1 &&
        count($pageEvents) === 1 &&
        count((array) ($routePayload["deferred_ids"] ?? [])) === 1 &&
        count($pageDeferredIds) <= 1,
    "Replay duplicou métricas de rota ou página.",
);

file_put_contents(
    maestro_deferred_storage_dir() . "/invalid.json",
    '{"tampered":true}',
);
$invalidPass = maestro_process_deferred_work(5000, 10);
security_regression_assert(
    (int) ($invalidPass["invalid"] ?? 0) === 1 &&
        (int) ($invalidPass["dead_letter"] ?? 0) >= 1 &&
        !empty($invalidPass["alert"]),
    "Envelope inválido não foi supervisionado em fila morta.",
);
$retryId = maestro_deferred_enqueue("telemetry", [
    "event" => [],
    "include_page_metric" => false,
]);
security_regression_assert(
    is_string($retryId) && $retryId !== "",
    "Não foi possível criar envelope para prova de tentativas.",
);
for ($attempt = 0; $attempt < 5; $attempt++) {
    maestro_process_deferred_work(5000, 10);
}
$healthPayload = json_decode(
    (string) file_get_contents(
        maestro_deferred_storage_dir() .
            "/state/deferred-work.json",
    ),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
security_regression_assert(
    ($healthPayload["status"] ?? "") === "attention" &&
        (int) ($healthPayload["stats"]["dead_letter"] ?? 0) >= 2,
    "Supervisão persistente não registrou tentativas esgotadas.",
);

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
        "telemetry_replay_count" => $routeCount,
        "dead_letter" => (int) (
            $healthPayload["stats"]["dead_letter"] ?? 0
        ),
        "mfa_lifecycle_actions" => 5,
        "maestro_shared_budget" => true,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
) . PHP_EOL;
