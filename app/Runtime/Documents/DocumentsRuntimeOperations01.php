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

final class DocumentsRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function document_assign_identifier(int $cid, int $docId): string
    
    {
    
        return \Prontoo\Runtime\Operational\OperationalComposition::documentIssuance()
            ->assignIdentifier($cid, $docId);
    
    }

    public static function create_document_draft_from_template(
        array $c,
        int $templateId,
        int $patientId = 0,
        int $appointmentId = 0,
        array $context = [],
    ): int 
    {
    
        $cid = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_id_required($c);
        $uid = (int) ($c["user"]["id"] ?? 0);
        [$patientId, $appointmentId, $appt] = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_resolve_patient_appointment(
            $cid,
            $patientId,
            $appointmentId,
        );
        if ($patientId > 0) {
            $block = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_sensitive_block_reason($cid, $patientId);
            if ($block !== null) {
                throw new RuntimeException($block);
            }
        }
        if ($templateId <= 0) {
            throw new RuntimeException("Escolha um modelo aprovado.");
        }
        $visibility = \Prontoo\Domain\Documents\DocumentTemplatePolicy::document_template_visibility($c);
        $params = (array) ($visibility["parameters"] ?? []);
        $visibilityMode = (string) ($visibility["mode"] ?? "role");
        $tpl = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.01.create_document_draft_from_template.01', array_merge([$templateId, $cid], $params), compact('visibilityMode'));
        if (!$tpl) {
            throw new RuntimeException(
                "Modelo não encontrado para esta credencial.",
            );
        }
        if ((string) $tpl["status"] !== "approved") {
            throw new RuntimeException("Este modelo ainda não está aprovado.");
        }
        if (!\Prontoo\Domain\Documents\DocumentTemplatePolicy::document_can_issue_template($c, $tpl)) {
            throw new RuntimeException(
                "Este modelo não pode ser usado por este cargo.",
            );
        }
        $contextJson = \Prontoo\Domain\Documents\DocumentsDomainOperations02::document_context_json_encode($context);
        $vars = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::document_issue_context($c, $patientId, $appointmentId, $context);
        $content = \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::render_document_body((string) $tpl["body"], $vars);
        \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.01.create_document_draft_from_template.02', [
                $cid,
                $templateId,
                $patientId > 0 ? $patientId : null,
                $appointmentId > 0 ? $appointmentId : null,
                $contextJson,
                $uid,
                (string) $tpl["type_key"],
                (string) $tpl["title"],
                $content,
            ], []);
        $docId = \Prontoo\Runtime\Operational\OperationalComposition::documents()->lastInsertId();
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("documento_previsualizacao_criada", "documento", $docId, [
            "titulo" => $tpl["title"] ?? "",
            "document_type" => (string) $tpl["type_key"],
            "patient_link_id" => $patientId > 0 ? $patientId : null,
            "appointment_id" => $appointmentId > 0 ? $appointmentId : null,
            "contexto_documental" => \Prontoo\Domain\Documents\DocumentHtmlPolicy::document_context_json_decode($contextJson),
            "patient_name" => $patientId > 0 ? $vars["paciente"] ?? "" : "",
            "audit_body" =>
                "Pré-visualização criada a partir de modelo aprovado. Conteúdo livre não é editável antes da emissão.",
        ]);
        return $docId;
    
    }

    public static function save_document_draft(
        array $c,
        int $docId,
        string $title,
        int $patientId,
        string $content,
        string $status = "preparado",
        int $appointmentId = 0,
        array $context = [],
    ): void 
    {
    
        $doc = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::fetch_document_for_current_user($c, $docId);
        if (!$doc) {
            throw new RuntimeException("Documento não encontrado.");
        }
        $current = (string) ($doc["document_status"] ?? "emitido");
        if (!\Prontoo\Domain\Documents\DocumentTypePolicy::document_editable_status($current)) {
            throw new RuntimeException(
                "Documento já emitido não pode ser editado. Cancele ou substitua por uma nova versão.",
            );
        }
        if (
            (int) ($doc["issued_by"] ?? 0) !== (int) ($c["user"]["id"] ?? 0) &&
            (string) !\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente")
        ) {
            throw new RuntimeException(
                "Apenas o criador ou o Administrativo pode ajustar esta pré-visualização.",
            );
        }
        $cid = (int) $c["clinic_id"];
        [$patientId, $appointmentId, $appt] = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations02::document_resolve_patient_appointment(
            $cid,
            $patientId,
            $appointmentId,
        );
        if ($patientId > 0) {
            $block = \Prontoo\Runtime\Patients\PatientsRuntimeOperations01::patient_sensitive_block_reason($cid, $patientId);
            if ($block !== null) {
                throw new RuntimeException($block);
            }
        }
        $tpl = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.documents.01.save_document_draft.01', [(int) ($doc["template_id"] ?? 0), $cid], []);
        if (!$tpl) {
            throw new RuntimeException(
                "Modelo original não está aprovado ou não foi encontrado.",
            );
        }
        $contextJson = \Prontoo\Domain\Documents\DocumentsDomainOperations02::document_context_json_encode($context);
        $vars = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::document_issue_context($c, $patientId, $appointmentId, $context);
        $body = \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::render_document_body((string) $tpl["body"], $vars);
        if (\Prontoo\Domain\Documents\DocumentHtmlPolicy::document_body_is_empty($body)) {
            throw new RuntimeException(
                "O modelo aprovado gerou conteúdo vazio. Revise o modelo antes de emitir.",
            );
        }
        \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.01.save_document_draft.02', [
                (string) $tpl["title"],
                $patientId > 0 ? $patientId : null,
                $appointmentId > 0 ? $appointmentId : null,
                $contextJson,
                $body,
                $docId,
                $cid,
            ], []);
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("documento_vinculos_previsualizacao", "documento", $docId, [
            "titulo" => $tpl["title"] ?? "",
            "status" => \Prontoo\Domain\Documents\DocumentTypePolicy::document_status_label("preparado"),
            "patient_link_id" => $patientId > 0 ? $patientId : null,
            "appointment_id" => $appointmentId > 0 ? $appointmentId : null,
            "contexto_documental" => \Prontoo\Domain\Documents\DocumentHtmlPolicy::document_context_json_decode($contextJson),
            "audit_body" =>
                "Vínculos contextuais associados à pré-visualização do documento. O conteúdo permanece derivado exclusivamente do modelo aprovado.",
        ]);
    
    }

    public static function discard_document_draft(array $c, int $docId): void
    
    {
    
        $doc = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::fetch_document_for_current_user($c, $docId);
        if (!$doc) {
            throw new RuntimeException("Documento não encontrado.");
        }
        $current = (string) ($doc["document_status"] ?? "emitido");
        if (!\Prontoo\Domain\Documents\DocumentTypePolicy::document_editable_status($current)) {
            throw new RuntimeException(
                "Documento já emitido não pode ser descartado.",
            );
        }
        if (
            (int) ($doc["issued_by"] ?? 0) !== (int) ($c["user"]["id"] ?? 0) &&
            (string) !\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente")
        ) {
            throw new RuntimeException(
                "Apenas o criador ou o Administrativo pode descartar esta pré-visualização.",
            );
        }
        \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.documents.01.discard_document_draft.01', [$docId, (int) $c["clinic_id"]], []);
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("documento_descartado", "documento", $docId, [
            "titulo" => $doc["title"] ?? "",
            "status" => "Cancelado",
            "audit_body" =>
                "Pré-visualização de documento descartada antes da emissão.",
        ]);
    
    }

    public static function confirm_document_issue(array $c, int $docId): void
    
    {
    
        $doc = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::fetch_document_for_current_user($c, $docId);
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
            (string) !\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::has_effective_role($c, "gerente")
        ) {
            throw new RuntimeException(
                "Apenas o criador ou a Gestão pode confirmar este documento.",
            );
        }
        if (\Prontoo\Domain\Documents\DocumentHtmlPolicy::document_body_is_empty((string) ($doc["content"] ?? ""))) {
            throw new RuntimeException(
                "Conteúdo vazio. Revise o modelo antes de emitir.",
            );
        }
        $identifier = \Prontoo\Runtime\Operational\OperationalComposition::documentIssuance()
            ->confirmIssue((int) $c["clinic_id"], $docId);
        $types = \Prontoo\Domain\Documents\DocumentTypePolicy::document_type_options();
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("documento_emitido", "documento", $docId, [
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

    public static function document_role_config(string $role, int $cid): array
    
    {
    
        $label = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($role, $cid);
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

    public static function document_ds_recent_row(array $d, array $typeOptions): string
    
    {
    
        $st = (string) ($d["document_status"] ?? "emitido");
        $idText = \Prontoo\Domain\Documents\DocumentIdentifierPolicy::document_identifier_display($d["document_identifier"] ?? "");
        $patient = mb_trim((string) ($d["patient_name"] ?? ""));
        if ($patient === "") {
            $patient = "Paciente";
        }
        $actor = mb_trim((string) ($d["issued_name"] ?? ""));
        $type = \Prontoo\Domain\Documents\DocumentTypePolicy::document_ds_type_label(
            $typeOptions,
            (string) ($d["type_key"] ?? ""),
        );
        $when = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($d["updated_at"] ?: $d["issued_at"] ?? null);
        $idHtml =
            $idText !== ""
                ? '<span class="doc-ds-id">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("fingerprint") .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($idText) .
                    "</span></span>"
                : '<span class="doc-ds-id is-empty-id">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("fingerprint") .
                    "<span>sem código</span></span>";
        $issuerLabel = $actor !== "" ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($actor) : "";
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
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("assignment_ind") .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($summary) .
                    "</span></span>"
                : "";
        $chips = "";
        if ($when !== "" && $when !== "—") {
            $chips .=
                '<span class="doc-ds-meta-chip">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("schedule") .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($when) .
                "</span></span>";
        }
        $chips .=
            '<span class="doc-ds-meta-chip">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("category") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($type) .
            "</span></span>";
        $chips .=
            '<span class="doc-issued-state">' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_ds_status_chip($st) .
            "</span>";
        $action = \Prontoo\Domain\Documents\DocumentTypePolicy::document_editable_status($st)
            ? '<a class="primary small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("documents", ["doc" => (int) $d["id"]]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("preview") .
                "<span>Conferir</span></a>"
            : '<a class="ghost small" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("document_view", ["id" => (int) $d["id"]]) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("visibility") .
                "<span>Visualizar</span></a>" .
                ($st === "emitido"
                    ? '<a class="primary small" target="_blank" rel="noopener" href="' .
                        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("document_print", ["id" => (int) $d["id"]]) .
                        '">' .
                        \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("print") .
                        "<span>Imprimir</span></a>" .
                        \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_link((int) $d["id"])
                    : "");
        return '<article class="doc-issued-row doc-ds-row doc-issued-row-compact" data-ds-row-kind="surface"><span class="doc-history-icon doc-issued-icon doc-ds-row-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(\Prontoo\Domain\Documents\DocumentTypePolicy::document_editable_status($st) ? "preview" : "description") .
            '</span><span class="doc-issued-main doc-ds-row-main"><strong class="doc-issued-title doc-ds-row-title">' .
            $idHtml .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($d["title"] ?? "Documento") .
            "</span></strong>" .
            $summaryHtml .
            '<span class="doc-ds-row-meta">' .
            $chips .
            '</span></span><span class="doc-history-actions doc-issued-actions doc-ds-row-actions">' .
            $action .
            "</span></article>";
    
    }

    public static function document_ds_model_row(array $tpl, array $typeOptions, int $cid): string
    
    {
    
        $kind = $typeOptions[(string) ($tpl["type_key"] ?? "")] ?? "Documento";
        $owner = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for((string) ($tpl["owner_role"] ?? ""), $cid);
        $last = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_br($tpl["last_used_at"] ?? null);
        $lastMeta =
            $last !== "" && $last !== "—"
                ? '<span class="doc-ds-meta-chip">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("history") .
                    "<span>Usado em " .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($last) .
                    "</span></span>"
                : '<span class="doc-ds-meta-chip">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("fiber_new") .
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("description") .
            '</span><span class="doc-model-copy doc-ds-row-main"><strong class="doc-ds-row-title">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($tpl["title"] ?? "Modelo") .
            '</strong><span class="doc-ds-row-meta"><span class="doc-ds-meta-chip">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("category") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($kind) .
            '</span></span><span class="doc-ds-meta-chip">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($owner) .
            "</span></span>" .
            $lastMeta .
            '</span><small>Abre pré-visualização antes da emissão definitiva.</small></span><form method="post" class="doc-model-create doc-ds-row-actions">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="create_document"><input type="hidden" name="template_id" value="' .
            (int) $tpl["id"] .
            '">' .
            $patientHidden .
            $appointmentHidden .
            '<button class="primary small" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("preview") .
            "<span>Pré-visualizar</span></button></form></article>";
    
    }

    public static function document_template_status_after_save(
        array $c,
        string $ownerRole,
        bool $isUpdate = false,
    ): array 
    {
    
        $editorRole = (string) ($c["role"] ?? "");
        if ($editorRole !== "gerente") {
            return ["pending_approval", 1, null, null];
        }
        return ["approved", 0, (int) $c["user"]["id"], \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::now()];
    
    }
}
