<?php
declare(strict_types=1);

namespace Prontoo\Runtime\AuditActivity;

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

final class AuditActivityRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function audit(
        string $event,
        ?string $entity = null,
        mixed $entityId = null,
        array $context = [],
        ?array $trustedOrigin = null,
    ): bool 
    {
    
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg() || !\Prontoo\Domain\AuditActivity\AuditWritePolicy::audit_should_write($event)) {
            return false;
        }
        try {
            \Prontoo\Runtime\Operational\OperationalComposition::administration()->atomic(function () use (
                $event,
                $entity,
                $entityId,
                $context,
                $trustedOrigin,
            ): void {
    
                $origin = \Prontoo\Domain\AuditActivity\AuditWritePolicy::audit_trusted_origin_resolve(
                    $context,
                    $trustedOrigin,
                );
                $skipRuntimeContext = (bool) $origin["skip_runtime_context"];
                $skipContextEnrichment = (bool) $origin[
                    "skip_context_enrichment"
                ];
                $hasForcedUser = (bool) $origin["has_user_id"];
                $forcedUserId = (int) $origin["user_id"];
                $hasForcedIpHash = (bool) $origin["has_ip_hash"];
                $forcedIpHash = (string) $origin["ip_hash"];
                $hasForcedUserAgent = (bool) $origin["has_user_agent"];
                $forcedUserAgent = (string) $origin["user_agent"];
                $forcedCreatedAt = (string) $origin["created_at"];
                $forcedProofContext = $origin["proof_context"];
                $c = $skipRuntimeContext ? [] : \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
                if ($forcedProofContext === null) {
                    $forcedProofContext = [
                        "route" => is_callable([\Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::class, 'route']) ? (string) \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route() : "",
                        "method" => (string) ($_SERVER["REQUEST_METHOD"] ?? ""),
                        "scope" => (string) ($c["scope"] ?? ""),
                        "clinic_id" => $c["clinic_id"] ?? null,
                        "role" => (string) ($c["role"] ?? ""),
                    ];
                }
                $uid = $hasForcedUser
                    ? ($forcedUserId > 0 ? $forcedUserId : null)
                    : ((int) ($c["user"]["id"] ?? ($_SESSION["uid"] ?? 0)) ?:
                        null);
                $cid = $context["clinic_id"] ?? ($c["clinic_id"] ?? null);
                unset($context["clinic_id"]);
                if (($c["scope"] ?? "") === "clinic") {
                    if (empty($context["role_code"]) && !empty($c["role"])) {
                        $context["role_code"] = (string) $c["role"];
                    }
                    if (
                        empty($context["environment_label"]) &&
                        !empty($c["role"]) &&
                        is_callable([\Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::class, 'role_label_for'])
                    ) {
                        $context["environment_label"] = \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for(
                            (string) $c["role"],
                            $cid ? (int) $cid : null,
                        );
                    }
                }
                if (!$skipContextEnrichment) {
                    $context = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_enrich_context(
                        $event,
                        $entity,
                        $entityId,
                        $context,
                        $cid ? (int) $cid : null,
                    );
                }
                if (mb_trim((string) ($context["audit_body"] ?? "")) === "") {
                    $context["audit_body"] = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_body_for_event(
                        $event,
                        $entity,
                        $entityId,
                        $context,
                    );
                }
                $friendly = mb_substr(
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit_friendly($event, $entity, $entityId, $context, $uid),
                    0,
                    255,
                );
                $json = json_encode(
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::mask($context),
                    JSON_UNESCAPED_UNICODE |
                        JSON_HEX_TAG |
                        JSON_HEX_APOS |
                        JSON_HEX_AMP |
                        JSON_HEX_QUOT,
                );
                if ($json === false) {
                    $json = "{}";
                }
                $eventLabel = \Prontoo\Domain\AuditActivity\AuditActivityDomainOperations03::event_label($event);
                $eventIcon = \Prontoo\Domain\AuditActivity\AuditActivityDomainOperations03::event_icon($event);
                $entityLabel = $entity !== null && $entity !== "" ? \Prontoo\Domain\AuditActivity\AuditActivityDomainOperations03::entity_label($entity) : null;
                $ip = substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45);
                $ipHash = $hasForcedIpHash
                    ? ($forcedIpHash !== "" ? $forcedIpHash : null)
                    : ($ip !== "" ? hash("sha256", $ip . "|ip") : null);
                $userAgent = $hasForcedUserAgent
                    ? $forcedUserAgent
                    : mb_substr(mb_trim((string) ($_SERVER["HTTP_USER_AGENT"] ?? "")), 0, 180);
                if ($userAgent === "") {
                    $userAgent = null;
                }
                $row = [
                    "clinic_id" => $cid,
                    "user_id" => $uid,
                    "event_key" => $event,
                    "entity_key" => $entity,
                    "entity_id" => (string) $entityId,
                    "friendly_text" => $friendly,
                    "context_json" => $json,
                ];
                $proof = \Prontoo\Infrastructure\Audit\AuditChain::build(
                    $row,
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key(),
                    $forcedProofContext,
                );
                \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.audit_activity.04.audit.01', [
                        $cid,
                        $uid,
                        $event,
                        $eventLabel,
                        $eventIcon,
                        $entity,
                        $entityLabel,
                        (string) $entityId,
                        $friendly,
                        $json,
                        $proof["integrity_hash"],
                        $proof["previous_hash"],
                        $proof["chain_hash"],
                        $proof["proof_hash"],
                        $proof["proof_json"],
                        $proof["policy_version"],
                        $ipHash,
                        $userAgent,
                        $forcedCreatedAt !== "" ? $forcedCreatedAt : null,
                    ], []);
            });
            return true;
        } catch (Throwable $e) {
            error_log("[Prontoo audit] " . $e->getMessage());
            return false;
        }
    
    }

    public static function audit_actor_name(array $ctx, ?int $uid): string
    
    {
    
        $actor = mb_trim((string) ($ctx["actor_name"] ?? ""));
        return $actor !== "" ? \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::first_name($actor) : \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations01::user_name_by_id($uid);
    
    }

    public static function audit_friendly(
        string $event,
        ?string $entity,
        mixed $entityId,
        array $ctx,
        ?int $uid,
    ): string 
    {
    
        return \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations02::activity_direct_title($event, $entity, $entityId, $ctx, $uid);
    
    }

    public static function audit_visibility(array $c): array
    
    {
    
        $cid = (int) $c["clinic_id"];
        $role = (string) $c["role"];
        $uid = (int) $c["user"]["id"];
        if ($role === "gerente") {
            return ["mode" => "clinic", "user_ids" => []];
        }
        if ($role === "medico") {
            $ids = array_unique(
                array_merge(
                    [$uid],
                    \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::team_user_ids_for_roles($cid, ["assistente", "recepcionista"]),
                ),
            );
            return ["mode" => $role, "user_ids" => array_values($ids)];
        }
        if ($role === "assistente") {
            $ids = array_unique(
                array_merge(
                    [$uid],
                    \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::team_user_ids_for_roles($cid, ["recepcionista"]),
                ),
            );
            return ["mode" => $role, "user_ids" => array_values($ids)];
        }
        if ($role === "recepcionista") {
            $ids = array_unique(
                array_merge(
                    [$uid],
                    \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations02::team_user_ids_for_roles($cid, ["recepcionista"]),
                ),
            );
            return ["mode" => $role, "user_ids" => array_values($ids)];
        }
        return ["mode" => "clinic", "user_ids" => []];
    
    }

    public static function recent_events(int $cid, int $uid, string $role): array
    
    {
    
        $rows = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_rows_light(["scope" => "clinic"], [$cid], 12);
        return \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations02::audit_items($rows);
    
    }

    public static function audit_preview_for_appointment(int $cid, int $appointmentId): string
    
    {
    
        $rows = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations01::audit_rows_light(
            ["scope" => "appointment"],
            [$cid, "consulta", (string) $appointmentId],
            3,
        );
        $items = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations02::audit_items($rows);
        if (!$items) {
            return '<div class="audit-mini compact-empty">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("info") .
                "<span>Nenhum evento de atividade vinculado.</span></div>";
        }
        $h = '<div class="audit-mini compact-audit">';
        foreach ($items as $it) {
            $h .=
                "<div><span>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($it["icon"] ?? "radio_button_checked") .
                "</span><p><b>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($it["title"] ?? "")) .
                "</b><small>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) ($it["body"] ?? "")) .
                "</small></p></div>";
        }
        return $h . "</div>";
    
    }

    public static function audit_team_filter_options(int $cid): array
    
    {
    
        if ($cid <= 0) {
            return [];
        }
        $loader = static function () use ($cid): array {
    
            try {
                $rows = \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.audit_activity.04.audit_team_filter_options.01', [$cid], [])->fetchAll();
            } catch (Throwable $e) {
                error_log("[Prontoo audit team lookup] " . $e->getMessage());
                return [];
            }
            $out = [];
            foreach ($rows as $r) {
                $id = (int) ($r["id"] ?? 0);
                $name = mb_trim((string) ($r["name"] ?? ""));
                if ($id <= 0 || $name === "") {
                    continue;
                }
                $out[$id] = preg_split("/\s+/u", $name)[0] ?? $name;
            }
            return $out;
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember']) && \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_read_allowed()) {
            return (array) \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "lookup",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("audit_team", $cid),
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_ttl("lookup"),
                $loader,
                ["table:pi_users", "table:pi_user_roles", "scope:" . $cid],
            );
        }
        return $loader();
    
    }

    public static function audit_activity_day_name(
        string $day,
        int $cid = 0,
        ?array $c = null,
    ): string 
    {
    
        $labels = [
            "Domingo",
            "Segunda",
            "Terça",
            "Quarta",
            "Quinta",
            "Sexta",
            "Sábado",
        ];
        try {
            $zone = new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone($c, $cid));
            $dt = new DateTimeImmutable($day . " 12:00:00", $zone);
            return $labels[(int) $dt->format("w")] ?? "Dia";
        } catch (Throwable $e) {
            $ts = strtotime($day . " 12:00:00");
            return $labels[(int) date("w", $ts ?: time())] ?? "Dia";
        }
    
    }

    public static function audit_period_options(int $cid = 0, ?array $c = null): array
    
    {
    
        $today = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c);
        $before = date("Y-m-d", strtotime($today . " -2 days"));
        return [
            "today" => "Hoje",
            "yesterday" => "Ontem",
            "before_yesterday" => \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit_activity_day_name($before, $cid, $c),
            "date" => "Escolher data",
        ];
    
    }

    public static function audit_period_range(
        string $period,
        int $cid,
        array $c,
        ?string $selectedDate = null,
    ): array
    {
    
        $opts = \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit_period_options($cid, $c);
        if (!isset($opts[$period])) {
            $period = "today";
        }
        $today = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($cid, $c);
        if ($period === "yesterday") {
            $day = date("Y-m-d", strtotime($today . " -1 day"));
        } elseif ($period === "before_yesterday") {
            $day = date("Y-m-d", strtotime($today . " -2 days"));
        } elseif (
            $period === "date" &&
            is_string($selectedDate) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)
        ) {
            $day = $selectedDate;
        } else {
            $day = $today;
        }
        [$start, $end] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($day, $cid, $c);
        return [
            gmdate("Y-m-d H:i:s", (int) $start),
            gmdate("Y-m-d H:i:s", (int) $end),
        ];
    
    }

    public static function audit_activity_url(array $extra = []): string
    
    {
    
        $base = ["r" => "audit"];
        foreach (["member", "period", "date"] as $k) {
            if (isset($_GET[$k]) && mb_trim((string) $_GET[$k]) !== "") {
                $base[$k] = (string) $_GET[$k];
            }
        }
        return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("audit", array_merge($base, $extra));
    
    }
}
