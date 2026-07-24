from __future__ import annotations

from pathlib import Path
import datetime
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.24.4"
PREVIOUS = "1.7.24.3"
BUILD = "1.7.24.4-logout-cascade"
PACKAGE = "logout_generation_cascade"
NOTES = (
    "Implementa logout em cascata por geração canônica do usuário: sessões "
    "paralelas falham no próximo uso, caches de contexto antigos ficam "
    "inalcançáveis pela chave geracional e o logout deixa de limpar categorias "
    "operacionais ou atualizar dispositivos legados de forma síncrona."
)


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, text: str) -> None:
    (ROOT / path).write_text(text, encoding="utf-8")


def regex_once(path: str, pattern: str, replacement: str, flags: int = 0) -> None:
    source = read(path)
    updated, count = re.subn(pattern, replacement, source, count=1, flags=flags)
    if count != 1:
        raise RuntimeError(f"{path}: correspondência única ausente para {pattern}")
    write(path, updated)


def replace_once(path: str, old: str, new: str) -> None:
    source = read(path)
    count = source.count(old)
    if count != 1:
        raise RuntimeError(
            f"{path}: esperado um trecho e encontrados {count}: {old[:100]!r}"
        )
    write(path, source.replace(old, new, 1))


def update_release() -> None:
    now = datetime.datetime.now(datetime.timezone.utc).replace(microsecond=0)
    now_iso = now.isoformat().replace("+00:00", "Z")
    now_unix = int(now.timestamp())
    sync_id = "hostoo-logout-cascade-" + now.strftime("%Y%m%dT%H%M%SZ")

    changelog = read("ChangeLog.txt")
    entry = """Prontoo 1.7.24.4 — logout em cascata por geração canônica

- Torna a geração de autenticação do usuário a raiz única da revogação: ao ser rotacionada, todas as sessões paralelas falham fechadas na próxima requisição.
- Vincula a chave do cache de contexto à geração da sessão, tornando contextos anteriores automaticamente inalcançáveis sem varredura ou exclusão síncrona de arquivos.
- Retira do logout a limpeza de 18 categorias de cache, a atualização em massa de dispositivos legados, a materialização completa de contexto e a descarga do Guardião.
- Mantém CSRF, auditoria, destruição da sessão local, MFA, timeout de 60 minutos e revogação global do usuário.
- Acrescenta prova regressiva permanente; banco e schema permanecem inalterados.

"""
    write("ChangeLog.txt", entry + changelog)

    version_path = ROOT / "version.json"
    version = json.loads(version_path.read_text(encoding="utf-8"))
    version.update(
        {
            "version": VERSION,
            "release": VERSION,
            "generated_at_unix": now_unix,
            "generated_at": now_iso,
            "updated_at": now_iso,
            "build": BUILD,
            "package_type": PACKAGE,
            "database_changes": False,
            "schema_changes": False,
            "logic_changes": True,
            "visual_changes": False,
            "notes": NOTES,
            "previous_version": PREVIOUS,
            "documentation_changes": True,
            "deployment_sync_id": sync_id,
            "deployment_sync_requested_at": now_iso,
            "authentication_policy": "password_plus_mfa_idle_3600_seconds_per_user_generation_cascade_lazy_revocation",
        }
    )
    version_path.write_text(
        json.dumps(version, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )

    architecture_path = ROOT / "app/architecture.manifest.json"
    architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
    architecture.update(
        {
            "version": VERSION,
            "database_changes": False,
            "schema_changes": False,
            "visual_changes": False,
            "logic_changes": True,
            "authentication_policy": "password_plus_mfa_idle_3600_seconds_per_user_generation_cascade_lazy_revocation",
            "json_cache_policy": "generation_keyed_lazy_cascade_without_logout_directory_sweep",
            "logout_cascade_policy": "rotate_one_user_generation_then_reject_sessions_contexts_and_legacy_credentials_on_next_use",
        }
    )
    architecture_path.write_text(
        json.dumps(architecture, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    regex_once(
        "app/prontoo.php",
        r'const PRONTOO_VERSION_FALLBACK = "1\.7\.24\.3";',
        'const PRONTOO_VERSION_FALLBACK = "1.7.24.4";',
    )
    regex_once(
        "app/prontoo.php",
        r'const PRONTOO_PREVIOUS_VERSION = "1\.7\.24\.2";',
        'const PRONTOO_PREVIOUS_VERSION = "1.7.24.3";',
    )
    regex_once(
        "br/index.php",
        r'const BR_LANDING_VERSION_FALLBACK = "1\.7\.24\.3";',
        'const BR_LANDING_VERSION_FALLBACK = "1.7.24.4";',
    )

    manifest_path = ROOT / "app/update.manifest.json"
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    manifest.update(
        {
            "version": VERSION,
            "release": VERSION,
            "build": BUILD,
            "package_type": PACKAGE,
            "generated_at": now_iso,
            "database_changes": False,
            "schema_changes": False,
            "logic_changes": True,
            "visual_changes": False,
            "documentation_changes": True,
            "previous_version": PREVIOUS,
            "updated_at": now_iso,
            "notes": NOTES,
            "deployment_sync_id": sync_id,
            "deployment_sync_requested_at": now_iso,
            "authentication_policy": "password_plus_mfa_idle_3600_seconds_per_user_generation_cascade_lazy_revocation",
        }
    )
    refreshed: dict[str, str] = {}
    total = 0
    for relative in manifest.get("files", {}):
        path = ROOT / relative
        if not path.is_file():
            raise RuntimeError(f"manifest: arquivo ausente {relative}")
        data = path.read_bytes()
        refreshed[relative] = hashlib.sha256(data).hexdigest()
        total += len(data)
    manifest["files"] = refreshed
    manifest["file_count"] = len(refreshed)
    manifest["total_uncompressed_bytes"] = total
    manifest_path.write_text(
        json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8"
    )


def main() -> None:
    current = json.loads(read("version.json")).get("version")
    if current == VERSION:
        print("logout cascade already applied")
        return
    if current != PREVIOUS:
        raise RuntimeError(f"baseline inesperada: {current}")

    regex_once(
        "app/Support/SecurityAccess.php",
        r'/\* Guia de manutenção: Revoga todas as sessões do usuário pela rotação criptográfica da geração\. \*/\nfunction user_auth_generation_rotate\(int \$uid\): string\n\{.*?\n\}\nfunction session_harden_after_login',
        '''/* Guia de manutenção: Rotaciona a raiz canônica da cascata; artefatos derivados falham no próximo uso sem limpeza física síncrona. */
function user_auth_generation_rotate(int $uid): string
{
    if ($uid <= 0) {
        throw new RuntimeException("Usuário inválido para revogação de sessão.");
    }
    $generation = bin2hex(random_bytes(24));
    meta_set(user_auth_generation_key($uid), $generation);
    return $generation;
}
function session_harden_after_login''',
        re.S,
    )

    regex_once(
        "app/Support/SecurityAccess.php",
        r'audit\("sessao_obsoleta_encerrada", "seguranca", \$uid, \[\s*"audit_body" =>\s*"Sessão encerrada porque a geração de autenticação foi renovada pela normalização pré-login do index\.",\s*\]\);',
        '''audit("sessao_obsoleta_encerrada", "seguranca", $uid, [
            "_skip_runtime_context" => 1,
            "_skip_context_enrichment" => 1,
            "clinic_id" => (int) ($_SESSION["clinic_id"] ?? 0) ?: null,
            "role_code" => (string) ($_SESSION["role_code"] ?? ""),
            "audit_body" =>
                "Sessão encerrada no primeiro uso após divergência da geração canônica de autenticação do usuário.",
        ]);''',
        re.S,
    )

    replace_once(
        "app/Support/ServerJsonCache.php",
        '''    $route = strtolower(trim($route));
    $act = strtolower(trim($act));

    // Tudo que representa estado operacional permanece live após qualquer gravação.''',
        '''    $route = strtolower(trim($route));
    $act = strtolower(trim($act));

    // Logout invalida somente a geração canônica. Os artefatos derivados
    // tornam-se inalcançáveis ou falham fechados na próxima tentativa de uso.
    if ($route === "logout") {
        return [];
    }

    // Tudo que representa estado operacional permanece live após qualquer gravação.''',
    )
    replace_once(
        "app/Support/ServerJsonCache.php",
        '''        "role" => $role,
        "version" => defined("PRONTOO_VERSION") ? PRONTOO_VERSION : "",''',
        '''        "role" => $role,
        "user_auth_generation" =>
            (string) ($_SESSION["user_auth_generation"] ?? ""),
        "version" => defined("PRONTOO_VERSION") ? PRONTOO_VERSION : "",''',
    )

    replace_once(
        "app/Runtime/Runner.php",
        '    if (in_array($route, ["login", "login_autotest", "mfa"], true)) {',
        '    if (in_array($route, ["login", "login_autotest", "mfa", "logout"], true)) {',
    )
    replace_once(
        "app/Runtime/Runner.php",
        '''        $cNow = $publicHome ? [] : ctx();
        enforce_read_only($cNow, $r);
        enforce_action_integrity($cNow, $r);''',
        '''        $cNow = $publicHome || $r === "logout" ? [] : ctx();
        enforce_read_only($cNow, $r);
        if ($r !== "logout") {
            enforce_action_integrity($cNow, $r);
        }''',
    )
    replace_once(
        "app/Runtime/Runner.php",
        '''        prontoo_load_route_modules($r);
        prontoo_flush_integrity_before_render();''',
        '''        prontoo_load_route_modules($r);
        if ($r !== "logout") {
            prontoo_flush_integrity_before_render();
        }''',
    )
    replace_once(
        "app/Support/ModuleLoader.php",
        "    return ['login', 'login_autotest', 'mfa', 'mobile_web_access', 'signup'];",
        "    return ['login', 'login_autotest', 'mfa', 'mobile_web_access', 'signup', 'logout'];",
    )

    replace_once(
        "app/Domain/Audit/AuditActivity.php",
        '''            $c = ctx();
            $uid = (int) ($c["user"]["id"] ?? ($_SESSION["uid"] ?? 0)) ?: null;
            $cid = $context["clinic_id"] ?? ($c["clinic_id"] ?? null);
            unset($context["clinic_id"]);''',
        '''            $skipRuntimeContext = !empty($context["_skip_runtime_context"]);
            $skipContextEnrichment = !empty(
                $context["_skip_context_enrichment"]
            );
            unset(
                $context["_skip_runtime_context"],
                $context["_skip_context_enrichment"],
            );
            $c = $skipRuntimeContext ? [] : ctx();
            $uid = (int) ($c["user"]["id"] ?? ($_SESSION["uid"] ?? 0)) ?: null;
            $cid = $context["clinic_id"] ?? ($c["clinic_id"] ?? null);
            unset($context["clinic_id"]);''',
    )
    replace_once(
        "app/Domain/Audit/AuditActivity.php",
        '''            $context = audit_enrich_context(
                $event,
                $entity,
                $entityId,
                $context,
                $cid ? (int) $cid : null,
            );''',
        '''            if (!$skipContextEnrichment) {
                $context = audit_enrich_context(
                    $event,
                    $entity,
                    $entityId,
                    $context,
                    $cid ? (int) $cid : null,
                );
            }''',
    )

    regex_once(
        "app/Auth/AuthOnboarding.php",
        r'function page_logout\(\): void\n\{.*?\n\}\nfunction page_profile\(\): void',
        '''function page_logout(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_logout
     * Responsabilidade: Invalida a raiz canônica da autenticação e encerra a sessão local; os demais artefatos derivados falham fechados no próximo uso.
     * Local arquitetural: app/Auth/AuthOnboarding.php (autenticação, sessão e entrada de usuários).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `redirect`, `user_auth_generation_rotate`, `audit`, `secure_session_destroy`, `header`, `href`.
     * Estado externo lido: `$_SERVER`, `$_SESSION`.
     * Efeitos colaterais: lê ou altera a sessão; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 2: A geração do usuário é a raiz da cascata; não reintroduza limpeza física ampla de cache ou varredura de dispositivos no caminho crítico.
     */
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
        redirect("login");
    }
    $uid = (int) ($_SESSION["uid"] ?? 0);
    $clinicId = (int) ($_SESSION["clinic_id"] ?? 0);
    $roleCode = (string) ($_SESSION["role_code"] ?? "");
    try {
        if ($uid > 0) {
            user_auth_generation_rotate($uid);
        }
        audit("saida_realizada", "seguranca", $uid ?: null, [
            "_skip_runtime_context" => 1,
            "_skip_context_enrichment" => 1,
            "clinic_id" => $clinicId > 0 ? $clinicId : null,
            "role_code" => $roleCode,
            "audit_body" =>
                "Logout concluído pela rotação da geração canônica; sessões, contextos e credenciais derivadas serão recusados na próxima tentativa de uso.",
        ]);
    } catch (Throwable $e) {
        error_log("[Prontoo logout cascade] " . $e->getMessage());
    } finally {
        secure_session_destroy();
    }
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Location: " . href("login"));
    exit();
}
function page_profile(): void''',
        re.S,
    )

    architecture_check = read("tools/architecture-check.php")
    anchor = '''$result = [
    'ok' =>
        !empty($architecture['ok']) &&
        !empty($selfTest['ok']) &&
        !empty($dashboardIconCascade['ok']),
    'architecture' => $architecture,
    'self_test' => $selfTest,
    'dashboard_icon_cascade' => $dashboardIconCascade,
];'''
    contract = '''$logoutCascadeSources = [
    'auth' => (string) file_get_contents($root . '/app/Support/SecurityAccess.php'),
    'cache' => (string) file_get_contents($root . '/app/Support/ServerJsonCache.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
    'loader' => (string) file_get_contents($root . '/app/Support/ModuleLoader.php'),
    'audit' => (string) file_get_contents($root . '/app/Domain/Audit/AuditActivity.php'),
    'logout' => (string) file_get_contents($root . '/app/Auth/AuthOnboarding.php'),
];
$logoutCascadeFailures = [];
foreach ([
    'auth' => [
        'meta_set(user_auth_generation_key($uid), $generation);',
        'hash_equals($userCurrent, $userSession)',
    ],
    'cache' => [
        'if ($route === "logout") {',
        '"user_auth_generation" =>',
        '$_SESSION["user_auth_generation"]',
    ],
    'runner' => [
        '$publicHome || $r === "logout" ? [] : ctx()',
        'if ($r !== "logout") {',
    ],
    'loader' => ["'signup', 'logout'"],
    'audit' => [
        '$skipRuntimeContext = !empty($context["_skip_runtime_context"])',
        '$c = $skipRuntimeContext ? [] : ctx();',
    ],
    'logout' => [
        'user_auth_generation_rotate($uid);',
        '"_skip_runtime_context" => 1',
        'secure_session_destroy();',
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($logoutCascadeSources[$sourceKey], $requiredToken)) {
            $logoutCascadeFailures[] = $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'auth' => ['server_json_cache_clear_categories(["context", "meta"])'],
    'logout' => ['security_retire_persistent_devices_for_user($uid);'],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($logoutCascadeSources[$sourceKey], $forbiddenToken)) {
            $logoutCascadeFailures[] = $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$logoutCascade = [
    'ok' => $logoutCascadeFailures === [],
    'failed' => $logoutCascadeFailures,
];
$result = [
    'ok' =>
        !empty($architecture['ok']) &&
        !empty($selfTest['ok']) &&
        !empty($dashboardIconCascade['ok']) &&
        !empty($logoutCascade['ok']),
    'architecture' => $architecture,
    'self_test' => $selfTest,
    'dashboard_icon_cascade' => $dashboardIconCascade,
    'logout_cascade' => $logoutCascade,
];'''
    if architecture_check.count(anchor) != 1:
        raise RuntimeError("tools/architecture-check.php: âncora inválida")
    write("tools/architecture-check.php", architecture_check.replace(anchor, contract, 1))

    update_release()
    print("logout cascade applied")


if __name__ == "__main__":
    main()
