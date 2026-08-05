from __future__ import annotations

import hashlib
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SCRIPT = ROOT / ".agent-maestro-hardening.py"
WORKFLOW = ROOT / ".github/workflows/agent-maestro-hardening.yml"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_exact(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise RuntimeError(f"Trecho {label} encontrado {count} vez(es)")
    return source.replace(old, new, 1)


maestro = read("app/Domain/Maestro/Maestro.php")
maestro = replace_exact(
    maestro,
    '''function maestro_due_dt(int $offsetDays = 0): ?string
{

    $offsetDays = max(0, min(365, $offsetDays));
    return date("Y-m-d 17:00:00", strtotime("+" . $offsetDays . " days"));
}
''',
    '''function maestro_local_day(int $clinicId, int $offsetDays = 0): string
{
    $offsetDays = max(-365, min(365, $offsetDays));
    $modifier = ($offsetDays >= 0 ? "+" : "") . $offsetDays . " days";
    return app_now_in_timezone($clinicId)
        ->setTime(0, 0, 0)
        ->modify($modifier)
        ->format("Y-m-d");
}
function maestro_local_day_utc_range(int $clinicId, string $day): array
{
    [$start, $end] = app_local_day_utc_range($day, $clinicId);
    return [
        gmdate("Y-m-d H:i:s", (int) $start),
        gmdate("Y-m-d H:i:s", (int) $end),
    ];
}
function maestro_due_dt(int $offsetDays = 0, int $clinicId = 0): ?string
{
    $offsetDays = max(0, min(365, $offsetDays));
    if ($clinicId <= 0) {
        return gmdate("Y-m-d 17:00:00", strtotime("+" . $offsetDays . " days UTC"));
    }
    $local = new DateTimeImmutable(
        maestro_local_day($clinicId, $offsetDays) . " 17:00:00",
        new DateTimeZone(app_context_timezone(null, $clinicId)),
    );
    return $local
        ->setTimezone(new DateTimeZone("UTC"))
        ->format("Y-m-d H:i:s");
}
''',
    "maestro_due_dt",
)
maestro = replace_exact(
    maestro,
    '''    if (!isset($actions[$action])) {
        $action = "create_task";
    }
''',
    '''    if (!isset($actions[$action])) {
        throw new RuntimeException("Ação da rotina inválida.");
    }
''',
    "ação inválida",
)
maestro = replace_exact(
    maestro,
    '''    $targetScope = (string) ($_POST["target_scope"] ?? "role");
    if (!in_array($targetScope, ["clinic", "role", "user"], true)) {
        $targetScope = "clinic";
    }
    $targetRole =
        (string) ($_POST["target_role"] ??
            ($item["default_target_role"] ?? "recepcionista"));
    $roleOpts = clinic_role_options($cid, true);
    if ($targetScope === "role" && !isset($roleOpts[$targetRole])) {
        $targetRole = array_key_first($roleOpts) ?: "recepcionista";
    }
    $targetUserId = max(0, (int) ($_POST["target_user_id"] ?? 0));
    if ($targetScope === "user" && !clinic_user_exists($cid, $targetUserId)) {
        $targetScope = "clinic";
    }
''',
    '''    $targetScope = (string) ($_POST["target_scope"] ?? "role");
    if (!in_array($targetScope, ["clinic", "role", "user"], true)) {
        throw new RuntimeException("Escopo de destinatário inválido.");
    }
    $targetRole =
        (string) ($_POST["target_role"] ??
            ($item["default_target_role"] ?? "recepcionista"));
    $roleOpts = clinic_role_options($cid, true);
    if ($targetScope === "role" && !isset($roleOpts[$targetRole])) {
        throw new RuntimeException("Cargo destinatário inválido para o consultório.");
    }
    $targetUserId = max(0, (int) ($_POST["target_user_id"] ?? 0));
    if ($targetScope === "user" && !clinic_user_exists($cid, $targetUserId)) {
        throw new RuntimeException("Pessoa destinatária inválida para o consultório.");
    }
''',
    "destinatário no salvamento",
)
maestro = replace_exact(
    maestro,
    '''        if ($trigger === "appointment_before_start") {
            $start = date("Y-m-d 00:00:00", strtotime("+" . $amount . " days"));
            $end = date(
                "Y-m-d 00:00:00",
                strtotime("+" . ($amount + 1) . " days"),
            );
''',
    '''        if ($trigger === "appointment_before_start") {
            $day = maestro_local_day($cid, $amount);
            [$start, $end] = maestro_local_day_utc_range($cid, $day);
''',
    "janela de consulta futura",
)
maestro = replace_exact(
    maestro,
    '''        } elseif ($trigger === "appointment_today_without_patient") {
            $rows = q(
                "SELECT id,start_at,reason FROM pi_appointments WHERE clinic_id=? AND DATE(start_at)=CURDATE() AND patient_link_id IS NULL AND status<>'cancelado' ORDER BY start_at ASC LIMIT $limit",
                [$cid],
            )->fetchAll();
''',
    '''        } elseif ($trigger === "appointment_today_without_patient") {
            [$start, $end] = maestro_local_day_utc_range(
                $cid,
                maestro_local_day($cid),
            );
            $rows = q(
                "SELECT id,start_at,reason FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<? AND patient_link_id IS NULL AND status<>'cancelado' ORDER BY start_at ASC LIMIT $limit",
                [$cid, $start, $end],
            )->fetchAll();
''',
    "consulta de hoje",
)
maestro = replace_exact(
    maestro,
    '''        } elseif ($trigger === "lead_next_action_before_days") {
            $day = date("Y-m-d", strtotime("+" . $amount . " days"));
            $rows = q(
                "SELECT id,name,next_action_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NOT NULL AND DATE(next_action_at)=? ORDER BY next_action_at ASC LIMIT $limit",
                [$cid, $day],
            )->fetchAll();
''',
    '''        } elseif ($trigger === "lead_next_action_before_days") {
            $day = maestro_local_day($cid, $amount);
            [$start, $end] = maestro_local_day_utc_range($cid, $day);
            $rows = q(
                "SELECT id,name,next_action_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NOT NULL AND next_action_at>=? AND next_action_at<? ORDER BY next_action_at ASC LIMIT $limit",
                [$cid, $start, $end],
            )->fetchAll();
''',
    "retorno de interessado",
)
maestro = replace_exact(
    maestro,
    '''                $occ = $ts ? date("Ymd", $ts) : str_replace("-", "", $day);
''',
    '''                $occ = str_replace("-", "", $day);
''',
    "ocorrência local de interessado",
)
maestro = replace_exact(
    maestro,
    '''        } elseif ($trigger === "patient_birthday_before_days") {
            $target = date("Y-m-d", strtotime("+" . $amount . " days"));
            $md = date("m-d", strtotime($target));
            $year = date("Y", strtotime($target));
''',
    '''        } elseif ($trigger === "patient_birthday_before_days") {
            $target = maestro_local_day($cid, $amount);
            $md = substr($target, 5, 5);
            $year = substr($target, 0, 4);
''',
    "aniversário local",
)
maestro = replace_exact(
    maestro,
    '''                        "data" => $birthTs
                            ? date("d/m", $birthTs) . "/" . $year
                            : date("d/m/Y", strtotime($target)),
''',
    '''                        "data" => substr($target, 8, 2) .
                            "/" .
                            substr($target, 5, 2) .
                            "/" .
                            $year,
''',
    "exibição de aniversário",
)
maestro = replace_exact(
    maestro,
    '''        } elseif ($trigger === "document_issued_after_days") {
            $day = date("Y-m-d", strtotime("-" . $amount . " days"));
            $rows = q(
                "SELECT id,title,patient_link_id,issued_at,document_identifier FROM pi_documents WHERE clinic_id=? AND document_status='emitido' AND DATE(issued_at)=? ORDER BY issued_at DESC LIMIT $limit",
                [$cid, $day],
            )->fetchAll();
''',
    '''        } elseif ($trigger === "document_issued_after_days") {
            $day = maestro_local_day($cid, -$amount);
            [$start, $end] = maestro_local_day_utc_range($cid, $day);
            $rows = q(
                "SELECT id,title,patient_link_id,issued_at,document_identifier FROM pi_documents WHERE clinic_id=? AND document_status='emitido' AND issued_at>=? AND issued_at<? ORDER BY issued_at DESC LIMIT $limit",
                [$cid, $start, $end],
            )->fetchAll();
''',
    "documento emitido no dia local",
)
maestro = replace_exact(
    maestro,
    '''        } elseif ($trigger === "task_due_in_days") {
            $day = date("Y-m-d", strtotime("+" . $amount . " days"));
            $rows = q(
                "SELECT id,title,due_at FROM pi_tasks WHERE clinic_id=? AND status IN ($activeTask) AND due_at IS NOT NULL AND DATE(due_at)=? ORDER BY due_at ASC LIMIT $limit",
                [$cid, $day],
            )->fetchAll();
''',
    '''        } elseif ($trigger === "task_due_in_days") {
            $day = maestro_local_day($cid, $amount);
            [$start, $end] = maestro_local_day_utc_range($cid, $day);
            $rows = q(
                "SELECT id,title,due_at FROM pi_tasks WHERE clinic_id=? AND status IN ($activeTask) AND due_at IS NOT NULL AND due_at>=? AND due_at<? ORDER BY due_at ASC LIMIT $limit",
                [$cid, $start, $end],
            )->fetchAll();
''',
    "tarefa com prazo local",
)
maestro = replace_exact(
    maestro,
    '''        } elseif ($trigger === "revenue_due_in_days") {
            $day = date("Y-m-d", strtotime("+" . $amount . " days"));
            $rows = q(
                "SELECT id,title,patient_link_id,expected_at,amount_cents FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND DATE(expected_at)=? ORDER BY expected_at ASC LIMIT $limit",
                [$cid, $day],
            )->fetchAll();
''',
    '''        } elseif ($trigger === "revenue_due_in_days") {
            $day = maestro_local_day($cid, $amount);
            [$start, $end] = maestro_local_day_utc_range($cid, $day);
            $rows = q(
                "SELECT id,title,patient_link_id,expected_at,amount_cents FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND expected_at>=? AND expected_at<? ORDER BY expected_at ASC LIMIT $limit",
                [$cid, $start, $end],
            )->fetchAll();
''',
    "receita prevista local",
)
maestro = replace_exact(
    maestro,
    '''        } elseif ($trigger === "expense_due_in_days") {
            $day = date("Y-m-d", strtotime("+" . $amount . " days"));
''',
    '''        } elseif ($trigger === "expense_due_in_days") {
            $day = maestro_local_day($cid, $amount);
''',
    "despesa prevista local",
)
maestro = replace_exact(
    maestro,
    '''        } elseif ($trigger === "expense_overdue_after_days") {
            $rows = q(
                "SELECT id,title,due_at,amount_cents FROM pi_financial_expenses WHERE clinic_id=? AND status='prevista' AND due_at IS NOT NULL AND due_at<=DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY due_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
''',
    '''        } elseif ($trigger === "expense_overdue_after_days") {
            $cutoff = maestro_local_day($cid, -$amount);
            $rows = q(
                "SELECT id,title,due_at,amount_cents FROM pi_financial_expenses WHERE clinic_id=? AND status='prevista' AND due_at IS NOT NULL AND due_at<=? ORDER BY due_at ASC LIMIT $limit",
                [$cid, $cutoff],
            )->fetchAll();
''',
    "despesa vencida local",
)
maestro = replace_exact(
    maestro,
    '''    if (
        $scope === "role" &&
        !array_key_exists((string) $role, clinic_role_options($cid, true))
    ) {
        $scope = "clinic";
        $role = null;
    }
    if ($scope === "user" && (!$userId || !clinic_user_exists($cid, $userId))) {
        $scope = "clinic";
        $userId = null;
    }
''',
    '''    if (!in_array($scope, ["clinic", "role", "user"], true)) {
        throw new RuntimeException("Escopo de destinatário inválido para a ação.");
    }
    if (
        $scope === "role" &&
        !array_key_exists((string) $role, clinic_role_options($cid, true))
    ) {
        throw new RuntimeException("Cargo destinatário indisponível para a ação.");
    }
    if ($scope === "user" && (!$userId || !clinic_user_exists($cid, $userId))) {
        throw new RuntimeException("Pessoa destinatária indisponível para a ação.");
    }
''',
    "destinatário na materialização",
)
maestro = replace_exact(
    maestro,
    '''        maestro_due_dt((int) ($act["due_offset_days"] ?? 0)),
''',
    '''        maestro_due_dt((int) ($act["due_offset_days"] ?? 0), $cid),
''',
    "vencimento UTC da ação",
)
maestro = replace_exact(
    maestro,
    '''function maestro_supervised_with_clinic_timezone(int $clinicId, callable $callback): mixed
{
    $previousTimezone = date_default_timezone_get();
    $timezone = app_context_timezone(null, $clinicId);
    $offset = app_timezone_offset_string($timezone);
    try {
        @date_default_timezone_set($timezone);
        $statement = pdo()->prepare("SET time_zone=?");
        $statement->execute([$offset]);
        return $callback();
    } finally {
        @date_default_timezone_set($previousTimezone ?: "UTC");
        try {
            $statement = pdo()->prepare("SET time_zone=?");
            $statement->execute(["+00:00"]);
        } catch (Throwable $error) {
            error_log("[Prontoo Maestro timezone restore] " . $error->getMessage());
        }
    }
}
''',
    '''function maestro_supervised_with_clinic_timezone(int $clinicId, callable $callback): mixed
{
    $previousTimezone = date_default_timezone_get();
    $hadDisplayTimezone = array_key_exists("PRONTOO_DISPLAY_TIMEZONE", $GLOBALS);
    $previousDisplayTimezone = $GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? null;
    $timezone = app_context_timezone(null, $clinicId);
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
''',
    "wrapper de fuso",
)
write("app/Domain/Maestro/Maestro.php", maestro)

deferred = read("app/Support/DeferredAudit.php")
deferred = replace_exact(
    deferred,
    '''        $deadLetter += count((array) glob($dir . "/dead-letter/*.json"));
''',
    '''        foreach ((array) glob($dir . "/dead-letter/*") as $deadFile) {
            if (is_file($deadFile)) {
                $deadLetter++;
            }
        }
''',
    "inventário de fila morta",
)
write("app/Support/DeferredAudit.php", deferred)

tasks = read("app/Domain/Tasks/TasksNotices.php")
tasks = replace_exact(
    tasks,
    '''        if (!in_array($targetScope, ["clinic", "role", "user"], true)) {
            $targetScope = "clinic";
        }
''',
    '''        if (!in_array($targetScope, ["clinic", "role", "user"], true)) {
            if ($strict) {
                throw new RuntimeException("Escopo de destinatário inválido para a tarefa.");
            }
            $targetScope = "clinic";
        }
''',
    "escopo estrito da tarefa",
)
tasks = replace_exact(
    tasks,
    '''        if (
            $targetScope === "role" &&
            !array_key_exists(
                (string) $targetRole,
                clinic_role_options($cid, true),
            )
        ) {
            $targetScope = "clinic";
            $targetRole = null;
        }
        if ($assignedTo !== null && !clinic_user_exists($cid, $assignedTo)) {
            $assignedTo = null;
        }
        if ($targetScope === "user" && $assignedTo === null) {
            $targetScope = "clinic";
            $targetRole = null;
        }
''',
    '''        if (
            $targetScope === "role" &&
            !array_key_exists(
                (string) $targetRole,
                clinic_role_options($cid, true),
            )
        ) {
            if ($strict) {
                throw new RuntimeException("Cargo destinatário inválido para a tarefa.");
            }
            $targetScope = "clinic";
            $targetRole = null;
        }
        if ($assignedTo !== null && !clinic_user_exists($cid, $assignedTo)) {
            if ($strict) {
                throw new RuntimeException("Pessoa destinatária inválida para a tarefa.");
            }
            $assignedTo = null;
        }
        if ($targetScope === "user" && $assignedTo === null) {
            if ($strict) {
                throw new RuntimeException("A tarefa exige uma pessoa destinatária ativa.");
            }
            $targetScope = "clinic";
            $targetRole = null;
        }
''',
    "destinatário estrito da tarefa",
)
write("app/Domain/Tasks/TasksNotices.php", tasks)

cron = read("cron/maestro.php")
cron = replace_exact(
    cron,
    '''    $rulesBudget = min(45000, max(5000, prontoo_cron_remaining_budget_ms($__prontooCronDeadline)));
    $rules = maestro_supervised_cron_run($rulesBudget);
''',
    '''    $rulesBudget = min(45000, prontoo_cron_remaining_budget_ms($__prontooCronDeadline));
    $rules = $rulesBudget >= 5000
        ? maestro_supervised_cron_run($rulesBudget)
        : [
            "success" => true,
            "status" => "attention",
            "rules_seen" => 0,
            "rules_run" => 0,
            "actions_created" => 0,
            "deferred" => 1,
            "errors" => 0,
            "note" => "Regras adiadas por orçamento residual insuficiente.",
        ];
''',
    "orçamento residual das regras",
)
write("cron/maestro.php", cron)

domain_doc = read("docs/domain/maestro.md")
domain_doc = domain_doc.replace(
    "- cálculo de janelas e vencimentos no fuso do consultório;",
    "- cálculo de janelas no fuso do consultório, convertido explicitamente para UTC antes da consulta ou persistência;",
)
domain_doc = domain_doc.replace(
    "O resultado pai é persistido em `ssd/maestro/state/latest.json`. Cada estágio informa saúde, duração e contadores próprios.",
    "O resultado pai é persistido em `ssd/maestro/state/latest.json`. Cada estágio informa saúde, duração e contadores próprios. A sessão MySQL permanece em UTC durante todo o ciclo.",
)
write("docs/domain/maestro.md", domain_doc)

adr = read("docs/adr/0005-maestro-supervised-server-cycle.md")
adr = adr.replace(
    "- fuso do consultório durante avaliação e materialização;",
    "- datas civis calculadas no fuso do consultório e convertidas para UTC sem alterar a sessão MySQL;",
)
write("docs/adr/0005-maestro-supervised-server-cycle.md", adr)

runbook = read("docs/operations/maestro-runbook.md")
runbook = runbook.replace(
    "Uma regra com cargo sem colaborador ativo ou usuário inválido falha de modo explícito. Corrija o destinatário; não amplie manualmente para toda a clínica como solução automática.",
    "Uma regra com cargo sem colaborador ativo ou usuário inválido falha de modo explícito. Corrija o destinatário; não amplie manualmente para toda a clínica como solução automática. Janelas locais são convertidas para UTC; a sessão MySQL não muda de fuso.",
)
write("docs/operations/maestro-runbook.md", runbook)

checks = {
    "mysql_timezone_mutation_removed": 'SET time_zone' not in maestro[maestro.index("function maestro_supervised_with_clinic_timezone"):maestro.index("function maestro_supervised_candidate_result")],
    "legacy_dead_letter_visible": 'glob($dir . "/dead-letter/*")' in deferred,
    "save_fail_closed": 'throw new RuntimeException("Cargo destinatário inválido para o consultório.")' in maestro,
    "action_fail_closed": 'throw new RuntimeException("Cargo destinatário indisponível para a ação.")' in maestro,
    "strict_task_fail_closed": 'throw new RuntimeException("Cargo destinatário inválido para a tarefa.")' in tasks,
    "due_utc": 'maestro_due_dt((int) ($act["due_offset_days"] ?? 0), $cid)' in maestro,
    "civil_day_queries_hardened": all(token not in maestro for token in [
        "DATE(start_at)=CURDATE()",
        "DATE(next_action_at)=?",
        "DATE(issued_at)=?",
        "DATE(due_at)=?",
        "DATE(expected_at)=?",
        "DATE_SUB(CURDATE(), INTERVAL ? DAY)",
    ]),
}
if not all(checks.values()):
    raise RuntimeError(json.dumps(checks, ensure_ascii=False))

for transient in (SCRIPT, WORKFLOW):
    if transient.exists():
        transient.unlink()

now = datetime.now(timezone.utc)
manifest_path = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest["generated_at"] = now.isoformat()
manifest["generated_at_unix"] = int(now.timestamp())
manifest["updated_at"] = now.isoformat()
manifest["notes"] = "Ciclo Maestro supervisionado com sessão MySQL UTC, destinatários fail-closed e inventário retrocompatível de fila morta."
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=ROOT).split(b"\0")
files: dict[str, str] = {}
total = 0
for raw in tracked:
    if not raw:
        continue
    relative = raw.decode("utf-8")
    if relative == "app/update.manifest.json":
        continue
    absolute = ROOT / relative
    if not absolute.is_file():
        continue
    data = absolute.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["files"] = dict(sorted(files.items()))
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4, sort_keys=False) + "\n",
    encoding="utf-8",
)

print(json.dumps({"ok": True, "checks": checks, "files": len(files)}, ensure_ascii=False))
