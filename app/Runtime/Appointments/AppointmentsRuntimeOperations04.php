<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Appointments;

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

final class AppointmentsRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function agenda_note_form_html(
        int $cid,
        string $day,
        array $c,
        array $roleOptions,
    ): string 
    {
    
        $activeRole = (string) ($c["role"] ?? "");
        $activeRoleLabel =
            $activeRole !== "" ? \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for($activeRole, $cid) : "meu cargo";
        $scopeOptions = ["my_role" => "Meu Cargo", "clinic" => "Toda Clínica"];
        $roleOptions = $roleOptions ?: \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::clinic_role_options($cid, false);
        $back = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", ["d" => $day]);
        $returnHidden =
            '<input type="hidden" name="return_day" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($day) .
            '"><input type="hidden" name="return_doctor" value="' .
            (int) ($_GET["doctor"] ?? 0) .
            '">';
        $hero =
            '<div class="agenda-quick-hero agenda-note-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("sticky_note_2") .
            '</span><div class="agenda-quick-hero-copy"><h2>Nova Anotação</h2><p>Registre uma orientação discreta para a data escolhida na Agenda.</p></div><a class="ghost small agenda-quick-back" href="' .
            $back .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            "<span>Agenda</span></a></div>";
        $summary =
            '<div class="agenda-quick-summary agenda-note-summary" aria-label="Resumo da anotação"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("calendar_month") .
            "<b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($day)) .
            "</b><small>Data da Agenda</small></span><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("visibility") .
            "<b>Acesso controlado</b><small>Rodapé da anotação</small></span></div>";
        $visibility =
            '<footer class="agenda-note-visibility"><div class="agenda-note-visibility-title">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("visibility") .
            '<span>Quem pode ver</span></div><div class="agenda-note-visibility-grid agenda-note-visibility-grid--simple">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                "Visibilidade",
                "visibility_scope",
                $scopeOptions,
                "my_role",
                "required",
            ) .
            '</div><p class="muted-copy">Em “Meu Cargo”, apenas usuários do seu cargo visualizam. Em “Toda Clínica”, todos os usuários da clínica podem visualizar.</p></footer>';
        return '<section class="form-panel agenda-route-form agenda-note-form-panel">' .
            $hero .
            $summary .
            '<form method="post" class="compact agenda-note-form agenda-structured-form">' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            $returnHidden .
            '<input type="hidden" name="act" value="agenda_note"><fieldset class="agenda-quick-section agenda-note-content"><legend>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("sticky_note_2") .
            "<span>Conteúdo</span></legend>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row("Data", \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("note_date", "date", $day, "required")) .
            '<div class="two agenda-note-time-window">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Horário de início (opcional)",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("note_start_time", "time", "", 'step="300"'),
            ) .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Horário de fim (opcional)",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input("note_end_time", "time", "", 'step="300"'),
            ) .
            '</div><p class="muted-copy">Deixe os dois horários em branco para manter a anotação como dia todo. Se informar um intervalo, a anotação aparecerá posicionada nessa faixa da Agenda diária.</p>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Conteúdo",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                    "content",
                    "",
                    'required maxlength="4000" rows="8" placeholder="Escreva a anotação para esta data."',
                ),
            ) .
            $visibility .
            '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
            $back .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
            '<span>Desistir</span></a><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("save") .
            "<span>Salvar anotação</span></button></div></form></section>";
    
    }
}
