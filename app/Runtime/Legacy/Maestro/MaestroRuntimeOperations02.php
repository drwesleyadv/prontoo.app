<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Maestro;

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

final class MaestroRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function page_maestro(): void
    
    {
    
        $c = require_can("maestro");
        if (
            ($c["scope"] ?? "") !== "clinic" ||
            !has_effective_role($c, "gerente")
        ) {
            throw new ProntooHttpError(
                403,
                "Rotinas são exclusivas do administrador do consultório.",
            );
        }
        maestro_ensure_schema();
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "save_rule");
            if ($act === "save_rule") {
                maestro_save_rule($c);
                flash("Rotina salva.");
                redirect("maestro");
            }
            $id = max(0, (int) ($_POST["id"] ?? 0));
            if ($act === "toggle_rule" && $id > 0) {
                q(
                    "UPDATE pi_maestro_rules SET active=IF(active=1,0,1), next_run_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [$uid, $id, $cid],
                );
                audit("maestro_regra_status", "maestro", $id, [
                    "audit_body" => "Status da rotina alterado.",
                ]);
                flash("Rotina atualizada.");
                redirect("maestro");
            }
            if ($act === "delete_rule" && $id > 0) {
                q("DELETE FROM pi_maestro_rules WHERE id=? AND clinic_id=?", [
                    $id,
                    $cid,
                ]);
                audit("maestro_regra_excluida", "maestro", $id, [
                    "audit_body" =>
                        "Rotina excluída pelo administrador do consultório.",
                ]);
                flash("Rotina excluída.");
                redirect("maestro");
            }
        }
        $isNew = (string) ($_GET["new"] ?? "") === "1";
        $catalog = maestro_trigger_catalog();
        $moduleOpts = maestro_module_options($catalog);
        $firstModule = array_key_first($moduleOpts) ?: "appointments";
        $rules = q(
            "SELECT * FROM pi_maestro_rules WHERE clinic_id=? ORDER BY active DESC, priority DESC, id DESC LIMIT 300",
            [$cid],
        )->fetchAll();
        $lastRun = one(
            "SELECT started_at,finished_at,duration_ms,actions_created,rules_run,deferred_count FROM pi_maestro_job_runs ORDER BY id DESC LIMIT 1",
        );
        $lastRunValue = $lastRun
            ? dt_br((string) ($lastRun["finished_at"] ?: $lastRun["started_at"]))
            : "Nunca";
        $avgDuration24Ms = (int) round(
            (float) (val(
                "SELECT AVG(duration_ms) FROM pi_maestro_job_runs WHERE finished_at>=DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            ) ?? 0),
        0, \RoundingMode::HalfAwayFromZero);
        if ($avgDuration24Ms <= 0 && $lastRun) {
            $avgDuration24Ms = (int) ($lastRun["duration_ms"] ?? 0);
        }
        $tempoGastoValue =
            $avgDuration24Ms > 0
                ? maestro_duration_label($avgDuration24Ms)
                : "Nunca";
        $created = (int) val(
            "SELECT COUNT(*) FROM pi_maestro_executions WHERE clinic_id=? AND status='created' AND executed_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$cid],
        );
        $roles = clinic_role_options($cid, true);
        $users = q(
            "SELECT u.id,u.name FROM pi_users u INNER JOIN pi_user_roles ur ON ur.user_id=u.id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1 GROUP BY u.id,u.name ORDER BY u.name LIMIT 200",
            [$cid],
        )->fetchAll();
        $userOpts = ["0" => "Escolha um colaborador"];
        foreach ($users as $u) {
            $userOpts[(string) $u["id"]] = $u["name"];
        }
        $moduleSelect = select_label(
            "Onde a rotina deve observar?",
            "trigger_module",
            $moduleOpts,
            $firstModule,
            "required data-maestro-module",
        );
        $triggerSelect = form_row(
            "Qual movimento inicia a rotina?",
            maestro_trigger_option_html($catalog),
        );
        $roleRow =
            '<label class="field" data-maestro-target-role-row><span>Naipe/setor</span>' .
            select_html(
                "target_role",
                $roles,
                "recepcionista",
                "data-maestro-role",
            ) .
            "</label>";
        $userRow =
            '<label class="field" data-maestro-target-user-row><span>Pessoa específica</span>' .
            select_html("target_user_id", $userOpts, "0", "data-maestro-user") .
            "</label>";
        $form =
            '<form method="post" class="maestro-form maestro-simple-form" data-maestro-form>' .
            csrf_field() .
            '<input type="hidden" name="act" value="save_rule"><input type="hidden" name="name" value="" data-maestro-name><input type="hidden" name="task_title" value="" data-maestro-title><input type="hidden" name="task_description" value="" data-maestro-description><input type="hidden" name="due_offset_days" value="0"><div class="two">' .
            $moduleSelect .
            $triggerSelect .
            '</div><div class="two">' .
            select_label(
                "A rotina deve",
                "action_type",
                maestro_action_types(),
                "create_task",
                "required data-maestro-action",
            ) .
            select_label(
                "Para quem a rotina distribui?",
                "target_scope",
                [
                    "clinic" => "Toda a equipe",
                    "role" => "Um setor/cargo",
                    "user" => "Uma pessoa",
                ],
                "role",
                "data-maestro-target-scope",
            ) .
            '</div><div class="two maestro-target-details">' .
            $roleRow .
            $userRow .
            '</div><div class="two">' .
            form_row(
                "Prioridade",
                input(
                    "priority",
                    "number",
                    "85",
                    'min="1" max="100" data-maestro-priority',
                ),
            ) .
            form_row(
                "Prazo",
                input(
                    "trigger_amount",
                    "number",
                    "3",
                    'min="0" max="1440" data-maestro-amount',
                ),
            ) .
            '</div><small class="maestro-amount-help" data-maestro-amount-help>Informe o compasso da condição selecionada.</small><label class="checkline"><input type="checkbox" name="active" value="1" checked><span>Manter esta rotina ativa</span></label><div class="form-actions"><button type="submit" class="primary">' .
            icon("save") .
            "<span>Salvar rotina</span></button></div></form>";
        $execStats = [];
        if ($rules) {
            $ruleIds = array_map( fn($rr) => (int) $rr["id"], $rules);
            $ph = implode(",", array_fill(0, count($ruleIds), "?"));
            foreach (
                q(
                    "SELECT rule_id, SUM(CASE WHEN status='created' THEN 1 ELSE 0 END) AS created_count, MAX(executed_at) AS last_at FROM pi_maestro_executions WHERE clinic_id=? AND rule_id IN ($ph) GROUP BY rule_id",
                    array_merge([$cid], $ruleIds),
                )->fetchAll()
                as $er
            ) {
                $execStats[(int) $er["rule_id"]] = $er;
            }
        }
        $list = "";
        if (!$rules) {
            $list =
                '<div class="empty-state maestro-empty"><span class="material-symbols-rounded" aria-hidden="true">event_repeat</span><b>Nenhuma rotina cadastrada.</b><small>Use o botão Nova rotina para criar automações de tarefas ou avisos quando o fluxo do consultório pedir atenção.</small></div>';
        } else {
            foreach ($rules as $r) {
                $cond = maestro_decode_json($r["condition_json"] ?? "");
                $act = maestro_decode_json($r["action_json"] ?? "");
                $item = $catalog[(string) $r["trigger_event"]] ?? null;
                $label = $item["label"] ?? (string) $r["trigger_event"];
                $module = (string) $r["trigger_module"];
                $moduleLabel = maestro_module_label($module);
                $unit = (string) ($cond["unit"] ?? ($item["unit"] ?? "days"));
                $amount = (int) ($cond["amount"] ?? ($cond["days"] ?? 0));
                $amountLabel = $amount . " " . maestro_unit_label($unit, $amount);
                $destLabel = maestro_target_label($act, $cid);
                $actionLabel =
                    maestro_action_types()[(string) $r["action_type"]] ??
                    (string) $r["action_type"];
                $createdCount =
                    (int) ($execStats[(int) $r["id"]]["created_count"] ?? 0);
                $lastLabel = maestro_last_label(
                    $r["last_run_at"] ??
                        ($execStats[(int) $r["id"]]["last_at"] ?? null),
                );
                $nextLabel = maestro_next_label($r["next_run_at"] ?? null);
                $status = (int) $r["active"] ? "Ativa" : "Pausada";
                $statusClass = (int) $r["active"] ? "ok" : "muted";
                $list .=
                    '<details class="maestro-rule maestro-rule-row ' .
                    ((int) $r["active"] ? "is-active" : "is-paused") .
                    '">' .
                    '<summary class="maestro-rule-summary">' .
                    '<span class="maestro-rule-icon">' .
                    icon(maestro_module_icon($module)) .
                    "</span>" .
                    '<span class="maestro-rule-titleline"><b>' .
                    e((string) $r["name"]) .
                    "</b><small>" .
                    e($moduleLabel) .
                    " · " .
                    $status .
                    "</small></span>" .
                    '<span class="maestro-rule-open"><span>Abrir</span>' .
                    icon("unfold_more") .
                    "</span>" .
                    "</summary>" .
                    '<div class="maestro-rule-details">' .
                    '<div class="maestro-rule-chips">' .
                    "<span>" .
                    icon("schedule") .
                    e($amountLabel) .
                    "</span>" .
                    "<span>" .
                    icon(maestro_action_icon((string) $r["action_type"])) .
                    e($actionLabel) .
                    "</span>" .
                    "<span>" .
                    icon("groups") .
                    e($destLabel) .
                    "</span>" .
                    "<span>" .
                    icon("flag") .
                    e("Prioridade " . (int) $r["priority"]) .
                    "</span>" .
                    "</div>" .
                    '<div class="maestro-rule-meta">' .
                    "<span><small>Última afinação</small><b>" .
                    e($lastLabel) .
                    "</b></span>" .
                    "<span><small>Próximo compasso</small><b>" .
                    e($nextLabel) .
                    "</b></span>" .
                    "<span><small>Distribuídas</small><b>" .
                    (int) $createdCount .
                    "</b></span>" .
                    "</div>" .
                    '<form method="post" class="maestro-rule-actions">' .
                    csrf_field() .
                    '<input type="hidden" name="id" value="' .
                    (int) $r["id"] .
                    '">' .
                    '<button name="act" value="toggle_rule" class="ghost small" type="submit">' .
                    icon((int) $r["active"] ? "pause" : "play_arrow") .
                    "<span>" .
                    ((int) $r["active"] ? "Pausar" : "Ativar") .
                    "</span></button>" .
                    '<button name="act" value="delete_rule" class="ghost small danger" type="submit">' .
                    icon("delete") .
                    "<span>Excluir</span></button>" .
                    "</form>" .
                    "</div>" .
                    "</details>";
            }
        }
        $stats =
            '<div class="stats-grid">' .
            stat_card("Rotinas", count($rules), "event_repeat") .
            stat_card("Última execução", $lastRunValue, "event_repeat") .
            stat_card("Tempo gasto", $tempoGastoValue, "timer") .
            stat_card(
                "Atividades distribuídas (30 dias)",
                $created,
                "assignment_turned_in",
            ) .
            "</div>";
        if ($isNew) {
            $pageAction =
                '<a class="ghost small" href="' .
                href("maestro") .
                '">' .
                icon("arrow_back") .
                "<span>Rotinas</span></a>";
            $html = card(
                '<h2>Nova rotina</h2><p class="muted">Escolha onde a rotina deve observar o fluxo, qual movimento dispara a automação e para quem a atividade será distribuída.</p>' .
                    $form,
                "maestro-card maestro-new-card",
            );
        } else {
            $pageAction =
                '<a class="primary small" href="' .
                href("maestro", ["new" => 1]) .
                '">' .
                icon("event_repeat") .
                "<span>Nova rotina</span></a>";
            $html = $stats . card("<h2>Rotinas</h2>" . $list, "maestro-list-card");
        }
        page("Rotinas", page_head("Rotinas", "", $pageAction) . $html);
    
    }
}
