<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Temporal;

final class PdoTemporalIntegrity
{
    private function __construct()
    {
    }

    public static function normalizeInsertedTemporalDefaults(
        \PDO $pdo,
        string $table,
        string|int $id,
    ): void {

        try {
            $table = self::cleanIdent($table);
            $pk = self::singlePrimaryKey($pdo, $table) ?: "id";
            $cols = self::columns($pdo, $table);
            $sets = [];
            $params = [];
            $now = time();
            foreach (["created_at", "updated_at"] as $col) {
                if (!in_array($col, $cols, true)) {
                    continue;
                }
                if ($col === "created_at") {
                    $sets[] =
                        "`created_at`=IF(`created_at` IS NULL OR `created_at`=0, ?, `created_at`)";
                    $params[] = $now;
                }
            }
            if (!$sets) {
                return;
            }
            $params[] = (string) $id;
            $st = $pdo->prepare(
                "UPDATE `" .
                    $table .
                    "` SET " .
                    implode(",", $sets) .
                    " WHERE `" .
                    self::cleanIdent($pk) .
                    "`=? LIMIT 1",
            );
            $st->execute($params);
        } catch (\Throwable $e) {
            error_log(
                "[Prontoo PI time normalize insert] " .
                    $table .
                    " | " .
                    $e->getMessage(),
            );
        }
    }
    private static function singlePrimaryKey(\PDO $pdo, string $table): ?string
    {

        $st = $pdo->prepare(
            "SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_key='PRI' ORDER BY ordinal_position",
        );
        $st->execute([$table]);
        $pks = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        return count($pks) === 1 ? (string) $pks[0] : null;
    }
    private static function columns(\PDO $pdo, string $table): array
    {

        $st = $pdo->prepare(
            "SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ordinal_position",
        );
        $st->execute([$table]);
        return array_values(
            array_map("strval", $st->fetchAll(\PDO::FETCH_COLUMN) ?: []),
        );
    }
    public static function countTemporalViolations(?\PDO $pdo = null): array
    {

        $out = [
            "datetime_columns" => 0,
            "date_columns" => 0,
            "timestamp_columns" => 0,
            "columns" => [],
        ];
        try {
            $pdo = $pdo ?: (is_callable([\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::class, 'pdo']) ? \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo() : null);
            if (!$pdo) {
                return $out;
            }
            $st = $pdo->query(
                "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, DATA_TYPE AS data_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name LIKE 'pi\\_%' AND data_type IN ('datetime','timestamp','date') ORDER BY TABLE_NAME,COLUMN_NAME",
            );
            foreach (($st ? $st->fetchAll(\PDO::FETCH_ASSOC) : []) ?: [] as $r) {
                $row = [];
                foreach ($r as $k => $v) {
                    if (is_string($k)) {
                        $row[strtolower($k)] = $v;
                    }
                }
                $dt = strtolower((string) ($row["data_type"] ?? ""));
                $table = mb_trim((string) ($row["table_name"] ?? ""));
                $column = mb_trim((string) ($row["column_name"] ?? ""));
                if ($dt === "" || $table === "" || $column === "") {
                    continue;
                }
                $out[$dt . "_columns"] =
                    (int) ($out[$dt . "_columns"] ?? 0) + 1;
                $out["columns"][] = $table . "." . $column . ":" . $dt;
            }
        } catch (\Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
        return $out;
    }
    private static function cleanIdent(string $name): string
    {
        $name = mb_trim($name);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Identificador SQL inválido.');
        }
        return $name;
    }
}
