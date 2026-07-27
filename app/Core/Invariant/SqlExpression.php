<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;

final class SqlExpression
{
    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function operation(string $sql): ?string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::operation
         * Responsabilidade: Implementa a responsabilidade “operation” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::guard`, `Core.Invariant.SqlExpression::assignments`.
         * Dependências chamadas: `preg_match`, `strtoupper`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (!preg_match('/^\s*(UPDATE|DELETE|INSERT|REPLACE)\b/i', $sql, $match)) {
            return null;
        }
        return strtoupper((string) $match[1]);
    }

    public static function targetTable(string $sql): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::targetTable
         * Responsabilidade: Implementa a responsabilidade “target table” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::guard`, `Core.Invariant.Mutation.MutationInvariant::logicSelfTest`.
         * Dependências chamadas: `preg_match`, `strtolower`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $patterns = [
            '/^\s*(?:INSERT|REPLACE)\s+(?:(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE)\s+)*INTO\s+(?:`?[a-z0-9_]+`?\s*\.\s*)?`?([a-z0-9_]+)`?/i',
            '/^\s*UPDATE\s+(?:(?:LOW_PRIORITY|IGNORE)\s+)*(?:`?[a-z0-9_]+`?\s*\.\s*)?`?([a-z0-9_]+)`?/i',
            '/^\s*DELETE\s+(?:(?:LOW_PRIORITY|QUICK|IGNORE)\s+)*FROM\s+(?:`?[a-z0-9_]+`?\s*\.\s*)?`?([a-z0-9_]+)`?/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql, $match)) {
                return strtolower((string) $match[1]);
            }
        }
        return "";
    }

    public static function topLevelKeywordPosition(string $sql, string $keyword, int $start = 0): ?int
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::topLevelKeywordPosition
         * Responsabilidade: Implementa a responsabilidade “top level keyword position” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::assignments`, `Core.Invariant.SqlExpression::whereExpression`.
         * Dependências chamadas: `self::topLevelPhrasePosition`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return self::topLevelPhrasePosition($sql, $keyword, $start);
    }

    public static function topLevelPhrasePosition(string $sql, string $phrase, int $start = 0): ?int
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::topLevelPhrasePosition
         * Responsabilidade: Implementa a responsabilidade “top level phrase position” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::topLevelKeywordPosition`, `Core.Invariant.SqlExpression::parseInsert`, `Core.Invariant.SqlExpression::assignments`.
         * Dependências chamadas: `strtolower`, `trim`, `preg_replace`, `strlen`, `max`, `substr`, `preg_match`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $phrase = strtolower(trim(preg_replace('/\s+/', ' ', $phrase) ?? $phrase));
        if ($phrase === "") {
            return null;
        }
        $length = strlen($sql);
        $phraseLength = strlen($phrase);
        $depth = 0;
        $quote = null;
        for ($i = max(0, $start); $i <= $length - $phraseLength; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    if ($quote === "'" && $i + 1 < $length && $sql[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    if ($i === 0 || $sql[$i - 1] !== "\\") {
                        $quote = null;
                    }
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
            if ($depth !== 0) {
                continue;
            }
            $candidate = strtolower(preg_replace('/\s+/', ' ', substr($sql, $i, $phraseLength)) ?? "");
            if ($candidate !== $phrase) {
                continue;
            }
            $before = $i > 0 ? $sql[$i - 1] : " ";
            $after = $i + $phraseLength < $length ? $sql[$i + $phraseLength] : " ";
            if (preg_match('/[a-z0-9_]/i', $before) || preg_match('/[a-z0-9_]/i', $after)) {
                continue;
            }
            return $i;
        }
        return null;
    }

    public static function splitTopLevelWithOffsets(string $expression, string $delimiter = ","): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::splitTopLevelWithOffsets
         * Responsabilidade: Implementa a responsabilidade “split top level with offsets” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::parseInsert`, `Core.Invariant.SqlExpression::valueTuples`, `Core.Invariant.SqlExpression::assignments`, `Core.Invariant.SqlExpression::whereEqualityValues`, `Core.Invariant.SqlExpression::atomicColumnConstraint`.
         * Dependências chamadas: `strlen`, `max`, `substr`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $parts = [];
        $start = 0;
        $length = strlen($expression);
        $depth = 0;
        $quote = null;
        for ($i = 0; $i < $length; $i++) {
            $ch = $expression[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    if ($quote === "'" && $i + 1 < $length && $expression[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    if ($i === 0 || $expression[$i - 1] !== "\\") {
                        $quote = null;
                    }
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
            if ($depth === 0 && $ch === $delimiter) {
                $parts[] = [substr($expression, $start, $i - $start), $start];
                $start = $i + 1;
            }
        }
        $parts[] = [substr($expression, $start), $start];
        return $parts;
    }

    public static function splitBooleanTopLevel(string $expression, string $operator): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::splitBooleanTopLevel
         * Responsabilidade: Implementa a responsabilidade “split boolean top level” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::columnValueConstraint`, `Core.Invariant.Tenant.ScopeProof::booleanStatus`.
         * Dependências chamadas: `strtolower`, `trim`, `strlen`, `max`, `strncasecmp`, `substr`, `preg_match`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $operator = strtolower(trim($operator));
        $parts = [];
        $start = 0;
        $length = strlen($expression);
        $wordLength = strlen($operator);
        $depth = 0;
        $quote = null;
        for ($i = 0; $i <= $length - $wordLength; $i++) {
            $ch = $expression[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    if ($quote === "'" && $i + 1 < $length && $expression[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    if ($i === 0 || $expression[$i - 1] !== "\\") {
                        $quote = null;
                    }
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
        if ($parts !== []) {
            $parts[] = [substr($expression, $start), $start];
            return $parts;
        }
        return [[$expression, 0]];
    }

    public static function trimExpression(string $expression, int $baseOffset): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::trimExpression
         * Responsabilidade: Implementa a responsabilidade “trim expression” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::columnValueConstraint`, `Core.Invariant.Tenant.ScopeProof::booleanStatus`.
         * Dependências chamadas: `strlen`, `ltrim`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $left = strlen($expression) - strlen(ltrim($expression));
        return [trim($expression), $baseOffset + $left];
    }

    public static function outerParenthesesWrap(string $expression): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::outerParenthesesWrap
         * Responsabilidade: Implementa a responsabilidade “outer parentheses wrap” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::columnValueConstraint`, `Core.Invariant.Tenant.ScopeProof::booleanStatus`.
         * Dependências chamadas: `trim`, `strlen`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $expression = trim($expression);
        $length = strlen($expression);
        if ($length < 2 || $expression[0] !== "(" || $expression[$length - 1] !== ")") {
            return false;
        }
        $depth = 0;
        $quote = null;
        for ($i = 0; $i < $length; $i++) {
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
            } elseif ($ch === ")") {
                $depth--;
                if ($depth === 0 && $i < $length - 1) {
                    return false;
                }
            }
        }
        return $depth === 0;
    }

    public static function placeholderIndexBefore(string $sql, int $offset): int
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::placeholderIndexBefore
         * Responsabilidade: Implementa a responsabilidade “placeholder index before” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::tokenValue`.
         * Dependências chamadas: `min`, `strlen`, `max`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $count = 0;
        $quote = null;
        $limit = min(strlen($sql), max(0, $offset));
        for ($i = 0; $i < $limit; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    if ($quote === "'" && $i + 1 < $limit && $sql[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    if ($i === 0 || $sql[$i - 1] !== "\\") {
                        $quote = null;
                    }
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

    public static function tokenValue(
        string $sql,
        string $token,
        int $absoluteOffset,
        array $params,
        ?bool &$known = null,
    ): mixed {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::tokenValue
         * Responsabilidade: Implementa a responsabilidade “token value” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.TaskContextInvariant::assertEnumColumn`, `Core.Invariant.Relation.ForeignKeyGraph::assertWrite`, `Core.Invariant.SqlExpression::whereEqualityValues`, `Core.Invariant.SqlExpression::atomicColumnConstraint`, `Core.Invariant.SqlExpression::insertColumnValues`, `Core.Invariant.Tenant.ScopeProof::atomicStatus`, `Core.Invariant.Workflow.AppointmentWorkflow::assertWrite`.
         * Dependências chamadas: `trim`, `self::placeholderIndexBefore`, `array_key_exists`, `array_values`, `preg_match`, `strcasecmp`, `str_replace`, `stripcslashes`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $token = trim($token);
        $known = true;
        if ($token === "?") {
            $index = self::placeholderIndexBefore($sql, $absoluteOffset);
            if (!array_key_exists($index, array_values($params))) {
                $known = false;
                return null;
            }
            return array_values($params)[$index];
        }
        if (preg_match('/^[-+]?\d+$/', $token)) {
            return (int) $token;
        }
        if (preg_match('/^[-+]?\d+\.\d+$/', $token)) {
            return (float) $token;
        }
        if (strcasecmp($token, "null") === 0) {
            return null;
        }
        if (preg_match('/^\'(.*)\'$/s', $token, $match)) {
            return str_replace("''", "'", (string) $match[1]);
        }
        if (preg_match('/^\"(.*)\"$/s', $token, $match)) {
            return stripcslashes((string) $match[1]);
        }
        $known = false;
        return null;
    }

    public static function parseInsert(string $sql): ?array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::parseInsert
         * Responsabilidade: Implementa a responsabilidade “parse insert” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::guard`, `Core.Invariant.Mutation.MutationInvariant::logicSelfTest`, `Core.Invariant.Tenant.ScopeProof::logicSelfTest`.
         * Dependências chamadas: `preg_match`, `self::splitTopLevelWithOffsets`, `self::identifier`, `strlen`, `self::topLevelPhrasePosition`, `self::valueTuples`, `count`, `strtolower`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (!preg_match(
            '/^\s*(?:insert|replace)\s+(?:(?:low_priority|delayed|high_priority|ignore)\s+)*into\s+(?:`?[a-z0-9_]+`?\s*\.\s*)?`?([a-z0-9_]+)`?\s*\(([^)]*)\)\s*values\b/is',
            $sql,
            $match,
            PREG_OFFSET_CAPTURE,
        )) {
            return null;
        }
        $columns = [];
        foreach (self::splitTopLevelWithOffsets((string) $match[2][0]) as [$raw]) {
            $column = self::identifier((string) $raw);
            if ($column === "") {
                return null;
            }
            $columns[] = $column;
        }
        $valuesStart = (int) $match[0][1] + strlen((string) $match[0][0]);
        $duplicatePosition = self::topLevelPhrasePosition($sql, "on duplicate key update", $valuesStart);
        $valuesEnd = $duplicatePosition ?? strlen($sql);
        $rows = self::valueTuples($sql, $valuesStart, $valuesEnd);
        if ($rows === []) {
            return null;
        }
        foreach ($rows as $row) {
            if (count($row) !== count($columns)) {
                return null;
            }
        }
        return [
            "table" => strtolower((string) $match[1][0]),
            "columns" => $columns,
            "rows" => $rows,
            "duplicate_position" => $duplicatePosition,
        ];
    }

    private static function valueTuples(string $sql, int $start, int $end): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::valueTuples
         * Responsabilidade: Implementa a responsabilidade “value tuples” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::parseInsert`.
         * Dependências chamadas: `substr`, `self::splitTopLevelWithOffsets`, `strlen`, `ltrim`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $rows = [];
        $depth = 0;
        $quote = null;
        $tupleStart = null;
        for ($i = $start; $i < $end; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    if ($quote === "'" && $i + 1 < $end && $sql[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    if ($i === 0 || $sql[$i - 1] !== "\\") {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === "`") {
                $quote = $ch;
                continue;
            }
            if ($ch === "(") {
                if ($depth === 0) {
                    $tupleStart = $i + 1;
                }
                $depth++;
                continue;
            }
            if ($ch === ")") {
                $depth--;
                if ($depth < 0) {
                    return [];
                }
                if ($depth === 0 && $tupleStart !== null) {
                    $inner = substr($sql, $tupleStart, $i - $tupleStart);
                    $tokens = [];
                    foreach (self::splitTopLevelWithOffsets($inner) as [$token, $offset]) {
                        $left = strlen($token) - strlen(ltrim($token));
                        $tokens[] = [
                            "token" => trim($token),
                            "offset" => $tupleStart + $offset + $left,
                        ];
                    }
                    $rows[] = $tokens;
                    $tupleStart = null;
                }
            }
        }
        return $depth === 0 ? $rows : [];
    }

    public static function assignments(string $sql): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::assignments
         * Responsabilidade: Implementa a responsabilidade “assignments” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.TaskContextInvariant::assertEnumColumn`, `Core.Invariant.Relation.ForeignKeyGraph::assertWrite`, `Core.Invariant.Tenant.ScopeProof::updateChangesScope`, `Core.Invariant.Tenant.ScopeProof::duplicatePreservesScope`, `Core.Invariant.Workflow.AppointmentWorkflow::assertWrite`.
         * Dependências chamadas: `self::operation`, `self::topLevelKeywordPosition`, `self::topLevelPhrasePosition`, `strlen`, `substr`, `self::splitTopLevelWithOffsets`, `preg_match`, `strtolower`, `trim`, `self::isDirectValueToken`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $operation = self::operation($sql);
        $setPosition = $operation === "UPDATE"
            ? self::topLevelKeywordPosition($sql, "set")
            : self::topLevelPhrasePosition($sql, "on duplicate key update");
        if ($setPosition === null) {
            return [];
        }
        $setLength = $operation === "UPDATE" ? 3 : strlen("on duplicate key update");
        $start = $setPosition + $setLength;
        $wherePosition = $operation === "UPDATE"
            ? self::topLevelKeywordPosition($sql, "where", $start)
            : null;
        $end = $wherePosition ?? strlen($sql);
        $segment = substr($sql, $start, $end - $start);
        $assignments = [];
        foreach (self::splitTopLevelWithOffsets($segment) as [$raw, $offset]) {
            if (!preg_match('/^\s*(?:`?[a-z0-9_]+`?\s*\.\s*)?`?([a-z0-9_]+)`?\s*=\s*(.+?)\s*$/is', $raw, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $column = strtolower((string) $match[1][0]);
            $expression = trim((string) $match[2][0]);
            $expressionOffset = $start + $offset + (int) $match[2][1];
            $assignments[$column] = [
                "token" => $expression,
                "offset" => $expressionOffset,
                "known_direct" => self::isDirectValueToken($expression),
            ];
        }
        return $assignments;
    }

    public static function whereExpression(string $sql): ?array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::whereExpression
         * Responsabilidade: Implementa a responsabilidade “where expression” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::guard`, `Core.Invariant.SqlExpression::whereEqualityValues`, `Core.Invariant.SqlExpression::whereAllowedValues`, `Core.Invariant.Tenant.ScopeProof::whereStatus`.
         * Dependências chamadas: `self::topLevelKeywordPosition`, `substr`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $where = self::topLevelKeywordPosition($sql, "where");
        if ($where === null) {
            return null;
        }
        $start = $where + 5;
        return [substr($sql, $start), $start];
    }

    public static function whereEqualityValues(
        string $sql,
        string $column,
        array $params,
        ?bool &$complete = null,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::whereEqualityValues
         * Responsabilidade: Implementa a responsabilidade “where equality values” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::logicSelfTest`, `closure@app/Core/Invariant/Mutation/MutationInvariant.php:225`, `Core.Invariant.Workflow.AppointmentWorkflow::assertWrite`.
         * Dependências chamadas: `self::whereExpression`, `preg_quote`, `strtolower`, `preg_match_all`, `self::tokenValue`, `self::splitTopLevelWithOffsets`, `strlen`, `ltrim`, `trim`, `array_values`, `array_unique`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $complete = true;
        $where = self::whereExpression($sql);
        if ($where === null) {
            $complete = false;
            return [];
        }
        [$expression, $baseOffset] = $where;
        $columnPattern = '(?<![a-z0-9_])(?:`?[a-z0-9_]+`?\s*\.\s*)?`?' . preg_quote(strtolower($column), '/') . '`?(?![a-z0-9_])';
        $values = [];
        if (preg_match_all('/' . $columnPattern . '\s*=\s*(\?|[-+]?\d+|\'[^\']*\'|"[^"]*")/i', $expression, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $token = (string) $match[1][0];
                $known = false;
                $value = self::tokenValue(
                    $sql,
                    $token,
                    $baseOffset + (int) $match[1][1],
                    $params,
                    $known,
                );
                if (!$known) {
                    $complete = false;
                    continue;
                }
                $values[] = $value;
            }
        }
        if (preg_match_all('/' . $columnPattern . '\s+in\s*\(([^)]*)\)/i', $expression, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $list = (string) $match[1][0];
                $listOffset = $baseOffset + (int) $match[1][1];
                foreach (self::splitTopLevelWithOffsets($list) as [$token, $offset]) {
                    $left = strlen($token) - strlen(ltrim($token));
                    $known = false;
                    $value = self::tokenValue(
                        $sql,
                        trim($token),
                        $listOffset + $offset + $left,
                        $params,
                        $known,
                    );
                    if (!$known) {
                        $complete = false;
                        continue;
                    }
                    $values[] = $value;
                }
            }
        }
        return array_values(array_unique($values, SORT_REGULAR));
    }

    public static function whereAllowedValues(
        string $sql,
        string $column,
        array $params,
        ?bool &$complete = null,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::whereAllowedValues
         * Responsabilidade: Implementa a responsabilidade “where allowed values” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Workflow.AppointmentWorkflow::assertWrite`, `Core.Invariant.Workflow.AppointmentWorkflow::logicSelfTest`.
         * Dependências chamadas: `self::whereExpression`, `self::columnValueConstraint`, `strtolower`, `array_values`, `array_unique`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $complete = true;
        $where = self::whereExpression($sql);
        if ($where === null) {
            $complete = false;
            return [];
        }
        [$expression, $baseOffset] = $where;
        $constraint = self::columnValueConstraint(
            $sql,
            $expression,
            $baseOffset,
            strtolower($column),
            array_values($params),
        );
        if ($constraint === null) {
            $complete = false;
            return [];
        }
        return array_values(array_unique($constraint, SORT_REGULAR));
    }

    private static function columnValueConstraint(
        string $sql,
        string $expression,
        int $baseOffset,
        string $column,
        array $params,
    ): ?array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::columnValueConstraint
         * Responsabilidade: Implementa a responsabilidade “column value constraint” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::whereAllowedValues`.
         * Dependências chamadas: `self::trimExpression`, `self::outerParenthesesWrap`, `substr`, `self::splitBooleanTopLevel`, `count`, `self::columnValueConstraint`, `array_merge`, `array_values`, `array_unique`, `array_shift`, `array_uintersect`, `strcmp` e mais 2.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        [$expression, $baseOffset] = self::trimExpression($expression, $baseOffset);
        while (self::outerParenthesesWrap($expression)) {
            $expression = substr($expression, 1, -1);
            $baseOffset++;
            [$expression, $baseOffset] = self::trimExpression($expression, $baseOffset);
        }

        $orParts = self::splitBooleanTopLevel($expression, "or");
        if (count($orParts) > 1) {
            $values = [];
            foreach ($orParts as [$part, $offset]) {
                $branch = self::columnValueConstraint(
                    $sql,
                    $part,
                    $baseOffset + $offset,
                    $column,
                    $params,
                );
                if ($branch === null) {
                    return null;
                }
                $values = array_merge($values, $branch);
            }
            return array_values(array_unique($values, SORT_REGULAR));
        }

        $andParts = self::splitBooleanTopLevel($expression, "and");
        if (count($andParts) > 1) {
            $constraints = [];
            foreach ($andParts as [$part, $offset]) {
                $branch = self::columnValueConstraint(
                    $sql,
                    $part,
                    $baseOffset + $offset,
                    $column,
                    $params,
                );
                if ($branch !== null) {
                    $constraints[] = $branch;
                }
            }
            if ($constraints === []) {
                return null;
            }
            $values = array_shift($constraints);
            foreach ($constraints as $constraint) {
                $values = array_values(array_uintersect(
                    $values,
                    $constraint,
                    static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de núcleo de invariantes e decisões canônicas. Dependências diretas: `strcmp`, `self::canonicalComparable`. Efeitos: transformação local sem efeito externo detectado. */ fn(mixed $a, mixed $b): int => strcmp(
                        self::canonicalComparable($a),
                        self::canonicalComparable($b),
                    ),
                ));
            }
            return $values;
        }

        return self::atomicColumnConstraint(
            $sql,
            $expression,
            $baseOffset,
            $column,
            $params,
        );
    }

    private static function atomicColumnConstraint(
        string $sql,
        string $expression,
        int $baseOffset,
        string $column,
        array $params,
    ): ?array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::atomicColumnConstraint
         * Responsabilidade: Implementa a responsabilidade “atomic column constraint” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::columnValueConstraint`.
         * Dependências chamadas: `preg_quote`, `preg_match`, `self::tokenValue`, `self::splitTopLevelWithOffsets`, `strlen`, `ltrim`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $columnPattern = '(?:`?[a-z0-9_]+`?\\s*\\.\\s*)?`?' . preg_quote($column, '/') . '`?';
        $valuePattern = '(\\?|[-+]?\\d+|\\\'[^\\\']*\\\'|"[^"]*")';
        $patterns = [
            '/^\\s*' . $columnPattern . '\\s*=\\s*' . $valuePattern . '\\s*$/i',
            '/^\\s*' . $valuePattern . '\\s*=\\s*' . $columnPattern . '\\s*$/i',
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $expression, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $token = (string) ($match[1][0] ?? "");
            $known = false;
            $value = self::tokenValue(
                $sql,
                $token,
                $baseOffset + (int) ($match[1][1] ?? 0),
                $params,
                $known,
            );
            return $known ? [$value] : null;
        }

        if (preg_match(
            '/^\\s*' . $columnPattern . '\\s+in\\s*\\((.*)\\)\\s*$/is',
            $expression,
            $match,
            PREG_OFFSET_CAPTURE,
        )) {
            $list = (string) ($match[1][0] ?? "");
            $listOffset = $baseOffset + (int) ($match[1][1] ?? 0);
            $values = [];
            foreach (self::splitTopLevelWithOffsets($list) as [$token, $offset]) {
                $left = strlen($token) - strlen(ltrim($token));
                $known = false;
                $value = self::tokenValue(
                    $sql,
                    trim($token),
                    $listOffset + $offset + $left,
                    $params,
                    $known,
                );
                if (!$known) {
                    return null;
                }
                $values[] = $value;
            }
            return $values;
        }

        return null;
    }

    private static function canonicalComparable(mixed $value): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::canonicalComparable
         * Responsabilidade: Implementa a responsabilidade “canonical comparable” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::columnValueConstraint`, `arrow@app/Core/Invariant/SqlExpression.php:567`.
         * Dependências chamadas: `is_bool`, `is_int`, `is_float`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if ($value === null) {
            return "null";
        }
        if (is_bool($value)) {
            return $value ? "bool:1" : "bool:0";
        }
        if (is_int($value) || is_float($value)) {
            return "number:" . (string) $value;
        }
        return "string:" . (string) $value;
    }

    public static function insertColumnValues(
        string $sql,
        array $params,
        array $parsed,
        string $column,
        ?bool &$complete = null,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::insertColumnValues
         * Responsabilidade: Implementa a responsabilidade “insert column values” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.TaskContextInvariant::assertEnumColumn`, `Core.Invariant.Relation.ForeignKeyGraph::assertWrite`, `Core.Invariant.Tenant.ScopeProof::insertStatus`, `Core.Invariant.Workflow.AppointmentWorkflow::assertWrite`.
         * Dependências chamadas: `array_search`, `strtolower`, `array_map`, `self::tokenValue`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $complete = true;
        $index = array_search(strtolower($column), array_map('strtolower', $parsed["columns"] ?? []), true);
        if ($index === false) {
            return [];
        }
        $values = [];
        foreach ($parsed["rows"] ?? [] as $row) {
            if (!isset($row[$index])) {
                $complete = false;
                continue;
            }
            $known = false;
            $values[] = self::tokenValue(
                $sql,
                (string) $row[$index]["token"],
                (int) $row[$index]["offset"],
                $params,
                $known,
            );
            if (!$known) {
                $complete = false;
            }
        }
        return $values;
    }

    public static function identifier(string $raw): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::identifier
         * Responsabilidade: Implementa a responsabilidade “identifier” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::parseInsert`, `Core.Invariant.Tenant.ScopeProof::insertColumnPresent`, `arrow@app/Core/Invariant/Tenant/ScopeProof.php:78`.
         * Dependências chamadas: `strtolower`, `trim`, `preg_match`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $raw = strtolower(trim($raw));
        $raw = trim($raw, "` \t\n\r\0\x0B");
        return preg_match('/^[a-z0-9_]+$/', $raw) ? $raw : "";
    }

    public static function isDirectValueToken(string $token): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.SqlExpression::isDirectValueToken
         * Responsabilidade: Implementa a responsabilidade “is direct value token” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/SqlExpression.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.SqlExpression::assignments`.
         * Dependências chamadas: `trim`, `preg_match`, `strcasecmp`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $token = trim($token);
        return $token === "?" ||
            preg_match('/^[-+]?\d+(?:\.\d+)?$/', $token) === 1 ||
            strcasecmp($token, "null") === 0 ||
            preg_match('/^\'(?:[^\']|\'\')*\'$/s', $token) === 1 ||
            preg_match('/^"(?:[^"]|\\")*"$/s', $token) === 1;
    }
}
