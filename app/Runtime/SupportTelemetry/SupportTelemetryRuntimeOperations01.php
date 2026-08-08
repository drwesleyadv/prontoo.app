<?php
declare(strict_types=1);

namespace Prontoo\Runtime\SupportTelemetry;

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

final class SupportTelemetryRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function telemetry_page_request_candidate(string $route): bool
    
    {
        $route = telemetry_route_safe($route);
        if (
            $route === "unknown" ||
            str_starts_with($route, "landing_") ||
            in_array($route, telemetry_page_internal_routes(), true)
        ) {
            return false;
        }
        $method = strtoupper(mb_trim((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")));
        if (!in_array($method, ["GET", "POST"], true)) {
            return false;
        }
        $requestedWith = strtolower(mb_trim((string) ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "")));
        if ($requestedWith !== "") {
            return false;
        }
        $purpose = strtolower(
            mb_trim(
                (string) ($_SERVER["HTTP_PURPOSE"] ?? "") .
                    " " .
                    (string) ($_SERVER["HTTP_SEC_PURPOSE"] ?? ""),
            ),
        );
        if (str_contains($purpose, "prefetch") || str_contains($purpose, "prerender")) {
            return false;
        }
        $mode = strtolower(mb_trim((string) ($_SERVER["HTTP_SEC_FETCH_MODE"] ?? "")));
        $destination = strtolower(mb_trim((string) ($_SERVER["HTTP_SEC_FETCH_DEST"] ?? "")));
        if ($mode !== "" || $destination !== "") {
            if ($mode !== "navigate" || $destination !== "document") {
                return false;
            }
        } elseif ($method !== "GET") {
            return false;
        }
        $accept = strtolower((string) ($_SERVER["HTTP_ACCEPT"] ?? ""));
        if (
            str_contains($accept, "application/json") &&
            !str_contains($accept, "text/html") &&
            !str_contains($accept, "application/xhtml+xml")
        ) {
            return false;
        }
        return true;
    
    }

    public static function telemetry_route_start_marker(
        string $route,
        ?int $startedMonotonicNs = null,
        ?int $startedUnixUs = null,
    ): void 
    {
        if (
            PHP_SAPI === "cli" ||
            !empty($GLOBALS["PRONTOO_TELEMETRY_REGISTERED"]) ||
            !telemetry_page_request_candidate($route)
        ) {
            return;
        }
        $GLOBALS["PRONTOO_TELEMETRY_REGISTERED"] = true;
        $GLOBALS["PRONTOO_ROUTE_NAME"] = telemetry_route_safe($route);
        $GLOBALS["PRONTOO_ROUTE_STARTED_MONOTONIC_NS"] =
            $startedMonotonicNs ?? hrtime(true);
        $GLOBALS["PRONTOO_ROUTE_STARTED_UNIX_US"] =
            $startedUnixUs ?? (int) floor(microtime(true) * 1000000);
        $GLOBALS["PRONTOO_PAGE_LOAD_METHOD"] = strtoupper(
            mb_trim((string) ($_SERVER["REQUEST_METHOD"] ?? "GET")),
        );
        $path = parse_url((string) ($_SERVER["REQUEST_URI"] ?? "/"), PHP_URL_PATH);
        $GLOBALS["PRONTOO_PAGE_LOAD_PATH"] =
            is_string($path) && str_starts_with($path, "/") ? $path : "/";
        register_shutdown_function(
            static function (): void {
                telemetry_route_finish_marker(true);
            },
        );
    
    }

    public static function telemetry_route_finish_marker(bool $shutdownFallback = false): void
    
    {
        static $finished = false;
        if (
            $finished ||
            PHP_SAPI === "cli" ||
            empty($GLOBALS["PRONTOO_TELEMETRY_REGISTERED"])
        ) {
            return;
        }
        $finished = true;
        $finishedMonotonicNs = hrtime(true);
        $finishedUnixUs = (int) floor(microtime(true) * 1000000);
        try {
            $startedMonotonicNs = (int) ($GLOBALS["PRONTOO_ROUTE_STARTED_MONOTONIC_NS"] ?? 0);
            $startedUnixUs = (int) ($GLOBALS["PRONTOO_ROUTE_STARTED_UNIX_US"] ?? 0);
            if ($startedMonotonicNs <= 0 || $startedUnixUs <= 0) {
                return;
            }
            $statusCode = (int) http_response_code();
            if (!telemetry_page_response_candidate($statusCode)) {
                return;
            }
            $fatalError = telemetry_fatal_error(error_get_last());
            $event = telemetry_build_event(
                (string) ($GLOBALS["PRONTOO_ROUTE_NAME"] ?? "unknown"),
                $startedMonotonicNs,
                $finishedMonotonicNs,
                $startedUnixUs,
                $finishedUnixUs,
                $statusCode,
                $fatalError,
                null,
                (string) ($GLOBALS["PRONTOO_PAGE_LOAD_METHOD"] ?? "GET"),
                (string) ($GLOBALS["PRONTOO_PAGE_LOAD_PATH"] ?? "/"),
                $shutdownFallback
                    ? "shutdown_fallback"
                    : "front_controller_last_useful_line",
            );
            if (!telemetry_append_event($event)) {
                error_log("[Prontoo telemetria] Carregamento de página não persistido.");
            }
            telemetry_prune($finishedUnixUs);
        } catch (Throwable $error) {
            error_log("[Prontoo telemetria] " . $error->getMessage());
        }
    
    }

    public static function telemetry_sequence_records_series_20d(?int $nowUnix = null): array
    
    {
        $timezone = telemetry_cuiaba_tz();
        $today = $nowUnix === null
            ? new DateTimeImmutable("today", $timezone)
            : (new DateTimeImmutable("@" . max(0, $nowUnix)))
      ->setTimezone($timezone)
      ->setTime(0, 0);
        $days = [];
        $select = [];
        $params = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = $today->modify("-" . $i . " days");
            $next = $day->modify("+1 day");
            $key = $day->format("Y-m-d");
            $days[$key] = [
      "key" => $key,
      "label" => telemetry_day_axis_label($day),
      "tooltip" => $day->format("d/m/Y"),
      "value" => 0,
            ];
            $select[] =
      "SUM(CASE WHEN created_at>=? AND created_at<? AND status='committed' THEN mutation_count ELSE 0 END) AS d" .
      (29 - $i);
            $params[] = $day->getTimestamp();
            $params[] = $next->getTimestamp();
        }
        if (
            !function_exists("has_cfg") ||
            !has_cfg() ||
            !function_exists("db_table_exists") ||
            !db_table_exists("pi_action_ledger")
        ) {
            return array_values($days);
        }
        try {
            $sql =
      "SELECT " .
      implode(",", $select) .
      " FROM pi_action_ledger WHERE created_at>=? AND created_at<?";
            $first = array_key_first($days);
            $last = array_key_last($days);
            $params[] = new DateTimeImmutable(
      $first . " 00:00:00",
      $timezone,
            )->getTimestamp();
            $params[] = (new DateTimeImmutable(
      $last . " 00:00:00",
      $timezone,
            ))
      ->modify("+1 day")
      ->getTimestamp();
            $row = q($sql, $params)->fetch() ?: [];
            $index = 0;
            foreach ($days as &$day) {
      $day["value"] = max(0, (int) ($row["d" . $index] ?? 0));
      $index++;
            }
            unset($day);
        } catch (Throwable $error) {
            error_log(
      "[Prontoo login telemetry records] " .
          $error->getMessage(),
            );
        }
        return array_values($days);
    
    }
}
