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
    
        $id = document_identifier_display($identifier);
        return $id !== ""
            ? '<footer class="doc-print-footer" aria-label="Identificador do documento para segunda via"><span class="doc-print-identifier">' .
                    e($id) .
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

    public static function document_ds_status_chip(string $status): string
    
    {
    
        return '<span class="doc-ds-chip ' .
            document_status_class($status) .
            '">' .
            e(document_status_label($status)) .
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
                $out .= "<p>" . nl2br(e($p), false) . "</p>";
            }
            return $out;
        }
        return document_sanitize_html($body);
    
    }

    public static function document_print_page_core_html(
        string $html,
        ?string $identifier = null,
    ): string 
    {
    
        return '<div class="doc-print-page" role="document">' .
            document_print_header_html($identifier) .
            '<div class="doc-print-content"><div class="doc-print-main">' .
            $html .
            "</div></div>" .
            document_print_footer_html($identifier) .
            "</div>";
    
    }

    public static function document_preview_page_html(
        string $html,
        ?string $identifier = null,
    ): string 
    {
    
        return '<div class="doc-issued-body doc-a4-preview" aria-label="Pré-visualização em folha A4 aproximada da impressão"><div class="doc-a4-preview-frame">' .
            document_print_page_core_html($html, $identifier) .
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

    public static function render_document_body(string $body, array $vars): string
    
    {
    
        $map = [];
        foreach ($vars as $k => $v) {
            $map["{{" . $k . "}}"] = e((string) $v);
        }
        return document_sanitize_html(strtr(document_body_to_html($body), $map));
    
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

    public static function document_field_buttons(): string
    
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

    public static function document_editor_button(
        string $cmd,
        string $iconName,
        string $label,
        string $value = "",
    ): string 
    {
    
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

    public static function document_editor_html(
        string $name,
        string $value = "",
        string $id = "",
    ): string 
    {
    
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

    public static function document_overview_cards(
        int $approved,
        int $pending,
        int $visibleDocs,
        string $docSearchMode,
    ): string 
    {
    
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
}
