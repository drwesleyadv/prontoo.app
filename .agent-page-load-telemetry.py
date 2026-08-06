from __future__ import annotations

import hashlib
import json
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent
VERSION = "1.8.6.2"
PREVIOUS_VERSION = "1.8.6.1"
BUILD = "1.8.6.2-page-load-source-of-truth"
SYNC_ID = "github-prontoo-1.8.6.2-page-load-source-of-truth"
SCRIPT = ROOT / ".agent-page-load-telemetry.py"
WORKFLOW = ROOT / ".github/workflows/agent-page-load-telemetry.yml"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace_once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"Trecho {label} encontrado {count} vez(es)")
    return source.replace(old, new, 1)


def replace_function(source: str, name: str, next_name: str, replacement: str) -> str:
    start_marker = f"function {name}("
    end_marker = f"function {next_name}("
    start = source.find(start_marker)
    end = source.find(end_marker, start + len(start_marker))
    if start < 0 or end < 0 or end <= start:
        raise RuntimeError(f"Contrato funcional não localizado: {name}")
    return source[:start] + replacement.rstrip() + "\n\n" + source[end:]


telemetry = read("app/Support/Telemetry.php")
telemetry = replace_function(
    telemetry,
    "telemetry_file",
    "telemetry_schema",
    '''function telemetry_file(): string
{
    return telemetry_storage_dir() . "/page-loads.jsonl";
}''',
)
telemetry = replace_function(
    telemetry,
    "telemetry_schema",
    "telemetry_retention_microseconds",
    '''function telemetry_schema(): string
{
    return "prontoo.telemetria.pagina.v2";
}

function telemetry_page_internal_routes(): array
{
    return [
        "login_telemetry_wave",
        "login_autotest",
        "goal_status",
        "patient_lookup",
        "patient_suggest",
        "person_lookup",
        "lead_lookup",
        "lead_patient_lookup",
        "counterparty_lookup",
        "counterparty_suggest",
    ];
}

function telemetry_page_request_candidate(string $route): bool
{
    $route = telemetry_route_safe($route);
    if (
        $route === "unknown" ||
        str_starts_with($route, "landing_") ||
        in_array($route, telemetry_page_internal_routes(), true)
    ) {
        return false;
    }
    $method = strtoupper(mb_trim((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")));
    if (!in_array($method, ["GET", "POST"], true)) {
        return false;
    }
    $requestedWith = strtolower(mb_trim((string) ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "")));
    if ($requestedWith !== "") {
        return false;
    }
    $purpose = strtolower(
        mb_trim(
            (string) ($_SERVER["HTTP_PURPOSE"] ?? "") .
                " " .
                (string) ($_SERVER["HTTP_SEC_PURPOSE"] ?? ""),
        ),
    );
    if (str_contains($purpose, "prefetch") || str_contains($purpose, "prerender")) {
        return false;
    }
    $mode = strtolower(mb_trim((string) ($_SERVER["HTTP_SEC_FETCH_MODE"] ?? "")));
    $destination = strtolower(mb_trim((string) ($_SERVER["HTTP_SEC_FETCH_DEST"] ?? "")));
    if ($mode !== "" || $destination !== "") {
        if ($mode !== "navigate" || $destination !== "document") {
            return false;
        }
    } elseif ($method !== "GET") {
        return false;
    }
    $accept = strtolower((string) ($_SERVER["HTTP_ACCEPT"] ?? ""));
    if (
        str_contains($accept, "application/json") &&
        !str_contains($accept, "text/html") &&
        !str_contains($accept, "application/xhtml+xml")
    ) {
        return false;
    }
    return true;
}

function telemetry_page_response_candidate(int $statusCode): bool
{
    if (
        $statusCode < 200 ||
        $statusCode === 204 ||
        $statusCode === 205 ||
        $statusCode === 304 ||
        ($statusCode >= 300 && $statusCode < 400)
    ) {
        return false;
    }
    $contentType = "";
    foreach (headers_list() as $header) {
        $normalized = strtolower(mb_trim($header));
        if (str_starts_with($normalized, "location:")) {
            return false;
        }
        if (
            str_starts_with($normalized, "content-disposition:") &&
            str_contains($normalized, "attachment")
        ) {
            return false;
        }
        if (str_starts_with($normalized, "content-type:")) {
            $contentType = mb_trim(substr($normalized, strlen("content-type:")));
        }
    }
    return $contentType === "" ||
        str_contains($contentType, "text/html") ||
        str_contains($contentType, "application/xhtml+xml");
}''',
)
telemetry = replace_function(
    telemetry,
    "telemetry_route_start_marker",
    "telemetry_route_identify",
    '''function telemetry_route_start_marker(
    string $route,
    ?int $startedMonotonicNs = null,
    ?int $startedUnixUs = null,
): void {
    if (
        PHP_SAPI === "cli" ||
        !empty($GLOBALS["PRONTOO_TELEMETRY_REGISTERED"]) ||
        !telemetry_page_request_candidate($route)
    ) {
        return;
    }
    $GLOBALS["PRONTOO_TELEMETRY_REGISTERED"] = true;
    $GLOBALS["PRONTOO_ROUTE_NAME"] = telemetry_route_safe($route);
    $GLOBALS["PRONTOO_ROUTE_STARTED_MONOTONIC_NS"] =
        $startedMonotonicNs ?? hrtime(true);
    $GLOBALS["PRONTOO_ROUTE_STARTED_UNIX_US"] =
        $startedUnixUs ?? (int) floor(microtime(true) * 1000000);
    $GLOBALS["PRONTOO_PAGE_LOAD_METHOD"] = strtoupper(
        mb_trim((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")),
    );
    $path = parse_url((string) ($_SERVER["REQUEST_URI"] ?? "/"), PHP_URL_PATH);
    $GLOBALS["PRONTOO_PAGE_LOAD_PATH"] =
        is_string($path) && str_starts_with($path, "/") ? $path : "/";
    register_shutdown_function(
        static fn(): mixed => telemetry_route_finish_marker(true),
    );
}''',
)
telemetry = replace_function(
    telemetry,
    "telemetry_build_event",
    "telemetry_normalize_event",
    '''function telemetry_build_event(
    string $route,
    int $startedMonotonicNs,
    int $finishedMonotonicNs,
    int $startedUnixUs,
    int $finishedUnixUs,
    int $statusCode,
    ?string $fatalError = null,
    ?string $release = null,
    ?string $method = null,
    ?string $path = null,
    string $finishMarker = "front_controller_last_useful_line",
): array {
    $durationNs = max(0, $finishedMonotonicNs - $startedMonotonicNs);
    $statusCode = $statusCode >= 100 && $statusCode <= 599 ? $statusCode : 200;
    $route = telemetry_route_safe($route);
    $method = strtoupper(mb_trim((string) ($method ?? "GET")));
    $method = in_array($method, ["GET", "POST"], true) ? $method : "GET";
    $path = mb_trim((string) ($path ?? "/"));
    $path = str_starts_with($path, "/") ? substr($path, 0, 240) : "/";
    $finishMarker = in_array(
        $finishMarker,
        ["front_controller_last_useful_line", "shutdown_fallback"],
        true,
    )
        ? $finishMarker
        : "front_controller_last_useful_line";
    return [
        "schema" => telemetry_schema(),
        "tipo" => "page_load",
        "evento_id" => substr(
            hash(
                "sha256",
                $route .
                    "|" .
                    $method .
                    "|" .
                    $path .
                    "|" .
                    $startedUnixUs .
                    "|" .
                    $finishedUnixUs .
                    "|" .
                    $durationNs,
            ),
            0,
            32,
        ),
        "rota" => $route,
        "metodo" => $method,
        "caminho" => $path,
        "marco_inicial" => "front_controller_first_executable_line",
        "marco_final" => $finishMarker,
        "inicio_utc" => telemetry_utc_from_unix_microseconds($startedUnixUs),
        "fim_utc" => telemetry_utc_from_unix_microseconds($finishedUnixUs),
        "inicio_unix_us" => $startedUnixUs,
        "fim_unix_us" => $finishedUnixUs,
        "inicio_monotonico_ns" => $startedMonotonicNs,
        "fim_monotonico_ns" => $finishedMonotonicNs,
        "duracao_ns" => $durationNs,
        "duracao_ms" => round(
            $durationNs / 1000000,
            6,
            \\RoundingMode::HalfAwayFromZero,
        ),
        "status_http" => $statusCode,
        "sucesso" => $fatalError === null && $statusCode < 500,
        "erro_fatal" => $fatalError,
        "versao" => mb_trim(
            (string) ($release ??
                (defined("PRONTOO_VERSION")
                    ? PRONTOO_VERSION
                    : (defined("BR_LANDING_VERSION")
                        ? BR_LANDING_VERSION
                        : "unknown"))),
        ),
    ];
}''',
)
telemetry = replace_function(
    telemetry,
    "telemetry_normalize_event",
    "telemetry_json_line",
    '''function telemetry_normalize_event(array $event): ?array
{
    if (
        (string) ($event["schema"] ?? "") !== telemetry_schema() ||
        (string) ($event["tipo"] ?? "") !== "page_load"
    ) {
        return null;
    }
    $route = telemetry_route_safe((string) ($event["rota"] ?? ""));
    $startedNs = (int) ($event["inicio_monotonico_ns"] ?? -1);
    $finishedNs = (int) ($event["fim_monotonico_ns"] ?? -1);
    $startedUs = (int) ($event["inicio_unix_us"] ?? 0);
    $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
    $durationNs = (int) ($event["duracao_ns"] ?? -1);
    $method = strtoupper(mb_trim((string) ($event["metodo"] ?? "")));
    $path = mb_trim((string) ($event["caminho"] ?? ""));
    $finishMarker = (string) ($event["marco_final"] ?? "");
    if (
        $route === "unknown" ||
        $startedNs < 0 ||
        $finishedNs < $startedNs ||
        $startedUs <= 0 ||
        $finishedUs <= 0 ||
        $durationNs !== $finishedNs - $startedNs ||
        !in_array($method, ["GET", "POST"], true) ||
        !str_starts_with($path, "/") ||
        (string) ($event["marco_inicial"] ?? "") !==
            "front_controller_first_executable_line" ||
        !in_array(
            $finishMarker,
            ["front_controller_last_useful_line", "shutdown_fallback"],
            true,
        )
    ) {
        return null;
    }
    $event["rota"] = $route;
    $event["metodo"] = $method;
    $event["caminho"] = substr($path, 0, 240);
    $event["duracao_ms"] = round(
        $durationNs / 1000000,
        6,
        \\RoundingMode::HalfAwayFromZero,
    );
    $event["status_http"] = max(
        100,
        min(599, (int) ($event["status_http"] ?? 200)),
    );
    $event["sucesso"] = (bool) ($event["sucesso"] ?? false);
    return $event;
}''',
)
telemetry = replace_function(
    telemetry,
    "telemetry_route_finish_marker",
    "telemetry_percentage_variation",
    '''function telemetry_route_finish_marker(bool $shutdownFallback = false): void
{
    static $finished = false;
    if (
        $finished ||
        PHP_SAPI === "cli" ||
        empty($GLOBALS["PRONTOO_TELEMETRY_REGISTERED"])
    ) {
        return;
    }
    $finished = true;
    $finishedMonotonicNs = hrtime(true);
    $finishedUnixUs = (int) floor(microtime(true) * 1000000);
    try {
        $startedMonotonicNs = (int) ($GLOBALS["PRONTOO_ROUTE_STARTED_MONOTONIC_NS"] ?? 0);
        $startedUnixUs = (int) ($GLOBALS["PRONTOO_ROUTE_STARTED_UNIX_US"] ?? 0);
        if ($startedMonotonicNs <= 0 || $startedUnixUs <= 0) {
            return;
        }
        $statusCode = (int) http_response_code();
        if (!telemetry_page_response_candidate($statusCode)) {
            return;
        }
        $fatalError = telemetry_fatal_error(error_get_last());
        $event = telemetry_build_event(
            (string) ($GLOBALS["PRONTOO_ROUTE_NAME"] ?? "unknown"),
            $startedMonotonicNs,
            $finishedMonotonicNs,
            $startedUnixUs,
            $finishedUnixUs,
            $statusCode,
            $fatalError,
            null,
            (string) ($GLOBALS["PRONTOO_PAGE_LOAD_METHOD"] ?? "GET"),
            (string) ($GLOBALS["PRONTOO_PAGE_LOAD_PATH"] ?? "/"),
            $shutdownFallback
                ? "shutdown_fallback"
                : "front_controller_last_useful_line",
        );
        if (!telemetry_append_event($event)) {
            error_log("[Prontoo telemetria] Carregamento de página não persistido.");
        }
        telemetry_prune($finishedUnixUs);
    } catch (Throwable $error) {
        error_log("[Prontoo telemetria] " . $error->getMessage());
    }
}''',
)
write("app/Support/Telemetry.php", telemetry)

index = read("index.php")
index = replace_once(
    index,
    '''    prontoo_install();
    exit;
''',
    '''    prontoo_install();
    telemetry_route_finish_marker();
    exit;
''',
    "fechamento do instalador pelo front controller",
)
index = replace_once(
    index,
    '''prontoo_run(false);
''',
    '''prontoo_run(false);
telemetry_route_finish_marker();
''',
    "fechamento da página principal",
)
write("index.php", index)

installer = read("install.php")
installer = replace_once(
    installer,
    '''prontoo_install();
''',
    '''prontoo_install();
telemetry_route_finish_marker();
''',
    "fechamento da entrada pública do instalador",
)
write("install.php", installer)

landing = read("br/index.php")
start_lines = '''$brRouteStartedMonotonicNs = hrtime(true);
$brRouteStartedUnixUs = (int) floor(microtime(true) * 1000000);
'''
landing = replace_once(
    landing,
    '''declare(strict_types=1);
if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
''',
    '''declare(strict_types=1);
$brRouteStartedMonotonicNs = hrtime(true);
$brRouteStartedUnixUs = (int) floor(microtime(true) * 1000000);
if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
''',
    "marco inicial da landing",
)
landing = replace_once(
    landing,
    start_lines,
    "",
    "remoção do marco inicial antigo da landing",
)
landing = replace_once(
    landing,
    '''require __DIR__ . "/landing/view-final.php";
''',
    '''require __DIR__ . "/landing/view-final.php";
telemetry_route_finish_marker();
''',
    "fechamento da landing",
)
write("br/index.php", landing)

contract = r'''<?php
declare(strict_types=1);

$telemetryContractRoot = dirname(__DIR__);
require_once $telemetryContractRoot . "/app/Support/Telemetry.php";
$telemetryContractAssert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Contrato de carregamento de página: " . $message);
    }
};
$telemetryContractServer = $_SERVER;
$telemetryContractScenario = static function (array $server, string $route): bool {
    $_SERVER = $server;
    return telemetry_page_request_candidate($route);
};
$telemetryContractAssert(
    $telemetryContractScenario(
        [
            "REQUEST_METHOD" => "GET",
            "HTTP_ACCEPT" => "text/html,application/xhtml+xml",
            "HTTP_SEC_FETCH_MODE" => "navigate",
            "HTTP_SEC_FETCH_DEST" => "document",
        ],
        "painel",
    ),
    "navegação HTML deve ser contabilizada",
);
foreach ([
    [
        [
            "REQUEST_METHOD" => "GET",
            "HTTP_ACCEPT" => "application/json",
            "HTTP_SEC_FETCH_MODE" => "cors",
            "HTTP_SEC_FETCH_DEST" => "empty",
        ],
        "login_telemetry_wave",
    ],
    [
        [
            "REQUEST_METHOD" => "GET",
            "HTTP_ACCEPT" => "application/json",
            "HTTP_X_REQUESTED_WITH" => "XMLHttpRequest",
        ],
        "patient_lookup",
    ],
    [
        [
            "REQUEST_METHOD" => "GET",
            "HTTP_ACCEPT" => "text/html",
            "HTTP_PURPOSE" => "prefetch",
        ],
        "patients",
    ],
    [
        [
            "REQUEST_METHOD" => "GET",
            "HTTP_ACCEPT" => "text/plain",
        ],
        "landing_robots",
    ],
] as [$server, $route]) {
    $telemetryContractAssert(
        !$telemetryContractScenario($server, $route),
        "requisição interna não pode ser contabilizada: " . $route,
    );
}
$_SERVER = $telemetryContractServer;
$telemetryContractEvent = telemetry_build_event(
    "painel",
    1000,
    2001000,
    1000000,
    1002000,
    200,
    null,
    "test",
    "GET",
    "/",
);
$telemetryContractAssert(
    telemetry_normalize_event($telemetryContractEvent) !== null,
    "evento v2 válido deve ser aceito",
);
$telemetryContractAssert(
    telemetry_normalize_event([
        "schema" => "prontoo.telemetria.rota.v1",
        "tipo" => "route_request",
    ]) === null,
    "evento legado de requisição não pode compor a fonte de verdade",
);
$telemetryContractAssert(
    telemetry_page_response_candidate(200),
    "resposta HTML sem cabeçalho explícito deve ser elegível",
);
$telemetryContractAssert(
    !telemetry_page_response_candidate(302),
    "redirecionamento não pode ser contabilizado como página carregada",
);
foreach (["index.php", "install.php", "br/index.php"] as $path) {
    $source = (string) file_get_contents($telemetryContractRoot . "/" . $path);
    $telemetryContractAssert(
        str_contains($source, "telemetry_route_finish_marker();"),
        "entrada sem marco final explícito: " . $path,
    );
    $telemetryContractAssert(
        strpos($source, "hrtime(true)") < strpos($source, "require"),
        "marco inicial deve anteceder o primeiro require: " . $path,
    );
}
$telemetryContractSource = (string) file_get_contents(
    $telemetryContractRoot . "/app/Support/Telemetry.php",
);
foreach ([
    "prontoo.telemetria.pagina.v2",
    "page-loads.jsonl",
    "front_controller_first_executable_line",
    "front_controller_last_useful_line",
    "shutdown_fallback",
] as $token) {
    $telemetryContractAssert(
        str_contains($telemetryContractSource, $token),
        "token obrigatório ausente: " . $token,
    );
}
unset(
    $telemetryContractRoot,
    $telemetryContractAssert,
    $telemetryContractServer,
    $telemetryContractScenario,
    $telemetryContractEvent,
    $telemetryContractSource,
);
'''
write("tools/page-load-telemetry-contract-check", contract)

architecture_check = read("tools/architecture-check.php")
require_line = 'require __DIR__ . "/page-load-telemetry-contract-check";\n'
if require_line not in architecture_check:
    architecture_check = replace_once(
        architecture_check,
        "declare(strict_types=1);\n",
        "declare(strict_types=1);\n" + require_line,
        "integração do contrato de carregamento",
    )
write("tools/architecture-check.php", architecture_check)

write(
    "docs/adr/0007-page-load-telemetry-source-of-truth.md",
    '''# ADR 0007 — Carregamento de página como fonte de verdade da telemetria

## Status

Aceito na versão 1.8.6.2.

## Contexto

A telemetria anterior registrava toda execução do front controller. Uma única navegação podia gerar eventos adicionais por `fetch`, XHR, consultas JSON, atualização do gráfico de telemetria e redirecionamentos. A métrica de requisições, por isso, não representava carregamentos de página.

## Decisão

A unidade contabilizada passa a ser uma navegação de documento HTML concluída. O candidato é identificado pelos metadados HTTP de navegação, pelo método, pelo destino e pelo formato aceito. Rotas internas, JSON, XHR, prefetch, prerender, assets, downloads e redirecionamentos não são persistidos.

O marco inicial é capturado na primeira linha executável do front controller, antes do primeiro `require`. O marco final normal é chamado explicitamente após a última etapa útil de renderização. O shutdown permanece apenas como fallback para encerramentos excepcionais e é identificado no evento.

Os eventos passam a usar o schema `prontoo.telemetria.pagina.v2` e o arquivo `ssd/telemetry/page-loads.jsonl`. Eventos legados de requisição não compõem os novos indicadores.

## Consequências

Cada navegação concluída produz no máximo um evento. Rotinas internas não alteram contagem nem tempo médio. Redirecionamentos somente contribuem por meio da página final realmente carregada. A série histórica reinicia semanticamente na publicação desta versão, preservando o arquivo legado sem utilizá-lo como fonte corrente.
''',
)
write(
    "docs/operations/page-load-telemetry.md",
    '''# Telemetria de carregamento de página

## Fonte de verdade

A fonte corrente é `ssd/telemetry/page-loads.jsonl`. Cada linha válida representa uma página HTML carregada, não uma requisição auxiliar.

## Marcos de duração

- início: `front_controller_first_executable_line`, capturado antes do primeiro carregamento de módulo;
- fim normal: `front_controller_last_useful_line`, chamado depois da renderização da página;
- fallback: `shutdown_fallback`, reservado para encerramentos excepcionais nos quais o fluxo normal não alcançou o fechamento explícito.

O tempo de persistência da própria telemetria e a compactação do arquivo ocorrem depois da captura do marco final e não contaminam a duração medida.

## Exclusões

Não são contabilizados JSON, XHR, `fetch`, rotas de lookup, atualização da meta, autoteste de login, atualização do gráfico, prefetch, prerender, assets, anexos e respostas de redirecionamento.

## Diagnóstico

Um evento deve conter `tipo=page_load`, schema `prontoo.telemetria.pagina.v2`, rota, método, caminho sem query string, marcos, duração, status e versão. O caminho não armazena parâmetros para evitar persistência de dados pessoais.

A validação permanente é executada por `tools/architecture-check.php`, que inclui `tools/page-load-telemetry-contract-check`.
''',
)

app = read("app/prontoo.php")
app = re.sub(
    r'const PRONTOO_VERSION_FALLBACK = ["\'][^"\']+["\'];',
    f'const PRONTOO_VERSION_FALLBACK = "{VERSION}";',
    app,
    count=1,
)
app = re.sub(
    r'const PRONTOO_PREVIOUS_VERSION = ["\'][^"\']+["\'];',
    f'const PRONTOO_PREVIOUS_VERSION = "{PREVIOUS_VERSION}";',
    app,
    count=1,
)
write("app/prontoo.php", app)

landing = read("br/index.php")
landing = re.sub(
    r'const BR_LANDING_VERSION_FALLBACK = ["\'][^"\']+["\'];',
    f'const BR_LANDING_VERSION_FALLBACK = "{VERSION}";',
    landing,
    count=1,
)
write("br/index.php", landing)

now = datetime.now(timezone.utc)
now_iso = now.isoformat()
now_unix = int(now.timestamp())
version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": now_unix,
    "generated_at": now_iso,
    "updated_at": now_iso,
    "build": BUILD,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "functional_equivalence_policy": "page-load-source-of-truth-internal-requests-excluded",
    "notes": "Telemetria padronizada por carregamento de página HTML, com marcos explícitos no primeiro e último pontos úteis do front controller.",
    "previous_version": PREVIOUS_VERSION,
    "documentation_changes": True,
    "deployment_sync_id": SYNC_ID,
    "deployment_sync_requested_at": now_iso,
    "rewrite_scope": "page_load_telemetry_navigation_classification_explicit_boundaries_and_historical_schema_reset",
    "release_date": "2026-08-06",
})
persistent = dict(version.get("persistent_storage_policy") or {})
persistent["telemetry"] = "ssd/telemetry/page-loads.jsonl"
persistent["legacy_telemetry"] = "ssd/telemetry/telemetria.json"
version["persistent_storage_policy"] = persistent
version_path.write_text(
    json.dumps(version, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "generated_at": now_iso,
    "updated_at": now_iso,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "previous_version": PREVIOUS_VERSION,
    "deployment_sync_id": SYNC_ID,
    "telemetry_policy": "one_completed_html_document_navigation_equals_one_page_load_event",
    "telemetry_timing_policy": "first_front_controller_executable_line_to_last_useful_render_line",
})
architecture_path.write_text(
    json.dumps(architecture, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

changelog_path = ROOT / "CHANGELOG.md"
changelog = changelog_path.read_text(encoding="utf-8")
entry = '''## 1.8.6.2 — Carregamento de página como fonte de verdade

- contabiliza somente navegações de documento HTML concluídas;
- exclui `fetch`, XHR, JSON, rotas internas, prefetch, downloads e redirecionamentos;
- marca o início antes do primeiro `require` dos front controllers;
- fecha a duração explicitamente após a última etapa útil de renderização;
- mantém shutdown apenas como fallback identificado;
- inicia o schema `prontoo.telemetria.pagina.v2` em `ssd/telemetry/page-loads.jsonl`;
- preserva o arquivo histórico de requisições sem misturá-lo aos novos indicadores;
- não altera banco de dados, schema, rotas funcionais, layout ou assets.

'''
heading = "# Histórico de versões\n\n"
if "## 1.8.6.2 —" not in changelog:
    if not changelog.startswith(heading):
        raise RuntimeError("Cabeçalho do CHANGELOG não reconhecido")
    changelog = heading + entry + changelog[len(heading):]
changelog_path.write_text(changelog, encoding="utf-8")

for transient in (SCRIPT,):
    if transient.exists():
        transient.unlink()

manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "generated_at": now_iso,
    "generated_at_unix": now_unix,
    "updated_at": now_iso,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "previous_version": PREVIOUS_VERSION,
    "deployment_sync_id": SYNC_ID,
    "functional_equivalence_policy": "page-load-source-of-truth-internal-requests-excluded",
    "notes": "Telemetria de página v2 com navegação HTML como unidade única e duração delimitada no front controller.",
})
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=ROOT).split(b"\0")
candidates = {raw.decode("utf-8") for raw in tracked if raw}
candidates.discard("app/update.manifest.json")
candidates.discard(".agent-page-load-telemetry.py")
candidates.discard(".github/workflows/agent-page-load-telemetry.yml")
candidates.update({
    "tools/page-load-telemetry-contract-check",
    "docs/adr/0007-page-load-telemetry-source-of-truth.md",
    "docs/operations/page-load-telemetry.md",
})
files: dict[str, str] = {}
total = 0
for relative in sorted(candidates):
    absolute = ROOT / relative
    if not absolute.is_file():
        continue
    data = absolute.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["files"] = files
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

print(json.dumps({
    "ok": True,
    "version": VERSION,
    "schema": "prontoo.telemetria.pagina.v2",
    "storage": "ssd/telemetry/page-loads.jsonl",
    "files": len(files),
}, ensure_ascii=False))
