<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Legacy\Patients;

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

final class PatientsPresentationOperations01
{
    private function __construct()
    {
    }

    public static function posted_patient_search_value(): string
    
    {
    
        foreach ($_POST as $k => $v) {
            if (is_string($k) && str_starts_with($k, "patient_search")) {
                return mb_trim((string) $v);
            }
        }
        return mb_trim((string) ($_POST["patient_search"] ?? ""));
    
    }

    public static function patient_reception_meta_chips_html(array $parts): string
    
    {
    
        $icons = [
            "Telefone" => "call",
            "Origem" => "conversion_path",
            "Interesse" => "interests",
            "Etapa" => "route",
        ];
        $classes = [
            "Telefone" => "phone",
            "Origem" => "source",
            "Interesse" => "interest",
            "Etapa" => "stage",
        ];
        $h = "";
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }
            $label = mb_trim((string) ($part["label"] ?? ""));
            $value = mb_trim((string) ($part["value"] ?? ""));
            if ($label === "" || $value === "") {
                continue;
            }
            $iconName = $icons[$label] ?? "label";
            $class = preg_replace(
                "/[^a-z0-9_-]/i",
                "",
                (string) ($classes[$label] ?? strtolower($label)),
            );
            $h .=
                '<span class="patient-reception-tag patient-reception-tag-' .
                e($class) .
                '" aria-label="' .
                e($label . ": " . $value) .
                '" title="' .
                e($label . ": " . $value) .
                '">' .
                icon($iconName) .
                "<b>" .
                e($value) .
                "</b></span>";
        }
        return $h !== ""
            ? '<div class="patient-reception-tags" aria-label="Detalhes do atendimento">' .
                    $h .
                    "</div>"
            : "";
    
    }

    public static function patient_reception_history_panel(array $items): string
    
    {
    
        $count = count($items);
        return '<section class="patient-panel patient-panel-atendimentos" role="tabpanel"><div class="patient-section-title"><div><h2>Atendimentos</h2><p>Histórico de ocorrências registradas pela recepção antes ou durante o vínculo com o paciente.</p></div><span>' .
            (int) $count .
            ' ocorrência(s)</span></div><div class="patient-reception-history">' .
            ($items
                ? timeline($items, "")
                : '<div class="empty patient-empty-cta"><span class="empty-icon">' .
                    icon("forum") .
                    "</span><h3>Sem ocorrências registradas.</h3><p>Paciente cadastrado diretamente ou ainda sem contato registrado pela recepção. Quando o telefone aparecer em novo contato, a ficha será localizada automaticamente.</p></div>") .
            "</div></section>";
    
    }
}
