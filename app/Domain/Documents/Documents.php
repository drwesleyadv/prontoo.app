<?php
declare(strict_types=1);
function document_identifier_consonants(): array
{

    return str_split("BCDFGHJKLMNPQRSTVWXYZ");
}
function document_identifier_base(): int
{

    return count(document_identifier_consonants());
}
function document_identifier_random_alphabet(): string
{

    $letters = document_identifier_consonants();
    shuffle($letters);
    return implode("", $letters);
}
function document_identifier_valid_alphabet(string $alphabet): bool
{

    $alphabet = strtoupper(trim($alphabet));
    $letters = str_split($alphabet);
    $allowed = document_identifier_consonants();
    sort($letters);
    sort($allowed);
    return strlen($alphabet) === document_identifier_base() &&
        $letters === $allowed;
}
function document_identifier_digits(int $sequence): array
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
function document_identifier_numeric_part(int $sequence): string
{

    return implode(
        "",
        array_map(
            static  fn($d) => (string) $d,
            document_identifier_digits($sequence),
        ),
    );
}
function document_identifier_encode(int $sequence, string $alphabet): string
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
function document_identifier_display(?string $identifier): string
{

    $identifier = strtoupper(mb_trim((string) $identifier));
    return preg_match('/^[A-Z]{3,12}$/', $identifier) ? $identifier : "";
}
function document_identifier_capacity_for_length(int $length = 3): int
{

    $length = max(1, $length);
    return (int) (document_identifier_base() ** $length);
}
function document_assign_identifier(int $cid, int $docId): string
{

    if ($cid <= 0 || $docId <= 0) {
        throw new RuntimeException("Documento inválido para identificação.");
    }
    return db_tx(function () use ($cid, $docId) {

        $doc = one(
            "SELECT id,document_identifier FROM pi_documents WHERE id=? AND clinic_id=? LIMIT 1 FOR UPDATE",
            [$docId, $cid],
        );
        if (!$doc) {
            throw new RuntimeException(
                "Documento não encontrado para identificação.",
            );
        }
        $current = document_identifier_display(
            $doc["document_identifier"] ?? "",
        );
        if ($current !== "") {
            return $current;
        }
        $clinic = one(
            "SELECT id,document_code_alphabet,document_sequence FROM pi_clinics WHERE id=? LIMIT 1 FOR UPDATE",
            [$cid],
        );
        if (!$clinic) {
            throw new RuntimeException(
                "Consultório não encontrado para identificação documental.",
            );
        }
        $alphabet = strtoupper(
            mb_trim((string) ($clinic["document_code_alphabet"] ?? "")),
        );
        if (!document_identifier_valid_alphabet($alphabet)) {
            $alphabet = document_identifier_random_alphabet();
        }
        $seq =
            max(
                (int) ($clinic["document_sequence"] ?? 0),
                (int) (val(
                    "SELECT COALESCE(MAX(document_sequence),0) FROM pi_documents WHERE clinic_id=?",
                    [$cid],
                ) ?? 0),
            ) + 1;
        for ($tries = 0; $tries < 100; $tries++) {
            $identifier = document_identifier_encode($seq, $alphabet);
            $exists =
                (int) (val(
                    "SELECT COUNT(*) FROM pi_documents WHERE clinic_id=? AND document_identifier=? AND id<>?",
                    [$cid, $identifier, $docId],
                ) ?? 0);
            if ($exists === 0) {
                q(
                    "UPDATE pi_clinics SET document_code_alphabet=?, document_sequence=? WHERE id=?",
                    [$alphabet, $seq, $cid],
                );
                q(
                    "UPDATE pi_documents SET document_sequence=?, document_identifier=? WHERE id=? AND clinic_id=?",
                    [$seq, $identifier, $docId, $cid],
                );
                return $identifier;
            }
            $seq++;
        }
        throw new RuntimeException(
            "Não foi possível gerar identificador documental único.",
        );
    });
}
function document_print_header_html(?string $identifier): string
{

    return "";
}
function document_print_footer_html(?string $identifier): string
{

    $id = document_identifier_display($identifier);
    return $id !== ""
        ? '<footer class="doc-print-footer" aria-label="Identificador do documento para segunda via"><span class="doc-print-identifier">' .
                e($id) .
                "</span></footer>"
        : "";
}
function document_status_label(string $status): string
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
function document_status_class(string $status): string
{

    return match ($status) {
        "emitido", "entregue", "approved" => "ok",
        "preparado", "pendente_assinatura", "pending_approval" => "warn",
        "cancelado", "substituido", "rejected" => "bad",
        default => "neutral",
    };
}
function document_editable_status(string $status): bool
{

    return in_array($status, ["rascunho", "preparado"], true);
}
function create_document_draft_from_template(
    array $c,
    int $templateId,
    int $patientId = 0,
    int $appointmentId = 0,
    array $context = [],
): int {

    $cid = clinic_id_required($c);
    $uid = (int) ($c["user"]["id"] ?? 0);
    [$patientId, $appointmentId, $appt] = document_resolve_patient_appointment(
        $cid,
        $patientId,
        $appointmentId,
    );
    if ($patientId > 0) {
        $block = patient_sensitive_block_reason($cid, $patientId);
        if ($block !== null) {
            throw new RuntimeException($block);
        }
    }
    if ($templateId <= 0) {
        throw new RuntimeException("Escolha um modelo aprovado.");
    }
    [$where, $params] = document_template_visible_where($c, "dt");
    $tpl = one(
        "SELECT dt.id,dt.owner_user_id,dt.owner_role,dt.type_key,dt.title,dt.body,dt.status FROM pi_document_templates dt WHERE dt.id=? AND dt.clinic_id=? AND $where",
        array_merge([$templateId, $cid], $params),
    );
    if (!$tpl) {
        throw new RuntimeException(
            "Modelo não encontrado para esta credencial.",
        );
    }
    if ((string) $tpl["status"] !== "approved") {
        throw new RuntimeException("Este modelo ainda não está aprovado.");
    }
    if (!document_can_issue_template($c, $tpl)) {
        throw new RuntimeException(
            "Este modelo não pode ser usado por este cargo.",
        );
    }
    $contextJson = document_context_json_encode($context);
    $vars = document_issue_context($c, $patientId, $appointmentId, $context);
    $content = render_document_body((string) $tpl["body"], $vars);
    q(
        "INSERT INTO pi_documents (clinic_id,template_id,patient_link_id,appointment_id,context_json,issued_by,type_key,title,content,document_status,issued_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?, 'preparado',NOW(),NOW())",
        [
            $cid,
            $templateId,
            $patientId > 0 ? $patientId : null,
            $appointmentId > 0 ? $appointmentId : null,
            $contextJson,
            $uid,
            (string) $tpl["type_key"],
            (string) $tpl["title"],
            $content,
        ],
    );
    $docId = db_last_insert_id();
    audit("documento_previsualizacao_criada", "documento", $docId, [
        "titulo" => $tpl["title"] ?? "",
        "document_type" => (string) $tpl["type_key"],
        "patient_link_id" => $patientId > 0 ? $patientId : null,
        "appointment_id" => $appointmentId > 0 ? $appointmentId : null,
        "contexto_documental" => document_context_json_decode($contextJson),
        "patient_name" => $patientId > 0 ? $vars["paciente"] ?? "" : "",
        "audit_body" =>
            "Pré-visualização criada a partir de modelo aprovado. Conteúdo livre não é editável antes da emissão.",
    ]);
    return $docId;
}
function save_document_draft(
    array $c,
    int $docId,
    string $title,
    int $patientId,
    string $content,
    string $status = "preparado",
    int $appointmentId = 0,
    array $context = [],
): void {

    $doc = fetch_document_for_current_user($c, $docId);
    if (!$doc) {
        throw new RuntimeException("Documento não encontrado.");
    }
    $current = (string) ($doc["document_status"] ?? "emitido");
    if (!document_editable_status($current)) {
        throw new RuntimeException(
            "Documento já emitido não pode ser editado. Cancele ou substitua por uma nova versão.",
        );
    }
    if (
        (int) ($doc["issued_by"] ?? 0) !== (int) ($c["user"]["id"] ?? 0) &&
        (string) !has_effective_role($c, "gerente")
    ) {
        throw new RuntimeException(
            "Apenas o criador ou o Administrativo pode ajustar esta pré-visualização.",
        );
    }
    $cid = (int) $c["clinic_id"];
    [$patientId, $appointmentId, $appt] = document_resolve_patient_appointment(
        $cid,
        $patientId,
        $appointmentId,
    );
    if ($patientId > 0) {
        $block = patient_sensitive_block_reason($cid, $patientId);
        if ($block !== null) {
            throw new RuntimeException($block);
        }
    }
    $tpl = one(
        "SELECT dt.id,dt.title,dt.body,dt.type_key FROM pi_document_templates dt WHERE dt.id=? AND dt.clinic_id=? AND dt.status='approved'",
        [(int) ($doc["template_id"] ?? 0), $cid],
    );
    if (!$tpl) {
        throw new RuntimeException(
            "Modelo original não está aprovado ou não foi encontrado.",
        );
    }
    $contextJson = document_context_json_encode($context);
    $vars = document_issue_context($c, $patientId, $appointmentId, $context);
    $body = render_document_body((string) $tpl["body"], $vars);
    if (document_body_is_empty($body)) {
        throw new RuntimeException(
            "O modelo aprovado gerou conteúdo vazio. Revise o modelo antes de emitir.",
        );
    }
    q(
        "UPDATE pi_documents SET title=?, patient_link_id=?, appointment_id=?, context_json=?, content=?, document_status='preparado', updated_at=NOW() WHERE id=? AND clinic_id=?",
        [
            (string) $tpl["title"],
            $patientId > 0 ? $patientId : null,
            $appointmentId > 0 ? $appointmentId : null,
            $contextJson,
            $body,
            $docId,
            $cid,
        ],
    );
    audit("documento_vinculos_previsualizacao", "documento", $docId, [
        "titulo" => $tpl["title"] ?? "",
        "status" => document_status_label("preparado"),
        "patient_link_id" => $patientId > 0 ? $patientId : null,
        "appointment_id" => $appointmentId > 0 ? $appointmentId : null,
        "contexto_documental" => document_context_json_decode($contextJson),
        "audit_body" =>
            "Vínculos contextuais associados à pré-visualização do documento. O conteúdo permanece derivado exclusivamente do modelo aprovado.",
    ]);
}
function discard_document_draft(array $c, int $docId): void
{

    $doc = fetch_document_for_current_user($c, $docId);
    if (!$doc) {
        throw new RuntimeException("Documento não encontrado.");
    }
    $current = (string) ($doc["document_status"] ?? "emitido");
    if (!document_editable_status($current)) {
        throw new RuntimeException(
            "Documento já emitido não pode ser descartado.",
        );
    }
    if (
        (int) ($doc["issued_by"] ?? 0) !== (int) ($c["user"]["id"] ?? 0) &&
        (string) !has_effective_role($c, "gerente")
    ) {
        throw new RuntimeException(
            "Apenas o criador ou o Administrativo pode descartar esta pré-visualização.",
        );
    }
    q(
        "UPDATE pi_documents SET document_status='cancelado', updated_at=NOW() WHERE id=? AND clinic_id=?",
        [$docId, (int) $c["clinic_id"]],
    );
    audit("documento_descartado", "documento", $docId, [
        "titulo" => $doc["title"] ?? "",
        "status" => "Cancelado",
        "audit_body" =>
            "Pré-visualização de documento descartada antes da emissão.",
    ]);
}
function confirm_document_issue(array $c, int $docId): void
{

    $doc = fetch_document_for_current_user($c, $docId);
    if (!$doc) {
        throw new RuntimeException("Documento não encontrado.");
    }
    $status = (string) ($doc["document_status"] ?? "emitido");
    if ($status !== "preparado") {
        throw new RuntimeException(
            "Confira a pré-visualização antes de confirmar a emissão.",
        );
    }
    if (
        (int) ($doc["issued_by"] ?? 0) !== (int) ($c["user"]["id"] ?? 0) &&
        (string) !has_effective_role($c, "gerente")
    ) {
        throw new RuntimeException(
            "Apenas o criador ou a Gestão pode confirmar este documento.",
        );
    }
    if (document_body_is_empty((string) ($doc["content"] ?? ""))) {
        throw new RuntimeException(
            "Conteúdo vazio. Revise o modelo antes de emitir.",
        );
    }
    $identifier = db_tx(function () use ($c, $docId) {

        q(
            "UPDATE pi_documents SET document_status='emitido', confirmed_at=NOW(), issued_at=NOW(), updated_at=NOW() WHERE id=? AND clinic_id=?",
            [$docId, (int) $c["clinic_id"]],
        );
        return document_assign_identifier((int) $c["clinic_id"], $docId);
    });
    $types = document_type_options();
    audit("documento_emitido", "documento", $docId, [
        "titulo" => $doc["title"] ?? "",
        "document_identifier" => $identifier,
        "document_type" => (string) ($doc["type_key"] ?? ""),
        "document_type_label" =>
            $types[(string) ($doc["type_key"] ?? "")] ?? "Documento",
        "patient_link_id" => (int) ($doc["patient_link_id"] ?? 0),
        "appointment_id" => (int) ($doc["appointment_id"] ?? 0),
        "patient_name" => $doc["patient_name"] ?? "",
        "audit_body" =>
            "Documento confirmado a partir de modelo pré-aprovado. Identificador: " .
            $identifier,
    ]);
}
function document_type_options(): array
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
function document_system_field_groups(): array
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
function document_system_fields(): array
{

    $out = [];
    foreach (document_system_field_groups() as $group) {
        foreach ($group["fields"] ?? [] as $key => $label) {
            $out[$key] = $label;
        }
    }
    return $out;
}
function document_allowed_types_for_role(string $role): array
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
function document_type_options_for_role(string $role): array
{

    $all = document_type_options();
    $allowed = array_flip(document_allowed_types_for_role($role));
    return array_intersect_key($all, $allowed);
}
function document_role_config(string $role, int $cid): array
{

    $label = role_label_for($role, $cid);
    $base = [
        "recepcionista" => [
            "title" => "Documentos da Recepção",
            "subtitle" =>
                "Criação, impressão e entrega de documentos administrativos para o paciente.",
            "quick" => "Emissão rápida",
            "models" => "Modelos administrativos",
            "history" => "Documentos recentes",
            "empty" => "Nenhum documento administrativo disponível.",
            "icon" => "post_add",
        ],
        "assistente" => [
            "title" => "Documentos do Assistente",
            "subtitle" =>
                "Fichas, checklists e registros de preparo para deixar o atendimento pronto para a consulta.",
            "quick" => "Registros rápidos do assistente",
            "models" => "Modelos de preparo",
            "history" => "Documentos recentes",
            "empty" => "Nenhum modelo de triagem disponível.",
            "icon" => "clinical_notes",
        ],
        "medico" => [
            "title" => "Documentos Clínicos",
            "subtitle" =>
                "Rascunhos, pré-visualização, assinatura e emissão segura de documentos clínicos.",
            "quick" => "Emissão clínica",
            "models" => "Modelos clínicos",
            "history" => "Documentos recentes",
            "empty" => "Nenhum modelo clínico aprovado.",
            "icon" => "prescriptions",
        ],
        "gerente" => [
            "title" => "Emissão de Documentos",
            "subtitle" =>
                "Acompanhamento documental, pendências, entregas e modelos do consultório.",
            "quick" => "Controle documental",
            "models" => "Modelos do consultório",
            "history" => "Documentos recentes",
            "empty" => "Nenhum modelo disponível.",
            "icon" => "fact_check",
        ],
    ];
    return $base[$role] ?? [
        "title" => "Documentos",
        "subtitle" => "Documentos disponíveis para " . $label . ".",
        "quick" => "Emissão rápida",
        "models" => "Modelos",
        "history" => "Documentos recentes",
        "empty" => "Nenhum modelo disponível.",
        "icon" => "description",
    ];
}
function document_ds_metric(
    string $label,
    mixed $value,
    string $iconName,
    string $note = "",
    string $class = "",
): string {

    return '<article class="doc-ds-metric patient-kpi-card kpi-card ' .
        e($class) .
        '"><span class="doc-ds-metric-icon">' .
        icon($iconName) .
        '</span><p class="doc-ds-metric-copy"><b>' .
        e((string) $value) .
        "</b><span>" .
        e($label) .
        "</span>" .
        ($note !== "" ? "<small>" . e($note) . "</small>" : "") .
        "</p></article>";
}
function document_ds_status_chip(string $status): string
{

    return '<span class="doc-ds-chip ' .
        document_status_class($status) .
        '">' .
        e(document_status_label($status)) .
        "</span>";
}
function document_ds_type_label(array $typeOptions, ?string $type): string
{

    $type = (string) ($type ?? "");
    return $typeOptions[$type] ??
        ucfirst(str_replace("_", " ", $type ?: "documento"));
}
function document_ds_recent_row(array $d, array $typeOptions): string
{

    $st = (string) ($d["document_status"] ?? "emitido");
    $idText = document_identifier_display($d["document_identifier"] ?? "");
    $patient = mb_trim((string) ($d["patient_name"] ?? ""));
    if ($patient === "") {
        $patient = "Paciente";
    }
    $actor = mb_trim((string) ($d["issued_name"] ?? ""));
    $type = document_ds_type_label(
        $typeOptions,
        (string) ($d["type_key"] ?? ""),
    );
    $when = dt_br($d["updated_at"] ?: $d["issued_at"] ?? null);
    $idHtml =
        $idText !== ""
            ? '<span class="doc-ds-id">' .
                icon("fingerprint") .
                "<span>" .
                e($idText) .
                "</span></span>"
            : '<span class="doc-ds-id is-empty-id">' .
                icon("fingerprint") .
                "<span>sem código</span></span>";
    $issuerLabel = $actor !== "" ? first_name($actor) : "";
    $summary = "";
    if ($issuerLabel !== "" && $patient !== "") {
        $summary = "Emitido por " . $issuerLabel . " para " . $patient . ".";
    } elseif ($issuerLabel !== "") {
        $summary = "Emitido por " . $issuerLabel . ".";
    } elseif ($patient !== "") {
        $summary = "Emitido para " . $patient . ".";
    }
    $summaryHtml =
        $summary !== ""
            ? '<span class="doc-ds-row-summary">' .
                icon("assignment_ind") .
                "<span>" .
                e($summary) .
                "</span></span>"
            : "";
    $chips = "";
    if ($when !== "" && $when !== "—") {
        $chips .=
            '<span class="doc-ds-meta-chip">' .
            icon("schedule") .
            "<span>" .
            e($when) .
            "</span></span>";
    }
    $chips .=
        '<span class="doc-ds-meta-chip">' .
        icon("category") .
        "<span>" .
        e($type) .
        "</span></span>";
    $chips .=
        '<span class="doc-issued-state">' .
        document_ds_status_chip($st) .
        "</span>";
    $action = document_editable_status($st)
        ? '<a class="primary small" href="' .
            href("documents", ["doc" => (int) $d["id"]]) .
            '">' .
            icon("preview") .
            "<span>Conferir</span></a>"
        : '<a class="ghost small" href="' .
            href("document_view", ["id" => (int) $d["id"]]) .
            '">' .
            icon("visibility") .
            "<span>Visualizar</span></a>" .
            ($st === "emitido"
                ? '<a class="primary small" target="_blank" rel="noopener" href="' .
                    href("document_print", ["id" => (int) $d["id"]]) .
                    '">' .
                    icon("print") .
                    "<span>Imprimir</span></a>" .
                    document_pdf_link((int) $d["id"])
                : "");
    return '<article class="doc-issued-row doc-ds-row doc-issued-row-compact" data-ds-row-kind="surface"><span class="doc-history-icon doc-issued-icon doc-ds-row-icon">' .
        icon(document_editable_status($st) ? "preview" : "description") .
        '</span><span class="doc-issued-main doc-ds-row-main"><strong class="doc-issued-title doc-ds-row-title">' .
        $idHtml .
        "<span>" .
        e($d["title"] ?? "Documento") .
        "</span></strong>" .
        $summaryHtml .
        '<span class="doc-ds-row-meta">' .
        $chips .
        '</span></span><span class="doc-history-actions doc-issued-actions doc-ds-row-actions">' .
        $action .
        "</span></article>";
}
function document_ds_model_row(array $tpl, array $typeOptions, int $cid): string
{

    $kind = $typeOptions[(string) ($tpl["type_key"] ?? "")] ?? "Documento";
    $owner = role_label_for((string) ($tpl["owner_role"] ?? ""), $cid);
    $last = dt_br($tpl["last_used_at"] ?? null);
    $lastMeta =
        $last !== "" && $last !== "—"
            ? '<span class="doc-ds-meta-chip">' .
                icon("history") .
                "<span>Usado em " .
                e($last) .
                "</span></span>"
            : '<span class="doc-ds-meta-chip">' .
                icon("fiber_new") .
                "<span>Ainda não usado</span></span>";
    $patientHidden =
        (int) ($_GET["patient_id"] ?? 0) > 0
            ? '<input type="hidden" name="patient_link_id" value="' .
                (int) $_GET["patient_id"] .
                '">'
            : "";
    $appointmentHidden =
        (int) ($_GET["appointment_id"] ?? 0) > 0
            ? '<input type="hidden" name="appointment_id" value="' .
                (int) $_GET["appointment_id"] .
                '">'
            : "";
    return '<article class="doc-model-row doc-ds-model-row doc-ds-row"><span class="doc-history-icon doc-model-icon doc-ds-row-icon">' .
        icon("description") .
        '</span><span class="doc-model-copy doc-ds-row-main"><strong class="doc-ds-row-title">' .
        e($tpl["title"] ?? "Modelo") .
        '</strong><span class="doc-ds-row-meta"><span class="doc-ds-meta-chip">' .
        icon("category") .
        "<span>" .
        e($kind) .
        '</span></span><span class="doc-ds-meta-chip">' .
        icon("badge") .
        "<span>" .
        e($owner) .
        "</span></span>" .
        $lastMeta .
        '</span><small>Abre pré-visualização antes da emissão definitiva.</small></span><form method="post" class="doc-model-create doc-ds-row-actions">' .
        csrf_field() .
        '<input type="hidden" name="act" value="create_document"><input type="hidden" name="template_id" value="' .
        (int) $tpl["id"] .
        '">' .
        $patientHidden .
        $appointmentHidden .
        '<button class="primary small" type="submit">' .
        icon("preview") .
        "<span>Pré-visualizar</span></button></form></article>";
}
if (!function_exists("cpf_br")) {
    function cpf_br(string $cpf): string
    {

        $d = only_digits($cpf);
        return strlen($d) === 11
            ? substr($d, 0, 3) .
                    "." .
                    substr($d, 3, 3) .
                    "." .
                    substr($d, 6, 3) .
                    "-" .
                    substr($d, 9, 2)
            : $cpf;
    }
}
function document_template_visible_where(array $c, string $alias = "dt"): array
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
function can_edit_document_template(array $c, array $tpl): bool
{

    $role = (string) ($c["role"] ?? "");
    if ($role === "gerente") {
        return true;
    }
    return (string) ($tpl["owner_role"] ?? "") === $role;
}
function document_template_status_after_save(
    array $c,
    string $ownerRole,
    bool $isUpdate = false,
): array {

    $editorRole = (string) ($c["role"] ?? "");
    if ($editorRole !== "gerente") {
        return ["pending_approval", 1, null, null];
    }
    return ["approved", 0, (int) $c["user"]["id"], now()];
}
function document_can_issue_template(array $c, array $tpl): bool
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
function document_sanitize_html(string $html): string
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
function document_body_to_html(string $body): string
{

    $body = trim($body);
    if ($body === "") {
        return "";
    }
    if ($body === strip_tags($body)) {
        $paras = preg_split("/\R{2,}/", $body) ?: [$body];
        $out = "";
        foreach ($paras as $p) {
            $p = trim($p);
            if ($p === "") {
                continue;
            }
            $out .= "<p>" . nl2br(e($p), false) . "</p>";
        }
        return $out;
    }
    return document_sanitize_html($body);
}
function document_print_page_core_html(
    string $html,
    ?string $identifier = null,
): string {

    return '<div class="doc-print-page" role="document">' .
        document_print_header_html($identifier) .
        '<div class="doc-print-content"><div class="doc-print-main">' .
        $html .
        "</div></div>" .
        document_print_footer_html($identifier) .
        "</div>";
}
function document_preview_page_html(
    string $html,
    ?string $identifier = null,
): string {

    return '<div class="doc-issued-body doc-a4-preview" aria-label="Pré-visualização em folha A4 aproximada da impressão"><div class="doc-a4-preview-frame">' .
        document_print_page_core_html($html, $identifier) .
        "</div></div>";
}
function document_print_document_shell_html(
    array $doc,
    bool $autoPrint = true,
    string $mode = "print",
): string {

    $title = mb_trim((string) ($doc["title"] ?? "Documento"));
    if ($title === "") {
        $title = "Documento";
    }
    $identifier = $doc["document_identifier"] ?? null;
    $content = document_body_to_html((string) ($doc["content"] ?? ""));
    $nonce = e((string) ($GLOBALS["csp_nonce"] ?? ""));
    $script = $autoPrint
        ? '<script nonce="' .
            $nonce .
            '">window.addEventListener("load",function(){setTimeout(function(){window.print()},120)});</script>'
        : "";
    $modeClass = preg_replace("/[^a-z0-9_-]/i", "", (string) $mode) ?: "print";
    return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>' .
        e($title) .
        '</title><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
        rawurlencode(
            defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
        ) .
        '"></head><body class="document-print document-print-exact document-print-' .
        $modeClass .
        '"><main class="doc-print-standalone" aria-label="Documento pronto para impressão">' .
        document_print_page_core_html($content, $identifier) .
        "</main>" .
        $script .
        "</body></html>";
}
function document_body_is_empty(string $html): bool
{

    return trim(
        preg_replace(
            "/\s+/",
            " ",
            strip_tags(str_replace(["&nbsp;", "<br>"], [" ", " "], $html)),
        ),
    ) === "";
}
function document_issue_meta_sentence(
    ?string $issuedName,
    ?string $patientName,
    null|string|int $issuedAt,
    string $status = "emitido",
): string {

    $actor = mb_trim((string) ($issuedName ?? ""));
    $actor = $actor !== "" ? first_name($actor) : "Colaborador";
    $patient = mb_trim((string) ($patientName ?? ""));
    $patient = $patient !== "" ? $patient : "paciente";
    $when = dt_br($issuedAt);
    $verb =
        document_editable_status($status) && $status !== "emitido"
            ? "preparou"
            : "emitiu";
    return $actor . " " . $verb . " para " . $patient . " em " . $when . ".";
}
function render_document_body(string $body, array $vars): string
{

    $map = [];
    foreach ($vars as $k => $v) {
        $map["{{" . $k . "}}"] = e((string) $v);
    }
    return document_sanitize_html(strtr(document_body_to_html($body), $map));
}
function document_time_br(?string $dt): string
{

    $ts = app_storage_timestamp($dt);
    return $ts ? date("H\hi", $ts) : "";
}
function document_appointment_row(int $cid, int $appointmentId): ?array
{

    if ($cid <= 0 || $appointmentId <= 0) {
        return null;
    }
    return one(
        "SELECT a.id,a.patient_link_id,a.doctor_user_id,a.start_at,a.end_at,a.reason,a.notes,a.status,a.consultation_started_at,a.consultation_finished_at,COALESCE(pr.title,a.reason,'Consulta') AS procedure_title,u.name AS doctor_name,p.full_name AS patient_name FROM pi_appointments a LEFT JOIN pi_procedures pr ON pr.id=a.procedure_id AND pr.clinic_id=a.clinic_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE a.id=? AND a.clinic_id=? LIMIT 1",
        [$appointmentId, $cid],
    ) ?:
        null;
}
function document_resolve_patient_appointment(
    int $cid,
    int $patientId = 0,
    int $appointmentId = 0,
): array {

    if ($appointmentId > 0) {
        $appt = document_appointment_row($cid, $appointmentId);
        if (!$appt) {
            throw new RuntimeException(
                "Agendamento não encontrado para este consultório.",
            );
        }
        $apptPatient = (int) ($appt["patient_link_id"] ?? 0);
        if ($apptPatient > 0) {
            require_patient_in_clinic($cid, $apptPatient);
            if ($patientId > 0 && $patientId !== $apptPatient) {
                throw new RuntimeException(
                    "O agendamento escolhido pertence a outro paciente.",
                );
            }
            $patientId = $apptPatient;
        } elseif ($patientId <= 0) {
            throw new RuntimeException(
                "Escolha o paciente antes de vincular este agendamento.",
            );
        }
        return [$patientId, $appointmentId, $appt];
    }
    if ($patientId > 0) {
        require_patient_in_clinic($cid, $patientId);
    }
    return [$patientId, 0, null];
}
function document_appointment_options(
    int $cid,
    int $selectedId = 0,
    int $limit = 240,
): array {

    $limit = max(20, min(500, $limit));
    $rows = q(
        "SELECT a.id,a.patient_link_id,a.start_at,a.end_at,a.reason,a.status,a.consultation_started_at,a.consultation_finished_at,COALESCE(pr.title,a.reason,'Consulta') AS procedure_title,u.name AS doctor_name,p.full_name AS patient_name FROM pi_appointments a LEFT JOIN pi_procedures pr ON pr.id=a.procedure_id AND pr.clinic_id=a.clinic_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE a.clinic_id=? AND COALESCE(a.status,'')<>'cancelado' ORDER BY ABS(TIMESTAMPDIFF(SECOND,a.start_at,NOW())) ASC, a.start_at DESC LIMIT " .
            (int) $limit,
        [$cid],
    )->fetchAll();
    $out = [];
    foreach ($rows as $a) {
        $id = (int) $a["id"];
        $when = dt_br($a["start_at"] ?? "");
        $patient = trim(
            (string) ($a["patient_name"] ?? "Paciente não vinculado"),
        );
        $proc = mb_trim((string) ($a["procedure_title"] ?? "Consulta"));
        $doctor = mb_trim((string) ($a["doctor_name"] ?? "Profissional"));
        $out[$id] = $when . " · " . $patient . " · " . $proc . " · " . $doctor;
    }
    if ($selectedId > 0 && !isset($out[$selectedId])) {
        $a = document_appointment_row($cid, $selectedId);
        if ($a) {
            $out[$selectedId] =
                dt_br($a["start_at"] ?? "") .
                " · " .
                ($a["patient_name"] ?? "" ?: "Paciente não vinculado") .
                " · " .
                ($a["procedure_title"] ?? "" ?: "Consulta") .
                " · " .
                ($a["doctor_name"] ?? "" ?: "Profissional");
        }
    }
    return $out;
}
function document_appointment_select_field(
    int $cid,
    int $selectedId = 0,
): string {

    return select_label(
        "Agendamento vinculado",
        "appointment_id",
        ["0" => "Sem agendamento vinculado"] +
            document_appointment_options($cid, $selectedId),
        (string) max(0, $selectedId),
    );
}
function document_context_json_decode(mixed $raw): array
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
function document_context_json_encode(array $context): string
{

    $clean = [];
    foreach (
        [
            "lead_id",
            "procedure_id",
            "task_id",
            "care_id",
            "collaborator_user_id",
        ]
        as $k
    ) {
        $v = max(0, (int) ($context[$k] ?? 0));
        if ($v > 0) {
            $clean[$k] = $v;
        }
    }
    return $clean
        ? (json_encode(
            $clean,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?:
            "{}")
        : "{}";
}
function document_context_ids_from_post(array $c): array
{

    $cid = (int) ($c["clinic_id"] ?? 0);
    $ctx = [];
    $checks = [
        "lead_id" => ["table" => "pi_leads", "label" => "Interessado"],
        "procedure_id" => [
            "table" => "pi_procedures",
            "label" => "Procedimento",
        ],
        "task_id" => ["table" => "pi_tasks", "label" => "Tarefa"],
        "care_id" => ["table" => "pi_care", "label" => "Atividade"],
    ];
    foreach ($checks as $key => $cfg) {
        $id = max(0, (int) ($_POST[$key] ?? 0));
        if ($id <= 0) {
            continue;
        }
        $exists = (int) safe_val(
            "SELECT id FROM " .
                $cfg["table"] .
                " WHERE id=? AND clinic_id=? LIMIT 1",
            [$id, $cid],
            0,
        );
        if ($exists <= 0) {
            throw new RuntimeException(
                $cfg["label"] . " não encontrado(a) neste consultório.",
            );
        }
        $ctx[$key] = $id;
    }
    $collab = max(0, (int) ($_POST["collaborator_user_id"] ?? 0));
    if ($collab > 0) {
        if (!clinic_user_exists($cid, $collab)) {
            throw new RuntimeException(
                "Colaborador não encontrado neste consultório.",
            );
        }
        $ctx["collaborator_user_id"] = $collab;
    }
    return $ctx;
}
function document_context_select_options(
    int $cid,
    string $kind,
    int $selectedId = 0,
    int $limit = 120,
): array {

    $limit = max(20, min(240, $limit));
    $out = [];
    try {
        switch ($kind) {
            case "lead":
                $rows = q(
                    "SELECT id,name,phone,interest,stage,created_at FROM pi_leads WHERE clinic_id=? ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT " .
                        (int) $limit,
                    [$cid],
                )->fetchAll();
                foreach ($rows as $r) {
                    $meta = array_filter([
                        (string) ($r["phone"] ?? ""),
                        (string) ($r["interest"] ?? ""),
                        (string) ($r["stage"] ?? ""),
                    ]);
                    $out[(int) $r["id"]] =
                        mb_trim((string) $r["name"]) .
                        ($meta ? " · " . implode(" · ", $meta) : "");
                }
                break;
            case "procedure":
                $rows = array_slice(procedure_options($cid, true), 0, $limit);
                foreach ($rows as $r) {
                    $meta = array_filter([
                        (string) ($r["category"] ?? ""),
                        format_minutes((int) ($r["duration_minutes"] ?? 0)),
                        (int) ($r["price_cents"] ?? 0) > 0
                            ? money_br((int) $r["price_cents"])
                            : "",
                    ]);
                    $out[(int) $r["id"]] =
                        mb_trim((string) $r["title"]) .
                        ($meta ? " · " . implode(" · ", $meta) : "");
                }
                break;
            case "task":
                $rows = q(
                    "SELECT t.id,t.title,t.status,t.due_at,u.name AS assigned_name FROM pi_tasks t LEFT JOIN pi_users u ON u.id=COALESCE(t.assigned_to,t.target_user_id) WHERE t.clinic_id=? ORDER BY FIELD(t.status,'aberta','em_andamento','concluida'), COALESCE(t.due_at,t.created_at) DESC, t.id DESC LIMIT " .
                        (int) $limit,
                    [$cid],
                )->fetchAll();
                foreach ($rows as $r) {
                    $meta = array_filter([
                        document_status_label((string) ($r["status"] ?? "")),
                        !empty($r["due_at"])
                            ? dt_br((string) $r["due_at"])
                            : "",
                        (string) ($r["assigned_name"] ?? ""),
                    ]);
                    $out[(int) $r["id"]] =
                        mb_trim((string) $r["title"]) .
                        ($meta ? " · " . implode(" · ", $meta) : "");
                }
                break;
            case "care":
                $rows = q(
                    "SELECT c.id,c.record_type,c.title,c.created_at,p.full_name AS patient_name FROM pi_care c JOIN pi_patients pl ON pl.id=c.patient_link_id AND pl.clinic_id=c.clinic_id JOIN pi_persons p ON p.id=pl.person_id WHERE c.clinic_id=? AND c.deleted_at IS NULL ORDER BY c.created_at DESC,c.id DESC LIMIT " .
                        (int) $limit,
                    [$cid],
                )->fetchAll();
                foreach ($rows as $r) {
                    $title = mb_trim((string) ($r["title"] ?? ""));
                    if ($title === "") {
                        $title = "Atividade";
                    }
                    $meta = array_filter([
                        (string) ($r["patient_name"] ?? ""),
                        patient_record_type_label(
                            (string) ($r["record_type"] ?? ""),
                        ),
                        dt_br((string) ($r["created_at"] ?? "")),
                    ]);
                    $out[(int) $r["id"]] =
                        $title . ($meta ? " · " . implode(" · ", $meta) : "");
                }
                break;
            case "collaborator":
                $collaboratorLoader =  fn(): array => q(
                    "SELECT u.id,u.name,u.email,GROUP_CONCAT(DISTINCT cr.label ORDER BY cr.sort_order SEPARATOR ', ') AS role_label FROM pi_users u JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE u.active=1 GROUP BY u.id,u.name,u.email ORDER BY u.name ASC LIMIT " .
                        (int) $limit,
                    [$cid],
                )->fetchAll();
                $rows = function_exists("server_json_cache_remember")
                    ? server_json_cache_remember(
                        "catalog",
                        server_json_cache_safe_key("collaborators", [$cid, $limit]),
                        server_json_cache_ttl("catalog"),
                        $collaboratorLoader,
                        ["clinic:" . $cid, "table:pi_user_roles"],
                    )
                    : $collaboratorLoader();
                foreach ($rows as $r) {
                    $meta = array_filter([
                        (string) ($r["role_label"] ?? ""),
                        (string) ($r["email"] ?? ""),
                    ]);
                    $out[(int) $r["id"]] =
                        mb_trim((string) $r["name"]) .
                        ($meta ? " · " . implode(" · ", $meta) : "");
                }
                break;
        }
    } catch (Throwable $e) {
        error_log(
            "[Prontoo document_context_select_options] " .
                $kind .
                " " .
                $e->getMessage(),
        );
    }
    if ($selectedId > 0 && !isset($out[$selectedId])) {
        try {
            $label = "";
            if ($kind === "lead") {
                $label = (string) safe_val(
                    "SELECT name FROM pi_leads WHERE id=? AND clinic_id=?",
                    [$selectedId, $cid],
                    "",
                );
            } elseif ($kind === "procedure") {
                $label = (string) safe_val(
                    "SELECT title FROM pi_procedures WHERE id=? AND clinic_id=?",
                    [$selectedId, $cid],
                    "",
                );
            } elseif ($kind === "task") {
                $label = (string) safe_val(
                    "SELECT title FROM pi_tasks WHERE id=? AND clinic_id=?",
                    [$selectedId, $cid],
                    "",
                );
            } elseif ($kind === "care") {
                $label = (string) safe_val(
                    "SELECT COALESCE(title,record_type) FROM pi_care WHERE id=? AND clinic_id=?",
                    [$selectedId, $cid],
                    "",
                );
            } elseif ($kind === "collaborator") {
                $label = (string) safe_val(
                    "SELECT name FROM pi_users u WHERE id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1)",
                    [$selectedId, $cid],
                    "",
                );
            }
            if ($label !== "") {
                $out[$selectedId] = $label;
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $e->getMessage(),
            );
        }
    }
    return $out;
}
function document_context_binding_fields(array $c, array $doc): string
{

    $cid = (int) ($c["clinic_id"] ?? 0);
    $ctx = document_context_json_decode($doc["context_json"] ?? "{}");
    $lead = (int) ($ctx["lead_id"] ?? 0);
    $proc = (int) ($ctx["procedure_id"] ?? 0);
    $task = (int) ($ctx["task_id"] ?? 0);
    $care = (int) ($ctx["care_id"] ?? 0);
    $collab = (int) ($ctx["collaborator_user_id"] ?? 0);
    $html =
        '<div class="doc-context-grid"><section><h4>' .
        icon("personal_injury") .
        '<span>Paciente e agenda</span></h4><div class="two">' .
        form_row(
            "",
            patient_lookup_field(
                $cid,
                "patient_link_id",
                (string) ($doc["patient_link_id"] ?? 0),
                "patient_search_doc_" . (int) $doc["id"],
            ),
        ) .
        document_appointment_select_field(
            $cid,
            (int) ($doc["appointment_id"] ?? 0),
        ) .
        "</div></section>";
    $html .=
        "<section><h4>" .
        icon("dataset_linked") .
        '<span>Registros contextuais</span></h4><div class="two">' .
        select_label(
            "Interessado",
            "lead_id",
            ["0" => "Sem interessado vinculado"] +
                document_context_select_options($cid, "lead", $lead),
            (string) $lead,
        ) .
        select_label(
            "Procedimento",
            "procedure_id",
            ["0" => "Sem procedimento vinculado"] +
                document_context_select_options($cid, "procedure", $proc),
            (string) $proc,
        ) .
        select_label(
            "Tarefa",
            "task_id",
            ["0" => "Sem tarefa vinculada"] +
                document_context_select_options($cid, "task", $task),
            (string) $task,
        ) .
        select_label(
            "Atividade da ficha",
            "care_id",
            ["0" => "Sem atividade vinculada"] +
                document_context_select_options($cid, "care", $care),
            (string) $care,
        ) .
        select_label(
            "Colaborador",
            "collaborator_user_id",
            ["0" => "Usar colaborador atual"] +
                document_context_select_options($cid, "collaborator", $collab),
            (string) $collab,
        ) .
        "</div></section></div>";
    return $html;
}
function document_issue_context(
    array $c,
    int $patientId = 0,
    int $appointmentId = 0,
    array $context = [],
): array {

    $cid = (int) ($c["clinic_id"] ?? 0);
    [$patientId, $appointmentId, $appt] = document_resolve_patient_appointment(
        $cid,
        $patientId,
        $appointmentId,
    );
    $patientName = "";
    $patientCpf = "";
    $patientBirth = "";
    $patientCity = "";
    $guardianName = "";
    $guardianCpf = "";
    $guardianRel = "";
    if ($patientId > 0) {
        $pat = one(
            "SELECT p.full_name,p.cpf,p.birth_date,pl.phone,pl.email,pl.address,pl.address_number,pl.address_neighborhood,pl.address_city,pl.address_state,pl.address_zip FROM pi_patients pl JOIN pi_persons p ON p.id=pl.person_id WHERE pl.id=? AND pl.clinic_id=? AND pl.active=1",
            [$patientId, $cid],
        );
        if ($pat) {
            $patientName = (string) ($pat["full_name"] ?? "");
            $patientCpf = cpf_br((string) ($pat["cpf"] ?? ""));
            $patientBirth = date_br($pat["birth_date"] ?? null);
            $patientCity = city_state_label(
                $pat["address_city"] ?? "",
                $pat["address_state"] ?? "",
            );
        }
        $guardian = patient_primary_legal_guardian($cid, $patientId);
        if ($guardian) {
            $relOpts = patient_guardian_relationship_options();
            $guardianName = (string) ($guardian["full_name"] ?? "");
            $guardianCpf = cpf_br((string) ($guardian["cpf"] ?? ""));
            $guardianRel =
                $relOpts[(string) ($guardian["relationship"] ?? "")] ??
                "Responsável Legal";
        }
    }
    $cl =
        one(
            "SELECT display_name,legal_name,address_city,address_state,address_line FROM pi_clinics WHERE id=?",
            [$cid],
        ) ?:
        [];
    $apptStart = (string) ($appt["start_at"] ?? "");
    $apptEnd = (string) ($appt["end_at"] ?? "");
    $realStart = (string) ($appt["consultation_started_at"] ?? "");
    $realEnd = (string) ($appt["consultation_finished_at"] ?? "");
    $scheduledRange = trim(
        ($apptStart !== "" ? document_time_br($apptStart) : "") .
            ($apptEnd !== "" ? " a " . document_time_br($apptEnd) : ""),
    );
    $realDuration = "";
    if (
        $realStart !== "" &&
        $realEnd !== "" &&
        strtotime($realEnd) > strtotime($realStart)
    ) {
        $realDuration = format_minutes(
            (int) floor((strtotime($realEnd) - strtotime($realStart)) / 60),
        );
    }
    $ctx = document_context_json_decode($context);
    $collaboratorName = (string) ($c["user"]["name"] ?? "Colaborador");
    $collaboratorEmail = "";
    $collaboratorRole = "";
    $professional = (string) ($c["user"]["name"] ?? "Profissional");
    $professionalRole = role_label_for((string) ($c["role"] ?? ""), $cid);
    $collabId = (int) ($ctx["collaborator_user_id"] ?? 0);
    if ($collabId > 0 && clinic_user_exists($cid, $collabId)) {
        $u = one(
            "SELECT u.id,u.name,u.email,GROUP_CONCAT(DISTINCT ur.role_code ORDER BY ur.role_code SEPARATOR ',') AS role_codes,GROUP_CONCAT(DISTINCT cr.label ORDER BY cr.sort_order SEPARATOR ', ') AS role_labels FROM pi_users u JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE u.id=? GROUP BY u.id,u.name,u.email",
            [$cid, $collabId],
        );
        if ($u) {
            $collaboratorName = (string) ($u["name"] ?? $collaboratorName);
            $collaboratorEmail = (string) ($u["email"] ?? "");
            $collaboratorRole = (string) ($u["role_labels"] ?? "");
            if (str_contains((string) ($u["role_codes"] ?? ""), "medico")) {
                $professional = $collaboratorName;
                $professionalRole = $collaboratorRole;
            }
        }
    }
    $vars = [
        "paciente" => $patientName ?: "Paciente",
        "nome_paciente" => $patientName ?: "Paciente",
        "paciente_nome" => $patientName ?: "Paciente",
        "cpf_paciente" => $patientCpf ?: "",
        "paciente_cpf" => $patientCpf ?: "",
        "nascimento_paciente" => $patientBirth ?: "",
        "paciente_nascimento" => $patientBirth ?: "",
        "telefone_paciente" => (string) ($pat["phone"] ?? ""),
        "paciente_telefone" => (string) ($pat["phone"] ?? ""),
        "email_paciente" => (string) ($pat["email"] ?? ""),
        "paciente_email" => (string) ($pat["email"] ?? ""),
        "endereco_paciente" => trim(
            (string) ($pat["address"] ?? "") .
                ((string) ($pat["address_number"] ?? "") !== ""
                    ? ", " . (string) $pat["address_number"]
                    : ""),
        ),
        "bairro_paciente" => (string) ($pat["address_neighborhood"] ?? ""),
        "cep_paciente" => mask_cep((string) ($pat["address_zip"] ?? "")),
        "cidade_paciente" => $patientCity,
        "responsavel_legal" => $guardianName,
        "cpf_responsavel_legal" => $guardianCpf,
        "vinculo_responsavel_legal" => $guardianRel,
        "profissional" => $professional,
        "profissional_cargo" => $professionalRole,
        "colaborador" => $collaboratorName,
        "colaborador_nome" => $collaboratorName,
        "colaborador_email" => $collaboratorEmail,
        "colaborador_cargo" => $collaboratorRole,
        "consultorio" =>
            $cl["display_name"] ?? "" ?:
            ($c["clinic"] ?? "" ?:
            $cl["legal_name"] ?? "Consultório"),
        "data" => date("d/m/Y"),
        "data_abreviada" => date("d/m/Y"),
        "data_extenso" => date_extenso_br(now()),
        "cidade" => city_state_label(
            $cl["address_city"] ?? "",
            $cl["address_state"] ?? "",
        ),
        "endereco" => (string) ($cl["address_line"] ?? ""),
        "agendamento_data" =>
            $apptStart !== "" ? date_extenso_br($apptStart) : "",
        "agendamento_hora" => document_time_br($apptStart),
        "agendamento_horario" => $scheduledRange,
        "horario_agendamento" => $scheduledRange,
        "agendamento_inicio" => $apptStart !== "" ? dt_br($apptStart) : "",
        "agendamento_fim" => $apptEnd !== "" ? dt_br($apptEnd) : "",
        "agendamento_profissional" => (string) ($appt["doctor_name"] ?? ""),
        "agendamento_procedimento" => (string) ($appt["procedure_title"] ?? ""),
        "agendamento_motivo" => (string) ($appt["reason"] ?? ""),
        "agendamento_observacoes" => (string) ($appt["notes"] ?? ""),
        "agendamento_status" => document_status_label(
            (string) ($appt["status"] ?? ""),
        ),
        "atendimento_inicio_real" =>
            $realStart !== "" ? document_time_br($realStart) : "",
        "horario_real_inicio_consulta" =>
            $realStart !== "" ? document_time_br($realStart) : "",
        "atendimento_fim_real" =>
            $realEnd !== "" ? document_time_br($realEnd) : "",
        "horario_real_fim_atendimento" =>
            $realEnd !== "" ? document_time_br($realEnd) : "",
        "atendimento_duracao_real" => $realDuration,
    ];
    $leadId = (int) ($ctx["lead_id"] ?? 0);
    if ($leadId > 0) {
        $l = one(
            "SELECT name,phone,source,interest,stage,next_action_at,notes FROM pi_leads WHERE id=? AND clinic_id=?",
            [$leadId, $cid],
        );
        if ($l) {
            $vars += [
                "interessado" => (string) ($l["name"] ?? ""),
                "interessado_telefone" => (string) ($l["phone"] ?? ""),
                "interessado_origem" => (string) ($l["source"] ?? ""),
                "interessado_interesse" => (string) ($l["interest"] ?? ""),
                "interessado_etapa" => (string) ($l["stage"] ?? ""),
                "interessado_proximo_contato" => !empty($l["next_action_at"])
                    ? dt_br((string) $l["next_action_at"])
                    : "",
                "interessado_observacoes" => (string) ($l["notes"] ?? ""),
            ];
        }
    }
    $procId = (int) ($ctx["procedure_id"] ?? 0);
    if ($procId > 0) {
        $pr = one(
            "SELECT title,category,description,duration_minutes,price_cents,payment_methods,pre_instructions,post_care FROM pi_procedures WHERE id=? AND clinic_id=?",
            [$procId, $cid],
        );
        if ($pr) {
            $vars += [
                "procedimento" => (string) ($pr["title"] ?? ""),
                "procedimento_categoria" => (string) ($pr["category"] ?? ""),
                "procedimento_descricao" => (string) ($pr["description"] ?? ""),
                "procedimento_duracao" => format_minutes(
                    (int) ($pr["duration_minutes"] ?? 0),
                ),
                "procedimento_valor" =>
                    (int) ($pr["price_cents"] ?? 0) > 0
                        ? money_br((int) $pr["price_cents"])
                        : "",
                "procedimento_formas_pagamento" =>
                    (string) ($pr["payment_methods"] ?? ""),
                "procedimento_orientacoes_pre" =>
                    (string) ($pr["pre_instructions"] ?? ""),
                "procedimento_cuidados_pos" =>
                    (string) ($pr["post_care"] ?? ""),
            ];
        }
    }
    $taskId = (int) ($ctx["task_id"] ?? 0);
    if ($taskId > 0) {
        $t = one(
            "SELECT t.title,t.status,t.due_at,td.description,u.name AS assigned_name FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id LEFT JOIN pi_users u ON u.id=COALESCE(t.assigned_to,t.target_user_id) WHERE t.id=? AND t.clinic_id=?",
            [$taskId, $cid],
        );
        if ($t) {
            $vars += [
                "tarefa" => (string) ($t["title"] ?? ""),
                "tarefa_status" => document_status_label(
                    (string) ($t["status"] ?? ""),
                ),
                "tarefa_prazo" => !empty($t["due_at"])
                    ? dt_br((string) $t["due_at"])
                    : "",
                "tarefa_responsavel" => (string) ($t["assigned_name"] ?? ""),
                "tarefa_descricao" => (string) ($t["description"] ?? ""),
            ];
        }
    }
    $careId = (int) ($ctx["care_id"] ?? 0);
    if ($careId > 0) {
        $a = one(
            "SELECT c.title,c.record_type,c.created_at,cc.content,p.full_name AS patient_name FROM pi_care c LEFT JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id JOIN pi_patients pl ON pl.id=c.patient_link_id AND pl.clinic_id=c.clinic_id JOIN pi_persons p ON p.id=pl.person_id WHERE c.id=? AND c.clinic_id=? AND c.deleted_at IS NULL",
            [$careId, $cid],
        );
        if ($a) {
            $vars += [
                "atividade" => (string) ($a["title"] ?? "" ?: "Atividade"),
                "atividade_tipo" => patient_record_type_label(
                    (string) ($a["record_type"] ?? ""),
                ),
                "atividade_data" => !empty($a["created_at"])
                    ? dt_br((string) $a["created_at"])
                    : "",
                "atividade_conteudo" => patient_summary_excerpt(
                    (string) ($a["content"] ?? ""),
                    800,
                ),
                "atividade_paciente" => (string) ($a["patient_name"] ?? ""),
            ];
        }
    }
    foreach (document_system_fields() as $key => $label) {
        if (!array_key_exists($key, $vars)) {
            $vars[$key] = "";
        }
    }
    return $vars;
}
function approved_document_template_options(array $c): array
{

    $cid = (int) ($c["clinic_id"] ?? 0);
    $uid = (int) ($c["user"]["id"] ?? 0);
    $role = (string) ($c["role"] ?? "");
    if ($cid <= 0) {
        return [];
    }
    $loader = function () use ($c, $cid): array {

        [$where, $params] = document_template_visible_where($c, "dt");
        $rows = q(
            "SELECT dt.id,dt.title,dt.type_key FROM pi_document_templates dt WHERE dt.clinic_id=? AND dt.status='approved' AND $where ORDER BY dt.title ASC, dt.id DESC",
            array_merge([$cid], $params),
        )->fetchAll();
        $types = document_type_options();
        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row["id"]] =
                (string) $row["title"] .
                " · " .
                ($types[(string) $row["type_key"]] ?? "Documento");
        }
        return $options;
    };
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "templates",
            server_json_cache_safe_key("approved_options", [$cid, $uid, $role]),
            server_json_cache_ttl("templates"),
            $loader,
            ["clinic:" . $cid, "user:" . $uid, "role:" . $role],
        );
    }
    return $loader();
}
function patient_document_timeline_items(
    array $c,
    int $patientId,
    int $limit = 40,
): array {

    $cid = (int) ($c["clinic_id"] ?? 0);
    $types = document_type_options();
    $limit = max(1, min(200, $limit));
    $rows = q(
        "SELECT d.id,d.title,d.type_key,d.issued_at,u.name AS issued_name FROM pi_documents d LEFT JOIN pi_users u ON u.id=d.issued_by WHERE d.clinic_id=? AND d.patient_link_id=? ORDER BY d.issued_at DESC,d.id DESC LIMIT $limit",
        [$cid, $patientId],
    )->fetchAll();
    $items = [];
    foreach ($rows as $d) {
        $docId = (int) $d["id"];
        $items[] = [
            "time" => dt_br($d["issued_at"]),
            "icon" => "description",
            "title" => $d["title"],
            "body" =>
                ($types[$d["type_key"]] ?? "Documento") .
                " emitido por " .
                ($d["issued_name"] ?? "colaborador"),
            "html" =>
                '<a class="ghost small" href="' .
                href("document_view", ["id" => $docId]) .
                '">Visualizar</a><a class="primary small" target="_blank" rel="noopener" href="' .
                href("document_print", ["id" => $docId]) .
                '">Imprimir</a>' .
                document_pdf_link($docId),
        ];
    }
    return $items;
}
function patient_summary_excerpt(string $text, int $limit = 220): string
{

    $text = str_ireplace(["<br>", "<br/>", "<br />"], "\n", $text);
    $plain = html_entity_decode(
        strip_tags($text),
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );
    $plain = mb_trim((string) preg_replace("/\s+/u", " ", $plain));
    if ($plain === "") {
        return "Sem conteúdo registrado.";
    }
    return mb_strlen($plain) > $limit
        ? mb_substr($plain, 0, $limit) . "…"
        : $plain;
}
function patient_summary_clinical_block(
    string $title,
    string $iconName,
    array $items,
    string $empty,
): string {

    $items = array_slice($items, 0, 3);
    $h =
        '<article class="patient-clinical-card"><header>' .
        icon($iconName) .
        "<h3>" .
        e($title) .
        "</h3></header>";
    if (!$items) {
        return $h . '<p class="empty-mini">' . e($empty) . "</p></article>";
    }
    $h .= '<div class="clinical-mini-list">';
    foreach ($items as $it) {
        $it = is_array($it) ? $it : [];
        $h .=
            '<div class="clinical-mini-item"><time>' .
            e((string) ($it["time"] ?? "")) .
            "</time><strong>" .
            e((string) ($it["title"] ?? $title)) .
            "</strong><p>" .
            e(patient_summary_excerpt((string) ($it["body"] ?? ""), 220)) .
            "</p></div>";
    }
    return $h . "</div></article>";
}
function document_can_access(array $c, array $doc): bool
{

    $cid = (int) ($c["clinic_id"] ?? 0);
    $uid = (int) ($c["user"]["id"] ?? 0);
    $role = (string) ($c["role"] ?? "");
    if (
        ($c["scope"] ?? "") !== "clinic" ||
        (int) ($doc["clinic_id"] ?? 0) !== $cid
    ) {
        return false;
    }
    if (!can("documents")) {
        return false;
    }
    $patientId = (int) ($doc["patient_link_id"] ?? 0);
    if ($patientId > 0 && !clinic_patient_exists($cid, $patientId, true)) {
        return false;
    }
    if ($role === "gerente") {
        return true;
    }
    if ($role === "medico") {
        return (int) ($doc["issued_by"] ?? 0) === $uid ||
            (int) ($doc["owner_user_id"] ?? 0) === $uid ||
            in_array(
                (string) ($doc["owner_role"] ?? ""),
                ["assistente", "recepcionista"],
                true,
            );
    }
    return (int) ($doc["issued_by"] ?? 0) === $uid ||
        (int) ($doc["owner_user_id"] ?? 0) === $uid;
}
function fetch_document_for_current_user(array $c, int $docId): ?array
{

    if ($docId <= 0) {
        return null;
    }
    $doc = one(
        "SELECT d.*,dt.owner_user_id,dt.owner_role,u.name AS issued_name,p.full_name AS patient_name,p.cpf AS patient_cpf,p.birth_date AS patient_birth_date FROM pi_documents d LEFT JOIN pi_document_templates dt ON dt.id=d.template_id AND dt.clinic_id=d.clinic_id LEFT JOIN pi_users u ON u.id=d.issued_by LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id AND pl.clinic_id=d.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE d.id=? AND d.clinic_id=? LIMIT 1",
        [$docId, (int) ($c["clinic_id"] ?? 0)],
    );
    return $doc && document_can_access($c, $doc) ? $doc : null;
}
function page_document_view(): void
{

    $c = need_login();
    if (($c["scope"] ?? "") !== "clinic") {
        throw new ProntooHttpError(
            403,
            "Documento disponível apenas no contexto do consultório.",
        );
    }
    $doc = fetch_document_for_current_user($c, (int) ($_GET["id"] ?? 0));
    if (!$doc) {
        http_response_code(404);
        page(
            "Documento",
            '<div class="empty">Documento não encontrado para esta credencial.</div>',
        );
        return;
    }
    $types = document_type_options();
    audit("documento_visualizado", "documento", (int) $doc["id"], [
        "titulo" => $doc["title"] ?? "",
        "document_type" => (string) ($doc["type_key"] ?? ""),
        "document_type_label" =>
            $types[(string) ($doc["type_key"] ?? "")] ?? "Documento",
        "patient_link_id" => (int) ($doc["patient_link_id"] ?? 0),
        "patient_name" => $doc["patient_name"] ?? "",
        "audit_body" => "",
    ]);
    $back =
        (int) ($doc["patient_link_id"] ?? 0) > 0
            ? href("patient", ["id" => (int) $doc["patient_link_id"]])
            : href("documents", ["doc" => (int) $doc["id"]]);
    $actions =
        '<a class="ghost small" href="' .
        $back .
        '">Voltar</a><a class="primary small" target="_blank" rel="noopener" href="' .
        href("document_print", ["id" => (int) $doc["id"]]) .
        '">' .
        icon("print") .
        " Imprimir</a>" .
        document_pdf_link((int) $doc["id"]);
    $body =
        page_head(
            "Documento emitido",
            "Visualização do conteúdo gerado a partir do modelo aprovado.",
            $actions,
        ) .
        '<section class="card doc-print-card"><div class="section-head"><div><h2>' .
        e($doc["title"] ?? "Documento") .
        "</h2><p>" .
        e(
            document_issue_meta_sentence(
                $doc["issued_name"] ?? null,
                $doc["patient_name"] ?? null,
                $doc["issued_at"],
                "emitido",
            ),
        ) .
        "</p></div></div>" .
        document_preview_page_html(
            document_body_to_html((string) $doc["content"]),
            $doc["document_identifier"] ?? null,
        ) .
        "</section>";
    page("Documento", $body);
}
function page_document_print(): void
{

    $c = need_login();
    if (($c["scope"] ?? "") !== "clinic") {
        throw new ProntooHttpError(
            403,
            "Documento disponível apenas no contexto do consultório.",
        );
    }
    $doc = fetch_document_for_current_user($c, (int) ($_GET["id"] ?? 0));
    if (!$doc) {
        http_response_code(404);
        echo '<!doctype html><meta charset="utf-8"><title>Documento não encontrado</title><p>Documento não encontrado.</p>';
        return;
    }
    $types = document_type_options();
    audit("documento_impresso", "documento", (int) $doc["id"], [
        "titulo" => $doc["title"] ?? "",
        "document_type" => (string) ($doc["type_key"] ?? ""),
        "document_type_label" =>
            $types[(string) ($doc["type_key"] ?? "")] ?? "Documento",
        "patient_link_id" => (int) ($doc["patient_link_id"] ?? 0),
        "patient_name" => $doc["patient_name"] ?? "",
        "audit_body" => "",
    ]);
    if (!headers_sent()) {
        header("Content-Type: text/html; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    $content = document_body_to_html((string) $doc["content"]);
    echo document_print_document_shell_html($doc, true, "print");
}
function document_field_buttons(): string
{

    $h =
        '<div class="doc-field-panel-head"><strong>' .
        icon("data_object") .
        '<span>Campos automáticos</span></strong><small>Clique para inserir no ponto do texto. O Prontoo substitui esses campos na pré-visualização.</small></div><div class="doc-field-groups" aria-label="Campos do sistema agrupados">';
    foreach (document_system_field_groups() as $groupKey => $group) {
        $h .=
            '<section class="doc-field-group doc-field-group-' .
            e((string) $groupKey) .
            '"><h4>' .
            icon((string) ($group["icon"] ?? "data_object")) .
            "<span>" .
            e((string) ($group["label"] ?? "Campos")) .
            '</span></h4><div class="doc-field-palette">';
        foreach ($group["fields"] ?? [] as $k => $label) {
            $token = "{{" . $k . "}}";
            $h .=
                '<button type="button" class="ghost small doc-field-token-button" data-doc-field="' .
                e($token) .
                '" title="Inserir ' .
                e($token) .
                '"><span>' .
                e($label) .
                "</span><small>" .
                e($token) .
                "</small></button>";
        }
        $h .= "</div></section>";
    }
    return $h . "</div>";
}
function document_editor_button(
    string $cmd,
    string $iconName,
    string $label,
    string $value = "",
): string {

    $valueAttr = $value !== "" ? ' data-doc-value="' . e($value) . '"' : "";
    return '<button type="button" class="ghost small doc-tool" data-doc-cmd="' .
        e($cmd) .
        '"' .
        $valueAttr .
        ' title="' .
        e($label) .
        '" aria-label="' .
        e($label) .
        '">' .
        icon($iconName) .
        "<span>" .
        e($label) .
        "</span></button>";
}
function document_editor_html(
    string $name,
    string $value = "",
    string $id = "",
): string {

    $id = $id !== "" ? $id : "doced_" . bin2hex(random_bytes(3));
    $html = document_body_to_html($value);
    $toolbar =
        '<div class="doc-editor-toolbar doc-google-toolbar" role="toolbar" aria-label="Formatação do modelo"><div class="doc-toolbar-group doc-toolbar-history">' .
        document_editor_button("undo", "undo", "Desfazer") .
        document_editor_button("redo", "redo", "Refazer") .
        '</div><div class="doc-toolbar-group doc-toolbar-style">' .
        document_editor_button("formatBlock", "title", "Título", "H2") .
        document_editor_button("formatBlock", "subject", "Subtítulo", "H3") .
        document_editor_button("formatBlock", "notes", "Texto", "P") .
        '</div><div class="doc-toolbar-group doc-toolbar-format">' .
        document_editor_button("bold", "format_bold", "Negrito") .
        document_editor_button("italic", "format_italic", "Itálico") .
        document_editor_button("underline", "format_underlined", "Sublinhar") .
        document_editor_button("removeFormat", "format_clear", "Limpar") .
        '</div><div class="doc-toolbar-group doc-toolbar-list">' .
        document_editor_button(
            "insertUnorderedList",
            "format_list_bulleted",
            "Lista",
        ) .
        document_editor_button(
            "insertOrderedList",
            "format_list_numbered",
            "Numeração",
        ) .
        document_editor_button(
            "outdent",
            "format_indent_decrease",
            "Recuo menor",
        ) .
        document_editor_button(
            "indent",
            "format_indent_increase",
            "Recuo maior",
        ) .
        '</div><div class="doc-toolbar-group doc-toolbar-align">' .
        document_editor_button("justifyLeft", "format_align_left", "Esquerda") .
        document_editor_button(
            "justifyCenter",
            "format_align_center",
            "Centralizar",
        ) .
        document_editor_button(
            "justifyRight",
            "format_align_right",
            "Direita",
        ) .
        document_editor_button(
            "justifyFull",
            "format_align_justify",
            "Justificar",
        ) .
        "</div></div>";
    return '<div class="doc-editor-wrap doc-google-editor" data-doc-editor-wrap><div class="doc-editor-titlebar"><div><strong>' .
        icon("edit_note") .
        '<span>Editor do modelo</span></strong><small>Escreva o texto-base, formate a leitura e insira campos automáticos quando precisar.</small></div><span class="doc-editor-mini" data-doc-word-count>0 palavras</span></div>' .
        $toolbar .
        '<div class="doc-editor-main"><div class="doc-editor-paper"><div class="doc-editor" contenteditable="true" data-doc-editor aria-label="Conteúdo do modelo" spellcheck="true">' .
        $html .
        '</div><textarea name="' .
        e($name) .
        '" id="' .
        e($id) .
        '" data-doc-editor-input hidden>' .
        e($html) .
        '</textarea></div><aside class="doc-field-panel">' .
        document_field_buttons() .
        '</aside></div><div class="doc-editor-footnote">' .
        icon("verified_user") .
        "<span>Campos como <b>{{paciente}}</b> e <b>{{data_extenso}}</b> serão preenchidos automaticamente antes da emissão.</span></div></div>";
}
function document_stage_actions(string $stage): string
{

    $emit = href("documents", ["emit" => 1]);
    $models = href("documents", ["models" => 1]);
    $newModel = href("documents", ["new_model" => 1]);
    if ($stage === "models") {
        return '<span class="doc-page-actions"><a class="primary small" href="' .
            $newModel .
            '">' .
            icon("add_circle") .
            "<span>Novo modelo</span></a></span>";
    }
    if ($stage === "new_model" || $stage === "edit_model") {
        return '<span class="doc-page-actions"><a class="ghost small" href="' .
            $models .
            '">' .
            icon("arrow_back") .
            "<span>Modelos</span></a></span>";
    }
    return '<span class="doc-page-actions"><a class="primary small" href="' .
        $emit .
        '">' .
        icon("post_add") .
        "<span>Criar documento</span></a></span>";
}
function document_stage_strip(
    string $stage,
    int $approved,
    int $pending,
    int $visibleDocs,
): string {

    $items = [
        [
            "recent",
            "description",
            "Emitidos",
            "Consultar documentos confirmados, imprimir ou abrir o PDF.",
            href("documents", ["recent" => 1]),
            $visibleDocs,
        ],
        [
            "emit",
            "post_add",
            "Criar documento",
            "Escolher modelo aprovado e gerar pré-visualização segura.",
            href("documents", ["emit" => 1]),
            $approved,
        ],
        [
            "models",
            "edit_note",
            "Modelos",
            "Criar, alterar e aprovar textos usados pela equipe.",
            href("documents", ["models" => 1]),
            $pending,
        ],
    ];
    $out = '<nav class="doc-stage-strip" aria-label="Fluxo de documentos">';
    foreach ($items as [$key, $ic, $title, $desc, $url, $count]) {
        $active =
            $stage === $key ||
            ($stage === "new_model" && $key === "models") ||
            ($stage === "doc" && $key === "recent");
        $out .=
            '<a class="doc-stage-item' .
            ($active ? " is-active" : "") .
            '" href="' .
            $url .
            '"><span class="doc-stage-icon">' .
            icon($ic) .
            '</span><span class="doc-stage-copy"><strong>' .
            e($title) .
            "</strong><small>" .
            e($desc) .
            "</small></span><b>" .
            n($count) .
            "</b></a>";
    }
    return $out . "</nav>";
}
function document_overview_cards(
    int $approved,
    int $pending,
    int $visibleDocs,
    string $docSearchMode,
): string {

    return '<section class="doc-overview-cards kpis" aria-label="Resumo de documentos">' .
        document_ds_metric(
            "Modelos aprovados",
            $approved,
            "verified",
            "Disponíveis para criar documentos",
            "is-ok",
        ) .
        document_ds_metric(
            "Aguardando aprovação",
            $pending,
            "pending_actions",
            "Modelos em revisão",
            "is-warn",
        ) .
        document_ds_metric(
            "Emitidos na lista",
            $visibleDocs,
            "description",
            $docSearchMode,
            "is-info",
        ) .
        "</section>";
}
function document_template_author_form(
    array $typeOptions,
    string $selectedType = "declaracao",
    string $title = "",
    string $body = "",
    int $id = 0,
    string $submitLabel = "Salvar modelo",
    string $cancelHref = "",
): string {

    if (!$typeOptions) {
        $typeOptions = document_type_options();
    }
    if (!isset($typeOptions[$selectedType])) {
        $selectedType =
            (string) (array_key_first($typeOptions) ?: "declaracao");
    }
    $editorId = $id > 0 ? "doc_body_" . $id : "doc_body_new";
    $idField =
        $id > 0
            ? '<input type="hidden" name="id" value="' . (int) $id . '">'
            : "";
    $mode = $id > 0 ? "Edição do modelo" : "Novo modelo";
    $hint =
        $id > 0
            ? "Ajuste o texto-base, revise os campos automáticos e salve uma nova versão para uso da equipe."
            : "Monte um texto-base reutilizável. Depois o Prontoo gera uma pré-visualização antes da emissão.";
    $steps =
        '<div class="doc-template-author-steps" aria-label="Etapas da edição do modelo"><span><b>1</b>Identifique</span><span><b>2</b>Escreva</span><span><b>3</b>Revise</span></div>';
    $guide =
        '<aside class="doc-template-author-guide"><strong>' .
        icon("lightbulb") .
        "<span>Como escrever melhor</span></strong><ul><li>Use títulos curtos para separar seções.</li><li>Prefira frases objetivas para leitura na consulta.</li><li>Insira campos automáticos em vez de digitar dados do paciente.</li><li>Confira a pré-visualização antes da emissão definitiva.</li></ul></aside>";
    $identity =
        '<fieldset class="doc-template-author-section doc-template-identity"><legend>' .
        icon("badge") .
        '<span>Identificação</span></legend><div class="doc-template-fields-grid">' .
        select_label(
            "Tipo de documento",
            "type_key",
            $typeOptions,
            $selectedType,
            "required",
        ) .
        form_row(
            "Nome do modelo",
            input(
                "title",
                "text",
                $title,
                'required maxlength="160" placeholder="Ex.: Receita simples, declaração de comparecimento" autocomplete="off"',
            ),
        ) .
        '</div><p class="doc-template-section-help">O nome deve ajudar a equipe a escolher o modelo certo sem abrir o conteúdo.</p></fieldset>';
    $editor =
        '<fieldset class="doc-template-author-section doc-template-editor-section"><legend>' .
        icon("article") .
        '<span>Texto do modelo</span></legend><div class="doc-template-editor-field"><span>Conteúdo e formatação</span>' .
        document_editor_html("body", $body, $editorId) .
        "</div></fieldset>";
    $actions =
        $cancelHref !== ""
            ? '<div class="form-actions"><a class="ghost" href="' .
                e($cancelHref) .
                '">' .
                icon("close") .
                '<span>Cancelar</span></a><button type="submit" class="primary">' .
                icon(form_submit_icon($submitLabel)) .
                "<span>" .
                e($submitLabel) .
                "</span></button></div>"
            : form_actions($submitLabel);
    return '<form method="post" class="compact doc-form doc-template-author-form" data-doc-template-form>' .
        csrf_field() .
        '<input type="hidden" name="act" value="save_template">' .
        $idField .
        '<div class="doc-template-author-head"><div><span class="eyebrow">' .
        icon("description") .
        "<span>" .
        e($mode) .
        "</span></span><h3>" .
        e($mode) .
        "</h3><p>" .
        e($hint) .
        "</p></div>" .
        $guide .
        "</div>" .
        $steps .
        '<div class="doc-template-author-layout">' .
        $identity .
        $editor .
        "</div>" .
        $actions .
        "</form>";
}
function document_model_search_card(
    string $modelSearch,
    string $mode = "emit",
): string {

    $mode = $mode === "models" ? "models" : "emit";
    $hidden =
        $mode === "models"
            ? '<input type="hidden" name="models" value="1">'
            : '<input type="hidden" name="emit" value="1">';
    $label =
        $mode === "models"
            ? "Buscar modelos"
            : "Buscar modelo para criar documento";
    $placeholder =
        $mode === "models"
            ? "Nome, tipo, cargo, responsável ou status"
            : "Nome do modelo, tipo de documento ou cargo";
    $clear = href("documents", [$mode === "models" ? "models" : "emit" => 1]);
    return '<section class="card doc-search-primary doc-ds-search ds-search-card patient-search-card doc-model-search-card"><form method="get" class="patient-search-bar doc-model-search" role="search"><input type="hidden" name="r" value="documents">' .
        $hidden .
        '<label class="search-field"><input type="search" name="model_q" value="' .
        e($modelSearch) .
        '" placeholder="' .
        e($placeholder) .
        '" aria-label="' .
        e($label) .
        '"></label><button class="primary small" type="submit">' .
        icon("search") .
        "<span>Busca rápida</span></button>" .
        ($modelSearch !== ""
            ? '<a class="ghost small" href="' .
                $clear .
                '">' .
                icon("close") .
                "<span>Limpar</span></a>"
            : "") .
        "</form></section>";
}
function page_documents(): void
{

    $c = require_can("documents");
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    $role = (string) $c["role"];
    $typeOptions = document_type_options();
    $typeOptionsForRole = document_type_options_for_role($role);
    $patientSuggest = patient_autosuggest_datalist($cid);
    $roleCfg = document_role_config($role, $cid);
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "");
        if ($act === "save_template") {
            $id = (int) ($_POST["id"] ?? 0);
            $title = mb_trim((string) ($_POST["title"] ?? ""));
            $body = document_body_to_html((string) ($_POST["body"] ?? ""));
            $type = (string) ($_POST["type_key"] ?? "declaracao");
            if (!isset($typeOptions[$type])) {
                $type = "declaracao";
            }
            if (
                $role !== "gerente" &&
                !in_array($type, document_allowed_types_for_role($role), true)
            ) {
                flash(
                    "Este tipo de documento não pertence ao cargo selecionado.",
                    "bad",
                );
                redirect("documents");
            }
            if ($title === "" || document_body_is_empty($body)) {
                flash("Informe título e conteúdo do modelo.", "bad");
                redirect("documents");
            }
            if ($id > 0) {
                $tpl = one(
                    "SELECT id,owner_user_id,owner_role FROM pi_document_templates WHERE id=? AND clinic_id=?",
                    [$id, $cid],
                );
                if (!$tpl || !can_edit_document_template($c, $tpl)) {
                    flash("Modelo não disponível para alteração.", "bad");
                    redirect("documents");
                }
                if (
                    $role !== "gerente" &&
                    (string) $tpl["owner_role"] !== $role
                ) {
                    flash(
                        "Este cargo não altera modelos de outro fluxo.",
                        "bad",
                    );
                    redirect("documents");
                }
                [
                    $status,
                    $approvalRequired,
                    $approvedBy,
                    $approvedAt,
                ] = document_template_status_after_save(
                    $c,
                    (string) $tpl["owner_role"],
                    true,
                );
                q(
                    "UPDATE pi_document_templates SET type_key=?,title=?,body=?,status=?,approval_required=?,approved_by=?,approved_at=?,updated_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [
                        $type,
                        $title,
                        $body,
                        $status,
                        $approvalRequired,
                        $approvedBy,
                        $approvedAt,
                        $uid,
                        $id,
                        $cid,
                    ],
                );
                audit("modelo_documento_atualizado", "documento", $id, [
                    "titulo" => $title,
                    "template_title" => $title,
                    "status" => document_status_label($status),
                    "audit_body" => "",
                ]);
                flash(
                    $status === "pending_approval"
                        ? "Modelo salvo e enviado para aprovação da Gestão."
                        : "Modelo salvo e disponível para criação.",
                );
            } else {
                [
                    $status,
                    $approvalRequired,
                    $approvedBy,
                    $approvedAt,
                ] = document_template_status_after_save($c, $role, false);
                q(
                    "INSERT INTO pi_document_templates (clinic_id,owner_user_id,owner_role,type_key,title,body,status,approval_required,approved_by,approved_at,updated_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
                    [
                        $cid,
                        $uid,
                        $role,
                        $type,
                        $title,
                        $body,
                        $status,
                        $approvalRequired,
                        $approvedBy,
                        $approvedAt,
                        $uid,
                    ],
                );
                $newId = db_last_insert_id();
                audit("modelo_documento_criado", "documento", $newId, [
                    "titulo" => $title,
                    "template_title" => $title,
                    "status" => document_status_label($status),
                    "audit_body" => "",
                ]);
                flash(
                    $status === "pending_approval"
                        ? "Modelo criado e enviado para aprovação da Gestão."
                        : "Modelo criado e disponível para criação.",
                );
            }
            redirect("documents");
        }
        if ($act === "approve_template" || $act === "reject_template") {
            if ($role !== "gerente") {
                flash("Apenas a Gestão pode aprovar modelos.", "bad");
                redirect("documents");
            }
            $id = (int) ($_POST["id"] ?? 0);
            $tpl = one(
                "SELECT id,title FROM pi_document_templates WHERE id=? AND clinic_id=?",
                [$id, $cid],
            );
            if (!$tpl) {
                flash("Modelo não encontrado.", "bad");
                redirect("documents");
            }
            $status = $act === "approve_template" ? "approved" : "rejected";
            q(
                "UPDATE pi_document_templates SET status=?,approval_required=0,approved_by=?,approved_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$status, $uid, $uid, $id, $cid],
            );
            audit(
                $act === "approve_template"
                    ? "modelo_documento_aprovado"
                    : "modelo_documento_rejeitado",
                "documento",
                $id,
                [
                    "titulo" => $tpl["title"] ?? "",
                    "template_title" => $tpl["title"] ?? "",
                    "audit_body" => "",
                ],
            );
            flash(
                $act === "approve_template"
                    ? "Modelo aprovado."
                    : "Modelo rejeitado.",
            );
            redirect("documents");
        }
        if ($act === "create_document") {
            try {
                $prefillPatientId = resolve_patient_lookup_id(
                    $cid,
                    (int) ($_POST["patient_link_id"] ?? 0),
                    posted_patient_search_value(),
                );
                $prefillAppointmentId = (int) ($_POST["appointment_id"] ?? 0);
                $docContext = document_context_ids_from_post($c);
                $docId = create_document_draft_from_template(
                    $c,
                    (int) ($_POST["template_id"] ?? 0),
                    $prefillPatientId,
                    $prefillAppointmentId,
                    $docContext,
                );
                flash(
                    $prefillPatientId > 0
                        ? "Pré-visualização criada para o paciente selecionado. Confira antes de emitir."
                        : "Pré-visualização criada. Confira o modelo e preencha os vínculos necessários, se houver.",
                );
                redirect("documents", ["doc" => $docId]);
            } catch (Throwable $e) {
                flash(
                    app_public_error_message(
                        $e,
                        "Não foi possível concluir a operação com o documento.",
                    ),
                    "bad",
                );
                redirect("documents");
            }
        }
        if ($act === "save_document") {
            $docId = (int) ($_POST["doc_id"] ?? 0);
            $next = (string) ($_POST["next_status"] ?? "preparado");
            try {
                if ($next === "descartar") {
                    discard_document_draft($c, $docId);
                    flash("Pré-visualização descartada.");
                    redirect("documents");
                }
                $patientId = resolve_patient_lookup_id(
                    $cid,
                    (int) ($_POST["patient_link_id"] ?? 0),
                    posted_patient_search_value(),
                );
                $appointmentId = (int) ($_POST["appointment_id"] ?? 0);
                $docContext = document_context_ids_from_post($c);
                save_document_draft(
                    $c,
                    $docId,
                    "",
                    $patientId,
                    "",
                    "preparado",
                    $appointmentId,
                    $docContext,
                );
                flash(
                    "Pré-visualização atualizada com os vínculos informados. Confira antes de emitir.",
                );
                redirect("documents", ["doc" => $docId]);
            } catch (Throwable $e) {
                flash(
                    app_public_error_message(
                        $e,
                        "Não foi possível concluir a operação com o documento.",
                    ),
                    "bad",
                );
                redirect("documents", ["doc" => $docId]);
            }
        }
        if ($act === "confirm_document") {
            $docId = (int) ($_POST["doc_id"] ?? 0);
            try {
                confirm_document_issue($c, $docId);
                flash("Documento confirmado e emitido.");
                redirect("documents", ["doc" => $docId]);
            } catch (Throwable $e) {
                flash(
                    app_public_error_message(
                        $e,
                        "Não foi possível concluir a operação com o documento.",
                    ),
                    "bad",
                );
                redirect("documents", ["doc" => $docId]);
            }
        }
    }
    [$where, $params] = document_template_visible_where($c, "dt");
    $templates = q(
        "SELECT dt.id,dt.owner_user_id,dt.owner_role,dt.type_key,dt.title,dt.status,u.name AS owner_name,(SELECT MAX(d.issued_at) FROM pi_documents d WHERE d.clinic_id=dt.clinic_id AND d.template_id=dt.id) AS last_used_at FROM pi_document_templates dt LEFT JOIN pi_users u ON u.id=dt.owner_user_id WHERE dt.clinic_id=? AND $where ORDER BY FIELD(dt.status,'pending_approval','approved','rejected','archived'), IF(dt.owner_role=?,0,1), COALESCE(last_used_at,dt.updated_at,dt.created_at) DESC, dt.title ASC LIMIT 240",
        array_merge([$cid], $params, [$role]),
    )->fetchAll();
    $modelSearch = mb_trim((string) ($_GET["model_q"] ?? ""));
    $createTemplates = [];
    $pending = 0;
    $approved = 0;
    foreach ($templates as $t) {
        $status = (string) ($t["status"] ?? "");
        if ($status === "pending_approval") {
            $pending++;
        }
        if ($status === "approved") {
            $approved++;
        }
        if ($status === "approved" && document_can_issue_template($c, $t)) {
            if ($modelSearch !== "") {
                $hay = mb_strtolower(
                    ($t["title"] ?? "") .
                        " " .
                        ($typeOptions[$t["type_key"]] ?? "") .
                        " " .
                        role_label_for((string) $t["owner_role"], $cid),
                );
                if (!str_contains($hay, mb_strtolower($modelSearch))) {
                    continue;
                }
            }
            $createTemplates[] = $t;
        }
    }
    usort($createTemplates, function ($a, $b) use ($role) {

        $la = app_storage_timestamp($a["last_used_at"] ?? "") ?: 0;
        $lb = app_storage_timestamp($b["last_used_at"] ?? "") ?: 0;
        $aa = (string) $a["owner_role"] === $role ? 0 : 1;
        $bb = (string) $b["owner_role"] === $role ? 0 : 1;
        return $lb <=> $la ?:
            ($aa <=> $bb ?:
                strcmp((string) $a["title"], (string) $b["title"]));
    });
    if ($modelSearch === "") {
        $createTemplates = array_slice($createTemplates, 0, 5);
    }
    $templatesForModels = $templates;
    if ($modelSearch !== "") {
        $qModel = mb_strtolower($modelSearch);
        $templatesForModels = array_values(
            array_filter($templatesForModels, function ($tpl) use (
                $typeOptions,
                $cid,
                $qModel,
            ) {

                $hay = mb_strtolower(
                    ($tpl["title"] ?? "") .
                        " " .
                        ($typeOptions[$tpl["type_key"]] ?? "") .
                        " " .
                        role_label_for(
                            (string) ($tpl["owner_role"] ?? ""),
                            $cid,
                        ) .
                        " " .
                        ($tpl["owner_name"] ?? "") .
                        " " .
                        document_status_label((string) ($tpl["status"] ?? "")),
                );
                return str_contains($hay, $qModel);
            }),
        );
    }
    $docWhere = "d.clinic_id=?";
    $docParams = [$cid];
    if ($role !== "gerente") {
        if ($role === "medico") {
            $docWhere .=
                " AND (d.issued_by=? OR dt.owner_user_id=? OR dt.owner_role IN ('medico','assistente','recepcionista'))";
            $docParams[] = $uid;
            $docParams[] = $uid;
        } else {
            $docWhere .=
                " AND (d.issued_by=? OR dt.owner_user_id=? OR dt.owner_role=?)";
            $docParams[] = $uid;
            $docParams[] = $uid;
            $docParams[] = $role;
        }
    }
    $docSearch = mb_trim((string) ($_GET["doc_q"] ?? ($_GET["q"] ?? "")));
    $docListWhere = $docWhere . " AND d.document_status='emitido'";
    $docListParams = $docParams;
    if ($docSearch !== "") {
        $like = "%" . $docSearch . "%";
        $code =
            "%" .
            strtoupper(preg_replace("/[^A-Za-z0-9]+/", "", $docSearch)) .
            "%";
        $docListWhere .=
            " AND (d.title LIKE ? OR d.content LIKE ? OR d.type_key LIKE ? OR p.full_name LIKE ? OR u.name LIKE ? OR UPPER(COALESCE(d.document_identifier,'')) LIKE ?)";
        array_push($docListParams, $like, $like, $like, $like, $like, $code);
    }
    $docLimit = $docSearch === "" ? 10 : 100;
    $docs = q(
        "SELECT d.id,d.title,d.type_key,d.issued_at,d.updated_at,d.confirmed_at,d.document_status,d.patient_link_id,d.appointment_id,d.document_identifier,u.name AS issued_name,p.full_name AS patient_name,dt.owner_role FROM pi_documents d LEFT JOIN pi_document_templates dt ON dt.id=d.template_id AND dt.clinic_id=d.clinic_id LEFT JOIN pi_users u ON u.id=d.issued_by LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id AND pl.clinic_id=d.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE $docListWhere ORDER BY COALESCE(d.issued_at,d.confirmed_at,d.updated_at) DESC,d.id DESC LIMIT " .
            (int) $docLimit,
        $docListParams,
    )->fetchAll();
    $selectedDoc = null;
    $selectedId = (int) ($_GET["doc"] ?? 0);
    if ($selectedId > 0) {
        $selectedDoc = fetch_document_for_current_user($c, $selectedId);
    }
    $defaultType = array_key_first($typeOptionsForRole) ?: "declaracao";
    $defaultBody =
        "<p><strong>{{consultorio}}</strong></p><p>{{data_extenso}}</p><p>Paciente: {{paciente}}<br>Agendamento: {{agendamento_data}} às {{agendamento_hora}}<br>Profissional: {{agendamento_profissional}}</p><p>Digite aqui o conteúdo do modelo pré-aprovado.</p>";
    $createForm = document_template_author_form(
        $typeOptionsForRole ?: $typeOptions,
        $defaultType,
        "",
        $defaultBody,
        0,
        "Salvar modelo",
        href("documents", ["models" => 1]),
    );
    $modelsOpen = (string) ($_GET["models"] ?? "") === "1";
    $newModelOpen = (string) ($_GET["new_model"] ?? "") === "1";
    $editModelId = (int) ($_GET["edit_model"] ?? 0);
    $recentOpen = (string) ($_GET["recent"] ?? "") === "1" || $docSearch !== "";
    $emitOpen = (string) ($_GET["emit"] ?? "") === "1";
    if (
        !$selectedDoc &&
        !$modelsOpen &&
        !$newModelOpen &&
        !$emitOpen &&
        $editModelId <= 0
    ) {
        $recentOpen = true;
    }
    $docSearchMode = $docSearch !== "" ? "Busca ativa" : "Últimos emitidos";
    $docVisibleCount = count($docs);
    $docHeroIntro = $roleCfg["subtitle"] ?? "Documentos do cuidado organizado.";
    $docStage = $selectedDoc
        ? "doc"
        : ($editModelId > 0
            ? "edit_model"
            : ($newModelOpen
                ? "new_model"
                : ($modelsOpen
                    ? "models"
                    : ($emitOpen
                        ? "emit"
                        : "recent"))));
    $html = page_head(
        $roleCfg["title"],
        $roleCfg["subtitle"],
        document_stage_actions($docStage),
    );
    $html .= document_overview_cards(
        $approved,
        $pending,
        $docVisibleCount,
        $docSearchMode,
    );
    if ($selectedDoc) {
        $st = (string) ($selectedDoc["document_status"] ?? "emitido");
        $editable =
            document_editable_status($st) &&
            ((int) ($selectedDoc["issued_by"] ?? 0) === $uid ||
                $role === "gerente");
        $statusBadge =
            '<span class="task-chip ' .
            document_status_class($st) .
            '">' .
            e(document_status_label($st)) .
            "</span>";
        $preview = document_preview_page_html(
            document_body_to_html((string) $selectedDoc["content"]),
            ($st ?? "") === "emitido"
                ? $selectedDoc["document_identifier"] ?? null
                : null,
        );
        if ($editable) {
            $bindingForm =
                '<form method="post" class="compact doc-draft-form doc-preview-bindings">' .
                csrf_field() .
                '<input type="hidden" name="act" value="save_document"><input type="hidden" name="doc_id" value="' .
                (int) $selectedDoc["id"] .
                '">' .
                document_context_binding_fields($c, $selectedDoc) .
                '<small class="field-help">O conteúdo vem exclusivamente do modelo pré-aprovado. Antes de emitir, é permitido preencher paciente, agendamento e demais registros contextuais usados pelos campos do sistema.</small><div class="form-actions"><button type="submit" name="next_status" value="descartar" class="ghost danger-soft" formnovalidate onclick="return confirm(&quot;Descartar esta pré-visualização de documento?&quot;)">' .
                icon("delete") .
                '<span>Descartar</span></button><button type="submit" name="next_status" value="preparado" class="primary">' .
                icon("sync") .
                "<span>Preencher agora</span></button></div></form>";
            $confirm =
                $st === "preparado"
                    ? '<form method="post" class="inline doc-confirm-form" onsubmit="return confirm(&quot;Confirmar emissão definitiva deste documento? Depois disso ele não será editável.&quot;)">' .
                        csrf_field() .
                        '<input type="hidden" name="act" value="confirm_document"><input type="hidden" name="doc_id" value="' .
                        (int) $selectedDoc["id"] .
                        '"><button type="submit" class="primary">' .
                        icon("verified") .
                        "<span>Confirmar emissão</span></button></form>"
                    : '<div class="empty compact-empty">Atualize os vínculos para liberar a confirmação de emissão.</div>';
            $html .=
                '<section class="card doc-draft-card"><div class="section-head"><div><h2>' .
                e($selectedDoc["title"]) .
                "</h2><p>" .
                ($selectedDoc["patient_name"]
                    ? "Paciente: " . e($selectedDoc["patient_name"]) . " · "
                    : "") .
                "Status: " .
                $statusBadge .
                '</p></div><a class="ghost small" href="' .
                href("documents", ["recent" => 1]) .
                '">' .
                icon("close") .
                '<span>Fechar</span></a></div><div class="doc-draft-layout doc-preview-only"><aside class="doc-preview-step doc-preview-step-bindings"><h3>Vínculos permitidos</h3><p class="muted-copy">Preencha primeiro os vínculos que serão usados pelo modelo. Depois confira a pré-visualização gerada.</p>' .
                $bindingForm .
                '</aside><div class="doc-preview-step doc-preview-step-preview"><h3>Pré-visualização</h3><p class="muted-copy">Visualizou e conferiu o documento? Confirme a emissão. Nenhuma edição livre do conteúdo é permitida nesta etapa.</p>' .
                $preview .
                $confirm .
                "</div></div></section>";
        } else {
            $actions =
                '<a class="ghost small" href="' .
                href("documents", ["recent" => 1]) .
                '">' .
                icon("arrow_back") .
                "<span>Voltar</span></a>" .
                ($st === "emitido"
                    ? '<a class="primary small" target="_blank" rel="noopener" href="' .
                        href("document_print", [
                            "id" => (int) $selectedDoc["id"],
                        ]) .
                        '">' .
                        icon("print") .
                        "<span>Imprimir</span></a>" .
                        document_pdf_link((int) $selectedDoc["id"])
                    : "");
            $code =
                document_identifier_display(
                    $selectedDoc["document_identifier"] ?? "",
                ) !== ""
                    ? '<span class="doc-id-inline"><span>' .
                        e(
                            document_identifier_display(
                                $selectedDoc["document_identifier"] ?? "",
                            ),
                        ) .
                        "</span></span>"
                    : "";
            $html .=
                '<section class="card doc-print-card"><div class="section-head doc-issued-head"><div><h2>' .
                e($selectedDoc["title"]) .
                "</h2><p>" .
                e(
                    document_issue_meta_sentence(
                        $selectedDoc["issued_name"] ?? null,
                        $selectedDoc["patient_name"] ?? null,
                        $selectedDoc["issued_at"],
                        $st,
                    ),
                ) .
                "</p></div>" .
                ($code !== ""
                    ? '<div class="doc-issued-code">' . $code . "</div>"
                    : "") .
                $actions .
                "</div>" .
                $preview .
                "</section>";
        }
        $html .= $patientSuggest;
        page("Documentos", $html);
        return;
    }
    if ($newModelOpen) {
        $html .=
            '<section class="card doc-template-new-card"><div class="section-head"><div><h2>Novo modelo</h2><p>Defina um texto base para emissão segura. Depois ele poderá ser aprovado ou usado conforme o cargo.</p></div><a class="ghost small" href="' .
            href("documents", ["recent" => 1]) .
            '">' .
            icon("arrow_back") .
            "<span>Voltar</span></a></div>" .
            $createForm .
            "</section>";
        page("Documentos", $html);
        return;
    }
    if ($editModelId > 0) {
        $editTpl = one(
            "SELECT dt.id,dt.owner_user_id,dt.owner_role,dt.type_key,dt.title,dt.body,dt.status FROM pi_document_templates dt WHERE dt.id=? AND dt.clinic_id=? AND $where LIMIT 1",
            array_merge([$editModelId, $cid], $params),
        );
        if (!$editTpl || !can_edit_document_template($c, $editTpl)) {
            flash("Modelo não disponível para alteração.", "bad");
            redirect("documents", ["models" => 1]);
        }
        $editTypeOptions =
            $role === "gerente"
                ? document_type_options_for_role(
                    (string) $editTpl["owner_role"],
                )
                : $typeOptionsForRole;
        if (!$editTypeOptions) {
            $editTypeOptions = $typeOptions;
        }
        $editForm = document_template_author_form(
            $editTypeOptions,
            (string) $editTpl["type_key"],
            (string) $editTpl["title"],
            (string) $editTpl["body"],
            (int) $editTpl["id"],
            "Salvar alteração",
            href("documents", ["models" => 1]),
        );
        $html .=
            '<section class="card doc-template-new-card doc-template-edit-route-card"><div class="section-head"><div><span class="eyebrow">' .
            icon("edit_note") .
            '<span>Modelo</span></span><h2>Editar modelo</h2><p>Revise o texto-base em uma tela própria, sem abrir formulário dentro da lista de modelos.</p></div><a class="ghost small" href="' .
            href("documents", ["models" => 1]) .
            '">' .
            icon("arrow_back") .
            "<span>Modelos</span></a></div>" .
            $editForm .
            "</section>";
        page("Documentos", $html);
        return;
    }
    $modelRows = "";
    foreach ($createTemplates as $tpl) {
        $modelRows .= document_ds_model_row($tpl, $typeOptions, $cid);
    }
    if ($modelRows === "") {
        $modelRows =
            '<div class="empty compact-empty">Nenhum modelo disponível para este cargo.</div>';
    }
    if ($emitOpen) {
        $html .= document_model_search_card($modelSearch, "emit");
        $modelFoundChip =
            $modelSearch !== ""
                ? '<nav class="patient-filter-chips doc-found-filters ds-selection-chips" aria-label="Resultado da busca de modelos"><span class="patient-filter-chip ds-filter-chip active is-active patient-filter-found" aria-current="page" data-ds-filter-chip>' .
                    icon("manage_search") .
                    "<span>Encontrados</span><small>" .
                    number_format(count($createTemplates), 0, ",", ".") .
                    "</small></span></nav>"
                : "";
        $html .=
            '<section class="card doc-create-card doc-ds-card ds-filter-list-block"><div class="section-head doc-ds-section-head"><div><span class="eyebrow">' .
            icon("post_add") .
            '<span>Criação segura</span></span><h2>Criar documento</h2><p>Escolha um modelo aprovado. O Prontoo gera uma pré-visualização antes da emissão definitiva.</p></div><a class="ghost small" href="' .
            href("documents", ["recent" => 1]) .
            '">' .
            icon("close") .
            "<span>Fechar</span></a></div>" .
            $modelFoundChip .
            '<div class="doc-history-list doc-model-list doc-ds-list">' .
            $modelRows .
            "</div></section>";
        page("Documentos", $html);
        return;
    }
    if ($modelsOpen) {
        $html .= document_model_search_card($modelSearch, "models");
        $modelFoundChip =
            $modelSearch !== ""
                ? '<nav class="patient-filter-chips doc-found-filters ds-selection-chips" aria-label="Resultado da busca de modelos"><span class="patient-filter-chip ds-filter-chip active is-active patient-filter-found" aria-current="page" data-ds-filter-chip>' .
                    icon("manage_search") .
                    "<span>Encontrados</span><small>" .
                    number_format(count($templatesForModels), 0, ",", ".") .
                    "</small></span></nav>"
                : "";
        $html .=
            '<section class="card doc-templates-card ds-filter-list-block"><div class="section-head doc-ds-section-head"><div><span class="eyebrow">' .
            icon("edit_note") .
            '<span>Modelos</span></span><h2>Modelos de documento</h2><p>Crie, altere e aprove os textos que orientarão a emissão pela equipe.</p></div><a class="ghost small" href="' .
            href("documents", ["recent" => 1]) .
            '">' .
            icon("arrow_back") .
            "<span>Voltar</span></a></div>" .
            $modelFoundChip .
            '<div class="doc-template-grid doc-template-list-grid">';
        if (!$templatesForModels) {
            $html .=
                '<div class="empty">Nenhum modelo cadastrado para esta credencial.</div>';
        }
        foreach ($templatesForModels as $tpl) {
            $canEdit = can_edit_document_template($c, $tpl);
            $status = (string) $tpl["status"];
            $badge =
                '<span class="task-chip ' .
                document_status_class($status) .
                '">' .
                e(document_status_label($status)) .
                "</span>";
            $html .=
                '<article class="task-card doc-template-card doc-ds-template-card"><div class="task-main"><span class="task-marker doc-template-marker"></span><div class="task-copy"><h3>' .
                e($tpl["title"]) .
                "</h3><p>" .
                e($typeOptions[$tpl["type_key"]] ?? "Documento") .
                " · Modelo de " .
                e(role_label_for((string) $tpl["owner_role"], $cid)) .
                " · " .
                e($tpl["owner_name"] ?? "colaborador") .
                '</p><div class="task-meta">' .
                $badge .
                "</div></div></div>";
            if ($role === "gerente" && $status === "pending_approval") {
                $html .=
                    '<div class="task-actions doc-template-actions"><form method="post" class="inline">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="approve_template"><input type="hidden" name="id" value="' .
                    (int) $tpl["id"] .
                    '"><button class="primary small" type="submit">' .
                    icon("verified") .
                    '<span>Aprovar</span></button></form><form method="post" class="inline">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="reject_template"><input type="hidden" name="id" value="' .
                    (int) $tpl["id"] .
                    '"><button class="ghost small" type="submit">' .
                    icon("block") .
                    "<span>Rejeitar</span></button></form></div>";
            }
            if ($canEdit) {
                $editTypeOptions =
                    $role === "gerente"
                        ? document_type_options_for_role(
                            (string) $tpl["owner_role"],
                        )
                        : $typeOptionsForRole;
                if (!$editTypeOptions) {
                    $editTypeOptions = $typeOptions;
                }
                $html .=
                    '<div class="task-actions doc-template-actions doc-template-route-actions"><a class="ghost small" href="' .
                    href("documents", ["edit_model" => (int) $tpl["id"]]) .
                    '">' .
                    icon("edit_note") .
                    "<span>Editar modelo</span></a></div>";
            }
            $html .= "</article>";
        }
        $html .= "</div></section>";
        page("Documentos", $html);
        return;
    }
    $docSearchForm =
        '<section class="card doc-search-primary doc-ds-search ds-search-card patient-search-card"><form method="get" class="patient-search-bar doc-model-search" role="search"><input type="hidden" name="r" value="documents"><input type="hidden" name="recent" value="1"><label class="search-field"><input type="search" name="doc_q" value="' .
        e($docSearch) .
        '" placeholder="Paciente, título, conteúdo, emissor ou código" autocomplete="off" aria-label="Buscar documento por paciente, título, conteúdo, emissor ou código"></label><button class="primary small" type="submit">' .
        icon("search") .
        "<span>Busca rápida</span></button>" .
        ($docSearch !== ""
            ? '<a class="ghost small" href="' .
                href("documents", ["recent" => 1]) .
                '">' .
                icon("close") .
                "<span>Limpar</span></a>"
            : "") .
        "</form></section>";
    $html .= $docSearchForm;
    $docRows = "";
    foreach ($docs as $d) {
        $docRows .= document_ds_recent_row($d, $typeOptions);
    }
    $docFoundChip =
        $docSearch !== ""
            ? '<nav class="patient-filter-chips doc-found-filters ds-selection-chips" aria-label="Resultado da busca"><span class="patient-filter-chip ds-filter-chip active is-active patient-filter-found" aria-current="page" data-ds-filter-chip>' .
                icon("manage_search") .
                "<span>Encontrados</span><small>" .
                number_format(count($docs), 0, ",", ".") .
                "</small></span></nav>"
            : "";
    $recentTitle =
        $docSearch !== "" ? "Resultado da busca" : "Documentos emitidos";
    $recentHint =
        $docSearch !== ""
            ? "Resultados encontrados para a busca atual."
            : "Lista dos últimos documentos emitidos, com código, paciente, emissor e ações úteis.";
    $html .=
        '<section class="card doc-issued-card ds-filter-list-block" id="documentos-recentes"><div class="section-head doc-ds-section-head"><div><span class="eyebrow">' .
        icon("fingerprint") .
        "<span>Emitidos</span></span><h2>" .
        e($recentTitle) .
        "</h2><p>" .
        e($recentHint) .
        '</p></div><a class="ghost small" href="' .
        href("documents", ["emit" => 1]) .
        '">' .
        icon("add_circle") .
        "<span>Novo</span></a></div>" .
        $docFoundChip .
        ($docRows !== ""
            ? '<div class="doc-history-list doc-issued-list">' .
                $docRows .
                "</div>"
            : '<div class="empty">Nenhum documento encontrado' .
                ($docSearch !== "" ? " com esta busca" : " ainda") .
                ".</div>") .
        "</section>";
    page("Documentos", $html);
}
function page_procedures(): void
{

    $c = require_can("procedures");
    if (!has_effective_role($c, "gerente")) {
        audit("acesso_negado", "rota", "procedures", [
            "janela" => "procedures",
        ]);
        http_response_code(403);
        page(
            "Acesso restrito",
            '<div class="empty">Apenas a Gestão cadastra procedimentos.</div>',
        );
        return;
    }
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "save");
        $id = (int) ($_POST["id"] ?? 0);
        $backParams = $id > 0 ? ["edit" => $id] : ["new" => "1"];
        if ($act === "toggle") {
            $p = one(
                "SELECT id,active FROM pi_procedures WHERE id=? AND clinic_id=?",
                [$id, $cid],
            );
            if ($p) {
                $active = (int) $p["active"] === 1 ? 0 : 1;
                q(
                    "UPDATE pi_procedures SET active=?,updated_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?",
                    [$active, $uid, $id, $cid],
                );
                audit("procedimento_status", "procedimento", $id, [
                    "active" => $active,
                ]);
                flash(
                    $active
                        ? "Procedimento reativado."
                        : "Procedimento desativado.",
                );
            }
            redirect("procedures");
        }
        $title = mb_trim((string) ($_POST["title"] ?? ""));
        if ($title === "") {
            flash("Informe o nome do procedimento.", "bad");
            redirect("procedures", $backParams);
        }
        $duration = max(5, min(600, (int) ($_POST["duration_minutes"] ?? 30)));
        $price = parse_money_cents((string) ($_POST["price"] ?? "0"));
        $category = mb_trim((string) ($_POST["category"] ?? ""));
        $description = mb_trim((string) ($_POST["description"] ?? ""));
        $payments = mb_trim((string) ($_POST["payment_methods"] ?? ""));
        $pre = mb_trim((string) ($_POST["pre_instructions"] ?? ""));
        $post = mb_trim((string) ($_POST["post_care"] ?? ""));
        if ($id > 0) {
            $p = one(
                "SELECT id FROM pi_procedures WHERE id=? AND clinic_id=?",
                [$id, $cid],
            );
            if (!$p) {
                flash("Procedimento não encontrado.", "bad");
                redirect("procedures");
            }
            q(
                "UPDATE pi_procedures SET title=?,category=?,description=?,duration_minutes=?,price_cents=?,payment_methods=?,pre_instructions=?,post_care=?,updated_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?",
                [
                    $title,
                    $category,
                    $description,
                    $duration,
                    $price,
                    $payments,
                    $pre,
                    $post,
                    $uid,
                    $id,
                    $cid,
                ],
            );
            audit("procedimento_atualizado", "procedimento", $id, [
                "titulo" => $title,
            ]);
            flash("Procedimento atualizado.");
        } else {
            $submissionToken = trim(
                (string) ($_POST["procedure_submission_token"] ?? ""),
            );
            $submissionTokens =
                $_SESSION["procedure_create_submission_tokens"] ?? [];
            if (!is_array($submissionTokens)) {
                $submissionTokens = [];
            }
            $submissionAccepted =
                $submissionToken !== "" &&
                isset($submissionTokens[$submissionToken]);
            if ($submissionAccepted) {
                unset($submissionTokens[$submissionToken]);
                $_SESSION["procedure_create_submission_tokens"] =
                    $submissionTokens;
            }
            if (!$submissionAccepted) {
                flash(
                    "Este cadastro já foi enviado. Confira a lista antes de tentar novamente.",
                    "bad",
                );
                redirect("procedures");
            }
            q(
                "INSERT INTO pi_procedures (clinic_id,title,category,description,duration_minutes,price_cents,payment_methods,pre_instructions,post_care,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    $title,
                    $category,
                    $description,
                    $duration,
                    $price,
                    $payments,
                    $pre,
                    $post,
                    $uid,
                ],
            );
            $id = db_last_insert_id();
            audit("procedimento_criado", "procedimento", $id, [
                "titulo" => $title,
            ]);
            flash("Procedimento cadastrado.");
        }
        redirect("procedures");
    }
    $isCreate =
        isset($_GET["new"]) || (string) ($_GET["mode"] ?? "") === "create";
    $editId = (int) ($_GET["edit"] ?? 0);
    if ($isCreate || $editId > 0) {
        $row = [
            "id" => 0,
            "title" => "",
            "category" => "",
            "description" => "",
            "duration_minutes" => 30,
            "price_cents" => 0,
            "payment_methods" => "",
            "pre_instructions" => "",
            "post_care" => "",
            "active" => 1,
        ];
        if ($editId > 0) {
            $found = one(
                "SELECT id,title,category,description,duration_minutes,price_cents,payment_methods,pre_instructions,post_care,active FROM pi_procedures WHERE id=? AND clinic_id=?",
                [$editId, $cid],
            );
            if (!$found) {
                flash("Procedimento não encontrado.", "bad");
                redirect("procedures");
            }
            $row = $found;
        }
        $isEdit = $editId > 0;
        $submissionToken = "";
        if (!$isEdit) {
            $submissionTokens =
                $_SESSION["procedure_create_submission_tokens"] ?? [];
            if (!is_array($submissionTokens)) {
                $submissionTokens = [];
            }
            $submissionCutoff = time() - 1800;
            foreach ($submissionTokens as $token => $issuedAt) {
                if ((int) $issuedAt < $submissionCutoff) {
                    unset($submissionTokens[$token]);
                }
            }
            if (count($submissionTokens) >= 8) {
                $submissionTokens = array_slice(
                    $submissionTokens,
                    -7,
                    null,
                    true,
                );
            }
            $submissionToken = bin2hex(random_bytes(24));
            $submissionTokens[$submissionToken] = time();
            $_SESSION["procedure_create_submission_tokens"] =
                $submissionTokens;
        }
        $title = $isEdit ? "Editar procedimento" : "Novo procedimento";
        $subtitle = $isEdit
            ? "Revise duração, valor, formas de pagamento e orientações usadas pela Agenda."
            : "Cadastre um atendimento para ser usado em agendamentos, orientações e financeiro.";
        $cancel = href("procedures");
        $priceValue =
            (int) ($row["price_cents"] ?? 0) > 0
                ? money_br((int) $row["price_cents"])
                : "";
        $summary =
            '<div class="agenda-quick-summary procedure-route-summary" aria-label="Resumo do procedimento"><span>' .
            icon($isEdit ? "edit_note" : "playlist_add") .
            "<b>" .
            e($isEdit ? "Edição" : "Cadastro") .
            "</b><small>Modo</small></span><span>" .
            icon("timer") .
            "<b>" .
            e((string) ($row["duration_minutes"] ?? 30)) .
            " min</b><small>Duração</small></span><span>" .
            icon("payments") .
            "<b>" .
            e($priceValue !== "" ? $priceValue : "Sem valor") .
            "</b><small>Valor</small></span></div>";
        $hero =
            '<div class="agenda-quick-hero procedure-route-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
            icon("medical_services") .
            '</span><div class="agenda-quick-hero-copy"><h2>' .
            e($title) .
            "</h2><p>" .
            e($subtitle) .
            '</p></div><a class="ghost small agenda-quick-back" href="' .
            $cancel .
            '">' .
            icon("arrow_back") .
            "<span>Procedimentos</span></a></div>";
        $form =
            '<section class="form-panel agenda-route-form agenda-quick-form-panel procedure-route-form-panel">' .
            $hero .
            $summary .
            '<form method="post" class="compact agenda-create-form agenda-quick-form procedure-route-form"' .
            (!$isEdit ? " data-submit-once" : "") .
            ">" .
            csrf_field() .
            '<input type="hidden" name="act" value="save"><input type="hidden" name="id" value="' .
            (int) ($row["id"] ?? 0) .
            '">' .
            (!$isEdit
                ? '<input type="hidden" name="procedure_submission_token" value="' .
                    e($submissionToken) .
                    '">'
                : "") .
            '<fieldset class="agenda-quick-section agenda-quick-main"><legend>' .
            icon("edit_note") .
            '<span>Identificação</span></legend><div class="agenda-quick-grid">' .
            form_row(
                "Nome do procedimento",
                input(
                    "title",
                    "text",
                    $row["title"] ?? "",
                    'required maxlength="160" placeholder="Ex.: Consulta inicial, retorno, exame..." autocomplete="off"',
                ),
            ) .
            form_row(
                "Categoria",
                input(
                    "category",
                    "text",
                    $row["category"] ?? "",
                    'maxlength="80" placeholder="Consulta, exame, evento..." autocomplete="off"',
                ),
            ) .
            '</div></fieldset><fieldset class="agenda-quick-section agenda-quick-time"><legend>' .
            icon("timer") .
            '<span>Duração e valor</span></legend><div class="agenda-quick-time-grid">' .
            form_row(
                "Duração média",
                input(
                    "duration_minutes",
                    "number",
                    (string) ($row["duration_minutes"] ?? 30),
                    'required min="5" max="600" step="5" inputmode="numeric"',
                ),
            ) .
            form_row(
                "Valor",
                input(
                    "price",
                    "text",
                    $priceValue,
                    'inputmode="decimal" placeholder="R$ 0,00"',
                ),
            ) .
            "</div>" .
            form_row(
                "Formas de pagamento disponíveis",
                input(
                    "payment_methods",
                    "text",
                    $row["payment_methods"] ?? "",
                    'placeholder="Pix, dinheiro, cartão, convênio..."',
                ),
            ) .
            '</fieldset><fieldset class="agenda-quick-section agenda-quick-notes"><legend>' .
            icon("notes") .
            "<span>Orientações</span></legend>" .
            form_row(
                "O que será feito",
                textarea(
                    "description",
                    $row["description"] ?? "",
                    'rows="3" placeholder="Resumo claro para orientar recepção, profissional e financeiro"',
                ),
            ) .
            form_row(
                "Pré-preparativos",
                textarea(
                    "pre_instructions",
                    $row["pre_instructions"] ?? "",
                    'rows="3" placeholder="Orientações antes do atendimento"',
                ),
            ) .
            form_row(
                "Cuidados pós-procedimento",
                textarea(
                    "post_care",
                    $row["post_care"] ?? "",
                    'rows="3" placeholder="Orientações após o procedimento"',
                ),
            ) .
            '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
            $cancel .
            '">' .
            icon("close") .
            '<span>Desistir</span></a><button type="submit" class="primary">' .
            icon("save") .
            "<span>Salvar procedimento</span></button></div></form></section>";
        page(
            $title,
            page_head(
                $title,
                $subtitle,
                '<a class="ghost small" href="' .
                    $cancel .
                    '">' .
                    icon("calendar_view_day") .
                    "<span>Lista</span></a>",
            ) .
                card(
                    $form,
                    "agenda-route-card agenda-quick-route-card procedure-route-card",
                ),
        );
        return;
    }
    $allRows = procedure_options($cid, false);
    $search = mb_trim((string) ($_GET["q"] ?? ""));
    $rows = $allRows;
    if ($search !== "") {
        $needle = mb_strtolower($search);
        $rows = array_values(
            array_filter($allRows, function (array $r) use ($needle): bool {

                $hay = mb_strtolower(
                    trim(
                        (string) ($r["title"] ?? "") .
                            " " .
                            (string) ($r["category"] ?? "") .
                            " " .
                            (string) ($r["description"] ?? "") .
                            " " .
                            (string) ($r["payment_methods"] ?? ""),
                    ),
                );
                return $needle === "" || str_contains($hay, $needle);
            }),
        );
    }
    $active = 0;
    $inactive = 0;
    $durationSum = 0;
    $durationCount = 0;
    $priced = 0;
    foreach ($allRows as $r) {
        if ((int) $r["active"] === 1) {
            $active++;
        } else {
            $inactive++;
        }
        $durationSum += (int) $r["duration_minutes"];
        $durationCount++;
        if ((int) $r["price_cents"] > 0) {
            $priced++;
        }
    }
    $avgDuration =
        $durationCount > 0 ? (int) round($durationSum / $durationCount, 0, \RoundingMode::HalfAwayFromZero) : 0;
    $stats =
        '<div class="kpis procedure-kpis procedure-kpis-refined procedure-ds-kpis"><div>' .
        icon("event_available") .
        "<p><b>" .
        (int) $active .
        "</b><span>ativos na Agenda</span></p></div><div>" .
        icon("inventory_2") .
        "<p><b>" .
        (int) count($allRows) .
        "</b><span>cadastrados</span></p></div><div>" .
        icon("payments") .
        "<p><b>" .
        (int) $priced .
        "</b><span>com valor</span></p></div><div>" .
        icon("timer") .
        "<p><b>" .
        ($avgDuration > 0 ? (int) $avgDuration . " min" : "—") .
        "</b><span>duração média</span></p></div></div>";
    $clearHref = href("procedures");
    $searchCard =
        '<section class="card patient-search-card ds-search-card procedures-search-card"><form method="get" class="patient-search-bar procedure-search-bar" role="search"><input type="hidden" name="r" value="procedures"><label class="search-field"><input type="search" name="q" value="' .
        e($search) .
        '" placeholder="Buscar por nome, categoria ou forma de pagamento" aria-label="Buscar procedimentos"></label><button class="primary small" type="submit">' .
        icon("search") .
        "<span>Busca rápida</span></button>" .
        ($search !== ""
            ? '<a class="ghost small" href="' .
                $clearHref .
                '">' .
                icon("close") .
                "<span>Limpar</span></a>"
            : "") .
        "</form></section>";
    $cards = "";
    foreach ($rows as $r) {
        $id = (int) $r["id"];
        $isActive = (int) $r["active"] === 1;
        $status = $isActive ? "Ativo" : "Inativo";
        $category = mb_trim((string) ($r["category"] ?? ""));
        $payments = mb_trim((string) ($r["payment_methods"] ?? ""));
        $priceLabel =
            (int) $r["price_cents"] > 0
                ? money_br((int) $r["price_cents"])
                : "Sem valor";
        $durationLabel = ((int) $r["duration_minutes"]) . " min";
        $categoryLabel = $category !== "" ? $category : "Sem categoria";
        $paymentLabel = $payments !== "" ? $payments : "Sem forma definida";
        $statusClass = $isActive ? "ok" : "warn";
        $edit =
            '<a class="ghost small procedure-edit-link" href="' .
            href("procedures", ["edit" => $id]) .
            '">' .
            icon("edit") .
            "<span>Editar</span></a>";
        $toggle =
            '<form method="post" class="inline procedure-toggle">' .
            csrf_field() .
            '<input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="' .
            $id .
            '"><button class="ghost small" type="submit" aria-label="' .
            ($isActive ? "Desativar procedimento" : "Ativar procedimento") .
            '">' .
            icon($isActive ? "toggle_on" : "toggle_off") .
            "<span>" .
            ($isActive ? "Desativar" : "Ativar") .
            "</span></button></form>";
        $cards .=
            '<article class="procedure-row ds-person-row ' .
            ($isActive ? "patient-status-ok" : "patient-status-warn") .
            '"><span class="ds-person-avatar" aria-hidden="true">' .
            icon("medical_services") .
            '</span><div class="ds-person-main"><div class="ds-person-title"><strong>' .
            e($r["title"]) .
            '</strong><span class="ds-status-pill ' .
            $statusClass .
            '">' .
            e($status) .
            '</span><span class="procedure-category-chip">' .
            e($categoryLabel) .
            '</span></div><div class="ds-person-meta procedure-row-meta"><span>' .
            icon("timer") .
            "<b>" .
            e($durationLabel) .
            "</b></span><span>" .
            icon("payments") .
            "<b>" .
            e($priceLabel) .
            "</b></span><span>" .
            icon("credit_card") .
            "<b>" .
            e($paymentLabel) .
            '</b></span></div></div><div class="ds-person-actions procedure-card-actions">' .
            $edit .
            $toggle .
            "</div></article>";
    }
    if ($cards === "") {
        $emptyText =
            $search !== ""
                ? "Nenhum procedimento encontrado para esta busca."
                : "Nenhum procedimento cadastrado.";
        $emptyHelp =
            $search !== ""
                ? "Revise o termo pesquisado ou limpe a busca para voltar à lista completa."
                : "Cadastre os tipos de atendimento para orientar a Agenda, estimar duração e padronizar orientações.";
        $cards =
            '<div class="empty procedure-empty"><span>' .
            icon("medical_services") .
            "</span><strong>" .
            e($emptyText) .
            "</strong><small>" .
            e($emptyHelp) .
            "</small>" .
            ($search !== ""
                ? '<a class="ghost small" href="' .
                    $clearHref .
                    '">' .
                    icon("close") .
                    "<span>Limpar busca</span></a>"
                : '<a class="primary small" href="' .
                    href("procedures", ["new" => "1"]) .
                    '">' .
                    icon("add") .
                    "<span>Novo procedimento</span></a>") .
            "</div>";
    }
    $foundChip =
        $search !== ""
            ? '<span class="task-chip info">' .
                (int) count($rows) .
                " encontrados</span>"
            : '<span class="task-chip info">' .
                (int) $active .
                " ativos</span>";
    $listHead =
        '<div class="procedure-list-head"><div><h2>Procedimentos cadastrados</h2><p>Lista compacta para manter Agenda, Recepção e Financeiro alinhados.</p></div>' .
        $foundChip .
        "</div>";
    $action =
        '<a class="primary small" href="' .
        href("procedures", ["new" => "1"]) .
        '">' .
        icon("add") .
        "<span>Novo procedimento</span></a>";
    page(
        "Procedimentos",
        page_head(
            "Procedimentos",
            "Tipos de atendimento, consultas e eventos usados pela Recepção ao agendar.",
            $action,
        ) .
            '<div class="procedures-ds-screen patient-directory-screen procedure-directory-screen">' .
            $stats .
            $searchCard .
            card(
                $listHead .
                    '<div class="procedure-list ds-person-list">' .
                    $cards .
                    "</div>",
                "procedure-list-card procedure-list-card-refined patient-list-card patient-directory-card ds-filter-list-block procedures-ds-list",
            ) .
            "</div>",
    );
}
