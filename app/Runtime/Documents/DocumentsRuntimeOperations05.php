<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Documents;

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

final class DocumentsRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function page_documents(): void
    
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
}
