<?php
declare(strict_types=1);

namespace Prontoo\Core\Integrity;

final class PiSequence
{
    private static array $columns = [];
    private static array $primaryKeys = [];
    private static array $autoIncrementKeys = [];
    private static array $validatedTables = [];

    private function __construct() {}

    public static function ensure(\PDO $pdo, int $budgetMs = 1800): void
    {
        self::ensureCounterTable($pdo);
        $startedAt = microtime(true);
        $batchSize = defined("PRONTOO_MAESTRO_PI_BATCH_SIZE")
            ? max(1, min(500, (int) PRONTOO_MAESTRO_PI_BATCH_SIZE))
            : 80;

        foreach (self::piTables($pdo) as $table) {
            if ((microtime(true) - $startedAt) * 1000 >= max(100, $budgetMs)) {
                break;
            }
            self::ensureTableHasSeq($pdo, $table);
            self::backfillTable($pdo, $table, $batchSize);
        }
    }

    public static function ensureCounterTable(\PDO $pdo): void
    {
        if (!self::tableExists($pdo, "pi_sequence")) {
            throw new \RuntimeException(
                "Tabela pi_sequence ausente no schema canônico.",
            );
        }
        self::ensureTableHasSeq($pdo, "pi_sequence");
    }

    public static function piTables(\PDO $pdo): array
    {
        $statement = $pdo->prepare(
            "SELECT table_name
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name LIKE 'pi\\_%'
              ORDER BY table_name",
        );
        $statement->execute();
        return array_values(
            array_map("strval", $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []),
        );
    }

    public static function ensureTableHasSeq(\PDO $pdo, string $table): void
    {
        $table = self::identifier($table);
        if (isset(self::$validatedTables[$table])) {
            return;
        }
        if (!self::tableExists($pdo, $table)) {
            throw new \RuntimeException(
                "Tabela {$table} ausente no schema canônico.",
            );
        }
        if (!self::columnExists($pdo, $table, "Seq")) {
            throw new \RuntimeException("Coluna Seq ausente em {$table}.");
        }
        if (!self::hasUniqueSequenceIndex($pdo, $table)) {
            throw new \RuntimeException(
                "Índice único de Seq ausente em {$table}.",
            );
        }
        self::$validatedTables[$table] = true;
    }

    public static function assignById(
        \PDO $pdo,
        string $table,
        string|int $id,
    ): ?int {
        $key = self::autoIncrementPrimaryKey($pdo, $table);
        if ($key === null) {
            return null;
        }
        return self::assignWhere(
            $pdo,
            $table,
            "{$key}=" . (string) $id,
            "`" . self::identifier($key) . "` = ?",
            [(string) $id],
        );
    }

    public static function assignByRecordPk(
        \PDO $pdo,
        string $table,
        string $recordPk,
    ): ?int {
        if (preg_match('/^([A-Za-z0-9_]+)=(.*)$/s', $recordPk, $match)) {
            $column = (string) $match[1];
            if (!self::columnExists($pdo, $table, $column)) {
                return null;
            }
            return self::assignWhere(
                $pdo,
                $table,
                $recordPk,
                "`" . self::identifier($column) . "` = ?",
                [(string) $match[2]],
            );
        }

        $key = self::singlePrimaryKey($pdo, $table);
        if ($key === null) {
            return null;
        }
        return self::assignWhere(
            $pdo,
            $table,
            "{$key}={$recordPk}",
            "`" . self::identifier($key) . "` = ?",
            [$recordPk],
        );
    }

    public static function assignForRow(
        \PDO $pdo,
        string $table,
        array $row,
    ): ?int {
        if ((int) ($row["Seq"] ?? 0) > 0) {
            return (int) $row["Seq"];
        }

        $primaryKeys = self::primaryKeys($pdo, $table);
        if ($primaryKeys !== []) {
            $where = [];
            $params = [];
            $record = [];
            foreach ($primaryKeys as $column) {
                if (!array_key_exists($column, $row)) {
                    return null;
                }
                $where[] = "`" . self::identifier($column) . "` = ?";
                $params[] = (string) $row[$column];
                $record[] = $column . "=" . (string) $row[$column];
            }
            return self::assignWhere(
                $pdo,
                $table,
                implode("|", $record),
                implode(" AND ", $where),
                $params,
            );
        }

        $recordHash = hash("sha256", self::canonicalJson($row));
        return self::assignWhere(
            $pdo,
            $table,
            "rowhash={$recordHash}",
            "`Seq` IS NULL",
            [],
            true,
        );
    }

    private static function assignWhere(
        \PDO $pdo,
        string $table,
        string $recordPk,
        string $whereSql,
        array $params,
        bool $limitOne = false,
    ): ?int {
        $table = self::identifier($table);
        try {
            self::ensureCounterTable($pdo);
            self::ensureTableHasSeq($pdo, $table);

            $read = $pdo->prepare(
                "SELECT `Seq` FROM `{$table}` WHERE {$whereSql} AND `Seq` IS NOT NULL LIMIT 1",
            );
            $read->execute($params);
            $existing = $read->fetchColumn();
            if ($existing !== false && (int) $existing > 0) {
                return (int) $existing;
            }

            $sequence = self::allocate($pdo, $table, $recordPk);
            $update = $pdo->prepare(
                "UPDATE `{$table}` SET `Seq` = ? WHERE {$whereSql} AND `Seq` IS NULL" .
                    ($limitOne ? " LIMIT 1" : ""),
            );
            $update->execute(array_merge([$sequence], $params));
            return $sequence;
        } catch (\Throwable $error) {
            error_log(
                "[Prontoo PI sequence] {$table} {$recordPk} | " .
                    substr($error->getMessage(), 0, 240),
            );
            return null;
        }
    }

    private static function allocate(
        \PDO $pdo,
        string $table,
        string $recordPk,
    ): int {
        $targetHash = hash("sha256", $table . "|" . $recordPk);
        $statement = $pdo->prepare(
            "INSERT INTO pi_sequence (table_name, record_pk, target_hash, allocated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `Seq` = LAST_INSERT_ID(`Seq`)",
        );
        $statement->execute([$table, $recordPk, $targetHash, time()]);
        return (int) $pdo->lastInsertId();
    }

    private static function backfillTable(
        \PDO $pdo,
        string $table,
        int $limit,
    ): void {
        $table = self::identifier($table);
        if ($table === "pi_sequence") {
            return;
        }
        $statement = $pdo->query(
            "SELECT * FROM `{$table}` WHERE `Seq` IS NULL " .
                self::orderSql($pdo, $table) .
                " LIMIT " .
                max(1, min(500, $limit)),
        );
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            self::assignForRow($pdo, $table, $row);
        }
    }

    private static function orderSql(\PDO $pdo, string $table): string
    {
        $available = array_fill_keys(self::columns($pdo, $table), true);
        $order = [];
        foreach (["created_at", "updated_at", "id", "Seq"] as $column) {
            if (isset($available[$column])) {
                $order[] = "`{$column}`";
            }
        }
        return $order === [] ? "" : "ORDER BY " . implode(", ", $order);
    }

    private static function tableExists(\PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1",
        );
        $statement->execute([$table]);
        return $statement->fetchColumn() !== false;
    }

    private static function columnExists(
        \PDO $pdo,
        string $table,
        string $column,
    ): bool {
        return in_array($column, self::columns($pdo, $table), true);
    }

    private static function hasUniqueSequenceIndex(
        \PDO $pdo,
        string $table,
    ): bool {
        $statement = $pdo->prepare(
            "SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = ?
                AND column_name = 'Seq'
                AND non_unique = 0
              LIMIT 1",
        );
        $statement->execute([$table]);
        return $statement->fetchColumn() !== false;
    }

    private static function columns(\PDO $pdo, string $table): array
    {
        $table = self::identifier($table);
        if (isset(self::$columns[$table])) {
            return self::$columns[$table];
        }
        $statement = $pdo->prepare(
            "SELECT column_name FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ?
              ORDER BY ordinal_position",
        );
        $statement->execute([$table]);
        return self::$columns[$table] = array_values(
            array_map("strval", $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []),
        );
    }

    private static function primaryKeys(\PDO $pdo, string $table): array
    {
        $table = self::identifier($table);
        if (isset(self::$primaryKeys[$table])) {
            return self::$primaryKeys[$table];
        }
        $statement = $pdo->prepare(
            "SELECT column_name FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = ?
                AND column_key = 'PRI'
              ORDER BY ordinal_position",
        );
        $statement->execute([$table]);
        return self::$primaryKeys[$table] = array_values(
            array_map("strval", $statement->fetchAll(\PDO::FETCH_COLUMN) ?: []),
        );
    }

    private static function singlePrimaryKey(\PDO $pdo, string $table): ?string
    {
        $keys = self::primaryKeys($pdo, $table);
        return count($keys) === 1 ? $keys[0] : null;
    }

    private static function autoIncrementPrimaryKey(
        \PDO $pdo,
        string $table,
    ): ?string {
        $table = self::identifier($table);
        if (array_key_exists($table, self::$autoIncrementKeys)) {
            return self::$autoIncrementKeys[$table];
        }
        $statement = $pdo->prepare(
            "SELECT column_name, extra FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = ?
                AND column_key = 'PRI'
              ORDER BY ordinal_position",
        );
        $statement->execute([$table]);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        if (count($rows) !== 1) {
            return self::$autoIncrementKeys[$table] = null;
        }
        $row = array_change_key_case($rows[0], CASE_LOWER);
        $column = trim((string) ($row["column_name"] ?? ""));
        $extra = strtolower((string) ($row["extra"] ?? ""));
        return self::$autoIncrementKeys[$table] =
            $column !== "" && str_contains($extra, "auto_increment")
                ? $column
                : null;
    }

    private static function canonicalJson(mixed $value): string
    {
        return json_encode(
            self::canonicalize($value),
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PRESERVE_ZERO_FRACTION,
        ) ?:
            "null";
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $isList = array_keys($value) === range(0, count($value) - 1);
        if (!$isList) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }
        return $value;
    }

    private static function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \RuntimeException("Identificador inválido: {$name}");
        }
        return $name;
    }
}
