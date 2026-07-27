from __future__ import annotations
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import time

VERSION = "1.7.27.7"
PREVIOUS = "1.7.27.6"
now = datetime.now(timezone.utc).replace(microsecond=0)
stamp = now.isoformat().replace("+00:00", "Z")
stamp_compact = now.strftime("%Y%m%dT%H%M%SZ")


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    Path(path).write_text(content, encoding="utf-8")


def replace_once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1 trecho, encontrado {count}")
    return source.replace(old, new, 1)


def sub_once(source: str, pattern: str, replacement: str, label: str) -> str:
    result, count = re.subn(pattern, replacement, source, count=1, flags=re.S)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1 trecho, encontrado {count}")
    return result


def mutate_section(
    source: str,
    start_marker: str,
    end_marker: str,
    transform,
    label: str,
) -> str:
    start = source.find(start_marker)
    if start < 0:
        raise RuntimeError(f"{label}: início não encontrado")
    end = source.find(end_marker, start + len(start_marker))
    if end < 0:
        raise RuntimeError(f"{label}: fim não encontrado")
    section = source[start:end]
    changed = transform(section)
    if changed == section:
        raise RuntimeError(f"{label}: seção não alterada")
    return source[:start] + changed + source[end:]


# 1) Primitiva transversal de submissão única.
path = "app/Support/SecurityPrivacy.php"
source = read(path)
anchor = "function privacy_sanitize_text(string $text, int $limit = 900): string\n"
if source.count(anchor) != 1:
    raise RuntimeError("Âncora de SecurityPrivacy não encontrada")
block = r'''function security_submission_token_issue(
    string $scope,
    int $entityId = 0,
): string {
    /*
     * GUIA DE MANUTENÇÃO — security_submission_token_issue
     * Responsabilidade: Emite prova assinada e vinculada à sessão para que uma mutação de formulário seja aceita uma única vez.
     * Local arquitetural: app/Support/SecurityPrivacy.php (serviços transversais de suporte).
     * Chamadores detectados: `page_leads`.
     * Dependências chamadas: `preg_replace`, `trim`, `is_string`, `strlen`, `random_bytes`, `bin2hex`, `time`, `base64_encode`, `strtr`, `rtrim`, `hash_hmac`.
     * Estado externo lido e alterado: `$_SESSION`.
     * Efeitos colaterais: mantém segredo efêmero na sessão ativa.
     * Cuidado 1: O token não substitui CSRF; ele complementa a proteção impedindo reenvio da mesma intenção de gravação.
     */
    $scope = preg_replace("/[^a-z0-9_.:-]+/i", "", trim($scope)) ?: "mutation";
    $secret = $_SESSION["prontoo_submission_token_secret"] ?? "";
    if (!is_string($secret) || strlen($secret) < 64) {
        $secret = bin2hex(random_bytes(32));
        $_SESSION["prontoo_submission_token_secret"] = $secret;
    }
    $payload = implode("|", [
        $scope,
        (string) max(0, $entityId),
        (string) time(),
        bin2hex(random_bytes(16)),
    ]);
    $encoded = rtrim(strtr(base64_encode($payload), "+/", "-_"), "=");
    return $encoded . "." . hash_hmac("sha256", $encoded, $secret);
}

function security_submission_token_consume(
    string $scope,
    string $token,
    int $entityId = 0,
    int $ttlSeconds = 7200,
): bool {
    /*
     * GUIA DE MANUTENÇÃO — security_submission_token_consume
     * Responsabilidade: Valida e consome uma prova de submissão, rejeitando repetição, adulteração, troca de escopo ou expiração.
     * Local arquitetural: app/Support/SecurityPrivacy.php (serviços transversais de suporte).
     * Chamadores detectados: `page_leads` e regressão de CI.
     * Dependências chamadas: `preg_replace`, `trim`, `explode`, `array_pad`, `is_string`, `strlen`, `hash_hmac`, `hash_equals`, `strtr`, `str_repeat`, `base64_decode`, `count`, `ctype_digit`, `time`, `max`, `min`, `is_array`, `hash`, `asort`, `array_slice`.
     * Estado externo lido e alterado: `$_SESSION`.
     * Efeitos colaterais: registra o hash do token consumido durante a janela de validade.
     * Cuidado 1: Consuma o token imediatamente antes da primeira mutação persistente da ação protegida.
     */
    $scope = preg_replace("/[^a-z0-9_.:-]+/i", "", trim($scope)) ?: "mutation";
    $token = trim($token);
    [$encoded, $signature] = array_pad(explode(".", $token, 2), 2, "");
    $secret = $_SESSION["prontoo_submission_token_secret"] ?? "";
    if (
        $encoded === "" ||
        strlen($signature) !== 64 ||
        !is_string($secret) ||
        strlen($secret) < 64
    ) {
        return false;
    }
    $expected = hash_hmac("sha256", $encoded, $secret);
    if (!hash_equals($expected, $signature)) {
        return false;
    }
    $base64 = strtr($encoded, "-_", "+/");
    $remainder = strlen($base64) % 4;
    if ($remainder !== 0) {
        $base64 .= str_repeat("=", 4 - $remainder);
    }
    $payload = base64_decode($base64, true);
    if (!is_string($payload)) {
        return false;
    }
    $parts = explode("|", $payload, 4);
    if (
        count($parts) !== 4 ||
        $parts[0] !== $scope ||
        !ctype_digit($parts[1]) ||
        (int) $parts[1] !== max(0, $entityId) ||
        !ctype_digit($parts[2])
    ) {
        return false;
    }
