<?php
declare(strict_types=1);
namespace Prontoo\Core\Database;
use Prontoo\Core\Readonly\ReadonlyPolicy;
use Prontoo\Core\Support\Check;
use Prontoo\Core\Tenant\TenantRegistry;
final class SqlScopeGuard
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
    }
    public static function guard(string $sql, array $params = []): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::guard
         * Responsabilidade: Avalia ou impõe a regra “guard”, falhando de forma controlada quando a pré-condição não é satisfeita.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `sql_write_scope_guard`.
         * Dependências chamadas: `max`, `self::globalOrSystemContext`, `TenantRegistry::sessionClinicId`, `Check::writeOperation`, `Check::normalizedSql`, `function_exists`, `self::clinicReadOnly`, `ReadonlyPolicy::sqlAllowed`, `array_keys`, `TenantRegistry::scopedTables`, `Check::tableHit`, `self::recordViolation` e mais 1.
         * Classes ou serviços instanciados: `.ProntooHttpError`.
         * Estado externo lido: `$GLOBALS`, `$_POST`.
         * Efeitos colaterais: consome dados da requisição HTTP; pode interromper o fluxo por exceção.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_DISABLED"])) {
            return;
        }
        if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"])) {
            return;
        }
        $expectedClinicId = max(
            0,
            (int) ($GLOBALS["PRONTOO_SCOPE_GUARD_EXPECTED_CLINIC_ID"] ?? 0),
        );
        if ($expectedClinicId <= 0 && self::globalOrSystemContext()) {
            return;
        }
        $clinicId = $expectedClinicId > 0
            ? $expectedClinicId
            : TenantRegistry::sessionClinicId();
        if ($clinicId <= 0) {
            return;
        }
        $operation = Check::writeOperation($sql);
        if ($operation === null) {
            return;
        }
        $normalized = Check::normalizedSql($sql);
        $route = function_exists("route") ? \route() : "login";
        $action = (string) ($_POST["act"] ?? "");
        $readOnlyBypass = !empty($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"]);
        if (
            !$readOnlyBypass &&
            self::clinicReadOnly($clinicId) &&
            !ReadonlyPolicy::sqlAllowed($sql, $route, $action, true)
        ) {
            foreach (array_keys(TenantRegistry::scopedTables()) as $table) {
                if (Check::tableHit($normalized, $table)) {
                    self::recordViolation(
                        "write_in_read_only",
                        $sql,
                        "Assinatura vencida: escrita bloqueada em " .
                            $table .
                            ".",
                    );
                    throw new \ProntooHttpError(
                        403,
                        "Assinatura pendente: regularize antes de alterar dados.",
                    );
                }
            }
        }
        foreach (TenantRegistry::scopedTables() as $table => $scopeColumn) {
            if (!Check::tableHit($normalized, $table)) {
                continue;
            }
            self::assertScopedWrite(
                $operation,
                $sql,
                $params,
                $scopeColumn,
                $clinicId,
            );
            return;
        }
    }
    private static function assertScopedWrite(
        string $operation,
        string $rawSql,
        array $params,
        string $scopeColumn,
        int $clinicId,
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::assertScopedWrite
         * Responsabilidade: Implementa a responsabilidade “assert scoped write” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`.
         * Dependências chamadas: `strtolower`, `self::topLevelKeywordPosition`, `self::recordViolation`, `strlen`, `substr`, `preg_match`, `preg_quote`, `self::updateChangesScope`, `self::whereClinicProof`, `self::parseInsert`, `self::insertColumnListContains`, `self::insertClinicProof`.
         * Classes ou serviços instanciados: `.ProntooHttpError`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $scopeColumn = strtolower($scopeColumn);
        if ($operation === "UPDATE" || $operation === "DELETE") {
            $wherePosition = self::topLevelKeywordPosition($rawSql, "where");
            if ($wherePosition === null) {
                self::recordViolation(
                    "write_without_where",
                    $rawSql,
                    "Operação de escrita em tabela operacional sem WHERE.",
                );
                throw new \ProntooHttpError(
                    500,
                    "Proteção de isolamento: operação bloqueada por não limitar o registro do consultório.",
                );
            }
            $whereStart = $wherePosition + strlen("where");
            $where = substr($rawSql, $whereStart);
            if (
                !preg_match(
                    "/\b" . preg_quote($scopeColumn, "/") . "\b/",
                    $where,
                )
            ) {
                self::recordViolation(
                    "write_without_clinic_where",
                    $rawSql,
                    "UPDATE/DELETE em tabela operacional sem clinic_id no WHERE.",
                );
                throw new \ProntooHttpError(
                    500,
                    "Proteção de isolamento: operação bloqueada por não filtrar o consultório ativo.",
                );
            }
            if (
                $operation === "UPDATE" &&
                self::updateChangesScope(
                    $rawSql,
                    $scopeColumn,
                    $wherePosition,
                )
            ) {
                self::recordViolation(
                    "write_changes_clinic_scope",
                    $rawSql,
                    "UPDATE tentou alterar a coluna clinic_id de um registro operacional.",
                );
                throw new \ProntooHttpError(
                    500,
                    "Proteção de isolamento: a transferência de registros entre consultórios foi bloqueada.",
                );
            }
            $proof = self::whereClinicProof(
                $rawSql,
                $whereStart,
                $scopeColumn,
                $clinicId,
                $params,
            );
            if ($proof !== "active") {
                $key = $proof === "mismatch"
                    ? "write_mismatched_clinic_where"
                    : "write_unproved_clinic_where";
                self::recordViolation(
                    $key,
                    $rawSql,
                    $proof === "mismatch"
                        ? "UPDATE/DELETE apontou clinic_id diferente do consultório ativo."
                        : "UPDATE/DELETE sem prova lógica de que todos os ramos do WHERE permanecem no consultório ativo.",
                );
                throw new \ProntooHttpError(
                    500,
                    "Proteção de isolamento: operação bloqueada por escopo não demonstrável.",
                );
            }
            return;
        }
        $insert = self::parseInsert($rawSql, $scopeColumn);
        if ($insert === null) {
            $columnPresent = self::insertColumnListContains(
                $rawSql,
                $scopeColumn,
            );
            self::recordViolation(
                $columnPresent
                    ? "insert_unproved_clinic_value"
                    : "insert_without_clinic_column",
                $rawSql,
                $columnPresent
                    ? "INSERT/REPLACE sem lista VALUES demonstrável para validar o clinic_id."
                    : "INSERT/REPLACE em tabela operacional sem coluna clinic_id na lista inicial.",
            );
            throw new \ProntooHttpError(
                500,
                $columnPresent
                    ? "Proteção de isolamento: operação bloqueada por escopo não demonstrável."
                    : "Proteção de isolamento: operação bloqueada por não gravar o consultório ativo.",
            );
        }
        if (empty($insert["column_present"])) {
            self::recordViolation(
                "insert_without_clinic_column",
                $rawSql,
                "INSERT/REPLACE em tabela operacional sem coluna clinic_id na lista inicial.",
            );
            throw new \ProntooHttpError(
                500,
                "Proteção de isolamento: operação bloqueada por não gravar o consultório ativo.",
            );
        }
        $proof = self::insertClinicProof(
            $rawSql,
            $clinicId,
            $params,
            $insert,
        );
        if ($proof !== "active") {
            $key = $proof === "mismatch"
                ? "insert_mismatched_clinic_value"
                : "insert_unproved_clinic_value";
            self::recordViolation(
                $key,
                $rawSql,
                $proof === "mismatch"
                    ? "INSERT/REPLACE informou clinic_id diferente do consultório ativo."
                    : "INSERT/REPLACE sem prova de que todas as linhas gravam o consultório ativo.",
            );
            throw new \ProntooHttpError(
                500,
                "Proteção de isolamento: operação bloqueada por escopo não demonstrável.",
            );
        }
    }
    private static function whereClinicProof(
        string $sql,
        int $whereStart,
        string $scopeColumn,
        int $clinicId,
        array $params,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::whereClinicProof
         * Responsabilidade: Implementa a responsabilidade “where clinic proof” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::assertScopedWrite`, `Core.Database.SqlScopeGuard::logicSelfTest`, `closure@app/Core/Database/SqlScopeGuard.php:773`.
         * Dependências chamadas: `self::booleanScopeProof`, `substr`, `array_values`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        return self::booleanScopeProof(
            $sql,
            substr($sql, $whereStart),
            $whereStart,
            $scopeColumn,
            $clinicId,
            array_values($params),
        );
    }
    private static function booleanScopeProof(
        string $sql,
        string $expression,
        int $baseOffset,
        string $scopeColumn,
        int $clinicId,
        array $params,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::booleanScopeProof
         * Responsabilidade: Implementa a responsabilidade “boolean scope proof” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::whereClinicProof`.
         * Dependências chamadas: `self::trimExpression`, `self::outerParenthesesWrap`, `substr`, `self::splitBooleanTopLevel`, `count`, `self::booleanScopeProof`, `array_filter`, `self::atomicScopeProof`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        [$expression, $baseOffset] = self::trimExpression(
            $expression,
            $baseOffset,
        );
        while (self::outerParenthesesWrap($expression)) {
            $expression = substr($expression, 1, -1);
            $baseOffset++;
            [$expression, $baseOffset] = self::trimExpression(
                $expression,
                $baseOffset,
            );
        }
        $orParts = self::splitBooleanTopLevel($expression, "or");
        if (count($orParts) > 1) {
            $statuses = [];
            foreach ($orParts as [$part, $offset]) {
                $statuses[] = self::booleanScopeProof(
                    $sql,
                    $part,
                    $baseOffset + $offset,
                    $scopeColumn,
                    $clinicId,
                    $params,
                );
            }
            if (count(array_filter($statuses, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de núcleo de invariantes e decisões canônicas. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn($v) => $v === "active")) === count($statuses)) {
                return "active";
            }
            if (count(array_filter($statuses, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de núcleo de invariantes e decisões canônicas. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn($v) => $v === "mismatch")) === count($statuses)) {
                return "mismatch";
            }
            return "unproved";
        }
        $andParts = self::splitBooleanTopLevel($expression, "and");
        if (count($andParts) > 1) {
            $hasMismatch = false;
            foreach ($andParts as [$part, $offset]) {
                $status = self::booleanScopeProof(
                    $sql,
                    $part,
                    $baseOffset + $offset,
                    $scopeColumn,
                    $clinicId,
                    $params,
                );
                if ($status === "active") {
                    return "active";
                }
                $hasMismatch = $hasMismatch || $status === "mismatch";
            }
            return $hasMismatch ? "mismatch" : "unproved";
        }
        return self::atomicScopeProof(
            $sql,
            $expression,
            $baseOffset,
            $scopeColumn,
            $clinicId,
            $params,
        );
    }
    private static function atomicScopeProof(
        string $sql,
        string $expression,
        int $baseOffset,
        string $scopeColumn,
        int $clinicId,
        array $params,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::atomicScopeProof
         * Responsabilidade: Implementa a responsabilidade “atomic scope proof” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::booleanScopeProof`.
         * Dependências chamadas: `preg_quote`, `preg_match_all`, `self::scopeValueProof`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $column = preg_quote($scopeColumn, "/");
        $columnExpr = "(?:`?[a-z0-9_]+`?\\s*\\.\\s*)?`?" . $column . "`?";
        $patterns = [
            "/" . $columnExpr . "\\s*=\\s*(\\?|[-+]?\\d+\\b)/i",
            "/(\\?|[-+]?\\d+)\\s*=\\s*" . $columnExpr . "/i",
        ];
        $mismatch = false;
        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $expression, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches as $match) {
                $token = (string) ($match[1][0] ?? "");
                $tokenOffset = $baseOffset + (int) ($match[1][1] ?? 0);
                $status = self::scopeValueProof(
                    $sql,
                    $token,
                    $tokenOffset,
                    $clinicId,
                    $params,
                );
                if ($status === "active") {
                    return "active";
                }
                $mismatch = $mismatch || $status === "mismatch";
            }
        }
        return $mismatch ? "mismatch" : "unproved";
    }
    private static function scopeValueProof(
        string $sql,
        string $token,
        int $tokenOffset,
        int $clinicId,
        array $params,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::scopeValueProof
         * Responsabilidade: Implementa a responsabilidade “scope value proof” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::atomicScopeProof`, `Core.Database.SqlScopeGuard::insertClinicProof`.
         * Dependências chamadas: `self::placeholderIndexBefore`, `array_key_exists`, `preg_match`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if ($token === "?") {
            $index = self::placeholderIndexBefore($sql, $tokenOffset);
            if (!array_key_exists($index, $params)) {
                return "unproved";
            }
            return (int) $params[$index] === $clinicId
                ? "active"
                : "mismatch";
        }
        if (!preg_match('/^[-+]?\d+$/', $token)) {
            return "unproved";
        }
        return (int) $token === $clinicId ? "active" : "mismatch";
    }
    private static function parseInsert(
        string $sql,
        string $scopeColumn,
    ): ?array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::parseInsert
         * Responsabilidade: Implementa a responsabilidade “parse insert” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::assertScopedWrite`, `Core.Database.SqlScopeGuard::logicSelfTest`.
         * Dependências chamadas: `preg_match`, `self::splitSqlList`, `strtolower`, `trim`, `str_replace`, `strlen`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if (
            !preg_match(
                "/^\s*(?:insert|replace)\s+(?:(?:low_priority|delayed|high_priority|ignore)\s+)*into\s+(?:`?[a-z0-9_]+`?\s*\.\s*)?`?[a-z0-9_]+`?\s*\(([^)]*)\)\s*values\b/is",
                $sql,
                $m,
                PREG_OFFSET_CAPTURE,
            )
        ) {
            return null;
        }
        $columns = self::splitSqlList((string) $m[1][0]);
        $clinicIndex = null;
        foreach ($columns as $i => $column) {
            $column = strtolower(trim(str_replace("`", "", $column)));
            if ($column === strtolower($scopeColumn)) {
                $clinicIndex = $i;
                break;
            }
        }
        return [
            "column_present" => $clinicIndex !== null,
            "clinic_index" => $clinicIndex,
            "values_start" => (int) $m[0][1] + strlen((string) $m[0][0]),
            "scope_column" => strtolower($scopeColumn),
        ];
    }
    private static function insertColumnListContains(
        string $sql,
        string $scopeColumn,
    ): bool {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::insertColumnListContains
         * Responsabilidade: Implementa a responsabilidade “insert column list contains” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::assertScopedWrite`, `Core.Database.SqlScopeGuard::logicSelfTest`.
         * Dependências chamadas: `preg_match`, `self::splitSqlList`, `strtolower`, `trim`, `str_replace`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if (
            !preg_match(
                "/^\s*(?:insert|replace)\s+(?:(?:low_priority|delayed|high_priority|ignore)\s+)*into\s+(?:`?[a-z0-9_]+`?\s*\.\s*)?`?[a-z0-9_]+`?\s*\(([^)]*)\)/is",
                $sql,
                $m,
            )
        ) {
            return false;
        }
        foreach (self::splitSqlList((string) $m[1]) as $column) {
            $column = strtolower(trim(str_replace("`", "", $column)));
            if ($column === strtolower($scopeColumn)) {
                return true;
            }
        }
        return false;
    }
    private static function insertClinicProof(
        string $sql,
        int $clinicId,
        array $params,
        array $insert,
    ): string {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::insertClinicProof
         * Responsabilidade: Implementa a responsabilidade “insert clinic proof” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::assertScopedWrite`, `Core.Database.SqlScopeGuard::logicSelfTest`.
         * Dependências chamadas: `is_int`, `self::insertValueGroups`, `array_values`, `self::splitSqlListWithOffsets`, `array_key_exists`, `trim`, `strlen`, `ltrim`, `self::scopeValueProof`, `substr`, `preg_match`, `self::splitSqlList` e mais 3.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $clinicIndex = $insert["clinic_index"] ?? null;
        if (!is_int($clinicIndex)) {
            return "unproved";
        }
        [$groups, $tailStart] = self::insertValueGroups(
            $sql,
            (int) ($insert["values_start"] ?? 0),
        );
        if (!$groups) {
            return "unproved";
        }
        $ordered = array_values($params);
        foreach ($groups as [$group, $groupOffset]) {
            $values = self::splitSqlListWithOffsets($group);
            if (!array_key_exists($clinicIndex, $values)) {
                return "unproved";
            }
            [$value, $valueOffset] = $values[$clinicIndex];
            $value = trim($value);
            $leading = strlen($value) - strlen(ltrim($value));
            $status = self::scopeValueProof(
                $sql,
                $value,
                $groupOffset + $valueOffset + $leading,
                $clinicId,
                $ordered,
            );
            if ($status !== "active") {
                return $status;
            }
        }
        $tail = substr($sql, $tailStart);
        if (
            preg_match('/\bon\s+duplicate\s+key\s+update\b(.*)$/is', $tail, $m)
        ) {
            foreach (self::splitSqlList((string) ($m[1] ?? "")) as $assignment) {
                if (
                    !preg_match(
                        "/^\s*(?:`?[a-z0-9_]+`?\s*\.\s*)?`?" .
                            preg_quote((string) $insert["scope_column"], "/") .
                            "`?\s*=\s*(.*?)\s*$/is",
                        $assignment,
                        $scopeAssignment,
                    )
                ) {
                    continue;
                }
                $right = strtolower(
                    preg_replace('/\s+/', '', (string) ($scopeAssignment[1] ?? "")) ?? "",
                );
                $column = preg_quote((string) $insert["scope_column"], "/");
                if (!preg_match('/^values\(`?' . $column . '`?\)$/i', $right)) {
                    return "unproved";
                }
            }
        }
        return "active";
    }
    private static function splitSqlList(string $list): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::splitSqlList
         * Responsabilidade: Implementa a responsabilidade “split sql list” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::parseInsert`, `Core.Database.SqlScopeGuard::insertColumnListContains`, `Core.Database.SqlScopeGuard::insertClinicProof`, `Core.Database.SqlScopeGuard::updateChangesScope`.
         * Dependências chamadas: `strlen`, `max`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $items = [];
        $current = "";
        $depth = 0;
        $quote = null;
        $len = strlen($list);
        for ($i = 0; $i < $len; $i++) {
            $ch = $list[$i];
            if ($quote !== null) {
                $current .= $ch;
                if ($ch === $quote && ($i === 0 || $list[$i - 1] !== "\\")) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $current .= $ch;
                continue;
            }
            if ($ch === "(") {
                $depth++;
                $current .= $ch;
                continue;
            }
            if ($ch === ")") {
                $depth = max(0, $depth - 1);
                $current .= $ch;
                continue;
            }
            if ($ch === "," && $depth === 0) {
                $items[] = trim($current);
                $current = "";
                continue;
            }
            $current .= $ch;
        }
        if (trim($current) !== "") {
            $items[] = trim($current);
        }
        return $items;
    }
    private static function splitSqlListWithOffsets(string $list): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::splitSqlListWithOffsets
         * Responsabilidade: Implementa a responsabilidade “split sql list with offsets” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::insertClinicProof`.
         * Dependências chamadas: `strlen`, `max`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $items = [];
        $current = "";
        $start = 0;
        $depth = 0;
        $quote = null;
        $len = strlen($list);
        for ($i = 0; $i < $len; $i++) {
            $ch = $list[$i];
            if ($quote !== null) {
                $current .= $ch;
                if ($ch === $quote && ($i === 0 || $list[$i - 1] !== "\\")) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                $current .= $ch;
                continue;
            }
            if ($ch === "(") {
                $depth++;
            } elseif ($ch === ")") {
                $depth = max(0, $depth - 1);
            } elseif ($ch === "," && $depth === 0) {
                $items[] = [$current, $start];
                $current = "";
                $start = $i + 1;
                continue;
            }
            $current .= $ch;
        }
        if (trim($current) !== "" || $items) {
            $items[] = [$current, $start];
        }
        return $items;
    }
    private static function insertValueGroups(string $sql, int $offset): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::insertValueGroups
         * Responsabilidade: Implementa a responsabilidade “insert value groups” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::insertClinicProof`.
         * Dependências chamadas: `strlen`, `max`, `preg_match`, `self::matchingParenthesis`, `substr`, `ctype_space`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $groups = [];
        $length = strlen($sql);
        $cursor = max(0, $offset);
        while ($cursor < $length) {
            while ($cursor < $length && preg_match('/[\s,]/', $sql[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $length || $sql[$cursor] !== "(") {
                break;
            }
            $end = self::matchingParenthesis($sql, $cursor);
            if ($end === null) {
                return [[], $cursor];
            }
            $groups[] = [substr($sql, $cursor + 1, $end - $cursor - 1), $cursor + 1];
            $cursor = $end + 1;
            $lookahead = $cursor;
            while ($lookahead < $length && ctype_space($sql[$lookahead])) {
                $lookahead++;
            }
            if ($lookahead >= $length || $sql[$lookahead] !== ",") {
                $cursor = $lookahead;
                break;
            }
            $afterComma = $lookahead + 1;
            while ($afterComma < $length && ctype_space($sql[$afterComma])) {
                $afterComma++;
            }
            if ($afterComma >= $length || $sql[$afterComma] !== "(") {
                $cursor = $lookahead;
                break;
            }
            $cursor = $afterComma;
        }
        return [$groups, $cursor];
    }
    private static function matchingParenthesis(string $sql, int $start): ?int
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::matchingParenthesis
         * Responsabilidade: Implementa a responsabilidade “matching parenthesis” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::insertValueGroups`, `Core.Database.SqlScopeGuard::outerParenthesesWrap`.
         * Dependências chamadas: `strlen`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        for ($i = $start; $i < $length; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                if ($ch === $quote && ($i === 0 || $sql[$i - 1] !== "\\")) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "(") {
                $depth++;
            } elseif ($ch === ")") {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return null;
    }
    private static function topLevelKeywordPosition(
        string $sql,
        string $keyword,
    ): ?int {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::topLevelKeywordPosition
         * Responsabilidade: Implementa a responsabilidade “top level keyword position” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::assertScopedWrite`, `Core.Database.SqlScopeGuard::updateChangesScope`, `Core.Database.SqlScopeGuard::logicSelfTest`, `closure@app/Core/Database/SqlScopeGuard.php:773`.
         * Dependências chamadas: `strlen`, `max`, `strncasecmp`, `substr`, `preg_match`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        $wordLength = strlen($keyword);
        for ($i = 0; $i <= $length - $wordLength; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                if ($ch === $quote && ($i === 0 || $sql[$i - 1] !== "\\")) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "(") {
                $depth++;
                continue;
            }
            if ($ch === ")") {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth !== 0 || strncasecmp(substr($sql, $i, $wordLength), $keyword, $wordLength) !== 0) {
                continue;
            }
            $before = $i > 0 ? $sql[$i - 1] : " ";
            $after = $i + $wordLength < $length ? $sql[$i + $wordLength] : " ";
            if (!preg_match('/[a-z0-9_]/i', $before) && !preg_match('/[a-z0-9_]/i', $after)) {
                return $i;
            }
        }
        return null;
    }
    private static function updateChangesScope(
        string $sql,
        string $scopeColumn,
        int $wherePosition,
    ): bool {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::updateChangesScope
         * Responsabilidade: Implementa a responsabilidade “update changes scope” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::assertScopedWrite`, `Core.Database.SqlScopeGuard::logicSelfTest`.
         * Dependências chamadas: `self::topLevelKeywordPosition`, `substr`, `strlen`, `self::splitSqlList`, `preg_match`, `preg_quote`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $setPosition = self::topLevelKeywordPosition($sql, "set");
        if ($setPosition === null || $setPosition >= $wherePosition) {
            return true;
        }
        $set = substr(
            $sql,
            $setPosition + strlen("set"),
            $wherePosition - ($setPosition + strlen("set")),
        );
        foreach (self::splitSqlList($set) as $assignment) {
            if (
                preg_match(
                    "/^\s*(?:`?[a-z0-9_]+`?\s*\.\s*)?`?" .
                        preg_quote($scopeColumn, "/") .
                        "`?\s*=/i",
                    $assignment,
                )
            ) {
                return true;
            }
        }
        return false;
    }
    private static function trimExpression(string $expression, int $offset): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::trimExpression
         * Responsabilidade: Implementa a responsabilidade “trim expression” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::booleanScopeProof`.
         * Dependências chamadas: `strlen`, `ltrim`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $leading = strlen($expression) - strlen(ltrim($expression));
        return [trim($expression), $offset + $leading];
    }
    private static function outerParenthesesWrap(string $expression): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::outerParenthesesWrap
         * Responsabilidade: Implementa a responsabilidade “outer parentheses wrap” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::booleanScopeProof`.
         * Dependências chamadas: `trim`, `strlen`, `self::matchingParenthesis`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $expression = trim($expression);
        if (strlen($expression) < 2 || $expression[0] !== "(") {
            return false;
        }
        return self::matchingParenthesis($expression, 0) === strlen($expression) - 1;
    }
    private static function splitBooleanTopLevel(
        string $expression,
        string $operator,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::splitBooleanTopLevel
         * Responsabilidade: Implementa a responsabilidade “split boolean top level” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::booleanScopeProof`.
         * Dependências chamadas: `strlen`, `max`, `strncasecmp`, `substr`, `preg_match`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($expression);
        $wordLength = strlen($operator);
        for ($i = 0; $i <= $length - $wordLength; $i++) {
            $ch = $expression[$i];
            if ($quote !== null) {
                if ($ch === $quote && ($i === 0 || $expression[$i - 1] !== "\\")) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "(") {
                $depth++;
                continue;
            }
            if ($ch === ")") {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($depth !== 0 || strncasecmp(substr($expression, $i, $wordLength), $operator, $wordLength) !== 0) {
                continue;
            }
            $before = $i > 0 ? $expression[$i - 1] : " ";
            $after = $i + $wordLength < $length ? $expression[$i + $wordLength] : " ";
            if (preg_match('/[a-z0-9_]/i', $before) || preg_match('/[a-z0-9_]/i', $after)) {
                continue;
            }
            $parts[] = [substr($expression, $start, $i - $start), $start];
            $i += $wordLength - 1;
            $start = $i + 1;
        }
        if ($parts) {
            $parts[] = [substr($expression, $start), $start];
            return $parts;
        }
        return [[$expression, 0]];
    }
    private static function placeholderIndexBefore(string $sql, int $offset): int
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::placeholderIndexBefore
         * Responsabilidade: Implementa a responsabilidade “placeholder index before” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::scopeValueProof`.
         * Dependências chamadas: `min`, `strlen`, `max`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $count = 0;
        $quote = null;
        $limit = min(strlen($sql), max(0, $offset));
        for ($i = 0; $i < $limit; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                if ($ch === $quote && ($i === 0 || $sql[$i - 1] !== "\\")) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "?") {
                $count++;
            }
        }
        return $count;
    }
    public static function logicSelfTest(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::logicSelfTest
         * Responsabilidade: Executa verificações regressivas embutidas para confirmar que os contratos lógicos deste componente permanecem válidos.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `platform_backend_selftest`, `page_admin_security`.
         * Dependências chamadas: `self::topLevelKeywordPosition`, `self::whereClinicProof`, `self::updateChangesScope`, `self::parseInsert`, `is_array`, `self::insertClinicProof`, `self::insertColumnListContains`, `array_keys`, `array_filter`, `count`.
         * Efeitos colaterais: consulta dados persistidos; pode gravar ou remover dados.
         * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
         * Cuidado 2: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $clinicId = 17;
        $cases = [];
        $where = static function (string $sql, array $params) use ($clinicId): string {
            /*
             * GUIA DE MANUTENÇÃO — closure@app/Core/Database/SqlScopeGuard.php:773
             * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de núcleo de invariantes e decisões canônicas.
             * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
             * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
             * Dependências chamadas: `self::topLevelKeywordPosition`, `self::whereClinicProof`.
             * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
             * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
             */
            $position = self::topLevelKeywordPosition($sql, "where");
            return $position === null
                ? "unproved"
                : self::whereClinicProof(
                    $sql,
                    $position + 5,
                    "clinic_id",
                    $clinicId,
                    $params,
                );
        };
        $cases["conjunction_with_nested_or"] =
            $where(
                "UPDATE pi_tasks SET status=? WHERE clinic_id=? AND (assigned_to IS NULL OR assigned_to=?)",
                ["ok", 17, 4],
            ) === "active";
        $cases["unscoped_or_branch_is_denied"] =
            $where(
                "UPDATE pi_tasks SET status=? WHERE clinic_id=? OR id=?",
                ["ok", 17, 4],
            ) === "unproved";
        $cases["every_or_branch_is_scoped"] =
            $where(
                "DELETE FROM pi_tasks WHERE (clinic_id=? AND id=?) OR (clinic_id=? AND id=?)",
                [17, 1, 17, 2],
            ) === "active";
        $cases["set_scope_does_not_prove_where"] =
            $where(
                "UPDATE pi_tasks SET clinic_id=? WHERE id=? AND clinic_id=?",
                [17, 4, 99],
            ) === "mismatch";
        $scopeChangeSql =
            "UPDATE pi_tasks SET clinic_id=? WHERE id=? AND clinic_id=?";
        $scopeChangeWhere = self::topLevelKeywordPosition(
            $scopeChangeSql,
            "where",
        );
        $cases["scope_column_change_is_denied"] =
            $scopeChangeWhere !== null &&
            self::updateChangesScope(
                $scopeChangeSql,
                "clinic_id",
                $scopeChangeWhere,
            );
        $insertSql =
            "INSERT INTO pi_tasks (clinic_id,title) VALUES (?,?),(?,?)";
        $insert = self::parseInsert($insertSql, "clinic_id");
        $cases["every_insert_row_is_scoped"] =
            is_array($insert) &&
            self::insertClinicProof(
                $insertSql,
                $clinicId,
                [17, "a", 17, "b"],
                $insert,
            ) === "active";
        $cases["mismatched_insert_row_is_denied"] =
            is_array($insert) &&
            self::insertClinicProof(
                $insertSql,
                $clinicId,
                [17, "a", 99, "b"],
                $insert,
            ) === "mismatch";
        $insertIgnoreSql =
            "INSERT IGNORE INTO pi_tasks (clinic_id,title) VALUES (?,?)";
        $insertIgnore = self::parseInsert($insertIgnoreSql, "clinic_id");
        $cases["insert_ignore_is_scoped"] =
            is_array($insertIgnore) &&
            self::insertClinicProof(
                $insertIgnoreSql,
                $clinicId,
                [17, "a"],
                $insertIgnore,
            ) === "active";
        $cases["insert_select_is_unproved_not_missing"] =
            self::parseInsert(
                "INSERT INTO pi_tasks (clinic_id,title) SELECT id,name FROM pi_clinics",
                "clinic_id",
            ) === null &&
            self::insertColumnListContains(
                "INSERT INTO pi_tasks (clinic_id,title) SELECT id,name FROM pi_clinics",
                "clinic_id",
            );
        $failed = array_keys(array_filter($cases, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de núcleo de invariantes e decisões canônicas. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn($ok) => !$ok));
        return [
            "ok" => $failed === [],
            "passed" => count($cases) - count($failed),
            "total" => count($cases),
            "failed" => $failed,
        ];
    }
    private static function globalOrSystemContext(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::globalOrSystemContext
         * Responsabilidade: Implementa a responsabilidade “global or system context” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`.
         * Dependências chamadas: `session_status`, `function_exists`, `str_starts_with`, `self::sessionUserIsGlobalAdmin`.
         * Estado externo lido: `$GLOBALS`, `$_SESSION`.
         * Efeitos colaterais: lê ou altera a sessão.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if (!empty($GLOBALS["PRONTOO_SCOPE_GUARD_SYSTEM"])) {
            return true;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $scope = (string) ($_SESSION["scope"] ?? "");
        if ($scope === "global") {
            return true;
        }
        $route = function_exists("route") ? (string) \route() : "";
        if (
            str_starts_with($route, "admin_") &&
            self::sessionUserIsGlobalAdmin()
        ) {
            return true;
        }
        return false;
    }
    private static function sessionUserIsGlobalAdmin(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::sessionUserIsGlobalAdmin
         * Responsabilidade: Implementa a responsabilidade “session user is global admin” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::globalOrSystemContext`.
         * Dependências chamadas: `function_exists`, `error_log`, `->getMessage`.
         * Estado externo lido: `$_SESSION`.
         * Efeitos colaterais: lê ou altera a sessão; gera trilha de auditoria ou telemetria.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $uid = (int) ($_SESSION["uid"] ?? 0);
        if ($uid <= 0) {
            return false;
        }
        if (function_exists("user_is_global_admin")) {
            try {
                return (bool) \user_is_global_admin($uid);
            } catch (\Throwable $recoverableError) {
                error_log(
                    "[Prontoo recoverable " .
                        __FUNCTION__ .
                        "] " .
                        $recoverableError->getMessage(),
                );
            }
        }
        return false;
    }
    private static function clinicReadOnly(int $clinicId): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::clinicReadOnly
         * Responsabilidade: Implementa a responsabilidade “clinic read only” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`.
         * Dependências chamadas: `function_exists`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        return function_exists("clinic_read_only_db")
            ? \clinic_read_only_db($clinicId)
            : false;
    }
    private static function recordViolation(
        string $key,
        string $sql,
        string $detail,
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SqlScopeGuard::recordViolation
         * Responsabilidade: Implementa a responsabilidade “record violation” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SqlScopeGuard.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SqlScopeGuard::guard`, `Core.Database.SqlScopeGuard::assertScopedWrite`.
         * Dependências chamadas: `function_exists`, `error_log`, `Check::sqlFingerprint`, `Check::clampText`.
         * Efeitos colaterais: gera trilha de auditoria ou telemetria.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if (function_exists("record_scope_violation")) {
            \record_scope_violation($key, $sql, $detail);
            return;
        }
        error_log(
            "[Prontoo scope violation] " .
                $key .
                " | " .
                Check::sqlFingerprint($sql) .
                " | " .
                Check::clampText($detail, 500),
        );
    }
}
