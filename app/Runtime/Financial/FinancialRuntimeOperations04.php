<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Financial;

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

final class FinancialRuntimeOperations04
{
    private function __construct()
    {
    }

    public static function financial_drawer_lock_after_close(
        int $cid,
        int $drawerId,
        string $businessDate,
        int $sessionId,
        int $uid,
    ): void 
    {
    
        if ($cid <= 0 || $drawerId <= 0) {
            return;
        }
        $open = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_open_session($cid, $drawerId, 0);
        if ($open) {
            return;
        }
        $current = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_row($cid, $drawerId);
        if (
            !$current ||
            (string) ($current["drawer_lock_status"] ?? "unlocked") === "locked"
        ) {
            return;
        }
        $linked =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.04.drawer_lock_after_close.01", [$cid, $drawerId], []) ?:
            0);
        if ($linked <= 0 && (int) ($current["user_id"] ?? 0) > 0) {
            $linked = 1;
        }
        if ($linked <= 0) {
            $linked = 1;
        }
        $done =
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.04.drawer_lock_after_close.02", [$cid, $drawerId, $businessDate], []) ?:
            0);
        if ($done < $linked) {
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
                "gaveta_permanece_destrancada_turnos_pendentes",
                "financeiro",
                $drawerId,
                [
                    "cash_session_id" => $sessionId,
                    "business_date" => $businessDate,
                    "colaboradores_vinculados" => $linked,
                    "colaboradores_com_sessao_finalizada" => $done,
                    "audit_body" =>
                        "Gaveta permaneceu disponível porque ainda há colaboradores vinculados sem fechamento no dia.",
                ],
            );
            return;
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.04.drawer_lock_after_close.03", [$businessDate, $drawerId, $cid], []);
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("gaveta_trancada_para_conferencia", "financeiro", $drawerId, [
            "cash_session_id" => $sessionId,
            "business_date" => $businessDate,
            "colaboradores_vinculados" => $linked,
            "audit_body" =>
                "Gaveta trancada automaticamente após o último colaborador vinculado finalizar o Caixa do dia.",
        ]);
        \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_notify_drawer_locked($cid, $drawerId, $sessionId, $uid);
    
    }

    public static function financial_default_drawer_unlock_local(
        int $cid,
        int $drawerId,
        ?array $drawer = null,
    ): string
    
    {
    
        $d = $drawer ?: \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_row($cid, $drawerId);
        $locked =
            (string) ($d["drawer_locked_business_date"] ?? \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid));
        try {
            $dt = new DateTimeImmutable(
                $locked . " 08:00:00",
                new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone(null, $cid)),
            )->modify("+1 day");
        } catch (Throwable $e) {
            $dt = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_now_in_timezone($cid)->modify("+1 day")->setTime(8, 0);
        }
        return $dt->format("Y-m-d\TH:i");
    
    }

    public static function financial_schedule_drawer_unlock(
        int $cid,
        int $drawerId,
        int $adminUid,
        string $unlockLocal,
        string $notes = "",
    ): void 
    {
    
        $d = \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_drawer_row($cid, $drawerId);
        if (!$d) {
            throw new RuntimeException("Gaveta inválida.");
        }
        if ((string) ($d["drawer_lock_status"] ?? "unlocked") !== "locked") {
            throw new RuntimeException(
                "Esta Gaveta não está trancada para conferência.",
            );
        }
        $lockedDay = (string) ($d["drawer_locked_business_date"] ?? "");
        $unlockLocal = trim($unlockLocal);
        if ($unlockLocal === "") {
            throw new RuntimeException(
                "Informe o horário em que a Gaveta será destrancada.",
            );
        }
        try {
            $tz = new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone(null, $cid));
            $localDt = new DateTimeImmutable($unlockLocal, $tz);
            if ($lockedDay !== "" && $localDt->format("Y-m-d") <= $lockedDay) {
                throw new RuntimeException(
                    "O destravamento deve ser agendado para o dia seguinte ao fechamento, no mínimo.",
                );
            }
            $unlockUtc = $localDt
                ->setTimezone(new DateTimeZone("UTC"))
                ->format("Y-m-d H:i:s");
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Informe um horário de destravamento válido.",
            );
        }
        \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.04.schedule_drawer_unlock.01", [$unlockUtc, $adminUid, trim($notes) ?: null, $drawerId, $cid], []);
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("gaveta_destravamento_agendado", "financeiro", $drawerId, [
            "unlock_at" => $unlockUtc,
            "locked_business_date" => $lockedDay,
            "observacao" => trim($notes),
            "audit_body" => "Gerência conferiu a Gaveta e agendou o destravamento.",
        ]);
    
    }

    public static function financial_drawer_open_session(
        int $cid,
        int $locationId,
        int $excludeUid = 0,
    ): ?array 
    {
    
        if ($cid <= 0 || $locationId <= 0) {
            return null;
        }
        $params = [$cid, $locationId];
        if ($excludeUid > 0) {
            $params[] = $excludeUid;
        }
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.04.drawer_open_session.01", $params, compact('excludeUid'));
    
    }

    public static function financial_latest_drawer_session(
        int $cid,
        int $locationId,
        string $maxDate = "",
        int $ignoreSessionId = 0,
    ): ?array 
    {
    
        if ($cid <= 0 || $locationId <= 0) {
            return null;
        }
        $params = [$cid, $locationId];
        if ($maxDate !== "") {
            $params[] = $maxDate;
        }
        if ($ignoreSessionId > 0) {
            $params[] = $ignoreSessionId;
        }
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.04.latest_drawer_session.01", $params, compact('maxDate', 'ignoreSessionId'));
    
    }

    public static function financial_drawer_previous_balance(
        int $cid,
        int $locationId,
        string $businessDate = "",
        int $ignoreSessionId = 0,
    ): int 
    {
    
        $businessDate = $businessDate ?: \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        $last = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_latest_drawer_session(
            $cid,
            $locationId,
            $businessDate,
            $ignoreSessionId,
        );
        if (!$last) {
            return 0;
        }
        return (int) ($last["keep_in_drawer_cents"] ?? 0);
    
    }

    public static function financial_drawer_pending_previous_review(
        int $cid,
        int $locationId,
        string $today = "",
    ): ?array 
    {
    
        $today = $today ?: \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
        if ($cid <= 0 || $locationId <= 0) {
            return null;
        }
        return \Prontoo\Runtime\Financial\FinancialComposition::dataService()->row("financial.04.drawer_pending_previous_review.01", [$cid, $locationId, $today], []);
    
    }

    public static function financial_drawer_balance(int $cid, int $locationId): int
    
    {
    
        $open = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_open_session($cid, $locationId, 0);
        if ($open) {
            return \Prontoo\Runtime\Financial\FinancialRuntimeOperations07::financial_session_expected($open);
        }
        $last = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_latest_drawer_session($cid, $locationId, "", 0);
        return $last ? (int) ($last["keep_in_drawer_cents"] ?? 0) : 0;
    
    }

    public static function financial_drawer_balance_snapshot(
        int $cid,
        array $locationIds,
    ): array 
    {
    
        static $requestCache = [];
        $locationIds = array_values(
            array_unique(
                array_filter(
                    array_map("intval", $locationIds),
                    static  fn(int $id): bool => $id > 0,
                ),
            ),
        );
        $balances = array_fill_keys($locationIds, 0);
        $openByLocation = [];
        if ($cid <= 0 || !$locationIds) {
            return ["balances" => $balances, "open" => $openByLocation];
        }
        sort($locationIds, SORT_NUMERIC);
        $requestKey = $cid . ":" . implode(",", $locationIds);
        if (isset($requestCache[$requestKey])) {
            return $requestCache[$requestKey];
        }
        $openRows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.04.drawer_balance_snapshot.01", array_merge([$cid], $locationIds), ['itemCount' => count($locationIds)])->fetchAll();
        $sessionToLocation = [];
        foreach ($openRows as $open) {
            $locationId = (int) ($open["location_id"] ?? 0);
            if ($locationId <= 0 || isset($openByLocation[$locationId])) {
                continue;
            }
            $openByLocation[$locationId] = $open;
            $sessionToLocation[(int) $open["id"]] = $locationId;
            $balances[$locationId] = (int) $open["opening_balance_cents"];
        }
        if ($sessionToLocation) {
            $sessionIds = array_keys($sessionToLocation);
            $movementRows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.04.drawer_balance_snapshot.02", array_merge([$cid], $sessionIds), ['itemCount' => count($sessionIds)])->fetchAll();
            foreach ($movementRows as $movement) {
                $locationId =
                    $sessionToLocation[(int) ($movement["cash_session_id"] ?? 0)] ??
                    0;
                if ($locationId <= 0) {
                    continue;
                }
                $amount = (int) ($movement["total"] ?? 0);
                $balances[$locationId] = \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_checked_add(
                    (int) $balances[$locationId],
                    \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_movement_delta_for_location(
                        $amount,
                        (int) ($movement["from_location_id"] ?? 0) ?: null,
                        (int) ($movement["to_location_id"] ?? 0) ?: null,
                        $locationId,
                    ),
                    "Saldo da Gaveta",
                );
            }
        }
        $closedLocationIds = array_values(
            array_diff($locationIds, array_keys($openByLocation)),
        );
        if ($closedLocationIds) {
            $closedRows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.04.drawer_balance_snapshot.03", array_merge([$cid], $closedLocationIds), ['itemCount' => count($closedLocationIds)])->fetchAll();
            foreach ($closedRows as $closed) {
                $balances[(int) $closed["location_id"]] =
                    (int) $closed["keep_in_drawer_cents"];
            }
        }
        return $requestCache[$requestKey] = [
            "balances" => $balances,
            "open" => $openByLocation,
        ];
    
    }

    public static function financial_drawer_daily_totals(
        int $cid,
        int $locationId,
        string $date,
    ): array 
    {
    
        $map = \Prontoo\Runtime\Financial\FinancialRuntimeOperations04::financial_drawer_daily_totals_map($cid, [$locationId], [$date]);
        return $map[$locationId . "|" . $date] ??
            \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_drawer_daily_totals_empty();
    
    }

    public static function financial_drawer_daily_totals_map(
        int $cid,
        array $locationIds,
        array $dates,
    ): array 
    {
    
        $locationIds = array_values(
            array_unique(
                array_filter(
                    array_map("intval", $locationIds),
                    static  fn(int $id): bool => $id > 0,
                ),
            ),
        );
        $dates = array_values(
            array_unique(
                array_filter(
                    array_map("strval", $dates),
                    static  fn(string $date): bool =>
                        preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1,
                ),
            ),
        );
        if ($cid <= 0 || !$locationIds || !$dates) {
            return [];
        }
        $out = [];
        foreach ($locationIds as $locationId) {
            foreach ($dates as $date) {
                $out[$locationId . "|" . $date] =
                    \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_drawer_daily_totals_empty();
            }
        }
        $storageDates = array_map(
            static  fn(string $date): int =>
                \Prontoo\Core\Temporal\PiTime::dateOnlyToTimestamp($date),
            $dates,
        );
        $params = array_merge([$cid], $locationIds, $storageDates);
        $queryShape = ['locationCount' => count($locationIds), 'dateCount' => count($dates)];
        $sessions = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.04.drawer_daily_totals_map.01", $params, $queryShape)->fetchAll();
        foreach ($sessions as $session) {
            $key =
                (int) ($session["location_id"] ?? 0) .
                "|" .
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($session["business_date"] ?? "");
            if (!isset($out[$key])) {
                continue;
            }
            if ((int) $out[$key]["sessions"] === 0) {
                $out[$key]["opening"] = (int) $session["opening_balance_cents"];
            }
            $out[$key]["sessions"]++;
            $out[$key]["kept"] = (int) $session["keep_in_drawer_cents"];
            $out[$key]["withdrawn"] +=
                (int) $session["transfer_to_safe_cents"];
            $out[$key]["declared"] +=
                (int) $session["declared_closing_cents"];
            if ((string) $session["status"] === "open") {
                $out[$key]["open_count"]++;
            }
            if ((string) $session["status"] === "closed_pending_review") {
                $out[$key]["pending_count"]++;
            }
        }
        $movements = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.04.drawer_daily_totals_map.02", $params, $queryShape)->fetchAll();
        $movementKeys = [
            "receipt" => "receipts",
            "payment" => "payments",
            "transfer" => "transfers",
        ];
        foreach ($movements as $movement) {
            $key =
                (int) ($movement["location_id"] ?? 0) .
                "|" .
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($movement["business_date"] ?? "");
            $metric = $movementKeys[(string) ($movement["movement_type"] ?? "")] ??
                null;
            if ($metric !== null && isset($out[$key])) {
                $out[$key][$metric] = (int) $movement["total"];
            }
        }
        return $out;
    
    }

    public static function financial_location_belongs(int $cid, int $locationId): bool
    
    {
    
        return $locationId > 0 &&
            (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.04.location_belongs.01", [$locationId, $cid], []) ?:
                0) > 0;
    
    }

    public static function financial_admin_location_belongs(int $cid, int $locationId): bool
    
    {
    
        if ($locationId <= 0) {
            return false;
        }
        return (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.04.admin_location_belongs.01", [$locationId, $cid], []) ?:
            0) > 0;
    
    }

    public static function financial_admin_location_select_options(int $cid): array
    
    {
    
        \Prontoo\Domain\Financial\FinancialDomainOperations01::financial_operational_schema_ready();
        $rows = \Prontoo\Runtime\Financial\FinancialComposition::dataService()->result("financial.04.admin_location_select_options.01", [$cid], [])->fetchAll();
        $out = ["" => "Selecione"];
        foreach ($rows as $r) {
            $label =
                (string) ($r["location_type"] ?? "") === "bank_account"
                    ? "Banco"
                    : "Cofre";
            $out[(int) $r["id"]] = $label . " · " . (string) $r["name"];
        }
        return $out;
    
    }

    public static function financial_day_is_consolidated(
        int $cid,
        string $businessDate = "",
    ): bool 
    {
    
        try {
            \Prontoo\Runtime\Financial\FinancialComposition::dataService()->ensureDailyClosingSchema();
            $businessDate = $businessDate ?: \Prontoo\Runtime\Financial\FinancialRuntimeOperations03::financial_today($cid);
            return (int) (\Prontoo\Runtime\Financial\FinancialComposition::dataService()->scalar("financial.04.day_is_consolidated.01", [$cid, $businessDate], []) ?:
                0) > 0;
        } catch (Throwable $e) {
            error_log("[Prontoo daily closing check] " . $e->getMessage());
            throw $e;
        }
    
    }
}
