<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\UiComponents;

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

final class UiComponentsRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function page(string $title, string $body, array $opts = []): void
    
    {
    
        $public = $opts["public"] ?? false;
        $current = route();
        $c = $public ? [] : ctx();
        if (
            PRONTOO_AUDIT_PAGE_VIEWS &&
            function_exists("audit") &&
            $c &&
            !$public &&
            ($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET"
        ) {
            $auditEntity = "janela";
            $auditId = $current;
            $auditCtx = ["titulo" => $title];
            if (
                $current === "patient" &&
                (int) ($_GET["id"] ?? 0) > 0 &&
                ($c["scope"] ?? "") === "clinic"
            ) {
                $patientId = (int) $_GET["id"];
                $patientName = function_exists("audit_patient_name_by_link")
                    ? audit_patient_name_by_link($patientId, (int) $c["clinic_id"])
                    : "";
                $auditEntity = "paciente";
                $auditId = $patientId;
                $auditCtx = [
                    "titulo" => $title,
                    "janela" => "Ficha do paciente",
                    "patient_link_id" => $patientId,
                ];
                if ($patientName !== "") {
                    $auditCtx["patient_name"] = $patientName;
                }
            }
            audit("janela_aberta", $auditEntity, $auditId, $auditCtx);
        }
        $visual = function_exists("clinic_visual")
            ? clinic_visual(
                $c && ($c["scope"] ?? "") === "clinic" ? (int) $c["clinic_id"] : 0,
                $c ?: [],
            )
            : [
                "icon" => "medical_services",
                "brand" => "#334155",
                "brand_dark" => "#1f2937",
                "brand_soft" => "#f1f5f9",
                "brand_soft_2" => "#f8fafc",
                "on_brand" => "#ffffff",
                "favicon" =>
                    "/public/assets/favicon-" .
                    rawurlencode(PRONTOO_ASSET_REV) .
                    ".png",
                "css_vars" =>
                    "--clinic-accent:#334155;--clinic-accent-dark:#1f2937;",
            ];
        if ($public && in_array($current, ["login", "signup", "mfa"], true)) {
            $visual["brand"] = "#238763";
            $visual["brand_dark"] = "#105e44";
            $visual["brand_soft"] = "#dff3ea";
            $visual["brand_soft_2"] = "#f1fbf6";
            $visual["on_brand"] = "#ffffff";
            $visual["css_vars"] =
                "--clinic-accent:#238763;--clinic-accent-dark:#105e44;--clinic-accent-soft:#dff3ea;--clinic-on-accent:#ffffff;";
        }
        if (!$public && $c && ($c["scope"] ?? "") === "global") {
            $visual["icon"] = "admin_panel_settings";
            $visual["brand"] = "#238763";
            $visual["brand_dark"] = "#105e44";
            $visual["brand_soft"] = "#dff3ea";
            $visual["brand_soft_2"] = "#f1fbf6";
            $visual["on_brand"] = "#ffffff";
            $visual["css_vars"] =
                "--clinic-accent:#238763;--clinic-accent-dark:#105e44;--clinic-accent-soft:#dff3ea;--clinic-on-accent:#ffffff;";
        }
        if (
            !$public &&
            $c &&
            ($c["scope"] ?? "") === "clinic" &&
            !empty(($c["billing"] ?? [])["read_only"])
        ) {
            $visual["brand"] = "#333333";
            $visual["brand_dark"] = "rgba(0,0,0,.90)";
            $visual["brand_soft"] = "rgba(0,0,0,.10)";
            $visual["brand_soft_2"] = "rgba(0,0,0,.05)";
            $visual["on_brand"] = "#ffffff";
            $visual["css_vars"] .=
                "--clinic-readonly-accent:rgba(0,0,0,.80);--md-ref-palette-primary40:rgba(0,0,0,.80);--md-ref-palette-primary30:rgba(0,0,0,.90);--clinic-accent:rgba(0,0,0,.80);--clinic-accent-rgb:0,0,0;--clinic-accent-strong:rgba(0,0,0,.90);--clinic-accent-hover:rgba(0,0,0,.86);--clinic-accent-pressed:rgba(0,0,0,.72);--clinic-accent-soft:rgba(0,0,0,.10);--clinic-accent-subtle:rgba(0,0,0,.05);--clinic-accent-muted:rgba(0,0,0,.36);--clinic-accent-border:rgba(0,0,0,.26);--clinic-accent-focus:rgba(0,0,0,.22);--clinic-accent-shadow:rgba(0,0,0,.20);--clinic-on-accent:#ffffff;--clinic-clock-bg:rgba(0,0,0,.80);--clinic-clock-border:rgba(0,0,0,.38);--clinic-clock-ink:#ffffff;--brand:rgba(0,0,0,.80);--brand-rgb:0,0,0;--brand-dark:rgba(0,0,0,.90);--brand-soft:rgba(0,0,0,.10);--brand-soft-2:rgba(0,0,0,.05);--brand-border:rgba(0,0,0,.26);--brand-focus:rgba(0,0,0,.22);--brand-shadow:rgba(0,0,0,.20);--on-brand:#ffffff;--md-sys-color-primary:rgba(0,0,0,.80);--md-sys-color-on-primary:#ffffff;--md-sys-color-primary-container:rgba(0,0,0,.10);--md-sys-color-on-primary-container:#111827;--md-sys-color-secondary:rgba(0,0,0,.86);--md-sys-color-on-secondary:#ffffff;--md-sys-color-secondary-container:rgba(0,0,0,.08);--md-sys-color-on-secondary-container:#1f2937;--md-sys-color-tertiary:rgba(0,0,0,.80);--md-sys-color-on-tertiary:#ffffff;--md-sys-color-tertiary-container:rgba(0,0,0,.10);--md-sys-color-on-tertiary-container:#111827;--md-sys-color-inverse-primary:rgba(0,0,0,.54);--md-sys-state-hover:rgba(0,0,0,.07);--md-sys-state-focus:rgba(0,0,0,.12);--md-sys-state-pressed:rgba(0,0,0,.14);";
        }
        $appName =
            $c && ($c["scope"] ?? "") === "clinic"
                ? mb_trim((string) ($c["clinic"] ?? ""))
                : (($c["scope"] ?? "") === "global"
                    ? "Desenvolvedor Prontoo"
                    : PRONTOO_NAME);
        if ($appName === "") {
            $appName = PRONTOO_NAME;
        }
        $brandIconHtml =
            $c && ($c["scope"] ?? "") === "clinic"
                ? '<span class="brand-mark clinic-brandmark-inline" title="Ícone do consultório">' .
                    icon((string) ($visual["icon"] ?? "home_health")) .
                    "</span>"
                : '<span class="brand-mark app-brandmark-inline" data-app-brandmark><img class="brand-mark-img app-brandmark-img" src="/public/assets/app-icon-' .
                    e(PRONTOO_ASSET_REV) .
                    '.png" alt="" aria-hidden="true"></span>';
        $brandVersionHtml = "";
        if ($c && ($c["scope"] ?? "") === "global") {
            $publishedVersion = defined("PRONTOO_VERSION")
                ? (string) PRONTOO_VERSION
                : "";
            $brandVersionHtml =
                '<small class="pagehead-version">Versão: ' .
                e($publishedVersion) .
                "</small>";
        }
        $brand =
            '<a class="brand" href="' .
            href(
                $c && ($c["scope"] ?? "") === "global"
                    ? "admin_painel"
                    : "appointments",
            ) .
            '" aria-label="' .
            e($appName) .
            '">' .
            $brandIconHtml .
            '<span class="brand-copy"><b>' .
            e($appName) .
            "</b>" .
            $brandVersionHtml .
            "</span></a>";
        $nav = "";
        $topCenter = "";
        if ($c && $c["scope"] === "global") {
            $cmd = "";
            $adminItems = cmdbar_order_items(
                PRONTOO_ADMIN_ACTIONS,
                $current,
                $c,
                true,
            );
            foreach ($adminItems as $key => $a) {
                $adminParent = function_exists("admin_nav_parent")
                    ? admin_nav_parent($current)
                    : $current;
                $isActive = $adminParent === $key;
                $active = $isActive ? " active" : "";
                $extra = "";
                $labelHtml = cmdbar_label_html(
                    (string) $a["label"],
                    $isActive,
                    $extra,
                );
                $adminIcon = function_exists("prontoo_icon_for_route_label")
                    ? prontoo_icon_for_route_label(
                        (string) $key,
                        (string) $a["label"],
                        [],
                        (string) $a["icon"],
                    )
                    : (string) $a["icon"];
                $cmd .=
                    '<a class="cmd' .
                    $active .
                    '" href="' .
                    href($key) .
                    '" title="' .
                    e($a["label"]) .
                    '" aria-label="' .
                    e($a["label"]) .
                    '"' .
                    ($active ? ' aria-current="page"' : "") .
                    ">" .
                    icon($adminIcon) .
                    $labelHtml .
                    "</a>";
            }
            $topCenter = "";
            $nav =
                '<nav class="cmdbar contextsbar" aria-label="Contextos">' .
                $cmd .
                "</nav>";
        } elseif ($c) {
            $roleCode = (string) ($c["role"] ?? "");
            $effectiveRoles = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            "strval",
                            (array) ($c["effective_roles"] ?? [$roleCode]),
                        ),
                    ),
                ),
            );
            $topCenter = "";
            $tabs =
                '<a class="role active" href="' .
                href("switch") .
                '" aria-current="true">' .
                icon(role_icon($roleCode, (int) $c["clinic_id"])) .
                e(role_label_for($roleCode, (int) $c["clinic_id"])) .
                "</a>";
            $visibleActions = [];
            foreach (role_actions_effective($effectiveRoles) as $key => $a) {
                if (can($key)) {
                    $visibleActions[$key] = $a;
                }
            }
            if (has_effective_role($c, "gerente")) {
                unset($visibleActions["painel"]);
            }
            $visibleActions = cmdbar_order_items(
                $visibleActions,
                $current,
                $c,
                false,
            );
            $activeKey = cmdbar_parent_key($current, $visibleActions, false);
            $acts = "";
            foreach ($visibleActions as $key => $a) {
                $isActive = $activeKey === $key;
                $active = $isActive ? " active" : "";
                $navLabel =
                    $key === "financial" && $roleCode === "recepcionista"
                        ? "Caixa"
                        : (string) $a["label"];
                $navIcon =
                    $key === "financial" && $roleCode === "recepcionista"
                        ? reception_cash_state_icon($c)
                        : (string) $a["icon"];
                if (function_exists("prontoo_icon_for_route_label")) {
                    $navIcon = prontoo_icon_for_route_label(
                        (string) $key,
                        $navLabel,
                        [],
                        $navIcon,
                    );
                }
                $extra = "";
                $labelHtml = cmdbar_label_html($navLabel, $isActive, $extra);
                $navHref = $key === "patients" ? href("patients") : href($key);
                $acts .=
                    '<a class="cmd' .
                    $active .
                    '" href="' .
                    $navHref .
                    '" title="' .
                    e($navLabel) .
                    '" aria-label="' .
                    e($navLabel) .
                    '"' .
                    ($active ? ' aria-current="page"' : "") .
                    ">" .
                    icon($navIcon) .
                    $labelHtml .
                    "</a>";
            }
            $goal = shared_goal_cmdbar_html($c);
            $nav =
                '<nav class="cmdbar contextsbar" aria-label="Contextos">' .
                $acts .
                $goal .
                "</nav>";
        }
        $flash = flash();
        $flashHtml = $flash
            ? '<div class="flash ' .
                $flash[0] .
                '" role="' .
                ($flash[0] === "bad" ? "alert" : "status") .
                '">' .
                e($flash[1]) .
                "</div>"
            : "";
        $logout = "";
        if ($c) {
            $loggedFirstName = first_name((string) ($c["user"]["name"] ?? ""));
            $loggedEnvironment =
                ($c["scope"] ?? "") === "global"
                    ? "Desenvolvedor"
                    : role_label_for(
                        (string) ($c["role"] ?? ""),
                        (int) ($c["clinic_id"] ?? 0),
                    );
            $loggedContextLabel =
                trim($loggedFirstName . " em " . $loggedEnvironment);
            $notifyButton = function_exists("notification_button")
                ? notification_button($c)
                : notification_button_light($c);
            $accountIcon =
                ($c["scope"] ?? "") === "global"
                    ? "admin_panel_settings"
                    : role_icon(
                        (string) ($c["role"] ?? ""),
                        (int) ($c["clinic_id"] ?? 0),
                    );
            $logout =
                '<a class="top-user-name top-account" href="' .
                href("profile") .
                '" title="' .
                e($loggedContextLabel) .
                '" aria-label="' .
                e($loggedContextLabel) .
                '"><span>' .
                e($loggedFirstName) .
                "</span>" .
                icon($accountIcon) .
                "</a>" .
                $notifyButton .
                '<form method="post" action="' .
                href("logout") .
                '" class="logout">' .
                csrf_field() .
                '<button type="submit" class="top-icon logout-icon" aria-label="Sair" title="Sair">' .
                icon("logout") .
                "</button></form>";
        } else {
            $logout =
                '<a class="ghost" href="' .
                href("login") .
                '">Entrar</a>' .
                ($current === "signup" ||
                (function_exists("clinic_signup_blocked") && clinic_signup_blocked())
                    ? ""
                    : '<a class="primary" href="' .
                        href("signup") .
                        '">Criar consultório</a>');
        }
        $clock = "";
        $pendingFloating = "";
        if ($c && !$public) {
            $suppressFloatingCards =
                $current === "appointments" &&
                (string) ($_GET["view"] ?? "diario") === "mensal";
            $pendingFloating =
                !$suppressFloatingCards &&
                function_exists("floating_pending_cards_html")
                    ? floating_pending_cards_html($c, $current)
                    : "";
            if ($pendingFloating !== "") {
                $clock = $pendingFloating;
            } elseif ($current !== "appointments") {
                $tz = app_timezone_safe(
                    (string) ($c["timezone"] ?? "America/Cuiaba"),
                );
                $clock =
                    '<aside class="floating-clock agenda-floating-card context-floating-clock-card" data-floating-clock data-clock-tz="' .
                    e($tz) .
                    '" aria-label="Horário atual"><span class="agenda-floating-icon" aria-hidden="true">' .
                    icon("schedule") .
                    '</span><div class="agenda-floating-copy"><b data-clock>--:--</b></div></aside>';
            }
        }
        $installCta = "";
        $bodyClass = ($public ? "public" : "app") . " has-top-shell";
        if ($pendingFloating !== "") {
            $bodyClass .= " has-floating-pending";
        }
        if (!$public && $c) {
            $bodyClass .=
                " scope-" .
                preg_replace(
                    "/[^a-z0-9_-]+/i",
                    "-",
                    (string) ($c["scope"] ?? "clinic"),
                );
            if (($c["scope"] ?? "") !== "global") {
                $bodyClass .=
                    " role-" .
                    preg_replace(
                        "/[^a-z0-9_-]+/i",
                        "-",
                        (string) ($c["role"] ?? "usuario"),
                    );
                if (!empty(($c["billing"] ?? [])["read_only"])) {
                    $bodyClass .= " is-read-only";
                }
            }
        }
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="canonical" href="https://prontoo.app/"><title>' .
            e($title) .
            " · " .
            e($appName) .
            '</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="' .
            e($visual["brand"]) .
            '"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="application-name" content="' .
            e($appName) .
            '"><meta name="prontoo-version" content="' .
            e(PRONTOO_VERSION) .
            '"><meta name="csrf-token" content="' .
            e(csrf()) .
            '"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" type="image/png" href="/public/assets/favicon-' .
            rawurlencode(PRONTOO_ASSET_REV) .
            '.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,-25..200&display=swap" rel="stylesheet"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
            rawurlencode(
                defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
            ) .
            '&release=' .
            rawurlencode(PRONTOO_VERSION) .
            '"><script defer src="/public/assets/app.js?v=' .
            rawurlencode(
                defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
            ) .
            '&release=' .
            rawurlencode(PRONTOO_VERSION) .
            '&ui=design-system-global-enxuto"></script></head><body class="' .
            e($bodyClass) .
            '" style="' .
            e($visual["css_vars"]) .
            '" data-route="' .
            e($current) .
            '" data-app-version="' .
            e(PRONTOO_VERSION) .
            '"><a class="skip-link" href="#conteudo">Pular para o conteúdo</a><header class="top">' .
            $brand .
            $topCenter .
            '<div class="top-actions">' .
            $logout .
            "</div></header>" .
            $nav .
            '<main id="conteudo" tabindex="-1">' .
            $flashHtml .
            billing_notice($c) .
            onboarding_tip_html($c, $current) .
            $body .
            "</main>" .
            $clock .
            "</body></html>";
    
    }

    public static function operation_link_html(
        string $route,
        string $label,
        string $iconName,
        string $current,
        array $params = [],
    ): string 
    {
    
        if (function_exists("prontoo_icon_for_route_label")) {
            $iconName = prontoo_icon_for_route_label(
                $route,
                $label,
                $params,
                $iconName,
            );
        }
        $active = operation_current_match($route, $params, $current);
        $cls = "operation-chip" . ($active ? " is-active" : "");
        return '<a class="' .
            $cls .
            '" href="' .
            href($route, $params) .
            '" title="' .
            e($label) .
            '"' .
            ($active ? ' aria-current="page"' : "") .
            ">" .
            icon($iconName) .
            "<span>" .
            e($label) .
            "</span></a>";
    
    }
}
