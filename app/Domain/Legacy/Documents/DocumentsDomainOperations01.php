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

final class DocumentsDomainOperations01
{
    private function __construct()
    {
    }

    public static function document_identifier_consonants(): array
    
    {
    
        return str_split("BCDFGHJKLMNPQRSTVWXYZ");
    
    }

    public static function document_identifier_base(): int
    
    {
    
        return count(document_identifier_consonants());
    
    }

    public static function document_identifier_random_alphabet(): string
    
    {
    
        $letters = document_identifier_consonants();
        shuffle($letters);
        return implode("", $letters);
    
    }

    public static function document_identifier_valid_alphabet(string $alphabet): bool
    
    {
    
        $alphabet = strtoupper(trim($alphabet));
        $letters = str_split($alphabet);
        $allowed = document_identifier_consonants();
        sort($letters);
        sort($allowed);
        return strlen($alphabet) === document_identifier_base() &&
            $letters === $allowed;
    
    }

    public static function document_identifier_digits(int $sequence): array
    
    {
    
        if ($sequence <= 0) {
            throw new RuntimeException("Sequência documental inválida.");
        }
        $base = document_identifier_base();
        $n = $sequence - 1;
        $digits = [];
        do {
            $digits[] = $n % $base;
            $n = intdiv($n, $base);
        } while ($n > 0);
        $digits = array_reverse($digits);
        while (count($digits) < 3) {
            array_unshift($digits, 0);
        }
        return $digits;
    
    }

    public static function document_identifier_numeric_part(int $sequence): string
    
    {
    
        return implode(
            "",
            array_map(
                static  fn($d) => (string) $d,
                document_identifier_digits($sequence),
            ),
        );
    
    }

    public static function document_identifier_encode(int $sequence, string $alphabet): string
    
    {
    
        $alphabet = strtoupper($alphabet);
        if (!document_identifier_valid_alphabet($alphabet)) {
            throw new RuntimeException(
                "Mapa de identificação documental inválido.",
            );
        }
        $out = "";
        foreach (document_identifier_digits($sequence) as $digit) {
            $out .= $alphabet[(int) $digit];
        }
        return $out;
    
    }

    public static function document_identifier_display(?string $identifier): string
    
    {
    
        $identifier = strtoupper(mb_trim((string) $identifier));
        return preg_match('/^[A-Z]{3,12}$/', $identifier) ? $identifier : "";
    
    }

    public static function document_identifier_capacity_for_length(int $length = 3): int
    
    {
    
        $length = max(1, $length);
        return (int) (document_identifier_base() ** $length);
    
    }

    public static function document_print_header_html(?string $identifier): string
    
    {
    
        return "";
    
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

    public static function document_template_visible_where(array $c, string $alias = "dt"): array
    
    {
    
        $role = (string) ($c["role"] ?? "");
        $uid = (int) ($c["user"]["id"] ?? 0);
        if ($role === "gerente") {
            return ["1=1", []];
        }
        if ($role === "medico") {
            return [
                "($alias.owner_user_id=? OR $alias.owner_role IN ('medico','assistente','recepcionista'))",
                [$uid],
            ];
        }
        return ["($alias.owner_user_id=? OR $alias.owner_role=?)", [$uid, $role]];
    
    }

    public static function can_edit_document_template(array $c, array $tpl): bool
    
    {
    
        $role = (string) ($c["role"] ?? "");
        if ($role === "gerente") {
            return true;
        }
        return (string) ($tpl["owner_role"] ?? "") === $role;
    
    }

    public static function document_can_issue_template(array $c, array $tpl): bool
    
    {
    
        $role = (string) ($c["role"] ?? "");
        $type = (string) ($tpl["type_key"] ?? "");
        $ownerRole = (string) ($tpl["owner_role"] ?? "");
        if (!in_array($type, document_allowed_types_for_role($role), true)) {
            return false;
        }
        if ($role === "gerente") {
            return $ownerRole === "gerente" ||
                in_array($type, document_allowed_types_for_role("gerente"), true);
        }
        if ($role === "medico") {
            return $ownerRole === "medico" ||
                (int) ($tpl["owner_user_id"] ?? 0) ===
                    (int) ($c["user"]["id"] ?? 0);
        }
        return $ownerRole === $role ||
            (int) ($tpl["owner_user_id"] ?? 0) === (int) ($c["user"]["id"] ?? 0);
    
    }

    public static function document_sanitize_html(string $html): string
    
    {
    
        $html = trim($html);
        if ($html === "") {
            return "";
        }
        $html = preg_replace("/<!--.*?-->/s", "", $html);
        $html = preg_replace(
            "/<\s*(script|style|iframe|object|embed|link|meta|form|input|button)[^>]*>.*?<\s*\/\s*\1\s*>/is",
            "",
            $html,
        );
        $html = preg_replace(
            "/<\s*(script|style|iframe|object|embed|link|meta|form|input|button)[^>]*\/?>/is",
            "",
            $html,
        );
        $blockClass = function (string $attrs): string {
    
            $classes = [];
            if (
                preg_match(
                    "/text-align\s*:\s*(left|center|right|justify)/i",
                    $attrs,
                    $a,
                ) ||
                preg_match(
                    "/\balign\s*=\s*[\"']?(left|center|right|justify)/i",
                    $attrs,
                    $a,
                ) ||
                preg_match(
                    "/class\s*=\s*[\"'][^\"']*ta-(left|center|right|justify)/i",
                    $attrs,
                    $a,
                )
            ) {
                $classes[] = "ta-" . strtolower($a[1]);
            }
            $indent = 0;
            if (
                preg_match(
                    "/(?:class\s*=\s*[\"'][^\"']*(?:doc-)?indent-|ql-indent-)([1-3])/i",
                    $attrs,
                    $i,
                )
            ) {
                $indent = (int) $i[1];
            } elseif (
                preg_match(
                    "/margin-left\s*:\s*([0-9.]+)\s*(px|pt|cm|rem|em)?/i",
                    $attrs,
                    $i,
                )
            ) {
                $n = (float) $i[1];
                $u = strtolower($i[2] ?? "px");
                $px =
                    $u === "cm"
                        ? $n * 37.8
                        : ($u === "pt"
                            ? $n * 1.333
                            : ($u === "rem" || $u === "em"
                                ? $n * 16
                                : $n));
                $indent = max(1, min(3, (int) ceil($px / 36)));
            }
            if ($indent > 0) {
                $classes[] = "indent-" . $indent;
            }
            return $classes
                ? ' class="' . implode(" ", array_unique($classes)) . '"'
                : "";
        };
        $html = preg_replace(
            "/<\s*center\b[^>]*>/i",
            '<div class="ta-center">',
            $html,
        );
        $html = preg_replace("/<\s*\/\s*center\s*>/i", "</div>", $html);
        $html = preg_replace_callback(
            "/<\s*(p|div|blockquote|h2|h3)\b([^>]*)>/i",
            function ($m) use ($blockClass) {
    
                $tag =
                    strtolower($m[1]) === "blockquote" ? "div" : strtolower($m[1]);
                return "<" . $tag . $blockClass($m[2] ?? "") . ">";
            },
            $html,
        );
        $html = preg_replace("/<\s*\/\s*blockquote\s*>/i", "</div>", $html);
        $html = strip_tags(
            $html,
            "<b><strong><i><em><u><p><br><div><ul><ol><li><h2><h3>",
        );
    
        $html = preg_replace_callback(
            "/<\s*(\/?)\s*(b|strong|i|em|u|p|br|div|ul|ol|li|h2|h3)\b([^>]*)>/i",
            function ($m) {
    
                $closing = ($m[1] ?? "") === "/";
                $tag = strtolower((string) ($m[2] ?? ""));
                if ($closing) {
                    return $tag === "br" ? "" : "</" . $tag . ">";
                }
                if ($tag === "br") {
                    return "<br>";
                }
                $attrs = $m[3] ?? "";
                $classes = [];
                if (
                    in_array($tag, ["p", "div", "h2", "h3"], true) &&
                    preg_match_all(
                        "/\b(ta-(?:left|center|right|justify)|indent-[1-3])\b/i",
                        $attrs,
                        $mm,
                    )
                ) {
                    foreach ($mm[1] as $c) {
                        $classes[] = strtolower($c);
                    }
                }
                return "<" .
                    $tag .
                    ($classes
                        ? ' class="' . implode(" ", array_unique($classes)) . '"'
                        : "") .
                    ">";
            },
            $html,
        );
        $html = preg_replace("/(?:<br>\s*){4,}/i", "<br><br><br>", $html);
        return trim($html);
    
    }

    public static function document_body_is_empty(string $html): bool
    
    {
    
        return trim(
            preg_replace(
                "/\s+/",
                " ",
                strip_tags(str_replace(["&nbsp;", "<br>"], [" ", " "], $html)),
            ),
        ) === "";
    
    }

    public static function document_context_json_decode(mixed $raw): array
    
    {
    
        if (is_array($raw)) {
            return $raw;
        }
        $raw = mb_trim((string) ($raw ?? ""));
        if ($raw === "") {
            return [];
        }
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    
    }
}
