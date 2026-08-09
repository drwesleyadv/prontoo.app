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

final class DocumentsRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function document_stage_strip(
        string $stage,
        int $approved,
        int $pending,
        int $visibleDocs,
    ): string 
    {
    
        $items = [
            [
                "recent",
                "description",
                "Emitidos",
                "Consultar documentos confirmados, imprimir ou abrir o PDF.",
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("documents", ["recent" => 1]),
                $visibleDocs,
            ],
            [
                "emit",
                "post_add",
                "Criar documento",
                "Escolher modelo aprovado e gerar pré-visualização segura.",
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("documents", ["emit" => 1]),
                $approved,
            ],
            [
                "models",
                "edit_note",
                "Modelos",
                "Criar, alterar e aprovar textos usados pela equipe.",
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("documents", ["models" => 1]),
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
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ic) .
                '</span><span class="doc-stage-copy"><strong>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
                "</strong><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($desc) .
                "</small></span><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($count) .
                "</b></a>";
        }
        return $out . "</nav>";
    
    }

    public static function document_template_author_form(
        array $typeOptions,
        string $selectedType = "declaracao",
        string $title = "",
        string $body = "",
        int $id = 0,
        string $submitLabel = "Salvar modelo",
        string $cancelHref = "",
    ): string 
    {
    
        if (!$typeOptions) {
            $typeOptions = \Prontoo\Domain\Documents\DocumentTypePolicy::document_type_options();
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
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("lightbulb") .
            "<span>Como escrever melhor</span></strong><ul><li>Use títulos curtos para separar seções.</li><li>Prefira frases objetivas para leitura na consulta.</li><li>Insira campos automáticos em vez de digitar dados do paciente.</li><li>Confira a pré-visualização antes da emissão definitiva.</li></ul></aside>";
        $identity =
            '<fieldset class="doc-template-author-section doc-template-identity"><legend>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("badge") .
            '<span>Identificação</span></legend><div class="doc-template-fields-grid">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Tipo de documento",
                "type_key",
                $typeOptions,
                $selectedType,
                "required",
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Nome do modelo",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "title",
                    "text",
                    $title,
                    'required maxlength="160" placeholder="Ex.: Receita simples, declaração de comparecimento" autocomplete="off"',
                ),
            ) .
            '</div><p class="doc-template-section-help">O nome deve ajudar a equipe a escolher o modelo certo sem abrir o conteúdo.</p></fieldset>';
        $editor =
            '<fieldset class="doc-template-author-section doc-template-editor-section"><legend>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("article") .
            '<span>Texto do modelo</span></legend><div class="doc-template-editor-field"><span>Conteúdo e formatação</span>' .
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_editor_html("body", $body, $editorId) .
            "</div></fieldset>";
        $actions =
            $cancelHref !== ""
                ? '<div class="form-actions"><a class="ghost" href="' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($cancelHref) .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                    '<span>Cancelar</span></a><button type="submit" class="primary">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_submit_icon($submitLabel)) .
                    "<span>" .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($submitLabel) .
                    "</span></button></div>"
                : \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations04::form_actions($submitLabel);
        return '<form method="post" class="compact doc-form doc-template-author-form" data-doc-template-form>' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="save_template">' .
            $idField .
            '<div class="doc-template-author-head"><div><span class="eyebrow">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("description") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($mode) .
            "</span></span><h3>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($mode) .
            "</h3><p>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($hint) .
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

    public static function document_model_search_card(
        string $modelSearch,
        string $mode = "emit",
    ): string 
    {
    
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
        $clear = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("documents", [$mode === "models" ? "models" : "emit" => 1]);
        return '<section class="card doc-search-primary doc-ds-search ds-search-card patient-search-card doc-model-search-card"><form method="get" class="patient-search-bar doc-model-search" role="search"><input type="hidden" name="r" value="documents">' .
            $hidden .
            '<label class="search-field"><input type="search" name="model_q" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($modelSearch) .
            '" placeholder="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($placeholder) .
            '" aria-label="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            '"></label><button class="primary small" type="submit">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("search") .
            "<span>Busca rápida</span></button>" .
            ($modelSearch !== ""
                ? '<a class="ghost small" href="' .
                    $clear .
                    '">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                    "<span>Limpar</span></a>"
                : "") .
            "</form></section>";
    
    }
}
