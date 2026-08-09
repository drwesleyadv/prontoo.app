<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Appointments;

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

final class AppointmentsRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function appointment_journey_view_model(
        array $a,
        string $role = "",
        ?int $nowTs = null,
    ): array 
    {
    
        $nowTs = $nowTs ?? time();
        $technical = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a);
        $code = $technical;
        $start = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp((string) ($a["start_at"] ?? "")) ?: 0;
        if (
            in_array($code, ["agendado", "confirmado"], true) &&
            $start > 0 &&
            $start < $nowTs
        ) {
            $code = "atrasado";
        }
        $map = [
            "agendado" => [
                "Agendamento",
                "agendamento",
                "Agendado",
                "neutral",
                "event",
                "Recepção",
                "Confirmar ou aguardar chegada.",
            ],
            "confirmado" => [
                "Agendamento",
                "agendamento",
                "Confirmado",
                "neutral",
                "event_available",
                "Recepção",
                "Registrar chegada.",
            ],
            "atrasado" => [
                "Agendamento",
                "agendamento",
                "Atrasado",
                "bad",
                "warning",
                "Recepção",
                "Registrar chegada ou ausência.",
            ],
            "chegou" => [
                "Chegada",
                "chegada",
                "Paciente chegou",
                "warn",
                "how_to_reg",
                "Assistente",
                "Iniciar preparo.",
            ],
            "em_preparo" => [
                "Preparo",
                "preparo",
                "Em preparo",
                "warn",
                "clinical_notes",
                "Assistente",
                "Concluir preparo.",
            ],
            "pronto_atendimento" => [
                "Preparo",
                "preparo",
                "Pronto para atendimento",
                "info",
                "chair",
                "Profissional",
                "Iniciar atendimento.",
            ],
            "em_atendimento" => [
                "Atendimento",
                "atendimento",
                "Em atendimento",
                "info",
                "stethoscope",
                "Profissional",
                "Concluir atendimento.",
            ],
            "atendimento_concluido" => [
                "Saída",
                "saida",
                "Atendimento concluído",
                "warn",
                "task_alt",
                "Recepção",
                "Finalizar saída.",
            ],
            "finalizado" => [
                "Saída",
                "saida",
                "Finalizado",
                "ok",
                "done_all",
                "Jornada concluída",
                "Jornada concluída.",
            ],
            "cancelado" => [
                "Exceção",
                "excecao",
                "Cancelado",
                "bad",
                "event_busy",
                "Recepção/Admin",
                "Histórico preservado.",
            ],
            "nao_compareceu" => [
                "Exceção",
                "excecao",
                "Não compareceu",
                "bad",
                "person_off",
                "Recepção",
                "Avaliar remarcação.",
            ],
            "reagendado" => [
                "Exceção",
                "excecao",
                "Reagendado",
                "neutral",
                "event_repeat",
                "Recepção",
                "Acompanhar novo horário.",
            ],
        ];
        $m = $map[$code] ?? $map["agendado"];
        $order = [
            "agendamento" => 0,
            "chegada" => 1,
            "preparo" => 2,
            "atendimento" => 3,
            "saida" => 4,
        ];
        $phaseIndex = $order[$m[1]] ?? -1;
        $steps = [];
        foreach (\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_steps() as $idx => $step) {
            $state = "upcoming";
            if ($phaseIndex < 0) {
                $state = "muted";
            } elseif ($idx < $phaseIndex) {
                $state = "done";
            } elseif ($idx === $phaseIndex) {
                $state = "current";
            }
            $step["state"] = $state;
            $steps[] = $step;
        }
        return [
            "code" => $code,
            "technical_code" => $technical,
            "phase_label" => $m[0],
            "phase" => $m[1],
            "phase_index" => $phaseIndex,
            "label" => $m[2],
            "class" => $m[3],
            "icon" => $m[4],
            "owner" => $m[5],
            "action" => $m[6],
            "elapsed" => \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_elapsed_label($a, $code, $nowTs),
            "steps" => $steps,
            "role" => $role,
            "role_actions" => \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_role_actions($a, $role, $nowTs),
            "role_hint" => \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_role_hint($a, $role, $nowTs),
        ];
    
    }

    public static function appointment_journey_meta(
        array $a,
        string $role = "",
        ?int $nowTs = null,
    ): array 
    {
    
        return \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_view_model($a, $role, $nowTs);
    
    }

    public static function appointment_journey_view_code(array $a, ?int $nowTs = null): string
    
    {
    
        $nowTs = $nowTs ?? time();
        $code = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a);
        $start = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp((string) ($a["start_at"] ?? "")) ?: 0;
        if (
            in_array($code, ["agendado", "confirmado"], true) &&
            $start > 0 &&
            $start < $nowTs
        ) {
            return "atrasado";
        }
        return $code;
    
    }

    public static function appointment_journey_role_hint(
        array $a,
        string $role = "",
        ?int $nowTs = null,
    ): array 
    {
    
        $vmCode = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_view_code($a, $nowTs);
        $role = (string) $role;
        $map = [
            "recepcionista" => [
                "label" => "Entrada e saída",
                "icon" => "support_agent",
                "hint" => "Movimente chegada, ausência e saída administrativa.",
            ],
            "assistente" => [
                "label" => "Preparo",
                "icon" => "clinical_notes",
                "hint" =>
                    "Assuma a preparação e libere o paciente para o profissional.",
            ],
            "medico" => [
                "label" => "Atendimento",
                "icon" => "stethoscope",
                "hint" =>
                    "Inicie e conclua a etapa clínica quando o paciente estiver liberado.",
            ],
            "gerente" => [
                "label" => "Supervisão",
                "icon" => "monitoring",
                "hint" =>
                    "Acompanhe gargalos e exceções sem tomar a fila da equipe.",
            ],
        ];
        $base = $map[$role] ?? [
            "label" => "Jornada",
            "icon" => "route",
            "hint" => "Acompanhe a próxima medida da jornada.",
        ];
        if (
            in_array(
                $vmCode,
                ["finalizado", "cancelado", "nao_compareceu", "reagendado"],
                true,
            )
        ) {
            $base["hint"] =
                "Jornada encerrada ou em exceção; acompanhar apenas se houver pendência administrativa.";
        }
        return $base;
    
    }

    public static function appointment_journey_role_actions(
        array $a,
        string $role = "",
        ?int $nowTs = null,
        int $uid = 0,
    ): array 
    {
    
        $nowTs = $nowTs ?? time();
        $role = (string) $role;
        $technical = \Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_status_code($a);
        $display = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_view_code($a, $nowTs);
        $start = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp((string) ($a["start_at"] ?? "")) ?: 0;
        $actions = [];
        $add = function (
            string $act,
            string $label,
            string $icon,
            string $class = "primary",
            string $title = "",
            bool $danger = false,
        ) use (&$actions): void {
    
            $actions[] = [
                "act" => $act,
                "label" => $label,
                "icon" => $icon,
                "class" => $class,
                "title" => $title,
                "danger" => $danger,
            ];
        };
        if (\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "recepcionista")) {
            if ($technical === "agendado" && empty($a["arrived_at"])) {
                $add(
                    "confirm",
                    "Confirmar",
                    "event_available",
                    "ghost",
                    "Confirmar presença",
                );
            }
            if (
                in_array($technical, ["agendado", "confirmado"], true) &&
                empty($a["arrived_at"])
            ) {
                $add(
                    "arrived",
                    "Chegou",
                    "how_to_reg",
                    "primary",
                    "Registrar chegada",
                );
            }
            if (
                $display === "atrasado" &&
                empty($a["arrived_at"]) &&
                $start > 0 &&
                $start < $nowTs
            ) {
                $add(
                    "no_show_quick",
                    "Não veio",
                    "person_off",
                    "danger",
                    "Marcar ausência",
                    true,
                );
            }
            if ($technical === "atendimento_concluido") {
                $add(
                    "finish_checkout",
                    "Finalizar",
                    "logout",
                    "primary",
                    "Encerrar jornada administrativa",
                );
            }
        } elseif (\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "assistente")) {
            if ($technical === "chegou") {
                $add(
                    "start_prepare",
                    "Iniciar",
                    "play_arrow",
                    "primary",
                    "Assumir preparo/triagem",
                );
            }
            if ($technical === "em_preparo") {
                $add(
                    "finish_prepare",
                    "Concluir",
                    "task_alt",
                    "primary",
                    "Concluir preparo e avisar profissional",
                );
            }
        } elseif (\Prontoo\Domain\Appointments\AppointmentsDomainOperations01::appointment_journey_role_matches($role, "medico")) {
            if ($technical === "pronto_atendimento") {
                $add(
                    "start_consultation",
                    "Iniciar",
                    "play_arrow",
                    "primary",
                    "Abrir atendimento clínico",
                );
            }
            if ($technical === "em_atendimento") {
                $add(
                    "finish_consultation",
                    "Concluir",
                    "task_alt",
                    "primary",
                    "Encerrar etapa clínica",
                );
            }
        }
        $filtered = [];
        foreach ($actions as $candidate) {
            $real = (string) ($candidate["act"] ?? "");
            if ($real === "no_show_quick") {
                $real = "no_show";
            }
            if (
                $real !== "" &&
                \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations01::appointment_journey_hard_guard_message(
                    $a,
                    $real,
                    $role,
                    $uid,
                    $nowTs,
                ) === ""
            ) {
                $filtered[] = $candidate;
            }
        }
        return $filtered;
    
    }

    public static function appointment_journey_quick_actions_html(
        array $a,
        string $role = "",
        ?string $returnHidden = "",
        string $actionUrl = "",
        int $uid = 0,
    ): string 
    {
    
        $returnHidden = (string) ($returnHidden ?? "");
        $actions = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_role_actions($a, $role, null, $uid);
        if (!$actions) {
            return "";
        }
        $id = (int) ($a["id"] ?? 0);
        if ($id <= 0) {
            return "";
        }
        $actionAttr = $actionUrl !== "" ? ' action="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($actionUrl) . '"' : "";
        $html =
            '<span class="journey-ux-quick" aria-label="Ações rápidas da jornada">';
        foreach ($actions as $act) {
            $class = (string) ($act["class"] ?? "primary");
            $title = (string) ($act["title"] ?? $act["label"]);
            $confirm = !empty($act["danger"])
                ? ' onsubmit="return confirm(\'Registrar esta exceção na jornada do paciente?\')"'
                : "";
            $realAct = (string) $act["act"];
            $extra = "";
            if ($realAct === "no_show_quick") {
                $realAct = "no_show";
                $extra =
                    '<input type="hidden" name="no_show_reason" value="Paciente não compareceu no horário agendado.">';
            }
            $html .=
                '<form method="post" class="inline journey-ux-move"' .
                $actionAttr .
                $confirm .
                ">" .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                $returnHidden .
                '<input type="hidden" name="act" value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($realAct) .
                '"><input type="hidden" name="id" value="' .
                $id .
                '">' .
                $extra .
                '<button type="submit" class="small ' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($class) .
                '" title="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon((string) $act["icon"]) .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $act["label"]) .
                "</span></button></form>";
        }
        return $html . "</span>";
    
    }

    public static function appointment_journey_compact_html(
        array $a,
        string $role = "",
        ?string $returnHidden = "",
        string $actionUrl = "",
    ): string 
    {
    
        $returnHidden = (string) ($returnHidden ?? "");
        $vm = \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_view_model($a, $role);
        $elapsed = mb_trim((string) ($vm["elapsed"] ?? ""));
        $elapsed = $elapsed !== "" ? $elapsed : "Agora";
        $moves =
            $returnHidden !== "" || $actionUrl !== ""
                ? \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations02::appointment_journey_quick_actions_html(
                    $a,
                    $role,
                    $returnHidden,
                    $actionUrl,
                )
                : "";
        $facts =
            \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::appointment_journey_fact_html(
                "account_circle",
                "Responsável",
                (string) $vm["owner"],
            ) .
            \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::appointment_journey_fact_html("timer", "Na etapa há", $elapsed) .
            \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::appointment_journey_fact_html(
                "arrow_forward",
                "Próxima medida",
                (string) $vm["action"],
            );
        return '<span class="journey-ux journey-ux--compact journey-ux--operable journey-ux--timeline-card journey-ux--lean is-' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $vm["class"]) .
            '" title="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $vm["label"] . " · " . (string) $vm["phase_label"]) .
            '">' .
            \Prontoo\Presentation\Appointments\AppointmentsPresentationOperations01::appointment_journey_steps_html($vm) .
            '<span class="journey-ux-facts">' .
            $facts .
            "</span>" .
            $moves .
            "</span>";
    
    }
}
