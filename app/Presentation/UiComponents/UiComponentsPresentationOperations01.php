<?php
declare(strict_types=1);

namespace Prontoo\Presentation\UiComponents;

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

final class UiComponentsPresentationOperations01
{
    private function __construct()
    {
    }

    public static function e(mixed $v): string
    
    {
    
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    
    }

    public static function first_name(?string $name): string
    
    {
    
        $p = preg_split("/\s+/", mb_trim((string) $name));
        return $p[0] ?: "Sistema";
    
    }

    public static function icon(string $name): string
    
    {
    
        static $aliases = [
            "auto_awesome" => "auto_awesome",
            "finance_mode" => "payments",
            "settings_suggest" => "tune",
            "database" => "storage",
            "schema" => "storage",
            "php" => "code",
            "folder_managed" => "folder",
            "folder_off" => "folder_off",
            "database_off" => "database_off",
            "playlist_add_check" => "fact_check",
            "request_quote" => "payments",
            "receipt_long" => "receipt_long",
            "account_balance_wallet" => "account_balance_wallet",
            "admin_panel_settings" => "admin_panel_settings",
            "health_metrics" => "health_metrics",
            "patient_list" => "patient_list",
            "person_search" => "person_search",
            "home_health" => "home_health",
            "groups" => "groups",
            "clinical_notes" => "clinical_notes",
        ];
        $name = $aliases[$name] ?? $name;
        return '<span class="material-symbols-rounded pt-icon-glyph" aria-hidden="true">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
            "</span>";
    
    }

    public static function pix_symbol(): string
    
    {
    
        return '<span class="pix-brand pix-brand-mask" aria-hidden="true"></span>';
    
    }

    public static function n(mixed $value): string
    
    {
    
        if (
            is_int($value) ||
            (is_string($value) && preg_match('/^-?\d+$/', $value))
        ) {
            return number_format((int) $value, 0, ",", ".");
        }
        if (is_float($value) || is_numeric($value)) {
            return number_format((float) $value, 0, ",", ".");
        }
        $text = mb_trim((string) $value);
        return $text !== "" ? $text : "0";
    
    }

    public static function money_br(int|float|string|null $cents): string
    
    {
    
        $value = (int) round((float) ($cents ?? 0), 0, \RoundingMode::HalfAwayFromZero);
        $sign = $value < 0 ? "-" : "";
        $value = abs($value);
        return $sign . 'R$ ' . number_format($value / 100, 2, ",", ".");
    
    }

    public static function prontoo_months_br(): array
    
    {
    
        return [
            1 => "Janeiro",
            2 => "Fevereiro",
            3 => "Março",
            4 => "Abril",
            5 => "Maio",
            6 => "Junho",
            7 => "Julho",
            8 => "Agosto",
            9 => "Setembro",
            10 => "Outubro",
            11 => "Novembro",
            12 => "Dezembro",
        ];
    
    }

    public static function city_state_label(?string $city, ?string $uf): string
    
    {
    
        $city = mb_trim((string) ($city ?? ""));
        $uf = strtoupper(mb_trim((string) ($uf ?? "")));
        if ($city === "" && $uf === "") {
            return "";
        }
        if ($city === "") {
            return $uf;
        }
        if ($uf === "") {
            return $city;
        }
        return $city . " - " . $uf;
    
    }

    public static function cmdbar_context_key(array $c): array
    
    {
    
        $uid = (int) ($c["user"]["id"] ?? 0);
        $scope = (string) ($c["scope"] ?? "clinic") === "global" ? "global" : "clinic";
        $clinic = (int) ($c["clinic_id"] ?? 0);
        if ($scope === "global") {
            $clinic = 0;
        }
        $role = $scope === "global" ? "administrador" : (string) ($c["role"] ?? "");
        return [
            $uid,
            $scope,
            max(0, $clinic),
            substr(preg_replace("/[^a-z0-9_\-]/i", "", $role) ?: "", 0, 40),
        ];
    
    }

    public static function cmdbar_parent_key(
        string $route,
        array $actions,
        bool $global = false,
    ): string 
    {
    
        if ($global && is_callable([\Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::class, 'admin_nav_parent'])) {
            $parent = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_nav_parent($route);
            if (isset($actions[$parent])) {
                return $parent;
            }
        }
        if (isset($actions[$route])) {
            return $route;
        }
        $map = [
            "admin_routes" => "admin_performance",
            "admin_stats" => "admin_clinics",
            "admin_operations" => "admin_clinics",
            "admin_onboarding" => "admin_clinics",
            "admin_users" => "admin_clinics",
            "admin_people" => "admin_clinics",
            "admin_payment_proof" => "admin_clinics",
            "admin_global_notices" => "admin_clinics",
            "admin_alerts" => "admin_clinics",
            "admin_audit" => "admin_clinics",
            "admin_maintenance" => "admin_administration",
            "admin_settings" => "admin_administration",
            "painel" => "appointments",
            "patient" => "patients",
            "patient_lookup" => "patients",
            "patient_suggest" => "patients",
            "person_lookup" => "patients",
            "leads" => "patients",
            "lead_lookup" => "patients",
            "lead_patient_lookup" => "patients",
            "creditors" => "patients",
            "documents" => "documents",
            "document_view" => "documents",
            "document_print" => "documents",
            "document_pdf" => "documents",
            "document_pdf_file" => "documents",
            "counterparty_lookup" => "financial",
            "counterparty_suggest" => "financial",
            "goal_status" => "financial",
            "procedures" => "settings",
            "users" => "settings",
            "user" => "settings",
            "permissions" => "settings",
            "operations" => "settings",
            "audit" => "patients",
        ];
        $parent = $map[$route] ?? "";
        return $parent !== "" && isset($actions[$parent]) ? $parent : "";
    
    }

    public static function cmdbar_label_html(
        string $label,
        bool $active,
        string $extra = "",
    ): string 
    {
    
        return $active ? "<span>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) . "</span>" . $extra : "";
    
    }

    public static function context_parent_for_route(string $route, array $c): string
    
    {
    
        if (($c["scope"] ?? "") === "global") {
            if (is_callable([\Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::class, 'admin_nav_parent'])) {
                return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_nav_parent($route);
            }
            $map = [
                "admin_routes" => "admin_performance",
                "admin_stats" => "admin_clinics",
                "admin_operations" => "admin_clinics",
                "admin_onboarding" => "admin_clinics",
                "admin_users" => "admin_clinics",
                "admin_people" => "admin_clinics",
                "admin_payment_proof" => "admin_clinics",
                "admin_global_notices" => "admin_clinics",
                "admin_alerts" => "admin_clinics",
                "admin_audit" => "admin_clinics",
                "admin_maintenance" => "admin_administration",
                "admin_settings" => "admin_administration",
            ];
            return $map[$route] ?? $route;
        }
        $map = [
            "painel" => "appointments",
            "patient" => "patients",
            "patient_lookup" => "patients",
            "patient_suggest" => "patients",
            "person_lookup" => "patients",
            "leads" => "patients",
            "lead_lookup" => "patients",
            "lead_patient_lookup" => "patients",
            "creditors" => "patients",
            "document_view" => "documents",
            "document_print" => "documents",
            "document_pdf" => "documents",
            "document_pdf_file" => "documents",
            "counterparty_lookup" => "financial",
            "counterparty_suggest" => "financial",
            "goal_status" => "financial",
            "procedures" => "settings",
            "users" => "settings",
            "user" => "settings",
            "permissions" => "settings",
            "operations" => "settings",
            "audit" => "patients",
        ];
        return $map[$route] ?? $route;
    
    }

    public static function operation_current_match(
        string $route,
        array $params,
        string $current,
    ): bool 
    {
    
        if ($route !== $current) {
            return false;
        }
        if ($current === "documents") {
            $isModels =
                (string) ($_GET["models"] ?? "") === "1" ||
                (string) ($_GET["new_model"] ?? "") === "1";
            $isEmit = (string) ($_GET["emit"] ?? "") === "1";
            $isDoc = (int) ($_GET["doc"] ?? 0) > 0;
            $isRecent =
                (string) ($_GET["recent"] ?? "") === "1" ||
                mb_trim((string) ($_GET["doc_q"] ?? ($_GET["q"] ?? ""))) !== "" ||
                (!$isModels && !$isEmit && !$isDoc);
            if (isset($params["models"])) {
                return $isModels;
            }
            if (isset($params["recent"])) {
                return $isRecent || $isDoc;
            }
            return !$isModels && !$isEmit;
        }
        if ($current === "appointments" && isset($params["view"])) {
            $want = (string) $params["view"];
            $actual = (string) ($_GET["view"] ?? "diario");
            if ($actual === "") {
                $actual = "diario";
            }
            return $actual === $want;
        }
        if ($current === "admin_alerts") {
            $actualView = (string) ($_GET["view"] ?? "received");
            if (!in_array($actualView, ["received", "sent"], true)) {
                $actualView = "received";
            }
            if (
                isset($params["view"]) &&
                $actualView !== (string) $params["view"]
            ) {
                return false;
            }
            $actualCompose = (string) ($_GET["compose"] ?? "0");
            if (isset($params["compose"])) {
                return $actualCompose === (string) $params["compose"];
            }
            if ($actualCompose === "1") {
                return false;
            }
            return true;
        }
        foreach ($params as $k => $v) {
            if ($k === "tab") {
                if ((string) ($_GET["tab"] ?? "perfil") !== (string) $v) {
                    return false;
                }
                continue;
            }
            if ($k === "d" || $k === "doctor") {
                continue;
            }
            if ((string) ($_GET[$k] ?? "") !== (string) $v) {
                return false;
            }
        }
        return true;
    
    }

    public static function card(string $html, string $class = ""): string
    
    {
    
        return '<section class="card ' . $class . '">' . $html . "</section>";
    
    }

    public static function ds_class(string ...$classes): string
    
    {
    
        $out = [];
        foreach ($classes as $class) {
            foreach (preg_split("/\s+/", trim($class)) ?: [] as $part) {
                if ($part !== "" && !isset($out[$part])) {
                    $out[$part] = true;
                }
            }
        }
        return implode(" ", array_keys($out));
    
    }

    public static function ds_card_class(string $additionalClass = ""): string
    
    {
    
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::ds_class("card", $additionalClass);
    
    }

    public static function ds_search_card_class(string $additionalClass = ""): string
    
    {
    
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::ds_class("ds-search-card", $additionalClass);
    
    }

    public static function ds_filter_list_class(string $additionalClass = ""): string
    
    {
    
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::ds_class("ds-filter-list-block", $additionalClass);
    
    }

    public static function ds_filter_chip_class(
        string $additionalClass = "",
        bool $active = false,
    ): string 
    {
    
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::ds_class(
            "ds-filter-chip",
            $additionalClass,
            $active ? "is-active" : "",
        );
    
    }

    public static function stat_card(
        string $label,
        mixed $value,
        string $iconName,
        string $note = "",
    ): string 
    {
    
        return '<article class="stat-card">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($iconName) .
            "<div><b>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::n($value) .
            "</b><span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span>" .
            ($note ? "<small>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($note) . "</small>" : "") .
            "</div></article>";
    
    }

    public static function form_row(string $label, string $input): string
    
    {
    
        return '<label class="field"><span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span>" .
            $input .
            "</label>";
    
    }

    public static function input(
        string $name,
        string $type = "text",
        mixed $value = "",
        string $extra = "",
    ): string 
    {
    
        $extra = trim($extra);
        if (
            $type === "text" &&
            preg_match('/(^|_)cpf($|_)/i', $name) &&
            !preg_match('/_omitted$/i', $name)
        ) {
            foreach (
                [
                    "data-cpf-mask",
                    "data-cpf-validate",
                    'inputmode="numeric"',
                    'maxlength="14"',
                ]
                as $attr
            ) {
                $key = preg_split("/[= ]/", $attr, 2)[0];
                if (stripos($extra, $key) === false) {
                    $extra = trim($extra . " " . $attr);
                }
            }
        }
        return '<input name="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
            '" type="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($type) .
            '" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $value) .
            '" ' .
            $extra .
            ">";
    
    }

    public static function textarea(string $name, mixed $value = "", string $extra = ""): string
    
    {
    
        return '<textarea name="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
            '" ' .
            $extra .
            ">" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $value) .
            "</textarea>";
    
    }

    public static function select_html(
        string $name,
        array $options,
        mixed $selected = null,
        string $extra = "",
    ): string 
    {
    
        $h = '<select name="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) . '" ' . $extra . ">";
        foreach ($options as $k => $v) {
            $h .=
                '<option value="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $k) .
                '" ' .
                ((string) $k === (string) $selected ? "selected" : "") .
                ">" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($v) .
                "</option>";
        }
        return $h . "</select>";
    
    }

    public static function select_label(
        string $label,
        string $name,
        array $opts,
        mixed $sel = null,
        string $extra = "",
    ): string 
    {
    
        return \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row($label, \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_html($name, $opts, $sel, $extra));
    
    }

    public static function cancel_button(string $label = "Cancelar"): string
    
    {
    
        return '<button type="button" class="ghost" data-close-panel>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
            "<span>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($label) .
            "</span></button>";
    
    }

    public static function timeline(?array $items, string $empty = "Nada por enquanto."): string
    
    {
    
        if (!$items) {
            return '<div class="empty">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($empty) . "</div>";
        }
        $h = '<div class="timeline">';
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $dot = !empty($it["badge"])
                ? '<span class="dot time-dot">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $it["badge"]) .
                    "</span>"
                : '<span class="dot">' .
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($it["icon"] ?? "radio_button_checked") .
                    "</span>";
            $class =
                "titem" .
                (!empty($it["class"])
                    ? " " .
                        preg_replace("/[^a-z0-9_\- ]/i", "", (string) $it["class"])
                    : "");
            $h .=
                '<article class="' .
                $class .
                '">' .
                $dot .
                "<div><time>" .
                (!empty($it["time_html"])
                    ? (string) $it["time_html"]
                    : \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($it["time"] ?? "")) .
                "</time><h3>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($it["title"] ?? "") .
                "</h3>" .
                (!empty($it["body"]) ? "<p>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($it["body"]) . "</p>" : "") .
                (!empty($it["meta"])
                    ? "<small>" . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($it["meta"]) . "</small>"
                    : "") .
                (!empty($it["html"])
                    ? '<div class="t-actions">' . $it["html"] . "</div>"
                    : "") .
                "</div></article>";
        }
        return $h === '<div class="timeline">'
            ? '<div class="empty">' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($empty) . "</div>"
            : $h . "</div>";
    
    }
}
