<?php
declare(strict_types=1);

namespace Prontoo\Domain\Legacy\Documents;

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

final class DocumentTypePolicy
{
    private function __construct()
    {
    }

    public static function document_status_label(string $status): string
    
    {
    
        return [
            "rascunho" => "Rascunho",
            "preparado" => "Pré-visualização",
            "pendente_assinatura" => "Pendente de assinatura",
            "emitido" => "Emitido",
            "entregue" => "Entregue",
            "cancelado" => "Cancelado",
            "substituido" => "Substituído",
            "pending_approval" => "Aguardando aprovação",
            "approved" => "Aprovado",
            "rejected" => "Rejeitado",
            "archived" => "Arquivado",
        ][$status] ?? ucfirst(str_replace("_", " ", $status));
    
    }

    public static function document_status_class(string $status): string
    
    {
    
        return match ($status) {
            "emitido", "entregue", "approved" => "ok",
            "preparado", "pendente_assinatura", "pending_approval" => "warn",
            "cancelado", "substituido", "rejected" => "bad",
            default => "neutral",
        };
    
    }

    public static function document_editable_status(string $status): bool
    
    {
    
        return in_array($status, ["rascunho", "preparado"], true);
    
    }

    public static function document_type_options(): array
    
    {
    
        return [
            "recibo" => "Recibo",
            "comprovante" => "Comprovante",
            "atestado" => "Atestado",
            "receita" => "Prescrição/Receita",
            "declaracao" => "Declaração",
            "encaminhamento" => "Encaminhamento",
            "solicitacao_exame" => "Solicitação de exame",
            "termo" => "Termo",
            "orientacao" => "Orientação",
            "ficha_triagem" => "Ficha de triagem",
            "checklist" => "Checklist",
            "relatorio" => "Relatório",
            "laudo" => "Laudo",
            "outro" => "Outro documento",
        ];
    
    }

    public static function document_system_field_groups(): array
    
    {
    
        return [
            "consultorio" => [
                "label" => "Consultório",
                "icon" => "home_health",
                "fields" => [
                    "consultorio" => "Nome Fantasia",
                    "endereco" => "Endereço",
                    "cidade" => "Cidade",
                ],
            ],
            "datas" => [
                "label" => "Data",
                "icon" => "calendar_month",
                "fields" => [
                    "data_abreviada" => "Data abreviada",
                    "data_extenso" => "Data por extenso",
                ],
            ],
            "paciente" => [
                "label" => "Paciente",
                "icon" => "personal_injury",
                "fields" => [
                    "paciente" => "Nome do paciente",
                    "cpf_paciente" => "CPF do paciente",
                    "nascimento_paciente" => "Nascimento do paciente",
                    "cidade_paciente" => "Cidade do paciente",
                    "responsavel_legal" => "Responsável legal",
                    "cpf_responsavel_legal" => "CPF do responsável legal",
                    "vinculo_responsavel_legal" => "Vínculo do responsável legal",
                ],
            ],
            "agendamento" => [
                "label" => "Agendamento e atendimento",
                "icon" => "event_available",
                "fields" => [
                    "agendamento_data" => "Data marcada",
                    "agendamento_hora" => "Hora marcada",
                    "agendamento_horario" => "Período marcado",
                    "agendamento_profissional" => "Profissional agendado",
                    "agendamento_procedimento" => "Procedimento agendado",
                    "agendamento_motivo" => "Motivo informado",
                    "agendamento_observacoes" => "Observações do agendamento",
                    "agendamento_status" => "Situação do agendamento",
                    "atendimento_inicio_real" => "Início do atendimento",
                    "atendimento_fim_real" => "Fim do atendimento",
                    "atendimento_duracao_real" => "Tempo de atendimento",
                ],
            ],
            "procedimento" => [
                "label" => "Procedimento",
                "icon" => "medical_information",
                "fields" => [
                    "procedimento" => "Nome do procedimento",
                    "procedimento_categoria" => "Categoria do procedimento",
                    "procedimento_descricao" => "Descrição do procedimento",
                    "procedimento_duracao" => "Duração do procedimento",
                    "procedimento_valor" => "Valor do procedimento",
                    "procedimento_formas_pagamento" => "Formas de pagamento",
                    "procedimento_orientacoes_pre" => "Orientações prévias",
                    "procedimento_cuidados_pos" => "Cuidados posteriores",
                ],
            ],
            "atividade" => [
                "label" => "Atividade da ficha",
                "icon" => "clinical_notes",
                "fields" => [
                    "atividade" => "Título da atividade",
                    "atividade_tipo" => "Tipo da atividade",
                    "atividade_data" => "Data da atividade",
                    "atividade_conteudo" => "Conteúdo da atividade",
                    "atividade_paciente" => "Paciente da atividade",
                ],
            ],
            "equipe" => [
                "label" => "Equipe",
                "icon" => "groups",
                "fields" => [
                    "profissional" => "Profissional",
                    "profissional_cargo" => "Cargo Profissional",
                    "colaborador" => "Colaborador",
                    "colaborador_cargo" => "Cargo do Colaborador",
                ],
            ],
        ];
    
    }

    public static function document_system_fields(): array
    
    {
    
        $out = [];
        foreach (document_system_field_groups() as $group) {
            foreach ($group["fields"] ?? [] as $key => $label) {
                $out[$key] = $label;
            }
        }
        return $out;
    
    }

    public static function document_allowed_types_for_role(string $role): array
    
    {
    
        return match ($role) {
            "recepcionista" => [
                "declaracao",
                "comprovante",
                "termo",
                "recibo",
                "orientacao",
                "outro",
            ],
            "assistente" => [
                "ficha_triagem",
                "checklist",
                "declaracao",
                "termo",
                "orientacao",
                "outro",
            ],
            "medico" => [
                "atestado",
                "receita",
                "solicitacao_exame",
                "encaminhamento",
                "relatorio",
                "laudo",
                "declaracao",
                "orientacao",
                "outro",
            ],
            "gerente" => [
                "declaracao",
                "comprovante",
                "termo",
                "recibo",
                "relatorio",
                "orientacao",
                "outro",
            ],
            default => ["declaracao", "outro"],
        };
    
    }

    public static function document_type_options_for_role(string $role): array
    
    {
    
        $all = document_type_options();
        $allowed = array_flip(document_allowed_types_for_role($role));
        return array_intersect_key($all, $allowed);
    
    }

    public static function document_ds_type_label(array $typeOptions, ?string $type): string
    
    {
    
        $type = (string) ($type ?? "");
        return $typeOptions[$type] ??
            ucfirst(str_replace("_", " ", $type ?: "documento"));
    
    }

}
