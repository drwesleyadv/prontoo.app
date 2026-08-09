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

final class ActivityDisplayPolicy
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
                    $v = \Prontoo\Domain\AuditActivity\ActivityDisplayPolicy::activity_display_label((string) $v);
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
            if (\Prontoo\Domain\AuditActivity\AuditCopyPolicy::audit_value_present($ctx[$key])) {
                $out[] = \Prontoo\Domain\AuditActivity\ActivityDisplayPolicy::activity_display_label($label);
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

}
