<?php
declare(strict_types=1);
function has_session_user(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — has_session_user
     * Responsabilidade: Avalia ou impõe a regra “has session user”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_person_lookup`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return !empty($_SESSION["uid"]);
}
function boot_security(): void
{
    /*
     * GUIA DE MANUTENÇÃO — boot_security
     * Responsabilidade: Implementa a responsabilidade “boot security” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_install`, `prontoo_run`.
     * Dependências chamadas: `function_exists`, `security_disable_runtime_error_display`, `security_storage_deny_file`, `storage_path`, `ini_set`, `security_https_active`, `session_name`, `session_set_cookie_params`, `session_status`, `session_start`, `time`, `hash` e mais 9.
     * Estado externo lido: `$_SERVER`, `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 2: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    if (function_exists("security_disable_runtime_error_display")) {
        security_disable_runtime_error_display();
    }
    if (
        function_exists("security_storage_deny_file") &&
        function_exists("storage_path")
    ) {
        security_storage_deny_file(storage_path());
        security_storage_deny_file(storage_path("cache"));
        security_storage_deny_file(storage_path("logs"));
    }
    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.cookie_httponly", "1");
    $secure = function_exists("security_https_active")
        ? security_https_active()
        : (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
            (string) ($_SERVER["SERVER_PORT"] ?? "") === "443";
    session_name("PRONTOO");
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $secure,
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    security_clear_legacy_device_cookie();
    $now = time();
    if (empty($_SESSION["born"])) {
        $_SESSION["born"] = $now;
    }
    $fp = hash("sha256", ($_SERVER["HTTP_USER_AGENT"] ?? "") . "|prontoo");
    if (empty($_SESSION["fp"])) {
        $_SESSION["fp"] = $fp;
    }
    if (!hash_equals((string) $_SESSION["fp"], $fp)) {
        secure_session_destroy();
        header("Location: " . href("login"));
        exit();
    }
    if (!empty($_SESSION["uid"])) {
        $idle = (int) (defined("PRONTOO_SESSION_IDLE_SECONDS")
            ? PRONTOO_SESSION_IDLE_SECONDS
            : 3600);
        $absolute = (int) (defined("PRONTOO_SESSION_ABSOLUTE_SECONDS")
            ? PRONTOO_SESSION_ABSOLUTE_SECONDS
            : 43200);
        $last = (int) ($_SESSION["last_activity"] ?? $now);
        $born = (int) ($_SESSION["born"] ?? $now);
        if (
            ($idle > 0 && $now - $last >= $idle) ||
            ($absolute > 0 && $now - $born > $absolute)
        ) {
            try {
                if (function_exists("audit")) {
                    audit("sessao_expirada", "seguranca", null, [
                        "audit_body" =>
                            "Sessão encerrada automaticamente por tempo de inatividade ou duração máxima.",
                    ]);
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo recoverable " .
                        __FUNCTION__ .
                        "] " .
                        $e->getMessage(),
                );
            }
            secure_session_destroy();
            header("Location: " . href("login"));
            exit();
        }
    }
    $_SESSION["last_activity"] = $now;
    if (empty($_SESSION["rot"]) || $now - (int) $_SESSION["rot"] > 900) {
        session_regenerate_id(true);
        $_SESSION["rot"] = $now;
    }
}
function headers_secure(bool $public = false): void
{
    /*
     * GUIA DE MANUTENÇÃO — headers_secure
     * Responsabilidade: Implementa a responsabilidade “headers secure” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_install`, `prontoo_run`.
     * Dependências chamadas: `bin2hex`, `random_bytes`, `header`, `function_exists`, `security_https_active`.
     * Estado externo lido: `$GLOBALS`, `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     * Cuidado 2: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    $img = "'self' data:";
    $style = "'self' 'unsafe-inline' https://fonts.googleapis.com";
    $GLOBALS["csp_nonce"] = bin2hex(random_bytes(16));
    $script = "'self' 'nonce-" . $GLOBALS["csp_nonce"] . "'";
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-Permitted-Cross-Domain-Policies: none");
    header("Cross-Origin-Opener-Policy: same-origin");
    header("Referrer-Policy: same-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    $upgrade =
        function_exists("security_https_active") && security_https_active()
            ? "; upgrade-insecure-requests"
            : "";
    $csp = "default-src 'self'; script-src $script; style-src $style; font-src 'self' https://fonts.gstatic.com; img-src $img; connect-src 'self' https://servicodados.ibge.gov.br https://viacep.com.br https://brasilapi.com.br; object-src 'none'; frame-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'$upgrade";
    header("Content-Security-Policy: " . $csp);
    header(
        "Content-Security-Policy-Report-Only: default-src 'self'; script-src $script; style-src 'self' https://fonts.googleapis.com; style-src-attr 'none'; font-src 'self' https://fonts.gstatic.com; img-src $img; connect-src 'self' https://servicodados.ibge.gov.br https://viacep.com.br https://brasilapi.com.br; object-src 'none'; frame-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'$upgrade",
    );
    if (!$public) {
        header(
            "Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0",
        );
        header("Pragma: no-cache");
        header("Expires: 0");
    }
    if (
        function_exists("security_https_active")
            ? security_https_active()
            : (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
                (string) ($_SERVER["SERVER_PORT"] ?? "") === "443"
    ) {
        header(
            "Strict-Transport-Security: max-age=31536000; includeSubDomains",
        );
    }
}
function posted_identity_document_error(
    array $data,
    string $prefix = "",
): ?string {
    /*
     * GUIA DE MANUTENÇÃO — posted_identity_document_error
     * Responsabilidade: Registra, consulta ou apresenta evidências técnicas relacionadas a “posted identity document error”.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `enforce_posted_identity_documents`.
     * Dependências chamadas: `is_array`, `posted_identity_document_error`, `strtolower`, `str_ends_with`, `str_contains`, `only_digits`, `preg_match`, `in_array`, `valid_cpf`, `strlen`, `valid_cnpj`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    foreach ($data as $key => $value) {
        $name = $prefix === "" ? (string) $key : $prefix . "." . (string) $key;
        if (is_array($value)) {
            $err = posted_identity_document_error($value, $name);
            if ($err !== null) {
                return $err;
            }
            continue;
        }
        $field = strtolower((string) $key);
        if (str_ends_with($field, "_omitted") || str_contains($field, "omit")) {
            continue;
        }
        $digits = only_digits((string) $value);
        $isCpfField =
            (bool) preg_match('/(^|_|-)cpf($|_|-)/', $field) ||
            in_array(
                $field,
                ["cpf", "admin_cpf", "team_cpf", "guardian_cpf"],
                true,
            );
        $isDocField =
            in_array(
                $field,
                [
                    "legal_document",
                    "doc",
                    "document",
                    "documento",
                    "documento_legal",
                ],
                true,
            ) ||
            (str_contains($field, "document") &&
                !str_contains($field, "proof"));
        if ($isCpfField && !$isDocField) {
            if ($digits !== "" && !valid_cpf($digits)) {
                return "Informe um CPF válido.";
            }
            continue;
        }
        if ($isDocField) {
            if ($digits === "") {
                continue;
            }
            if (strlen($digits) === 11 && !valid_cpf($digits)) {
                return "Informe um CPF válido.";
            }
            if (strlen($digits) === 14 && !valid_cnpj($digits)) {
                return "Informe um CNPJ válido.";
            }
            if (!in_array(strlen($digits), [11, 14], true)) {
                return "Informe CPF ou CNPJ válido.";
            }
        }
    }
    return null;
}
function enforce_posted_identity_documents(): void
{
    /*
     * GUIA DE MANUTENÇÃO — enforce_posted_identity_documents
     * Responsabilidade: Implementa a responsabilidade “enforce posted identity documents” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `guard_request`.
     * Dependências chamadas: `posted_identity_document_error`, `http_response_code`, `header`.
     * Estado externo lido: `$_SERVER`, `$_POST`.
     * Efeitos colaterais: consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
        return;
    }
    $err = posted_identity_document_error($_POST);
    if ($err !== null) {
        http_response_code(400);
        header("Content-Type: text/plain; charset=utf-8");
        exit($err);
    }
}
function guard_request(): void
{
    /*
     * GUIA DE MANUTENÇÃO — guard_request
     * Responsabilidade: Avalia ou impõe a regra “guard request”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_install`, `prontoo_run`.
     * Dependências chamadas: `function_exists`, `app_enforce_canonical_host`, `in_array`, `http_response_code`, `preg_replace`, `count`, `strtolower`, `parse_url`, `enforce_posted_identity_documents`.
     * Estado externo lido: `$_SERVER`, `$_GET`, `$_POST`, `$_FILES`.
     * Efeitos colaterais: consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    if (function_exists("app_enforce_canonical_host")) {
        app_enforce_canonical_host();
    }
    $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
    if (!in_array($method, ["GET", "POST"], true)) {
        http_response_code(405);
        exit("Método não permitido.");
    }
    if ($method === "POST") {
        $routeHint = preg_replace(
            "/[^a-z0-9_\-]/i",
            "",
            (string) ($_GET["r"] ?? ""),
        );
        $maxContent = match ($routeHint) {
            "settings" => 6291456,
            default => 4194304,
        };
        if ((int) ($_SERVER["CONTENT_LENGTH"] ?? 0) > $maxContent) {
            http_response_code(413);
            exit("Requisição maior que o permitido.");
        }
        if (count($_POST, COUNT_RECURSIVE) > 160) {
            http_response_code(400);
            exit("Formulário maior que o permitido.");
        }
        $site = $_SERVER["HTTP_SEC_FETCH_SITE"] ?? "";
        if (
            $site &&
            !in_array($site, ["same-origin", "same-site", "none"], true)
        ) {
            http_response_code(403);
            exit("Origem recusada.");
        }
        $origin = $_SERVER["HTTP_ORIGIN"] ?? "";
        $hostHeader = strtolower((string) ($_SERVER["HTTP_HOST"] ?? ""));
        $originHost = $origin
            ? strtolower((string) parse_url($origin, PHP_URL_HOST))
            : "";
        if (
            $origin &&
            $originHost !== preg_replace('/:\d+$/', "", $hostHeader)
        ) {
            http_response_code(403);
            exit("Origem inválida.");
        }
        if (count($_FILES, COUNT_RECURSIVE) > 40) {
            http_response_code(400);
            exit("Envio maior que o permitido.");
        }
        enforce_posted_identity_documents();
    }
}
function security_rate_limit(
    string $bucket,
    int $limit,
    int $windowSeconds,
): bool {
    /*
     * GUIA DE MANUTENÇÃO — security_rate_limit
     * Responsabilidade: Implementa a responsabilidade “security rate limit” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `platform_login_loaded_audit`, `page_signup`, `page_person_lookup`, `subscription_payment_proof_guard`, `page_counterparty_lookup`, `page_lead_lookup`, `page_lead_patient_lookup`, `page_login_autotest`.
     * Dependências chamadas: `preg_replace`, `time`, `cache_get`, `is_array`, `array_values`, `array_filter`, `array_map`, `count`, `cache_set`.
     * Efeitos colaterais: lê, grava ou invalida cache.
     * Cuidado 1: O cache é derivado: preserve TTL, chave por escopo e invalidação por tags; nunca o trate como fonte de verdade.
     */
    $bucket = preg_replace("/[^a-zA-Z0-9_\-]/", "_", $bucket) ?: "rate";
    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $dir = storage_path("cache/rate-limits");
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        error_log("[Prontoo rate limit] Diretório de controle indisponível.");
        return true;
    }
    $file = $dir . "/" . hash("sha256", $bucket) . ".json";
    $handle = @fopen($file, "c+");
    if (!is_resource($handle)) {
        error_log("[Prontoo rate limit] Controle atômico indisponível.");
        return true;
    }
    $locked = false;
    $now = time();
    try {
        $locked = flock($handle, LOCK_EX);
        if (!$locked) {
            error_log("[Prontoo rate limit] Trava atômica indisponível.");
            return true;
        }
        rewind($handle);
        $raw = stream_get_contents($handle);
        $state = is_string($raw) && $raw !== ""
            ? json_decode($raw, true)
            : [];
        if (!is_array($state)) {
            $state = [];
        }
        $hits = array_values(
            array_filter(
                array_map("intval", $state),
                static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn($t) => $t > $now - $windowSeconds,
            ),
        );
        $limited = count($hits) >= $limit;
        if (!$limited) {
            $hits[] = $now;
        }
        $encoded = json_encode($hits, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return true;
        }
        rewind($handle);
        $written = false;
        if (ftruncate($handle, 0)) {
            $written = fwrite($handle, $encoded);
        }
        if ($written !== strlen($encoded) || !fflush($handle)) {
            error_log("[Prontoo rate limit] Estado atômico não pôde ser salvo.");
            return true;
        }
        @chmod($file, 0640);
        return $limited;
    } finally {
        if ($locked) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}
function security_client_bucket(string $prefix): string
{
    /*
     * GUIA DE MANUTENÇÃO — security_client_bucket
     * Responsabilidade: Implementa a responsabilidade “security client bucket” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `platform_login_loaded_audit`, `page_signup`, `page_person_lookup`, `subscription_payment_proof_guard`, `page_counterparty_lookup`, `page_lead_lookup`, `page_lead_patient_lookup`, `page_login_autotest`.
     * Dependências chamadas: `hash`.
     * Estado externo lido: `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return $prefix .
        "_" .
        hash(
            "sha256",
            ($_SERVER["REMOTE_ADDR"] ?? "") .
                "|" .
                ($_SERVER["HTTP_USER_AGENT"] ?? ""),
        );
}
function security_ip_bucket(string $prefix): string
{
    /*
     * GUIA DE MANUTENÇÃO — security_ip_bucket
     * Responsabilidade: Implementa a responsabilidade “security ip bucket” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_signup`, `page_person_lookup`, `subscription_payment_proof_guard`.
     * Dependências chamadas: `hash`.
     * Estado externo lido: `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return $prefix .
        "_ip_" .
        hash("sha256", (string) ($_SERVER["REMOTE_ADDR"] ?? ""));
}
function security_value_bucket(string $prefix, string $value): string
{
    /*
     * GUIA DE MANUTENÇÃO — security_value_bucket
     * Responsabilidade: Implementa a responsabilidade “security value bucket” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_signup`, `page_person_lookup`, `subscription_payment_proof_guard`.
     * Dependências chamadas: `hash`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return $prefix . "_" . hash("sha256", $value);
}
function safe_val(string $sql, array $p = [], mixed $fallback = 0): mixed
{
    /*
     * GUIA DE MANUTENÇÃO — safe_val
     * Responsabilidade: Implementa a responsabilidade “safe val” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_global_ops_finance_html`, `closure@app/Admin/AdminPages.php:688`, `closure@app/Admin/AdminPages.php:691`, `page_admin_painel`, `closure@app/Admin/AdminPages.php:2871`, `clinic_is_global_admin_owned`, `closure@app/Domain/Clinic/ClinicConfig.php:1206`, `document_context_ids_from_post` e mais 10.
     * Dependências chamadas: `val`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        return val($sql, $p) ?? $fallback;
    } catch (Throwable $e) {
        error_log("[Prontoo safe_val] " . $e->getMessage());
        return $fallback;
    }
}
function csrf(): string
{
    /*
     * GUIA DE MANUTENÇÃO — csrf
     * Responsabilidade: Implementa a responsabilidade “csrf” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `install_head`, `prontoo_run`, `app_fail`, `csrf_field`, `page`.
     * Dependências chamadas: `bin2hex`, `random_bytes`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}
function csrf_field(): string
{
    /*
     * GUIA DE MANUTENÇÃO — csrf_field
     * Responsabilidade: Monta a representação de interface associada a “csrf field” sem alterar o contrato visual externo.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_deleted`, `page_admin_errors`, `page_admin_maintenance`, `page_admin_painel`, `admin_clinic_detail_page`, `page_admin_clinics`, `page_admin_security`, `page_admin_alerts` e mais 45.
     * Dependências chamadas: `e`, `csrf`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
}
function check_csrf(): void
{
    /*
     * GUIA DE MANUTENÇÃO — check_csrf
     * Responsabilidade: Avalia ou impõe a regra “check csrf”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_run`.
     * Dependências chamadas: `hash_equals`, `audit`, `route`, `ProntooHttpError`.
     * Classes ou serviços instanciados: `ProntooHttpError`.
     * Estado externo lido: `$_SERVER`, `$_SESSION`, `$_POST`.
     * Efeitos colaterais: lê ou altera a sessão; consome dados da requisição HTTP; gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
     * Cuidado 1: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    if (
        ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" &&
        !hash_equals($_SESSION["csrf"] ?? "", $_POST["csrf"] ?? "")
    ) {
        audit("csrf_bloqueado", "seguranca", null, ["janela" => route()]);
        throw new ProntooHttpError(
            403,
            "Sessão expirada ou formulário inválido.",
        );
    }
}
function flash(?string $m = null, string $type = "ok"): ?array
{
    /*
     * GUIA DE MANUTENÇÃO — flash
     * Responsabilidade: Implementa a responsabilidade “flash” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_deleted`, `page_admin_errors`, `page_admin_maintenance`, `page_admin_painel`, `admin_clinic_detail_page`, `page_admin_clinics`, `page_admin_security`, `page_admin_alerts` e mais 25.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($m !== null) {
        $_SESSION["flash"] = [$type, $m];
        return null;
    }
    $f = $_SESSION["flash"] ?? null;
    unset($_SESSION["flash"]);
    return $f;
}
function password_common_rejected(string $s): bool
{
    /*
     * GUIA DE MANUTENÇÃO — password_common_rejected
     * Responsabilidade: Implementa a responsabilidade “password common rejected” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `password_ok`.
     * Dependências chamadas: `strtolower`, `trim`, `in_array`, `preg_match`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $v = strtolower(trim($s));
    $common = [
        "123456",
        "12345678",
        "123456789",
        "1234567890",
        "password",
        "senha",
        "senha123",
        "admin123",
        "prontoo123",
        "qwerty",
        "abc123",
        "111111",
        "000000",
    ];
    if (in_array($v, $common, true)) {
        return true;
    }
    if (preg_match('/^(.)\1{7,}$/', $v)) {
        return true;
    }
    if (preg_match("/^(0123456789|1234567890|9876543210)/", $v)) {
        return true;
    }
    return false;
}
function password_ok(string $s): bool
{
    /*
     * GUIA DE MANUTENÇÃO — password_ok
     * Responsabilidade: Implementa a responsabilidade “password ok” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_signup`, `page_profile`, `save_team_member`, `prontoo_install`.
     * Dependências chamadas: `preg_match`, `strlen`, `password_common_rejected`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $length = mb_strlen($s);
    return $length >= 8 &&
        $length <= 128 &&
        !password_common_rejected($s);
}
function password_hash_secure(string $password): string
{
    /*
     * GUIA DE MANUTENÇÃO — password_hash_secure
     * Responsabilidade: Implementa a responsabilidade “password hash secure” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_signup`, `page_profile`, `save_team_member`, `prontoo_install`.
     * Dependências chamadas: `defined`, `password_hash`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (defined("PASSWORD_ARGON2ID")) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            "memory_cost" => 65536,
            "time_cost" => 3,
            "threads" => 1,
        ]);
    }
    return password_hash($password, PASSWORD_DEFAULT);
}
/* Guia de manutenção: Resolve a chave canônica do cadastro MFA por usuário; mantenha o isolamento por identificador. */
function mfa_meta_key(int $uid): string
{
    return "mfa_user_" . max(0, $uid);
}
/* Guia de manutenção: Deriva exclusivamente a chave de proteção dos segredos MFA; preserve a separação de domínio criptográfico. */
function mfa_crypto_key(): string
{
    return hash_hmac(
        "sha256",
        "prontoo-mfa-secret-v1",
        secret_key(),
        true,
    );
}
/* Guia de manutenção: Protege o segredo MFA com cifra autenticada; nunca persista o segredo em texto claro. */
function mfa_secret_encrypt(string $secret): string
{
    if (!function_exists("openssl_encrypt")) {
        throw new RuntimeException(
            "Criptografia necessária para o MFA não está disponível.",
        );
    }
    $nonce = random_bytes(12);
    $tag = "";
    $ciphertext = openssl_encrypt(
        $secret,
        "aes-256-gcm",
        mfa_crypto_key(),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        "prontoo-mfa-v1",
        16,
    );
    if (!is_string($ciphertext) || strlen($tag) !== 16) {
        throw new RuntimeException("Não foi possível proteger o segredo MFA.");
    }
    return base64_encode($nonce . $tag . $ciphertext);
}
/* Guia de manutenção: Abre e autentica o segredo MFA persistido; falhe fechado quando a integridade não puder ser comprovada. */
function mfa_secret_decrypt(string $encrypted): string
{
    if (!function_exists("openssl_decrypt")) {
        throw new RuntimeException(
            "Criptografia necessária para o MFA não está disponível.",
        );
    }
    $payload = base64_decode($encrypted, true);
    if (!is_string($payload) || strlen($payload) < 29) {
        throw new RuntimeException("Cadastro MFA inválido.");
    }
    $secret = openssl_decrypt(
        substr($payload, 28),
        "aes-256-gcm",
        mfa_crypto_key(),
        OPENSSL_RAW_DATA,
        substr($payload, 0, 12),
        substr($payload, 12, 16),
        "prontoo-mfa-v1",
    );
    if (!is_string($secret) || $secret === "") {
        throw new RuntimeException("Não foi possível validar o cadastro MFA.");
    }
    return $secret;
}
/* Guia de manutenção: Carrega o cadastro MFA canônico do usuário; trate registros incompletos como não cadastrados. */
function mfa_record_load(int $uid): ?array
{
    if ($uid <= 0 || !has_cfg()) {
        return null;
    }
    $raw = val(
        "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
        [mfa_meta_key($uid)],
    );
    if (!is_string($raw) || trim($raw) === "") {
        return null;
    }
    $record = json_decode($raw, true);
    if (
        !is_array($record) ||
        (int) ($record["v"] ?? 0) !== 1 ||
        empty($record["secret"])
    ) {
        return null;
    }
    return $record;
}
/* Guia de manutenção: Persiste atomicamente o cadastro MFA serializado; preserve a versão e as defesas contra repetição. */
function mfa_record_save(int $uid, array $record): void
{
    $encoded = json_encode(
        $record,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
    meta_set(mfa_meta_key($uid), $encoded);
}
/* Guia de manutenção: Informa se há MFA utilizável; erros de persistência não podem liberar acesso privilegiado. */
function mfa_is_enrolled(int $uid): bool
{
    try {
        return mfa_record_load($uid) !== null;
    } catch (Throwable $e) {
        error_log("[Prontoo MFA enrolled] " . $e->getMessage());
        return false;
    }
}
/* Guia de manutenção: Codifica segredos TOTP no alfabeto Base32 interoperável com autenticadores. */
function mfa_base32_encode(string $bytes): string
{
    $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
    $buffer = 0;
    $bits = 0;
    $out = "";
    $length = strlen($bytes);
    for ($i = 0; $i < $length; $i++) {
        $buffer = ($buffer << 8) | ord($bytes[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= $alphabet[($buffer >> $bits) & 31];
        }
    }
    if ($bits > 0) {
        $out .= $alphabet[($buffer << (5 - $bits)) & 31];
    }
    return $out;
}
/* Guia de manutenção: Decodifica Base32 estrito para validação TOTP; rejeite caracteres fora do alfabeto. */
function mfa_base32_decode(string $value): string
{
    $alphabet = array_flip(
        str_split("ABCDEFGHIJKLMNOPQRSTUVWXYZ234567"),
    );
    $value = strtoupper(
        preg_replace('/[^A-Z2-7]/i', "", $value) ?? "",
    );
    $buffer = 0;
    $bits = 0;
    $out = "";
    foreach (str_split($value) as $char) {
        if (!isset($alphabet[$char])) {
            throw new RuntimeException("Segredo MFA inválido.");
        }
        $buffer = ($buffer << 5) | (int) $alphabet[$char];
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($buffer >> $bits) & 255);
        }
    }
    return $out;
}
/* Guia de manutenção: Calcula o contador TOTP de 30 segundos; preserve a compatibilidade temporal. */
function mfa_totp_counter(?int $timestamp = null): int
{
    return intdiv($timestamp ?? time(), 30);
}
/* Guia de manutenção: Calcula TOTP RFC 6238 de seis dígitos; preserve algoritmo e truncamento interoperáveis. */
function mfa_totp_code(string $secret, int $counter): string
{
    $binary = mfa_base32_decode($secret);
    $counterBytes = pack(
        "N2",
        (int) floor($counter / 4294967296),
        $counter & 0xffffffff,
    );
    $hash = hash_hmac("sha1", $counterBytes, $binary, true);
    $offset = ord($hash[19]) & 15;
    $number =
        ((ord($hash[$offset]) & 127) << 24) |
        ((ord($hash[$offset + 1]) & 255) << 16) |
        ((ord($hash[$offset + 2]) & 255) << 8) |
        (ord($hash[$offset + 3]) & 255);
    return str_pad(
        (string) ($number % 1000000),
        6,
        "0",
        STR_PAD_LEFT,
    );
}
/* Guia de manutenção: Valida TOTP em janela curta e impede reutilização de contador já aceito. */
function mfa_totp_matching_counter(
    string $secret,
    string $code,
    int $lastCounter = -1,
): ?int {
    $code = preg_replace('/\D/', "", $code) ?? "";
    if (strlen($code) !== 6) {
        return null;
    }
    $current = mfa_totp_counter();
    for ($delta = -1; $delta <= 1; $delta++) {
        $counter = $current + $delta;
        if (
            $counter > $lastCounter &&
            hash_equals(mfa_totp_code($secret, $counter), $code)
        ) {
            return $counter;
        }
    }
    return null;
}
/* Guia de manutenção: Canonicaliza códigos de recuperação antes da comparação criptográfica. */
function mfa_recovery_code_normalize(string $code): string
{
    return strtoupper(
        preg_replace('/[^A-Z0-9]/i', "", trim($code)) ?? "",
    );
}
/* Guia de manutenção: Produz verificador não reversível; nunca persista o código de recuperação original. */
function mfa_recovery_code_hash(string $code): string
{
    return hash_hmac(
        "sha256",
        mfa_recovery_code_normalize($code),
        secret_key(),
    );
}
/* Guia de manutenção: Gera códigos de recuperação aleatórios e legíveis; preserve entropia e uso único. */
function mfa_recovery_codes_generate(int $count = 10): array
{
    $alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $raw = "";
        for ($j = 0; $j < 12; $j++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $codes[] =
            substr($raw, 0, 4) .
            "-" .
            substr($raw, 4, 4) .
            "-" .
            substr($raw, 8, 4);
    }
    return $codes;
}
/* Guia de manutenção: Conclui cadastro MFA somente após prova TOTP e entrega recuperação uma única vez. */
function mfa_enroll_user(
    int $uid,
    string $secret,
    string $firstCode,
): array {
    $lock = "prontoo_mfa_user_" . max(0, $uid);
    $locked = false;
    try {
        $locked = (int) val("SELECT GET_LOCK(?,5)", [$lock]) === 1;
        if (!$locked) {
            throw new RuntimeException(
                "Não foi possível proteger o cadastro MFA.",
            );
        }
        if (mfa_record_load($uid) !== null) {
            throw new RuntimeException("O MFA já está cadastrado.");
        }
        $counter = mfa_totp_matching_counter($secret, $firstCode);
        if ($counter === null) {
            throw new RuntimeException(
                "O código do autenticador não confere.",
            );
        }
        $codes = mfa_recovery_codes_generate();
        mfa_record_save($uid, [
            "v" => 1,
            "secret" => mfa_secret_encrypt($secret),
            "recovery" => array_map("mfa_recovery_code_hash", $codes),
            "last_counter" => $counter,
            "enrolled_at" => time(),
            "updated_at" => time(),
        ]);
        return $codes;
    } finally {
        if ($locked) {
            try {
                val("SELECT RELEASE_LOCK(?)", [$lock]);
            } catch (Throwable $e) {
                error_log("[Prontoo MFA enroll unlock] " . $e->getMessage());
            }
        }
    }
}
/* Guia de manutenção: Valida TOTP ou recuperação sob trava por usuário, consumindo provas contra repetição. */
function mfa_verify_user_code(int $uid, string $code): bool
{
    $lock = "prontoo_mfa_user_" . max(0, $uid);
    $locked = false;
    try {
        $locked = (int) val("SELECT GET_LOCK(?,5)", [$lock]) === 1;
        if (!$locked) {
            return false;
        }
        $record = mfa_record_load($uid);
        if (!$record) {
            return false;
        }
        $secret = mfa_secret_decrypt((string) $record["secret"]);
        $counter = mfa_totp_matching_counter(
            $secret,
            $code,
            (int) ($record["last_counter"] ?? -1),
        );
        if ($counter !== null) {
            $record["last_counter"] = $counter;
            $record["updated_at"] = time();
            mfa_record_save($uid, $record);
            return true;
        }
        $normalized = mfa_recovery_code_normalize($code);
        if (strlen($normalized) !== 12) {
            return false;
        }
        $candidate = mfa_recovery_code_hash($normalized);
        foreach ((array) ($record["recovery"] ?? []) as $index => $hash) {
            if (hash_equals((string) $hash, $candidate)) {
                unset($record["recovery"][$index]);
                $record["recovery"] = array_values($record["recovery"]);
                $record["updated_at"] = time();
                mfa_record_save($uid, $record);
                return true;
            }
        }
        return false;
    } finally {
        if ($locked) {
            try {
                val("SELECT RELEASE_LOCK(?)", [$lock]);
            } catch (Throwable $e) {
                error_log("[Prontoo MFA unlock] " . $e->getMessage());
            }
        }
    }
}
/* Guia de manutenção: Gera segredo TOTP criptograficamente aleatório com tamanho interoperável. */
function mfa_totp_secret_generate(): string
{
    return mfa_base32_encode(random_bytes(20));
}
/* Guia de manutenção: Monta URI otpauth para cadastro; preserve a codificação dos componentes. */
function mfa_otpauth_uri(string $account, string $secret): string
{
    $issuer = "Prontoo";
    return "otpauth://totp/" .
        rawurlencode($issuer . ":" . $account) .
        "?secret=" .
        rawurlencode($secret) .
        "&issuer=" .
        rawurlencode($issuer) .
        "&algorithm=SHA1&digits=6&period=30";
}
function auth_generation_current(): string
{
    /*
     * GUIA DE MANUTENÇÃO — auth_generation_current
     * Responsabilidade: Implementa a responsabilidade “auth generation current” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `session_harden_after_login`, `security_session_generation_enforce`.
     * Dependências chamadas: `has_cfg`, `meta_get`, `error_log`, `->getMessage`.
     * Efeitos colaterais: gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!has_cfg()) {
        return "bootstrap";
    }
    try {
        return (string) meta_get("auth_generation", "0");
    } catch (Throwable $e) {
        error_log("[Prontoo auth generation] " . $e->getMessage());
        return "0";
    }
}
/* Guia de manutenção: Resolve a chave da geração de autenticação por usuário para revogação seletiva. */
function user_auth_generation_key(int $uid): string
{
    return "auth_user_" . max(0, $uid);
}
/* Guia de manutenção: Lê a geração vigente; ausência deve falhar fechado nas sessões existentes. */
function user_auth_generation_current(int $uid): string
{
    if ($uid <= 0 || !has_cfg()) {
        return "0";
    }
    try {
        return (string) (val(
            "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
            [user_auth_generation_key($uid)],
        ) ?? "0");
    } catch (Throwable $e) {
        error_log("[Prontoo user auth generation] " . $e->getMessage());
        return "0";
    }
}
/* Guia de manutenção: Inicializa uma geração única sob trava concorrente antes de criar a sessão. */
function user_auth_generation_ensure(int $uid): string
{
    $current = user_auth_generation_current($uid);
    if ($current !== "0" && $current !== "") {
        return $current;
    }
    $lock = "prontoo_auth_user_" . max(0, $uid);
    $locked = false;
    try {
        $locked = (int) val("SELECT GET_LOCK(?,5)", [$lock]) === 1;
        if (!$locked) {
            throw new RuntimeException(
                "Não foi possível proteger a geração de autenticação.",
            );
        }
        $current = user_auth_generation_current($uid);
        if ($current === "0" || $current === "") {
            $current = bin2hex(random_bytes(24));
            meta_set(user_auth_generation_key($uid), $current);
        }
        return $current;
    } finally {
        if ($locked) {
            try {
                val("SELECT RELEASE_LOCK(?)", [$lock]);
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo user auth generation unlock] " .
                        $e->getMessage(),
                );
            }
        }
    }
}
/* Guia de manutenção: Revoga todas as sessões do usuário pela rotação criptográfica da geração. */
function user_auth_generation_rotate(int $uid): string
{
    if ($uid <= 0) {
        throw new RuntimeException("Usuário inválido para revogação de sessão.");
    }
    $generation = bin2hex(random_bytes(24));
    meta_set(user_auth_generation_key($uid), $generation);
    if (function_exists("server_json_cache_clear_categories")) {
        server_json_cache_clear_categories(["context", "meta"]);
    }
    return $generation;
}
function session_harden_after_login(int $uid = 0): void
{
    /*
     * GUIA DE MANUTENÇÃO — session_harden_after_login
     * Responsabilidade: Implementa a responsabilidade “session harden after login” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `login_apply_resolved_credential`, `device_session_auto_login`.
     * Dependências chamadas: `session_regenerate_id`, `bin2hex`, `random_bytes`, `time`, `auth_generation_current`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $mfaVerified = !empty($_SESSION["mfa_verified_at"]);
    $privilegedVerified = !empty($_SESSION["privileged_auth_at"]);
    session_regenerate_id(true);
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
    $now = time();
    $_SESSION["rot"] = $now;
    $_SESSION["born"] = $now;
    $_SESSION["last_activity"] = $now;
    if ($mfaVerified) {
        $_SESSION["mfa_verified_at"] = $now;
    }
    if ($privilegedVerified) {
        $_SESSION["privileged_auth_at"] = $now;
    }
    $_SESSION["auth_generation"] = auth_generation_current();
    $_SESSION["auth_policy_generation"] = defined(
        "PRONTOO_AUTH_POLICY_GENERATION",
    )
        ? PRONTOO_AUTH_POLICY_GENERATION
        : "password-session-v1";
    if ($uid > 0) {
        $_SESSION["user_auth_generation"] = user_auth_generation_ensure($uid);
    }
}
function security_session_generation_enforce(int $uid): void
{
    /*
     * GUIA DE MANUTENÇÃO — security_session_generation_enforce
     * Responsabilidade: Implementa a responsabilidade “security session generation enforce” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `ctx`.
     * Dependências chamadas: `has_cfg`, `auth_generation_current`, `audit`, `error_log`, `->getMessage`, `device_session_revoke_current`, `device_cookie_clear`, `secure_session_destroy`, `headers_sent`, `header`, `href`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão; controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 2: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    if ($uid <= 0 || !has_cfg()) {
        return;
    }
    $current = auth_generation_current();
    $session = (string) ($_SESSION["auth_generation"] ?? "");
    $policy = defined("PRONTOO_AUTH_POLICY_GENERATION")
        ? PRONTOO_AUTH_POLICY_GENERATION
        : "password-session-v1";
    $sessionPolicy = (string) ($_SESSION["auth_policy_generation"] ?? "");
    $userCurrent = user_auth_generation_current($uid);
    $userSession = (string) ($_SESSION["user_auth_generation"] ?? "");
    if (
        $sessionPolicy === $policy &&
        ($current === "0" || $session === $current) &&
        $userCurrent !== "0" &&
        hash_equals($userCurrent, $userSession)
    ) {
        return;
    }
    try {
        audit("sessao_obsoleta_encerrada", "seguranca", $uid, [
            "audit_body" =>
                "Sessão encerrada porque a geração de autenticação foi renovada pela normalização pré-login do index.",
        ]);
    } catch (Throwable $e) {
        error_log("[Prontoo auth generation audit] " . $e->getMessage());
    }
    security_clear_legacy_device_cookie();
    secure_session_destroy();
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Location: " . href("login", ["relogin" => "1"]));
    }
    exit();
}
function device_cookie_name(): string
{
    /*
     * GUIA DE MANUTENÇÃO — device_cookie_name
     * Responsabilidade: Implementa a responsabilidade “device cookie name” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_cookie_set`, `device_cookie_clear`, `device_cookie_unpack`, `device_session_remember_after_login`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return "PRONTOO_DEVICE";
}
function device_session_lifetime_seconds(): int
{
    /*
     * GUIA DE MANUTENÇÃO — device_session_lifetime_seconds
     * Responsabilidade: Implementa a responsabilidade “device session lifetime seconds” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_session_remember_after_login`, `device_session_enforce_current`, `device_session_auto_login`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return 86400;
}
function device_session_cookie_ttl_seconds(): int
{
    /*
     * GUIA DE MANUTENÇÃO — device_session_cookie_ttl_seconds
     * Responsabilidade: Implementa a responsabilidade “device session cookie ttl seconds” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_session_remember_after_login`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return 2592000;
}
function device_hash_is_valid(string $hash): bool
{
    /*
     * GUIA DE MANUTENÇÃO — device_hash_is_valid
     * Responsabilidade: Avalia ou impõe a regra “device hash is valid”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_client_hash_from_post`, `device_cookie_unpack`, `device_session_remember_after_login`.
     * Dependências chamadas: `preg_match`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return (bool) preg_match('/^[a-f0-9]{64}$/', $hash);
}
function device_secure_cookie(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — device_secure_cookie
     * Responsabilidade: Implementa a responsabilidade “device secure cookie” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_cookie_set`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Estado externo lido: `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return function_exists("security_https_active")
        ? security_https_active()
        : (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
            (string) ($_SERVER["SERVER_PORT"] ?? "") === "443";
}
function device_cookie_set(string $value, int $expires): void
{
    /*
     * GUIA DE MANUTENÇÃO — device_cookie_set
     * Responsabilidade: Implementa a responsabilidade “device cookie set” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_cookie_clear`, `device_session_remember_after_login`.
     * Dependências chamadas: `headers_sent`, `setcookie`, `device_cookie_name`, `device_secure_cookie`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (headers_sent()) {
        return;
    }
    // O recurso de dispositivo persistente foi descontinuado. Esta função
    // permanece apenas como compatibilidade defensiva e nunca emite credencial.
    setcookie(device_cookie_name(), "", [
        "expires" => time() - 42000,
        "path" => "/",
        "secure" => device_secure_cookie(),
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    unset($_COOKIE[device_cookie_name()]);
}
function device_cookie_clear(): void
{
    /*
     * GUIA DE MANUTENÇÃO — device_cookie_clear
     * Responsabilidade: Implementa a responsabilidade “device cookie clear” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `security_session_generation_enforce`, `device_session_enforce_current`, `device_session_auto_login`, `device_session_revoke_current`, `secure_relogin_after_forbidden_action`.
     * Dependências chamadas: `device_cookie_set`, `time`, `device_cookie_name`.
     * Estado externo lido: `$_COOKIE`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    device_cookie_set("", time() - 42000);
    unset($_COOKIE[device_cookie_name()]);
}
/* Guia de manutenção: Remove cookie legado de dispositivo persistente sem aceitar seu conteúdo. */
function security_clear_legacy_device_cookie(): void
{
    if (
        isset($_COOKIE["PRONTOO_DEVICE"]) ||
        isset($_SERVER["HTTP_COOKIE"]) &&
            str_contains((string) $_SERVER["HTTP_COOKIE"], "PRONTOO_DEVICE=")
    ) {
        device_cookie_clear();
    }
}
/* Guia de manutenção: Revoga e inutiliza credenciais legadas de dispositivo persistente do usuário. */
function security_retire_persistent_devices_for_user(int $uid): void
{
    if ($uid <= 0 || !has_cfg()) {
        security_clear_legacy_device_cookie();
        return;
    }
    try {
        q(
            "UPDATE pi_user_devices SET revoked_at=COALESCE(revoked_at,NOW()), logout_at=COALESCE(logout_at,NOW()), token_hash=SHA2(CONCAT(token_hash,':retired:',id),256), updated_at=NOW() WHERE user_id=? AND (revoked_at IS NULL OR logout_at IS NULL)",
            [$uid],
        );
    } catch (Throwable $e) {
        error_log(
            "[Prontoo persistent device retirement] " . $e->getMessage(),
        );
    }
    security_clear_legacy_device_cookie();
}
function device_token_hash(string $token): string
{
    /*
     * GUIA DE MANUTENÇÃO — device_token_hash
     * Responsabilidade: Implementa a responsabilidade “device token hash” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_session_remember_after_login`, `device_session_auto_login`.
     * Dependências chamadas: `hash_hmac`, `secret_key`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return hash_hmac("sha256", $token, secret_key());
}
function device_fallback_hash(): string
{
    /*
     * GUIA DE MANUTENÇÃO — device_fallback_hash
     * Responsabilidade: Implementa a responsabilidade “device fallback hash” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_client_hash_from_post`, `device_session_remember_after_login`.
     * Dependências chamadas: `hash_hmac`, `secret_key`.
     * Estado externo lido: `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return hash_hmac(
        "sha256",
        ($_SERVER["HTTP_USER_AGENT"] ?? "") .
            "|" .
            ($_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? "") .
            "|" .
            ($_SERVER["REMOTE_ADDR"] ?? "") .
            "|prontoo-device",
        secret_key(),
    );
}
function device_client_hash_from_post(): string
{
    /*
     * GUIA DE MANUTENÇÃO — device_client_hash_from_post
     * Responsabilidade: Implementa a responsabilidade “device client hash from post” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_login_payload_from_post`.
     * Dependências chamadas: `strtolower`, `trim`, `device_hash_is_valid`, `device_fallback_hash`.
     * Estado externo lido: `$_POST`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $hash = strtolower(trim((string) ($_POST["device_hash"] ?? "")));
    return device_hash_is_valid($hash) ? $hash : device_fallback_hash();
}
function device_login_payload_from_post(): array
{
    /*
     * GUIA DE MANUTENÇÃO — device_login_payload_from_post
     * Responsabilidade: Implementa a responsabilidade “device login payload from post” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_login`, `device_session_remember_after_login`.
     * Dependências chamadas: `trim`, `json_decode`, `device_client_hash_from_post`, `mb_substr`.
     * Estado externo lido: `$_POST`, `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $meta = trim((string) ($_POST["device_meta"] ?? ""));
    if ($meta !== "" && json_decode($meta, true) === null) {
        $meta = "";
    }
    return [
        "hash" => device_client_hash_from_post(),
        "label" => mb_substr(
            trim((string) ($_POST["device_label"] ?? "")),
            0,
            160,
        ),
        "platform" => mb_substr(
            trim((string) ($_POST["device_platform"] ?? "")),
            0,
            120,
        ),
        "meta" => mb_substr($meta, 0, 1200),
        "ua" => mb_substr((string) ($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 600),
        "ip" => mb_substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45),
    ];
}
function device_login_fields(): string
{
    /*
     * GUIA DE MANUTENÇÃO — device_login_fields
     * Responsabilidade: Implementa a responsabilidade “device login fields” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_login`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return '<input type="hidden" name="device_hash" value="" data-device-hash><input type="hidden" name="device_label" value="" data-device-label><input type="hidden" name="device_platform" value="" data-device-platform><input type="hidden" name="device_meta" value="" data-device-meta>';
}
function device_cookie_pack(int $uid, string $deviceHash, string $token): string
{
    /*
     * GUIA DE MANUTENÇÃO — device_cookie_pack
     * Responsabilidade: Implementa a responsabilidade “device cookie pack” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_session_remember_after_login`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return $uid . "." . $deviceHash . "." . $token;
}
function device_cookie_unpack(): ?array
{
    /*
     * GUIA DE MANUTENÇÃO — device_cookie_unpack
     * Responsabilidade: Implementa a responsabilidade “device cookie unpack” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_session_auto_login`.
     * Dependências chamadas: `device_cookie_name`, `explode`, `count`, `strtolower`, `device_hash_is_valid`, `preg_match`.
     * Estado externo lido: `$_COOKIE`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $raw = (string) ($_COOKIE[device_cookie_name()] ?? "");
    if ($raw === "") {
        return null;
    }
    $parts = explode(".", $raw, 3);
    if (count($parts) !== 3) {
        return null;
    }
    [$uid, $hash, $token] = $parts;
    $uid = (int) $uid;
    $hash = strtolower($hash);
    if (
        $uid <= 0 ||
        !device_hash_is_valid($hash) ||
        !preg_match('/^[a-f0-9]{64}$/', $token)
    ) {
        return null;
    }
    return ["uid" => $uid, "hash" => $hash, "token" => $token];
}
function device_session_context_payload(
    string $scope,
    ?int $clinicRoleId = null,
): array {
    /*
     * GUIA DE MANUTENÇÃO — device_session_context_payload
     * Responsabilidade: Implementa a responsabilidade “device session context payload” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `device_session_remember_after_login`, `device_session_update_current_context`.
     * Dependências chamadas: `one`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $scope = $scope === "global" ? "global" : "clinic";
    $clinicId = null;
    $roleCode = null;
    if ($scope === "clinic" && $clinicRoleId && $clinicRoleId > 0) {
        try {
            $r = one(
                "SELECT clinic_id,role_code FROM pi_user_roles WHERE id=? LIMIT 1",
                [$clinicRoleId],
            );
            if ($r) {
                $clinicId = (int) $r["clinic_id"];
                $roleCode = (string) $r["role_code"];
            }
        } catch (Throwable $e) {
            error_log("[Prontoo device context] " . $e->getMessage());
        }
    }
    return [$scope, $clinicRoleId ?: null, $clinicId, $roleCode];
}
function device_session_remember_after_login(
    int $uid,
    string $scope,
    ?int $clinicRoleId = null,
    ?array $payload = null,
): void {
    /*
     * GUIA DE MANUTENÇÃO — device_session_remember_after_login
     * Responsabilidade: Implementa a responsabilidade “device session remember after login” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `login_apply_resolved_credential`.
     * Dependências chamadas: `device_login_payload_from_post`, `device_hash_is_valid`, `device_fallback_hash`, `bin2hex`, `random_bytes`, `device_session_context_payload`, `trim`, `hash`, `q`, `device_token_hash`, `route`, `function_exists` e mais 8.
     * Estado externo lido: `$_SERVER`, `$_SESSION`, `$_COOKIE`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; lê ou altera a sessão; consome dados da requisição HTTP.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    security_retire_persistent_devices_for_user($uid);
}
function device_session_update_current_context(
    string $scope,
    ?int $clinicRoleId = null,
): void {
    /*
     * GUIA DE MANUTENÇÃO — device_session_update_current_context
     * Responsabilidade: Valida e executa a mutação “device session update current context”, preservando as invariantes do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_profile`, `propagate_user_role_permissions`.
     * Dependências chamadas: `device_session_context_payload`, `q`, `route`, `function_exists`, `login_last_credential_remember`, `error_log`, `->getMessage`.
     * Estado externo lido: `$_SESSION`, `$_SERVER`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; lê ou altera a sessão; consome dados da requisição HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    security_clear_legacy_device_cookie();
}
function device_session_enforce_current(int $uid): void
{
    /*
     * GUIA DE MANUTENÇÃO — device_session_enforce_current
     * Responsabilidade: Implementa a responsabilidade “device session enforce current” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `ctx`.
     * Dependências chamadas: `time`, `device_session_lifetime_seconds`, `secure_session_destroy`, `device_cookie_clear`, `header`, `href`, `one`, `app_storage_timestamp`, `q`, `route`, `error_log`, `->getMessage`.
     * Estado externo lido: `$_SESSION`, `$_SERVER`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; lê ou altera a sessão; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    security_clear_legacy_device_cookie();
}
function device_session_auto_login(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — device_session_auto_login
     * Responsabilidade: Implementa a responsabilidade “device session auto login” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_login_autotest`.
     * Dependências chamadas: `device_cookie_unpack`, `one`, `app_storage_timestamp`, `time`, `hash_equals`, `device_token_hash`, `device_cookie_clear`, `session_harden_after_login`, `function_exists`, `login_last_credential_remember`, `single_active_role_cleanup_for_user`, `device_session_lifetime_seconds` e mais 5.
     * Estado externo lido: `$_SERVER`, `$_SESSION`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; lê ou altera a sessão; consome dados da requisição HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    security_clear_legacy_device_cookie();
    return false;
}
function device_session_revoke_current(): void
{
    /*
     * GUIA DE MANUTENÇÃO — device_session_revoke_current
     * Responsabilidade: Implementa a responsabilidade “device session revoke current” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_logout`, `propagate_user_role_permissions`, `security_session_generation_enforce`, `secure_relogin_after_forbidden_action`.
     * Dependências chamadas: `q`, `error_log`, `->getMessage`, `device_cookie_clear`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; lê ou altera a sessão; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    $uid = (int) ($_SESSION["uid"] ?? 0);
    security_retire_persistent_devices_for_user($uid);
}
function secure_session_destroy(): void
{
    /*
     * GUIA DE MANUTENÇÃO — secure_session_destroy
     * Responsabilidade: Implementa a responsabilidade “secure session destroy” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_logout`, `boot_security`, `security_session_generation_enforce`, `device_session_enforce_current`, `secure_relogin_after_forbidden_action`.
     * Dependências chamadas: `ini_get`, `session_get_cookie_params`, `setcookie`, `session_name`, `time`, `session_destroy`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), "", [
            "expires" => time() - 42000,
            "path" => $p["path"] ?? "/",
            "domain" => $p["domain"] ?? "",
            "secure" => (bool) ($p["secure"] ?? false),
            "httponly" => true,
            "samesite" => $p["samesite"] ?? "Lax",
        ]);
    }
    session_destroy();
}
/* Guia de manutenção: Exige MFA e reautenticação recente antes de materializar contexto global privilegiado. */
function security_global_scope_verified(int $uid): bool
{
    if ($uid <= 0) {
        return false;
    }
    $mfaAt = (int) ($_SESSION["mfa_verified_at"] ?? 0);
    $privilegedAt = (int) ($_SESSION["privileged_auth_at"] ?? 0);
    $born = (int) ($_SESSION["born"] ?? 0);
    return $mfaAt > 0 &&
        $privilegedAt > 0 &&
        $mfaAt >= $born &&
        $privilegedAt >= $born;
}
function session_clinic_scope_id(): int
{
    /*
     * GUIA DE MANUTENÇÃO — session_clinic_scope_id
     * Responsabilidade: Implementa a responsabilidade “session clinic scope id” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `save_person_flexible`, `fetch_map`, `log_runtime_error`, `scope_guard_active_clinic_id`.
     * Dependências chamadas: `.Core.Tenant.TenantRegistry::sessionClinicId`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return \Prontoo\Core\Tenant\TenantRegistry::sessionClinicId();
}
function session_clinic_role_code(): string
{
    /*
     * GUIA DE MANUTENÇÃO — session_clinic_role_code
     * Responsabilidade: Implementa a responsabilidade “session clinic role code” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `record_scope_violation`.
     * Dependências chamadas: `.Core.Tenant.TenantRegistry::sessionRoleCode`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return \Prontoo\Core\Tenant\TenantRegistry::sessionRoleCode();
}
function tenant_scoped_tables(): array
{
    /*
     * GUIA DE MANUTENÇÃO — tenant_scoped_tables
     * Responsabilidade: Implementa a responsabilidade “tenant scoped tables” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `.Core.Tenant.TenantRegistry::scopedTables`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return \Prontoo\Core\Tenant\TenantRegistry::scopedTables();
}
function tenant_table_is_scoped(string $table): bool
{
    /*
     * GUIA DE MANUTENÇÃO — tenant_table_is_scoped
     * Responsabilidade: Avalia ou impõe a regra “tenant table is scoped”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `fetch_map`, `closure@app/Domain/Audit/AuditActivity.php:368`, `require_same_clinic_entity`.
     * Dependências chamadas: `.Core.Tenant.TenantRegistry::isScoped`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return \Prontoo\Core\Tenant\TenantRegistry::isScoped($table);
}
function sql_fingerprint(string $sql): string
{
    /*
     * GUIA DE MANUTENÇÃO — sql_fingerprint
     * Responsabilidade: Implementa a responsabilidade “sql fingerprint” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `record_scope_violation`.
     * Dependências chamadas: `hash`, `preg_replace`, `trim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return hash("sha256", preg_replace("/\s+/", " ", trim($sql)));
}
function scope_violation_detail_decode(mixed $details): array
{
    /*
     * GUIA DE MANUTENÇÃO — scope_violation_detail_decode
     * Responsabilidade: Transforma e normaliza “scope violation detail decode” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_scope_evidence_html`, `scope_violation_detail_summary`.
     * Dependências chamadas: `trim`, `json_decode`, `is_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $raw = trim((string) $details);
    if ($raw === "") {
        return ["v" => 1, "reason" => "Motivo não registrado."];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || (int) ($decoded["v"] ?? 0) < 2) {
        return ["v" => 1, "reason" => $raw];
    }
    return [
        "v" => (int) ($decoded["v"] ?? 2),
        "reason" => trim((string) ($decoded["reason"] ?? "")),
        "method" => trim((string) ($decoded["method"] ?? "")),
        "action" => trim((string) ($decoded["action"] ?? "")),
        "operation" => trim((string) ($decoded["operation"] ?? "")),
        "table" => trim((string) ($decoded["table"] ?? "")),
        "sql_shape" => trim((string) ($decoded["sql_shape"] ?? "")),
    ];
}
function scope_violation_detail_summary(mixed $details): string
{
    /*
     * GUIA DE MANUTENÇÃO — scope_violation_detail_summary
     * Responsabilidade: Implementa a responsabilidade “scope violation detail summary” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `maestro_fetch_candidates`.
     * Dependências chamadas: `scope_violation_detail_decode`, `trim`, `array_values`, `array_filter`, `implode`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $payload = scope_violation_detail_decode($details);
    $summary = trim((string) ($payload["reason"] ?? ""));
    $context = array_values(
        array_filter([
            trim((string) ($payload["operation"] ?? "")),
            trim((string) ($payload["table"] ?? "")),
            trim((string) ($payload["action"] ?? "")),
        ]),
    );
    if ($context) {
        $summary .= ($summary !== "" ? " · " : "") . implode(" · ", $context);
    }
    return $summary !== "" ? $summary : "Motivo não registrado.";
}
function scope_violation_safe_reason(string $detail): string
{
    /*
     * GUIA DE MANUTENÇÃO — scope_violation_safe_reason
     * Responsabilidade: Implementa a responsabilidade “scope violation safe reason” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `scope_violation_evidence_payload`.
     * Dependências chamadas: `preg_replace`, `trim`, `mb_substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $detail = preg_replace('/#\d+\b/', "#?", $detail) ?? $detail;
    $detail = preg_replace('/\b(?:usuário|registro)\s+\d+\b/iu', '$1 ?', $detail) ?? $detail;
    $detail = preg_replace("/\s+/", " ", trim($detail)) ?? trim($detail);
    return mb_substr($detail, 0, 140);
}
function scope_violation_sql_shape(string $sql): string
{
    /*
     * GUIA DE MANUTENÇÃO — scope_violation_sql_shape
     * Responsabilidade: Implementa a responsabilidade “scope violation sql shape” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `scope_violation_evidence_payload`.
     * Dependências chamadas: `preg_replace`, `trim`, `mb_substr`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $shape = preg_replace('/\/\*.*?\*\//s', " ", $sql) ?? $sql;
    $shape = preg_replace('/--[^\r\n]*/', " ", $shape) ?? $shape;
    $shape = preg_replace(
        "/'(?:''|\\\\.|[^'])*'|\"(?:\"\"|\\\\.|[^\"])*\"/s",
        "?",
        $shape,
    ) ?? $shape;
    $shape = preg_replace('/\b(?:0x[0-9a-f]+|\d+(?:\.\d+)?)\b/i', "?", $shape) ?? $shape;
    $shape = preg_replace("/\s+/", " ", trim($shape)) ?? trim($shape);
    return mb_substr($shape, 0, 170);
}
function scope_violation_evidence_payload(string $sql, string $detail): string
{
    /*
     * GUIA DE MANUTENÇÃO — scope_violation_evidence_payload
     * Responsabilidade: Implementa a responsabilidade “scope violation evidence payload” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `record_scope_violation`.
     * Dependências chamadas: `preg_match`, `strtoupper`, `preg_replace`, `trim`, `scope_violation_safe_reason`, `mb_substr`, `scope_violation_sql_shape`, `json_encode`, `is_string`, `strlen`.
     * Estado externo lido: `$_POST`, `$_SERVER`.
     * Efeitos colaterais: consome dados da requisição HTTP; produz conteúdo de saída.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $operation = preg_match('/^\s*(UPDATE|DELETE|INSERT|REPLACE)\b/i', $sql, $m)
        ? strtoupper((string) $m[1])
        : "ACCESS";
    $table = "";
    $tablePattern = match ($operation) {
        "UPDATE" => '/^\s*UPDATE\s+`?([a-z0-9_]+)`?/i',
        "INSERT", "REPLACE" => '/^\s*(?:INSERT|REPLACE)\s+(?:(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE)\s+)*INTO\s+`?([a-z0-9_]+)`?/i',
        "DELETE" => '/^\s*DELETE\s+FROM\s+`?([a-z0-9_]+)`?/i',
        default => '',
    };
    if ($tablePattern !== "" && preg_match($tablePattern, $sql, $tableMatch)) {
        $table = preg_replace('/[^a-z0-9_]/i', "", (string) ($tableMatch[1] ?? "")) ?: "";
    }
    $action = preg_replace(
        '/[^a-z0-9_.:\-]/i',
        "_",
        trim((string) ($_POST["act"] ?? "")),
    ) ?:
        "";
    $method = preg_replace(
        '/[^A-Z]/',
        "",
        strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "")),
    ) ?:
        "";
    $payload = [
        "v" => 2,
        "reason" => scope_violation_safe_reason($detail),
        "method" => mb_substr($method, 0, 8),
        "action" => mb_substr($action, 0, 48),
        "operation" => mb_substr($operation, 0, 10),
        "table" => mb_substr($table, 0, 64),
        "sql_shape" => scope_violation_sql_shape($sql),
    ];
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return scope_violation_safe_reason($detail);
    }
    if (strlen($encoded) > 580) {
        $payload["sql_shape"] = mb_substr((string) $payload["sql_shape"], 0, 100);
        $payload["reason"] = mb_substr((string) $payload["reason"], 0, 100);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return is_string($encoded) ? $encoded : scope_violation_safe_reason($detail);
}
function record_scope_violation(
    string $key,
    string $sql,
    string $detail = "",
): void {
    /*
     * GUIA DE MANUTENÇÃO — record_scope_violation
     * Responsabilidade: Valida e executa a mutação “record scope violation”, preservando as invariantes do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `require_user_in_clinic`, `require_same_clinic_entity`.
     * Dependências chamadas: `scope_guard_active_clinic_id`, `sql_fingerprint`, `route`, `hash`, `session_clinic_role_code`, `scope_violation_evidence_payload`, `class_exists`, `.Core.Integrity.PiIntegrity::prepareRuntimeQuery`, `pdo`, `->prepare`, `->execute`, `error_log` e mais 1.
     * Estado externo lido: `$_POST`, `$_SESSION`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; lê ou altera a sessão; consome dados da requisição HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    $cid = scope_guard_active_clinic_id();
    if ($cid <= 0) {
        return;
    }
    $fingerprint = sql_fingerprint($sql);
    $requestRoute = route();
    $dedupKey = hash(
        "sha256",
        $cid . "|" . $key . "|" . $fingerprint . "|" . $requestRoute . "|" . (string) ($_POST["act"] ?? ""),
    );
    static $recorded = [];
    if (isset($recorded[$dedupKey])) {
        return;
    }
    $recorded[$dedupKey] = true;
    try {
        $ins =
            "INSERT INTO pi_scope_violations (clinic_id,user_id,role_code,route,violation_key,sql_fingerprint,details,created_at) VALUES (?,?,?,?,?,?,?,NOW())";
        $params = [
            $cid,
            $_SESSION["uid"] ?? null,
            session_clinic_role_code(),
            $requestRoute,
            $key,
            $fingerprint,
            scope_violation_evidence_payload($sql, $detail),
        ];
        if (class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")) {
            [
                $ins,
                $params,
            ] = \Prontoo\Core\Integrity\PiIntegrity::prepareRuntimeQuery(
                $ins,
                $params,
            );
        }
        $st = pdo()->prepare($ins);
        $st->execute($params);
    } catch (Throwable $e) {
        error_log(
            "[Prontoo scope violation] " .
                $key .
                " | " .
                $fingerprint .
                " | " .
                $e->getMessage(),
        );
    }
}
function read_only_post_allowed(string $route): bool
{
    /*
     * GUIA DE MANUTENÇÃO — read_only_post_allowed
     * Responsabilidade: Avalia ou impõe a regra “read only post allowed”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `read_only_write_allowed`, `enforce_read_only`.
     * Dependências chamadas: `.Core.Readonly.ReadonlyPolicy::postAllowed`.
     * Estado externo lido: `$_POST`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return \Prontoo\Core\Readonly\ReadonlyPolicy::postAllowed(
        $route,
        (string) ($_POST["act"] ?? ""),
    );
}
function read_only_write_allowed(): bool
{
    /*
     * GUIA DE MANUTENÇÃO — read_only_write_allowed
     * Responsabilidade: Avalia ou impõe a regra “read only write allowed”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `read_only_post_allowed`, `route`.
     * Estado externo lido: `$GLOBALS`, `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!empty($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"])) {
        return true;
    }
    if (($_SESSION["scope"] ?? "") !== "clinic") {
        return true;
    }
    return read_only_post_allowed(route());
}
function read_only_allowed_write_tables_for_request(): array
{
    /*
     * GUIA DE MANUTENÇÃO — read_only_allowed_write_tables_for_request
     * Responsabilidade: Avalia ou impõe a regra “read only allowed write tables for request”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `.Core.Readonly.ReadonlyPolicy::allowedWriteTables`, `route`.
     * Estado externo lido: `$GLOBALS`, `$_POST`, `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão; consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!empty($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"])) {
        return ["*"];
    }
    return \Prontoo\Core\Readonly\ReadonlyPolicy::allowedWriteTables(
        route(),
        (string) ($_POST["act"] ?? ""),
        ($_SESSION["scope"] ?? "") === "clinic",
    );
}
function read_only_write_allowed_for_sql(string $sql): bool
{
    /*
     * GUIA DE MANUTENÇÃO — read_only_write_allowed_for_sql
     * Responsabilidade: Avalia ou impõe a regra “read only write allowed for sql”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `.Core.Readonly.ReadonlyPolicy::sqlAllowed`, `route`.
     * Estado externo lido: `$GLOBALS`, `$_POST`, `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão; consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!empty($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"])) {
        return true;
    }
    return \Prontoo\Core\Readonly\ReadonlyPolicy::sqlAllowed(
        $sql,
        route(),
        (string) ($_POST["act"] ?? ""),
        ($_SESSION["scope"] ?? "") === "clinic",
    );
}
function with_read_only_guard_disabled(callable $fn): mixed
{
    /*
     * GUIA DE MANUTENÇÃO — with_read_only_guard_disabled
     * Responsabilidade: Avalia ou impõe a regra “with read only guard disabled”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `seed_clinic_roles`, `ensure_clinic_trial_active`, `maestro_with_guarded_clinic`, `arrow@app/Domain/Maestro/Maestro.php:2273`, `seed_permissions`.
     * Dependências chamadas: `array_key_exists`.
     * Estado externo lido: `$GLOBALS`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $had = array_key_exists("PRONTOO_READONLY_GUARD_DISABLED", $GLOBALS);
    $prev = $GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"] ?? null;
    $GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"] = true;
    try {
        return $fn();
    } finally {
        if ($had) {
            $GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"] = $prev;
        } else {
            unset($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"]);
        }
    }
}
function with_scope_guard_disabled(callable $fn): mixed
{
    /*
     * GUIA DE MANUTENÇÃO — with_scope_guard_disabled
     * Responsabilidade: Avalia ou impõe a regra “with scope guard disabled”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `maestro_grant_runtime_access`, `scope_guard_context_selftest`, `arrow@app/Support/SecurityAccess.php:1226`.
     * Dependências chamadas: `LogicException`, `array_key_exists`.
     * Classes ou serviços instanciados: `LogicException`.
     * Estado externo lido: `$GLOBALS`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (
        (int) ($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] ?? 0) > 0
    ) {
        throw new LogicException(
            "Contextos de consultório e sistema não podem ser combinados.",
        );
    }
    $had = array_key_exists("PRONTOO_SCOPE_GUARD_SYSTEM", $GLOBALS);
    $prev = $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] ?? null;
    $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] = true;
    try {
        return $fn();
    } finally {
        if ($had) {
            $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] = $prev;
        } else {
            unset($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"]);
        }
    }
}
function scope_guard_expected_clinic_id(): int
{
    /*
     * GUIA DE MANUTENÇÃO — scope_guard_expected_clinic_id
     * Responsabilidade: Avalia ou impõe a regra “scope guard expected clinic id”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `scope_guard_active_clinic_id`, `with_scope_guard_clinic`, `scope_guard_context_selftest`, `arrow@app/Support/SecurityAccess.php:1203`, `arrow@app/Support/SecurityAccess.php:1207`, `arrow@app/Support/SecurityAccess.php:1209`, `sql_write_scope_guard`.
     * Dependências chamadas: `max`.
     * Estado externo lido: `$GLOBALS`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return max(
        0,
        (int) ($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] ?? 0),
    );
}
function scope_guard_active_clinic_id(): int
{
    /*
     * GUIA DE MANUTENÇÃO — scope_guard_active_clinic_id
     * Responsabilidade: Avalia ou impõe a regra “scope guard active clinic id”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `record_scope_violation`.
     * Dependências chamadas: `scope_guard_expected_clinic_id`, `session_clinic_scope_id`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $expected = scope_guard_expected_clinic_id();
    return $expected > 0 ? $expected : session_clinic_scope_id();
}
function with_scope_guard_clinic(int $clinicId, callable $fn): mixed
{
    /*
     * GUIA DE MANUTENÇÃO — with_scope_guard_clinic
     * Responsabilidade: Avalia ou impõe a regra “with scope guard clinic”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `maestro_with_guarded_clinic`, `scope_guard_context_selftest`, `arrow@app/Support/SecurityAccess.php:1207`, `arrow@app/Support/SecurityAccess.php:1216`, `arrow@app/Support/SecurityAccess.php:1236`.
     * Dependências chamadas: `InvalidArgumentException`, `LogicException`, `array_key_exists`, `scope_guard_expected_clinic_id`.
     * Classes ou serviços instanciados: `InvalidArgumentException`, `LogicException`.
     * Estado externo lido: `$GLOBALS`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($clinicId <= 0) {
        throw new InvalidArgumentException(
            "O contexto determinístico do guardião exige um consultório válido.",
        );
    }
    if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"])) {
        throw new LogicException(
            "Contextos de sistema e consultório não podem ser combinados.",
        );
    }
    $had = array_key_exists(
        "PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID",
        $GLOBALS,
    );
    $previous = scope_guard_expected_clinic_id();
    if ($previous > 0 && $previous !== $clinicId) {
        throw new LogicException(
            "Uma execução não pode trocar de consultório durante a mesma operação.",
        );
    }
    $GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] = $clinicId;
    try {
        return $fn();
    } finally {
        if ($had) {
            $GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] = $previous;
        } else {
            unset($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"]);
        }
    }
}
function scope_guard_context_selftest(): array
{
    /*
     * GUIA DE MANUTENÇÃO — scope_guard_context_selftest
     * Responsabilidade: Avalia ou impõe a regra “scope guard context selftest”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `platform_backend_selftest`, `page_admin_security`.
     * Dependências chamadas: `array_key_exists`, `with_scope_guard_clinic`, `scope_guard_expected_clinic_id`, `with_scope_guard_disabled`, `array_keys`, `array_filter`, `count`.
     * Estado externo lido: `$GLOBALS`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $hadExpected = array_key_exists(
        "PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID",
        $GLOBALS,
    );
    $previousExpected =
        $GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] ?? null;
    $hadSystem = array_key_exists("PRONTOO_SCOPE_GUARD_SYSTEM", $GLOBALS);
    $previousSystem = $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] ?? null;
    unset(
        $GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"],
        $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"],
    );
    $cases = [];
    try {
        $cases["explicit_clinic_is_active"] = with_scope_guard_clinic(
            17,
            static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `scope_guard_expected_clinic_id`. Efeitos: transformação local sem efeito externo detectado. */ fn(): bool => scope_guard_expected_clinic_id() === 17,
        );
        $cases["same_clinic_can_nest"] = with_scope_guard_clinic(
            17,
            static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `with_scope_guard_clinic`, `scope_guard_expected_clinic_id`. Efeitos: transformação local sem efeito externo detectado. */ fn(): bool => with_scope_guard_clinic(
                17,
                static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `scope_guard_expected_clinic_id`. Efeitos: transformação local sem efeito externo detectado. */ fn(): bool => scope_guard_expected_clinic_id() === 17,
            ),
        );
        $clinicSwitchBlocked = false;
        try {
            with_scope_guard_clinic(
                17,
                static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `with_scope_guard_clinic`. Efeitos: transformação local sem efeito externo detectado. */ fn() => with_scope_guard_clinic(18, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn() => true),
            );
        } catch (LogicException $expected) {
            $clinicSwitchBlocked = true;
        }
        $cases["clinic_switch_is_blocked"] = $clinicSwitchBlocked;
        $systemInsideClinicBlocked = false;
        try {
            with_scope_guard_clinic(
                17,
                static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `with_scope_guard_disabled`. Efeitos: transformação local sem efeito externo detectado. */ fn() => with_scope_guard_disabled(static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn() => true),
            );
        } catch (LogicException $expected) {
            $systemInsideClinicBlocked = true;
        }
        $cases["system_inside_clinic_is_blocked"] =
            $systemInsideClinicBlocked;
        $clinicInsideSystemBlocked = false;
        try {
            with_scope_guard_disabled(
                static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `with_scope_guard_clinic`. Efeitos: transformação local sem efeito externo detectado. */ fn() => with_scope_guard_clinic(17, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn() => true),
            );
        } catch (LogicException $expected) {
            $clinicInsideSystemBlocked = true;
        }
        $cases["clinic_inside_system_is_blocked"] =
            $clinicInsideSystemBlocked;
        $cases["context_is_restored"] =
            !array_key_exists(
                "PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID",
                $GLOBALS,
            ) && !array_key_exists("PRONTOO_SCOPE_GUARD_SYSTEM", $GLOBALS);
    } finally {
        if ($hadExpected) {
            $GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] =
                $previousExpected;
        } else {
            unset($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"]);
        }
        if ($hadSystem) {
            $GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"] = $previousSystem;
        } else {
            unset($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"]);
        }
    }
    $failed = array_keys(array_filter($cases, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn($ok) => !$ok));
    return [
        "ok" => $failed === [],
        "passed" => count($cases) - count($failed),
        "total" => count($cases),
        "failed" => $failed,
    ];
}
function sql_table_hit(string $norm, string $table): bool
{
    /*
     * GUIA DE MANUTENÇÃO — sql_table_hit
     * Responsabilidade: Monta a representação de interface associada a “sql table hit” sem alterar o contrato visual externo.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `strtolower`, `preg_match`, `preg_quote`, `strpos`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $tl = strtolower($table);
    return (bool) preg_match("/\b" . preg_quote($tl, "/") . "\b/", $norm) ||
        strpos($norm, "`" . $tl . "`") !== false;
}
function sql_write_scope_guard(string $sql, array $params = []): void
{
    /*
     * GUIA DE MANUTENÇÃO — sql_write_scope_guard
     * Responsabilidade: Avalia ou impõe a regra “sql write scope guard”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `q`.
     * Dependências chamadas: `preg_match`, `strtoupper`, `hash`, `preg_replace`, `trim`, `scope_guard_expected_clinic_id`, `route`, `.Core.Database.SqlScopeGuard::guard`, `count`.
     * Estado externo lido: `$GLOBALS`, `$_SESSION`, `$_POST`.
     * Efeitos colaterais: lê ou altera a sessão; consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"])) {
        return;
    }
    $kind = preg_match("/^\s*(UPDATE|DELETE|INSERT|REPLACE)\s+/i", $sql, $m)
        ? strtoupper($m[1])
        : "";
    if ($kind === "") {
        return;
    }
    $cacheable = empty($params) && !preg_match("/\?/", $sql);
    $key = $cacheable
        ? hash(
            "sha256",
            $kind .
                "|" .
                preg_replace("/\s+/", " ", trim($sql)) .
                "|" .
                (string) ($_SESSION["scope"] ?? "") .
                "|" .
                (string) ($_SESSION["clinic_id"] ?? "") .
                "|" .
                scope_guard_expected_clinic_id() .
                "|" .
                (string) ($_POST["act"] ?? "") .
                "|" .
                route(),
        )
        : "";
    static $ok = [];
    if ($key !== "" && isset($ok[$key])) {
        return;
    }
    \Prontoo\Core\Database\SqlScopeGuard::guard($sql, $params);
    if ($key !== "" && count($ok) < 256) {
        $ok[$key] = 1;
    }
}
function require_same_clinic_entity(
    int $cid,
    string $table,
    int $id,
    string $cols = "id",
): array {
    /*
     * GUIA DE MANUTENÇÃO — require_same_clinic_entity
     * Responsabilidade: Avalia ou impõe a regra “require same clinic entity”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `require_patient_in_clinic`.
     * Dependências chamadas: `allowed_db_table`, `tenant_table_is_scoped`, `RuntimeException`, `safe_db_columns`, `ProntooHttpError`, `one`, `record_scope_violation`.
     * Classes ou serviços instanciados: `RuntimeException`, `ProntooHttpError`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $table = allowed_db_table($table);
    if (!tenant_table_is_scoped($table)) {
        throw new RuntimeException("Tabela sem escopo de consultório.");
    }
    $cols = safe_db_columns($cols);
    if ($cid <= 0 || $id <= 0) {
        throw new ProntooHttpError(
            403,
            "Registro não pertence ao consultório ativo.",
        );
    }
    $row = one("SELECT $cols FROM $table WHERE id=? AND clinic_id=? LIMIT 1", [
        $id,
        $cid,
    ]);
    if (!$row) {
        record_scope_violation(
            "entity_outside_clinic",
            "SELECT $cols FROM $table WHERE id=? AND clinic_id=?",
            "Registro " .
                $table .
                "#" .
                $id .
                " não encontrado no consultório ativo.",
        );
        throw new ProntooHttpError(
            403,
            "Registro não pertence ao consultório ativo.",
        );
    }
    return $row;
}
function prontoo_icon_matrix(): array
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_icon_matrix
     * Responsabilidade: Implementa a responsabilidade “prontoo icon matrix” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_icon_for`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: produz conteúdo de saída.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    return [
        "context" => [
            "painel" => "space_dashboard",
            "admin_painel" => "space_dashboard",
            "operations" => "account_tree",
            "admin_operations" => "account_tree",
            "maestro" => "event_repeat",
            "leads" => "person_search",
            "patients" => "patient_list",
            "creditors" => "receipt_long",
            "people" => "groups",
            "appointments" => "calendar_month",
            "financial" => "payments",
            "cash" => "point_of_sale",
            "procedures" => "medical_services",
            "documents" => "description",
            "tasks" => "task_alt",
            "notices" => "campaign",
            "admin_alerts" => "campaign",
            "users" => "groups",
            "permissions" => "admin_panel_settings",
            "audit" => "history",
            "settings" => "home_health",
            "clinic" => "home_health",
            "admin_clinics" => "home_health",
            "admin_health" => "crisis_alert",
            "admin_maintenance" => "construction",
            "admin_settings" => "settings",
        ],
        "operation" => [
            "summary" => "space_dashboard",
            "daily" => "today",
            "weekly" => "view_week",
            "monthly" => "calendar_month",
            "received" => "inbox",
            "sent" => "outbox",
            "new_notice" => "add_comment",
            "new_patient" => "person_add",
            "new_lead" => "person_search",
            "schedule" => "event_available",
            "block" => "event_busy",
            "save" => "save",
            "edit" => "edit",
            "delete" => "delete",
            "close" => "close",
            "confirm" => "check_circle",
            "print" => "print",
            "view" => "visibility",
            "search" => "search",
            "archive" => "archive",
        ],
        "label" => [
            "painel" => "space_dashboard",
            "operação" => "account_tree",
            "fluxo" => "account_tree",
            "maestro" => "event_repeat",
            "interessados" => "person_search",
            "pacientes" => "patient_list",
            "credores" => "receipt_long",
            "pessoas" => "groups",
            "agenda" => "calendar_month",
            "diário" => "today",
            "diario" => "today",
            "semanal" => "view_week",
            "mensal" => "calendar_month",
            "financeiro" => "payments",
            "caixa" => "point_of_sale",
            "procedimentos" => "medical_services",
            "documentos" => "description",
            "tarefas" => "task_alt",
            "avisos" => "campaign",
            "aviso" => "campaign",
            "recebidos" => "inbox",
            "enviados" => "outbox",
            "novo aviso" => "add_comment",
            "colaboradores" => "groups",
            "permissões" => "admin_panel_settings",
            "permissoes" => "admin_panel_settings",
            "atividades" => "history",
            "auditoria" => "history",
            "consultório" => "home_health",
            "consultorio" => "home_health",
            "dados" => "home_health",
            "departamentos" => "corporate_fare",
            "aparência" => "palette",
            "aparencia" => "palette",
            "assinatura" => "credit_card",
            "configurações" => "settings",
            "configuracoes" => "settings",
            "consultórios" => "home_health",
            "consultorios" => "home_health",
            "incidentes" => "crisis_alert",
            "erros" => "bug_report",
            "diagnóstico" => "troubleshoot",
            "diagnostico" => "troubleshoot",
            "integridade" => "verified_user",
            "segurança" => "security",
            "seguranca" => "security",
            "manutenção" => "construction",
            "manutencao" => "construction",
            "excluídos" => "restore_from_trash",
            "excluidos" => "restore_from_trash",
            "onboarding" => "assignment_turned_in",
        ],
    ];
}
function prontoo_icon_for(string $key, string $fallback = "monitoring"): string
{
    /*
     * GUIA DE MANUTENÇÃO — prontoo_icon_for
     * Responsabilidade: Implementa a responsabilidade “prontoo icon for” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_icon_for_route_label`, `actions`, `role_actions`, `role_actions_effective`.
     * Dependências chamadas: `mb_strtolower`, `trim`, `prontoo_icon_matrix`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $key = mb_strtolower(trim($key));
    if ($key === "") {
        return $fallback;
    }
    $m = prontoo_icon_matrix();
    foreach (["context", "operation", "label"] as $group) {
        if (isset($m[$group][$key])) {
            return (string) $m[$group][$key];
        }
    }
    return $fallback;
}
function prontoo_icon_for_route_label(
    string $route,
    string $label = "",
    array $params = [],
    string $fallback = "monitoring",
): string {
    /*
     * GUIA DE MANUTENÇÃO — prontoo_icon_for_route_label
     * Responsabilidade: Monta a representação de interface associada a “prontoo icon for route label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page`, `operation_link_html`, `page_head_icon_name`, `action_icon_for`, `form_submit_icon`.
     * Dependências chamadas: `trim`, `mb_strtolower`, `prontoo_icon_for`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $route = trim($route);
    $labelKey = mb_strtolower(trim($label));
    if ($route === "financial" && $labelKey === "caixa") {
        return prontoo_icon_for("cash", $fallback);
    }
    if ($route === "patients" && $labelKey === "pessoas") {
        return prontoo_icon_for("people", $fallback);
    }
    if ($route === "appointments") {
        $view = (string) ($params["view"] ?? "");
        if ($view === "resumo") {
            return prontoo_icon_for("summary", $fallback);
        }
        if ($view === "diario") {
            return prontoo_icon_for("daily", $fallback);
        }
        if ($view === "semanal") {
            return prontoo_icon_for("weekly", $fallback);
        }
        if ($view === "mensal") {
            return prontoo_icon_for("monthly", $fallback);
        }
    }
    if ($route === "settings") {
        $tab = (string) ($params["tab"] ?? "");
        if ($tab === "visual") {
            return prontoo_icon_for("aparência", $fallback);
        }
        if ($tab === "assinatura") {
            return prontoo_icon_for("assinatura", $fallback);
        }
        if ($tab === "setores") {
            return prontoo_icon_for("departamentos", $fallback);
        }
        if ($tab === "perfil") {
            return prontoo_icon_for("dados", $fallback);
        }
    }
    if ($route === "admin_alerts") {
        if (!empty($params["compose"])) {
            return prontoo_icon_for("new_notice", $fallback);
        }
        $view = (string) ($params["view"] ?? "");
        if ($view === "sent") {
            return prontoo_icon_for("sent", $fallback);
        }
        if ($view === "received") {
            return prontoo_icon_for("received", $fallback);
        }
    }
    if ($labelKey !== "" && ($ico = prontoo_icon_for($labelKey, "")) !== "") {
        return $ico;
    }
    if ($route !== "" && ($ico = prontoo_icon_for($route, "")) !== "") {
        return $ico;
    }
    return $fallback;
}
function actions(): array
{
    /*
     * GUIA DE MANUTENÇÃO — actions
     * Responsabilidade: Implementa a responsabilidade “actions” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_onboarding`, `clinic_restore_default_permissions_for_role`, `permission_module_defs`, `permission_module_available_for_role`, `collaborator_permission_matrix_base`, `role_actions`, `effective_allowed_modules_for_roles`, `default_permissions` e mais 3.
     * Dependências chamadas: `prontoo_icon_for`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    return [
        "painel" => [
            "label" => "Painel",
            "icon" => prontoo_icon_for("painel"),
            "roles" => ["recepcionista", "assistente", "medico", "gerente"],
        ],
        "operations" => [
            "label" => "Fluxo",
            "icon" => prontoo_icon_for("operations"),
            "roles" => ["gerente"],
        ],
        "maestro" => [
            "label" => "Rotinas",
            "icon" => prontoo_icon_for("maestro"),
            "roles" => ["gerente"],
        ],
        "leads" => [
            "label" => "Interessados",
            "icon" => prontoo_icon_for("leads"),
            "roles" => ["recepcionista", "gerente"],
        ],
        "patients" => [
            "label" => "Pacientes",
            "icon" => prontoo_icon_for("patients"),
            "roles" => ["recepcionista", "assistente", "medico", "gerente"],
        ],
        "creditors" => [
            "label" => "Credores",
            "icon" => prontoo_icon_for("creditors"),
            "roles" => ["gerente"],
        ],
        "appointments" => [
            "label" => "Agenda",
            "icon" => prontoo_icon_for("appointments"),
            "roles" => ["recepcionista", "assistente", "medico", "gerente"],
        ],
        "financial" => [
            "label" => "Financeiro",
            "icon" => prontoo_icon_for("financial"),
            "roles" => ["recepcionista", "gerente"],
        ],
        "procedures" => [
            "label" => "Procedimentos",
            "icon" => prontoo_icon_for("procedures"),
            "roles" => ["gerente"],
        ],
        "documents" => [
            "label" => "Documentos",
            "icon" => prontoo_icon_for("documents"),
            "roles" => ["recepcionista", "assistente", "medico", "gerente"],
        ],
        "tasks" => [
            "label" => "Tarefas",
            "icon" => prontoo_icon_for("tasks"),
            "roles" => ["recepcionista", "assistente", "medico", "gerente"],
        ],
        "notices" => [
            "label" => "Avisos",
            "icon" => prontoo_icon_for("notices"),
            "roles" => ["recepcionista", "assistente", "medico", "gerente"],
        ],
        "users" => [
            "label" => "Colaboradores",
            "icon" => prontoo_icon_for("users"),
            "roles" => ["gerente"],
        ],
        "permissions" => [
            "label" => "Permissões",
            "icon" => prontoo_icon_for("permissions"),
            "roles" => ["gerente"],
        ],
        "audit" => [
            "label" => "Atividades",
            "icon" => prontoo_icon_for("audit"),
            "roles" => ["recepcionista", "assistente", "medico", "gerente"],
        ],
        "settings" => [
            "label" => "Consultório",
            "icon" => prontoo_icon_for("settings"),
            "roles" => ["medico", "gerente"],
        ],
    ];
}
function role_actions(string $role): array
{
    /*
     * GUIA DE MANUTENÇÃO — role_actions
     * Responsabilidade: Implementa a responsabilidade “role actions” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `role_actions_effective`.
     * Dependências chamadas: `actions`, `prontoo_icon_for`, `function_exists`, `reception_cash_state_icon`, `array_keys`, `in_array`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $all = actions();
    if (isset($all["patients"])) {
        $all["patients"]["label"] = "Pessoas";
        $all["patients"]["icon"] = prontoo_icon_for("people");
    }
    if ($role === "recepcionista" && isset($all["financial"])) {
        $all["financial"]["label"] = "Caixa";
        $all["financial"]["icon"] = function_exists("reception_cash_state_icon")
            ? reception_cash_state_icon()
            : "lock_clock";
    }
    $orders = [
        "recepcionista" => [
            "appointments",
            "patients",
            "documents",
            "tasks",
            "financial",
            "audit",
        ],
        "assistente" => [
            "appointments",
            "patients",
            "documents",
            "tasks",
            "audit",
        ],
        "medico" => ["appointments", "patients", "documents", "tasks", "audit"],
        "gerente" => [
            "appointments",
            "patients",
            "documents",
            "tasks",
            "financial",
            "settings",
            "maestro",
            "audit",
        ],
    ];
    $order = $orders[$role] ?? array_keys($all);
    if (!in_array("audit", $order, true)) {
        $order[] = "audit";
    }
    $out = [];
    foreach ($order as $key) {
        if (
            isset($all[$key]) &&
            $key !== "notices" &&
            ($key === "audit" || in_array($role, $all[$key]["roles"], true))
        ) {
            $out[$key] = $all[$key];
        }
    }
    return $out;
}
function role_rank(string $role): int
{
    /*
     * GUIA DE MANUTENÇÃO — role_rank
     * Responsabilidade: Implementa a responsabilidade “role rank” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `primary_role_from_codes`, `arrow@app/Support/SecurityAccess.php:1693`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $rank = [
        "recepcionista" => 10,
        "assistente" => 20,
        "medico" => 30,
        "gerente" => 40,
    ];
    return $rank[$role] ?? 0;
}
function primary_role_from_codes(array $roles): string
{
    /*
     * GUIA DE MANUTENÇÃO — primary_role_from_codes
     * Responsabilidade: Implementa a responsabilidade “primary role from codes” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `array_values`, `array_unique`, `array_map`, `usort`, `role_rank`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $roles = array_values(array_unique(array_map("strval", $roles)));
    usort($roles, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: `role_rank`. Efeitos: transformação local sem efeito externo detectado. */ fn($a, $b) => role_rank($b) <=> role_rank($a));
    return $roles[0] ?? "";
}
function has_effective_role(array $c, string $role): bool
{
    /*
     * GUIA DE MANUTENÇÃO — has_effective_role
     * Responsabilidade: Avalia ou impõe a regra “has effective role”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_appointments`, `save_document_draft`, `discard_document_draft`, `confirm_document_issue`, `page_procedures`, `page_creditors`, `page_maestro`, `page_painel` e mais 5.
     * Dependências chamadas: `in_array`, `array_map`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return in_array(
        $role,
        array_map(
            "strval",
            (array) ($c["effective_roles"] ?? [$c["role"] ?? ""]),
        ),
        true,
    );
}
function role_actions_effective(array $roles): array
{
    /*
     * GUIA DE MANUTENÇÃO — role_actions_effective
     * Responsabilidade: Implementa a responsabilidade “role actions effective” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page`.
     * Dependências chamadas: `array_values`, `array_unique`, `array_filter`, `array_map`, `role_actions`, `function_exists`, `reception_cash_state_icon`, `prontoo_icon_for`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    $roles = array_values(
        array_unique(array_filter(array_map("strval", $roles))),
    );
    if (!$roles) {
        return [];
    }
    $order = [
        "appointments",
        "painel",
        "leads",
        "patients",
        "documents",
        "tasks",
        "financial",
        "settings",
        "procedures",
        "users",
        "permissions",
        "operations",
        "maestro",
        "audit",
    ];
    $out = [];
    foreach ($roles as $role) {
        foreach (role_actions($role) as $key => $a) {
            if ($key === "notices") {
                continue;
            }
            if ($key === "financial" && $role === "recepcionista") {
                $a["label"] = "Caixa";
                $a["icon"] = function_exists("reception_cash_state_icon")
                    ? reception_cash_state_icon()
                    : prontoo_icon_for("cash", "lock_clock");
            }
            $out[$key] = $a;
        }
    }
    $sorted = [];
    foreach ($order as $key) {
        if (isset($out[$key])) {
            $sorted[$key] = $out[$key];
        }
    }
    foreach ($out as $key => $a) {
        if (!isset($sorted[$key])) {
            $sorted[$key] = $a;
        }
    }
    return $sorted;
}
function effective_allowed_modules_for_roles(int $cid, array $roles): array
{
    /*
     * GUIA DE MANUTENÇÃO — effective_allowed_modules_for_roles
     * Responsabilidade: Avalia ou impõe a regra “effective allowed modules for roles”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `ctx`.
     * Dependências chamadas: `array_values`, `array_unique`, `array_filter`, `array_map`, `in_array`, `array_keys`, `actions`, `implode`, `array_fill`, `count`, `q`, `array_merge` e mais 3.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    $roles = array_values(
        array_unique(array_filter(array_map("strval", $roles))),
    );
    if ($cid <= 0 || !$roles) {
        return [];
    }
    if (in_array("gerente", $roles, true)) {
        return array_keys(actions());
    }
    $ph = implode(",", array_fill(0, count($roles), "?"));
    try {
        $allowed = q(
            "SELECT DISTINCT action_key FROM pi_permissions WHERE clinic_id=? AND role_code IN ($ph) AND allowed=1",
            array_merge([$cid], $roles),
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_unique(array_map("strval", $allowed ?: [])));
    } catch (Throwable $e) {
        error_log("[Prontoo effective permissions] " . $e->getMessage());
        return [];
    }
}
function default_permissions(): array
{
    /*
     * GUIA DE MANUTENÇÃO — default_permissions
     * Responsabilidade: Implementa a responsabilidade “default permissions” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_onboarding`, `clinic_restore_default_permissions_for_role`, `seed_permissions`, `closure@app/Support/SecurityAccess.php:1819`.
     * Dependências chamadas: `array_keys`, `actions`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return [
        "recepcionista" => [
            "painel",
            "leads",
            "appointments",
            "patients",
            "financial",
            "tasks",
            "documents",
            "notices",
            "audit",
        ],
        "assistente" => [
            "painel",
            "appointments",
            "patients",
            "tasks",
            "documents",
            "notices",
            "audit",
        ],
        "medico" => [
            "painel",
            "appointments",
            "patients",
            "tasks",
            "documents",
            "notices",
            "audit",
        ],
        "gerente" => array_keys(actions()),
    ];
}
function seed_permissions(int $clinicId): void
{
    /*
     * GUIA DE MANUTENÇÃO — seed_permissions
     * Responsabilidade: Implementa a responsabilidade “seed permissions” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_signup`, `propagate_user_role_permissions`.
     * Dependências chamadas: `with_read_only_guard_disabled`, `default_permissions`, `actions`, `q`, `in_array`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     */
    with_read_only_guard_disabled(function () use ($clinicId): void {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Support/SecurityAccess.php:1819
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de serviços transversais de suporte.
         * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `default_permissions`, `actions`, `q`, `in_array`.
         * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados.
         * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
         * Cuidado 2: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
         */
        foreach (default_permissions() as $role => $keys) {
            foreach (actions() as $key => $a) {
                q(
                    "INSERT INTO pi_permissions (clinic_id, role_code, action_key, allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=allowed",
                    [
                        $clinicId,
                        $role,
                        $key,
                        in_array($key, $keys, true) ? 1 : 0,
                    ],
                );
            }
        }
    });
}
function secret_key(): string
{
    /*
     * GUIA DE MANUTENÇÃO — secret_key
     * Responsabilidade: Implementa a responsabilidade “secret key” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `login_key`, `verify_audit_row`, `audit`, `closure@app/Domain/Audit/AuditActivity.php:2161`, `person_signature_value`, `device_token_hash`, `device_fallback_hash`.
     * Dependências chamadas: `val`, `cfg`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return (string) (val(
        "SELECT meta_value FROM pi_meta WHERE meta_key='app_secret'",
    ) ??
        (cfg()["secret"] ?? "prontoo"));
}
function billing_state(array $clinic): array
{
    /*
     * GUIA DE MANUTENÇÃO — billing_state
     * Responsabilidade: Implementa a responsabilidade “billing state” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_clinic_detail_page`, `page_admin_clinics`, `ctx`.
     * Dependências chamadas: `now`, `app_storage_timestamp`, `time`, `default_trial_days`, `default_monthly_price_cents`, `app_date_only_end_timestamp`, `trim`, `max`, `ceil`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $started = $clinic["trial_started_at"] ?: $clinic["created_at"] ?? now();
    $startedTs = app_storage_timestamp($started);
    if ($startedTs <= 0) {
        $startedTs = time();
    }
    $trialEnd =
        $clinic["trial_ends_at"] ?:
        (string) ($startedTs + default_trial_days() * 86400);
    $paidUntil = $clinic["paid_until"] ?? null;
    $status = (string) ($clinic["subscription_status"] ?? "trial");
    $price =
        (int) ($clinic["monthly_price_cents"] ??
            default_monthly_price_cents()) ?:
        default_monthly_price_cents();
    $trialEndTs = app_storage_timestamp($trialEnd, true);
    $paidUntilTs = $paidUntil
        ? app_date_only_end_timestamp(
            $paidUntil,
            (int) ($clinic["id"] ?? ($clinic["clinic_id"] ?? 0)),
            $clinic,
        )
        : 0;
    $trialActive = $trialEndTs >= time();
    $paidActive = $paidUntilTs >= time();
    $exempt = $status === "exempt";
    $activeWithoutDue =
        $status === "active" && trim((string) $paidUntil) === "";
    $readOnly =
        $status === "read_only" ||
        !($trialActive || $paidActive || $exempt || $activeWithoutDue);
    if ($readOnly) {
        $label = "Somente leitura";
    } elseif ($exempt) {
        $label = "Isento";
    } else {
        $label = "Ativo";
    }
    $days = $trialActive
        ? max(0, (int) ceil(($trialEndTs - time()) / 86400))
        : 0;
    return [
        "status" => $status,
        "label" => $label,
        "trial_started_at" => $started,
        "trial_ends_at" => $trialEnd,
        "paid_until" => $paidUntil,
        "price_cents" => $price,
        "trial_active" => $trialActive,
        "paid_active" => $paidActive,
        "exempt" => $exempt,
        "read_only" => $readOnly,
        "trial_days_left" => $days,
    ];
}
function billing_notice(array $c): string
{
    /*
     * GUIA DE MANUTENÇÃO — billing_notice
     * Responsabilidade: Implementa a responsabilidade “billing notice” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page`.
     * Dependências chamadas: `function_exists`, `has_effective_role`, `role_label_for`, `trim`, `icon`, `e`, `money_br`, `href`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (($c["scope"] ?? "") !== "clinic") {
        return "";
    }
    $b = $c["billing"] ?? [];
    if (!$b || empty($b["read_only"])) {
        return "";
    }
    $isAdmin = function_exists("has_effective_role")
        ? has_effective_role($c, "gerente")
        : (string) ($c["role"] ?? "") === "gerente";
    $clinicId = (int) ($c["clinic_id"] ?? 0);
    $adminLabel = function_exists("role_label_for")
        ? role_label_for("gerente", $clinicId)
        : PRONTOO_ROLES["gerente"] ?? "Administrador";
    $adminLabel = trim((string) $adminLabel) ?: "Administrador";
    if (!$isAdmin) {
        return '<section class="billing-banner billing-readonly-banner ds-readonly-banner billing-user-readonly-banner" role="status" aria-label="Modo somente leitura"><span class="billing-readonly-icon">' .
            icon("lock") .
            '</span><span class="billing-readonly-copy"><span class="eyebrow">Modo somente leitura</span><b>Alterações bloqueadas</b><small>Aguarde até que ' .
            e($adminLabel) .
            " desabilite este modo.</small></span></section>";
    }
    $price = isset($b["price_cents"]) ? money_br((int) $b["price_cents"]) : "";
    $link =
        '<a class="primary small billing-link" href="' .
        e(href("settings", ["tab" => "assinatura"])) .
        '">' .
        icon("credit_card") .
        "<span>Regularizar</span></a>";
    $support =
        '<a class="ghost small billing-support-link" href="' .
        e(href("notices")) .
        '">' .
        icon("support_agent") .
        "<span>Suporte</span></a>";
    $detail =
        $price !== ""
            ? "Pagamento pendente: " .
                $price .
                ". Alterações temporariamente bloqueadas até a regularização."
            : "Alterações temporariamente bloqueadas até a regularização.";
    return '<section class="billing-banner billing-readonly-banner ds-readonly-banner billing-admin-readonly-banner" role="status" aria-label="Assinatura pendente"><span class="billing-readonly-icon">' .
        icon("credit_card") .
        '</span><span class="billing-readonly-copy"><span class="eyebrow">Assinatura</span><b>Pagamento pendente</b><small>' .
        e($detail) .
        '</small></span><nav class="billing-readonly-actions" aria-label="Ações da assinatura">' .
        $link .
        $support .
        "</nav></section>";
}
function enforce_read_only(array $c, string $route): void
{
    /*
     * GUIA DE MANUTENÇÃO — enforce_read_only
     * Responsabilidade: Localiza, carrega ou resolve os dados de “enforce read only” para consumo pelas camadas superiores.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_run`.
     * Dependências chamadas: `read_only_post_allowed`, `audit`, `flash`, `redirect`.
     * Estado externo lido: `$_SERVER`, `$_POST`.
     * Efeitos colaterais: consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 2: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
        return;
    }
    if (($c["scope"] ?? "") !== "clinic") {
        return;
    }
    if (read_only_post_allowed($route)) {
        return;
    }
    $b = $c["billing"] ?? [];
    if (empty($b["read_only"])) {
        return;
    }
    audit(
        "somente_leitura_bloqueio",
        "assinatura",
        (string) ($c["clinic_id"] ?? ""),
        ["rota" => $route, "acao" => (string) ($_POST["act"] ?? "")],
    );
    flash("Assinatura pendente: regularize para alterar dados.", "bad");
    redirect($route);
}
function enforce_action_integrity(array $c, string $route): void
{
    /*
     * GUIA DE MANUTENÇÃO — enforce_action_integrity
     * Responsabilidade: Implementa a responsabilidade “enforce action integrity” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `prontoo_run`.
     * Dependências chamadas: `.Core.Integrity.ActionProof::enforce`.
     * Estado externo lido: `$_SERVER`, `$_POST`.
     * Efeitos colaterais: consome dados da requisição HTTP.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    \Prontoo\Core\Integrity\ActionProof::enforce(
        $route,
        (string) ($_SERVER["REQUEST_METHOD"] ?? "GET"),
        $_POST,
        $c,
    );
}
function ctx(): array
{
    /*
     * GUIA DE MANUTENÇÃO — ctx
     * Responsabilidade: Implementa a responsabilidade “ctx” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `admin_maestro_health_time_label`, `page_admin_painel`, `page_login`, `page_signup`, `normalize_db_datetime`, `datetime_local_value`, `agenda_note_card_html`, `audit` e mais 19.
     * Dependências chamadas: `device_session_enforce_current`, `security_session_generation_enforce`, `function_exists`, `server_json_cache_context_key`, `server_json_cache_get`, `server_json_cache_ttl`, `is_array`, `server_json_cache_apply_context_session`, `one`, `server_json_cache_sanitize_context`, `server_json_cache_set`, `app_global_admin_timezone` e mais 16.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; lê ou altera a sessão; lê, grava ou invalida cache.
     * Cuidado 1: O cache é derivado: preserve TTL, chave por escopo e invalidação por tags; nunca o trate como fonte de verdade.
     */
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $uid = (int) ($_SESSION["uid"] ?? 0);
    if ($uid <= 0) {
        return $c = [];
    }

    security_session_generation_enforce($uid);

    $scopeHint = (string) ($_SESSION["scope"] ?? "global");
    $clinicHint = (int) ($_SESSION["clinic_id"] ?? 0);
    $ucHint = (int) ($_SESSION["uc_id"] ?? 0);
    $roleHint = (string) ($_SESSION["role_code"] ?? "");
    if (
        $scopeHint === "global" &&
        !security_global_scope_verified($uid)
    ) {
        secure_session_destroy();
        if (!headers_sent()) {
            header(
                "Location: " . href("login", ["relogin" => "1"]),
            );
        }
        exit();
    }
    if (
        function_exists("server_json_cache_context_key") &&
        function_exists("server_json_cache_get")
    ) {
        $cacheKey = server_json_cache_context_key(
            $uid,
            $scopeHint,
            $clinicHint,
            $ucHint,
            $roleHint,
        );
        $cached = server_json_cache_get(
            "context",
            $cacheKey,
            server_json_cache_ttl("context"),
        );
        if (
            is_array($cached) &&
            !empty($cached["scope"]) &&
            isset($cached["user"]["id"]) &&
            (int) $cached["user"]["id"] === $uid
        ) {
            server_json_cache_apply_context_session($cached);
            return $c = $cached;
        }
    }

    $user = one(
        "SELECT id,person_id,name,email,password_hash,is_global_admin,active,failed_login_count,locked_until,last_login_at,created_at,updated_at FROM pi_users WHERE id=? AND active=1",
        [$uid],
    );
    if (!$user) {
        return $c = [];
    }
    $person =
        one("SELECT cpf,birth_date FROM pi_persons WHERE id=?", [
            (int) $user["person_id"],
        ]) ?:
        [];
    $user += [
        "cpf" => $person["cpf"] ?? "",
        "birth_date" => $person["birth_date"] ?? null,
    ];

    $storeContext = function (array $ctx) use (
        $uid,
        $scopeHint,
        $clinicHint,
        $ucHint,
        $roleHint,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Support/SecurityAccess.php:2045
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de serviços transversais de suporte.
         * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `function_exists`, `server_json_cache_sanitize_context`, `server_json_cache_context_key`, `server_json_cache_set`, `server_json_cache_ttl`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $ctx = function_exists("server_json_cache_sanitize_context")
            ? server_json_cache_sanitize_context($ctx)
            : $ctx;
        if (
            function_exists("server_json_cache_set") &&
            function_exists("server_json_cache_context_key")
        ) {
            $key = server_json_cache_context_key(
                $uid,
                $scopeHint,
                $clinicHint,
                $ucHint,
                $roleHint,
            );
            server_json_cache_set(
                "context",
                $key,
                $ctx,
                server_json_cache_ttl("context"),
                ["user:" . $uid, "context"],
            );
        }
        return $ctx;
    };

    if (
        (int) $user["is_global_admin"] === 1 &&
        ($_SESSION["scope"] ?? "global") === "global" &&
        security_global_scope_verified($uid)
    ) {
        $_SESSION["scope"] = "global";
        unset(
            $_SESSION["clinic_id"],
            $_SESSION["role_code"],
            $_SESSION["uc_id"],
        );
        $tz = app_global_admin_timezone((int) $user["id"]);
        $ctx = [
            "scope" => "global",
            "user" => $user,
            "role" => "administrador",
            "effective_roles" => ["administrador"],
            "clinic_id" => null,
            "clinic" => "Painel do Desenvolvedor",
            "timezone" => $tz,
            "roles" => [],
            "allowed" => array_flip(array_keys(PRONTOO_ADMIN_ACTIONS)),
        ];
        app_apply_request_timezone($tz);
        return $c = $storeContext($ctx);
    }

    if (function_exists("single_active_role_cleanup_for_user")) {
        single_active_role_cleanup_for_user($uid, null);
    }
    if (function_exists("clinic_enable_roles_from_active_user_links")) {
        clinic_enable_roles_from_active_user_links($uid);
    }
    $links = q(
        "SELECT id,user_id,clinic_id,role_code,is_owner,active,created_at FROM pi_user_roles WHERE user_id=? AND active=1 ORDER BY clinic_id ASC, is_owner DESC, FIELD(role_code,'gerente','medico','assistente','recepcionista'), id ASC LIMIT 80",
        [$uid],
    )->fetchAll();
    if (!$links) {
        if (
            (int) $user["is_global_admin"] === 1 &&
            security_global_scope_verified($uid)
        ) {
            $_SESSION["scope"] = "global";
            unset(
                $_SESSION["clinic_id"],
                $_SESSION["role_code"],
                $_SESSION["uc_id"],
            );
            $tz = app_global_admin_timezone((int) $user["id"]);
            $ctx = [
                "scope" => "global",
                "user" => $user,
                "role" => "administrador",
                "effective_roles" => ["administrador"],
                "clinic_id" => null,
                "clinic" => "Painel do Desenvolvedor",
                "timezone" => $tz,
                "roles" => [],
                "allowed" => array_flip(array_keys(PRONTOO_ADMIN_ACTIONS)),
            ];
            app_apply_request_timezone($tz);
            return $c = $storeContext($ctx);
        }
        return $c = [];
    }
    $clinicIds = int_ids($links, "clinic_id");
    $clinics = fetch_map(
        "pi_clinics",
        $clinicIds,
        "id,display_name,timezone,address_state,address_city,responsible_profession,clinic_icon,accent_color,created_at,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents,active",
    );
    $roles = [];
    foreach ($links as $ln) {
        $cl = $clinics[(int) $ln["clinic_id"]] ?? null;
        if (!$cl || (int) ($cl["active"] ?? 0) !== 1) {
            continue;
        }
        $ln["clinic_name"] = $cl["display_name"];
        $ln["clinic_timezone"] = $cl["timezone"];
        $ln["address_state"] = $cl["address_state"];
        $ln["address_city"] = $cl["address_city"];
        $ln["responsible_profession"] =
            $cl["responsible_profession"] ?? "Médico(a)";
        $ln["clinic_icon"] = $cl["clinic_icon"] ?? "medical_services";
        $ln["accent_color"] = $cl["accent_color"] ?? "#334155";
        $ln["clinic_created_at"] = $cl["created_at"];
        $ln["trial_started_at"] = $cl["trial_started_at"];
        $ln["trial_ends_at"] = $cl["trial_ends_at"];
        $ln["subscription_status"] = $cl["subscription_status"];
        $ln["paid_until"] = $cl["paid_until"];
        $ln["monthly_price_cents"] = $cl["monthly_price_cents"];
        $roles[] = $ln;
    }
    if (!$roles) {
        return $c = [];
    }
    $wantClinic = (int) ($_SESSION["clinic_id"] ?? 0);
    if ($wantClinic <= 0 && (int) ($_SESSION["uc_id"] ?? 0) > 0) {
        foreach ($roles as $r) {
            if ((int) $r["id"] === (int) $_SESSION["uc_id"]) {
                $wantClinic = (int) $r["clinic_id"];
                break;
            }
        }
    }
    if ($wantClinic <= 0) {
        $wantClinic = (int) $roles[0]["clinic_id"];
    }
    $clinicRoles = array_values(
        array_filter(
            $roles,
            static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn($r) => (int) $r["clinic_id"] === $wantClinic,
        ),
    );
    if (!$clinicRoles) {
        $wantClinic = (int) $roles[0]["clinic_id"];
        $clinicRoles = array_values(
            array_filter(
                $roles,
                static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de serviços transversais de suporte. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn($r) => (int) $r["clinic_id"] === $wantClinic,
            ),
        );
    }
    $chosen = $clinicRoles[0];
    foreach ($clinicRoles as $r) {
        if (
            (int) ($_SESSION["uc_id"] ?? 0) > 0 &&
            (int) $r["id"] === (int) $_SESSION["uc_id"]
        ) {
            $chosen = $r;
            break;
        }
        if ((int) $r["is_owner"] === 1) {
            $chosen = $r;
        }
    }
    $primary = (string) $chosen["role_code"];
    $effectiveRoles = [$primary];
    $_SESSION["uc_id"] = (int) $chosen["id"];
    $_SESSION["scope"] = "clinic";
    $_SESSION["clinic_id"] = $wantClinic;
    $_SESSION["role_code"] = $primary;
    $_SESSION["effective_roles"] = $effectiveRoles;
    app_apply_request_timezone(
        valid_timezone((string) ($chosen["clinic_timezone"] ?? "America/Cuiaba")),
    );
    if (function_exists("maestro_runtime_upgrade")) {
        maestro_runtime_upgrade();
    }
    $allowed = effective_allowed_modules_for_roles(
        $wantClinic,
        $effectiveRoles,
    );
    $billing = billing_state([
        "id" => $wantClinic,
        "clinic_id" => $wantClinic,
        "timezone" => $chosen["clinic_timezone"] ?? "America/Cuiaba",
        "created_at" => $chosen["clinic_created_at"] ?? null,
        "trial_started_at" => $chosen["trial_started_at"] ?? null,
        "trial_ends_at" => $chosen["trial_ends_at"] ?? null,
        "subscription_status" => $chosen["subscription_status"] ?? "trial",
        "paid_until" => $chosen["paid_until"] ?? null,
        "monthly_price_cents" =>
            $chosen["monthly_price_cents"] ?? default_monthly_price_cents(),
    ]);
    $ctx = [
        "scope" => "clinic",
        "user" => $user,
        "role" => $primary,
        "effective_roles" => $effectiveRoles,
        "clinic_id" => $wantClinic,
        "clinic" => $chosen["clinic_name"],
        "timezone" => valid_timezone(
            (string) ($chosen["clinic_timezone"] ?? "America/Cuiaba"),
        ),
        "responsible_profession" =>
            (string) ($chosen["responsible_profession"] ?? "Médico(a)"),
        "clinic_icon" =>
            (string) ($chosen["clinic_icon"] ?? "medical_services"),
        "accent_color" => (string) ($chosen["accent_color"] ?? "#334155"),
        "roles" => $roles,
        "uc_id" => (int) $chosen["id"],
        "clinic_roles" => [$chosen],
        "allowed" => array_flip($allowed),
        "billing" => $billing,
    ];
    app_apply_request_timezone((string) $ctx["timezone"]);
    return $c = $storeContext($ctx);
}
function need_login(): array
{
    /*
     * GUIA DE MANUTENÇÃO — need_login
     * Responsabilidade: Implementa a responsabilidade “need login” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `onboarding_tip_dismiss`, `page_profile`, `page_switch`, `page_onboarding`, `page_document_pdf_file`, `page_document_pdf`, `page_document_view`, `page_document_print` e mais 3.
     * Dependências chamadas: `ctx`, `redirect`.
     * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    $c = ctx();
    if (!$c) {
        redirect("login");
    }
    return $c;
}
function can(string $action): bool
{
    /*
     * GUIA DE MANUTENÇÃO — can
     * Responsabilidade: Avalia ou impõe a regra “can”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_person_lookup`, `document_can_access`, `page_operations`, `page_patient_lookup`, `page_patient_suggest`, `page_patient`, `require_can`, `page` e mais 2.
     * Dependências chamadas: `ctx`, `str_starts_with`, `function_exists`, `has_effective_role`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $c = ctx();
    if (!$c) {
        return false;
    }
    if ($c["scope"] === "global") {
        return str_starts_with($action, "admin_");
    }
    if (($c["scope"] ?? "") === "clinic" && $action === "creditors") {
        return function_exists("has_effective_role") &&
            has_effective_role($c, "gerente") &&
            isset(($c["allowed"] ?? [])["patients"]);
    }
    $normallyAllowed = isset(($c["allowed"] ?? [])[$action]);
    if (!empty(($c["billing"] ?? [])["read_only"])) {
        if ($action === "settings") {
            $isManager = function_exists("has_effective_role")
                ? has_effective_role($c, "gerente")
                : (string) ($c["role"] ?? "") === "gerente";
            return $normallyAllowed && $isManager;
        }
    }
    return $normallyAllowed;
}
function secure_relogin_after_forbidden_action(string $action): void
{
    /*
     * GUIA DE MANUTENÇÃO — secure_relogin_after_forbidden_action
     * Responsabilidade: Implementa a responsabilidade “secure relogin after forbidden action” dentro do módulo de serviços transversais de suporte.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `require_can`.
     * Dependências chamadas: `audit`, `error_log`, `->getMessage`, `device_session_revoke_current`, `device_cookie_clear`, `secure_session_destroy`, `headers_sent`, `header`, `href`.
     * Estado externo lido: `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão; controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 2: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    $uid = $_SESSION["uid"] ?? null;
    try {
        audit("acesso_negado_sessao_encerrada", "rota", $action, [
            "janela" => $action,
            "audit_body" =>
                "Sessão encerrada com segurança após divergência entre a rota solicitada e as permissões do cargo ativo.",
        ]);
    } catch (Throwable $e) {
        error_log("[Prontoo access guard audit] " . $e->getMessage());
    }
    try {
        if ((int) $uid > 0) {
            user_auth_generation_rotate((int) $uid);
            security_retire_persistent_devices_for_user((int) $uid);
        }
    } catch (Throwable $e) {
        error_log("[Prontoo access guard revocation] " . $e->getMessage());
    }
    secure_session_destroy();
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Location: " . href("login", ["relogin" => "1"]));
    }
    exit();
}
function require_can(string $action): array
{
    /*
     * GUIA DE MANUTENÇÃO — require_can
     * Responsabilidade: Avalia ou impõe a regra “require can”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Support/SecurityAccess.php (serviços transversais de suporte).
     * Chamadores detectados: `page_admin_operations`, `page_admin_deleted`, `page_admin_health`, `page_admin_diagnostics`, `page_admin_errors`, `page_admin_integrity`, `page_admin_maintenance`, `page_admin_painel` e mais 30.
     * Dependências chamadas: `need_login`, `can`, `secure_relogin_after_forbidden_action`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $c = need_login();
    if (!can($action)) {
        secure_relogin_after_forbidden_action($action);
    }
    return $c;
}
