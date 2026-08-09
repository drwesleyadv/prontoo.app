<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Documents;

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

final class DocumentsPresentationOperations01
{
    private function __construct()
    {
    }

    public static function document_print_footer_html(?string $identifier): string
    
    {
    
        $id = \Prontoo\Domain\Documents\DocumentIdentifierPolicy::document_identifier_display($identifier);
        return $id !== ""
            ? '<footer class="doc-print-footer" aria-label="Identificador do documento para segunda via"><span class="doc-print-identifier">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($id) .
                    "</span></footer>"
            : "";
    
    }

    public static function document_ds_metric(
        string $label,
        mixed $value,
        string $iconName,
        string $note = "",
        string $class = "",
    ): string 
    {
    
        return '<article class="doc-ds-metric patient-kpi-card kpi-card ' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($class) .
            '"><span class="doc-ds-metric-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
            '</span><p class="doc-ds-metric-copy"><b>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $value) .
            "</b><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span>" .
            ($note !== "" ? "<small>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($note) . "</small>" : "") .
            "</p></article>";
    
    }

    public static function document_ds_status_chip(string $status): string
    
    {
    
        return '<span class="doc-ds-chip ' .
            \Prontoo\Domain\Documents\DocumentTypePolicy::document_status_class($status) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Domain\Documents\DocumentTypePolicy::document_status_label($status)) .
            "</span>";
    
    }

    public static function document_body_to_html(string $body): string
    
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
                $out .= "<p>" . nl2br(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($p), false) . "</p>";
            }
            return $out;
        }
        return \Prontoo\Domain\Documents\DocumentHtmlPolicy::document_sanitize_html($body);
    
    }

    public static function document_print_page_core_html(
        string $html,
        ?string $identifier = null,
    ): string 
    {
    
        return '<div class="doc-print-page" role="document">' .
            \Prontoo\Domain\Documents\DocumentIdentifierPolicy::document_print_header_html($identifier) .
            '<div class="doc-print-content"><div class="doc-print-main">' .
            $html .
            "</div></div>" .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_print_footer_html($identifier) .
            "</div>";
    
    }

    public static function document_preview_page_html(
        string $html,
        ?string $identifier = null,
    ): string 
    {
    
        return '<div class="doc-issued-body doc-a4-preview" aria-label="Pré-visualização em folha A4 aproximada da impressão"><div class="doc-a4-preview-frame">' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_print_page_core_html($html, $identifier) .
            "</div></div>";
    
    }

    public static function document_print_document_shell_html(
        array $doc,
        bool $autoPrint = true,
        string $mode = "print",
    ): string 
    {
    
        $title = mb_trim((string) ($doc["title"] ?? "Documento"));
        if ($title === "") {
            $title = "Documento";
        }
        $identifier = $doc["document_identifier"] ?? null;
        $content = \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_body_to_html((string) ($doc["content"] ?? ""));
        $nonce = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($GLOBALS["csp_nonce"] ?? ""));
        $script = $autoPrint
            ? '<script nonce="' .
                $nonce .
                '">window.addEventListener("load",function(){setTimeout(function(){window.print()},120)});</script>'
            : "";
        $modeClass = preg_replace("/[^a-z0-9_-]/i", "", (string) $mode) ?: "print";
        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
            '</title><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
            rawurlencode(
                defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
            ) .
            '"></head><body class="document-print document-print-exact document-print-' .
            $modeClass .
            '"><main class="doc-print-standalone" aria-label="Documento pronto para impressão">' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_print_page_core_html($content, $identifier) .
            "</main>" .
            $script .
            "</body></html>";
    
    }

    public static function render_document_body(string $body, array $vars): string
    
    {
    
        $map = [];
        foreach ($vars as $k => $v) {
            $map["{{" . $k . "}}"] = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $v);
        }
        return \Prontoo\Domain\Documents\DocumentHtmlPolicy::document_sanitize_html(strtr(\Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_body_to_html($body), $map));
    
    }

    public static function patient_summary_clinical_block(
        string $title,
        string $iconName,
        array $items,
        string $empty,
    ): string 
    {
    
        $items = array_slice($items, 0, 3);
        $h =
            '<article class="patient-clinical-card"><header>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
            "<h3>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
            "</h3></header>";
        if (!$items) {
            return $h . '<p class="empty-mini">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($empty) . "</p></article>";
        }
        $h .= '<div class="clinical-mini-list">';
        foreach ($items as $it) {
            $it = is_array($it) ? $it : [];
            $h .=
                '<div class="clinical-mini-item"><time>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($it["time"] ?? "")) .
                "</time><strong>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($it["title"] ?? $title)) .
                "</strong><p>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Domain\Documents\DocumentsDomainOperations02::patient_summary_excerpt((string) ($it["body"] ?? ""), 220)) .
                "</p></div>";
        }
        return $h . "</div></article>";
    
    }

    public static function document_field_buttons(): string
    
    {
    
        $h =
            '<div class="doc-field-panel-head"><strong>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("data_object") .
            '<span>Campos automáticos</span></strong><small>Clique para inserir no ponto do texto. O Prontoo substitui esses campos na pré-visualização.</small></div><div class="doc-field-groups" aria-label="Campos do sistema agrupados">';
        foreach (\Prontoo\Domain\Documents\DocumentTypePolicy::document_system_field_groups() as $groupKey => $group) {
            $h .=
                '<section class="doc-field-group doc-field-group-' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $groupKey) .
                '"><h4>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon((string) ($group["icon"] ?? "data_object")) .
                "<span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($group["label"] ?? "Campos")) .
                '</span></h4><div class="doc-field-palette">';
            foreach ($group["fields"] ?? [] as $k => $label) {
                $token = "{{" . $k . "}}";
                $h .=
                    '<button type="button" class="ghost small doc-field-token-button" data-doc-field="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($token) .
                    '" title="Inserir ' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($token) .
                    '"><span>' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
                    "</span><small>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($token) .
                    "</small></button>";
            }
            $h .= "</div></section>";
        }
        return $h . "</div>";
    
    }

    public static function document_editor_button(
        string $cmd,
        string $iconName,
        string $label,
        string $value = "",
    ): string 
    {
    
        $valueAttr = $value !== "" ? ' data-doc-value="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($value) . '"' : "";
        return '<button type="button" class="ghost small doc-tool" data-doc-cmd="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cmd) .
            '"' .
            $valueAttr .
            ' title="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            '" aria-label="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span></button>";
    
    }

    public static function document_editor_html(
        string $name,
        string $value = "",
        string $id = "",
    ): string 
    {
    
        $id = $id !== "" ? $id : "doced_" . bin2hex(random_bytes(3));
        $html = \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_body_to_html($value);
        $toolbar =
            '<div class="doc-editor-toolbar doc-google-toolbar" role="toolbar" aria-label="Formatação do modelo"><div class="doc-toolbar-group doc-toolbar-history">' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button("undo", "undo", "Desfazer") .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button("redo", "redo", "Refazer") .
            '</div><div class="doc-toolbar-group doc-toolbar-style">' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button("formatBlock", "title", "Título", "H2") .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button("formatBlock", "subject", "Subtítulo", "H3") .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button("formatBlock", "notes", "Texto", "P") .
            '</div><div class="doc-toolbar-group doc-toolbar-format">' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button("bold", "format_bold", "Negrito") .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button("italic", "format_italic", "Itálico") .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button("underline", "format_underlined", "Sublinhar") .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button("removeFormat", "format_clear", "Limpar") .
            '</div><div class="doc-toolbar-group doc-toolbar-list">' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button(
                "insertUnorderedList",
                "format_list_bulleted",
                "Lista",
            ) .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button(
                "insertOrderedList",
                "format_list_numbered",
                "Numeração",
            ) .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button(
                "outdent",
                "format_indent_decrease",
                "Recuo menor",
            ) .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button(
                "indent",
                "format_indent_increase",
                "Recuo maior",
            ) .
            '</div><div class="doc-toolbar-group doc-toolbar-align">' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button("justifyLeft", "format_align_left", "Esquerda") .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button(
                "justifyCenter",
                "format_align_center",
                "Centralizar",
            ) .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button(
                "justifyRight",
                "format_align_right",
                "Direita",
            ) .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_button(
                "justifyFull",
                "format_align_justify",
                "Justificar",
            ) .
            "</div></div>";
        return '<div class="doc-editor-wrap doc-google-editor" data-doc-editor-wrap><div class="doc-editor-titlebar"><div><strong>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_note") .
            '<span>Editor do modelo</span></strong><small>Escreva o texto-base, formate a leitura e insira campos automáticos quando precisar.</small></div><span class="doc-editor-mini" data-doc-word-count>0 palavras</span></div>' .
            $toolbar .
            '<div class="doc-editor-main"><div class="doc-editor-paper"><div class="doc-editor" contenteditable="true" data-doc-editor aria-label="Conteúdo do modelo" spellcheck="true">' .
            $html .
            '</div><textarea name="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
            '" id="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($id) .
            '" data-doc-editor-input hidden>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($html) .
            '</textarea></div><aside class="doc-field-panel">' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_field_buttons() .
            '</aside></div><div class="doc-editor-footnote">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("verified_user") .
            "<span>Campos como <b>{{paciente}}</b> e <b>{{data_extenso}}</b> serão preenchidos automaticamente antes da emissão.</span></div></div>";
    
    }

    public static function document_overview_cards(
        int $approved,
        int $pending,
        int $visibleDocs,
        string $docSearchMode,
    ): string 
    {
    
        return '<section class="doc-overview-cards kpis" aria-label="Resumo de documentos">' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_ds_metric(
                "Modelos aprovados",
                $approved,
                "verified",
                "Disponíveis para criar documentos",
                "is-ok",
            ) .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_ds_metric(
                "Aguardando aprovação",
                $pending,
                "pending_actions",
                "Modelos em revisão",
                "is-warn",
            ) .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_ds_metric(
                "Emitidos na lista",
                $visibleDocs,
                "description",
                $docSearchMode,
                "is-info",
            ) .
            "</section>";
    
    }
}
