<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Maestro;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class MaestroRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function maestro_create_action(array $rule, array $match): array
    
    {
    
        $cid = (int) $rule["clinic_id"];
        $act = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_decode_json($rule["action_json"] ?? "");
        $vars = $match["variables"] ?? [];
        $title = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_text(
            \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_apply_placeholders(
                (string) ($act["title"] ?? "Ação da rotina"),
                $vars,
            ),
            180,
        );
        $body = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_template(
            \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_apply_placeholders((string) ($act["description"] ?? ""), $vars),
            1200,
        );
        if ($title === "") {
            $title = "Ação da rotina";
        }
        $scope = (string) ($act["target_scope"] ?? "clinic");
        $role = $act["target_role"] ?? null;
        $userId = isset($act["target_user_id"])
            ? (int) $act["target_user_id"]
            : null;
        if (!in_array($scope, ["clinic", "role", "user"], true)) {
            throw new RuntimeException("Escopo de destinatário inválido para a ação.");
        }
        if (
            $scope === "role" &&
            !array_key_exists((string) $role, \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_options($cid, true))
        ) {
            throw new RuntimeException("Cargo destinatário indisponível para a ação.");
        }
        if ($scope === "user" && (!$userId || !\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_user_exists($cid, $userId))) {
            throw new RuntimeException("Pessoa destinatária indisponível para a ação.");
        }
        if ($scope !== "role") {
            $role = null;
        }
        if ($scope !== "user") {
            $userId = null;
        }
        if ((string) $rule["action_type"] === "create_notice") {
            \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    $title,
                    $body,
                    1,
                    $scope === "clinic" ? "all" : $scope,
                    $role,
                    $userId,
                    \Prontoo\Presentation\Maestro\MaestroPresentationOperations01::maestro_actor_id(),
                ],
            );
            $id = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_last_insert_id();
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::counter_inc("notices_total");
            \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_metric_inc($cid, "notices");
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("maestro_notificacao_criada", "comunicado", $id, [
                "clinic_id" => $cid,
                "regra_id" => (int) $rule["id"],
                "titulo" => $title,
                "audit_body" => "Aviso criado automaticamente por uma rotina.",
            ]);
            return ["entity" => "comunicado", "id" => (string) $id];
        }
        $sourceEvent = "maestro_" . (int) $rule["id"];
        $sourceEntity = (string) ($match["source_entity"] ?? "registro");
        $sourceId = (string) ($match["source_entity_id"] ?? "0");
        $taskId = \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::create_workflow_task(
            $cid,
            $title,
            $body,
            $userId,
            $match["patient_link_id"] ?? null,
            $match["appointment_id"] ?? null,
            $sourceEvent,
            $sourceEntity,
            $sourceId,
            \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_due_dt((int) ($act["due_offset_days"] ?? 0), $cid),
            $scope,
            $role,
            true,
        );
        if (!$taskId) {
            throw new RuntimeException(
                "A tarefa da rotina não foi confirmada no consultório esperado.",
            );
        }
        return [
            "entity" => "tarefa",
            "id" => $taskId > 0 ? (string) $taskId : null,
        ];
    
    }

    public static function maestro_rule_score(array $rule, ?array $stat = null): float
    
    {
    
        $priority = (int) ($rule["priority"] ?? 50);
        $next = $rule["next_run_at"]
            ? \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($rule["next_run_at"])
            : 0;
        $lag = $next
            ? max(0, min(240, (time() - $next) / 60))
            : PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES;
        $yield = (float) ($stat["ewma_yield"] ?? 0);
        $duration = max(1, (float) ($stat["ewma_duration_ms"] ?? 100));
        return $priority + $lag + min(30, $yield * 8) - min(25, $duration / 500);
    
    }

    public static function maestro_stats_update(
        string $key,
        float $duration,
        int $created,
        float $score,
        bool $skipped = false,
    ): void 
    {
    
        \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_tx(function () use (
            $key,
            $duration,
            $created,
            $score,
            $skipped,
        ): void {
    
            $old = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::one(
                "SELECT * FROM pi_maestro_job_stats WHERE routine_key=? FOR UPDATE",
                [$key],
            );
            if ($skipped) {
                if ($old) {
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "UPDATE pi_maestro_job_stats SET skip_count=skip_count+1,last_score=?,updated_at=NOW() WHERE routine_key=?",
                        [$score, $key],
                    );
                } else {
                    \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                        "INSERT INTO pi_maestro_job_stats (routine_key,ewma_duration_ms,ewma_yield,run_count,skip_count,last_score,last_run_at,updated_at) VALUES (?,0,0,0,1,?,NULL,NOW())",
                        [$key, $score],
                    );
                }
                return;
            }
            $hasObservation = $old && (int) ($old["run_count"] ?? 0) > 0;
            $dur = (float) \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_ewma_observation(
                $hasObservation ? (float) $old["ewma_duration_ms"] : null,
                max(0.0, $duration),
            );
            $yield = (float) \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_ewma_observation(
                $hasObservation ? (float) $old["ewma_yield"] : null,
                max(0, $created),
            );
            if ($old) {
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "UPDATE pi_maestro_job_stats SET ewma_duration_ms=?,ewma_yield=?,run_count=run_count+1,last_score=?,last_run_at=NOW(),updated_at=NOW() WHERE routine_key=?",
                    [$dur, $yield, $score, $key],
                );
            } else {
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "INSERT INTO pi_maestro_job_stats (routine_key,ewma_duration_ms,ewma_yield,run_count,skip_count,last_score,last_run_at,updated_at) VALUES (?,?,?,1,0,?,NOW(),NOW())",
                    [$key, $dur, $yield, $score],
                );
            }
        });
    
    }

    public static function maestro_with_guarded_clinic(int $cid, callable $fn): mixed
    
    {
    
        return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_scope_guard_clinic(
            $cid,
            static  fn() => \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_read_only_guard_disabled($fn),
        );
    
    }

    public static function maestro_run_rule(array $rule, float $deadline): array
    
    {
    
        $cid = (int) ($rule["clinic_id"] ?? 0);
        return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_with_guarded_clinic(
            $cid,
            static  fn() => \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_run_rule_scoped($rule, $deadline),
        );
    
    }

    public static function maestro_run_rule_scoped(array $rule, float $deadline): array
    
    {
    
        $cid = (int) $rule["clinic_id"];
        if (\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_read_only_db($cid)) {
            return ["created" => 0, "seen" => 0, "errors" => 0];
        }
        $started = microtime(true);
        $created = 0;
        $seen = 0;
        $errors = 0;
        $matches = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations03::maestro_fetch_candidates($rule, 20);
        foreach ($matches as $m) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $seen++;
            $source = (string) ($m["source_entity"] ?? "registro");
            $sourceId = (string) ($m["source_entity_id"] ?? "0");
            $actionKey = (string) $rule["action_type"] . ":" . (int) $rule["id"];
            $ins = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "INSERT IGNORE INTO pi_maestro_executions (clinic_id,rule_id,source_entity,source_entity_id,action_key,status,message,executed_at) VALUES (?,?,?,?,?,'running','Em processamento',NOW())",
                [$cid, (int) $rule["id"], $source, $sourceId, $actionKey],
            );
            $claimed = $ins->rowCount() > 0;
            if (!$claimed) {
                $reclaimed = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "UPDATE pi_maestro_executions SET message='Retomada determinística após interrupção', executed_at=NOW() WHERE rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=? AND clinic_id=? AND status='running' AND executed_at<=DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
                    [
                        (int) $rule["id"],
                        $source,
                        $sourceId,
                        $actionKey,
                        $cid,
                    ],
                );
                $claimed = $reclaimed->rowCount() > 0;
            }
            if (!$claimed) {
                continue;
            }
            try {
                \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_begin_transaction();
                $res = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_create_action($rule, $m);
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "UPDATE pi_maestro_executions SET status='created', action_entity=?, action_entity_id=?, message='Ação criada', executed_at=NOW() WHERE rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=? AND clinic_id=?",
                    [
                        $res["entity"] ?? null,
                        $res["id"] ?? null,
                        (int) $rule["id"],
                        $source,
                        $sourceId,
                        $actionKey,
                        $cid,
                    ],
                );
                \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_commit();
                $created++;
            } catch (Throwable $e) {
                if (\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()->inTransaction()) {
                    \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_rollback();
                }
                $errors++;
                \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                    "UPDATE pi_maestro_executions SET status='error', message=?, executed_at=NOW() WHERE rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=? AND clinic_id=?",
                    [
                        mb_substr($e->getMessage(), 0, 240),
                        (int) $rule["id"],
                        $source,
                        $sourceId,
                        $actionKey,
                        $cid,
                    ],
                );
                error_log("[Prontoo Maestro action] " . $e->getMessage());
            }
        }
        \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "UPDATE pi_maestro_rules SET last_run_at=NOW(), next_run_at=DATE_ADD(NOW(), INTERVAL min_interval_minutes MINUTE), run_count=run_count+1, updated_at=NOW() WHERE id=? AND clinic_id=?",
            [(int) $rule["id"], $cid],
        );
        return [
            "created" => $created,
            "seen" => $seen,
            "errors" => $errors,
            "duration_ms" => (int) round((microtime(true) - $started) * 1000, 0, \RoundingMode::HalfAwayFromZero),
        ];
    
    }

    public static function maestro_supervised_with_clinic_timezone(int $clinicId, callable $callback): mixed
    
    {
        $previousTimezone = date_default_timezone_get();
        $hadDisplayTimezone = array_key_exists("PRONTOO_DISPLAY_TIMEZONE", $GLOBALS);
        $previousDisplayTimezone = $GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? null;
        $timezone = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone(null, $clinicId);
        try {
            $GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] = $timezone;
            @date_default_timezone_set($timezone);
            return $callback();
        } finally {
            @date_default_timezone_set($previousTimezone ?: "UTC");
            if ($hadDisplayTimezone) {
                $GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] = $previousDisplayTimezone;
            } else {
                unset($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"]);
            }
        }
    
    }

    public static function maestro_supervised_candidate_result(array $rule, int $limit = 300): array
    
    {
        $tmpDir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("tmp");
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0750, true);
        }
        $tmp = is_dir($tmpDir) && is_writable($tmpDir)
            ? tempnam($tmpDir, ".maestro-candidates-")
            : false;
        $previousLog = ini_get("error_log");
        if (is_string($tmp) && $tmp !== "") {
            @ini_set("log_errors", "1");
            @ini_set("error_log", $tmp);
        }
        try {
            $matches = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations03::maestro_fetch_candidates($rule, max(1, min(300, $limit)));
        } finally {
            if (is_string($previousLog)) {
                @ini_set("error_log", $previousLog);
            }
        }
        $diagnostic = is_string($tmp) && is_file($tmp)
            ? mb_trim((string) @file_get_contents($tmp))
            : "";
        if (is_string($tmp) && is_file($tmp)) {
            @unlink($tmp);
        }
        if (str_contains($diagnostic, "[Prontoo Maestro candidates]")) {
            $line = "";
            foreach (preg_split('/\R/u', $diagnostic) ?: [] as $candidate) {
                if (str_contains((string) $candidate, "[Prontoo Maestro candidates]")) {
                    $line = mb_trim((string) $candidate);
                    break;
                }
            }
            throw new RuntimeException(
                mb_substr($line !== "" ? $line : "Falha ao consultar candidatos da rotina.", 0, 240),
            );
        }
        return is_array($matches) ? $matches : [];
    
    }

    public static function maestro_supervised_execution_rows(
        int $clinicId,
        int $ruleId,
        string $actionKey,
        array $matches,
    ): array 
    {
        $ids = [];
        foreach ($matches as $match) {
            $id = (string) ($match["source_entity_id"] ?? "0");
            if ($id !== "") {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }
        $values = array_keys($ids);
        $placeholders = implode(",", array_fill(0, count($values), "?"));
        $params = array_merge([$clinicId, $ruleId, $actionKey], $values);
        $rows = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "SELECT source_entity,source_entity_id,status,message,executed_at FROM pi_maestro_executions WHERE clinic_id=? AND rule_id=? AND action_key=? AND source_entity_id IN ($placeholders)",
            $params,
        )->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[\Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_supervised_execution_key(
                (string) ($row["source_entity"] ?? ""),
                (string) ($row["source_entity_id"] ?? ""),
            )] = $row;
        }
        return $map;
    
    }

    public static function maestro_supervised_claim_execution(
        array $rule,
        array $match,
        ?array $existing,
    ): array 
    {
        $clinicId = (int) $rule["clinic_id"];
        $ruleId = (int) $rule["id"];
        $source = (string) ($match["source_entity"] ?? "registro");
        $sourceId = (string) ($match["source_entity_id"] ?? "0");
        $actionKey = (string) $rule["action_type"] . ":" . $ruleId;
        if (!$existing) {
            $insert = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "INSERT IGNORE INTO pi_maestro_executions (clinic_id,rule_id,source_entity,source_entity_id,action_key,status,message,executed_at) VALUES (?,?,?,?,?,'running','attempt:0;Em processamento',NOW())",
                [$clinicId, $ruleId, $source, $sourceId, $actionKey],
            );
            return [
                "claimed" => $insert->rowCount() > 0,
                "attempt" => 0,
                "terminal" => false,
            ];
        }
        $status = (string) ($existing["status"] ?? "");
        if ($status === "created") {
            return ["claimed" => false, "attempt" => 0, "terminal" => false];
        }
        $retry = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_supervised_execution_retry_state($existing);
        if ($retry["terminal"]) {
            return [
                "claimed" => false,
                "attempt" => (int) $retry["attempt"],
                "terminal" => true,
            ];
        }
        if ($status === "running") {
            $executedAt = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($existing["executed_at"] ?? null);
            if ($executedAt > 0 && $executedAt > time() - 900) {
                return [
                    "claimed" => false,
                    "attempt" => (int) $retry["attempt"],
                    "terminal" => false,
                ];
            }
            $update = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
                "UPDATE pi_maestro_executions SET message=?,executed_at=NOW() WHERE clinic_id=? AND rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=? AND status='running'",
                [
                    "attempt:" . (int) $retry["attempt"] . ";Retomada determinística",
                    $clinicId,
                    $ruleId,
                    $source,
                    $sourceId,
                    $actionKey,
                ],
            );
            return [
                "claimed" => $update->rowCount() > 0,
                "attempt" => (int) $retry["attempt"],
                "terminal" => false,
            ];
        }
        if ($status !== "error") {
            return ["claimed" => false, "attempt" => 0, "terminal" => false];
        }
        $nextAt = (int) $retry["next_at"];
        if ($nextAt <= 0) {
            $executedAt = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($existing["executed_at"] ?? null);
            $nextAt = $executedAt > 0 ? $executedAt + 1800 : 0;
        }
        if ($nextAt > time()) {
            return [
                "claimed" => false,
                "attempt" => (int) $retry["attempt"],
                "terminal" => false,
            ];
        }
        $update = \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "UPDATE pi_maestro_executions SET status='running',message=?,executed_at=NOW() WHERE clinic_id=? AND rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=? AND status='error'",
            [
                "attempt:" . (int) $retry["attempt"] . ";Nova tentativa supervisionada",
                $clinicId,
                $ruleId,
                $source,
                $sourceId,
                $actionKey,
            ],
        );
        return [
            "claimed" => $update->rowCount() > 0,
            "attempt" => (int) $retry["attempt"],
            "terminal" => false,
        ];
    
    }

    public static function maestro_supervised_target_assert(array $rule): void
    
    {
        $clinicId = (int) $rule["clinic_id"];
        $action = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_decode_json($rule["action_json"] ?? "");
        $scope = (string) ($action["target_scope"] ?? "clinic");
        if ($scope === "clinic") {
            return;
        }
        if ($scope === "role") {
            $role = mb_trim((string) ($action["target_role"] ?? ""));
            $roles = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_options($clinicId, true);
            if ($role === "" || !array_key_exists($role, $roles)) {
                throw new RuntimeException("Destinatário da rotina inválido: cargo não disponível.");
            }
            $recipients = is_callable([\Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::class, 'team_user_ids_for_roles'])
                ? \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::team_user_ids_for_roles($clinicId, [$role])
                : [];
            if ($recipients === []) {
                throw new RuntimeException("Destinatário da rotina indisponível: cargo sem colaborador ativo.");
            }
            return;
        }
        if ($scope === "user") {
            $userId = (int) ($action["target_user_id"] ?? 0);
            if ($userId <= 0 || !\Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::clinic_user_exists($clinicId, $userId)) {
                throw new RuntimeException("Destinatário da rotina inválido: pessoa não está ativa no consultório.");
            }
            return;
        }
        throw new RuntimeException("Escopo de destinatário inválido para a rotina.");
    
    }

    public static function maestro_supervised_action_failure(
        array $rule,
        array $match,
        int $previousAttempt,
        Throwable $error,
    ): void 
    {
        $attempt = max(1, $previousAttempt + 1);
        $terminal = $attempt >= 5;
        $nextAt = $terminal ? 0 : time() + \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_supervised_retry_delay_seconds($attempt);
        $message = ($terminal ? "terminal;" : "retry;") .
            "attempt:" . $attempt .
            ";next:" . $nextAt .
            ";error:" . mb_substr(preg_replace('/\s+/u', " ", trim($error->getMessage())) ?: "erro", 0, 170);
        \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::q(
            "UPDATE pi_maestro_executions SET status='error',message=?,executed_at=NOW() WHERE clinic_id=? AND rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=?",
            [
                $message,
                (int) $rule["clinic_id"],
                (int) $rule["id"],
                (string) ($match["source_entity"] ?? "registro"),
                (string) ($match["source_entity_id"] ?? "0"),
                (string) $rule["action_type"] . ":" . (int) $rule["id"],
            ],
        );
    
    }
}
