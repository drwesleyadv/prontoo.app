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

final class MaestroRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function maestro_supervised_run_rule(array $rule, float $deadline): array
    
    {
        $started = microtime(true);
        $clinicId = (int) ($rule["clinic_id"] ?? 0);
        $ruleId = (int) ($rule["id"] ?? 0);
        $result = [
            "created" => 0,
            "seen" => 0,
            "errors" => 0,
            "retrying" => 0,
            "terminal" => 0,
            "skipped_existing" => 0,
            "skipped_read_only" => 0,
            "candidate_window_saturated" => false,
            "changed_categories" => [],
            "duration_ms" => 0,
        ];
        if ($clinicId <= 0 || $ruleId <= 0) {
            $result["errors"] = 1;
            return $result;
        }
        return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_with_guarded_clinic(
            $clinicId,
            static function () use ($rule, $deadline, $started, $clinicId, $ruleId, $result): array {
                if (\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_read_only_db($clinicId)) {
                    \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_supervised_run_rule.01', [$ruleId, $clinicId], []);
                    $result["skipped_read_only"] = 1;
                    $result["duration_ms"] = (int) round((microtime(true) - $started) * 1000, 0, \RoundingMode::HalfAwayFromZero);
                    return $result;
                }
                try {
                    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_target_assert($rule);
                    $matches = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_with_clinic_timezone(
                        $clinicId,
                        static fn(): array => \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_candidate_result($rule, 300),
                    );
                    $result["candidate_window_saturated"] = count($matches) >= 300;
                    $actionKey = (string) $rule["action_type"] . ":" . $ruleId;
                    $existing = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_execution_rows(
                        $clinicId,
                        $ruleId,
                        $actionKey,
                        $matches,
                    );
                    $pending = [];
                    foreach ($matches as $match) {
                        $key = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_supervised_execution_key(
                            (string) ($match["source_entity"] ?? "registro"),
                            (string) ($match["source_entity_id"] ?? "0"),
                        );
                        $row = $existing[$key] ?? null;
                        if ((string) ($row["status"] ?? "") === "created") {
                            $result["skipped_existing"]++;
                            continue;
                        }
                        $pending[] = [$match, $row];
                    }
                    foreach (array_slice($pending, 0, 20) as [$match, $row]) {
                        if (microtime(true) >= $deadline) {
                            break;
                        }
                        $result["seen"]++;
                        $claim = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_claim_execution($rule, $match, $row);
                        if (!empty($claim["terminal"])) {
                            $result["terminal"]++;
                            continue;
                        }
                        if (empty($claim["claimed"])) {
                            continue;
                        }
                        if ((int) ($claim["attempt"] ?? 0) > 0) {
                            $result["retrying"]++;
                        }
                        try {
                            \Prontoo\Runtime\Operational\OperationalComposition::maestroCommands()
                                ->createSupervisedAction(
                                    $clinicId,
                                    $rule,
                                    $match,
                                    $ruleId,
                                    $actionKey,
                                    static fn(int $currentClinicId, array $currentRule, array $currentMatch): array =>
                                        \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_with_clinic_timezone(
                                            $currentClinicId,
                                            static fn(): array => \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_create_action(
                                                $currentRule,
                                                $currentMatch,
                                            ),
                                        ),
                                );
                            $result["created"]++;
                            $result["changed_categories"][] =
                                (string) $rule["action_type"] === "create_notice"
                                    ? "notices"
                                    : "tasks";
                        } catch (Throwable $error) {
                            $result["errors"]++;
                            \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_action_failure(
                                $rule,
                                $match,
                                (int) ($claim["attempt"] ?? 0),
                                $error,
                            );
                            error_log("[Prontoo Maestro action] " . $error->getMessage());
                        }
                    }
                    \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_supervised_run_rule.03', [$ruleId, $clinicId], []);
                } catch (Throwable $error) {
                    $result["errors"]++;
                    \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_supervised_run_rule.04', [$ruleId, $clinicId], []);
                    error_log("[Prontoo Maestro supervised rule] " . $error->getMessage());
                }
                $result["changed_categories"] = array_values(array_unique(
                    $result["changed_categories"],
                ));
                $result["duration_ms"] = (int) round((microtime(true) - $started) * 1000, 0, \RoundingMode::HalfAwayFromZero);
                return $result;
            },
        );
    
    }

    public static function maestro_supervised_fair_rules(array $rules, array $stats, int $limit = 80): array
    
    {
        $queues = [];
        foreach ($rules as $rule) {
            $clinicId = (int) ($rule["clinic_id"] ?? 0);
            if ($clinicId > 0) {
                $queues[$clinicId][] = $rule;
            }
        }
        foreach ($queues as $clinicId => $clinicRules) {
            usort($clinicRules, static function (array $left, array $right) use ($stats): int {
                return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_rule_score(
                    $right,
                    $stats[\Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_routine_key($right)] ?? null,
                ) <=> \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_rule_score(
                    $left,
                    $stats[\Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_routine_key($left)] ?? null,
                );
            });
            $queues[$clinicId] = array_values($clinicRules);
        }
        ksort($queues, SORT_NUMERIC);
        $selected = [];
        while ($queues !== [] && count($selected) < $limit) {
            foreach (array_keys($queues) as $clinicId) {
                if (count($selected) >= $limit) {
                    break 2;
                }
                $rule = array_shift($queues[$clinicId]);
                if (is_array($rule)) {
                    $selected[] = $rule;
                }
                if ($queues[$clinicId] === []) {
                    unset($queues[$clinicId]);
                }
            }
        }
        return $selected;
    
    }

    public static function maestro_supervised_record_job_run(float $startedAt, array $result): void
    
    {
        try {
            \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_supervised_record_job_run.01', [
                    $startedAt,
                    (int) ($result["duration_ms"] ?? 0),
                    (int) ($result["rules_seen"] ?? 0),
                    (int) ($result["rules_run"] ?? 0),
                    (int) ($result["actions_created"] ?? 0),
                    (int) ($result["deferred"] ?? 0),
                    (int) ($result["errors"] ?? 0),
                    !empty($result["success"]) ? 1 : 0,
                    (float) ($result["load_score"] ?? 0),
                    mb_substr((string) ($result["note"] ?? ""), 0, 255),
                ], []);
        } catch (Throwable $error) {
            error_log("[Prontoo Maestro job run] " . $error->getMessage());
        }
    
    }

    public static function maestro_supervised_cron_run(
        int $budgetMs = PRONTOO_MAESTRO_CRON_BUDGET_MS,
    ): array 
    {
        $startedAt = microtime(true);
        $result = [
            "success" => true,
            "status" => "healthy",
            "rules_seen" => 0,
            "rules_run" => 0,
            "actions_created" => 0,
            "deferred" => 0,
            "errors" => 0,
            "retrying" => 0,
            "terminal" => 0,
            "read_only" => 0,
            "candidate_window_saturated" => 0,
            "changed_categories" => [],
            "duration_ms" => 0,
            "load_score" => 0,
            "note" => "ok",
        ];
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            $result["note"] = "sem configuração";
            return $result;
        }
        $maxCycleSeconds = max(30, PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES * 60 - 30);
        $deadline = $startedAt + max(5, min($maxCycleSeconds, $budgetMs / 1000));
        $lockPath = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("maestro.lock");
        $lock = @fopen($lockPath, "c");
        if (!$lock) {
            $result["success"] = false;
            $result["status"] = "unavailable";
            $result["errors"] = 1;
            $result["note"] = "falha: arquivo de lock indisponível";
            $result["duration_ms"] = (int) round((microtime(true) - $startedAt) * 1000, 0, \RoundingMode::HalfAwayFromZero);
            \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_record_job_run($startedAt, $result);
            return $result;
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $result["status"] = "overlap";
            $result["note"] = "execução anterior em andamento";
            $result["duration_ms"] = (int) round((microtime(true) - $startedAt) * 1000, 0, \RoundingMode::HalfAwayFromZero);
            \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_record_job_run($startedAt, $result);
            return $result;
        }
        try {
            $rules = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_supervised_cron_run.01', [], [])->fetchAll();
            $stats = [];
            if ($rules !== []) {
                $keys = array_values(array_unique(array_map([\Prontoo\Domain\Maestro\MaestroDomainOperations02::class, "maestro_routine_key"], $rules)));
                foreach (
                    \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_supervised_cron_run.02', $keys, ['itemCount' => count($keys)])->fetchAll()
                    as $row
                ) {
                    $stats[(string) $row["routine_key"]] = $row;
                }
            }
            $rules = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_fair_rules($rules, $stats, 80);
            $result["rules_seen"] = count($rules);
            foreach ($rules as $rule) {
                if (\Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_supervised_remaining_ms($deadline) < 1000) {
                    $result["deferred"]++;
                    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_stats_update(
                        \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_routine_key($rule),
                        0,
                        0,
                        \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_rule_score(
                            $rule,
                            $stats[\Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_routine_key($rule)] ?? null,
                        ),
                        true,
                    );
                    continue;
                }
                $key = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_routine_key($rule);
                $score = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_rule_score($rule, $stats[$key] ?? null);
                $expected = max(80.0, (float) ($stats[$key]["ewma_duration_ms"] ?? 80));
                if ($expected > \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_supervised_remaining_ms($deadline) && $score < 90) {
                    $result["deferred"]++;
                    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_stats_update($key, 0, 0, $score, true);
                    continue;
                }
                $ruleResult = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_run_rule($rule, $deadline);
                $result["rules_run"]++;
                $result["actions_created"] += (int) ($ruleResult["created"] ?? 0);
                $result["errors"] += (int) ($ruleResult["errors"] ?? 0);
                $result["retrying"] += (int) ($ruleResult["retrying"] ?? 0);
                $result["terminal"] += (int) ($ruleResult["terminal"] ?? 0);
                $result["read_only"] += (int) ($ruleResult["skipped_read_only"] ?? 0);
                $result["candidate_window_saturated"] += !empty(
                    $ruleResult["candidate_window_saturated"]
                ) ? 1 : 0;
                $result["changed_categories"] = array_merge(
                    $result["changed_categories"],
                    (array) ($ruleResult["changed_categories"] ?? []),
                );
                \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_stats_update(
                    $key,
                    (float) ($ruleResult["duration_ms"] ?? 0),
                    (int) ($ruleResult["created"] ?? 0),
                    $score,
                    false,
                );
            }
        } catch (Throwable $error) {
            $result["errors"]++;
            $result["note"] = "falha: " . mb_substr($error->getMessage(), 0, 232);
            error_log("[Prontoo Maestro supervised cron] " . $error->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $result["success"] = $result["errors"] === 0;
        $result["status"] = $result["success"]
            ? (($result["terminal"] > 0 || $result["candidate_window_saturated"] > 0)
                ? "attention"
                : "healthy")
            : "failed";
        if ($result["errors"] > 0 && $result["note"] === "ok") {
            $result["note"] = "falha: " . $result["errors"] . " erro(s) em rotinas";
        }
        $result["changed_categories"] = array_values(array_unique(array_merge(
            $result["changed_categories"],
            $result["actions_created"] > 0 ? ["hot", "dashboard"] : [],
        )));
        if (
            $result["changed_categories"] !== [] &&
            is_callable([\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::class, 'server_json_cache_clear_categories'])
        ) {
            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories($result["changed_categories"]);
        }
        $result["duration_ms"] = (int) round((microtime(true) - $startedAt) * 1000, 0, \RoundingMode::HalfAwayFromZero);
        $result["load_score"] = $result["duration_ms"] > 0
            ? round($result["actions_created"] / max(1, $result["duration_ms"] / 1000), 3, \RoundingMode::HalfAwayFromZero)
            : 0;
        \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_record_job_run($startedAt, $result);
        return $result;
    
    }

    public static function maestro_cron_run(int $budgetMs = PRONTOO_MAESTRO_CRON_BUDGET_MS): array
    
    {
    
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return [
                "success" => true,
                "rules_seen" => 0,
                "rules_run" => 0,
                "actions_created" => 0,
                "deferred" => 0,
                "errors" => 0,
                "note" => "sem configuração",
            ];
        }
        \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_ensure_schema();
        $start = microtime(true);
        $maxCycleSeconds = max(30, PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES * 60 - 30);
        $deadline = $start + max(5, min($maxCycleSeconds, $budgetMs / 1000));
        $lockPath = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("maestro.lock");
        $fh = @fopen($lockPath, "c");
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            return [
                "success" => true,
                "rules_seen" => 0,
                "rules_run" => 0,
                "actions_created" => 0,
                "deferred" => 0,
                "errors" => 0,
                "note" => "execução anterior em andamento",
            ];
        }
        $seen = 0;
        $run = 0;
        $created = 0;
        $deferred = 0;
        $errors = 0;
        $success = true;
        $note = "ok";
        try {
            $rules = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_cron_run.01', [], [])->fetchAll();
            $seen = count($rules);
            $stats = [];
            if ($rules) {
                $keys = array_map([\Prontoo\Domain\Maestro\MaestroDomainOperations02::class, "maestro_routine_key"], $rules);
                foreach (
                    \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_cron_run.02', $keys, ['itemCount' => count($keys)])->fetchAll()
                    as $s
                ) {
                    $stats[(string) $s["routine_key"]] = $s;
                }
                usort($rules, function ($a, $b) use ($stats) {
    
                    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_rule_score(
                        $b,
                        $stats[\Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_routine_key($b)] ?? null,
                    ) <=>
                        \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_rule_score(
                            $a,
                            $stats[\Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_routine_key($a)] ?? null,
                        );
                });
            }
            foreach ($rules as $rule) {
                if (microtime(true) >= $deadline) {
                    $deferred++;
                    continue;
                }
                $key = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_routine_key($rule);
                $score = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_rule_score($rule, $stats[$key] ?? null);
                $remainingMs = max(0, ($deadline - microtime(true)) * 1000);
                $expected = (float) ($stats[$key]["ewma_duration_ms"] ?? 80);
                if ($expected > $remainingMs && $score < 90) {
                    $deferred++;
                    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_with_guarded_clinic(
                        (int) $rule["clinic_id"],
                        static  fn() => \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_cron_run.03', [(int) $rule["id"], (int) $rule["clinic_id"]], []),
                    );
                    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_stats_update($key, 0, 0, $score, true);
                    continue;
                }
                $r = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_run_rule($rule, $deadline);
                $run++;
                $created += (int) $r["created"];
                $errors += (int) ($r["errors"] ?? 0);
                \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_stats_update(
                    $key,
                    (float) ($r["duration_ms"] ?? 0),
                    (int) $r["created"],
                    $score,
                    false,
                );
            }
            if ($errors > 0) {
                $success = false;
                $note = "falha: " . $errors . " erro(s) em ações das rotinas";
            }
        } catch (Throwable $e) {
            $success = false;
            $errors++;
            $note = "falha: " . mb_substr($e->getMessage(), 0, 232);
            error_log("[Prontoo Maestro cron] " . $e->getMessage());
        } finally {
            if ($fh) {
                flock($fh, LOCK_UN);
                fclose($fh);
            }
        }
        $duration = (int) round((microtime(true) - $start) * 1000, 0, \RoundingMode::HalfAwayFromZero);
        \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_cron_run.04', [
                $start,
                $duration,
                $seen,
                $run,
                $created,
                $deferred,
                $errors,
                $success ? 1 : 0,
                $duration > 0 ? round($created / max(1, $duration / 1000), 3, \RoundingMode::HalfAwayFromZero) : 0,
                $note,
            ], []);
        return [
            "success" => $success,
            "rules_seen" => $seen,
            "rules_run" => $run,
            "actions_created" => $created,
            "deferred" => $deferred,
            "errors" => $errors,
            "duration_ms" => $duration,
            "note" => $note,
        ];
    
    }

    public static function maestro_record_cron_failure(float $startedAt, string $note): void
    
    {
    
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        try {
            \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_ensure_schema();
            $note =
                "falha: " .
                ltrim(
                    mb_substr(
                        preg_replace("/\s+/u", " ", trim($note)) ?:
                        "erro não informado",
                        0,
                        232,
                    ),
                );
            $duration = (int) max(0, round((microtime(true) - $startedAt) * 1000, 0, \RoundingMode::HalfAwayFromZero));
            \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.05.maestro_record_cron_failure.01', [$startedAt, $duration, $note], []);
        } catch (Throwable $e) {
            error_log("[Prontoo cron failure record] " . $e->getMessage());
        }
    
    }
}
