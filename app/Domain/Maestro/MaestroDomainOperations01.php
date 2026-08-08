<?php
declare(strict_types=1);

namespace Prontoo\Domain\Maestro;

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

final class MaestroDomainOperations01
{
    private function __construct()
    {
    }

    public static function maestro_trigger_catalog(): array
    
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

    public static function maestro_action_types(): array
    
    {
    
        return [
            "create_task" => "Distribuir tarefa",
            "create_notice" => "Emitir aviso",
        ];
    
    }
}
