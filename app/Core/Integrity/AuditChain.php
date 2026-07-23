<?php
declare(strict_types=1);
namespace Prontoo\Core\Integrity;
final class AuditChain
{
    private const CHAIN_NAME = "global";
    private const META_KEY = "audit_chain_head:global";
    private const POLICY_VERSION = "audit-chain-v3-linked";
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }
    public static function build(array $row, string $secret): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::build
         * Responsabilidade: Implementa a responsabilidade “build” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `audit`, `closure@app/Domain/Audit/AuditActivity.php:2161`.
         * Dependências chamadas: `function_exists`, `json_encode`, `hash_hmac`.
         * Estado externo lido: `$_SERVER`, `$_SESSION`.
         * Efeitos colaterais: lê ou altera a sessão; consome dados da requisição HTTP; produz conteúdo de saída.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $previous = self::currentHeadForUpdate();
        $proof = self::buildFromPrevious(
            $row,
            $secret,
            $previous,
            self::runtimeProofContext(),
        );
        self::advanceHead((string) $proof["chain_hash"]);
        return $proof;
    }
    public static function buildFromPrevious(
        array $row,
        string $secret,
        string $previous,
        array $proofContext = [],
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::buildFromPrevious
         * Responsabilidade: Constrói deterministicamente um elo da cadeia a partir do hash anterior, permitindo validação por propriedades sem acesso ao banco.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.AuditChain::build` e certificação matemática do CI.
         * Dependências chamadas: `audit_integrity_base`, `json_encode`, `hash_hmac`, `self::canonicalize`.
         * Efeitos colaterais: nenhum; função matemática pura.
         * Cuidado 1: A ordem e o conteúdo do envelope fazem parte do contrato criptográfico e não podem mudar sem nova versão de política.
         */
        $previous = trim($previous);
        if ($previous !== "" && preg_match('/^[a-f0-9]{64}$/', $previous) !== 1) {
            throw new \InvalidArgumentException("Hash anterior de auditoria inválido.");
        }
        $base = function_exists("audit_integrity_base")
            ? \audit_integrity_base($row)
            : json_encode($row, JSON_UNESCAPED_UNICODE);
        $integrity = hash_hmac("sha256", (string) $base, $secret);
        $proofContext["policy"] = self::POLICY_VERSION;
        $proofJson = json_encode(
            self::canonicalize($proofContext),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if ($proofJson === false) {
            $proofJson = "{}";
        }
        $proofHash = hash_hmac("sha256", $proofJson, $secret);
        $chain = hash_hmac(
            "sha256",
            $previous . "|" . $integrity . "|" . $proofHash,
            $secret,
        );
        return [
            "previous_hash" => $previous,
            "integrity_hash" => $integrity,
            "proof_hash" => $proofHash,
            "chain_hash" => $chain,
            "proof_json" => $proofJson,
            "policy_version" => self::POLICY_VERSION,
        ];
    }
    public static function verifyRow(array $row, string $secret): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::verifyRow
         * Responsabilidade: Implementa a responsabilidade “verify row” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `verify_audit_row`.
         * Dependências chamadas: `trim`, `function_exists`, `json_encode`, `json_decode`, `is_array`, `hash_equals`, `hash_hmac`.
         * Efeitos colaterais: produz conteúdo de saída.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $integrity = strtolower(trim((string) ($row["integrity_hash"] ?? "")));
        $previous = strtolower(trim((string) ($row["previous_hash"] ?? "")));
        $proof = strtolower(trim((string) ($row["proof_hash"] ?? "")));
        $chain = strtolower(trim((string) ($row["chain_hash"] ?? "")));
        $proofJson = (string) ($row["proof_json"] ?? "");
        $policy = trim((string) ($row["policy_version"] ?? ""));
        foreach ([$integrity, $proof, $chain] as $requiredHash) {
            if (preg_match('/^[a-f0-9]{64}$/', $requiredHash) !== 1) {
                return false;
            }
        }
        if (
            $previous !== "" &&
            preg_match('/^[a-f0-9]{64}$/', $previous) !== 1
        ) {
            return false;
        }
        $base = function_exists("audit_integrity_base")
            ? \audit_integrity_base($row)
            : json_encode($row, JSON_UNESCAPED_UNICODE);
        if (
            !hash_equals(
                $integrity,
                hash_hmac("sha256", (string) $base, $secret),
            )
        ) {
            return false;
        }
        if (
            $proofJson === "" ||
            !hash_equals(
                $proof,
                hash_hmac("sha256", $proofJson, $secret),
            )
        ) {
            return false;
        }
        $proofContext = json_decode($proofJson, true);
        if (
            !is_array($proofContext) ||
            $policy === "" ||
            !hash_equals(
                $policy,
                (string) ($proofContext["policy"] ?? ""),
            )
        ) {
            return false;
        }
        return hash_equals(
            $chain,
            hash_hmac(
                "sha256",
                $previous . "|" . $integrity . "|" . $proof,
                $secret,
            ),
        );
    }
    public static function verifySequence(array $rows, string $secret): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::verifySequence
         * Responsabilidade: Prova integridade individual, ordem estrita e continuidade dos elos v3 de uma sequência de auditoria.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `verify_audit_rows` e certificação matemática do CI.
         * Dependências chamadas: `usort`, `self::verifyRow`, `hash_equals`.
         * Efeitos colaterais: nenhum; validação determinística.
         * Cuidado 1: Subconjuntos podem começar no meio da cadeia; a continuidade passa a ser obrigatória a partir do segundo registro disponível.
         */
        usort(
            $rows,
            static /* Guia de manutenção: Ordena registros de auditoria pelo identificador; transformação local sem efeitos externos. */ fn(array $left, array $right): int =>
                ((int) ($left["id"] ?? 0)) <=> ((int) ($right["id"] ?? 0)),
        );
        $previousRow = null;
        $previousId = 0;
        foreach ($rows as $row) {
            $id = (int) ($row["id"] ?? 0);
            if ($id <= $previousId || !self::verifyRow($row, $secret)) {
                return false;
            }
            $policy = (string) ($row["policy_version"] ?? "");
            if (
                $policy === self::POLICY_VERSION &&
                is_array($previousRow) &&
                !hash_equals(
                    (string) ($previousRow["chain_hash"] ?? ""),
                    (string) ($row["previous_hash"] ?? ""),
                )
            ) {
                return false;
            }
            $previousRow = $row;
            $previousId = $id;
        }
        return true;
    }
    public static function storedHeadMatchesLatest(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::storedHeadMatchesLatest
         * Responsabilidade: Detecta truncamento ou inserção fora do protocolo comparando a âncora transacional com o último elo persistido.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `verify_audit_rows`.
         * Dependências chamadas: `one`, `hash_equals`.
         * Efeitos colaterais: consulta dados persistidos.
         * Cuidado 1: Esta verificação é somente leitura e nunca deve inicializar ou reparar silenciosamente a cabeça.
         */
        $head = \one(
            "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
            [self::META_KEY],
        );
        $latest = \one(
            "SELECT chain_hash,policy_version FROM pi_audit ORDER BY id DESC LIMIT 1",
        );
        $stored = trim((string) ($head["meta_value"] ?? ""));
        $actual = trim((string) ($latest["chain_hash"] ?? ""));
        if ($stored === "" && $actual === "") {
            return true;
        }
        if (
            $stored === "" &&
            $actual !== "" &&
            (string) ($latest["policy_version"] ?? "") !== self::POLICY_VERSION
        ) {
            return true;
        }
        return $stored !== "" && $actual !== "" && hash_equals($stored, $actual);
    }
    private static function runtimeProofContext(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::runtimeProofContext
         * Responsabilidade: Captura o contexto mínimo e não sensível que vincula a prova à requisição autora.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.AuditChain::build`.
         * Dependências chamadas: `route`.
         * Estado externo lido: `$_SERVER`, `$_SESSION`.
         * Efeitos colaterais: somente leitura do contexto da requisição.
         * Cuidado 1: Não inclua segredos ou dados pessoais no envelope.
         */
        return [
            "route" => function_exists("route") ? \route() : "",
            "method" => $_SERVER["REQUEST_METHOD"] ?? "",
            "scope" => $_SESSION["scope"] ?? "",
            "clinic_id" => $_SESSION["clinic_id"] ?? null,
            "role" => $_SESSION["role_code"] ?? "",
        ];
    }
    private static function canonicalize(mixed $value): mixed
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::canonicalize
         * Responsabilidade: Ordena recursivamente mapas antes da serialização criptográfica.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.AuditChain::buildFromPrevious`.
         * Dependências chamadas: `array_is_list`, `ksort`, `self::canonicalize`.
         * Efeitos colaterais: nenhum; transformação pura.
         * Cuidado 1: Listas preservam ordem; apenas mapas associativos são ordenados.
         */
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }
        return $value;
    }
    private static function ensureHead(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::ensureHead
         * Responsabilidade: Implementa a responsabilidade “ensure head” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        \q(
            "INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES (?,NULL,NOW()) ON DUPLICATE KEY UPDATE meta_key=VALUES(meta_key)",
            [self::META_KEY],
        );
        $head = \one(
            "SELECT meta_value FROM pi_meta WHERE meta_key=? FOR UPDATE",
            [self::META_KEY],
        );
        if (trim((string) ($head["meta_value"] ?? "")) !== "") {
            return;
        }
        $latest = \one(
            "SELECT chain_hash FROM pi_audit WHERE chain_hash IS NOT NULL AND chain_hash<>'' ORDER BY id DESC LIMIT 1",
        );
        $legacyHead = trim((string) ($latest["chain_hash"] ?? ""));
        \q(
            "UPDATE pi_meta SET meta_value=?,updated_at=NOW() WHERE meta_key=?",
            [$legacyHead !== "" ? $legacyHead : null, self::META_KEY],
        );
    }
    private static function currentHeadForUpdate(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::currentHeadForUpdate
         * Responsabilidade: Implementa a responsabilidade “current head for update” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        self::ensureHead();
        $row = \one(
            "SELECT meta_value FROM pi_meta WHERE meta_key=? FOR UPDATE",
            [self::META_KEY],
        );
        return trim((string) ($row["meta_value"] ?? ""));
    }
    private static function advanceHead(string $hash): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.AuditChain::advanceHead
         * Responsabilidade: Implementa a responsabilidade “advance head” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/AuditChain.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new \InvalidArgumentException("Novo hash de auditoria inválido.");
        }
        $statement = \q(
            "UPDATE pi_meta SET meta_value=?,updated_at=NOW() WHERE meta_key=?",
            [$hash, self::META_KEY],
        );
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException(
                "A cabeça da cadeia de auditoria não pôde ser avançada.",
            );
        }
    }
}
