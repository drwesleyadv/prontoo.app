<?php
declare(strict_types=1);

namespace Prontoo\Domain\AuditActivity;

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

final class AuditActivityDomainOperations03
{
    private function __construct()
    {
    }

    public static function audit_direct_events(): array
    
    {
    
        return [
            "janela_aberta",
            "consulta_agendada",
            "consulta_alterada",
            "consulta_excluida",
            "consulta_iniciada",
            "paciente_chegou",
            "agenda_bloqueada",
            "bloqueio_alterado",
            "bloqueio_removido",
            "lead_criado",
            "lead_atualizado",
            "paciente_salvo",
            "paciente_excluido",
            "paciente_recuperado",
            "registro_clinico",
            "prontuario_alterado",
            "prontuario_visualizado",
            "receita_visualizada",
            "documento_rascunho_criado",
            "documento_emitido",
            "documento_descartado",
            "documento_visualizado",
            "documento_impresso",
            "modelo_documento_criado",
            "modelo_documento_atualizado",
            "modelo_documento_aprovado",
            "modelo_documento_rejeitado",
            "procedimento_criado",
            "procedimento_atualizado",
            "procedimento_status",
            "tarefa_criada",
            "tarefa_iniciada",
            "tarefa_concluida",
            "tarefa_devolvida_fila",
            "comentario_tarefa_criado",
            "comentario_tarefa_editado",
            "comentario_tarefa_excluido",
            "notificacao_tarefa_individual",
            "comunicado_criado",
            "leitura_confirmada",
            "usuario_salvo",
            "usuario_status",
            "usuario_desativado",
            "senha_redefinida",
            "permissoes_atualizadas",
            "consultorio_criado",
            "consultorio_atualizado",
            "clinica_criada",
            "clinica_atualizada",
            "clinica_status",
            "onboarding_concluido",
            "entrada_realizada",
            "entrada_automatica_dispositivo",
            "saida_realizada",
            "login_clinica_pendente",
            "login_credencial_pendente",
            "falha_entrada",
            "acesso_negado",
            "csrf_bloqueado",
            "bloqueio_login_removido",
            "erro_marcado_resolvido",
            "aviso_global_criado",
            "aviso_global_status",
            "manutencao_atualizada",
            "config_global_atualizada",
            "assinatura_atualizada",
            "somente_leitura_bloqueio",
            "meta_financeira_salva",
            "conta_bancaria_criada",
            "forma_pagamento_salva",
            "credor_salvo",
            "despesa_cadastrada",
            "recebivel_destinado",
        ];
    
    }

    public static function audit_task_name(array $ctx): string
    
    {
    
        $t = trim(
            (string) ($ctx["task_title"] ??
                ($ctx["titulo"] ?? ($ctx["title"] ?? ""))),
        );
        return $t !== "" ? $t : "tarefa";
    
    }

    public static function audit_task_sentence(
        string $who,
        string $verb,
        array $ctx,
        string $suffix = "",
    ): string 
    {
    
        $task = audit_task_name($ctx);
        $txt = $who . " " . $verb . " tarefa " . $task;
        $patient = mb_trim((string) ($ctx["patient_name"] ?? ""));
        if (
            $patient !== "" &&
            !str_contains(mb_strtolower($txt), mb_strtolower($patient))
        ) {
            $txt .= " para " . $patient;
        }
        if ($suffix !== "") {
            $txt .= " " . $suffix;
        }
        return $txt . ".";
    
    }

    public static function audit_notice_name(array $ctx): string
    
    {
    
        $t = trim(
            (string) ($ctx["notice_title"] ??
                ($ctx["titulo"] ?? ($ctx["title"] ?? ""))),
        );
        return $t !== "" ? $t : "comunicado";
    
    }

    public static function audit_lead_name(array $ctx): string
    
    {
    
        $t = trim(
            (string) ($ctx["target_name"] ??
                ($ctx["name"] ?? ($ctx["nome"] ?? ""))),
        );
        return $t !== "" ? $t : "interessado";
    
    }

    public static function audit_status_verb(
        array $ctx,
        string $active = "ativou",
        string $inactive = "desativou",
        string $changed = "alterou",
    ): string 
    {
    
        $s = mb_strtolower(
            mb_trim((string) ($ctx["status"] ?? ($ctx["active"] ?? ""))),
        );
        if (
            in_array(
                $s,
                ["ativo", "ativa", "1", "sim", "true", "liberado", "liberada"],
                true,
            )
        ) {
            return $active;
        }
        if (
            in_array(
                $s,
                [
                    "inativo",
                    "inativa",
                    "0",
                    "nao",
                    "não",
                    "false",
                    "bloqueado",
                    "bloqueada",
                ],
                true,
            )
        ) {
            return $inactive;
        }
        return $changed;
    
    }

    public static function audit_route_name(array $ctx): string
    
    {
    
        $r = trim(
            (string) ($ctx["janela"] ?? ($ctx["rota"] ?? ($ctx["route"] ?? ""))),
        );
        return $r !== "" ? $r : "função restrita";
    
    }

    public static function audit_global_notice_title(array $ctx): string
    
    {
    
        $t = trim(
            (string) ($ctx["notice_title"] ??
                ($ctx["title"] ?? ($ctx["titulo"] ?? ""))),
        );
        return $t !== "" ? $t : "aviso global";
    
    }

    public static function audit_subscription_target(array $ctx): string
    
    {
    
        $cl = audit_clinic_target($ctx);
        $status = trim(
            (string) ($ctx["subscription_status"] ?? ($ctx["status"] ?? "")),
        );
        return $status !== "" ? $cl . " para " . $status : $cl;
    
    }

    public static function event_label(string $e): string
    
    {
    
        return [
            "janela_aberta" => "Tela consultada",
            "login_carregado" => "Login carregado",
            "autoteste_aviso" => "Aviso de autoteste",
            "consulta_agendada" => "Consulta criada",
            "consulta_alterada" => "Consulta modificada",
            "consulta_excluida" => "Consulta excluída/cancelada",
            "consulta_iniciada" => "Consulta iniciada",
            "paciente_chegou" => "Chegada registrada",
            "agenda_bloqueada" => "Bloqueio criado",
            "bloqueio_alterado" => "Bloqueio modificado",
            "bloqueio_removido" => "Bloqueio excluído",
            "lead_criado" => "Interessado criado",
            "lead_atualizado" => "Interessado modificado",
            "paciente_salvo" => "Paciente criado/modificado",
            "paciente_excluido" => "Paciente excluído",
            "paciente_recuperado" => "Paciente recuperado",
            "aba_paciente_criada" => "Aba de paciente criada",
            "responsavel_legal_salvo" => "Responsável Legal salvo",
            "responsavel_legal_removido" => "Responsável Legal removido",
            "registro_clinico" => "Prontuário modificado",
            "prontuario_alterado" => "Prontuário modificado",
            "prontuario_visualizado" => "Prontuário consultado",
            "receita_visualizada" => "Receita consultada",
            "documento_rascunho_criado" => "Rascunho de documento criado",
            "documento_emitido" => "Documento emitido",
            "documento_descartado" => "Rascunho excluído",
            "documento_visualizado" => "Documento consultado",
            "documento_impresso" => "Documento impresso",
            "modelo_documento_criado" => "Modelo criado",
            "modelo_documento_atualizado" => "Modelo modificado",
            "modelo_documento_aprovado" => "Modelo aprovado",
            "modelo_documento_rejeitado" => "Modelo rejeitado",
            "procedimento_criado" => "Procedimento criado",
            "procedimento_atualizado" => "Procedimento modificado",
            "procedimento_status" => "Status de procedimento modificado",
            "tarefa_criada" => "Tarefa criada",
            "tarefa_iniciada" => "Tarefa iniciada",
            "tarefa_concluida" => "Tarefa concluída",
            "tarefa_devolvida_fila" => "Tarefa devolvida",
            "comentario_tarefa_criado" => "Comentário criado",
            "comentario_tarefa_editado" => "Comentário modificado",
            "comentario_tarefa_excluido" => "Comentário excluído",
            "notificacao_tarefa_individual" => "Aviso de tarefa",
            "comunicado_criado" => "Comunicado criado",
            "leitura_confirmada" => "Leitura confirmada",
            "usuario_salvo" => "Colaborador criado/modificado",
            "usuario_status" => "Status de colaborador modificado",
            "usuario_desativado" => "Colaborador desativado",
            "senha_redefinida" => "Senha modificada",
            "permissoes_atualizadas" => "Permissões modificadas",
            "consultorio_criado" => "Consultório criado",
            "consultorio_atualizado" => "Consultório modificado",
            "clinica_criada" => "Consultório criado",
            "clinica_atualizada" => "Consultório modificado",
            "clinica_status" => "Status do consultório modificado",
            "entrada_realizada" => "Entrada realizada",
            "entrada_automatica_dispositivo" =>
                "Entrada automática por dispositivo",
            "saida_realizada" => "Saída realizada",
            "falha_entrada" => "Falha de entrada",
            "acesso_negado" => "Consulta negada",
            "csrf_bloqueado" => "Ação bloqueada",
            "bloqueio_login_removido" => "Bloqueio de entrada removido",
            "erro_marcado_resolvido" => "Incidente resolvido",
            "aviso_global_criado" => "Aviso global criada",
            "aviso_global_status" => "Status de aviso global",
            "manutencao_atualizada" => "Manutenção modificada",
            "config_global_atualizada" => "Configuração global modificada",
            "assinatura_ativada" => "Assinatura ativada",
            "assinatura_desativada" => "Assinatura desativada",
            "assinatura_atualizada" => "Assinatura modificada",
            "assinatura_pagamento_informado" => "Pagamento informado",
            "assinatura_pagamento_confirmado" => "Pagamento confirmado",
            "assinatura_pagamento_nao_confirmado" => "Pagamento recusado",
            "somente_leitura_bloqueio" => "Bloqueio por assinatura",
            "onboarding_concluido" => "Configuração inicial concluída",
            "meta_financeira_salva" => "Meta financeira modificada",
            "conta_bancaria_criada" => "Conta financeira criada",
            "forma_pagamento_salva" => "Forma de pagamento salva",
            "credor_salvo" => "Credor salvo",
            "despesa_cadastrada" => "Despesa criada",
            "recebivel_destinado" => "Recebível destinado",
        ][$e] ?? str_replace("_", " ", ucfirst($e));
    
    }

    public static function event_icon(string $e): string
    
    {
    
        return [
            "janela_aberta" => "visibility",
            "login_carregado" => "login",
            "autoteste_aviso" => "health_and_safety",
            "entrada_realizada" => "login",
            "entrada_automatica_dispositivo" => "devices",
            "saida_realizada" => "logout",
            "falha_entrada" => "lock",
            "consulta_agendada" => "event_available",
            "consulta_alterada" => "edit_calendar",
            "consulta_excluida" => "event_busy",
            "consulta_iniciada" => "play_circle",
            "paciente_chegou" => "how_to_reg",
            "agenda_bloqueada" => "event_busy",
            "bloqueio_alterado" => "edit_calendar",
            "bloqueio_removido" => "event_available",
            "lead_criado" => "person_add",
            "lead_atualizado" => "manage_accounts",
            "paciente_salvo" => "patient_list",
            "paciente_excluido" => "delete",
            "paciente_recuperado" => "restore",
            "aba_paciente_criada" => "add_circle",
            "responsavel_legal_salvo" => "supervisor_account",
            "responsavel_legal_removido" => "person_remove",
            "registro_clinico" => "clinical_notes",
            "prontuario_alterado" => "clinical_notes",
            "prontuario_visualizado" => "folder_open",
            "receita_visualizada" => "medication",
            "documento_rascunho_criado" => "note_add",
            "documento_emitido" => "description",
            "documento_descartado" => "delete_sweep",
            "documento_visualizado" => "visibility",
            "documento_impresso" => "print",
            "modelo_documento_criado" => "post_add",
            "modelo_documento_atualizado" => "edit_document",
            "modelo_documento_aprovado" => "verified",
            "modelo_documento_rejeitado" => "block",
            "procedimento_criado" => "add_task",
            "procedimento_atualizado" => "edit_note",
            "procedimento_status" => "toggle_on",
            "tarefa_criada" => "add_task",
            "tarefa_iniciada" => "play_arrow",
            "tarefa_concluida" => "task_alt",
            "tarefa_devolvida_fila" => "undo",
            "comentario_tarefa_criado" => "add_comment",
            "comentario_tarefa_editado" => "edit_note",
            "comentario_tarefa_excluido" => "delete",
            "notificacao_tarefa_individual" => "notifications_active",
            "comunicado_criado" => "campaign",
            "leitura_confirmada" => "mark_email_read",
            "usuario_salvo" => "group_add",
            "usuario_status" => "toggle_on",
            "usuario_desativado" => "person_off",
            "senha_redefinida" => "password",
            "permissoes_atualizadas" => "shield_person",
            "consultorio_criado" => "add_business",
            "consultorio_atualizado" => "home_health",
            "clinica_criada" => "add_business",
            "clinica_atualizada" => "home_health",
            "clinica_status" => "domain_verification",
            "acesso_negado" => "gpp_bad",
            "csrf_bloqueado" => "security",
            "bloqueio_login_removido" => "lock_open",
            "erro_marcado_resolvido" => "bug_report",
            "aviso_global_criado" => "campaign",
            "aviso_global_status" => "campaign",
            "manutencao_atualizada" => "engineering",
            "config_global_atualizada" => "tune",
            "assinatura_ativada" => "paid",
            "assinatura_desativada" => "money_off",
            "assinatura_atualizada" => "payments",
            "assinatura_pagamento_informado" => "payments",
            "assinatura_pagamento_confirmado" => "verified",
            "assinatura_pagamento_nao_confirmado" => "report",
            "somente_leitura_bloqueio" => "lock",
            "onboarding_concluido" => "playlist_add_check",
            "meta_financeira_salva" => "finance_mode",
            "conta_bancaria_criada" => "account_balance",
            "forma_pagamento_salva" => "payments",
            "credor_salvo" => "business_center",
            "despesa_cadastrada" => "receipt_long",
            "recebivel_destinado" => "account_balance_wallet",
        ][$e] ?? "radio_button_checked";
    
    }

    public static function entity_label(?string $e): string
    
    {
    
        return [
            "clinica" => "consultório",
            "usuario" => "colaborador",
            "consulta" => "consulta",
            "paciente" => "paciente",
            "lead" => "interessado",
            "bloqueio" => "bloqueio",
            "tarefa" => "tarefa",
            "comunicado" => "aviso",
            "prontuario" => "prontuário",
            "documento" => "documento",
            "janela" => "janela",
            "rota" => "rota",
            "procedimento" => "procedimento",
            "sistema" => "sistema",
            "consultorio" => "consultório",
            "permissoes" => "permissões",
            "erro" => "erro",
            "aviso_global" => "aviso global",
            "manutencao" => "manutenção",
            "configuracao" => "configuração",
            "assinatura" => "assinatura",
            "financeiro" => "financeiro",
            "login" => "entrada",
            "seguranca" => "segurança",
        ][$e ?? ""] ?? "sistema";
    
    }
}
