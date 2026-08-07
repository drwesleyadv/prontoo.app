<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\AuditActivity;

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

final class AuditActivityRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function activity_human_sentence(
        string $event,
        ?string $entity,
        mixed $entityId,
        array $ctx,
        ?int $uid,
    ): string 
    {
    
        $who = audit_actor_name($ctx, $uid);
        $patient = activity_patient_name($ctx, $entityId);
        $title = activity_title_from_ctx($ctx, "");
        $clinic = trim(str_replace("Consultório", "", audit_clinic_target($ctx)));
        $clinic = $clinic !== "" ? $clinic : "consultório";
        return match ($event) {
            "janela_aberta" => $entity === "paciente" ||
            mb_trim((string) ($ctx["patient_name"] ?? "")) !== ""
                ? $who . " abriu a ficha do paciente " . $patient
                : $who .
                    " abriu a tela " .
                    (activity_text_value($ctx["janela"] ?? $title) ?: "do sistema"),
            "login_carregado" => $who . " abriu a tela de entrada",
            "autoteste_aviso" => $who . " recebeu um aviso técnico na entrada",
            "entrada_realizada" => ($env = activity_environment_label($ctx)) !== ""
                ? $who . " entrou no ambiente " . $env
                : $who . " entrou no sistema",
            "entrada_automatica_dispositivo" => ($env = activity_environment_label(
                $ctx,
            )) !== ""
                ? $who .
                    " entrou no ambiente " .
                    $env .
                    " pelo dispositivo reconhecido"
                : $who . " entrou pelo dispositivo reconhecido",
            "saida_realizada" => ($env = activity_environment_label($ctx)) !== ""
                ? $who . " saiu do ambiente " . $env
                : $who . " saiu do sistema",
            "falha_entrada" => $who . " tentou entrar e não conseguiu",
            "login_clinica_pendente" => $who .
                " escolheu um consultório para entrar",
            "login_credencial_pendente" => $who .
                " precisou escolher uma credencial de acesso",
            "acesso_negado" => $who . " tentou abrir uma área sem permissão",
            "csrf_bloqueado" => $who . " teve um envio bloqueado por segurança",
            "consulta_agendada" => $who . " agendou uma consulta para " . $patient,
            "consulta_alterada" => $who . " alterou a consulta de " . $patient,
            "consulta_excluida" => $who . " cancelou a consulta de " . $patient,
            "consulta_iniciada" => $who . " iniciou o atendimento de " . $patient,
            "paciente_chegou" => $who . " registrou a chegada de " . $patient,
            "agenda_bloqueada" => $who . " bloqueou um horário na agenda",
            "bloqueio_alterado" => $who . " alterou um bloqueio da agenda",
            "bloqueio_removido" => $who . " liberou um horário bloqueado na agenda",
            "lead_criado" => $who .
                " cadastrou o interessado " .
                audit_lead_name($ctx),
            "lead_atualizado" => $who .
                " atualizou o interessado " .
                audit_lead_name($ctx),
            "paciente_salvo" => $who .
                " " .
                ((string) ($ctx["acao_paciente"] ?? "") === "cadastrou"
                    ? "cadastrou"
                    : "alterou") .
                " a ficha do paciente " .
                $patient,
            "paciente_excluido" => $who .
                " removeu a ficha do paciente " .
                $patient .
                " do fluxo ativo",
            "paciente_recuperado" => $who .
                " reativou a ficha do paciente " .
                $patient,
            "aba_paciente_criada" => $who .
                " criou uma nova aba na ficha do paciente " .
                $patient,
            "responsavel_legal_salvo" => $who .
                " atualizou o responsável legal de " .
                $patient,
            "responsavel_legal_removido" => $who .
                " removeu um responsável legal de " .
                $patient,
            "registro_clinico", "prontuario_alterado" => $who .
                " atualizou o prontuário de " .
                $patient,
            "prontuario_visualizado" => $who . " abriu o prontuário de " . $patient,
            "receita_visualizada" => $who . " abriu a receita de " . $patient,
            "documento_rascunho_criado" => $who .
                " preparou um rascunho de documento" .
                (mb_trim((string) ($ctx["patient_name"] ?? "")) !== ""
                    ? " para " . mb_trim((string) $ctx["patient_name"])
                    : ""),
            "documento_emitido" => $who .
                " emitiu " .
                mb_strtolower(
                    audit_document_article(audit_document_type_text($ctx)),
                ) .
                " " .
                audit_document_type_text($ctx) .
                (mb_trim((string) ($ctx["patient_name"] ?? "")) !== ""
                    ? " para " . mb_trim((string) $ctx["patient_name"])
                    : ""),
            "documento_descartado" => $who . " descartou um rascunho de documento",
            "documento_visualizado" => $who .
                " abriu um documento" .
                (mb_trim((string) ($ctx["patient_name"] ?? "")) !== ""
                    ? " de " . mb_trim((string) $ctx["patient_name"])
                    : ""),
            "documento_impresso" => $who .
                " imprimiu um documento" .
                (mb_trim((string) ($ctx["patient_name"] ?? "")) !== ""
                    ? " de " . mb_trim((string) $ctx["patient_name"])
                    : ""),
            "modelo_documento_criado" => $who .
                " criou o modelo de documento " .
                audit_model_title($ctx),
            "modelo_documento_atualizado" => $who .
                " alterou o modelo de documento " .
                audit_model_title($ctx),
            "modelo_documento_aprovado" => $who .
                " aprovou o modelo de documento " .
                audit_model_title($ctx),
            "modelo_documento_rejeitado" => $who .
                " rejeitou o modelo de documento " .
                audit_model_title($ctx),
            "procedimento_criado" => $who .
                " cadastrou o procedimento " .
                ($title ?: "do consultório"),
            "procedimento_atualizado" => $who .
                " alterou o procedimento " .
                ($title ?: "do consultório"),
            "procedimento_status" => $who .
                " mudou o status do procedimento " .
                ($title ?: "do consultório"),
            "tarefa_criada" => $who . " criou a tarefa " . audit_task_name($ctx),
            "tarefa_iniciada" => $who .
                " começou a tarefa " .
                audit_task_name($ctx),
            "tarefa_concluida" => $who .
                " concluiu a tarefa " .
                audit_task_name($ctx),
            "tarefa_devolvida_fila" => $who .
                " devolveu a tarefa " .
                audit_task_name($ctx) .
                " para a fila",
            "comentario_tarefa_criado" => $who .
                " comentou na tarefa " .
                audit_task_name($ctx),
            "comentario_tarefa_editado" => $who .
                " editou um comentário na tarefa " .
                audit_task_name($ctx),
            "comentario_tarefa_excluido" => $who .
                " removeu um comentário da tarefa " .
                audit_task_name($ctx),
            "notificacao_tarefa_individual" => $who .
                " avisou alguém sobre a tarefa " .
                audit_task_name($ctx),
            "comunicado_criado" => $who .
                " publicou o comunicado " .
                audit_notice_name($ctx),
            "leitura_confirmada" => $who .
                " confirmou a leitura do comunicado " .
                audit_notice_name($ctx),
            "usuario_salvo" => $who .
                " atualizou o colaborador " .
                activity_person_from_ctx($ctx),
            "usuario_status" => $who .
                " mudou o status do colaborador " .
                activity_person_from_ctx($ctx),
            "usuario_desativado" => $who .
                " desativou o colaborador " .
                activity_person_from_ctx($ctx),
            "senha_redefinida" => $who .
                " redefiniu a senha de " .
                activity_person_from_ctx($ctx),
            "permissoes_atualizadas" => $who . " ajustou permissões da equipe",
            "consultorio_criado", "clinica_criada" => $who .
                " inaugurou o consultório " .
                $clinic,
            "consultorio_atualizado", "clinica_atualizada" => $who .
                " atualizou o consultório " .
                $clinic,
            "clinica_status" => $who . " mudou o status do consultório " . $clinic,
            "clinica_modelo_atualizada" => $who .
                " atualizou a isenção administrativa da plataforma",
            "onboarding_concluido" => $who .
                " concluiu a configuração inicial do consultório",
            "bloqueio_login_removido" => $who . " liberou uma entrada bloqueada",
            "erro_marcado_resolvido" => $who .
                " marcou um incidente como resolvido",
            "aviso_global_criado" => $who . " publicou um aviso global",
            "aviso_global_status" => $who . " mudou o status de um aviso global",
            "manutencao_atualizada" => $who . " atualizou o modo manutenção",
            "config_global_atualizada" => $who .
                " atualizou configurações globais da plataforma",
            "assinatura_ativada" => $who .
                " ativou a assinatura do consultório " .
                $clinic,
            "assinatura_desativada" => $who .
                " desativou a assinatura do consultório " .
                $clinic,
            "assinatura_atualizada" => $who .
                " atualizou a assinatura do consultório " .
                $clinic,
            "assinatura_pagamento_informado" => $who .
                " informou um pagamento de assinatura",
            "assinatura_pagamento_confirmado" => $who .
                " confirmou um pagamento de assinatura",
            "assinatura_pagamento_nao_confirmado" => $who .
                " recusou um pagamento de assinatura",
            "assinatura_comprovante_visualizado" => $who .
                " abriu um comprovante de assinatura",
            "assinatura_comprovante_excluido" => $who .
                " removeu um comprovante de assinatura",
            "somente_leitura_bloqueio" => $who .
                " tentou alterar dados com a assinatura em somente leitura",
            "meta_financeira_salva" => $who . " atualizou a meta financeira",
            "conta_bancaria_criada", "conta_financeira_criada" => $who .
                " criou a conta financeira " .
                activity_title_from_ctx($ctx, "do consultório"),
            "forma_pagamento_salva" => $who . " atualizou uma forma de pagamento",
            "credor_salvo" => $who .
                " atualizou o favorecido " .
                audit_counterparty_name($ctx),
            "despesa_cadastrada", "despesa_operacional_salva" => $who .
                " registrou a despesa " .
                activity_financial_label($ctx, "do consultório"),
            "despesa_paga" => $who .
                " marcou como paga a despesa " .
                activity_financial_label($ctx, "do consultório"),
            "despesa_cancelada" => $who . " cancelou uma despesa",
            "recebivel_destinado" => $who . " definiu o destino de um recebimento",
            "receita_operacional_salva" => $who .
                " registrou a receita " .
                activity_financial_label($ctx, "do consultório"),
            "receita_recebida" => $who .
                " marcou como recebida a receita " .
                activity_financial_label($ctx, "do consultório"),
            "receita_cancelada" => $who . " cancelou uma receita",
            "transferencia_financeira" => $who . " transferiu saldo entre contas",
            default => $who .
                " " .
                activity_action_verb($event) .
                " " .
                activity_direct_target($event, $entity, $entityId, $ctx),
        };
    
    }

    public static function activity_direct_title(
        string $event,
        ?string $entity,
        mixed $entityId,
        array $ctx,
        ?int $uid,
    ): string 
    {
    
        return activity_human_sentence($event, $entity, $entityId, $ctx, $uid);
    
    }

    public static function activity_context_details(
        string $event,
        ?string $entity,
        array $ctx,
    ): array 
    {
    
        $details = [];
        $money = activity_money_from_ctx($ctx);
        if ($money !== "") {
            $details[] = "Valor: " . $money;
        }
        if (in_array($event, ["consulta_agendada", "consulta_alterada"], true)) {
            $when = activity_date_from_ctx($ctx, [
                "start_at",
                "horario",
                "expected_at",
            ]);
            if ($when !== "") {
                $details[] = "Horário: " . $when;
            }
            $doctor = activity_text_value($ctx["doctor_name"] ?? "");
            if ($doctor !== "") {
                $details[] = "Profissional: " . $doctor;
            }
        }
        if (
            in_array(
                $event,
                ["tarefa_criada", "notificacao_tarefa_individual"],
                true,
            )
        ) {
            $scope = activity_target_scope_human($ctx);
            if ($scope !== "") {
                $details[] = ucfirst($scope);
            }
            $due = activity_date_from_ctx($ctx, ["due_at", "vencimento"]);
            if ($due !== "") {
                $details[] = "Prazo: " . $due;
            }
        }
        if (str_contains($event, "receita_") || $event === "recebivel_destinado") {
            $date = activity_date_from_ctx($ctx, [
                "expected_at",
                "received_at",
                "paid_at",
                "due_at",
            ]);
            if ($date !== "") {
                $details[] = "Data: " . $date;
            }
            $account = activity_text_value(
                $ctx["account_name"] ?? ($ctx["conta"] ?? ""),
            );
            if ($account !== "") {
                $details[] = "Conta: " . $account;
            }
        }
        if (str_contains($event, "despesa_")) {
            $credor = activity_clean_name(audit_counterparty_name($ctx), "");
            if ($credor !== "") {
                $details[] = "Favorecido: " . $credor;
            }
            $cat = activity_text_value(
                $ctx["categoria"] ?? ($ctx["category"] ?? ""),
            );
            if ($cat !== "") {
                $details[] = "Categoria: " . $cat;
            }
            $date = activity_date_from_ctx($ctx, [
                "due_at",
                "vencimento",
                "paid_at",
            ]);
            if ($date !== "") {
                $details[] = "Data: " . $date;
            }
        }
        if ($event === "transferencia_financeira") {
            $from = activity_text_value(
                $ctx["origem"] ?? ($ctx["account_from"] ?? ($ctx["from"] ?? "")),
            );
            $to = activity_text_value(
                $ctx["destino"] ?? ($ctx["account_to"] ?? ($ctx["to"] ?? "")),
            );
            if ($from !== "" || $to !== "") {
                $details[] =
                    "Movimento: " .
                    ($from !== "" ? $from : "origem") .
                    " → " .
                    ($to !== "" ? $to : "destino");
            }
        }
        $status = activity_status_from_ctx($ctx);
        if (
            $status !== "" &&
            !in_array(
                $event,
                ["despesa_operacional_salva", "receita_operacional_salva"],
                true,
            )
        ) {
            $details[] = "Status: " . $status;
        }
        return array_values(array_unique(array_filter($details)));
    
    }

    public static function activity_direct_body(
        string $event,
        ?string $entity,
        mixed $entityId,
        array $ctx,
    ): string 
    {
    
        [$axis] = activity_axis_for_event($event);
        if ($axis === "Consultar") {
            return "Nenhuma informação foi alterada.";
        }
        if (
            in_array(
                $event,
                [
                    "documento_impresso",
                    "assinatura_comprovante_excluido",
                    "documento_descartado",
                    "comentario_tarefa_excluido",
                ],
                true,
            )
        ) {
            return "O registro original permanece rastreável no histórico.";
        }
        if ($axis === "Excluir") {
            return "O item saiu do fluxo ativo. O histórico foi preservado.";
        }
        $fields = activity_changed_fields($event, $entity, $ctx);
        $details = activity_context_details($event, $entity, $ctx);
        $parts = [];
        if ($fields) {
            $parts[] =
                (count($fields) === 1 ? "Mudou o campo " : "Mudaram os campos ") .
                pt_list($fields) .
                ".";
        }
        if ($details) {
            $parts[] = implode(" · ", $details) . ".";
        }
        if (!$parts) {
            return "Nenhuma informação foi alterada.";
        }
        return implode(" ", $parts);
    
    }

    public static function activity_fallback_body(string $event, ?string $entity): string
    
    {
    
        return activity_direct_body($event, $entity, null, []);
    
    }

    public static function audit_items(array $rows, bool $global = false): array
    
    {
    
        $items = [];
        foreach ($rows as $r) {
            $event = (string) ($r["event_key"] ?? ($r["event"] ?? ""));
            $entityRaw = (string) ($r["entity_key"] ?? ($r["entity"] ?? ""));
            $entity = $entityRaw !== "" ? $entityRaw : null;
            $entityId = $r["entity_id"] ?? null;
            $uid = isset($r["user_id"]) ? (int) $r["user_id"] : null;
            $cid = isset($r["clinic_id"]) ? (int) $r["clinic_id"] : null;
            $ctx = audit_context_array($r);
            $ctx = audit_enrich_context($event, $entity, $entityId, $ctx, $cid);
            if (empty($ctx["actor_name"]) && $uid) {
                $n = audit_user_name_lookup($uid, $cid);
                if ($n !== "") {
                    $ctx["actor_name"] = $n;
                }
            }
            [$axis, $axisClass, $axisIcon] = activity_axis_for_event($event);
            $title = activity_direct_title($event, $entity, $entityId, $ctx, $uid);
            if (trim($title) === "") {
                $title = trim(
                    (string) ($r["friendly_text"] ?? event_label($event)),
                );
            }
            $body = activity_direct_body($event, $entity, $entityId, $ctx);
            $meta = "";
            if ($global && $cid) {
                $cn = audit_clinic_name_lookup($cid);
                if ($cn !== "") {
                    $meta = "Consultório: " . $cn;
                }
            }
            $items[] = [
                "icon" =>
                    $axisIcon ?: (string) ($r["event_icon"] ?? event_icon($event)),
                "time" => activity_time_direct($r["created_at"] ?? "", $cid ?: 0),
                "title" => $title,
                "body" => $body,
                "meta" => $meta,
                "class" => "activity-axis-" . $axisClass,
            ];
        }
        return $items;
    
    }

    public static function count_for_clinics(
        string $table,
        array $clinicIds,
        string $where = "1=1",
    ): array 
    {
    
        $table = allowed_db_table($table);
        if (!preg_match('/^[A-Za-z0-9_ =?<>()\.\-\']+$/', $where)) {
            throw new RuntimeException("Filtro inválido.");
        }
        $clinicIds = array_values(
            array_unique(array_filter(array_map("intval", $clinicIds))),
        );
        if (!$clinicIds) {
            return [];
        }
        sort($clinicIds, SORT_NUMERIC);
        $clinicIds = array_slice($clinicIds, 0, 300);
        $loader = function () use ($table, $clinicIds, $where): array {
    
            $out = array_fill_keys($clinicIds, 0);
            $placeholders = implode(",", array_fill(0, count($clinicIds), "?"));
            try {
                $rows = q(
                    "SELECT clinic_id,COUNT(*) AS total FROM $table WHERE clinic_id IN ($placeholders) AND $where GROUP BY clinic_id",
                    $clinicIds,
                )->fetchAll();
                foreach ($rows as $row) {
                    $clinicId = (int) ($row["clinic_id"] ?? 0);
                    if ($clinicId > 0) {
                        $out[$clinicId] = (int) ($row["total"] ?? 0);
                    }
                }
            } catch (Throwable $e) {
                error_log("[Prontoo grouped clinic count] " . $e->getMessage());
            }
            return $out;
        };
        if (function_exists("server_json_cache_remember")) {
            return server_json_cache_remember(
                "dashboard",
                server_json_cache_safe_key("clinic_count", [
                    $table,
                    $clinicIds,
                    $where,
                ]),
                server_json_cache_ttl("dashboard"),
                $loader,
                ["table:" . $table, "admin:clinic_counts"],
            );
        }
        return $loader();
    
    }
}
