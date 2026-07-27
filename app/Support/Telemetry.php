<?php
declare(strict_types=1);
function pdo_metric(): PDO
{
    /*
     * GUIA DE MANUTENÇÃO — pdo_metric
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “pdo metric”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `cfg`, `array_key_exists`, `RuntimeException`, `PDO`, `db_assert_mysql_runtime`, `db_apply_mysql_session_contract`.
     * Classes ou serviços instanciados: `RuntimeException`, `PDO`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = cfg();
    foreach (["db_host", "db_name", "db_user", "db_pass"] as $k) {
        if (!array_key_exists($k, $c)) {
            throw new RuntimeException("Configuração incompleta: " . $k);
        }
    }
    $dsn =
        "mysql:host=" .
        $c["db_host"] .
        ";dbname=" .
        $c["db_name"] .
        ";charset=utf8mb4";
    $pdo = new PDO($dsn, (string) $c["db_user"], (string) $c["db_pass"], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    db_assert_mysql_runtime($pdo);
    db_apply_mysql_session_contract($pdo, true);
    return $pdo;
}
function telemetry_storage_dir(): string
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_storage_dir
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry storage dir”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_page_file`, `telemetry_prepare_storage`, `telemetry_route_perf_file`.
     * Dependências chamadas: `storage_path`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return storage_path("telemetry");
}
function maestro_deferred_storage_dir(): string
{
    /*
     * GUIA DE MANUTENÇÃO — maestro_deferred_storage_dir
     * Responsabilidade: Resolve o diretório protegido da fila durável de trabalhos secundários consumidos pelo Maestro.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `maestro_deferred_enqueue`, `maestro_process_deferred_work`.
     * Dependências chamadas: `storage_path`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: A fila permanece fora da raiz pública e não substitui a fonte canônica dos dados processados.
     */
    return storage_path("maestro-deferred");
}
function maestro_deferred_canonicalize(mixed $value): mixed
{
    /*
     * GUIA DE MANUTENÇÃO — maestro_deferred_canonicalize
     * Responsabilidade: Ordena mapas recursivamente para produzir assinaturas estáveis dos envelopes adiados.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `maestro_deferred_sign`.
     * Dependências chamadas: `array_is_list`, `ksort`, `maestro_deferred_canonicalize`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Listas preservam sua ordem; somente mapas associativos são ordenados.
     */
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map("maestro_deferred_canonicalize", $value);
    }
    ksort($value);
    foreach ($value as $key => $item) {
        $value[$key] = maestro_deferred_canonicalize($item);
    }
    return $value;
}
function maestro_deferred_sign(array $envelope): string
{
    /*
     * GUIA DE MANUTENÇÃO — maestro_deferred_sign
     * Responsabilidade: Autentica um envelope da fila do Maestro com chave derivada do segredo local da instalação.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `maestro_deferred_enqueue`, `maestro_deferred_envelope_valid`.
     * Dependências chamadas: `cfg`, `secret_key`, `hash`, `json_encode`, `maestro_deferred_canonicalize`, `hash_hmac`.
     * Efeitos colaterais: pode consultar a chave canônica apenas em instalações legadas sem segredo no arquivo de configuração.
     * Cuidado 1: Nunca inclua a assinatura no próprio conteúdo assinado.
     */
    static $key = null;
    if (!is_string($key) || $key === "") {
        $configSecret = trim((string) (cfg()["secret"] ?? ""));
        $source =
            strlen($configSecret) >= 32 ? $configSecret : secret_key();
        $key = hash("sha256", "prontoo|maestro-deferred|" . $source, true);
    }
    $json = json_encode(
        maestro_deferred_canonicalize($envelope),
        JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRESERVE_ZERO_FRACTION,
    );
    if (!is_string($json)) {
        throw new RuntimeException(
            "Envelope secundário inválido para assinatura.",
        );
    }
    return hash_hmac("sha256", $json, $key);
}
function maestro_deferred_enqueue(string $type, array $payload): ?string
{
    /*
     * GUIA DE MANUTENÇÃO — maestro_deferred_enqueue
     * Responsabilidade: Publica atomicamente um trabalho secundário em arquivo exclusivo, sem disputar a trava dos agregados de telemetria.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `maestro_defer_audit_event`, `maestro_defer_telemetry_event`.
     * Dependências chamadas: `has_cfg`, `maestro_deferred_storage_dir`, `mkdir`, `security_storage_deny_file`, `random_bytes`, `microtime`, `maestro_deferred_sign`, `json_encode`, `tempnam`, `file_put_contents`, `chmod`, `rename`, `unlink`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: A publicação por arquivo exclusivo e rename atômico evita uma trava global no caminho crítico HTTP.
     */
    if (!has_cfg() || !in_array($type, ["audit", "telemetry"], true)) {
        return null;
    }
    static $prepared = [];
    $temp = null;
    try {
        $dir = maestro_deferred_storage_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return null;
        }
        if (
            empty($prepared[$dir]) &&
            function_exists("security_storage_deny_file") &&
            (!is_file($dir . "/.htaccess") ||
                !is_file($dir . "/index.html"))
        ) {
            security_storage_deny_file($dir);
        }
        $prepared[$dir] = true;
        $id =
            sprintf("%020d", (int) round(microtime(true) * 1000000)) .
            "-" .
            bin2hex(random_bytes(8));
        $unsigned = [
            "version" => 1,
            "id" => $id,
            "type" => $type,
            "queued_at_utc" => gmdate("Y-m-d H:i:s"),
            "payload" => $payload,
        ];
        $envelope = $unsigned + [
            "signature" => maestro_deferred_sign($unsigned),
        ];
        $json = json_encode(
            $envelope,
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PRESERVE_ZERO_FRACTION,
        );
        if (!is_string($json)) {
            return null;
        }
        $temp = tempnam($dir, ".pending-");
        if (
            !is_string($temp) ||
            @file_put_contents($temp, $json) !== strlen($json)
        ) {
            return null;
        }
        @chmod($temp, 0640);
        $target = $dir . "/" . $id . ".json";
        if (!@rename($temp, $target)) {
            return null;
        }
        $temp = null;
        return $id;
    } catch (Throwable $e) {
        error_log("[Prontoo Maestro defer] " . $e->getMessage());
        return null;
    } finally {
        if (is_string($temp) && is_file($temp)) {
            @unlink($temp);
        }
    }
}
function maestro_defer_audit_event(
    string $event,
    ?string $entity,
    mixed $entityId,
    array $context,
    ?int $actorUserId = null,
): bool {
    /*
     * GUIA DE MANUTENÇÃO — maestro_defer_audit_event
     * Responsabilidade: Captura o contexto mínimo de uma auditoria antes da sessão ser encerrada e a entrega à próxima execução do Maestro.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `page_logout`.
     * Dependências chamadas: `hash`, `maestro_deferred_enqueue`.
     * Estado externo lido: `$_SERVER`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: O endereço de rede é transformado em hash antes de entrar na fila e o agente é limitado ao mesmo tamanho da auditoria canônica.
     */
    $ip = substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45);
    $userAgent = mb_substr(
        trim((string) ($_SERVER["HTTP_USER_AGENT"] ?? "")),
        0,
        180,
    );
    return maestro_deferred_enqueue("audit", [
        "event" => mb_substr($event, 0, 80),
        "entity" =>
            $entity !== null ? mb_substr($entity, 0, 80) : null,
        "entity_id" => (string) $entityId,
        "context" => $context,
        "actor_user_id" =>
            $actorUserId !== null && $actorUserId > 0
                ? $actorUserId
                : null,
        "ip_hash" => $ip !== "" ? hash("sha256", $ip . "|ip") : null,
        "user_agent" => $userAgent !== "" ? $userAgent : null,
        "occurred_at_utc" => gmdate("Y-m-d H:i:s"),
        "proof_context" => [
            "route" => function_exists("route")
                ? (string) route()
                : (string) ($_GET["r"] ?? ""),
            "method" => (string) ($_SERVER["REQUEST_METHOD"] ?? ""),
            "scope" => (string) ($_SESSION["scope"] ?? ""),
            "clinic_id" =>
                (int) ($_SESSION["clinic_id"] ?? 0) > 0
                    ? (int) $_SESSION["clinic_id"]
                    : null,
            "role" => (string) ($_SESSION["role_code"] ?? ""),
        ],
    ]) !== null;
}
function maestro_defer_telemetry_event(
    array $event,
    bool $includePageMetric,
): bool {
    /*
     * GUIA DE MANUTENÇÃO — maestro_defer_telemetry_event
     * Responsabilidade: Enfileira a amostra de desempenho de login ou logout para consolidação posterior pelo Maestro.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_request_metric_shutdown`.
     * Dependências chamadas: `maestro_deferred_enqueue`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Falha de telemetria nunca pode impedir a resposta HTTP nem reativar a consolidação síncrona.
     */
    return maestro_deferred_enqueue("telemetry", [
        "event" => $event,
        "include_page_metric" => $includePageMetric,
    ]) !== null;
}
function maestro_deferred_envelope_valid(array $envelope): bool
{
    /*
     * GUIA DE MANUTENÇÃO — maestro_deferred_envelope_valid
     * Responsabilidade: Valida formato, identidade e assinatura antes de o Maestro executar um trabalho secundário.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `maestro_process_deferred_work`.
     * Dependências chamadas: `preg_match`, `in_array`, `maestro_deferred_sign`, `hash_equals`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Envelopes desconhecidos ou adulterados devem ser colocados em quarentena sem execução.
     */
    $signature = strtolower(trim((string) ($envelope["signature"] ?? "")));
    if (
        (int) ($envelope["version"] ?? 0) !== 1 ||
        preg_match(
            '/^\d{20}-[a-f0-9]{16}$/',
            (string) ($envelope["id"] ?? ""),
        ) !== 1 ||
        !in_array(
            (string) ($envelope["type"] ?? ""),
            ["audit", "telemetry"],
            true,
        ) ||
        !isset($envelope["payload"]) ||
        !is_array($envelope["payload"]) ||
        preg_match('/^[a-f0-9]{64}$/', $signature) !== 1
    ) {
        return false;
    }
    $unsigned = $envelope;
    unset($unsigned["signature"]);
    try {
        return hash_equals(maestro_deferred_sign($unsigned), $signature);
    } catch (Throwable $e) {
        return false;
    }
}
function maestro_process_deferred_audit(array $envelope): bool
{
    /*
     * GUIA DE MANUTENÇÃO — maestro_process_deferred_audit
     * Responsabilidade: Materializa de forma idempotente a auditoria adiada, já fora do caminho crítico do logout.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `maestro_process_deferred_work`.
     * Dependências chamadas: `one`, `audit`.
     * Efeitos colaterais: consulta e grava a camada de persistência; gera trilha de auditoria.
     * Cuidado 1: O identificador do envelope impede duplicação caso o cron termine depois do commit e antes de remover o arquivo.
     */
    if (!function_exists("audit")) {
        return false;
    }
    $id = (string) ($envelope["id"] ?? "");
    $payload = (array) ($envelope["payload"] ?? []);
    $event = mb_substr((string) ($payload["event"] ?? ""), 0, 80);
    if ($event === "") {
        return false;
    }
    $existing = one(
        "SELECT id FROM pi_audit WHERE event_key=? AND JSON_UNQUOTE(JSON_EXTRACT(context_json,'$.deferred_id'))=? ORDER BY id DESC LIMIT 1",
        [$event, $id],
    );
    if ($existing) {
        return true;
    }
    $context = isset($payload["context"]) && is_array($payload["context"])
        ? $payload["context"]
        : [];
    $context["deferred_id"] = $id;
    $context["occurred_at_utc"] = (string) (
        $payload["occurred_at_utc"] ??
        ($envelope["queued_at_utc"] ?? "")
    );
    $context["_audit_user_id"] = isset($payload["actor_user_id"])
        ? (int) $payload["actor_user_id"]
        : null;
    $context["_audit_ip_hash"] = $payload["ip_hash"] ?? null;
    $context["_audit_user_agent"] = $payload["user_agent"] ?? null;
    $context["_audit_created_at"] = $payload["occurred_at_utc"] ?? null;
    $context["_audit_proof_context"] =
        isset($payload["proof_context"]) &&
        is_array($payload["proof_context"])
            ? $payload["proof_context"]
            : null;
    return audit(
        $event,
        isset($payload["entity"]) ? (string) $payload["entity"] : null,
        $payload["entity_id"] ?? null,
        $context,
    );
}
function maestro_process_deferred_telemetry(array $envelope): bool
{
    /*
     * GUIA DE MANUTENÇÃO — maestro_process_deferred_telemetry
     * Responsabilidade: Consolida no Maestro uma amostra de desempenho previamente capturada pelo runtime HTTP.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `maestro_process_deferred_work`.
     * Dependências chamadas: `telemetry_append_route_performance_metric`, `telemetry_append_page_metric`.
     * Efeitos colaterais: acessa o sistema de arquivos; gera telemetria.
     * Cuidado 1: Preserve a amostra original; o tempo de espera da fila não integra a latência da requisição do usuário.
     */
    $payload = (array) ($envelope["payload"] ?? []);
    $event = isset($payload["event"]) && is_array($payload["event"])
        ? $payload["event"]
        : [];
    if ($event === [] || trim((string) ($event["route"] ?? "")) === "") {
        return false;
    }
    telemetry_append_route_performance_metric($event);
    if (!empty($payload["include_page_metric"])) {
        telemetry_append_page_metric($event);
    }
    return true;
}
function maestro_process_deferred_work(
    int $budgetMs = 5000,
    int $limit = 500,
): array {
    /*
     * GUIA DE MANUTENÇÃO — maestro_process_deferred_work
     * Responsabilidade: Consome na execução seguinte do Maestro os trabalhos secundários publicados pelas rotas críticas.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `cron/maestro.php`.
     * Dependências chamadas: `maestro_deferred_storage_dir`, `glob`, `filemtime`, `rename`, `sort`, `microtime`, `file_get_contents`, `json_decode`, `maestro_deferred_envelope_valid`, `maestro_process_deferred_audit`, `maestro_process_deferred_telemetry`, `unlink`.
     * Efeitos colaterais: acessa o sistema de arquivos; pode consultar e gravar a camada de persistência; gera auditoria e telemetria.
     * Cuidado 1: O claim por rename é atômico; arquivos interrompidos são recuperados depois de cinco minutos e a auditoria é idempotente.
     */
    $started = microtime(true);
    $budgetMs = max(250, min(30000, $budgetMs));
    $limit = max(1, min(5000, $limit));
    $stats = [
        "success" => true,
        "queued" => 0,
        "processed" => 0,
        "audit" => 0,
        "telemetry" => 0,
        "invalid" => 0,
        "errors" => 0,
        "remaining" => 0,
        "duration_ms" => 0,
    ];
    $dir = maestro_deferred_storage_dir();
    if (!is_dir($dir)) {
        return $stats;
    }
    foreach ((array) glob($dir . "/*.json.processing") as $stale) {
        if ((int) @filemtime($stale) > 0 && time() - (int) @filemtime($stale) > 300) {
            @rename($stale, substr($stale, 0, -strlen(".processing")));
        }
    }
    $files = (array) glob($dir . "/*.json");
    sort($files, SORT_STRING);
    $stats["queued"] = count($files);
    foreach (array_slice($files, 0, $limit) as $file) {
        if ((microtime(true) - $started) * 1000 >= $budgetMs) {
            break;
        }
        $claimed = $file . ".processing";
        if (!@rename($file, $claimed)) {
            continue;
        }
        $completed = false;
        $invalid = false;
        try {
            $raw = @file_get_contents($claimed);
            $envelope =
                is_string($raw) && trim($raw) !== ""
                    ? json_decode($raw, true)
                    : null;
            if (
                !is_array($envelope) ||
                !maestro_deferred_envelope_valid($envelope)
            ) {
                $invalid = true;
                $stats["invalid"]++;
                continue;
            }
            $type = (string) $envelope["type"];
            $completed =
                $type === "audit"
                    ? maestro_process_deferred_audit($envelope)
                    : maestro_process_deferred_telemetry($envelope);
            if ($completed) {
                $stats["processed"]++;
                $stats[$type]++;
            } else {
                $stats["errors"]++;
            }
        } catch (Throwable $e) {
            $stats["errors"]++;
            error_log("[Prontoo Maestro deferred] " . $e->getMessage());
        } finally {
            if ($completed) {
                @unlink($claimed);
            } elseif ($invalid) {
                @rename($claimed, $file . ".invalid");
            } else {
                @rename($claimed, $file);
            }
        }
    }
    $stats["remaining"] = count((array) glob($dir . "/*.json"));
    $stats["duration_ms"] = (int) round((microtime(true) - $started) * 1000);
    $stats["success"] =
        $stats["errors"] === 0 && $stats["invalid"] === 0;
    return $stats;
}
function telemetry_page_file(): string
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_page_file
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry page file”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_read_events`, `telemetry_append_page_metric`.
     * Dependências chamadas: `telemetry_storage_dir`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return telemetry_storage_dir() . "/page-load.json";
}
function telemetry_cuiaba_tz(): DateTimeZone
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_cuiaba_tz
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry cuiaba tz”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_global_metric_series_24h`, `admin_global_sequence_series_30d`, `telemetry_append_page_metric`, `telemetry_append_route_performance_metric`, `telemetry_route_count_last_days`, `telemetry_route_requests_series_30d`.
     * Dependências chamadas: `DateTimeZone`.
     * Classes ou serviços instanciados: `DateTimeZone`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $tz = null;
    return $tz ?: ($tz = new DateTimeZone("America/Cuiaba"));
}
function telemetry_prepare_storage(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_prepare_storage
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry prepare storage”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_append_page_metric`, `telemetry_append_route_performance_metric`.
     * Dependências chamadas: `telemetry_storage_dir`, `is_dir`, `mkdir`, `is_writable`, `function_exists`, `security_storage_deny_file`, `is_file`, `file_put_contents`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        $dir = telemetry_storage_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }
        if (function_exists("security_storage_deny_file")) {
            security_storage_deny_file($dir);
        } else {
            $deny = $dir . "/.htaccess";
            if (!is_file($deny)) {
                @file_put_contents($deny, "Require all denied\n", LOCK_EX);
            }
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
function telemetry_read_events(): array
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_read_events
     * Responsabilidade: Localiza, carrega ou resolve os dados de “telemetry read events” para consumo pelas camadas superiores.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_global_metric_series_24h`, `telemetry_route_performance_summary`.
     * Dependências chamadas: `is_array`, `telemetry_page_file`, `is_file`, `file_get_contents`, `trim`, `json_decode`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $requestCache = null;
    if (is_array($requestCache)) {
        return $requestCache;
    }
    try {
        $file = telemetry_page_file();
        if (!is_file($file)) {
            return $requestCache = [];
        }
        $raw = @file_get_contents($file);
        if ($raw === false || trim($raw) === "") {
            return $requestCache = [];
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return $requestCache = [];
        }
        $events = $json["events"] ?? [];
        return $requestCache = is_array($events) ? $events : [];
    } catch (Throwable $e) {
        return $requestCache = [];
    }
}
function telemetry_sanitize_events(array $events, int $nowTs): array
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_sanitize_events
     * Responsabilidade: Transforma e normaliza “telemetry sanitize events” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_append_page_metric`.
     * Dependências chamadas: `is_array`, `mb_substr`, `max`, `round`, `count`, `array_slice`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $cut = $nowTs - 25 * 3600;
    $out = [];
    foreach ($events as $ev) {
        if (!is_array($ev)) {
            continue;
        }
        $ts = (int) ($ev["ts"] ?? 0);
        if ($ts < $cut || $ts > $nowTs + 60) {
            continue;
        }
        $out[] = [
            "ts" => $ts,
            "route" => mb_substr((string) ($ev["route"] ?? ""), 0, 80),
            "queries" => max(0, (int) ($ev["queries"] ?? 0)),
            "wide_selects" => max(0, (int) ($ev["wide_selects"] ?? 0)),
            "module_bytes" => max(0, (int) ($ev["module_bytes"] ?? 0)),
            "module_files" => max(0, (int) ($ev["module_files"] ?? 0)),
            "elapsed_ms" => max(
                0.0,
                round((float) ($ev["elapsed_ms"] ?? 0), 3),
            ),
            "query_ms" => max(0.0, round((float) ($ev["query_ms"] ?? 0), 3)),
            "success" => !empty($ev["success"]) ? 1 : 0,
        ];
    }
    if (count($out) > 60000) {
        $out = array_slice($out, -60000);
    }
    return $out;
}
function telemetry_append_page_metric(array $event): void
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_append_page_metric
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry append page metric”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_request_metric_shutdown`.
     * Dependências chamadas: `function_exists`, `telemetry_prepare_storage`, `telemetry_page_file`, `time`, `fopen`, `flock`, `rewind`, `stream_get_contents`, `is_string`, `trim`, `json_decode`, `is_array` e mais 15.
     * Classes ou serviços instanciados: `DateTimeImmutable`.
     * Efeitos colaterais: produz conteúdo de saída; acessa o sistema de arquivos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!function_exists("storage_path") || !telemetry_prepare_storage()) {
        return;
    }
    $file = telemetry_page_file();
    $nowTs = (int) ($event["ts"] ?? time());
    $fh = @fopen($file, "c+");
    if (!$fh) {
        return;
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            return;
        }
        rewind($fh);
        $raw = stream_get_contents($fh);
        $json =
            is_string($raw) && trim($raw) !== "" ? json_decode($raw, true) : [];
        $events =
            is_array($json) &&
            isset($json["events"]) &&
            is_array($json["events"])
                ? $json["events"]
                : [];
        $events = telemetry_sanitize_events($events, $nowTs);
        $events[] = [
            "ts" => $nowTs,
            "route" => mb_substr((string) ($event["route"] ?? ""), 0, 80),
            "queries" => max(0, (int) ($event["queries"] ?? 0)),
            "wide_selects" => max(
                0,
                (int) ($event["wide_selects"] ?? 0),
            ),
            "module_bytes" => max(0, (int) ($event["module_bytes"] ?? 0)),
            "module_files" => max(0, (int) ($event["module_files"] ?? 0)),
            "elapsed_ms" => max(
                0.0,
                round((float) ($event["elapsed_ms"] ?? 0), 3),
            ),
            "query_ms" => max(0.0, round((float) ($event["query_ms"] ?? 0), 3)),
            "success" => !empty($event["success"]) ? 1 : 0,
        ];
        $payload = [
            "timezone" => "America/Cuiaba",
            "retention_hours" => 25,
            "updated_at" => new DateTimeImmutable("@" . $nowTs)
                ->setTimezone(telemetry_cuiaba_tz())
                ->format(DateTimeInterface::ATOM),
            "events" => $events,
        ];
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if ($encoded !== false) {
            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, $encoded);
            fflush($fh);
        }
    } catch (Throwable $ignored) {
        error_log(
            "[Prontoo recoverable " .
                __FUNCTION__ .
                "] " .
                $ignored->getMessage(),
        );
    } finally {
        @flock($fh, LOCK_UN);
        @fclose($fh);
    }
}
function telemetry_route_perf_file(): string
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_route_perf_file
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry route perf file”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_append_route_performance_metric`, `telemetry_route_perf_snapshot`, `telemetry_route_count_last_days`, `telemetry_route_requests_series_30d`.
     * Dependências chamadas: `telemetry_storage_dir`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return telemetry_storage_dir() . "/route-performance.json";
}
function telemetry_route_perf_bucket_seconds(): int
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_route_perf_bucket_seconds
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry route perf bucket seconds”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_route_perf_bucket_start`, `telemetry_append_route_performance_metric`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return 300;
}
function telemetry_route_perf_bucket_start(int $ts): int
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_route_perf_bucket_start
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry route perf bucket start”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_append_route_performance_metric`.
     * Dependências chamadas: `telemetry_route_perf_bucket_seconds`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $size = telemetry_route_perf_bucket_seconds();
    return $ts - ($ts % $size);
}
function telemetry_route_perf_safe_route(string $route): string
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_route_perf_safe_route
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry route perf safe route”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_append_route_performance_metric`, `telemetry_release_performance_summary`, `telemetry_route_performance_summary`, `telemetry_route_count_last_days`.
     * Dependências chamadas: `preg_replace`, `trim`, `substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $route = preg_replace("/[^a-z0-9_\-]/i", "", trim($route)) ?: "unknown";
    return substr($route, 0, 80);
}
function telemetry_release_safe(string $release): string
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_release_safe
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry release safe”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_previous_release`, `telemetry_append_route_performance_metric`, `telemetry_release_performance_summary`.
     * Dependências chamadas: `preg_replace`, `trim`, `substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $release = preg_replace("/[^a-z0-9._\-]/i", "", trim($release)) ?: "unknown";
    return substr($release, 0, 48);
}
function telemetry_previous_release(): string
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_previous_release
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry previous release”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_append_route_performance_metric`, `telemetry_release_performance_summary`.
     * Dependências chamadas: `telemetry_release_safe`, `defined`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return telemetry_release_safe(
        defined("PRONTOO_PREVIOUS_VERSION")
            ? (string) PRONTOO_PREVIOUS_VERSION
            : "previous",
    );
}
function telemetry_route_perf_empty_row(): array
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_route_perf_empty_row
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry route perf empty row”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_route_perf_add_event`, `telemetry_append_route_performance_metric`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return [
        "count" => 0,
        "total_ms" => 0.0,
        "query_ms" => 0.0,
        "queries" => 0,
        "wide_selects" => 0,
        "module_bytes" => 0,
        "module_files" => 0,
        "max_ms" => 0.0,
        "min_ms" => null,
        "success" => 0,
        "errors" => 0,
        "cache_lookups" => 0,
        "cache_hits" => 0,
        "cache_misses" => 0,
        "cache_writes" => 0,
        "cache_loaders" => 0,
    ];
}
function telemetry_route_perf_add_event(
    array $row,
    float $elapsed,
    float $queryMs,
    int $queries,
    bool $success,
    array $cacheTotals = [],
    int $wideSelects = 0,
    int $moduleBytes = 0,
    int $moduleFiles = 0,
): array {
    /*
     * GUIA DE MANUTENÇÃO — telemetry_route_perf_add_event
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry route perf add event”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_append_route_performance_metric`.
     * Dependências chamadas: `array_replace`, `telemetry_route_perf_empty_row`, `round`, `max`, `min`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $row = array_replace(telemetry_route_perf_empty_row(), $row);
    $row["count"] = (int) $row["count"] + 1;
    $row["total_ms"] = round((float) $row["total_ms"] + $elapsed, 3);
    $row["query_ms"] = round((float) $row["query_ms"] + $queryMs, 3);
    $row["queries"] = (int) $row["queries"] + $queries;
    $row["wide_selects"] =
        (int) $row["wide_selects"] + max(0, $wideSelects);
    $row["module_bytes"] =
        (int) $row["module_bytes"] + max(0, $moduleBytes);
    $row["module_files"] =
        (int) $row["module_files"] + max(0, $moduleFiles);
    $row["max_ms"] = max((float) $row["max_ms"], round($elapsed, 3));
    $min = $row["min_ms"];
    $row["min_ms"] = $min === null
        ? round($elapsed, 3)
        : min((float) $min, round($elapsed, 3));
    if ($success) {
        $row["success"] = (int) $row["success"] + 1;
    } else {
        $row["errors"] = (int) $row["errors"] + 1;
    }
    $memoryHits = max(0, (int) ($cacheTotals["memory_hits"] ?? 0));
    $fileHits = max(0, (int) ($cacheTotals["file_hits"] ?? 0));
    $row["cache_lookups"] += max(0, (int) ($cacheTotals["lookups"] ?? 0));
    $row["cache_hits"] += $memoryHits + $fileHits;
    $row["cache_misses"] += max(0, (int) ($cacheTotals["misses"] ?? 0));
    $row["cache_writes"] += max(0, (int) ($cacheTotals["writes"] ?? 0));
    $row["cache_loaders"] += max(0, (int) ($cacheTotals["loaders"] ?? 0));
    return $row;
}
function telemetry_cache_empty_row(): array
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_cache_empty_row
     * Responsabilidade: Gerencia o cache ou a memoização de “telemetry cache empty row”, reduzindo I/O sem substituir a fonte canônica.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_cache_add_metrics`, `telemetry_cache_performance_summary`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return [
        "requests" => 0,
        "lookups" => 0,
        "memory_hits" => 0,
        "file_hits" => 0,
        "misses" => 0,
        "writes" => 0,
        "loaders" => 0,
        "bypasses" => 0,
        "invalidations" => 0,
        "invalidated_categories" => 0,
        "expired" => 0,
        "invalid" => 0,
        "write_errors" => 0,
        "lock_timeouts" => 0,
        "lock_wait_ms" => 0.0,
    ];
}
function telemetry_cache_add_metrics(array $row, array $metrics): array
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_cache_add_metrics
     * Responsabilidade: Gerencia o cache ou a memoização de “telemetry cache add metrics”, reduzindo I/O sem substituir a fonte canônica.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_append_route_performance_metric`.
     * Dependências chamadas: `array_replace`, `telemetry_cache_empty_row`, `round`, `max`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $row = array_replace(telemetry_cache_empty_row(), $row);
    $row["requests"] = (int) $row["requests"] + 1;
    foreach (telemetry_cache_empty_row() as $key => $default) {
        if ($key === "requests") {
            continue;
        }
        $value = $metrics[$key] ?? 0;
        if ($key === "lock_wait_ms") {
            $row[$key] = round((float) $row[$key] + max(0.0, (float) $value), 3);
        } else {
            $row[$key] = (int) $row[$key] + max(0, (int) $value);
        }
    }
    return $row;
}
function telemetry_append_route_performance_metric(array $event): void
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_append_route_performance_metric
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry append route performance metric”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_request_metric_shutdown`, `br_landing_register_route_telemetry`, `closure@br/runtime-telemetry.php:14`.
     * Dependências chamadas: `function_exists`, `telemetry_prepare_storage`, `telemetry_route_perf_file`, `time`, `telemetry_route_perf_bucket_start`, `telemetry_route_perf_safe_route`, `telemetry_release_safe`, `defined`, `max`, `is_array`, `DateTimeImmutable`, `->setTimezone` e mais 25.
     * Classes ou serviços instanciados: `DateTimeImmutable`.
     * Efeitos colaterais: produz conteúdo de saída; acessa o sistema de arquivos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!function_exists("storage_path") || !telemetry_prepare_storage()) {
        return;
    }
    $file = telemetry_route_perf_file();
    $nowTs = (int) ($event["ts"] ?? time());
    $bucket = (string) telemetry_route_perf_bucket_start($nowTs);
    $route = telemetry_route_perf_safe_route(
        (string) ($event["route"] ?? "unknown"),
    );
    $release = telemetry_release_safe(
        (string) ($event["release"] ?? (defined("PRONTOO_VERSION") ? PRONTOO_VERSION : "unknown")),
    );
    $elapsed = max(0.0, (float) ($event["elapsed_ms"] ?? 0));
    $queryMs = max(0.0, (float) ($event["query_ms"] ?? 0));
    $queries = max(0, (int) ($event["queries"] ?? 0));
    $wideSelects = max(0, (int) ($event["wide_selects"] ?? 0));
    $moduleBytes = max(0, (int) ($event["module_bytes"] ?? 0));
    $moduleFiles = max(0, (int) ($event["module_files"] ?? 0));
    $success = !empty($event["success"]);
    $cache = isset($event["cache"]) && is_array($event["cache"])
        ? $event["cache"]
        : [];
    $cacheTotals = isset($cache["totals"]) && is_array($cache["totals"])
        ? $cache["totals"]
        : [];
    $cacheCategories = isset($cache["categories"]) && is_array($cache["categories"])
        ? $cache["categories"]
        : [];
    $dayKey = new DateTimeImmutable("@" . $nowTs)
        ->setTimezone(telemetry_cuiaba_tz())
        ->format("Y-m-d");
    $fh = @fopen($file, "c+");
    if (!$fh) {
        return;
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            return;
        }
        rewind($fh);
        $raw = stream_get_contents($fh);
        $json = is_string($raw) && trim($raw) !== "" ? json_decode($raw, true) : [];
        if (!is_array($json)) {
            $json = [];
        }
        $buckets = isset($json["buckets"]) && is_array($json["buckets"])
            ? $json["buckets"]
            : [];
        $releaseBuckets = isset($json["release_buckets"]) && is_array($json["release_buckets"])
            ? $json["release_buckets"]
            : [];
        if (!$releaseBuckets && $buckets) {
            $previousRelease = telemetry_previous_release();
            foreach ($buckets as $existingBucket => $routes) {
                if (is_array($routes)) {
                    $releaseBuckets[(string) $existingBucket] = [
                        $previousRelease => $routes,
                    ];
                }
            }
        }
        $cacheBuckets = isset($json["cache_buckets"]) && is_array($json["cache_buckets"])
            ? $json["cache_buckets"]
            : [];
        $cut = $nowTs - 25 * 3600;
        foreach ([&$buckets, &$releaseBuckets, &$cacheBuckets] as &$bucketSet) {
            foreach (array_keys($bucketSet) as $key) {
                if ((int) $key < $cut) {
                    unset($bucketSet[$key]);
                }
            }
        }
        unset($bucketSet);

        if (!isset($buckets[$bucket]) || !is_array($buckets[$bucket])) {
            $buckets[$bucket] = [];
        }
        $buckets[$bucket][$route] = telemetry_route_perf_add_event(
            isset($buckets[$bucket][$route]) && is_array($buckets[$bucket][$route])
                ? $buckets[$bucket][$route]
                : [],
            $elapsed,
            $queryMs,
            $queries,
            $success,
            $cacheTotals,
            $wideSelects,
            $moduleBytes,
            $moduleFiles,
        );
        ksort($buckets, SORT_NUMERIC);

        if (!isset($releaseBuckets[$bucket]) || !is_array($releaseBuckets[$bucket])) {
            $releaseBuckets[$bucket] = [];
        }
        if (!isset($releaseBuckets[$bucket][$release]) || !is_array($releaseBuckets[$bucket][$release])) {
            $releaseBuckets[$bucket][$release] = [];
        }
        $releaseBuckets[$bucket][$release][$route] = telemetry_route_perf_add_event(
            isset($releaseBuckets[$bucket][$release][$route]) && is_array($releaseBuckets[$bucket][$release][$route])
                ? $releaseBuckets[$bucket][$release][$route]
                : [],
            $elapsed,
            $queryMs,
            $queries,
            $success,
            $cacheTotals,
            $wideSelects,
            $moduleBytes,
            $moduleFiles,
        );
        ksort($releaseBuckets, SORT_NUMERIC);

        if ($cacheCategories) {
            if (!isset($cacheBuckets[$bucket]) || !is_array($cacheBuckets[$bucket])) {
                $cacheBuckets[$bucket] = [];
            }
            foreach ($cacheCategories as $category => $metrics) {
                if (!is_array($metrics)) {
                    continue;
                }
                $safeCategory = preg_replace("/[^a-z0-9_\-]/i", "_", (string) $category) ?: "general";
                $cacheBuckets[$bucket][$safeCategory] = telemetry_cache_add_metrics(
                    isset($cacheBuckets[$bucket][$safeCategory]) && is_array($cacheBuckets[$bucket][$safeCategory])
                        ? $cacheBuckets[$bucket][$safeCategory]
                        : [],
                    $metrics,
                );
            }
            ksort($cacheBuckets[$bucket], SORT_STRING);
            ksort($cacheBuckets, SORT_NUMERIC);
        }

        $daily = isset($json["daily_requests"]) && is_array($json["daily_requests"])
            ? $json["daily_requests"]
            : [];
        $dailyCut = new DateTimeImmutable("@" . $nowTs)
            ->setTimezone(telemetry_cuiaba_tz())
            ->modify("-35 days")
            ->format("Y-m-d");
        foreach (array_keys($daily) as $key) {
            if ((string) $key < $dailyCut) {
                unset($daily[$key]);
            }
        }
        if (!isset($daily[$dayKey]) || !is_array($daily[$dayKey])) {
            $daily[$dayKey] = telemetry_route_perf_empty_row();
        }
        $daily[$dayKey] = telemetry_route_perf_add_event(
            $daily[$dayKey],
            $elapsed,
            $queryMs,
            $queries,
            $success,
            $cacheTotals,
            $wideSelects,
            $moduleBytes,
            $moduleFiles,
        );
        ksort($daily, SORT_STRING);

        $dailyRoutes = isset($json["daily_routes"]) && is_array($json["daily_routes"])
            ? $json["daily_routes"]
            : [];
        foreach (array_keys($dailyRoutes) as $key) {
            if ((string) $key < $dailyCut) {
                unset($dailyRoutes[$key]);
            }
        }
        if (!isset($dailyRoutes[$dayKey]) || !is_array($dailyRoutes[$dayKey])) {
            $dailyRoutes[$dayKey] = [];
            foreach ($buckets as $bucketTs => $routes) {
                $ts = (int) $bucketTs;
                if ($ts <= 0 || !is_array($routes)) {
                    continue;
                }
                $bucketDay = new DateTimeImmutable("@" . $ts)
                    ->setTimezone(telemetry_cuiaba_tz())
                    ->format("Y-m-d");
                if ($bucketDay !== $dayKey) {
                    continue;
                }
                foreach ($routes as $bucketRoute => $bucketRow) {
                    if (!is_array($bucketRow)) {
                        continue;
                    }
                    $safeBucketRoute = telemetry_route_perf_safe_route((string) $bucketRoute);
                    $dailyRoutes[$dayKey][$safeBucketRoute] =
                        (int) ($dailyRoutes[$dayKey][$safeBucketRoute] ?? 0) +
                        max(0, (int) ($bucketRow["count"] ?? 0));
                }
            }
        } else {
            $dailyRoutes[$dayKey][$route] =
                (int) ($dailyRoutes[$dayKey][$route] ?? 0) + 1;
        }
        ksort($dailyRoutes[$dayKey], SORT_STRING);
        ksort($dailyRoutes, SORT_STRING);

        $payload = [
            "timezone" => "America/Cuiaba",
            "storage" => "json",
            "bucket_seconds" => telemetry_route_perf_bucket_seconds(),
            "retention_hours" => 25,
            "daily_retention_days" => 35,
            "release_measurement" => true,
            "cache_measurement" => true,
            "updated_at" => new DateTimeImmutable("@" . $nowTs)
                ->setTimezone(telemetry_cuiaba_tz())
                ->format(DateTimeInterface::ATOM),
            "buckets" => $buckets,
            "release_buckets" => $releaseBuckets,
            "cache_buckets" => $cacheBuckets,
            "daily_requests" => $daily,
            "daily_routes" => $dailyRoutes,
        ];
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, $encoded);
            fflush($fh);
        }
    } catch (Throwable $ignored) {
        error_log(
            "[Prontoo recoverable " . __FUNCTION__ . "] " . $ignored->getMessage(),
        );
    } finally {
        @flock($fh, LOCK_UN);
        @fclose($fh);
    }
}
function telemetry_route_perf_snapshot(): array
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_route_perf_snapshot
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry route perf snapshot”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `telemetry_cache_performance_summary`, `telemetry_release_performance_summary`, `telemetry_route_performance_summary`.
     * Dependências chamadas: `is_array`, `telemetry_route_perf_file`, `is_file`, `file_get_contents`, `is_string`, `trim`, `json_decode`.
     * Efeitos colaterais: acessa o sistema de arquivos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    static $snapshot = null;
    if (is_array($snapshot)) {
        return $snapshot;
    }
    $file = telemetry_route_perf_file();
    if (!is_file($file)) {
        $snapshot = [];
        return $snapshot;
    }
    $raw = @file_get_contents($file);
    $json = is_string($raw) && trim($raw) !== "" ? json_decode($raw, true) : [];
    $snapshot = is_array($json) ? $json : [];
    return $snapshot;
}
function telemetry_cache_performance_summary(int $hours = 24): array
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_cache_performance_summary
     * Responsabilidade: Gerencia o cache ou a memoização de “telemetry cache performance summary”, reduzindo I/O sem substituir a fonte canônica.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_performance`.
     * Dependências chamadas: `max`, `min`, `time`, `telemetry_route_perf_snapshot`, `is_array`, `telemetry_cache_empty_row`, `preg_replace`, `round`, `array_merge`, `usort`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $hours = max(1, min(168, $hours));
    $cut = time() - $hours * 3600;
    $json = telemetry_route_perf_snapshot();
    $buckets = isset($json["cache_buckets"]) && is_array($json["cache_buckets"])
        ? $json["cache_buckets"]
        : [];
    $totals = telemetry_cache_empty_row();
    $categories = [];
    foreach ($buckets as $bucketTs => $rows) {
        if ((int) $bucketTs < $cut || !is_array($rows)) {
            continue;
        }
        foreach ($rows as $category => $row) {
            if (!is_array($row)) {
                continue;
            }
            $safeCategory = preg_replace("/[^a-z0-9_\-]/i", "_", (string) $category) ?: "general";
            if (!isset($categories[$safeCategory])) {
                $categories[$safeCategory] = telemetry_cache_empty_row();
            }
            foreach (telemetry_cache_empty_row() as $key => $default) {
                $value = $row[$key] ?? 0;
                if ($key === "lock_wait_ms") {
                    $totals[$key] = round((float) $totals[$key] + max(0.0, (float) $value), 3);
                    $categories[$safeCategory][$key] = round(
                        (float) $categories[$safeCategory][$key] + max(0.0, (float) $value),
                        3,
                    );
                } else {
                    $totals[$key] = (int) $totals[$key] + max(0, (int) $value);
                    $categories[$safeCategory][$key] =
                        (int) $categories[$safeCategory][$key] + max(0, (int) $value);
                }
            }
        }
    }
    $format = static function (array $row, string $category = "total"): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Support/Telemetry.php:614
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de serviços transversais de suporte.
         * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `max`, `array_merge`, `round`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $lookups = max(0, (int) ($row["lookups"] ?? 0));
        $memoryHits = max(0, (int) ($row["memory_hits"] ?? 0));
        $fileHits = max(0, (int) ($row["file_hits"] ?? 0));
        $hits = $memoryHits + $fileHits;
        return array_merge($row, [
            "category" => $category,
            "hits" => $hits,
            "hit_rate" => $lookups > 0 ? round(($hits / $lookups) * 100, 1) : 0.0,
            "miss_rate" => $lookups > 0
                ? round((max(0, (int) ($row["misses"] ?? 0)) / $lookups) * 100, 1)
                : 0.0,
            "avg_lock_wait_ms" => max(0, (int) ($row["requests"] ?? 0)) > 0
                ? round((float) ($row["lock_wait_ms"] ?? 0) / max(1, (int) $row["requests"]), 3)
                : 0.0,
        ]);
    };
    $categoryRows = [];
    foreach ($categories as $category => $row) {
        $categoryRows[] = $format($row, $category);
    }
    usort($categoryRows, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn(array $a, array $b): int =>
        ((int) ($b["lookups"] ?? 0)) <=> ((int) ($a["lookups"] ?? 0))
    );
    return [
        "hours" => $hours,
        "updated_at" => (string) ($json["updated_at"] ?? ""),
        "total" => $format($totals),
        "categories" => $categoryRows,
    ];
}
function telemetry_release_performance_summary(int $hours = 24): array
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_release_performance_summary
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry release performance summary”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `max`, `min`, `time`, `telemetry_route_perf_snapshot`, `is_array`, `telemetry_previous_release`, `telemetry_release_safe`, `telemetry_route_perf_safe_route`, `array_merge`, `round`, `defined`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $hours = max(1, min(168, $hours));
    $cut = time() - $hours * 3600;
    $json = telemetry_route_perf_snapshot();
    $releaseBuckets = isset($json["release_buckets"]) && is_array($json["release_buckets"])
        ? $json["release_buckets"]
        : [];
    if (!$releaseBuckets) {
        $buckets = isset($json["buckets"]) && is_array($json["buckets"])
            ? $json["buckets"]
            : [];
        foreach ($buckets as $bucketTs => $routes) {
            if (is_array($routes)) {
                $releaseBuckets[(string) $bucketTs] = [
                    telemetry_previous_release() => $routes,
                ];
            }
        }
    }
    $agg = [];
    foreach ($releaseBuckets as $bucketTs => $releases) {
        if ((int) $bucketTs < $cut || !is_array($releases)) {
            continue;
        }
        foreach ($releases as $release => $routes) {
            if (!is_array($routes)) {
                continue;
            }
            $safeRelease = telemetry_release_safe((string) $release);
            if (!isset($agg[$safeRelease])) {
                $agg[$safeRelease] = [
                    "release" => $safeRelease,
                    "count" => 0,
                    "total_ms" => 0.0,
                    "query_ms" => 0.0,
                    "queries" => 0,
                    "success" => 0,
                    "errors" => 0,
                    "routes" => [],
                ];
            }
            foreach ($routes as $route => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $safeRoute = telemetry_route_perf_safe_route((string) $route);
                $count = max(0, (int) ($row["count"] ?? 0));
                if ($count <= 0) {
                    continue;
                }
                if (!isset($agg[$safeRelease]["routes"][$safeRoute])) {
                    $agg[$safeRelease]["routes"][$safeRoute] = [
                        "route" => $safeRoute,
                        "count" => 0,
                        "total_ms" => 0.0,
                        "query_ms" => 0.0,
                        "queries" => 0,
                        "success" => 0,
                        "errors" => 0,
                    ];
                }
                foreach (["count", "queries", "success", "errors"] as $key) {
                    $value = max(0, (int) ($row[$key] ?? 0));
                    $agg[$safeRelease][$key] += $value;
                    $agg[$safeRelease]["routes"][$safeRoute][$key] += $value;
                }
                foreach (["total_ms", "query_ms"] as $key) {
                    $value = max(0.0, (float) ($row[$key] ?? 0));
                    $agg[$safeRelease][$key] += $value;
                    $agg[$safeRelease]["routes"][$safeRoute][$key] += $value;
                }
            }
        }
    }
    $rows = [];
    foreach ($agg as $release => $row) {
        $routeRows = [];
        foreach ($row["routes"] as $route => $routeRow) {
            $count = max(1, (int) $routeRow["count"]);
            $routeRows[$route] = array_merge($routeRow, [
                "avg_ms" => round((float) $routeRow["total_ms"] / $count, 1),
                "query_avg_ms" => round((float) $routeRow["query_ms"] / $count, 1),
                "queries_avg" => round((float) $routeRow["queries"] / $count, 1),
            ]);
        }
        $count = max(1, (int) $row["count"]);
        $rows[$release] = [
            "release" => $release,
            "count" => (int) $row["count"],
            "avg_ms" => round((float) $row["total_ms"] / $count, 1),
            "query_avg_ms" => round((float) $row["query_ms"] / $count, 1),
            "queries_avg" => round((float) $row["queries"] / $count, 1),
            "success" => (int) $row["success"],
            "errors" => (int) $row["errors"],
            "routes" => $routeRows,
        ];
    }
    return [
        "hours" => $hours,
        "updated_at" => (string) ($json["updated_at"] ?? ""),
        "current_release" => telemetry_release_safe(
            defined("PRONTOO_VERSION") ? (string) PRONTOO_VERSION : "unknown",
        ),
        "previous_release" => telemetry_previous_release(),
        "releases" => $rows,
    ];
}
function telemetry_route_performance_summary(int $hours = 24): array
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_route_performance_summary
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry route performance summary”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_painel`, `page_admin_performance`.
     * Dependências chamadas: `max`, `min`, `telemetry_route_perf_snapshot`, `time`, `is_array`, `telemetry_route_perf_safe_route`, `telemetry_read_events`, `error_log`, `->getMessage`, `round`, `usort`.
     * Efeitos colaterais: gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $hours = max(1, min(168, $hours));
    $jsonSnapshot = telemetry_route_perf_snapshot();
    $nowTs = time();
    $cut = $nowTs - $hours * 3600;
    $agg = [];
    $totalCount = 0;
    $totalMs = 0.0;
    $updated = "";
    try {
        if ($jsonSnapshot) {
            $json = $jsonSnapshot;
            if (is_array($json)) {
                $updated = (string) ($json["updated_at"] ?? "");
                $buckets =
                    isset($json["buckets"]) && is_array($json["buckets"])
                        ? $json["buckets"]
                        : [];
                foreach ($buckets as $bucketTs => $routes) {
                    if ((int) $bucketTs < $cut || !is_array($routes)) {
                        continue;
                    }
                    foreach ($routes as $route => $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $route = telemetry_route_perf_safe_route(
                            (string) $route,
                        );
                        if (!isset($agg[$route])) {
                            $agg[$route] = [
                                "route" => $route,
                                "count" => 0,
                                "total_ms" => 0.0,
                                "query_ms" => 0.0,
                                "queries" => 0,
                                "wide_selects" => 0,
                                "module_bytes" => 0,
                                "module_files" => 0,
                                "max_ms" => 0.0,
                                "min_ms" => null,
                                "success" => 0,
                                "errors" => 0,
                            ];
                        }
                        $count = max(0, (int) ($row["count"] ?? 0));
                        if ($count <= 0) {
                            continue;
                        }
                        $agg[$route]["count"] += $count;
                        $agg[$route]["total_ms"] += max(
                            0.0,
                            (float) ($row["total_ms"] ?? 0),
                        );
                        $agg[$route]["query_ms"] += max(
                            0.0,
                            (float) ($row["query_ms"] ?? 0),
                        );
                        $agg[$route]["queries"] += max(
                            0,
                            (int) ($row["queries"] ?? 0),
                        );
                        $agg[$route]["wide_selects"] += max(
                            0,
                            (int) ($row["wide_selects"] ?? 0),
                        );
                        $agg[$route]["module_bytes"] += max(
                            0,
                            (int) ($row["module_bytes"] ?? 0),
                        );
                        $agg[$route]["module_files"] += max(
                            0,
                            (int) ($row["module_files"] ?? 0),
                        );
                        $agg[$route]["max_ms"] = max(
                            (float) $agg[$route]["max_ms"],
                            max(0.0, (float) ($row["max_ms"] ?? 0)),
                        );
                        $min = $row["min_ms"] ?? null;
                        if ($min !== null) {
                            $agg[$route]["min_ms"] =
                                $agg[$route]["min_ms"] === null
                                    ? (float) $min
                                    : min(
                                        (float) $agg[$route]["min_ms"],
                                        (float) $min,
                                    );
                        }
                        $agg[$route]["success"] += max(
                            0,
                            (int) ($row["success"] ?? 0),
                        );
                        $agg[$route]["errors"] += max(
                            0,
                            (int) ($row["errors"] ?? 0),
                        );
                    }
                }
            }
        }
        if (!$agg) {
            foreach (telemetry_read_events() as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                $ts = (int) ($ev["ts"] ?? 0);
                if ($ts < $cut) {
                    continue;
                }
                $route = telemetry_route_perf_safe_route(
                    (string) ($ev["route"] ?? "unknown"),
                );
                if (!isset($agg[$route])) {
                    $agg[$route] = [
                        "route" => $route,
                        "count" => 0,
                        "total_ms" => 0.0,
                        "query_ms" => 0.0,
                        "queries" => 0,
                        "wide_selects" => 0,
                        "module_bytes" => 0,
                        "module_files" => 0,
                        "max_ms" => 0.0,
                        "min_ms" => null,
                        "success" => 0,
                        "errors" => 0,
                    ];
                }
                $elapsed = max(0.0, (float) ($ev["elapsed_ms"] ?? 0));
                $agg[$route]["count"]++;
                $agg[$route]["total_ms"] += $elapsed;
                $agg[$route]["query_ms"] += max(
                    0.0,
                    (float) ($ev["query_ms"] ?? 0),
                );
                $agg[$route]["queries"] += max(0, (int) ($ev["queries"] ?? 0));
                $agg[$route]["wide_selects"] += max(
                    0,
                    (int) ($ev["wide_selects"] ?? 0),
                );
                $agg[$route]["module_bytes"] += max(
                    0,
                    (int) ($ev["module_bytes"] ?? 0),
                );
                $agg[$route]["module_files"] += max(
                    0,
                    (int) ($ev["module_files"] ?? 0),
                );
                $agg[$route]["max_ms"] = max(
                    (float) $agg[$route]["max_ms"],
                    $elapsed,
                );
                $agg[$route]["min_ms"] =
                    $agg[$route]["min_ms"] === null
                        ? $elapsed
                        : min((float) $agg[$route]["min_ms"], $elapsed);
                if (!empty($ev["success"])) {
                    $agg[$route]["success"]++;
                } else {
                    $agg[$route]["errors"]++;
                }
            }
        }
    } catch (Throwable $ignored) {
        error_log(
            "[Prontoo recoverable " .
                __FUNCTION__ .
                "] " .
                $ignored->getMessage(),
        );
    }
    $rows = [];
    foreach ($agg as $route => $r) {
        $count = max(1, (int) $r["count"]);
        $avg = round((float) $r["total_ms"] / $count, 1);
        $qavg = round((float) $r["query_ms"] / $count, 1);
        $qcount = round((float) $r["queries"] / $count, 1);
        $wideSelects = round((float) $r["wide_selects"] / $count, 1);
        $moduleBytes = round((float) $r["module_bytes"] / $count, 1);
        $moduleFiles = round((float) $r["module_files"] / $count, 1);
        $success = (int) ($r["success"] ?? 0);
        $errors = (int) ($r["errors"] ?? 0);
        $rows[] = [
            "route" => (string) $route,
            "count" => $count,
            "avg_ms" => $avg,
            "query_avg_ms" => $qavg,
            "queries_avg" => $qcount,
            "wide_selects_avg" => $wideSelects,
            "module_bytes_avg" => $moduleBytes,
            "module_files_avg" => $moduleFiles,
            "min_ms" => round((float) ($r["min_ms"] ?? 0), 1),
            "max_ms" => round((float) $r["max_ms"], 1),
            "success" => $success,
            "errors" => $errors,
            "success_rate" => round(
                ($success / max(1, $success + $errors)) * 100,
                1,
            ),
        ];
        $totalCount += $count;
        $totalMs += (float) $r["total_ms"];
    }
    usort($rows, static function ($a, $b) {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Support/Telemetry.php:957
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de serviços transversais de suporte.
         * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $cmp = $b["avg_ms"] <=> $a["avg_ms"];
        if ($cmp !== 0) {
            return $cmp;
        }
        return $b["count"] <=> $a["count"];
    });
    return [
        "hours" => $hours,
        "updated_at" => $updated,
        "total" => $totalCount,
        "avg_ms" => $totalCount > 0 ? round($totalMs / $totalCount, 1) : 0.0,
        "routes" => $rows,
    ];
}

function telemetry_route_count_last_days(string $route, int $days = 7): int
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_route_count_last_days
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry route count last days”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_painel`.
     * Dependências chamadas: `telemetry_route_perf_safe_route`, `max`, `min`, `telemetry_cuiaba_tz`, `DateTimeImmutable`, `->modify`, `->format`, `telemetry_route_perf_file`, `is_file`, `file_get_contents`, `is_string`, `trim` e mais 5.
     * Classes ou serviços instanciados: `DateTimeImmutable`.
     * Efeitos colaterais: acessa o sistema de arquivos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $route = telemetry_route_perf_safe_route($route);
    $days = max(1, min(35, $days));
    $tz = telemetry_cuiaba_tz();
    $today = new DateTimeImmutable("today", $tz);
    $firstDay = $today->modify("-" . ($days - 1) . " days");
    $firstKey = $firstDay->format("Y-m-d");
    $lastKey = $today->format("Y-m-d");
    $total = 0;
    $coveredDays = [];

    try {
        $file = telemetry_route_perf_file();
        if (!is_file($file)) {
            return 0;
        }
        $raw = @file_get_contents($file);
        $json =
            is_string($raw) && trim($raw) !== "" ? json_decode($raw, true) : [];
        if (!is_array($json)) {
            return 0;
        }

        $dailyRoutes =
            isset($json["daily_routes"]) && is_array($json["daily_routes"])
                ? $json["daily_routes"]
                : [];
        foreach ($dailyRoutes as $key => $routes) {
            $key = (string) $key;
            if ($key < $firstKey || $key > $lastKey || !is_array($routes)) {
                continue;
            }
            $total += max(0, (int) ($routes[$route] ?? 0));
            $coveredDays[$key] = true;
        }

        $buckets =
            isset($json["buckets"]) && is_array($json["buckets"])
                ? $json["buckets"]
                : [];
        foreach ($buckets as $bucketTs => $routes) {
            $ts = (int) $bucketTs;
            if ($ts <= 0 || !is_array($routes)) {
                continue;
            }
            $key = new DateTimeImmutable("@" . $ts)
                ->setTimezone($tz)
                ->format("Y-m-d");
            if (
                $key < $firstKey ||
                $key > $lastKey ||
                isset($coveredDays[$key]) ||
                !isset($routes[$route]) ||
                !is_array($routes[$route])
            ) {
                continue;
            }
            $total += max(0, (int) ($routes[$route]["count"] ?? 0));
        }
    } catch (Throwable $ignored) {
        error_log(
            "[Prontoo recoverable " .
                __FUNCTION__ .
                "] " .
                $ignored->getMessage(),
        );
    }

    return $total;
}

function telemetry_route_requests_series_30d(): array
{
    /*
     * GUIA DE MANUTENÇÃO — telemetry_route_requests_series_30d
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “telemetry route requests series 30d”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_global_perf_charts_html`.
     * Dependências chamadas: `telemetry_cuiaba_tz`, `DateTimeImmutable`, `->modify`, `->format`, `telemetry_route_perf_file`, `is_file`, `file_get_contents`, `is_string`, `trim`, `json_decode`, `is_array`, `->setTimezone` e mais 4.
     * Classes ou serviços instanciados: `DateTimeImmutable`.
     * Efeitos colaterais: acessa o sistema de arquivos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $tz = telemetry_cuiaba_tz();
    $today = new DateTimeImmutable("today", $tz);
    $days = [];
    for ($i = 29; $i >= 0; $i--) {
        $day = $today->modify("-" . $i . " days");
        $key = $day->format("Y-m-d");
        $days[$key] = [
            "key" => $key,
            "label" => $day->format("d/m"),
            "tooltip" => $day->format("d/m/Y"),
            "value" => 0,
        ];
    }
    try {
        $file = telemetry_route_perf_file();
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $json =
                is_string($raw) && trim($raw) !== ""
                    ? json_decode($raw, true)
                    : [];
            if (is_array($json)) {
                $daily =
                    isset($json["daily_requests"]) &&
                    is_array($json["daily_requests"])
                        ? $json["daily_requests"]
                        : [];
                foreach ($daily as $key => $row) {
                    if (isset($days[(string) $key]) && is_array($row)) {
                        $days[(string) $key]["value"] +=
                            (int) ($row["count"] ?? 0);
                    }
                }
                $buckets =
                    isset($json["buckets"]) && is_array($json["buckets"])
                        ? $json["buckets"]
                        : [];
                foreach ($buckets as $bucketTs => $routes) {
                    $ts = (int) $bucketTs;
                    if ($ts <= 0 || !is_array($routes)) {
                        continue;
                    }
                    $key = new DateTimeImmutable("@" . $ts)
                        ->setTimezone($tz)
                        ->format("Y-m-d");
                    if (!isset($days[$key])) {
                        continue;
                    }
                    $sum = 0;
                    foreach ($routes as $row) {
                        if (is_array($row)) {
                            $sum += max(0, (int) ($row["count"] ?? 0));
                        }
                    }
                    if ($sum > 0 && empty($daily[$key])) {
                        $days[$key]["value"] += $sum;
                    }
                }
            }
        }
    } catch (Throwable $ignored) {
        error_log(
            "[Prontoo recoverable " .
                __FUNCTION__ .
                "] " .
                $ignored->getMessage(),
        );
    }
    return array_values($days);
}
function request_metric_fatal_error(?array $err): ?string
{
    /*
     * GUIA DE MANUTENÇÃO — request_metric_fatal_error
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “request metric fatal error”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_request_metric_shutdown`.
     * Dependências chamadas: `in_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!$err) {
        return null;
    }
    $fatal = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
        E_RECOVERABLE_ERROR,
    ];
    $type = (int) ($err["type"] ?? 0);
    return in_array($type, $fatal, true) ? "php_" . $type : null;
}
function request_metric_route_name(): string
{
    /*
     * GUIA DE MANUTENÇÃO — request_metric_route_name
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “request metric route name”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_request_metric_shutdown`.
     * Dependências chamadas: `function_exists`, `mb_substr`, `route`.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        return function_exists("route")
            ? mb_substr(route(), 0, 80)
            : mb_substr((string) ($_GET["r"] ?? "home"), 0, 80);
    } catch (Throwable $e) {
        return "unknown";
    }
}
function prontoo_request_metric_shutdown(): void
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_request_metric_shutdown
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “prontoo request metric shutdown”.
     * Local arquitetural: app/Support/Telemetry.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `has_cfg`, `round`, `max`, `microtime`, `http_response_code`, `request_metric_fatal_error`, `error_get_last`, `time`, `request_metric_route_name`, `defined`, `function_exists`, `server_json_cache_metrics_snapshot` e mais 5.
     * Estado externo lido: `$GLOBALS`.
     * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    static $busy = false;
    if (PHP_SAPI === "cli") {
        return;
    }
    if ($busy) {
        return;
    }
    $busy = true;
    try {
        $started = (float) ($GLOBALS["PRONTOO_REQUEST_STARTED_AT"] ?? 0);
        if ($started <= 0 || !has_cfg()) {
            return;
        }
        $elapsedMs = round(max(0.0, (microtime(true) - $started) * 1000), 3);
        $status = (int) http_response_code();
        if ($status < 100) {
            $status = 200;
        }
        $fatal = request_metric_fatal_error(error_get_last());
        $success = $fatal === null && $status < 500 ? 1 : 0;
        $sample = max(1, (int) PRONTOO_TELEMETRY_SAMPLE_RATE);
        $event = [
            "ts" => time(),
            "route" => request_metric_route_name(),
            "release" => defined("PRONTOO_VERSION")
                ? (string) PRONTOO_VERSION
                : "unknown",
            "queries" => (int) ($GLOBALS["PRONTOO_QUERY_COUNT"] ?? 0),
            "wide_selects" => (int) (
                $GLOBALS["PRONTOO_QUERY_WIDE_SELECT_COUNT"] ?? 0
            ),
            "module_bytes" => (int) (
                $GLOBALS["PRONTOO_MODULE_BYTES_LOADED"] ?? 0
            ),
            "module_files" => (int) (
                $GLOBALS["PRONTOO_MODULE_FILES_LOADED"] ?? 0
            ),
            "elapsed_ms" => $elapsedMs,
            "query_ms" => round(
                (float) ($GLOBALS["PRONTOO_QUERY_TOTAL_MS"] ?? 0.0),
                3,
            ),
            "success" => $success,
            "cache" => function_exists("server_json_cache_metrics_snapshot")
                ? server_json_cache_metrics_snapshot()
                : ["totals" => [], "categories" => []],
        ];
        $includePageMetric = !(
            $success &&
            $elapsedMs < 1200 &&
            random_int(1, $sample) !== 1
        );
        if (in_array($event["route"], ["login", "logout"], true)) {
            maestro_defer_telemetry_event($event, $includePageMetric);
            return;
        }
        telemetry_append_route_performance_metric($event);
        if ($includePageMetric) {
            telemetry_append_page_metric($event);
        }
    } catch (Throwable $ignored) {
        error_log(
            "[Prontoo recoverable " .
                __FUNCTION__ .
                "] " .
                $ignored->getMessage(),
        );
    } finally {
        $busy = false;
    }
}
