<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Mutation;

use Prontoo\Core\Invariant\Canonical;
use Prontoo\Core\Invariant\Context\ContextInvariantRegistry;
use Prontoo\Core\Invariant\Relation\ForeignKeyGraph;
use Prontoo\Core\Invariant\SqlExpression;
use Prontoo\Core\Invariant\Tenant\ScopeProof;
use Prontoo\Core\Invariant\Tenant\TenantContext;
use Prontoo\Core\Readonly\ReadonlyPolicy;
use Prontoo\Core\Tenant\TenantRegistry;

final class MutationInvariant
{
    private function __construct() {}

    public static function guard(string $sql, array $params = []): void
    {
        $operation = SqlExpression::operation($sql);
        if ($operation === null) {
            return;
        }
        $table = SqlExpression::targetTable($sql);
        if ($table === "") {
            self::deny(
                "write_target_unproved",
                $sql,
                "A tabela-alvo da escrita não pôde ser determinada.",
            );
        }

        $context = TenantContext::resolve();
        if (!empty($context["bypass"])) {
            return;
        }
        $clinicId = (int) ($context["clinic_id"] ?? 0);
        if ($clinicId <= 0) {
            return;
        }

        $scopeColumn = TenantRegistry::scopeColumn($table);
        $insert = in_array($operation, ["INSERT", "REPLACE"], true)
            ? SqlExpression::parseInsert($sql)
            : null;
        $tenantEvidence = [
            "scoped" => is_string($scopeColumn),
            "scope_status" => "not_applicable",
        ];

        if (is_string($scopeColumn)) {
            self::assertReadonly($table, $sql, $clinicId);
            if (in_array($operation, ["UPDATE", "DELETE"], true)) {
                if (SqlExpression::whereExpression($sql) === null) {
                    self::deny(
                        "write_without_where",
                        $sql,
                        "Operação em tabela de consultório sem WHERE.",
                    );
                }
                if (
                    $operation === "UPDATE" &&
                    ScopeProof::updateChangesScope($sql, $scopeColumn)
                ) {
                    self::deny(
                        "write_changes_clinic_scope",
                        $sql,
                        "A escrita tentou transferir o registro entre consultórios.",
                    );
                }
                $status = ScopeProof::whereStatus(
                    $sql,
                    $scopeColumn,
                    $clinicId,
                    $params,
                );
                if ($status !== ScopeProof::ACTIVE) {
                    self::deny(
                        $status === ScopeProof::MISMATCH
                            ? "write_mismatched_clinic_where"
                            : "write_unproved_clinic_where",
                        $sql,
                        $status === ScopeProof::MISMATCH
                            ? "O WHERE aponta para consultório diferente do ativo."
                            : "Nem todos os ramos alcançáveis do WHERE permanecem no consultório ativo.",
                    );
                }
                $tenantEvidence["scope_status"] = $status;
            } else {
                if (!is_array($insert)) {
                    $columnPresent = ScopeProof::insertColumnPresent($sql, $scopeColumn);
                    self::deny(
                        $columnPresent
                            ? "insert_unproved_clinic_value"
                            : "insert_without_clinic_column",
                        $sql,
                        $columnPresent
                            ? "INSERT/REPLACE sem conjunto VALUES demonstrável."
                            : "INSERT/REPLACE sem a coluna de consultório.",
                    );
                }
                $status = ScopeProof::insertStatus(
                    $sql,
                    $insert,
                    $scopeColumn,
                    $clinicId,
                    $params,
                );
                if ($status !== ScopeProof::ACTIVE) {
                    self::deny(
                        $status === ScopeProof::MISMATCH
                            ? "insert_mismatched_clinic_value"
                            : "insert_unproved_clinic_value",
                        $sql,
                        $status === ScopeProof::MISMATCH
                            ? "Uma linha do INSERT/REPLACE aponta para consultório diferente."
                            : "O consultório de todas as linhas do INSERT/REPLACE não foi demonstrado.",
                    );
                }
                if (!ScopeProof::duplicatePreservesScope($sql, $scopeColumn)) {
                    self::deny(
                        "duplicate_changes_clinic_scope",
                        $sql,
                        "ON DUPLICATE KEY UPDATE tentou modificar o consultório proprietário.",
                    );
                }
                $tenantEvidence["scope_status"] = $status;
            }
        }

        $relationEvidence = ForeignKeyGraph::assertWrite(
            $table,
            $operation,
            $sql,
            $params,
            $insert,
            $clinicId,
        );
        $contextEvidence = ContextInvariantRegistry::assertWrite(
            $table,
            $operation,
            $sql,
            $params,
            $insert,
            $clinicId,
        );

        MutationLedger::record([
            "policy" => Canonical::POLICY_VERSION,
            "table" => $table,
            "operation" => $operation,
            "clinic_id" => $clinicId,
            "route" => function_exists("route") ? \route() : "runtime",
            "sql_fingerprint" => hash(
                "sha256",
                preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql),
            ),
            "tenant" => $tenantEvidence,
            "relations" => $relationEvidence,
            "context" => $contextEvidence,
        ]);
    }

    private static function assertReadonly(
        string $table,
        string $sql,
        int $clinicId,
    ): void {
        if (!empty($GLOBALS["PRONTOO_READONLY_GUARD_DISABLED"])) {
            return;
        }
        $readOnly = function_exists("clinic_read_only_db")
            ? (bool) \clinic_read_only_db($clinicId)
            : false;
        if (!$readOnly) {
            return;
        }
        $route = function_exists("route") ? \route() : "login";
        $action = (string) ($_POST["act"] ?? "");
        if (ReadonlyPolicy::sqlAllowed($sql, $route, $action, true)) {
            return;
        }
        self::deny(
            "write_in_read_only",
            $sql,
            "Assinatura em Somente Leitura bloqueou escrita em {$table}.",
            403,
            "Assinatura pendente: regularize antes de alterar dados.",
        );
    }

    private static function deny(
        string $key,
        string $sql,
        string $detail,
        int $status = 500,
        string $publicMessage = "Proteção de isolamento: operação bloqueada por não preservar as invariantes do consultório ativo.",
    ): never {
        if (function_exists("record_scope_violation")) {
            \record_scope_violation($key, $sql, $detail);
        } else {
            error_log(
                "[Prontoo invariant mutation violation] {$key} | " .
                    hash("sha256", preg_replace('/\s+/', ' ', trim($sql)) ?? $sql),
            );
        }
        throw new \ProntooHttpError($status, $publicMessage);
    }

    public static function logicSelfTest(): array
    {
        $scope = ScopeProof::logicSelfTest();
        $contexts = ContextInvariantRegistry::logicSelfTest();
        $workflow = \Prontoo\Core\Invariant\Workflow\AppointmentWorkflow::logicSelfTest();
        $cases = [
            "scope" => !empty($scope["ok"]),
            "contexts" => !empty($contexts["ok"]),
            "workflow" => !empty($workflow["ok"]),
            "target_table" => SqlExpression::targetTable(
                "UPDATE pi_tasks SET status=? WHERE clinic_id=? AND id=?",
            ) === "pi_tasks",
            "insert_parser" => is_array(SqlExpression::parseInsert(
                "INSERT IGNORE INTO pi_tasks (clinic_id,title) VALUES (?,?)",
            )),
            "id_boundary" => (function (): bool {
                $complete = true;
                $values = SqlExpression::whereEqualityValues(
                    "UPDATE pi_appointments SET status=? WHERE patient_link_id=? AND id=? AND clinic_id=?",
                    "id",
                    ["confirmado", 88, 42, 17],
                    $complete,
                );
                return $complete && $values === [42];
            })(),
            "aliased_scope" => ScopeProof::whereStatus(
                "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id SET t.status=? WHERE t.clinic_id=? AND d.appointment_id=?",
                "clinic_id",
                17,
                ["concluida", 17, 4],
            ) === ScopeProof::ACTIVE,
        ];
        $failed = array_keys(array_filter($cases, static fn(bool $ok): bool => !$ok));
        return [
            "ok" => $failed === [],
            "passed" => count($cases) - count($failed),
            "total" => count($cases),
            "failed" => $failed,
            "scope" => $scope,
            "contexts" => $contexts,
            "workflow" => $workflow,
        ];
    }
}
