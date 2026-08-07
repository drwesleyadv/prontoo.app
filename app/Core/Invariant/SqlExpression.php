<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;
final class SqlExpression
{
    private function __construct()
    {
    }

    public static function operation(string $sql): ?string
    {
        return SqlMutationParser::operation($sql);
    }

    public static function targetTable(string $sql): string
    {
        return SqlMutationParser::targetTable($sql);
    }

    public static function topLevelKeywordPosition(string $sql, string $keyword, int $start = 0): ?int
    {
        return SqlLexicalScanner::topLevelKeywordPosition($sql, $keyword, $start);
    }

    public static function topLevelPhrasePosition(string $sql, string $phrase, int $start = 0): ?int
    {
        return SqlLexicalScanner::topLevelPhrasePosition($sql, $phrase, $start);
    }

    public static function splitTopLevelWithOffsets(string $expression, string $delimiter = ","): array
    {
        return SqlLexicalScanner::splitTopLevelWithOffsets($expression, $delimiter);
    }

    public static function splitBooleanTopLevel(string $expression, string $operator): array
    {
        return SqlLexicalScanner::splitBooleanTopLevel($expression, $operator);
    }

    public static function trimExpression(string $expression, int $baseOffset): array
    {
        return SqlLexicalScanner::trimExpression($expression, $baseOffset);
    }

    public static function outerParenthesesWrap(string $expression): bool
    {
        return SqlLexicalScanner::outerParenthesesWrap($expression);
    }

    public static function placeholderIndexBefore(string $sql, int $offset): int
    {
        return SqlLexicalScanner::placeholderIndexBefore($sql, $offset);
    }

    public static function tokenValue(
        string $sql,
        string $token,
        int $absoluteOffset,
        array $params,
        ?bool &$known = null,
    ): mixed
    {
        return SqlLexicalScanner::tokenValue($sql, $token, $absoluteOffset, $params, $known);
    }

    public static function parseInsert(string $sql): ?array
    {
        return SqlMutationParser::parseInsert($sql);
    }

    public static function assignments(string $sql): array
    {
        return SqlMutationParser::assignments($sql);
    }

    public static function whereExpression(string $sql): ?array
    {
        return SqlMutationParser::whereExpression($sql);
    }

    public static function whereEqualityValues(
        string $sql,
        string $column,
        array $params,
        ?bool &$complete = null,
    ): array
    {
        return SqlPredicateAnalyzer::whereEqualityValues($sql, $column, $params, $complete);
    }

    public static function whereAllowedValues(
        string $sql,
        string $column,
        array $params,
        ?bool &$complete = null,
    ): array
    {
        return SqlPredicateAnalyzer::whereAllowedValues($sql, $column, $params, $complete);
    }

    public static function insertColumnValues(
        string $sql,
        array $params,
        array $parsed,
        string $column,
        ?bool &$complete = null,
    ): array
    {
        return SqlMutationParser::insertColumnValues($sql, $params, $parsed, $column, $complete);
    }

    public static function identifier(string $raw): string
    {
        return SqlLexicalScanner::identifier($raw);
    }

    public static function isDirectValueToken(string $token): bool
    {
        return SqlLexicalScanner::isDirectValueToken($token);
    }

}
