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

final class AuditActivityDomainOperations02
{
    private function __construct()
    {
    }

    public static function activity_display_label(string $label): string
    
    {
    
        $label = trim($label);
        if ($label === "") {
            return "";
        }
        $known = [
            "cpf" => "CPF",
            "cnpj" => "CNPJ",
            "e-mail" => "E-mail",
            "email" => "E-mail",
            "telefone" => "Telefone",
            "nascimento" => "Nascimento",
            "endereço" => "Endereço",
            "endereco" => "Endereço",
            "nome" => "Nome",
            "nome completo" => "Nome completo",
            "cadastro do paciente" => "Cadastro do paciente",
            "responsável legal" => "Responsável legal",
            "responsavel legal" => "Responsável legal",
            "status" => "Status",
            "valor" => "Valor",
            "título" => "Título",
            "titulo" => "Título",
            "descricao" => "Descrição",
            "descrição" => "Descrição",
            "conta" => "Conta",
            "conta de origem" => "Conta de origem",
            "conta de destino" => "Conta de destino",
            "credor" => "Credor",
            "categoria" => "Categoria",
            "vencimento" => "Vencimento",
            "data prevista" => "Data prevista",
            "data realizada" => "Data realizada",
            "forma de pagamento" => "Forma de pagamento",
            "permissões" => "Permissões",
            "permissoes" => "Permissões",
            "senha" => "Senha",
            "cor de destaque" => "Cor de destaque",
            "ícone" => "Ícone",
            "icone" => "Ícone",
            "nomes dos setores" => "Nomes dos setores",
            "perfil" => "Perfil",
            "cargo" => "Cargo",
            "responsável" => "Responsável",
            "responsavel" => "Responsável",
            "paciente" => "Paciente",
            "profissional" => "Profissional",
            "horário" => "Horário",
            "horario" => "Horário",
            "observações" => "Observações",
            "observacoes" => "Observações",
            "marcadores" => "Marcadores",
        ];
        $key = mb_strtolower($label);
        return $known[$key] ?? mb_convert_case($label, MB_CASE_TITLE, "UTF-8");
    
    }

    public static function activity_changed_fields(
        string $event,
        ?string $entity,
        array $ctx,
    ): array 
    {
    
        $labels = [
            "nome" => "Nome",
            "name" => "Nome",
            "full_name" => "Nome completo",
            "phone" => "Telefone",
            "telefone" => "Telefone",
            "email" => "E-mail",
            "cpf" => "CPF",
            "cnpj" => "CNPJ",
            "legal_document" => "CPF/CNPJ",
            "birth_date" => "Nascimento",
            "birth" => "Nascimento",
            "address" => "Endereço",
            "address_line" => "Endereço",
            "address_city" => "Cidade",
            "address_state" => "UF",
            "tags" => "Marcadores",
            "notes" => "Observações",
            "stage" => "Etapa",
            "next_action_at" => "Próxima ação",
            "source" => "Origem",
            "interest" => "Interesse",
            "title" => "Título",
            "titulo" => "Título",
            "description" => "Descrição",
            "descricao" => "Descrição",
            "due_at" => "Vencimento",
            "vencimento" => "Vencimento",
            "expected_at" => "Data prevista",
            "received_at" => "Data realizada",
            "paid_at" => "Data realizada",
            "target_scope" => "Destinatário",
            "target_role" => "Cargo",
            "target_user_id" => "Pessoa",
            "display_name" => "Nome fantasia",
            "legal_name" => "Razão social",
            "clinic_icon" => "Ícone",
            "accent_color" => "Cor de destaque",
            "setores" => "Nomes dos setores",
            "status" => "Status",
            "novo_status" => "Status",
            "active" => "Status",
            "valor" => "Valor",
            "amount" => "Valor",
            "amount_cents" => "Valor",
            "target_cents" => "Valor",
            "account_id" => "Conta",
            "account_name" => "Conta",
            "conta" => "Conta",
            "origem" => "Conta de origem",
            "destino" => "Conta de destino",
            "counterparty_name" => "Credor",
            "credor" => "Credor",
            "categoria" => "Categoria",
            "category" => "Categoria",
            "payment_method" => "Forma de pagamento",
            "forma_pagamento" => "Forma de pagamento",
            "perfil" => "Perfil",
            "role_code" => "Cargo",
            "profession" => "Profissão",
            "responsible_profession" => "Profissão",
            "password" => "Senha",
            "senha" => "Senha",
        ];
        $out = [];
        foreach (["campos", "fields", "changed_fields"] as $k) {
            if (!empty($ctx[$k])) {
                $raw = is_array($ctx[$k])
                    ? $ctx[$k]
                    : explode(",", (string) $ctx[$k]);
                foreach ($raw as $v) {
                    $v = activity_display_label((string) $v);
                    if ($v !== "") {
                        $out[] = $v;
                    }
                }
            }
        }
        foreach ($labels as $key => $label) {
            if (!array_key_exists($key, $ctx)) {
                continue;
            }
            if (audit_value_present($ctx[$key])) {
                $out[] = activity_display_label($label);
            }
        }
        $eventFields = [
            "transferencia_financeira" => [
                "Valor",
                "Conta de origem",
                "Conta de destino",
            ],
            "consulta_agendada" => ["Paciente", "Profissional", "Horário"],
            "consulta_alterada" => [
                "Paciente",
                "Profissional",
                "Horário",
                "Status",
            ],
            "consulta_iniciada" => ["Status"],
            "paciente_chegou" => ["Status"],
            "tarefa_criada" => ["Título", "Destinatário", "Vencimento"],
            "tarefa_iniciada" => ["Status"],
            "tarefa_concluida" => ["Status"],
            "tarefa_devolvida_fila" => ["Status"],
            "usuario_salvo" => ["Nome", "CPF", "E-mail", "Cargo"],
            "usuario_status" => ["Status"],
            "usuario_desativado" => ["Status"],
            "permissoes_atualizadas" => ["Permissões"],
            "config_global_atualizada" => ["Configurações"],
            "assinatura_pagamento_informado" => ["Valor"],
            "assinatura_pagamento_confirmado" => ["Status"],
            "assinatura_pagamento_nao_confirmado" => ["Status"],
            "receita_operacional_salva" => [
                "Título",
                "Valor",
                "Data prevista",
                "Conta",
            ],
            "receita_recebida" => ["Status", "Valor", "Conta"],
            "receita_cancelada" => ["Status"],
            "despesa_operacional_salva" => [
                "Título",
                "Valor",
                "Credor",
                "Categoria",
                "Vencimento",
                "Conta",
            ],
            "despesa_paga" => ["Status", "Valor", "Conta"],
            "despesa_cancelada" => ["Status"],
        ];
        foreach ($eventFields[$event] ?? [] as $f) {
            $out[] = $f;
        }
        return array_values(
            array_unique(
                array_filter($out, static  fn($v) => mb_trim((string) $v) !== ""),
            ),
        );
    
    }

    public static function activity_patient_name(array $ctx, mixed $entityId = null): string
    
    {
    
        return activity_clean_name(audit_patient_name($ctx, $entityId), "paciente");
    
    }

    public static function activity_title_from_ctx(
        array $ctx,
        string $fallback = "registro",
    ): string 
    {
    
        return activity_clean_name(
            activity_text_value(
                $ctx["titulo"] ??
                    ($ctx["title"] ?? ($ctx["name"] ?? ($ctx["descricao"] ?? ""))),
            ),
            $fallback,
        );
    
    }

    public static function activity_person_from_ctx(
        array $ctx,
        string $fallback = "colaborador",
    ): string 
    {
    
        $n = activity_text_value(
            $ctx["target_name"] ?? ($ctx["nome"] ?? ($ctx["name"] ?? "")),
        );
        if ($n === "") {
            $n = trim(audit_person_target("", $ctx));
        }
        return activity_clean_name($n, $fallback);
    
    }

    public static function activity_target_scope_human(array $ctx): string
    
    {
    
        $scope = activity_text_value(
            $ctx["destino"] ?? ($ctx["target_scope"] ?? ""),
        );
        if ($scope === "role") {
            $cargo = activity_text_value(
                $ctx["cargo"] ?? ($ctx["target_role"] ?? ""),
            );
            return $cargo !== "" ? "para o setor " . $cargo : "para um setor";
        }
        if ($scope === "user") {
            $name = activity_text_value(
                $ctx["assigned_name"] ?? ($ctx["target_name"] ?? ""),
            );
            return $name !== "" ? "para " . $name : "para uma pessoa da equipe";
        }
        if ($scope === "clinic") {
            return "para toda a equipe";
        }
        return $scope !== "" ? "para " . $scope : "";
    
    }

    public static function activity_financial_label(array $ctx, string $fallback): string
    
    {
    
        $title = activity_title_from_ctx($ctx, "");
        if ($title !== "") {
            return $title;
        }
        $cp = activity_clean_name(audit_counterparty_name($ctx), "");
        return $cp !== "" ? $cp : $fallback;
    
    }

    public static function activity_action_verb(string $event): string
    
    {
    
        [$axis] = activity_axis_for_event($event);
        return [
            "Criar" => "cadastrou",
            "Modificar" => "alterou",
            "Consultar" => "consultou",
            "Excluir" => "excluiu",
        ][$axis] ?? "alterou";
    
    }

    public static function activity_direct_target(
        string $event,
        ?string $entity,
        mixed $entityId,
        array $ctx,
    ): string 
    {
    
        $patient = activity_patient_name($ctx, $entityId);
        $title = activity_title_from_ctx($ctx, "");
        if ($event === "janela_aberta") {
            return $entity === "paciente" ||
                mb_trim((string) ($ctx["patient_name"] ?? "")) !== ""
                ? "a ficha do paciente " . $patient
                : "a tela " .
                        (activity_text_value($ctx["janela"] ?? $title) ?:
                            "do sistema");
        }
        if ($entity === "paciente") {
            return "a ficha do paciente " . $patient;
        }
        if ($entity === "consulta") {
            return "a consulta de " . $patient;
        }
        if ($entity === "documento") {
            return "o documento " . audit_document_type_text($ctx);
        }
        if ($entity === "tarefa") {
            return "a tarefa " . audit_task_name($ctx);
        }
        if ($entity === "usuario") {
            return "o colaborador " . activity_person_from_ctx($ctx);
        }
        if ($entity === "financeiro") {
            return "o financeiro";
        }
        $label = entity_label($entity);
        $article = in_array(
            $label,
            [
                "consulta",
                "tarefa",
                "aviso",
                "janela",
                "rota",
                "configuração",
                "assinatura",
                "entrada",
                "segurança",
            ],
            true,
        )
            ? "a"
            : "o";
        return $article . " " . $label . ($title !== "" ? " " . $title : "");
    
    }

    public static function activity_meta_text(
        string $event,
        ?string $entity,
        string $currentMeta = "",
    ): string 
    {
    
        return trim($currentMeta);
    
    }

    public static function audit_should_write(string $event): bool
    
    {
    
        if ($event === "janela_aberta" && !PRONTOO_AUDIT_PAGE_VIEWS) {
            return false;
        }
        return true;
    
    }

    public static function audit_trusted_origin_resolve(
        array &$context,
        ?array $trustedOrigin,
    ): array 
    {
        foreach (array_keys($context) as $key) {
            if (
                str_starts_with((string) $key, "_audit_") ||
                str_starts_with((string) $key, "_skip_")
            ) {
                unset($context[$key]);
            }
        }
        $origin = is_array($trustedOrigin) ? $trustedOrigin : [];
        $createdAt = mb_trim((string) ($origin["created_at"] ?? ""));
        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                $createdAt,
            ) !== 1
        ) {
            $createdAt = "";
        }
        $ipHash = strtolower(mb_trim((string) ($origin["ip_hash"] ?? "")));
        if (preg_match('/^[a-f0-9]{64}$/', $ipHash) !== 1) {
            $ipHash = "";
        }
        return [
            "skip_runtime_context" => !empty(
                $origin["skip_runtime_context"]
            ),
            "skip_context_enrichment" => !empty(
                $origin["skip_context_enrichment"]
            ),
            "has_user_id" => array_key_exists("user_id", $origin),
            "user_id" => max(0, (int) ($origin["user_id"] ?? 0)),
            "has_ip_hash" => array_key_exists("ip_hash", $origin),
            "ip_hash" => $ipHash,
            "has_user_agent" => array_key_exists("user_agent", $origin),
            "user_agent" => mb_substr(
                mb_trim((string) ($origin["user_agent"] ?? "")),
                0,
                180,
            ),
            "created_at" => $createdAt,
            "proof_context" =>
                isset($origin["proof_context"]) &&
                is_array($origin["proof_context"])
                    ? $origin["proof_context"]
                    : null,
        ];
    
    }

    public static function audit_document_type_text(array $ctx): string
    
    {
    
        $label = mb_trim((string) ($ctx["document_type_label"] ?? ""));
        if ($label === "") {
            $key = (string) ($ctx["document_type"] ?? ($ctx["type_key"] ?? ""));
            $types = function_exists("document_type_options")
                ? document_type_options()
                : [];
            $label = $types[$key] ?? "";
        }
        if ($label === "") {
            $label = mb_trim((string) ($ctx["titulo"] ?? "Documento"));
        }
        return $label !== "" ? $label : "Documento";
    
    }

    public static function audit_document_article(string $label): string
    
    {
    
        $l = mb_strtolower(trim($label));
        foreach (["receita", "declaração", "solicitação", "orientação"] as $fem) {
            if (str_starts_with($l, $fem)) {
                return "uma";
            }
        }
        return "um";
    
    }

    public static function audit_document_activity_sentence(
        string $who,
        string $action,
        array $ctx,
    ): string 
    {
    
        $doc = audit_document_type_text($ctx);
        $patient = mb_trim((string) ($ctx["patient_name"] ?? ""));
        $txt =
            $who . " " . $action . " " . audit_document_article($doc) . " " . $doc;
        if ($patient !== "") {
            $txt .= ($action === "visualizou" ? " de " : " para ") . $patient;
        }
        return $txt . ".";
    
    }

    public static function audit_model_title(array $ctx): string
    
    {
    
        $t = trim(
            (string) ($ctx["template_title"] ??
                ($ctx["titulo"] ?? ($ctx["title"] ?? ($ctx["modelo"] ?? "")))),
        );
        return $t !== "" ? $t : "documento";
    
    }

    public static function audit_model_activity_sentence(
        string $who,
        string $action,
        array $ctx,
    ): string 
    {
    
        $model = audit_model_title($ctx);
        return match ($action) {
            "criou" => "$who criou um modelo de $model.",
            "alterou" => "$who alterou o modelo $model.",
            "aprovou" => "$who aprovou o modelo $model.",
            "rejeitou" => "$who rejeitou o modelo $model.",
            default => "$who alterou o modelo $model.",
        };
    
    }
}
