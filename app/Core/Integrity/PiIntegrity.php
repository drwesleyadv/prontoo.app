<?php
declare(strict_types=1);

namespace Prontoo\Core\Integrity;


final class PiIntegrity
{
    public const POLICY_VERSION = 'pi-action-ledger-v1';
    public const TABLE_PREFIX = 'pi_';

    private static bool $inside = false;
    private static array $events = [];
    private static array $flushedEvents = [];
    private static array $transactionMarks = [];
    private static bool $shutdownRegistered = false;
    private static ?string $requestId = null;
    private static int $flushErrors = 0;
    private static int $transactionPreparedEvents = 0;

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function activePrefix(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::activePrefix
         * Responsabilidade: Implementa a responsabilidade “active prefix” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return self::TABLE_PREFIX;
    }

    public static function physicalTableName(string $logicalTable): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::physicalTableName
         * Responsabilidade: Implementa a responsabilidade “physical table name” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::proveSchemaOperation`, `Core.Integrity.PiIntegrity::targetTableFromSql`.
         * Dependências chamadas: `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return trim($logicalTable, "` \t\n\r\0\x0B");
    }

    public static function rewriteSqlForRuntime(string $sql): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::rewriteSqlForRuntime
         * Responsabilidade: Implementa a responsabilidade “rewrite sql for runtime” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Infrastructure.Audit.PdoActionProofStore::write`.
         * Dependências chamadas: `class_exists`, `.Core.Temporal.PiTime::rewriteTemporalFunctions`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return class_exists('Prontoo\\Core\\Temporal\\PiTime')
            ? \Prontoo\Core\Temporal\PiTime::rewriteTemporalFunctions($sql)
            : $sql;
    }

    public static function prepareRuntimeQuery(string $sql, array $params): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::prepareRuntimeQuery
         * Responsabilidade: Implementa a responsabilidade “prepare runtime query” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `q`, `record_scope_violation`.
         * Dependências chamadas: `class_exists`, `.Core.Temporal.PiTime::prepareRuntimeQuery`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return class_exists('Prontoo\\Core\\Temporal\\PiTime')
            ? \Prontoo\Core\Temporal\PiTime::prepareRuntimeQuery($sql, $params)
            : [$sql, $params];
    }

    public static function rewriteSchemaSql(string $sql): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::rewriteSchemaSql
         * Responsabilidade: Implementa a responsabilidade “rewrite schema sql” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `class_exists`, `.Core.Temporal.PiTime::rewriteSchemaSql`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        return class_exists('Prontoo\\Core\\Temporal\\PiTime')
            ? \Prontoo\Core\Temporal\PiTime::rewriteSchemaSql($sql)
            : $sql;
    }

    public static function bootIndexAutotest(int $budgetMs = 450): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::bootIndexAutotest
         * Responsabilidade: Implementa a responsabilidade “boot index autotest” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `prontoo_install`, `prontoo_run_runtime_maintenance_cycle`.
         * Dependências chamadas: `self::canUseDatabase`, `self::ensureSystemTables`, `self::logOnce`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (!self::canUseDatabase()) {
            return;
        }
        try {
            self::ensureSystemTables();
        } catch (\Throwable $error) {
            self::logOnce('boot', $error);
        }
    }

    public static function bootIndexLightcheck(int $budgetMs = 80): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::bootIndexLightcheck
         * Responsabilidade: Implementa a responsabilidade “boot index lightcheck” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `prontoo_boot_database_for_route`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return;
    }

    public static function ensureGlobalSequence(int $budgetMs = 1800): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::ensureGlobalSequence
         * Responsabilidade: Implementa a responsabilidade “ensure global sequence” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        // Compatibilidade de assinatura: a geração de Seq é nativa no MySQL.
        return;
    }

    public static function runMaestroCycle(int $budgetMs = 120000): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::runMaestroCycle
         * Responsabilidade: Implementa a responsabilidade “run maestro cycle” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `self::flushFastEvents`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        self::flushFastEvents();
        return [
            'ok' => true,
            'complete' => true,
            'mode' => self::POLICY_VERSION,
            'requests_confirmed' => 0,
            'operation_events_confirmed' => 0,
            'batches' => 0,
            'remaining' => 0,
            'missing_seq' => 0,
            'errors' => [],
        ];
    }

    public static function beforeQuery(string $sql, array $params): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::beforeQuery
         * Responsabilidade: Implementa a responsabilidade “before query” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `q`.
         * Dependências chamadas: `self::canUseDatabase`, `self::queryKind`, `in_array`, `self::targetTableFromSql`, `self::isInternalTable`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (self::$inside || !self::canUseDatabase()) {
            return [];
        }
        $kind = self::queryKind($sql);
        if (!in_array($kind, ['insert', 'update', 'delete', 'replace'], true)) {
            return [];
        }
        $table = self::targetTableFromSql($sql);
        if ($table === '' || self::isInternalTable($table)) {
            return [];
        }
        return ['table' => $table, 'kind' => $kind];
    }

    public static function afterQuery(
        string $originalSql,
        string $runtimeSql,
        array $params,
        bool $success,
        int $rowCount,
        float $elapsedMs,
        ?\Throwable $error,
        array $before = [],
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::afterQuery
         * Responsabilidade: Implementa a responsabilidade “after query” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `q`.
         * Dependências chamadas: `self::canUseDatabase`, `self::queryKind`, `in_array`, `self::targetTableFromSql`, `self::isInternalTable`, `self::queueEvent`, `self::recordPkForEvent`, `max`, `hash`, `self::normalizeSql`, `self::canonicalJson`, `round` e mais 2.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (self::$inside || !self::canUseDatabase()) {
            return;
        }
        $kind = self::queryKind($originalSql);
        if (!in_array($kind, ['insert', 'update', 'delete', 'replace'], true)) {
            return;
        }
        $table = self::targetTableFromSql($originalSql);
        if ($table === '' || self::isInternalTable($table)) {
            return;
        }
        self::queueEvent(
            'sql_' . $kind,
            $kind,
            $table,
            self::recordPkForEvent($kind, $table),
            $success,
            max(0, $rowCount),
            hash('sha256', self::normalizeSql($runtimeSql)),
            hash('sha256', self::canonicalJson($params)),
            (int) round(max(0.0, $elapsedMs)),
            $error ? mb_substr($error->getMessage(), 0, 220, 'UTF-8') : null,
        );
    }

    public static function proveSchemaOperation(
        string $operation,
        string|bool $table,
        bool|string|null $success = null,
        ?string $error = null,
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::proveSchemaOperation
         * Responsabilidade: Implementa a responsabilidade “prove schema operation” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `run_schema_sql`.
         * Dependências chamadas: `is_bool`, `is_string`, `preg_match`, `self::physicalTableName`, `self::queueEvent`, `self::cleanToken`.
         * Classes ou serviços instanciados: `.TypeError`.
         * Estado externo lido: `$GLOBALS`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if (is_bool($table)) {
            $legacySql = $operation;
            $legacySuccess = $table;
            $legacyError = is_string($success) ? $success : null;
            if (
                !preg_match(
                    '/^\s*CREATE\s+TABLE\s+`?([a-z0-9_]+)`?/i',
                    $legacySql,
                    $match,
                )
            ) {
                return;
            }
            $operation = 'create_table';
            $table = self::physicalTableName((string) ($match[1] ?? ''));
            $success = $legacySuccess;
            $error = $legacyError;
        }
        if (!is_bool($success) || $table === '') {
            throw new \TypeError(
                'Prova estrutural exige operação, tabela, sucesso e erro opcional.',
            );
        }
        if (!empty($GLOBALS['PRONTOO_SCHEMA_INSTALLING'])) {
            return;
        }
        self::queueEvent(
            'schema_' . self::cleanToken($operation, 'operation'),
            'schema',
            self::physicalTableName($table),
            null,
            $success,
            1,
            null,
            null,
            0,
            $error,
        );
    }

    public static function proveExternalFile(
        string $operation,
        string $path,
        bool $success,
        ?string $error = null,
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::proveExternalFile
         * Responsabilidade: Implementa a responsabilidade “prove external file” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `self::queueEvent`, `self::cleanToken`, `hash`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        self::queueEvent(
            'file_' . self::cleanToken($operation, 'operation'),
            'file',
            null,
            hash('sha256', $path),
            $success,
            1,
            null,
            null,
            0,
            $error,
        );
    }

    public static function prepareForWriteTransaction(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::prepareForWriteTransaction
         * Responsabilidade: Implementa a responsabilidade “prepare for write transaction” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `db_prepare_write_transaction`.
         * Dependências chamadas: `self::canUseDatabase`, `self::ensureSystemTables`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (!self::canUseDatabase()) {
            return;
        }
        self::ensureSystemTables();
    }

    public static function markTransactionStart(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::markTransactionStart
         * Responsabilidade: Implementa a responsabilidade “mark transaction start” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `db_begin_transaction`.
         * Dependências chamadas: `count`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        self::$transactionMarks[] = count(self::$events);
    }

    public static function markTransactionCommitted(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::markTransactionCommitted
         * Responsabilidade: Implementa a responsabilidade “mark transaction committed” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `db_commit`.
         * Dependências chamadas: `array_pop`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        array_pop(self::$transactionMarks);
        if (self::$transactionPreparedEvents > 0) {
            self::$flushedEvents = array_merge(
                self::$flushedEvents,
                self::$events,
            );
            self::$events = [];
            self::$transactionPreparedEvents = 0;
            self::$flushErrors = 0;
        }
    }

    public static function discardTransactionEvents(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::discardTransactionEvents
         * Responsabilidade: Implementa a responsabilidade “discard transaction events” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `db_commit`, `db_rollback`.
         * Dependências chamadas: `array_pop`, `is_int`, `array_slice`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $mark = array_pop(self::$transactionMarks);
        if (is_int($mark) && $mark >= 0) {
            self::$events = array_slice(self::$events, 0, $mark);
        }
        self::$transactionPreparedEvents = 0;
    }

    public static function processDeferredEvents(int $budgetMs = 120000, int $batchSize = 120): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::processDeferredEvents
         * Responsabilidade: Implementa a responsabilidade “process deferred events” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `self::flushFastEvents`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        self::flushFastEvents();
        return [
            'ok' => true,
            'complete' => true,
            'requests_confirmed' => 0,
            'operation_events_confirmed' => 0,
            'batches' => 0,
            'remaining' => 0,
            'errors' => [],
        ];
    }

    public static function flushFastEvents(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::flushFastEvents
         * Responsabilidade: Implementa a responsabilidade “flush fast events” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::runMaestroCycle`, `Core.Integrity.PiIntegrity::processDeferredEvents`, `db_commit`, `prontoo_flush_integrity_before_render`.
         * Dependências chamadas: `self::canUseDatabase`, `self::pdo`, `->inTransaction`, `array_merge`, `self::ensureSystemTables`, `self::requestId`, `time`, `trim`, `function_exists`, `self::sessionInt`, `array_values`, `count` e mais 10.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Estado externo lido: `$GLOBALS`, `$_GET`.
         * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; consome dados da requisição HTTP; pode interromper o fluxo por exceção.
         * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
         */
        self::flushEvents(false);
    }

    public static function flushTransactionEvents(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::flushTransactionEvents
         * Responsabilidade: Persiste a prova da mutação dentro da mesma transação que contém os dados protegidos e falha fechada se a prova não puder ser gravada.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `db_commit`.
         * Dependências chamadas: `self::flushEvents`.
         * Efeitos colaterais: grava o ledger dentro da transação corrente; pode interromper o commit.
         * Cuidado 1: Nunca capture a exceção deste método no chamador sem também desfazer a transação.
         */
        self::flushEvents(true);
    }

    private static function flushEvents(bool $transactional): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::flushEvents
         * Responsabilidade: Materializa de forma canônica os eventos pendentes no ledger, com modo estrito para transações e modo resiliente fora delas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::flushFastEvents`, `Core.Integrity.PiIntegrity::flushTransactionEvents`.
         * Dependências chamadas: `self::pdo`, `self::ensureSystemTables`, `self::canonicalJson`, `hash_hmac`.
         * Efeitos colaterais: acessa e grava a camada de persistência.
         * Cuidado 1: No modo transacional, os eventos só saem da fila depois que `markTransactionCommitted` confirma o commit.
         */
        if (!self::canUseDatabase() || self::$inside || self::$events === []) {
            return;
        }
        if (!$transactional && self::$flushErrors > 3) {
            return;
        }
        try {
            $pdo = self::pdo();
            if ($transactional && !$pdo->inTransaction()) {
                throw new \RuntimeException(
                    'A prova transacional exige uma transação ativa.',
                );
            }
            if (!$transactional && $pdo->inTransaction()) {
                return;
            }
        } catch (\Throwable $error) {
            if ($transactional) {
                throw $error;
            }
            return;
        }

        $pendingEvents = self::$events;
        $events = array_merge(self::$flushedEvents, $pendingEvents);
        if (!$transactional) {
            self::$events = [];
        }
        try {
            self::$inside = true;
            self::ensureSystemTables();
            $pdo = self::pdo();
            $requestId = (string) ($GLOBALS['PRONTOO_ACTION_LEDGER_REQUEST_ID'] ?? self::requestId());
            $ledgerId = (int) ($GLOBALS['PRONTOO_ACTION_LEDGER_ID'] ?? 0);
            $createdAt = time();
            $tables = [];
            foreach ($events as $event) {
                $table = trim((string) ($event['table_name'] ?? ''));
                if ($table !== '') {
                    $tables[$table] = true;
                }
            }
            $payload = [
                'request_id' => $requestId,
                'route' => function_exists('route') ? (string) \route() : (string) ($_GET['r'] ?? ''),
                'clinic_id' => self::sessionInt('clinic_id'),
                'user_id' => self::sessionInt('uid'),
                'events' => array_values($events),
                'event_count' => count($events),
                'affected_tables' => array_keys($tables),
                'policy_version' => self::POLICY_VERSION,
                'finalized_at' => $createdAt,
            ];
            $payloadJson = self::canonicalJson($payload);
            $mutationHash = hash_hmac('sha256', $payloadJson, self::secret());
            $affectedJson = self::canonicalJson(array_keys($tables));

            if ($ledgerId > 0) {
                $statement = $pdo->prepare(
                    "UPDATE pi_action_ledger SET status='committed',mutation_hash=?,mutation_count=?,affected_tables_json=?,mutation_json=?,finalized_at=? WHERE id=? AND allowed=1",
                );
                $statement->execute([
                    $mutationHash,
                    count($events),
                    $affectedJson,
                    $payloadJson,
                    $createdAt,
                    $ledgerId,
                ]);
                if ($statement->rowCount() <= 0) {
                    throw new \RuntimeException('Ledger autorizado ausente durante finalização da mutação.');
                }
            } else {
                $authorizationHash = hash_hmac(
                    'sha256',
                    'system|' . $requestId . '|' . $mutationHash,
                    self::secret(),
                );
                $statement = $pdo->prepare(
                    "INSERT INTO pi_action_ledger (request_id,clinic_id,user_id,route,action_key,module_key,operation_key,scope,role_code,allowed,status,reason,authorization_hash,mutation_hash,mutation_count,affected_tables_json,mutation_json,context_json,policy_version,created_at,finalized_at) VALUES (?,?,?,?,?,?,?,?,?,1,'committed',?,?,?,?,?,?,?,?,?,?)",
                );
                $statement->execute([
                    $requestId,
                    self::sessionInt('clinic_id'),
                    self::sessionInt('uid'),
                    mb_substr((string) ($payload['route'] ?? 'system'), 0, 80, 'UTF-8') ?: 'system',
                    'system_mutation',
                    'integrity',
                    'write',
                    'system',
                    function_exists('session_clinic_role_code') ? (string) \session_clinic_role_code() : null,
                    'Mutação interna registrada pelo núcleo de invariantes.',
                    $authorizationHash,
                    $mutationHash,
                    count($events),
                    $affectedJson,
                    $payloadJson,
                    $payloadJson,
                    self::POLICY_VERSION,
                    $createdAt,
                    $createdAt,
                ]);
                $GLOBALS['PRONTOO_ACTION_LEDGER_ID'] = (int) $pdo->lastInsertId();
                $GLOBALS['PRONTOO_ACTION_LEDGER_REQUEST_ID'] = $requestId;
            }
            if ($transactional) {
                self::$transactionPreparedEvents = count($pendingEvents);
            } else {
                self::$flushedEvents = $events;
                self::$flushErrors = 0;
            }
        } catch (\Throwable $error) {
            self::$flushErrors++;
            if (!$transactional) {
                self::$events = array_merge($pendingEvents, self::$events);
            }
            self::logOnce('action-ledger-flush', $error);
            if ($transactional) {
                throw $error;
            }
        } finally {
            self::$inside = false;
        }
    }

    public static function rowHash(array $row): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::rowHash
         * Responsabilidade: Implementa a responsabilidade “row hash” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `ksort`, `hash_hmac`, `self::canonicalJson`, `self::secret`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        ksort($row);
        return hash_hmac('sha256', self::canonicalJson($row), self::secret());
    }

    public static function canonicalJson(mixed $value): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::canonicalJson
         * Responsabilidade: Implementa a responsabilidade “canonical json” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::afterQuery`, `Core.Integrity.PiIntegrity::flushFastEvents`, `Core.Integrity.PiIntegrity::rowHash`.
         * Dependências chamadas: `json_encode`, `self::canonicalize`.
         * Efeitos colaterais: produz conteúdo de saída.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return json_encode(
            self::canonicalize($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        ) ?: 'null';
    }

    private static function queueEvent(
        string $eventKey,
        string $operation,
        ?string $table,
        ?string $recordPk,
        bool $success,
        int $rowCount,
        ?string $sqlFingerprint,
        ?string $paramsHash,
        int $elapsedMs,
        ?string $error,
    ): void {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::queueEvent
         * Responsabilidade: Implementa a responsabilidade “queue event” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::afterQuery`, `Core.Integrity.PiIntegrity::proveSchemaOperation`, `Core.Integrity.PiIntegrity::proveExternalFile`.
         * Dependências chamadas: `self::canUseDatabase`, `self::registerShutdown`, `mb_substr`, `max`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (!self::canUseDatabase()) {
            return;
        }
        self::registerShutdown();
        self::$events[] = [
            'event_key' => mb_substr($eventKey, 0, 80, 'UTF-8'),
            'operation' => mb_substr($operation, 0, 24, 'UTF-8'),
            'table_name' => $table !== null ? mb_substr($table, 0, 120, 'UTF-8') : null,
            'record_pk' => $recordPk,
            'success' => $success,
            'row_count' => max(0, $rowCount),
            'sql_fingerprint' => $sqlFingerprint,
            'params_hash' => $paramsHash,
            'elapsed_ms' => max(0, $elapsedMs),
            'error' => $error !== null ? mb_substr($error, 0, 220, 'UTF-8') : null,
        ];
    }

    private static function registerShutdown(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::registerShutdown
         * Responsabilidade: Implementa a responsabilidade “register shutdown” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::queueEvent`.
         * Dependências chamadas: `register_shutdown_function`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function([self::class, 'flushFastEvents']);
    }

    private static function ensureSystemTables(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::ensureSystemTables
         * Responsabilidade: Implementa a responsabilidade “ensure system tables” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::bootIndexAutotest`, `Core.Integrity.PiIntegrity::prepareForWriteTransaction`, `Core.Integrity.PiIntegrity::flushFastEvents`.
         * Dependências chamadas: `self::tableExists`.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        foreach (['pi_action_ledger', 'pi_integrity_alerts'] as $table) {
            if (!self::tableExists($table)) {
                throw new \RuntimeException("Tabela de integridade ausente: {$table}.");
            }
        }
    }

    private static function tableExists(string $table): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::tableExists
         * Responsabilidade: Implementa a responsabilidade “table exists” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::ensureSystemTables`.
         * Dependências chamadas: `self::pdo`, `->prepare`, `->execute`, `->fetchColumn`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        $stmt = self::pdo()->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1',
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private static function canUseDatabase(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::canUseDatabase
         * Responsabilidade: Implementa a responsabilidade “can use database” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::bootIndexAutotest`, `Core.Integrity.PiIntegrity::beforeQuery`, `Core.Integrity.PiIntegrity::afterQuery`, `Core.Integrity.PiIntegrity::prepareForWriteTransaction`, `Core.Integrity.PiIntegrity::flushFastEvents`, `Core.Integrity.PiIntegrity::queueEvent`.
         * Dependências chamadas: `function_exists`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return function_exists('has_cfg') && \has_cfg() && function_exists('pdo');
    }

    private static function pdo(): \PDO
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::pdo
         * Responsabilidade: Implementa a responsabilidade “pdo” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::flushFastEvents`, `Core.Integrity.PiIntegrity::tableExists`, `Core.Integrity.PiIntegrity::recordPkForEvent`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return \pdo();
    }

    private static function sessionInt(string $key): ?int
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::sessionInt
         * Responsabilidade: Implementa a responsabilidade “session int” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::flushFastEvents`.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Estado externo lido: `$_SESSION`.
         * Efeitos colaterais: lê ou altera a sessão.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $value = (int) ($_SESSION[$key] ?? 0);
        return $value > 0 ? $value : null;
    }

    private static function isInternalTable(string $table): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::isInternalTable
         * Responsabilidade: Implementa a responsabilidade “is internal table” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::beforeQuery`, `Core.Integrity.PiIntegrity::afterQuery`.
         * Dependências chamadas: `in_array`, `strtolower`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return in_array(strtolower($table), [
            'pi_action_ledger',
            'pi_integrity_alerts',
            'pi_audit',
            'pi_error_events',
            'pi_scope_violations',
            'pi_platform_counters',
            'pi_clinic_daily_stats',
            'pi_maestro_job_runs',
            'pi_maestro_job_stats',
            'pi_meta',
        ], true);
    }

    private static function targetTableFromSql(string $sql): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::targetTableFromSql
         * Responsabilidade: Implementa a responsabilidade “target table from sql” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::beforeQuery`, `Core.Integrity.PiIntegrity::afterQuery`.
         * Dependências chamadas: `preg_match`, `self::physicalTableName`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $patterns = [
            '/^\s*INSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-z0-9_]+)`?/i',
            '/^\s*REPLACE\s+INTO\s+`?([a-z0-9_]+)`?/i',
            '/^\s*UPDATE\s+`?([a-z0-9_]+)`?/i',
            '/^\s*DELETE\s+FROM\s+`?([a-z0-9_]+)`?/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql, $match)) {
                return self::physicalTableName((string) ($match[1] ?? ''));
            }
        }
        return '';
    }

    private static function queryKind(string $sql): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::queryKind
         * Responsabilidade: Implementa a responsabilidade “query kind” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::beforeQuery`, `Core.Integrity.PiIntegrity::afterQuery`.
         * Dependências chamadas: `preg_match`, `strtolower`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return preg_match('/^\s*([a-z]+)/i', $sql, $match)
            ? strtolower((string) $match[1])
            : '';
    }

    private static function normalizeSql(string $sql): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::normalizeSql
         * Responsabilidade: Implementa a responsabilidade “normalize sql” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::afterQuery`.
         * Dependências chamadas: `strtolower`, `trim`, `preg_replace`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return strtolower(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));
    }

    private static function recordPkForEvent(string $kind, string $table): ?string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::recordPkForEvent
         * Responsabilidade: Implementa a responsabilidade “record pk for event” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::afterQuery`.
         * Dependências chamadas: `in_array`, `self::pdo`, `->lastInsertId`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (!in_array($kind, ['insert', 'replace'], true)) {
            return null;
        }
        try {
            $id = (string) self::pdo()->lastInsertId();
            return $id !== '' && $id !== '0' ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function canonicalize(mixed $value): mixed
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::canonicalize
         * Responsabilidade: Implementa a responsabilidade “canonicalize” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::canonicalJson`.
         * Dependências chamadas: `is_array`, `array_is_list`, `ksort`, `self::canonicalize`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
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

    private static function secret(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::secret
         * Responsabilidade: Implementa a responsabilidade “secret” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::flushFastEvents`, `Core.Integrity.PiIntegrity::rowHash`.
         * Dependências chamadas: `function_exists`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        try {
            return function_exists('secret_key')
                ? (string) \secret_key()
                : (string) (\cfg()['secret'] ?? 'prontoo-integrity');
        } catch (\Throwable) {
            return 'prontoo-integrity';
        }
    }

    private static function logOnce(string $stage, \Throwable $error): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::logOnce
         * Responsabilidade: Implementa a responsabilidade “log once” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::bootIndexAutotest`, `Core.Integrity.PiIntegrity::flushFastEvents`.
         * Dependências chamadas: `->getMessage`, `error_log`.
         * Efeitos colaterais: gera trilha de auditoria ou telemetria.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        static $logged = [];
        $key = $stage . '|' . $error->getMessage();
        if (isset($logged[$key])) {
            return;
        }
        $logged[$key] = true;
        error_log('[Prontoo integrity ' . $stage . '] ' . $error->getMessage());
    }

    private static function requestId(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::requestId
         * Responsabilidade: Implementa a responsabilidade “request id” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::flushFastEvents`.
         * Dependências chamadas: `bin2hex`, `random_bytes`, `md5`, `uniqid`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (self::$requestId !== null) {
            return self::$requestId;
        }
        try {
            return self::$requestId = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return self::$requestId = md5(uniqid('prontoo', true));
        }
    }

    private static function cleanToken(string $value, string $fallback): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Integrity.PiIntegrity::cleanToken
         * Responsabilidade: Implementa a responsabilidade “clean token” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Integrity/PiIntegrity.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Integrity.PiIntegrity::proveSchemaOperation`, `Core.Integrity.PiIntegrity::proveExternalFile`.
         * Dependências chamadas: `preg_replace`, `trim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return preg_replace('/[^a-z0-9_-]+/i', '_', trim($value)) ?: $fallback;
    }
}
