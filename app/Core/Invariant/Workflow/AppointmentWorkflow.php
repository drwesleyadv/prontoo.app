<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant\Workflow;

use Prontoo\Core\Invariant\Canonical;
use Prontoo\Core\Invariant\SqlExpression;

final class AppointmentWorkflow
{
    private static ?StateMachine $machine = null;

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.AppointmentWorkflow::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Invariant/Workflow/AppointmentWorkflow.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function assertWrite(
        string $table,
        string $operation,
        string $sql,
        array $params,
        ?array $insert,
        int $clinicId,
    ): array {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.AppointmentWorkflow::assertWrite
         * Responsabilidade: Implementa a responsabilidade “assert write” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Workflow/AppointmentWorkflow.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Context.AppointmentContextInvariant::assertWrite`.
         * Dependências chamadas: `self::machine`, `in_array`, `is_array`, `SqlExpression::insertColumnValues`, `self::deny`, `->normalize`, `->acceptsInitial`, `Canonical::hash`, `count`, `SqlExpression::assignments`, `SqlExpression::tokenValue`, `->knows` e mais 13.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if ($table !== "pi_appointments" || $clinicId <= 0) {
            return ["checked" => false, "transitions" => 0, "proofs" => []];
        }
        $machine = self::machine();
        $proofs = [];
        if (in_array($operation, ["INSERT", "REPLACE"], true)) {
            if (!is_array($insert)) {
                return ["checked" => false, "transitions" => 0, "proofs" => []];
            }
            $complete = true;
            $states = SqlExpression::insertColumnValues(
                $sql,
                $params,
                $insert,
                "status",
                $complete,
            );
            if ($states === []) {
                $states = ["agendado"];
            }
            if (!$complete) {
                self::deny("appointment_initial_state_unproved", $sql);
            }
            foreach ($states as $state) {
                $normalized = $machine->normalize((string) $state);
                if (!$machine->acceptsInitial($normalized)) {
                    self::deny("appointment_initial_state_invalid", $sql);
                }
                $proofs[] = Canonical::hash("appointment_initial_state", [
                    "state" => $normalized,
                    "clinic_id" => $clinicId,
                ]);
            }
            return ["checked" => true, "transitions" => count($proofs), "proofs" => $proofs];
        }
        if ($operation !== "UPDATE") {
            return ["checked" => false, "transitions" => 0, "proofs" => []];
        }
        $assignments = SqlExpression::assignments($sql);
        if (!isset($assignments["status"])) {
            return ["checked" => true, "transitions" => 0, "proofs" => []];
        }
        $assignment = $assignments["status"];
        if (empty($assignment["known_direct"])) {
            self::deny("appointment_target_state_unproved", $sql);
        }
        $known = false;
        $target = SqlExpression::tokenValue(
            $sql,
            (string) $assignment["token"],
            (int) $assignment["offset"],
            $params,
            $known,
        );
        if (!$known || !$machine->knows((string) $target)) {
            self::deny("appointment_target_state_invalid", $sql);
        }
        $targetState = $machine->normalize((string) $target);
        $sourcesComplete = true;
        $sourceValues = SqlExpression::whereAllowedValues(
            $sql,
            "status",
            $params,
            $sourcesComplete,
        );
        if (!$sourcesComplete || $sourceValues === []) {
            self::deny("appointment_transition_source_unproved", $sql);
        }
        $sourceStates = [];
        foreach ($sourceValues as $source) {
            $sourceState = $machine->normalize((string) $source);
            if (!$machine->knows($sourceState)) {
                self::deny("appointment_transition_source_invalid", $sql);
            }
            if (!$machine->canTransition($sourceState, $targetState)) {
                self::deny("appointment_transition_invalid", $sql);
            }
            $sourceStates[$sourceState] = true;
        }
        $idsComplete = true;
        $ids = array_values(array_filter(array_map(
            "intval",
            SqlExpression::whereEqualityValues($sql, "id", $params, $idsComplete),
        ), static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de núcleo de invariantes e decisões canônicas. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn(int $id): bool => $id > 0));
        if (!$idsComplete || $ids === []) {
            self::deny("appointment_transition_target_unproved", $sql);
        }
        $ids = array_values(array_unique($ids));
        $placeholders = implode(",", array_fill(0, count($ids), "?"));
        $statement = \pdo()->prepare(
            "SELECT id,status FROM pi_appointments WHERE clinic_id=? AND id IN ({$placeholders})",
        );
        $statement->execute(array_merge([$clinicId], $ids));
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $sourceState = $machine->normalize((string) ($row["status"] ?? ""));
            if (!isset($sourceStates[$sourceState])) {
                continue;
            }
            $proofs[] = Canonical::hash("appointment_transition", [
                "appointment_id" => (int) ($row["id"] ?? 0),
                "clinic_id" => $clinicId,
                "from" => $sourceState,
                "to" => $targetState,
            ]);
        }
        return ["checked" => true, "transitions" => count($proofs), "proofs" => $proofs];
    }

    private static function machine(): StateMachine
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.AppointmentWorkflow::machine
         * Responsabilidade: Implementa a responsabilidade “machine” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Workflow/AppointmentWorkflow.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Workflow.AppointmentWorkflow::assertWrite`, `Core.Invariant.Workflow.AppointmentWorkflow::logicSelfTest`.
         * Dependências chamadas: `StateMachine`.
         * Classes ou serviços instanciados: `StateMachine`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (self::$machine instanceof StateMachine) {
            return self::$machine;
        }
        $aliases = [
            "agendada" => "agendado",
            "marcada" => "agendado",
            "confirmada" => "confirmado",
            "presente" => "chegou",
            "aguardando_triagem" => "em_preparo",
            "triagem" => "em_preparo",
            "em_triagem" => "em_preparo",
            "aguardando" => "pronto_atendimento",
            "pronto" => "pronto_atendimento",
            "pronto_para_atendimento" => "pronto_atendimento",
            "concluida" => "atendimento_concluido",
            "realizada" => "atendimento_concluido",
            "atendida" => "atendimento_concluido",
            "finalizada" => "finalizado",
            "cancelada" => "cancelado",
            "faltou" => "nao_compareceu",
            "desistente" => "nao_compareceu",
            "desistiu" => "nao_compareceu",
            "reagendada" => "reagendado",
            "scheduled" => "agendado",
            "confirmed" => "confirmado",
            "arrived" => "chegou",
            "preparing" => "em_preparo",
            "ready" => "pronto_atendimento",
            "in_service" => "em_atendimento",
            "completed" => "atendimento_concluido",
            "done" => "finalizado",
            "canceled" => "cancelado",
            "cancelled" => "cancelado",
            "no_show" => "nao_compareceu",
            "rescheduled" => "reagendado",
        ];
        $transitions = [
            "agendado" => ["confirmado", "chegou", "cancelado", "nao_compareceu", "reagendado"],
            "confirmado" => ["chegou", "cancelado", "nao_compareceu", "reagendado"],
            "chegou" => ["em_preparo"],
            "em_preparo" => ["pronto_atendimento"],
            "pronto_atendimento" => ["em_atendimento"],
            "em_atendimento" => ["atendimento_concluido"],
            "atendimento_concluido" => ["finalizado"],
            "finalizado" => [],
            "cancelado" => [],
            "nao_compareceu" => ["reagendado"],
            "reagendado" => [],
        ];
        return self::$machine = new StateMachine($transitions, $aliases, ["agendado"]);
    }

    private static function deny(string $key, string $sql): never
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.AppointmentWorkflow::deny
         * Responsabilidade: Implementa a responsabilidade “deny” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Invariant/Workflow/AppointmentWorkflow.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Workflow.AppointmentWorkflow::assertWrite`.
         * Dependências chamadas: `function_exists`.
         * Classes ou serviços instanciados: `.ProntooHttpError`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (function_exists("record_scope_violation")) {
            \record_scope_violation(
                $key,
                $sql,
                "Transição da Jornada do Paciente recusada pelo autômato formal.",
            );
        }
        throw new \ProntooHttpError(
            409,
            "A etapa solicitada não é compatível com o estado atual da Jornada do Paciente.",
        );
    }

    public static function logicSelfTest(): array
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Invariant.Workflow.AppointmentWorkflow::logicSelfTest
         * Responsabilidade: Executa verificações regressivas embutidas para confirmar que os contratos lógicos deste componente permanecem válidos.
         * Local arquitetural: app/Core/Invariant/Workflow/AppointmentWorkflow.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Invariant.Mutation.MutationInvariant::logicSelfTest`.
         * Dependências chamadas: `self::machine`, `SqlExpression::whereAllowedValues`, `->acceptsInitial`, `->canTransition`, `array_keys`, `array_filter`, `count`.
         * Efeitos colaterais: pode gravar ou remover dados.
         * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
         */
        $machine = self::machine();
        $sourceComplete = true;
        $sourceSet = SqlExpression::whereAllowedValues(
            "UPDATE pi_appointments SET status=? WHERE id=? AND clinic_id=? AND status IN (?,?)",
            "status",
            ["chegou", 8, 4, "agendado", "confirmado"],
            $sourceComplete,
        );
        $unsafeComplete = true;
        SqlExpression::whereAllowedValues(
            "UPDATE pi_appointments SET status='confirmado' WHERE status='agendado' OR id=?",
            "status",
            [8],
            $unsafeComplete,
        );
        $cases = [
            "initial_agendado" => $machine->acceptsInitial("agendado"),
            "confirm_flow" => $machine->canTransition("agendado", "confirmado"),
            "care_flow" => $machine->canTransition("em_atendimento", "atendimento_concluido"),
            "terminal_blocks_reopen" => !$machine->canTransition("finalizado", "agendado"),
            "alias_normalized" => $machine->canTransition("scheduled", "confirmed"),
            "source_predicate_set_proved" => $sourceComplete && $sourceSet === ["agendado", "confirmado"],
            "unbounded_or_source_denied" => !$unsafeComplete,
        ];
        $failed = array_keys(array_filter($cases, static /* Guia de manutenção: Executa uma transformação curta usada como callback no módulo de núcleo de invariantes e decisões canônicas. Dependências diretas: nenhuma dependência direta detectada estaticamente. Efeitos: transformação local sem efeito externo detectado. */ fn(bool $ok): bool => !$ok));
        return [
            "ok" => $failed === [],
            "passed" => count($cases) - count($failed),
            "total" => count($cases),
            "failed" => $failed,
        ];
    }
}
