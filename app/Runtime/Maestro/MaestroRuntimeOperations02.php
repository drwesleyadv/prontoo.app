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

final class MaestroRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function page_maestro(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("maestro");
        if (
            ($c["scope"] ?? "") !== "clinic" ||
            !\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente")
        ) {
            throw new ProntooHttpError(
                403,
                "Rotinas são exclusivas do administrador do consultório.",
            );
        }
        \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_ensure_schema();
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            $act = (string) ($_POST["act"] ?? "save_rule");
            if ($act === "save_rule") {
                \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_save_rule($c);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Rotina salva.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("maestro");
            }
            $id = max(0, (int) ($_POST["id"] ?? 0));
            if ($act === "toggle_rule" && $id > 0) {
                \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.02.page_maestro.01', [$uid, $id, $cid], []);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("maestro_regra_status", "maestro", $id, [
                    "audit_body" => "Status da rotina alterado.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Rotina atualizada.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("maestro");
            }
            if ($act === "delete_rule" && $id > 0) {
                \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.02.page_maestro.02', [
                    $id,
                    $cid,
                ], []);
                \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("maestro_regra_excluida", "maestro", $id, [
                    "audit_body" =>
                        "Rotina excluída pelo administrador do consultório.",
                ]);
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Rotina excluída.");
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("maestro");
            }
        }
        $isNew = (string) ($_GET["new"] ?? "") === "1";
        $catalog = \Prontoo\Domain\Maestro\MaestroDomainOperations01::maestro_trigger_catalog();
        $moduleOpts = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_module_options($catalog);
        $firstModule = array_key_first($moduleOpts) ?: "appointments";
        $rules = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.02.page_maestro.03', [$cid], [])->fetchAll();
        $lastRun = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->row('operational.maestro.02.page_maestro.04', [], []);
        $lastRunValue = $lastRun
            ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br((string) ($lastRun["finished_at"] ?: $lastRun["started_at"]))
            : "Nunca";
        $avgDuration24Ms = (int) round(
            (float) (\Prontoo\Runtime\Operational\OperationalComposition::maestro()->scalar('operational.maestro.02.page_maestro.05', [], []) ?? 0),
        0, \RoundingMode::HalfAwayFromZero);
        if ($avgDuration24Ms <= 0 && $lastRun) {
            $avgDuration24Ms = (int) ($lastRun["duration_ms"] ?? 0);
        }
        $tempoGastoValue =
            $avgDuration24Ms > 0
                ? \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_duration_label($avgDuration24Ms)
                : "Nunca";
        $created = (int) \Prontoo\Runtime\Operational\OperationalComposition::maestro()->scalar('operational.maestro.02.page_maestro.06', [$cid], []);
        $roles = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_options($cid, true);
        $users = \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.02.page_maestro.07', [$cid], [])->fetchAll();
        $userOpts = ["0" => "Escolha um colaborador"];
        foreach ($users as $u) {
            $userOpts[(string) $u["id"]] = $u["name"];
        }
        $moduleSelect = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
            "Onde a rotina deve observar?",
            "trigger_module",
            $moduleOpts,
            $firstModule,
            "required data-maestro-module",
        );
        $triggerSelect = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
            "Qual movimento inicia a rotina?",
            \Prontoo\Presentation\Maestro\MaestroPresentationOperations01::maestro_trigger_option_html($catalog),
        );
        $roleRow =
            '<label class="field" data-maestro-target-role-row><span>Naipe/setor</span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_html(
                "target_role",
                $roles,
                "recepcionista",
                "data-maestro-role",
            ) .
            "</label>";
        $userRow =
            '<label class="field" data-maestro-target-user-row><span>Pessoa específica</span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_html("target_user_id", $userOpts, "0", "data-maestro-user") .
            "</label>";
        $form =
            '<form method="post" class="maestro-form maestro-simple-form" data-maestro-form>' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="save_rule"><input type="hidden" name="name" value="" data-maestro-name><input type="hidden" name="task_title" value="" data-maestro-title><input type="hidden" name="task_description" value="" data-maestro-description><input type="hidden" name="due_offset_days" value="0"><div class="two">' .
            $moduleSelect .
            $triggerSelect .
            '</div><div class="two">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "A rotina deve",
                "action_type",
                \Prontoo\Domain\Maestro\MaestroDomainOperations01::maestro_action_types(),
                "create_task",
                "required data-maestro-action",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Prioridade",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "priority",
                    "number",
                    "85",
                    'min="1" max="100" data-maestro-priority',
                ),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Prazo",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "trigger_amount",
                    "number",
                    "3",
                    'min="0" max="1440" data-maestro-amount',
                ),
            ) .
            '</div><small class="maestro-amount-help" data-maestro-amount-help>Informe o compasso da condição selecionada.</small><label class="checkline"><input type="checkbox" name="active" value="1" checked><span>Manter esta rotina ativa</span></label><div class="form-actions"><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
            "<span>Salvar rotina</span></button></div></form>";
        $execStats = [];
        if ($rules) {
            $ruleIds = array_map( fn($rr) => (int) $rr["id"], $rules);
            foreach (
                \Prontoo\Runtime\Operational\OperationalComposition::maestro()->result('operational.maestro.02.page_maestro.08', array_merge([$cid], $ruleIds), ['itemCount' => count($ruleIds)])->fetchAll()
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
                $cond = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_decode_json($r["condition_json"] ?? "");
                $act = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_decode_json($r["action_json"] ?? "");
                $item = $catalog[(string) $r["trigger_event"]] ?? null;
                $label = $item["label"] ?? (string) $r["trigger_event"];
                $module = (string) $r["trigger_module"];
                $moduleLabel = \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_module_label($module);
                $unit = (string) ($cond["unit"] ?? ($item["unit"] ?? "days"));
                $amount = (int) ($cond["amount"] ?? ($cond["days"] ?? 0));
                $amountLabel = $amount . " " . \Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_unit_label($unit, $amount);
                $destLabel = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_target_label($act, $cid);
                $actionLabel =
                    \Prontoo\Domain\Maestro\MaestroDomainOperations01::maestro_action_types()[(string) $r["action_type"]] ??
                    (string) $r["action_type"];
                $createdCount =
                    (int) ($execStats[(int) $r["id"]]["created_count"] ?? 0);
                $lastLabel = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_last_label(
                    $r["last_run_at"] ??
                        ($execStats[(int) $r["id"]]["last_at"] ?? null),
                );
                $nextLabel = \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_next_label($r["next_run_at"] ?? null);
                $status = (int) $r["active"] ? "Ativa" : "Pausada";
                $statusClass = (int) $r["active"] ? "ok" : "muted";
                $list .=
                    '<details class="maestro-rule maestro-rule-row ' .
                    ((int) $r["active"] ? "is-active" : "is-paused") .
                    '">' .
                    '<summary class="maestro-rule-summary">' .
                    '<span class="maestro-rule-icon">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(\Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_module_icon($module)) .
                    "</span>" .
                    '<span class="maestro-rule-titleline"><b>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $r["name"]) .
                    "</b><small>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($moduleLabel) .
                    " · " .
                    $status .
                    "</small></span>" .
                    '<span class="maestro-rule-open"><span>Abrir</span>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("unfold_more") .
                    "</span>" .
                    "</summary>" .
                    '<div class="maestro-rule-details">' .
                    '<div class="maestro-rule-chips">' .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($amountLabel) .
                    "</span>" .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(\Prontoo\Domain\Maestro\MaestroDomainOperations02::maestro_action_icon((string) $r["action_type"])) .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($actionLabel) .
                    "</span>" .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("groups") .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($destLabel) .
                    "</span>" .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("flag") .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e("Prioridade " . (int) $r["priority"]) .
                    "</span>" .
                    "</div>" .
                    '<div class="maestro-rule-meta">' .
                    "<span><small>Última afinação</small><b>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($lastLabel) .
                    "</b></span>" .
                    "<span><small>Próximo compasso</small><b>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($nextLabel) .
                    "</b></span>" .
                    "<span><small>Distribuídas</small><b>" .
                    (int) $createdCount .
                    "</b></span>" .
                    "</div>" .
                    '<form method="post" class="maestro-rule-actions">' .
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                    '<input type="hidden" name="id" value="' .
                    (int) $r["id"] .
                    '">' .
                    '<button name="act" value="toggle_rule" class="ghost small" type="submit">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon((int) $r["active"] ? "pause" : "play_arrow") .
                    "<span>" .
                    ((int) $r["active"] ? "Pausar" : "Ativar") .
                    "</span></button>" .
                    '<button name="act" value="delete_rule" class="ghost small danger" type="submit">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
                    "<span>Excluir</span></button>" .
                    "</form>" .
                    "</div>" .
                    "</details>";
            }
        }
        $stats =
            '<div class="stats-grid">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Rotinas", count($rules), "event_repeat") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Última execução", $lastRunValue, "event_repeat") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card("Tempo gasto", $tempoGastoValue, "timer") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::stat_card(
                "Atividades distribuídas (30 dias)",
                $created,
                "assignment_turned_in",
            ) .
            "</div>";
        if ($isNew) {
            $pageAction =
                '<a class="ghost small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("maestro") .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
                "<span>Rotinas</span></a>";
            $html = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
                '<h2>Nova rotina</h2><p class="muted">Escolha onde a rotina deve observar o fluxo, qual movimento dispara a automação e para quem a atividade será distribuída.</p>' .
                    $form,
                "maestro-card maestro-new-card",
            );
        } else {
            $pageAction =
                '<a class="primary small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("maestro", ["new" => 1]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_repeat") .
                "<span>Nova rotina</span></a>";
            $html = $stats . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card("<h2>Rotinas</h2>" . $list, "maestro-list-card");
        }
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Rotinas", \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Rotinas", "", $pageAction) . $html);
    
    }
}
