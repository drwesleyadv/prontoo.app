<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\Appointments;

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
            $activeRole !== "" ? role_label_for($activeRole, $cid) : "meu cargo";
        $scopeOptions = ["my_role" => "Meu Cargo", "clinic" => "Toda Clínica"];
        $roleOptions = $roleOptions ?: clinic_role_options($cid, false);
        $back = href("appointments", ["d" => $day]);
        $returnHidden =
            '<input type="hidden" name="return_day" value="' .
            e($day) .
            '"><input type="hidden" name="return_doctor" value="' .
            (int) ($_GET["doctor"] ?? 0) .
            '">';
        $hero =
            '<div class="agenda-quick-hero agenda-note-hero"><span class="agenda-quick-hero-icon" aria-hidden="true">' .
            icon("sticky_note_2") .
            '</span><div class="agenda-quick-hero-copy"><h2>Nova Anotação</h2><p>Registre uma orientação discreta para a data escolhida na Agenda.</p></div><a class="ghost small agenda-quick-back" href="' .
            $back .
            '">' .
            icon("arrow_back") .
            "<span>Agenda</span></a></div>";
        $summary =
            '<div class="agenda-quick-summary agenda-note-summary" aria-label="Resumo da anotação"><span>' .
            icon("calendar_month") .
            "<b>" .
            e(date_br($day)) .
            "</b><small>Data da Agenda</small></span><span>" .
            icon("visibility") .
            "<b>Acesso controlado</b><small>Rodapé da anotação</small></span></div>";
        $visibility =
            '<footer class="agenda-note-visibility"><div class="agenda-note-visibility-title">' .
            icon("visibility") .
            '<span>Quem pode ver</span></div><div class="agenda-note-visibility-grid agenda-note-visibility-grid--simple">' .
            select_label(
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
            csrf_field() .
            $returnHidden .
            '<input type="hidden" name="act" value="agenda_note"><fieldset class="agenda-quick-section agenda-note-content"><legend>' .
            icon("sticky_note_2") .
            "<span>Conteúdo</span></legend>" .
            form_row("Data", input("note_date", "date", $day, "required")) .
            form_row(
                "Conteúdo",
                textarea(
                    "content",
                    "",
                    'required maxlength="4000" rows="8" placeholder="Escreva a anotação para esta data."',
                ),
            ) .
            $visibility .
            '</fieldset><div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
            $back .
            '">' .
            icon("close") .
            '<span>Desistir</span></a><button type="submit" class="primary">' .
            icon("save") .
            "<span>Salvar anotação</span></button></div></form></section>";
    
    }
}
