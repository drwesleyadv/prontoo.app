<?php
declare(strict_types=1);
function maestro_global_physical_rollback(): void
{

    if (!has_cfg()) {
        return;
    }
    foreach (
        [
            "pi_maestro_rules",
            "pi_maestro_executions",
            "pi_maestro_job_runs",
            "pi_maestro_job_stats",
        ]
        as $table
    ) {
        if (!db_table_exists($table)) {
            throw new RuntimeException("Schema incompleto: {$table} ausente.");
        }
    }
}
function maestro_runtime_access_marker_ready(): bool
{

    if (array_key_exists("PRONTOO_MAESTRO_RUNTIME_ACCESS_READY", $GLOBALS)) {
        return (bool) $GLOBALS["PRONTOO_MAESTRO_RUNTIME_ACCESS_READY"];
    }
    if (!has_cfg()) {
        return false;
    }
    try {
        $ready =
            (string) (val(
                "SELECT meta_value FROM pi_meta WHERE meta_key='schema_maestro_runtime_access_v2' LIMIT 1",
            ) ?? "") === "1";
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS["PRONTOO_MAESTRO_RUNTIME_ACCESS_READY"] = $ready;
    return $ready;
}
function maestro_ensure_schema(): void
{

    static $validated = false;
    if (!has_cfg()) {
        return;
    }
    if (!$validated) {
        maestro_global_physical_rollback();
        foreach (["success", "errors_count", "deferred_count"] as $column) {
            if (!db_column_exists("pi_maestro_job_runs", $column)) {
                throw new RuntimeException(
                    "Schema incompleto: pi_maestro_job_runs.{$column} ausente.",
                );
            }
        }
        if (!maestro_runtime_access_marker_ready()) {
            maestro_grant_runtime_access();
        }
        $validated = maestro_runtime_access_marker_ready();
    }
}

function maestro_grant_runtime_access(): void
{

    if (!has_cfg() || maestro_runtime_access_marker_ready()) {
        return;
    }
    try {
        with_scope_guard_disabled(static function (): void {

            db_tx(static function (): void {

                q(
                    "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) SELECT id,'gerente','maestro',1 FROM pi_clinics WHERE active=1 ON DUPLICATE KEY UPDATE allowed=1",
                );
                q(
                    "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) SELECT id,'gerente','operations',1 FROM pi_clinics WHERE active=1 ON DUPLICATE KEY UPDATE allowed=1",
                );
                foreach (["view", "add", "edit", "delete"] as $op) {
                    q(
                        "INSERT INTO pi_permission_rules (clinic_id,role_code,action_key,operation_key,allowed) SELECT id,'gerente','maestro','" .
                            $op .
                            "',1 FROM pi_clinics WHERE active=1 ON DUPLICATE KEY UPDATE allowed=1",
                    );
                }
                q(
                    "INSERT INTO pi_meta (meta_key, meta_value) VALUES ('schema_maestro_runtime_access_v2','1') ON DUPLICATE KEY UPDATE meta_value='1', updated_at=NOW()",
                );
            });
        });
        $GLOBALS["PRONTOO_MAESTRO_RUNTIME_ACCESS_READY"] = true;
    } catch (Throwable $e) {
        error_log("[Prontoo maestro grant] " . $e->getMessage());
    }
}
function maestro_runtime_upgrade(): void
{

    if (!has_cfg()) {
        return;
    }
    try {
        maestro_ensure_schema();
    } catch (Throwable $e) {
        error_log("[Prontoo maestro upgrade] " . $e->getMessage());
    }
}
function maestro_trigger_catalog(): array
{

    return [
        "appointment_before_start" => [
            "module" => "appointments",
            "group" => "Agenda",
            "label" => "Consulta agendada",
            "amount_default" => 3,
            "amount_label" => "Dias antes da consulta",
            "unit" => "days",
            "source" => "consulta",
            "hint" =>
                "Criar tarefa para confirmar o agendamento antes do atendimento.",
            "default_name" => "Confirmar consulta futura",
            "default_title" => "Confirmar agendamento com {{paciente}}",
            "default_description" =>
                "Confirmar o agendamento de {{paciente}} marcado para {{data}} às {{hora}} com {{profissional}}.",
            "default_target_role" => "recepcionista",
            "default_priority" => 85,
        ],
        "appointment_created_without_confirmation" => [
            "module" => "appointments",
            "group" => "Agenda",
            "label" => "Consulta criada sem confirmação",
            "amount_default" => 1,
            "amount_label" => "Dias desde a criação",
            "unit" => "days",
            "source" => "consulta",
            "hint" =>
                "Conferir consultas que podem ter ficado sem retorno ao paciente.",
            "default_name" => "Conferir consulta sem confirmação",
            "default_title" => "Confirmar consulta de {{paciente}}",
            "default_description" =>
                "Consulta criada há {{prazo}} dia(s) para {{data}} às {{hora}}. Confirme os dados com o paciente.",
            "default_target_role" => "recepcionista",
            "default_priority" => 72,
        ],
        "appointment_changed_recent" => [
            "module" => "appointments",
            "group" => "Agenda",
            "label" => "Consulta remarcada ou alterada",
            "amount_default" => 1,
            "amount_label" => "Dias desde a alteração",
            "unit" => "days",
            "source" => "consulta",
            "hint" =>
                "Pedir confirmação depois de mudanças de data, horário ou observações.",
            "default_name" => "Confirmar alteração de consulta",
            "default_title" => "Confirmar nova data com {{paciente}}",
            "default_description" =>
                "A consulta de {{paciente}} foi alterada recentemente. Confirme a nova data: {{data}} às {{hora}}.",
            "default_target_role" => "recepcionista",
            "default_priority" => 78,
        ],
        "appointment_cancelled_recent" => [
            "module" => "appointments",
            "group" => "Agenda",
            "label" => "Consulta cancelada recentemente",
            "amount_default" => 1,
            "amount_label" => "Dias desde o cancelamento",
            "unit" => "days",
            "source" => "consulta",
            "hint" => "Criar tarefa de remarcação ou reorganização da agenda.",
            "default_name" => "Tratar consulta cancelada",
            "default_title" => "Verificar remarcação de {{paciente}}",
            "default_description" =>
                "A consulta de {{paciente}} foi cancelada. Avalie se deve ser remarcada ou apenas registrada como encerrada.",
            "default_target_role" => "recepcionista",
            "default_priority" => 70,
        ],
        "appointment_waiting_without_start_minutes" => [
            "module" => "appointments",
            "group" => "Agenda",
            "label" => "Paciente aguardando sem início",
            "amount_default" => 15,
            "amount_label" => "Minutos aguardando",
            "unit" => "minutes",
            "source" => "consulta",
            "hint" =>
                "Avisar triagem ou profissional quando o paciente já chegou e o atendimento não começou.",
            "default_name" => "Paciente aguardando atendimento",
            "default_title" => "Paciente {{paciente}} aguarda atendimento",
            "default_description" =>
                "{{paciente}} chegou e aguarda há pelo menos {{prazo}} minuto(s). Verifique o andamento do atendimento.",
            "default_target_role" => "assistente",
            "default_priority" => 92,
        ],
        "appointment_finished_without_care_hours" => [
            "module" => "appointments",
            "group" => "Agenda",
            "label" => "Consulta concluída sem registro",
            "amount_default" => 2,
            "amount_label" => "Horas após conclusão",
            "unit" => "hours",
            "source" => "consulta",
            "hint" =>
                "Lembrar o profissional de finalizar o registro da consulta.",
            "default_name" => "Finalizar registro de atendimento",
            "default_title" => "Registrar atendimento de {{paciente}}",
            "default_description" =>
                "A consulta de {{paciente}} foi concluída em {{data}} às {{hora}}, mas não há registro vinculado.",
            "default_target_role" => "medico",
            "default_priority" => 88,
        ],
        "appointment_today_without_patient" => [
            "module" => "appointments",
            "group" => "Agenda",
            "label" => "Consulta de hoje sem paciente",
            "amount_default" => 0,
            "amount_label" => "Dias",
            "unit" => "days",
            "source" => "consulta",
            "hint" => "Corrigir agendamentos sem vínculo antes do atendimento.",
            "default_name" => "Corrigir consulta sem paciente",
            "default_title" => "Vincular paciente ao horário de {{hora}}",
            "default_description" =>
                "Existe um horário de hoje sem paciente vinculado. Revise a agenda antes do atendimento.",
            "default_target_role" => "recepcionista",
            "default_priority" => 82,
        ],
        "lead_without_next_action_after_days" => [
            "module" => "leads",
            "group" => "Interessados",
            "label" => "Interessado sem próximo passo",
            "amount_default" => 1,
            "amount_label" => "Dias desde o cadastro",
            "unit" => "days",
            "source" => "interessado",
            "hint" => "Evitar que contatos novos fiquem sem retorno definido.",
            "default_name" => "Definir retorno de interessado",
            "default_title" => "Definir próximo passo para {{interessado}}",
            "default_description" =>
                "{{interessado}} foi cadastrado há {{prazo}} dia(s) e ainda não possui próximo retorno definido.",
            "default_target_role" => "recepcionista",
            "default_priority" => 74,
        ],
        "lead_next_action_before_days" => [
            "module" => "leads",
            "group" => "Interessados",
            "label" => "Retorno de interessado agendado",
            "amount_default" => 2,
            "amount_label" => "Dias antes do retorno",
            "unit" => "days",
            "source" => "interessado",
            "hint" =>
                "Distribuir tarefa antes da data marcada para retornar contato.",
            "default_name" => "Preparar retorno de interessado",
            "default_title" => "Preparar retorno para {{interessado}}",
            "default_description" =>
                "O retorno de {{interessado}} está previsto para {{data}}. Prepare o contato antes da data combinada.",
            "default_target_role" => "recepcionista",
            "default_priority" => 78,
        ],
        "lead_next_action_due" => [
            "module" => "leads",
            "group" => "Interessados",
            "label" => "Retorno de interessado vencido",
            "amount_default" => 0,
            "amount_label" => "Dias de tolerância",
            "unit" => "days",
            "source" => "interessado",
            "hint" => "Criar tarefa de contato quando o retorno ficou vencido.",
            "default_name" => "Retomar interessado",
            "default_title" => "Retornar contato de {{interessado}}",
            "default_description" =>
                "O retorno de {{interessado}} venceu em {{data}}. Faça contato e atualize o estágio.",
            "default_target_role" => "recepcionista",
            "default_priority" => 82,
        ],
        "lead_stalled_stage_after_days" => [
            "module" => "leads",
            "group" => "Interessados",
            "label" => "Interessado parado na etapa",
            "amount_default" => 7,
            "amount_label" => "Dias parado",
            "unit" => "days",
            "source" => "interessado",
            "hint" =>
                "Avisar quando um interessado fica tempo demais sem avanço.",
            "default_name" => "Revisar interessado parado",
            "default_title" => "Revisar interessado parado: {{interessado}}",
            "default_description" =>
                "{{interessado}} está parado há pelo menos {{prazo}} dia(s). Verifique se ainda há oportunidade de avanço.",
            "default_target_role" => "recepcionista",
            "default_priority" => 65,
        ],
        "lead_converted_recent" => [
            "module" => "leads",
            "group" => "Interessados",
            "label" => "Interessado convertido em paciente",
            "amount_default" => 1,
            "amount_label" => "Dias desde a conversão",
            "unit" => "days",
            "source" => "interessado",
            "hint" =>
                "Completar cadastro ou criar primeiro acompanhamento após conversão.",
            "default_name" => "Completar cadastro após conversão",
            "default_title" => "Completar cadastro de {{interessado}}",
            "default_description" =>
                "{{interessado}} foi convertido em paciente recentemente. Confira cadastro, vínculo e próximo atendimento.",
            "default_target_role" => "recepcionista",
            "default_priority" => 76,
        ],
        "patient_incomplete_registration" => [
            "module" => "patients",
            "group" => "Pacientes",
            "label" => "Paciente com cadastro incompleto",
            "amount_default" => 0,
            "amount_label" => "Dias",
            "unit" => "days",
            "source" => "paciente",
            "hint" =>
                "Criar tarefa para completar telefone, e-mail, cidade ou dados marcados para revisão.",
            "default_name" => "Completar cadastro de paciente",
            "default_title" => "Completar cadastro de {{paciente}}",
            "default_description" =>
                "O cadastro de {{paciente}} precisa de conferência ou complemento.",
            "default_target_role" => "recepcionista",
            "default_priority" => 68,
        ],
        "patient_without_recent_activity" => [
            "module" => "patients",
            "group" => "Pacientes",
            "label" => "Paciente sem movimentação",
            "amount_default" => 30,
            "amount_label" => "Dias sem atividade",
            "unit" => "days",
            "source" => "paciente",
            "hint" =>
                "Ajuda a recuperar pacientes parados sem consulta ou registro recente.",
            "default_name" => "Recontatar paciente parado",
            "default_title" => "Revisar movimentação de {{paciente}}",
            "default_description" =>
                "{{paciente}} está sem movimentação há pelo menos {{prazo}} dia(s). Avalie se há necessidade de recontato.",
            "default_target_role" => "recepcionista",
            "default_priority" => 52,
        ],
        "patient_birthday_before_days" => [
            "module" => "patients",
            "group" => "Pacientes",
            "label" => "Aniversário do paciente",
            "amount_default" => 7,
            "amount_label" => "Dias antes do aniversário",
            "unit" => "days",
            "source" => "paciente",
            "hint" =>
                "Criar lembrete para contato cordial antes do aniversário do paciente.",
            "default_name" => "Preparar contato de aniversário",
            "default_title" => "Aniversário de {{paciente}} chegando",
            "default_description" =>
                "{{paciente}} faz aniversário em {{data}}. Avalie se o consultório deve enviar uma mensagem ou registrar contato.",
            "default_target_role" => "recepcionista",
            "default_priority" => 40,
        ],
        "document_template_pending_approval" => [
            "module" => "documents",
            "group" => "Documentos",
            "label" => "Modelo aguardando aprovação",
            "amount_default" => 0,
            "amount_label" => "Dias aguardando",
            "unit" => "days",
            "source" => "modelo",
            "hint" =>
                "Levar modelos novos ou alterados para aprovação do Administrativo.",
            "default_name" => "Aprovar modelo de documento",
            "default_title" => "Revisar modelo: {{modelo}}",
            "default_description" =>
                "O modelo {{modelo}} aguarda aprovação para voltar a ser usado na emissão de documentos.",
            "default_target_role" => "gerente",
            "default_priority" => 86,
        ],
        "document_preview_abandoned" => [
            "module" => "documents",
            "group" => "Documentos",
            "label" => "Prévia não emitida",
            "amount_default" => 24,
            "amount_label" => "Horas sem emissão",
            "unit" => "hours",
            "source" => "documento",
            "hint" =>
                "Lembrar autor ou equipe de concluir/descartar pré-visualizações.",
            "default_name" => "Concluir prévia de documento",
            "default_title" => "Conferir prévia de {{documento}}",
            "default_description" =>
                "A pré-visualização do documento {{documento}} está preparada há {{prazo}} hora(s), mas ainda não foi emitida.",
            "default_target_role" => "recepcionista",
            "default_priority" => 58,
        ],
        "document_issued_without_patient" => [
            "module" => "documents",
            "group" => "Documentos",
            "label" => "Documento emitido sem paciente",
            "amount_default" => 1,
            "amount_label" => "Dias desde emissão",
            "unit" => "days",
            "source" => "documento",
            "hint" => "Revisar documentos emitidos sem vínculo com paciente.",
            "default_name" => "Revisar documento sem paciente",
            "default_title" => "Revisar vínculo do documento {{identificador}}",
            "default_description" =>
                "O documento {{documento}} foi emitido sem paciente vinculado. Verifique se isso está correto.",
            "default_target_role" => "gerente",
            "default_priority" => 72,
        ],
        "document_issued_after_days" => [
            "module" => "documents",
            "group" => "Documentos",
            "label" => "Documento emitido há X dias",
            "amount_default" => 1,
            "amount_label" => "Dias após emissão",
            "unit" => "days",
            "source" => "documento",
            "hint" => "Criar acompanhamento depois da emissão de um documento.",
            "default_name" => "Acompanhar documento emitido",
            "default_title" => "Acompanhar documento {{identificador}}",
            "default_description" =>
                "O documento {{documento}} foi emitido em {{data}}. Verifique se há providência posterior.",
            "default_target_role" => "recepcionista",
            "default_priority" => 48,
        ],
        "task_due_in_days" => [
            "module" => "tasks",
            "group" => "Tarefas",
            "label" => "Tarefa com prazo",
            "amount_default" => 1,
            "amount_label" => "Dias antes do vencimento",
            "unit" => "days",
            "source" => "tarefa",
            "hint" => "Gera reforço para evitar atrasos.",
            "default_name" => "Avisar tarefa vencendo",
            "default_title" => "Tarefa próxima do vencimento: {{tarefa}}",
            "default_description" =>
                "A tarefa {{tarefa}} vence em {{data}}. Verifique se está em andamento.",
            "default_target_role" => "gerente",
            "default_priority" => 70,
        ],
        "task_overdue_after_days" => [
            "module" => "tasks",
            "group" => "Tarefas",
            "label" => "Tarefa vencida",
            "amount_default" => 1,
            "amount_label" => "Dias de atraso",
            "unit" => "days",
            "source" => "tarefa",
            "hint" =>
                "Avisar Administrativo ou responsável sobre pendências atrasadas.",
            "default_name" => "Cobrar tarefa vencida",
            "default_title" => "Tarefa vencida: {{tarefa}}",
            "default_description" =>
                "A tarefa {{tarefa}} está vencida há pelo menos {{prazo}} dia(s).",
            "default_target_role" => "gerente",
            "default_priority" => 84,
        ],
        "task_stalled_after_days" => [
            "module" => "tasks",
            "group" => "Tarefas",
            "label" => "Tarefa em andamento parada",
            "amount_default" => 3,
            "amount_label" => "Dias sem atualização",
            "unit" => "days",
            "source" => "tarefa",
            "hint" =>
                "Identificar tarefas que começaram, mas não foram concluídas.",
            "default_name" => "Revisar tarefa parada",
            "default_title" => "Revisar tarefa em andamento: {{tarefa}}",
            "default_description" =>
                "A tarefa {{tarefa}} está em andamento sem atualização há pelo menos {{prazo}} dia(s).",
            "default_target_role" => "gerente",
            "default_priority" => 66,
        ],
        "task_returned_queue_recent" => [
            "module" => "tasks",
            "group" => "Tarefas",
            "label" => "Tarefa devolvida para fila",
            "amount_default" => 1,
            "amount_label" => "Dias desde devolução",
            "unit" => "days",
            "source" => "tarefa",
            "hint" =>
                "Avisar setor quando uma tarefa voltou para a fila coletiva.",
            "default_name" => "Redistribuir tarefa devolvida",
            "default_title" => "Redistribuir tarefa: {{tarefa}}",
            "default_description" =>
                "A tarefa {{tarefa}} foi devolvida para a fila. Verifique quem deve assumi-la.",
            "default_target_role" => "gerente",
            "default_priority" => 62,
        ],
        "revenue_due_in_days" => [
            "module" => "financial",
            "group" => "Financeiro",
            "label" => "Receita prevista",
            "amount_default" => 1,
            "amount_label" => "Dias antes do recebimento",
            "unit" => "days",
            "source" => "receita",
            "hint" => "Apoia conferência de recebimentos.",
            "default_name" => "Conferir receita prevista",
            "default_title" => "Conferir recebimento: {{receita}}",
            "default_description" =>
                "A receita {{receita}} de {{valor}} está prevista para {{data}}.",
            "default_target_role" => "gerente",
            "default_priority" => 60,
        ],
        "revenue_overdue_after_days" => [
            "module" => "financial",
            "group" => "Financeiro",
            "label" => "Receita vencida",
            "amount_default" => 1,
            "amount_label" => "Dias de atraso",
            "unit" => "days",
            "source" => "receita",
            "hint" => "Criar tarefa de conferência ou cobrança após atraso.",
            "default_name" => "Conferir receita vencida",
            "default_title" => "Receita vencida: {{receita}}",
            "default_description" =>
                "A receita {{receita}} de {{valor}} está vencida há pelo menos {{prazo}} dia(s).",
            "default_target_role" => "gerente",
            "default_priority" => 76,
        ],
        "expense_due_in_days" => [
            "module" => "financial",
            "group" => "Financeiro",
            "label" => "Despesa prevista",
            "amount_default" => 3,
            "amount_label" => "Dias antes do vencimento",
            "unit" => "days",
            "source" => "despesa",
            "hint" => "Apoia organização financeira.",
            "default_name" => "Preparar pagamento de despesa",
            "default_title" => "Despesa vencendo: {{despesa}}",
            "default_description" =>
                "A despesa {{despesa}} de {{valor}} vence em {{data}}.",
            "default_target_role" => "gerente",
            "default_priority" => 64,
        ],
        "expense_overdue_after_days" => [
            "module" => "financial",
            "group" => "Financeiro",
            "label" => "Despesa vencida",
            "amount_default" => 1,
            "amount_label" => "Dias de atraso",
            "unit" => "days",
            "source" => "despesa",
            "hint" =>
                "Avisar Gestão quando uma despesa prevista ficou vencida.",
            "default_name" => "Verificar despesa vencida",
            "default_title" => "Despesa vencida: {{despesa}}",
            "default_description" =>
                "A despesa {{despesa}} de {{valor}} está vencida há pelo menos {{prazo}} dia(s).",
            "default_target_role" => "gerente",
            "default_priority" => 80,
        ],
        "notice_unread_after_days" => [
            "module" => "notices",
            "group" => "Comunicados",
            "label" => "Comunicado sem confirmação",
            "amount_default" => 2,
            "amount_label" => "Dias sem confirmação",
            "unit" => "days",
            "source" => "notificacao",
            "hint" =>
                "Cria reforço para comunicações importantes não confirmadas.",
            "default_name" => "Reforçar comunicado não lido",
            "default_title" => "Reforçar comunicado: {{notificacao}}",
            "default_description" =>
                "O comunicado {{notificacao}} ainda não foi confirmado após {{prazo}} dia(s).",
            "default_target_role" => "gerente",
            "default_priority" => 54,
        ],
        "scope_violation_recent" => [
            "module" => "security",
            "group" => "Segurança",
            "label" => "Bloqueio de integridade registrado",
            "amount_default" => 1,
            "amount_label" => "Dias recentes",
            "unit" => "days",
            "source" => "segurança",
            "hint" =>
                "Avisar o Administrativo quando houver tentativa bloqueada de acesso ou escopo indevido.",
            "default_name" => "Revisar bloqueio de integridade",
            "default_title" => "Revisar bloqueio de integridade",
            "default_description" =>
                "O sistema registrou bloqueio de integridade no consultório. Verifique as atividades recentes.",
            "default_target_role" => "gerente",
            "default_priority" => 94,
            "default_action" => "create_notice",
        ],
        "technical_error_open_after_days" => [
            "module" => "security",
            "group" => "Segurança",
            "label" => "Erro técnico aberto",
            "amount_default" => 1,
            "amount_label" => "Dias aberto",
            "unit" => "days",
            "source" => "segurança",
            "hint" =>
                "Avisar Gestão sobre erro técnico não resolvido vinculado ao consultório.",
            "default_name" => "Revisar erro técnico",
            "default_title" => "Revisar erro técnico do sistema",
            "default_description" =>
                "Há erro técnico aberto há pelo menos {{prazo}} dia(s) no consultório.",
            "default_target_role" => "gerente",
            "default_priority" => 90,
            "default_action" => "create_notice",
        ],
    ];
}
function maestro_action_types(): array
{

    return [
        "create_task" => "Distribuir tarefa",
        "create_notice" => "Emitir aviso",
    ];
}
function maestro_module_label(string $module): string
{

    return [
        "appointments" => "Agenda",
        "leads" => "Interessados",
        "patients" => "Pacientes",
        "tasks" => "Tarefas",
        "documents" => "Documentos",
        "financial" => "Financeiro",
        "notices" => "Comunicados",
        "security" => "Segurança",
    ][$module] ?? $module;
}
function maestro_json(array $data): string
{

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false ? $json : "{}";
}
function maestro_decode_json(mixed $raw): array
{

    if (is_array($raw)) {
        return $raw;
    }
    $txt = trim((string) ($raw ?? ""));
    if ($txt === "") {
        return [];
    }
    $v = json_decode($txt, true);
    return is_array($v) ? $v : [];
}
function maestro_text(string $s, int $max = 180): string
{

    $s = trim(strip_tags($s));
    $s = preg_replace("/\s+/u", " ", $s) ?: "";
    return mb_substr($s, 0, $max);
}
function maestro_template(string $s, int $max = 900): string
{

    $s = trim(strip_tags($s));
    return mb_substr($s, 0, $max);
}
function maestro_days(mixed $v, int $default = 1): int
{

    $n = (int) $v;
    return max(0, min(365, $n > 0 || $v === "0" ? $n : $default));
}
function maestro_amount(mixed $v, int $default = 1, string $unit = "days"): int
{

    $n = (int) $v;
    if (!($n > 0 || $v === "0")) {
        $n = $default;
    }
    $max = match ($unit) {
        "minutes" => 1440,
        "hours" => 720,
        default => 365,
    };
    return max(0, min($max, $n));
}
function maestro_priority(mixed $v): int
{

    return max(1, min(100, (int) $v));
}
function maestro_due_dt(int $offsetDays = 0): ?string
{

    $offsetDays = max(0, min(365, $offsetDays));
    return date("Y-m-d 17:00:00", strtotime("+" . $offsetDays . " days"));
}
function maestro_apply_placeholders(string $template, array $vars): string
{

    return preg_replace_callback(
        "/\{\{\s*([a-z0-9_]+)\s*\}\}/iu",
        function ($m) use ($vars) {

            $k = mb_strtolower((string) $m[1]);
            return (string) ($vars[$k] ?? "");
        },
        $template,
    ) ?? $template;
}
function maestro_actor_id(): ?int
{

    return isset($_SESSION["uid"]) ? (int) $_SESSION["uid"] : null;
}
function maestro_save_rule(array $c): void
{

    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    $catalog = maestro_trigger_catalog();
    $actions = maestro_action_types();
    $id = max(0, (int) ($_POST["id"] ?? 0));
    $trigger = (string) ($_POST["trigger_event"] ?? "");
    if (!isset($catalog[$trigger])) {
        throw new RuntimeException("Condição inválida.");
    }
    $item = $catalog[$trigger];
    $unit = (string) ($item["unit"] ?? "days");
    $defaultAmount =
        (int) ($item["amount_default"] ?? ($item["days_default"] ?? 1));
    $action =
        (string) ($_POST["action_type"] ??
            ($item["default_action"] ?? "create_task"));
    if (!isset($actions[$action])) {
        $action = "create_task";
    }
    $name = maestro_text((string) ($_POST["name"] ?? ""), 160);
    if ($name === "") {
        $name = (string) ($item["default_name"] ?? $item["label"]);
    }
    $amount = maestro_amount(
        $_POST["trigger_amount"] ?? ($_POST["trigger_days"] ?? $defaultAmount),
        $defaultAmount,
        $unit,
    );
    $priority = maestro_priority(
        $_POST["priority"] ?? ($item["default_priority"] ?? 50),
    );
    $minInterval = max(
        PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES,
        min(
            1440,
            (int) ($_POST["min_interval_minutes"] ??
                PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES),
        ),
    );
    $targetScope = (string) ($_POST["target_scope"] ?? "role");
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
    if ($targetScope !== "role") {
        $targetRole = null;
    }
    if ($targetScope !== "user") {
        $targetUserId = null;
    }
    $title = maestro_text((string) ($_POST["task_title"] ?? ""), 180);
    if ($title === "") {
        $title =
            (string) ($item["default_title"] ??
                "Ação da rotina para {{origem}}");
    }
    $description = maestro_template(
        (string) ($_POST["task_description"] ?? ""),
        1200,
    );
    if ($description === "") {
        $description =
            (string) ($item["default_description"] ??
                "Rotina criada para acompanhamento de {{origem}}.");
    }
    $cond = [
        "amount" => $amount,
        "days" => $amount,
        "unit" => $unit,
        "status" => maestro_text((string) ($_POST["trigger_status"] ?? ""), 60),
    ];
    $act = [
        "target_scope" => $targetScope,
        "target_role" => $targetRole,
        "target_user_id" => $targetUserId,
        "title" => $title,
        "description" => $description,
        "due_offset_days" => maestro_days($_POST["due_offset_days"] ?? 0, 0),
    ];
    $active = empty($_POST["active"]) ? 0 : 1;
    $module = (string) $item["module"];
    if ($id > 0) {
        q(
            "UPDATE pi_maestro_rules SET name=?,active=?,trigger_module=?,trigger_event=?,condition_json=?,action_type=?,action_json=?,priority=?,min_interval_minutes=?,next_run_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?",
            [
                $name,
                $active,
                $module,
                $trigger,
                maestro_json($cond),
                $action,
                maestro_json($act),
                $priority,
                $minInterval,
                $uid,
                $id,
                $cid,
            ],
        );
        audit("maestro_regra_atualizada", "maestro", $id, [
            "nome" => $name,
            "condicao" => $trigger,
            "audit_body" =>
                "Rotina atualizada pelo administrador do consultório.",
        ]);
    } else {
        q(
            "INSERT INTO pi_maestro_rules (clinic_id,name,active,trigger_module,trigger_event,condition_json,action_type,action_json,priority,min_interval_minutes,next_run_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW())",
            [
                $cid,
                $name,
                $active,
                $module,
                $trigger,
                maestro_json($cond),
                $action,
                maestro_json($act),
                $priority,
                $minInterval,
                $uid,
            ],
        );
        $id = db_last_insert_id();
        audit("maestro_regra_criada", "maestro", $id, [
            "nome" => $name,
            "condicao" => $trigger,
            "audit_body" => "Rotina criada pelo administrador do consultório.",
        ]);
    }
}
function maestro_module_options(array $catalog): array
{

    $out = [];
    foreach ($catalog as $it) {
        $module = (string) ($it["module"] ?? "");
        if ($module !== "" && !isset($out[$module])) {
            $out[$module] = maestro_module_label($module);
        }
    }
    return $out;
}
function maestro_trigger_option_html(
    array $catalog,
    string $selected = "appointment_before_start",
): string {

    $h = '<select name="trigger_event" required data-maestro-trigger>';
    foreach ($catalog as $key => $it) {
        $sel = $key === $selected ? " selected" : "";
        $h .=
            '<option value="' .
            e($key) .
            '"' .
            $sel .
            ' data-module="' .
            e((string) ($it["module"] ?? "")) .
            '" data-amount="' .
            (int) ($it["amount_default"] ?? 1) .
            '" data-unit="' .
            e((string) ($it["unit"] ?? "days")) .
            '" data-amount-label="' .
            e((string) ($it["amount_label"] ?? "Prazo")) .
            '" data-name="' .
            e((string) ($it["default_name"] ?? $it["label"])) .
            '" data-title="' .
            e((string) ($it["default_title"] ?? "")) .
            '" data-description="' .
            e((string) ($it["default_description"] ?? "")) .
            '" data-role="' .
            e((string) ($it["default_target_role"] ?? "recepcionista")) .
            '" data-priority="' .
            (int) ($it["default_priority"] ?? 50) .
            '" data-action="' .
            e((string) ($it["default_action"] ?? "create_task")) .
            '">' .
            e((string) $it["label"]) .
            "</option>";
    }
    return $h . "</select>";
}
function maestro_duration_label(int $ms): string
{

    if ($ms <= 0) {
        return "0 ms";
    }
    if ($ms < 1000) {
        return $ms . " ms";
    }
    $seconds = $ms / 1000;
    if ($seconds < 60) {
        return number_format($seconds, 1, ",", ".") . " s";
    }
    $minutes = floor($seconds / 60);
    $rest = (int) round($seconds - $minutes * 60);
    return (int) $minutes . " min " . $rest . " s";
}
function maestro_unit_label(
    string $unit,
    int $amount,
    bool $short = false,
): string {

    if ($short) {
        return $unit === "minutes" ? "min" : ($unit === "hours" ? "h" : "d");
    }
    if ($unit === "minutes") {
        return $amount === 1 ? "minuto" : "minutos";
    }
    if ($unit === "hours") {
        return $amount === 1 ? "hora" : "horas";
    }
    return $amount === 1 ? "dia" : "dias";
}
function maestro_module_icon(string $module): string
{

    return [
        "appointments" => "calendar_month",
        "leads" => "person_search",
        "patients" => "patient_list",
        "tasks" => "task_alt",
        "documents" => "description",
        "financial" => "account_balance_wallet",
        "notices" => "campaign",
        "security" => "shield_lock",
    ][$module] ?? "auto_awesome";
}
function maestro_action_icon(string $action): string
{

    return $action === "create_notice" ? "campaign" : "assignment_turned_in";
}
function maestro_target_label(array $act, int $cid): string
{

    $dest = (string) ($act["target_scope"] ?? "clinic");
    if ($dest === "role") {
        return "Naipe: " .
            role_label_for((string) ($act["target_role"] ?? ""), $cid);
    }
    if ($dest === "user") {
        $uid = (int) ($act["target_user_id"] ?? 0);
        $name =
            $uid > 0
                ? (string) (val(
                    "SELECT u.name FROM pi_users u WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) LIMIT 1",
                    [$uid, $cid],
                ) ?:
                "")
                : "";
        return $name !== "" ? "Pessoa: " . $name : "Pessoa específica";
    }
    return "Toda a equipe";
}
function maestro_last_label(?string $value): string
{

    $value = trim((string) $value);
    return $value !== "" ? dt_br($value) : "Ainda não afinada";
}
function maestro_next_label(?string $value): string
{

    $value = trim((string) $value);
    if ($value === "") {
        return "No próximo ciclo";
    }
    $ts = strtotime($value);
    if (!$ts) {
        return "No próximo ciclo";
    }
    return $ts <= time() ? "No próximo ciclo" : dt_br($value);
}
function page_maestro(): void
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
    );
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
function maestro_match_base(array $vars, array $extra = []): array
{

    return $extra + ["variables" => $vars];
}
function maestro_fetch_candidates(array $rule, int $limit = 120): array
{

    $cid = (int) $rule["clinic_id"];
    $trigger = (string) $rule["trigger_event"];
    $catalog = maestro_trigger_catalog();
    $item = $catalog[$trigger] ?? [];
    $cond = maestro_decode_json($rule["condition_json"] ?? "");
    $unit = (string) ($cond["unit"] ?? ($item["unit"] ?? "days"));
    $amount = maestro_amount(
        $cond["amount"] ?? ($cond["days"] ?? ($item["amount_default"] ?? 1)),
        (int) ($item["amount_default"] ?? 1),
        $unit,
    );
    $days = $amount;
    $limit = max(1, min(300, $limit));
    $out = [];
    $clinic = audit_clinic_name_lookup($cid);
    $prazo = (string) $amount;
    $activeTask = "'aberta','em_andamento','aguardando'";
    try {
        if ($trigger === "appointment_before_start") {
            $start = date("Y-m-d 00:00:00", strtotime("+" . $amount . " days"));
            $end = date(
                "Y-m-d 00:00:00",
                strtotime("+" . ($amount + 1) . " days"),
            );
            $rows = q(
                "SELECT a.id,a.patient_link_id,a.doctor_user_id,a.start_at,a.reason,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.start_at>=? AND a.start_at<? AND a.status NOT IN ('cancelado','nao_compareceu','reagendado','atendimento_concluido','finalizado') ORDER BY a.start_at ASC LIMIT $limit",
                [$cid, $start, $end],
            )->fetchAll();
            foreach ($rows as $r) {
                $ts = app_storage_timestamp($r["start_at"]);
                $out[] = maestro_match_base(
                    [
                        "origem" => "consulta",
                        "consultorio" => $clinic,
                        "paciente" => $r["patient_name"] ?: "paciente",
                        "profissional" => $r["doctor_name"] ?: "profissional",
                        "data" => date_br($r["start_at"] ?? ""),
                        "hora" => app_time_br($r["start_at"] ?? ""),
                        "dias" => $prazo,
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "consulta",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => (int) $r["patient_link_id"],
                        "appointment_id" => (int) $r["id"],
                    ],
                );
            }
        } elseif ($trigger === "appointment_created_without_confirmation") {
            $rows = q(
                "SELECT a.id,a.patient_link_id,a.start_at,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) AND a.status='agendado' ORDER BY a.created_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $ts = app_storage_timestamp($r["start_at"]);
                $out[] = maestro_match_base(
                    [
                        "origem" => "consulta",
                        "consultorio" => $clinic,
                        "paciente" => $r["patient_name"] ?: "paciente",
                        "profissional" => $r["doctor_name"] ?: "profissional",
                        "data" => date_br($r["start_at"] ?? ""),
                        "hora" => app_time_br($r["start_at"] ?? ""),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "consulta",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => (int) $r["patient_link_id"],
                        "appointment_id" => (int) $r["id"],
                    ],
                );
            }
        } elseif ($trigger === "appointment_changed_recent") {
            $rows = q(
                "SELECT a.id,a.patient_link_id,a.start_at,a.change_reason,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.updated_at>=DATE_SUB(NOW(), INTERVAL ? DAY) AND a.change_reason IS NOT NULL AND a.status<>'cancelado' ORDER BY a.updated_at DESC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $ts = app_storage_timestamp($r["start_at"]);
                $out[] = maestro_match_base(
                    [
                        "origem" => "consulta",
                        "consultorio" => $clinic,
                        "paciente" => $r["patient_name"] ?: "paciente",
                        "profissional" => $r["doctor_name"] ?: "profissional",
                        "data" => date_br($r["start_at"] ?? ""),
                        "hora" => app_time_br($r["start_at"] ?? ""),
                        "motivo" => $r["change_reason"] ?? "",
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "consulta",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => (int) $r["patient_link_id"],
                        "appointment_id" => (int) $r["id"],
                    ],
                );
            }
        } elseif ($trigger === "appointment_cancelled_recent") {
            $rows = q(
                "SELECT a.id,a.patient_link_id,a.start_at,a.cancel_reason,p.full_name AS patient_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE a.clinic_id=? AND COALESCE(a.updated_at,a.created_at)>=DATE_SUB(NOW(), INTERVAL ? DAY) AND (a.status='cancelado' OR a.cancel_reason IS NOT NULL) ORDER BY COALESCE(a.updated_at,a.created_at) DESC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $ts = app_storage_timestamp($r["start_at"]);
                $out[] = maestro_match_base(
                    [
                        "origem" => "consulta",
                        "consultorio" => $clinic,
                        "paciente" => $r["patient_name"] ?: "paciente",
                        "data" => date_br($r["start_at"] ?? ""),
                        "hora" => app_time_br($r["start_at"] ?? ""),
                        "motivo" => $r["cancel_reason"] ?? "",
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "consulta",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => (int) $r["patient_link_id"],
                        "appointment_id" => (int) $r["id"],
                    ],
                );
            }
        } elseif ($trigger === "appointment_waiting_without_start_minutes") {
            $rows = q(
                "SELECT a.id,a.patient_link_id,a.arrived_at,a.start_at,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.arrived_at IS NOT NULL AND a.consultation_started_at IS NULL AND a.consultation_finished_at IS NULL AND a.arrived_at<=DATE_SUB(NOW(), INTERVAL ? MINUTE) AND a.status NOT IN ('cancelado','nao_compareceu','reagendado','atendimento_concluido','finalizado') ORDER BY a.arrived_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $ts = app_storage_timestamp($r["arrived_at"]);
                $out[] = maestro_match_base(
                    [
                        "origem" => "consulta",
                        "consultorio" => $clinic,
                        "paciente" => $r["patient_name"] ?: "paciente",
                        "profissional" => $r["doctor_name"] ?: "profissional",
                        "data" => date_br($r["arrived_at"] ?? ""),
                        "hora" => app_time_br($r["arrived_at"] ?? ""),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "consulta_aguardando",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => (int) $r["patient_link_id"],
                        "appointment_id" => (int) $r["id"],
                    ],
                );
            }
        } elseif ($trigger === "appointment_finished_without_care_hours") {
            $rows = q(
                "SELECT a.id,a.patient_link_id,a.consultation_finished_at,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.consultation_finished_at IS NOT NULL AND a.consultation_finished_at<=DATE_SUB(NOW(), INTERVAL ? HOUR) AND NOT EXISTS (SELECT 1 FROM pi_care c WHERE c.clinic_id=a.clinic_id AND c.appointment_id=a.id AND c.deleted_at IS NULL LIMIT 1) ORDER BY a.consultation_finished_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $ts = app_storage_timestamp($r["consultation_finished_at"]);
                $out[] = maestro_match_base(
                    [
                        "origem" => "consulta",
                        "consultorio" => $clinic,
                        "paciente" => $r["patient_name"] ?: "paciente",
                        "profissional" => $r["doctor_name"] ?: "profissional",
                        "data" => date_br($r["consultation_finished_at"] ?? ""),
                        "hora" => app_time_br(
                            $r["consultation_finished_at"] ?? "",
                        ),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "consulta_sem_registro",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => (int) $r["patient_link_id"],
                        "appointment_id" => (int) $r["id"],
                    ],
                );
            }
        } elseif ($trigger === "appointment_today_without_patient") {
            $rows = q(
                "SELECT id,start_at,reason FROM pi_appointments WHERE clinic_id=? AND DATE(start_at)=CURDATE() AND patient_link_id IS NULL AND status<>'cancelado' ORDER BY start_at ASC LIMIT $limit",
                [$cid],
            )->fetchAll();
            foreach ($rows as $r) {
                $ts = app_storage_timestamp($r["start_at"]);
                $out[] = maestro_match_base(
                    [
                        "origem" => "consulta",
                        "consultorio" => $clinic,
                        "paciente" => "paciente não vinculado",
                        "data" => date_br($r["start_at"] ?? ""),
                        "hora" => app_time_br($r["start_at"] ?? ""),
                        "motivo" => $r["reason"] ?? "",
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "consulta_sem_paciente",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => (int) $r["id"],
                    ],
                );
            }
        } elseif ($trigger === "lead_without_next_action_after_days") {
            $rows = q(
                "SELECT id,name,created_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NULL AND created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY created_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "interessado",
                        "consultorio" => $clinic,
                        "interessado" => $r["name"],
                        "paciente" => $r["name"],
                        "data" => date_br((string) $r["created_at"]),
                        "hora" => "",
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "interessado_sem_retorno",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "lead_next_action_before_days") {
            $day = date("Y-m-d", strtotime("+" . $amount . " days"));
            $rows = q(
                "SELECT id,name,next_action_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NOT NULL AND DATE(next_action_at)=? ORDER BY next_action_at ASC LIMIT $limit",
                [$cid, $day],
            )->fetchAll();
            foreach ($rows as $r) {
                $ts = app_storage_timestamp($r["next_action_at"]);
                $occ = $ts ? date("Ymd", $ts) : str_replace("-", "", $day);
                $out[] = maestro_match_base(
                    [
                        "origem" => "interessado",
                        "consultorio" => $clinic,
                        "interessado" => $r["name"],
                        "paciente" => $r["name"],
                        "data" => date_br($r["next_action_at"]),
                        "hora" => app_time_br($r["next_action_at"]),
                        "dias" => $prazo,
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "interessado_retorno_agendado",
                        "source_entity_id" => (string) $r["id"] . ":" . $occ,
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "lead_next_action_due") {
            $rows = q(
                "SELECT id,name,next_action_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NOT NULL AND next_action_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY next_action_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "interessado",
                        "consultorio" => $clinic,
                        "interessado" => $r["name"],
                        "paciente" => $r["name"],
                        "data" => date_br((string) $r["next_action_at"]),
                        "hora" => "",
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "interessado",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "lead_stalled_stage_after_days") {
            $rows = q(
                "SELECT id,name,stage,COALESCE(updated_at,created_at) AS moved_at FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND COALESCE(updated_at,created_at)<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY moved_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "interessado",
                        "consultorio" => $clinic,
                        "interessado" => $r["name"],
                        "etapa" => document_status_label((string) $r["stage"]),
                        "data" => date_br((string) $r["moved_at"]),
                        "hora" => "",
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "interessado_parado",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "lead_converted_recent") {
            $rows = q(
                "SELECT id,name,COALESCE(updated_at,created_at) AS moved_at FROM pi_leads WHERE clinic_id=? AND stage='convertido' AND COALESCE(updated_at,created_at)>=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY moved_at DESC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "interessado",
                        "consultorio" => $clinic,
                        "interessado" => $r["name"],
                        "data" => date_br((string) $r["moved_at"]),
                        "hora" => "",
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "interessado_convertido",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "patient_incomplete_registration") {
            $rows = q(
                "SELECT pl.id,p.full_name FROM pi_patients pl INNER JOIN pi_persons p ON p.id=pl.person_id WHERE pl.clinic_id=? AND pl.active=1 AND pl.deleted_at IS NULL AND (pl.registration_needs_update=1 OR pl.phone IS NULL OR pl.phone='' OR pl.email IS NULL OR pl.email='' OR pl.address_city IS NULL OR pl.address_city='') ORDER BY pl.updated_at ASC,pl.id ASC LIMIT $limit",
                [$cid],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "paciente",
                        "consultorio" => $clinic,
                        "paciente" => $r["full_name"],
                        "data" => date("d/m/Y"),
                        "hora" => date("H:i"),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "paciente_cadastro",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => (int) $r["id"],
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "patient_without_recent_activity") {
            $rows = q(
                "SELECT pl.id,p.full_name FROM pi_patients pl INNER JOIN pi_persons p ON p.id=pl.person_id WHERE pl.clinic_id=? AND pl.active=1 AND pl.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM pi_care c WHERE c.clinic_id=pl.clinic_id AND c.patient_link_id=pl.id AND c.deleted_at IS NULL AND c.created_at>=DATE_SUB(NOW(), INTERVAL ? DAY)) AND NOT EXISTS (SELECT 1 FROM pi_appointments a WHERE a.clinic_id=pl.clinic_id AND a.patient_link_id=pl.id AND a.start_at>=DATE_SUB(NOW(), INTERVAL ? DAY)) ORDER BY pl.updated_at ASC, pl.id ASC LIMIT $limit",
                [$cid, $amount, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "paciente",
                        "consultorio" => $clinic,
                        "paciente" => $r["full_name"],
                        "dias" => $prazo,
                        "prazo" => $prazo,
                        "data" => date("d/m/Y"),
                        "hora" => date("H:i"),
                    ],
                    [
                        "source_entity" => "paciente",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => (int) $r["id"],
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "patient_birthday_before_days") {
            $target = date("Y-m-d", strtotime("+" . $amount . " days"));
            $md = date("m-d", strtotime($target));
            $year = date("Y", strtotime($target));
            $rows = q(
                "SELECT pl.id AS patient_link_id, p.full_name, p.birth_date FROM pi_patients pl INNER JOIN pi_persons p ON p.id=pl.person_id WHERE pl.clinic_id=? AND pl.active=1 AND pl.deleted_at IS NULL AND p.birth_date IS NOT NULL AND DATE_FORMAT(p.birth_date,'%m-%d')=? ORDER BY p.full_name ASC LIMIT $limit",
                [$cid, $md],
            )->fetchAll();
            foreach ($rows as $r) {
                $birthTs = app_storage_timestamp($r["birth_date"]);
                $out[] = maestro_match_base(
                    [
                        "origem" => "paciente",
                        "consultorio" => $clinic,
                        "paciente" => $r["full_name"] ?: "paciente",
                        "data" => $birthTs
                            ? date("d/m", $birthTs) . "/" . $year
                            : date("d/m/Y", strtotime($target)),
                        "hora" => "",
                        "dias" => $prazo,
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "aniversario_paciente",
                        "source_entity_id" =>
                            (string) $r["patient_link_id"] . ":" . $year,
                        "patient_link_id" => (int) $r["patient_link_id"],
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "document_template_pending_approval") {
            $rows = q(
                "SELECT dt.id,dt.title,dt.status,u.name AS owner_name FROM pi_document_templates dt LEFT JOIN pi_users u ON u.id=dt.owner_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_owner WHERE ur_owner.user_id=u.id AND ur_owner.clinic_id=dt.clinic_id AND ur_owner.active=1) WHERE dt.clinic_id=? AND dt.status='pending_approval' AND COALESCE(dt.updated_at,dt.created_at)<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY COALESCE(dt.updated_at,dt.created_at) ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "modelo",
                        "consultorio" => $clinic,
                        "modelo" => $r["title"],
                        "colaborador" => $r["owner_name"] ?? "",
                        "data" => "",
                        "hora" => "",
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "modelo",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "document_preview_abandoned") {
            $rows = q(
                "SELECT id,title,patient_link_id,updated_at,issued_at FROM pi_documents WHERE clinic_id=? AND document_status='preparado' AND COALESCE(updated_at,issued_at)<=DATE_SUB(NOW(), INTERVAL ? HOUR) ORDER BY COALESCE(updated_at,issued_at) ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $ts = app_storage_timestamp(
                    $r["updated_at"] ?: $r["issued_at"],
                );
                $out[] = maestro_match_base(
                    [
                        "origem" => "documento",
                        "consultorio" => $clinic,
                        "documento" => $r["title"],
                        "paciente" => $r["patient_link_id"]
                            ? patient_display_name(
                                (int) $r["patient_link_id"],
                                $cid,
                            )
                            : "",
                        "data" => date_br(
                            ($r["updated_at"] ?: $r["issued_at"]) ?? "",
                        ),
                        "hora" => app_time_br(
                            ($r["updated_at"] ?: $r["issued_at"]) ?? "",
                        ),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "documento_previa",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" =>
                            (int) ($r["patient_link_id"] ?? 0) ?: null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "document_issued_without_patient") {
            $rows = q(
                "SELECT id,title,issued_at,document_identifier FROM pi_documents WHERE clinic_id=? AND document_status='emitido' AND patient_link_id IS NULL AND issued_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY issued_at DESC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $ts = app_storage_timestamp($r["issued_at"]);
                $out[] = maestro_match_base(
                    [
                        "origem" => "documento",
                        "consultorio" => $clinic,
                        "documento" => $r["title"],
                        "identificador" => $r["document_identifier"] ?? "",
                        "data" => date_br($r["issued_at"] ?? ""),
                        "hora" => app_time_br($r["issued_at"] ?? ""),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "documento_sem_paciente",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "document_issued_after_days") {
            $day = date("Y-m-d", strtotime("-" . $amount . " days"));
            $rows = q(
                "SELECT id,title,patient_link_id,issued_at,document_identifier FROM pi_documents WHERE clinic_id=? AND document_status='emitido' AND DATE(issued_at)=? ORDER BY issued_at DESC LIMIT $limit",
                [$cid, $day],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "documento",
                        "consultorio" => $clinic,
                        "documento" => $r["title"],
                        "identificador" => $r["document_identifier"] ?? "",
                        "paciente" => $r["patient_link_id"]
                            ? patient_display_name(
                                (int) $r["patient_link_id"],
                                $cid,
                            )
                            : "",
                        "data" => date_br((string) $r["issued_at"]),
                        "hora" => app_time_br($r["issued_at"]),
                        "dias" => $prazo,
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "documento",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" =>
                            (int) ($r["patient_link_id"] ?? 0) ?: null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "task_due_in_days") {
            $day = date("Y-m-d", strtotime("+" . $amount . " days"));
            $rows = q(
                "SELECT id,title,due_at FROM pi_tasks WHERE clinic_id=? AND status IN ($activeTask) AND due_at IS NOT NULL AND DATE(due_at)=? ORDER BY due_at ASC LIMIT $limit",
                [$cid, $day],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "tarefa",
                        "consultorio" => $clinic,
                        "tarefa" => $r["title"],
                        "data" => date_br((string) $r["due_at"]),
                        "hora" => $r["due_at"] ? app_time_br($r["due_at"]) : "",
                        "dias" => $prazo,
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "tarefa",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "task_overdue_after_days") {
            $rows = q(
                "SELECT id,title,due_at FROM pi_tasks WHERE clinic_id=? AND status IN ($activeTask) AND due_at IS NOT NULL AND due_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY due_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "tarefa",
                        "consultorio" => $clinic,
                        "tarefa" => $r["title"],
                        "data" => date_br((string) $r["due_at"]),
                        "hora" => app_time_br($r["due_at"]),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "tarefa_vencida",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "task_stalled_after_days") {
            $rows = q(
                "SELECT id,title,COALESCE(updated_at,started_at,created_at) AS ref_at FROM pi_tasks WHERE clinic_id=? AND status='em_andamento' AND COALESCE(updated_at,started_at,created_at)<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY ref_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "tarefa",
                        "consultorio" => $clinic,
                        "tarefa" => $r["title"],
                        "data" => date_br((string) $r["ref_at"]),
                        "hora" => app_time_br($r["ref_at"]),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "tarefa_parada",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "task_returned_queue_recent") {
            $rows = q(
                "SELECT t.id,t.title,e.created_at FROM pi_task_events e INNER JOIN pi_tasks t ON t.id=e.task_id AND t.clinic_id=e.clinic_id WHERE e.clinic_id=? AND e.event_key='devolvida_fila' AND e.created_at>=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY e.created_at DESC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "tarefa",
                        "consultorio" => $clinic,
                        "tarefa" => $r["title"],
                        "data" => date_br((string) $r["created_at"]),
                        "hora" => app_time_br($r["created_at"]),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "tarefa_devolvida",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "revenue_due_in_days") {
            $day = date("Y-m-d", strtotime("+" . $amount . " days"));
            $rows = q(
                "SELECT id,title,patient_link_id,expected_at,amount_cents FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND DATE(expected_at)=? ORDER BY expected_at ASC LIMIT $limit",
                [$cid, $day],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "receita",
                        "consultorio" => $clinic,
                        "receita" => $r["title"],
                        "valor" => money_br((int) $r["amount_cents"]),
                        "paciente" => $r["patient_link_id"]
                            ? patient_display_name(
                                (int) $r["patient_link_id"],
                                $cid,
                            )
                            : "",
                        "data" => date_br((string) $r["expected_at"]),
                        "hora" => app_time_br($r["expected_at"]),
                        "dias" => $prazo,
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "receita",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" =>
                            (int) ($r["patient_link_id"] ?? 0) ?: null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "revenue_overdue_after_days") {
            $rows = q(
                "SELECT id,title,patient_link_id,expected_at,amount_cents FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND expected_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY expected_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "receita",
                        "consultorio" => $clinic,
                        "receita" => $r["title"],
                        "valor" => money_br((int) $r["amount_cents"]),
                        "paciente" => $r["patient_link_id"]
                            ? patient_display_name(
                                (int) $r["patient_link_id"],
                                $cid,
                            )
                            : "",
                        "data" => date_br((string) $r["expected_at"]),
                        "hora" => app_time_br($r["expected_at"]),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "receita_vencida",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" =>
                            (int) ($r["patient_link_id"] ?? 0) ?: null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "expense_due_in_days") {
            $day = date("Y-m-d", strtotime("+" . $amount . " days"));
            $rows = q(
                "SELECT id,title,due_at,amount_cents FROM pi_financial_expenses WHERE clinic_id=? AND status='prevista' AND due_at=? ORDER BY due_at ASC LIMIT $limit",
                [$cid, $day],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "despesa",
                        "consultorio" => $clinic,
                        "despesa" => $r["title"],
                        "valor" => money_br((int) $r["amount_cents"]),
                        "data" => date_br((string) $r["due_at"]),
                        "hora" => "",
                        "dias" => $prazo,
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "despesa",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "expense_overdue_after_days") {
            $rows = q(
                "SELECT id,title,due_at,amount_cents FROM pi_financial_expenses WHERE clinic_id=? AND status='prevista' AND due_at IS NOT NULL AND due_at<=DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY due_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "despesa",
                        "consultorio" => $clinic,
                        "despesa" => $r["title"],
                        "valor" => money_br((int) $r["amount_cents"]),
                        "data" => date_br((string) $r["due_at"]),
                        "hora" => "",
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "despesa_vencida",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "notice_unread_after_days") {
            $rows = q(
                "SELECT n.id,n.title,n.created_at FROM pi_notices n WHERE n.clinic_id=? AND n.requires_ack=1 AND n.created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND (r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1) ORDER BY n.created_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "aviso",
                        "consultorio" => $clinic,
                        "notificacao" => $r["title"],
                        "data" => date_br((string) $r["created_at"]),
                        "hora" => app_time_br($r["created_at"]),
                        "dias" => $prazo,
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "notificacao",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "scope_violation_recent") {
            $rows = q(
                "SELECT violation_key,sql_fingerprint,route,MAX(details) AS details,MAX(created_at) AS created_at,COUNT(*) AS occurrences FROM pi_scope_violations WHERE clinic_id=? AND violation_key<>'write_in_read_only' AND created_at>=DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY violation_key,sql_fingerprint,route ORDER BY created_at DESC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "segurança",
                        "consultorio" => $clinic,
                        "evento" => $r["violation_key"],
                        "rota" => $r["route"] ?? "",
                        "ocorrencias" => (int) ($r["occurrences"] ?? 1),
                        "detalhes" => function_exists(
                            "scope_violation_detail_summary",
                        )
                            ? scope_violation_detail_summary(
                                $r["details"] ?? "",
                            )
                            : (string) ($r["details"] ?? ""),
                        "data" => date_br((string) $r["created_at"]),
                        "hora" => app_time_br($r["created_at"]),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "seguranca_integridade",
                        "source_entity_id" =>
                            mb_substr((string) $r["violation_key"], 0, 40) .
                            ":" .
                            mb_substr(
                                (string) $r["sql_fingerprint"],
                                0,
                                32,
                            ),
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        } elseif ($trigger === "technical_error_open_after_days") {
            $rows = q(
                "SELECT id,message,route,created_at FROM pi_error_events WHERE clinic_id=? AND resolved_at IS NULL AND created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY created_at ASC LIMIT $limit",
                [$cid, $amount],
            )->fetchAll();
            foreach ($rows as $r) {
                $out[] = maestro_match_base(
                    [
                        "origem" => "segurança",
                        "consultorio" => $clinic,
                        "evento" => "erro técnico",
                        "detalhes" => mb_substr((string) $r["message"], 0, 120),
                        "rota" => $r["route"] ?? "",
                        "data" => date_br((string) $r["created_at"]),
                        "hora" => app_time_br($r["created_at"]),
                        "prazo" => $prazo,
                    ],
                    [
                        "source_entity" => "erro_tecnico",
                        "source_entity_id" => (string) $r["id"],
                        "patient_link_id" => null,
                        "appointment_id" => null,
                    ],
                );
            }
        }
    } catch (Throwable $e) {
        error_log(
            "[Prontoo Maestro candidates] " .
                $trigger .
                " | " .
                $e->getMessage(),
        );
    }
    return $out;
}
function maestro_create_action(array $rule, array $match): array
{

    $cid = (int) $rule["clinic_id"];
    $act = maestro_decode_json($rule["action_json"] ?? "");
    $vars = $match["variables"] ?? [];
    $title = maestro_text(
        maestro_apply_placeholders(
            (string) ($act["title"] ?? "Ação da rotina"),
            $vars,
        ),
        180,
    );
    $body = maestro_template(
        maestro_apply_placeholders((string) ($act["description"] ?? ""), $vars),
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
    if (
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
    if ($scope !== "role") {
        $role = null;
    }
    if ($scope !== "user") {
        $userId = null;
    }
    if ((string) $rule["action_type"] === "create_notice") {
        q(
            "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                $title,
                $body,
                1,
                $scope === "clinic" ? "all" : $scope,
                $role,
                $userId,
                maestro_actor_id(),
            ],
        );
        $id = db_last_insert_id();
        counter_inc("notices_total");
        clinic_metric_inc($cid, "notices");
        audit("maestro_notificacao_criada", "comunicado", $id, [
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
    $taskId = create_workflow_task(
        $cid,
        $title,
        $body,
        $userId,
        $match["patient_link_id"] ?? null,
        $match["appointment_id"] ?? null,
        $sourceEvent,
        $sourceEntity,
        $sourceId,
        maestro_due_dt((int) ($act["due_offset_days"] ?? 0)),
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
function maestro_routine_key(array $rule): string
{

    return "rule:" .
        (int) $rule["id"] .
        ":" .
        (string) $rule["trigger_event"] .
        ":" .
        (string) $rule["action_type"];
}
function maestro_rule_score(array $rule, ?array $stat = null): float
{

    $priority = (int) ($rule["priority"] ?? 50);
    $next = $rule["next_run_at"]
        ? app_storage_timestamp($rule["next_run_at"])
        : 0;
    $lag = $next
        ? max(0, min(240, (time() - $next) / 60))
        : PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES;
    $yield = (float) ($stat["ewma_yield"] ?? 0);
    $duration = max(1, (float) ($stat["ewma_duration_ms"] ?? 100));
    return $priority + $lag + min(30, $yield * 8) - min(25, $duration / 500);
}
function maestro_ewma_observation(
    ?float $previous,
    float $observation,
    bool $skipped = false,
    float $alpha = 0.25,
): ?float {

    if ($skipped) {
        return $previous;
    }
    $alpha = max(0.0, min(1.0, $alpha));
    return $previous === null
        ? $observation
        : ($previous * (1 - $alpha)) + ($observation * $alpha);
}
function maestro_stats_update(
    string $key,
    float $duration,
    int $created,
    float $score,
    bool $skipped = false,
): void {

    db_tx(function () use (
        $key,
        $duration,
        $created,
        $score,
        $skipped,
    ): void {

        $old = one(
            "SELECT * FROM pi_maestro_job_stats WHERE routine_key=? FOR UPDATE",
            [$key],
        );
        if ($skipped) {
            if ($old) {
                q(
                    "UPDATE pi_maestro_job_stats SET skip_count=skip_count+1,last_score=?,updated_at=NOW() WHERE routine_key=?",
                    [$score, $key],
                );
            } else {
                q(
                    "INSERT INTO pi_maestro_job_stats (routine_key,ewma_duration_ms,ewma_yield,run_count,skip_count,last_score,last_run_at,updated_at) VALUES (?,0,0,0,1,?,NULL,NOW())",
                    [$key, $score],
                );
            }
            return;
        }
        $hasObservation = $old && (int) ($old["run_count"] ?? 0) > 0;
        $dur = (float) maestro_ewma_observation(
            $hasObservation ? (float) $old["ewma_duration_ms"] : null,
            max(0.0, $duration),
        );
        $yield = (float) maestro_ewma_observation(
            $hasObservation ? (float) $old["ewma_yield"] : null,
            max(0, $created),
        );
        if ($old) {
            q(
                "UPDATE pi_maestro_job_stats SET ewma_duration_ms=?,ewma_yield=?,run_count=run_count+1,last_score=?,last_run_at=NOW(),updated_at=NOW() WHERE routine_key=?",
                [$dur, $yield, $score, $key],
            );
        } else {
            q(
                "INSERT INTO pi_maestro_job_stats (routine_key,ewma_duration_ms,ewma_yield,run_count,skip_count,last_score,last_run_at,updated_at) VALUES (?,?,?,1,0,?,NOW(),NOW())",
                [$key, $dur, $yield, $score],
            );
        }
    });
}
function maestro_with_guarded_clinic(int $cid, callable $fn): mixed
{

    return with_scope_guard_clinic(
        $cid,
        static  fn() => with_read_only_guard_disabled($fn),
    );
}
function maestro_run_rule(array $rule, float $deadline): array
{

    $cid = (int) ($rule["clinic_id"] ?? 0);
    return maestro_with_guarded_clinic(
        $cid,
        static  fn() => maestro_run_rule_scoped($rule, $deadline),
    );
}
function maestro_run_rule_scoped(array $rule, float $deadline): array
{

    $cid = (int) $rule["clinic_id"];
    if (clinic_read_only_db($cid)) {
        return ["created" => 0, "seen" => 0, "errors" => 0];
    }
    $started = microtime(true);
    $created = 0;
    $seen = 0;
    $errors = 0;
    $matches = maestro_fetch_candidates($rule, 20);
    foreach ($matches as $m) {
        if (microtime(true) >= $deadline) {
            break;
        }
        $seen++;
        $source = (string) ($m["source_entity"] ?? "registro");
        $sourceId = (string) ($m["source_entity_id"] ?? "0");
        $actionKey = (string) $rule["action_type"] . ":" . (int) $rule["id"];
        $ins = q(
            "INSERT IGNORE INTO pi_maestro_executions (clinic_id,rule_id,source_entity,source_entity_id,action_key,status,message,executed_at) VALUES (?,?,?,?,?,'running','Em processamento',NOW())",
            [$cid, (int) $rule["id"], $source, $sourceId, $actionKey],
        );
        $claimed = $ins->rowCount() > 0;
        if (!$claimed) {
            $reclaimed = q(
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
            db_begin_transaction();
            $res = maestro_create_action($rule, $m);
            q(
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
            db_commit();
            $created++;
        } catch (Throwable $e) {
            if (pdo()->inTransaction()) {
                db_rollback();
            }
            $errors++;
            q(
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
    q(
        "UPDATE pi_maestro_rules SET last_run_at=NOW(), next_run_at=DATE_ADD(NOW(), INTERVAL min_interval_minutes MINUTE), run_count=run_count+1, updated_at=NOW() WHERE id=? AND clinic_id=?",
        [(int) $rule["id"], $cid],
    );
    return [
        "created" => $created,
        "seen" => $seen,
        "errors" => $errors,
        "duration_ms" => (int) round((microtime(true) - $started) * 1000),
    ];
}
function maestro_cron_run(int $budgetMs = PRONTOO_MAESTRO_CRON_BUDGET_MS): array
{

    if (!has_cfg()) {
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
    maestro_ensure_schema();
    $start = microtime(true);
    $maxCycleSeconds = max(30, PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES * 60 - 30);
    $deadline = $start + max(5, min($maxCycleSeconds, $budgetMs / 1000));
    $lockPath = storage_path("maestro.lock");
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
        $rules = q(
            "SELECT * FROM pi_maestro_rules WHERE active=1 AND (next_run_at IS NULL OR next_run_at<=NOW()) ORDER BY priority DESC, COALESCE(next_run_at,created_at) ASC LIMIT 80",
        )->fetchAll();
        $seen = count($rules);
        $stats = [];
        if ($rules) {
            $keys = array_map("maestro_routine_key", $rules);
            $ph = implode(",", array_fill(0, count($keys), "?"));
            foreach (
                q(
                    "SELECT * FROM pi_maestro_job_stats WHERE routine_key IN ($ph)",
                    $keys,
                )->fetchAll()
                as $s
            ) {
                $stats[(string) $s["routine_key"]] = $s;
            }
            usort($rules, function ($a, $b) use ($stats) {

                return maestro_rule_score(
                    $b,
                    $stats[maestro_routine_key($b)] ?? null,
                ) <=>
                    maestro_rule_score(
                        $a,
                        $stats[maestro_routine_key($a)] ?? null,
                    );
            });
        }
        foreach ($rules as $rule) {
            if (microtime(true) >= $deadline) {
                $deferred++;
                continue;
            }
            $key = maestro_routine_key($rule);
            $score = maestro_rule_score($rule, $stats[$key] ?? null);
            $remainingMs = max(0, ($deadline - microtime(true)) * 1000);
            $expected = (float) ($stats[$key]["ewma_duration_ms"] ?? 80);
            if ($expected > $remainingMs && $score < 90) {
                $deferred++;
                maestro_with_guarded_clinic(
                    (int) $rule["clinic_id"],
                    static  fn() => q(
                        "UPDATE pi_maestro_rules SET next_run_at=DATE_ADD(NOW(), INTERVAL " .
                            (int) PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES .
                            " MINUTE) WHERE id=? AND clinic_id=?",
                        [(int) $rule["id"], (int) $rule["clinic_id"]],
                    ),
                );
                maestro_stats_update($key, 0, 0, $score, true);
                continue;
            }
            $r = maestro_run_rule($rule, $deadline);
            $run++;
            $created += (int) $r["created"];
            $errors += (int) ($r["errors"] ?? 0);
            maestro_stats_update(
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
    $duration = (int) round((microtime(true) - $start) * 1000);
    q(
        "INSERT INTO pi_maestro_job_runs (started_at,finished_at,duration_ms,rules_seen,rules_run,actions_created,deferred_count,errors_count,success,load_score,note) VALUES (FROM_UNIXTIME(?),NOW(),?,?,?,?,?,?,?,?,?)",
        [
            $start,
            $duration,
            $seen,
            $run,
            $created,
            $deferred,
            $errors,
            $success ? 1 : 0,
            $duration > 0 ? round($created / max(1, $duration / 1000), 3) : 0,
            $note,
        ],
    );
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
function maestro_record_cron_failure(float $startedAt, string $note): void
{

    if (!has_cfg()) {
        return;
    }
    try {
        maestro_ensure_schema();
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
        $duration = (int) max(0, round((microtime(true) - $startedAt) * 1000));
        q(
            "INSERT INTO pi_maestro_job_runs (started_at,finished_at,duration_ms,rules_seen,rules_run,actions_created,deferred_count,errors_count,success,load_score,note) VALUES (FROM_UNIXTIME(?),NOW(),?,0,0,0,0,1,0,0,?)",
            [$startedAt, $duration, $note],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo cron failure record] " . $e->getMessage());
    }
}
