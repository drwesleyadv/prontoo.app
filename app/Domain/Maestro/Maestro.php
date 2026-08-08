<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
function maestro_global_physical_rollback(): void
{
    \Prontoo\Infrastructure\Maestro\MaestroInfrastructureOperations01::maestro_global_physical_rollback();
}
function maestro_runtime_access_marker_ready(): bool
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_runtime_access_marker_ready();
}
function maestro_ensure_schema(): void
{
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_ensure_schema();
}

function maestro_grant_runtime_access(): void
{
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_grant_runtime_access();
}
function maestro_runtime_upgrade(): void
{
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_runtime_upgrade();
}
function maestro_trigger_catalog(): array
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations01::maestro_trigger_catalog();
}
function maestro_action_types(): array
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations01::maestro_action_types();
}
function maestro_module_label(string $module): string
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_module_label($module);
}
function maestro_json(array $data): string
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_json($data);
}
function maestro_decode_json(mixed $raw): array
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_decode_json($raw);
}
function maestro_text(string $s, int $max = 180): string
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_text($s, $max);
}
function maestro_template(string $s, int $max = 900): string
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_template($s, $max);
}
function maestro_days(mixed $v, int $default = 1): int
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_days($v, $default);
}
function maestro_amount(mixed $v, int $default = 1, string $unit = "days"): int
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_amount($v, $default, $unit);
}
function maestro_priority(mixed $v): int
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_priority($v);
}
function maestro_local_day(int $clinicId, int $offsetDays = 0): string
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day($clinicId, $offsetDays);
}
function maestro_local_day_utc_range(int $clinicId, string $day): array
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_local_day_utc_range($clinicId, $day);
}
function maestro_due_dt(int $offsetDays = 0, int $clinicId = 0): ?string
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_due_dt($offsetDays, $clinicId);
}
function maestro_apply_placeholders(string $template, array $vars): string
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_apply_placeholders($template, $vars);
}
function maestro_actor_id(): ?int
{
    return \Prontoo\Presentation\Maestro\MaestroPresentationOperations01::maestro_actor_id();
}
function maestro_save_rule(array $c): void
{
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_save_rule($c);
}
function maestro_module_options(array $catalog): array
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_module_options($catalog);
}
function maestro_trigger_option_html(
    array $catalog,
    string $selected = "appointment_before_start",
): string {
    return \Prontoo\Presentation\Maestro\MaestroPresentationOperations01::maestro_trigger_option_html($catalog, $selected);
}
function maestro_duration_label(int $ms): string
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_duration_label($ms);
}
function maestro_unit_label(
    string $unit,
    int $amount,
    bool $short = false,
): string {
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_unit_label($unit, $amount, $short);
}
function maestro_module_icon(string $module): string
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_module_icon($module);
}
function maestro_action_icon(string $action): string
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_action_icon($action);
}
function maestro_target_label(array $act, int $cid): string
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_target_label($act, $cid);
}
function maestro_last_label(?string $value): string
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_last_label($value);
}
function maestro_next_label(?string $value): string
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_next_label($value);
}
function page_maestro(): void
{
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations02::page_maestro();
}
function maestro_match_base(array $vars, array $extra = []): array
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_match_base($vars, $extra);
}
function maestro_fetch_candidates(array $rule, int $limit = 120): array
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations03::maestro_fetch_candidates($rule, $limit);
}
function maestro_create_action(array $rule, array $match): array
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_create_action($rule, $match);
}
function maestro_routine_key(array $rule): string
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_routine_key($rule);
}
function maestro_rule_score(array $rule, ?array $stat = null): float
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_rule_score($rule, $stat);
}
function maestro_ewma_observation(
    ?float $previous,
    float $observation,
    bool $skipped = false,
    float $alpha = 0.25,
): ?float {
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_ewma_observation($previous, $observation, $skipped, $alpha);
}
function maestro_stats_update(
    string $key,
    float $duration,
    int $created,
    float $score,
    bool $skipped = false,
): void {
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_stats_update($key, $duration, $created, $score, $skipped);
}
function maestro_with_guarded_clinic(int $cid, callable $fn): mixed
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_with_guarded_clinic($cid, $fn);
}
function maestro_run_rule(array $rule, float $deadline): array
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_run_rule($rule, $deadline);
}
function maestro_run_rule_scoped(array $rule, float $deadline): array
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_run_rule_scoped($rule, $deadline);
}
function maestro_supervised_remaining_ms(float $deadline): int
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_supervised_remaining_ms($deadline);
}

function maestro_supervised_with_clinic_timezone(int $clinicId, callable $callback): mixed
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_with_clinic_timezone($clinicId, $callback);
}

function maestro_supervised_candidate_result(array $rule, int $limit = 300): array
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_candidate_result($rule, $limit);
}

function maestro_supervised_execution_key(string $entity, string $id): string
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_supervised_execution_key($entity, $id);
}

function maestro_supervised_execution_rows(
    int $clinicId,
    int $ruleId,
    string $actionKey,
    array $matches,
): array {
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_execution_rows($clinicId, $ruleId, $actionKey, $matches);
}

function maestro_supervised_execution_retry_state(?array $row): array
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_supervised_execution_retry_state($row);
}

function maestro_supervised_retry_delay_seconds(int $attempt): int
{
    return \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_supervised_retry_delay_seconds($attempt);
}

function maestro_supervised_claim_execution(
    array $rule,
    array $match,
    ?array $existing,
): array {
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_claim_execution($rule, $match, $existing);
}

function maestro_supervised_target_assert(array $rule): void
{
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_target_assert($rule);
}

function maestro_supervised_action_failure(
    array $rule,
    array $match,
    int $previousAttempt,
    Throwable $error,
): void {
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations04::maestro_supervised_action_failure($rule, $match, $previousAttempt, $error);
}

function maestro_supervised_run_rule(array $rule, float $deadline): array
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_run_rule($rule, $deadline);
}

function maestro_supervised_fair_rules(array $rules, array $stats, int $limit = 80): array
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_fair_rules($rules, $stats, $limit);
}

function maestro_supervised_record_job_run(float $startedAt, array $result): void
{
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_record_job_run($startedAt, $result);
}

function maestro_supervised_cron_run(
    int $budgetMs = PRONTOO_MAESTRO_CRON_BUDGET_MS,
): array {
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_supervised_cron_run($budgetMs);
}

function maestro_cron_run(int $budgetMs = PRONTOO_MAESTRO_CRON_BUDGET_MS): array
{
    return \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_cron_run($budgetMs);
}
function maestro_record_cron_failure(float $startedAt, string $note): void
{
    \Prontoo\Runtime\Maestro\MaestroRuntimeOperations05::maestro_record_cron_failure($startedAt, $note);
}
