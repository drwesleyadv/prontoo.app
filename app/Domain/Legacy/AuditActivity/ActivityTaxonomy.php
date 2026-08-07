<?php
declare(strict_types=1);

namespace Prontoo\Domain\Legacy\AuditActivity;

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

final class ActivityTaxonomy
{
    private function __construct()
    {
    }

    public static function activity_axis_for_event(string $event): array
    
    {
    
        $map = [
            "janela_aberta" => ["Consultar", "read", "visibility"],
            "login_carregado" => ["Consultar", "read", "login"],
            "autoteste_aviso" => ["Consultar", "read", "health_and_safety"],
            "entrada_realizada" => ["Consultar", "read", "login"],
            "entrada_automatica_dispositivo" => ["Consultar", "read", "devices"],
            "saida_realizada" => ["Consultar", "read", "logout"],
            "falha_entrada" => ["Consultar", "read", "lock"],
            "login_clinica_pendente" => ["Consultar", "read", "account_tree"],
            "login_credencial_pendente" => ["Consultar", "read", "switch_account"],
            "acesso_negado" => ["Consultar", "read", "visibility_off"],
            "csrf_bloqueado" => ["Modificar", "update", "block"],
            "consulta_agendada" => ["Criar", "create", "event_available"],
            "consulta_alterada" => ["Modificar", "update", "edit_calendar"],
            "consulta_excluida" => ["Excluir", "delete", "event_busy"],
            "consulta_iniciada" => ["Modificar", "update", "play_circle"],
            "paciente_chegou" => ["Modificar", "update", "how_to_reg"],
            "agenda_bloqueada" => ["Criar", "create", "event_busy"],
            "bloqueio_alterado" => ["Modificar", "update", "edit_calendar"],
            "bloqueio_removido" => ["Excluir", "delete", "event_available"],
            "lead_criado" => ["Criar", "create", "person_add"],
            "lead_atualizado" => ["Modificar", "update", "manage_accounts"],
            "paciente_salvo" => ["Modificar", "update", "patient_list"],
            "paciente_excluido" => ["Excluir", "delete", "delete"],
            "paciente_recuperado" => ["Modificar", "update", "restore"],
            "aba_paciente_criada" => ["Criar", "create", "add_circle"],
            "responsavel_legal_salvo" => [
                "Modificar",
                "update",
                "supervisor_account",
            ],
            "responsavel_legal_removido" => ["Excluir", "delete", "person_remove"],
            "registro_clinico" => ["Modificar", "update", "clinical_notes"],
            "prontuario_alterado" => ["Modificar", "update", "clinical_notes"],
            "prontuario_visualizado" => ["Consultar", "read", "folder_open"],
            "receita_visualizada" => ["Consultar", "read", "medication"],
            "documento_rascunho_criado" => ["Criar", "create", "note_add"],
            "documento_emitido" => ["Criar", "create", "description"],
            "documento_descartado" => ["Excluir", "delete", "delete_sweep"],
            "documento_visualizado" => ["Consultar", "read", "visibility"],
            "documento_impresso" => ["Consultar", "read", "print"],
            "modelo_documento_criado" => ["Criar", "create", "post_add"],
            "modelo_documento_atualizado" => [
                "Modificar",
                "update",
                "edit_document",
            ],
            "modelo_documento_aprovado" => ["Modificar", "update", "verified"],
            "modelo_documento_rejeitado" => ["Modificar", "update", "block"],
            "procedimento_criado" => ["Criar", "create", "add_task"],
            "procedimento_atualizado" => ["Modificar", "update", "edit_note"],
            "procedimento_status" => ["Modificar", "update", "toggle_on"],
            "tarefa_criada" => ["Criar", "create", "add_task"],
            "tarefa_iniciada" => ["Modificar", "update", "play_arrow"],
            "tarefa_concluida" => ["Modificar", "update", "task_alt"],
            "tarefa_devolvida_fila" => ["Modificar", "update", "undo"],
            "comentario_tarefa_criado" => ["Criar", "create", "add_comment"],
            "comentario_tarefa_editado" => ["Modificar", "update", "edit_note"],
            "comentario_tarefa_excluido" => ["Excluir", "delete", "delete"],
            "notificacao_tarefa_individual" => [
                "Criar",
                "create",
                "notifications_active",
            ],
            "comunicado_criado" => ["Criar", "create", "campaign"],
            "leitura_confirmada" => ["Consultar", "read", "done_all"],
            "usuario_salvo" => ["Modificar", "update", "person_add"],
            "usuario_status" => ["Modificar", "update", "manage_accounts"],
            "usuario_desativado" => ["Excluir", "delete", "person_remove"],
            "senha_redefinida" => ["Modificar", "update", "key"],
            "permissoes_atualizadas" => [
                "Modificar",
                "update",
                "admin_panel_settings",
            ],
            "consultorio_criado" => ["Criar", "create", "home_health"],
            "consultorio_atualizado" => ["Modificar", "update", "home_health"],
            "clinica_criada" => ["Criar", "create", "home_health"],
            "clinica_atualizada" => ["Modificar", "update", "home_health"],
            "clinica_status" => ["Modificar", "update", "toggle_on"],
            "clinica_modelo_atualizada" => [
                "Modificar",
                "update",
                "workspace_premium",
            ],
            "onboarding_concluido" => ["Modificar", "update", "check_circle"],
            "bloqueio_login_removido" => ["Excluir", "delete", "lock_open"],
            "erro_marcado_resolvido" => ["Modificar", "update", "task_alt"],
            "aviso_global_criado" => ["Criar", "create", "campaign"],
            "aviso_global_status" => ["Modificar", "update", "campaign"],
            "manutencao_atualizada" => ["Modificar", "update", "engineering"],
            "config_global_atualizada" => ["Modificar", "update", "tune"],
            "assinatura_ativada" => ["Modificar", "update", "verified"],
            "assinatura_desativada" => ["Excluir", "delete", "block"],
            "assinatura_atualizada" => ["Modificar", "update", "payments"],
            "assinatura_pagamento_informado" => ["Criar", "create", "payments"],
            "assinatura_pagamento_confirmado" => [
                "Modificar",
                "update",
                "verified",
            ],
            "assinatura_pagamento_nao_confirmado" => ["Excluir", "delete", "block"],
            "assinatura_comprovante_visualizado" => [
                "Consultar",
                "read",
                "visibility",
            ],
            "assinatura_comprovante_excluido" => [
                "Excluir",
                "delete",
                "delete_sweep",
            ],
            "somente_leitura_bloqueio" => ["Consultar", "read", "lock"],
            "meta_financeira_salva" => ["Modificar", "update", "flag"],
            "conta_bancaria_criada" => ["Criar", "create", "account_balance"],
            "conta_financeira_criada" => [
                "Criar",
                "create",
                "account_balance_wallet",
            ],
            "forma_pagamento_salva" => ["Modificar", "update", "payments"],
            "credor_salvo" => ["Modificar", "update", "person_pin"],
            "despesa_cadastrada" => ["Criar", "create", "payments"],
            "despesa_operacional_salva" => ["Criar", "create", "payments"],
            "despesa_paga" => ["Modificar", "update", "paid"],
            "despesa_cancelada" => ["Excluir", "delete", "cancel"],
            "recebivel_destinado" => ["Modificar", "update", "payments"],
            "receita_operacional_salva" => ["Criar", "create", "payments"],
            "receita_recebida" => ["Modificar", "update", "paid"],
            "receita_cancelada" => ["Excluir", "delete", "cancel"],
            "transferencia_financeira" => ["Criar", "create", "sync_alt"],
        ];
        if (isset($map[$event])) {
            return $map[$event];
        }
        if (
            str_contains($event, "exclu") ||
            str_contains($event, "remov") ||
            str_contains($event, "desativ") ||
            str_contains($event, "nao_confirmado") ||
            str_contains($event, "cancelad")
        ) {
            return ["Excluir", "delete", "delete"];
        }
        if (
            str_contains($event, "visualiz") ||
            str_contains($event, "abert") ||
            str_contains($event, "login") ||
            str_contains($event, "entrada") ||
            str_contains($event, "acesso") ||
            str_contains($event, "consult")
        ) {
            return ["Consultar", "read", "visibility"];
        }
        if (
            str_contains($event, "criad") ||
            str_contains($event, "cadastr") ||
            str_contains($event, "emitid") ||
            str_contains($event, "informado") ||
            str_contains($event, "salva")
        ) {
            return ["Criar", "create", "add_circle"];
        }
        return ["Modificar", "update", "edit"];
    
    }

    public static function activity_module_label(?string $entity): string
    
    {
    
        return [
            "clinica" => "Consultório",
            "consultorio" => "Consultório",
            "usuario" => "Equipe",
            "consulta" => "Agenda",
            "paciente" => "Pacientes",
            "lead" => "Interessados",
            "bloqueio" => "Agenda",
            "tarefa" => "Tarefas",
            "comunicado" => "Comunicados",
            "prontuario" => "Ficha do paciente",
            "documento" => "Documentos",
            "janela" => "Navegação",
            "rota" => "Permissões",
            "procedimento" => "Procedimentos",
            "sistema" => "Sistema",
            "permissoes" => "Permissões",
            "erro" => "Incidentes",
            "aviso_global" => "Avisos globais",
            "manutencao" => "Manutenção",
            "configuracao" => "Configurações",
            "assinatura" => "Assinatura",
            "financeiro" => "Financeiro",
            "login" => "Entrada",
            "seguranca" => "Segurança",
            "plataforma" => "Plataforma",
        ][$entity ?? ""] ?? ucfirst(entity_label($entity));
    
    }

}
