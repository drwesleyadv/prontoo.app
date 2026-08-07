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

final class AuditActivityDomainOperations01
{
    private function __construct()
    {
    }

    public static function pt_list(array $items): string
    
    {
    
        $items = array_values(
            array_filter(
                array_map(static  fn($v) => mb_trim((string) $v), $items),
                static  fn($v) => $v !== "",
            ),
        );
        $n = count($items);
        if ($n === 0) {
            return "";
        }
        if ($n === 1) {
            return $items[0];
        }
        if ($n === 2) {
            return $items[0] . " e " . $items[1];
        }
        return implode(", ", array_slice($items, 0, -1)) . " e " . $items[$n - 1];
    
    }

    public static function audit_change_body(array $fields): string
    
    {
    
        $fields = array_values(
            array_unique(
                array_filter(
                    array_map(static  fn($v) => mb_trim((string) $v), $fields),
                    static  fn($v) => $v !== "",
                ),
            ),
        );
        if (!$fields) {
            return "Nenhuma informação foi alterada.";
        }
        if (count($fields) === 1) {
            return "O campo " . pt_list($fields) . " foi modificado.";
        }
        return "Os campos " . pt_list($fields) . " foram modificados.";
    
    }

    public static function audit_value_present(mixed $v): bool
    
    {
    
        if (is_array($v)) {
            return !empty($v);
        }
        return mb_trim((string) $v) !== "";
    
    }

    public static function audit_field_list(array $ctx, array $labels, array $forced = []): array
    
    {
    
        $out = [];
        foreach ($forced as $label) {
            if (mb_trim((string) $label) !== "") {
                $out[] = (string) $label;
            }
        }
        foreach ($labels as $key => $label) {
            if (
                array_key_exists((string) $key, $ctx) &&
                audit_value_present($ctx[(string) $key])
            ) {
                $out[] = (string) $label;
            }
        }
        return array_values(array_unique($out));
    
    }

    public static function audit_registered_body(
        array $fields,
        string $empty = "Nenhum dado adicional foi informado.",
    ): string 
    {
    
        $fields = array_values(
            array_unique(
                array_filter(
                    array_map(static  fn($v) => mb_trim((string) $v), $fields),
                    static  fn($v) => $v !== "",
                ),
            ),
        );
        if (!$fields) {
            return $empty;
        }
        if (count($fields) === 1) {
            return "Foi registrado: " . $fields[0] . ".";
        }
        return "Foram registrados: " . pt_list($fields) . ".";
    
    }

    public static function audit_status_body(array $ctx, string $label = "status"): string
    
    {
    
        $status = (string) ($ctx["status"] ?? ($ctx["novo_status"] ?? ""));
        return $status !== ""
            ? "O " . $label . " foi alterado para " . $status . "."
            : "O " . $label . " foi alterado.";
    
    }

    public static function audit_patient_name(array $ctx, mixed $entityId = null): string
    
    {
    
        $name = trim(
            (string) ($ctx["patient_name"] ??
                ($ctx["paciente"] ?? ($ctx["nome_paciente"] ?? ""))),
        );
        if ($name !== "") {
            return $name;
        }
        $id =
            (int) ($ctx["patient_link_id"] ??
                ($ctx["patient_id"] ?? ($entityId ?? 0)));
        return $id > 0 ? "Paciente #" . $id : "Paciente";
    
    }

    public static function audit_person_target(
        string $label,
        array $ctx,
        string $nameKey = "target_name",
    ): string 
    {
    
        $name = trim(
            (string) ($ctx[$nameKey] ?? ($ctx["nome"] ?? ($ctx["name"] ?? ""))),
        );
        return $name !== "" ? $label . " " . $name : $label;
    
    }

    public static function audit_patient_record_target(array $ctx, mixed $entityId = null): string
    
    {
    
        return "o prontuário de " . audit_patient_name($ctx, $entityId);
    
    }

    public static function audit_clinic_target(array $ctx): string
    
    {
    
        $name = mb_trim((string) ($ctx["clinic_name"] ?? ($ctx["consultorio"] ?? "")));
        return $name !== "" ? "Consultório " . $name : "Consultório";
    
    }

    public static function audit_task_target(array $ctx): string
    
    {
    
        $title = mb_trim((string) ($ctx["task_title"] ?? ($ctx["title"] ?? "")));
        return $title !== "" ? "Tarefa “" . $title . "”" : "Tarefa";
    
    }

    public static function audit_notice_target(array $ctx): string
    
    {
    
        $title = mb_trim((string) ($ctx["notice_title"] ?? ($ctx["title"] ?? "")));
        return $title !== "" ? "Aviso “" . $title . "”" : "Aviso";
    
    }

    public static function audit_ctx_pick(array $ctx, array $keys): string
    
    {
    
        foreach ($keys as $key) {
            if (array_key_exists((string) $key, $ctx)) {
                $v = mb_trim((string) $ctx[(string) $key]);
                if ($v !== "" && $v !== "0") {
                    return $v;
                }
            }
        }
        return "";
    
    }

    public static function audit_status_text(array $ctx): string
    
    {
    
        $s = audit_ctx_pick($ctx, ["status", "novo_status", "stage", "active"]);
        if ($s === "") {
            return "";
        }
        if (in_array($s, ["1", "true", "sim"], true)) {
            return "ativo";
        }
        if (in_array($s, ["0", "false", "nao", "não"], true)) {
            return "inativo";
        }
        return str_replace("_", " ", $s);
    
    }

    public static function audit_target_scope_label(array $ctx): string
    
    {
    
        $dest = audit_ctx_pick($ctx, ["destino", "target_scope"]);
        if ($dest === "clinic") {
            return "toda a clínica";
        }
        if ($dest === "role") {
            $cargo = audit_ctx_pick($ctx, ["cargo", "target_role"]);
            return $cargo !== ""
                ? "todos do cargo " . $cargo
                : "um cargo específico";
        }
        if ($dest === "user") {
            $name = audit_ctx_pick($ctx, [
                "assigned_name",
                "target_name",
                "pessoa_nome",
                "pessoa",
            ]);
            return $name !== "" ? "a pessoa " . $name : "uma pessoa específica";
        }
        return $dest !== "" ? $dest : "";
    
    }

    public static function audit_finance_base_label_from_ctx(array $ctx): string
    
    {
    
        $base = audit_ctx_pick($ctx, ["base", "base_metric"]);
        return $base !== "" ? financial_goal_base_label($base) : "";
    
    }

    public static function audit_account_name(array $ctx): string
    
    {
    
        return audit_ctx_pick($ctx, [
            "account_name",
            "conta",
            "name",
            "titulo",
            "title",
        ]);
    
    }

    public static function audit_counterparty_name(array $ctx): string
    
    {
    
        return audit_ctx_pick($ctx, [
            "counterparty_name",
            "credor",
            "target_name",
            "nome",
            "name",
        ]);
    
    }

    public static function audit_financial_title(array $ctx): string
    
    {
    
        return audit_ctx_pick($ctx, ["titulo", "title", "name", "descricao"]);
    
    }

    public static function audit_context_array(array $row): array
    
    {
    
        $raw = (string) ($row["context_json"] ?? "");
        if ($raw === "") {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    
    }

    public static function audit_integrity_base(array $r): string
    
    {
    
        return implode("|", [
            (string) ($r["clinic_id"] ?? ""),
            (string) ($r["user_id"] ?? ""),
            (string) ($r["event"] ?? ($r["event_key"] ?? "")),
            (string) ($r["entity"] ?? ($r["entity_key"] ?? "")),
            (string) ($r["entity_id"] ?? ""),
            (string) ($r["friendly_text"] ?? ""),
            (string) ($r["context_json"] ?? ""),
        ]);
    
    }

    public static function audit_select_sql(): string
    
    {
    
        return "SELECT a.id,a.clinic_id,a.user_id,a.event_key,a.event_key AS event,a.event_label,a.event_icon,a.entity_key,a.entity_key AS entity,a.entity_label,a.entity_id,a.friendly_text,a.context_json,a.integrity_hash,a.previous_hash,a.chain_hash,a.proof_hash,a.proof_json,a.policy_version,a.created_at FROM pi_audit a";
    
    }

    public static function int_ids(array $rows, string $key): array
    
    {
    
        $ids = [];
        foreach ($rows as $r) {
            $v = (int) ($r[$key] ?? 0);
            if ($v > 0) {
                $ids[$v] = $v;
            }
        }
        return array_values($ids);
    
    }

    public static function audit_where_sql(string $where): string
    
    {
    
        return trim($where) === "1=1" ? "1=1" : $where;
    
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

    public static function activity_text_value(mixed $v): string
    
    {
    
        if (is_array($v)) {
            return trim(implode(", ", array_filter(array_map("strval", $v))));
        }
        return mb_trim((string) $v);
    
    }

    public static function activity_clean_name(string $value, string $fallback = ""): string
    
    {
    
        $value = mb_trim(preg_replace("/\s+/", " ", $value));
        return $value !== "" ? $value : $fallback;
    
    }

    public static function activity_status_from_ctx(array $ctx): string
    
    {
    
        $s = activity_text_value(
            $ctx["status"] ?? ($ctx["novo_status"] ?? ($ctx["active"] ?? "")),
        );
        if ($s === "") {
            return "";
        }
        $l = mb_strtolower($s);
        return match ($l) {
            "1", "true", "sim", "ativo", "ativa" => "ativa",
            "0", "false", "nao", "não", "inativo", "inativa" => "inativa",
            "prevista" => "prevista",
            "paga" => "paga",
            "recebida" => "recebida",
            "cancelada" => "cancelada",
            default => str_replace("_", " ", $s),
        };
    
    }
}
