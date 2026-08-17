<?php
declare(strict_types=1);

namespace Prontoo\Runtime\SubscriptionSettings;

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

final class SubscriptionSettingsRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function default_monthly_price_cents(): int
    
    {
    
        $v = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get("default_monthly_price_cents", PRONTOO_MONTHLY_PRICE_CENTS);
        $c = (int) $v;
        return $c > 0 ? $c : PRONTOO_MONTHLY_PRICE_CENTS;
    
    }

    public static function default_trial_days(): int
    
    {
    
        $v = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get("default_trial_days", PRONTOO_TRIAL_DAYS);
        $days = (int) $v;
        $policyRevision = "trial_30_days_2026_07_14";
        $storedRevision = (string) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get("trial_days_policy_revision", "");
        if ($days === 90 && $storedRevision !== $policyRevision) {
            try {
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set("default_trial_days", (string) PRONTOO_TRIAL_DAYS);
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set("trial_days_policy_revision", $policyRevision);
            } catch (Throwable $ignored) {
                error_log(
                    "[Prontoo recoverable " .
                        __FUNCTION__ .
                        "] " .
                        $ignored->getMessage(),
                );
            }
            return PRONTOO_TRIAL_DAYS;
        }
        if ($days < 0 || $days > 3650) {
            return PRONTOO_TRIAL_DAYS;
        }
        return $days;
    
    }

    public static function subscription_pix_key(): string
    
    {
    
        $key = mb_trim((string) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get("subscription_pix_key", "pix@prontoo.app"));
        return $key !== "" ? $key : "pix@prontoo.app";
    
    }

    public static function subscription_time_ts(
        null|string|int $value,
        bool $endOfDay = false,
    ): int 
    {
    
        return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_storage_timestamp($value, $endOfDay);
    
    }

    public static function subscription_trial_end_from_start(
        null|string|int $start = null,
        ?int $days = null,
    ): int 
    {
    
        $days = $days ?? \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days();
        if ($days <= 0) {
            $days = PRONTOO_TRIAL_DAYS;
        }
        if ($days <= 0) {
            $days = 30;
        }
        $startTs = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_time_ts($start);
        if ($startTs <= 0) {
            $startTs = time();
        }
        return $startTs + $days * 86400;
    
    }

    public static function subscription_trial_is_active(null|string|int $trialEnd): bool
    
    {
    
        $ts = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_time_ts($trialEnd, true);
        return $ts <= 0 || $ts >= time();
    
    }

    public static function subscription_paid_is_active(
        null|string|int $paidUntil,
        int $clinicId = 0,
        ?array $context = null,
    ): bool 
    {
    
        $ts = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_date_only_end_timestamp($paidUntil, $clinicId, $context);
        return $ts > 0 && $ts >= time();
    
    }

    public static function ensure_clinic_trial_active(
        int $clinicId,
        bool $onlyIfOnboardingPending = true,
    ): void 
    {
    
        if ($clinicId <= 0 || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        $fn = function () use ($clinicId, $onlyIfOnboardingPending): void {
    
            $cl = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.subscription_settings.01.ensure_clinic_trial_active.01', [$clinicId], []);
            if (!$cl) {
                return;
            }
            if (
                $onlyIfOnboardingPending &&
                (int) ($cl["onboarding_done"] ?? 0) === 1
            ) {
                return;
            }
            $status = (string) ($cl["subscription_status"] ?? "trial");
            if (
                $status === "exempt" ||
                \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_paid_is_active($cl["paid_until"] ?? null, $clinicId)
            ) {
                return;
            }
            $startedTs = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_time_ts($cl["trial_started_at"] ?? null);
            if ($startedTs <= 0) {
                $startedTs = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_time_ts($cl["created_at"] ?? null);
            }
            if ($startedTs <= 0) {
                $startedTs = time();
            }
            $trialTs = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_time_ts($cl["trial_ends_at"] ?? null, true);
            $minTrialTs = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_trial_end_from_start(
                $startedTs,
                \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days(),
            );
            if ($trialTs < time()) {
                $trialTs = max(
                    $minTrialTs,
                    \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_trial_end_from_start(time(), \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days()),
                );
            }
            $price = (int) ($cl["monthly_price_cents"] ?? 0);
            if ($price <= 0) {
                $price = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents();
            }
            \Prontoo\Runtime\Operational\OperationalComposition::administration()->result('operational.subscription_settings.01.ensure_clinic_trial_active.02', [$startedTs, $trialTs, $price, $clinicId], []);
        };
        if (is_callable([\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::class, 'with_read_only_guard_disabled'])) {
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::with_read_only_guard_disabled($fn);
        } else {
            $fn();
        }
    
    }

    public static function clinic_subscription_kind(array $cl): string
    
    {
    
        $status = (string) ($cl["subscription_status"] ?? "trial");
        $paid = $cl["paid_until"] ?? null;
        $trial = $cl["trial_ends_at"] ?? null;
        if ($status === "read_only") {
            return "pause";
        }
        if ($status === "exempt") {
            return "active";
        }
        if (
            $status === "active" &&
            ($paid === null ||
                mb_trim((string) $paid) === "" ||
                \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_paid_is_active($paid, (int) ($cl["id"] ?? 0), $cl))
        ) {
            return "active";
        }
        if (\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_paid_is_active($paid, (int) ($cl["id"] ?? 0), $cl)) {
            return "active";
        }
        if ($status === "trial" && \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_trial_is_active($trial)) {
            return "trial";
        }
        return "pause";
    
    }

    public static function clinic_subscription_status_card(array $cl): string
    
    {
    
        $kind = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::clinic_subscription_kind($cl);
        $price =
            (int) ($cl["monthly_price_cents"] ?? \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents());
        if ($price <= 0) {
            $price = \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_monthly_price_cents();
        }
        $until = mb_trim((string) ($cl["paid_until"] ?? ""));
        $trial = mb_trim((string) ($cl["trial_ends_at"] ?? ""));
        if ($kind === "active") {
            $isExempt = (string) ($cl["subscription_status"] ?? "") === "exempt";
            $title = $isExempt ? "Assinatura isenta" : "Assinatura ativa";
            $status = $isExempt ? "Isento" : "Ativo";
            $ico = $isExempt ? "workspace_premium" : "verified";
            $body = $isExempt
                ? "Este consultório está isento de cobrança e liberado para testes ou uso administrativo."
                : "O consultório está liberado para atendimento, agenda, pessoas, documentos e financeiro.";
            $vigencia =
                $until !== "" ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($until) : "Sem vencimento informado";
            $tone = "ok";
        } elseif ($kind === "trial") {
            $title = "Assinatura ativa";
            $status = "Ativo";
            $ico = "verified";
            $body =
                "O consultório está liberado durante o período inicial. Ao final do prazo, regularize a mensalidade para manter a operação liberada.";
            $vigencia =
                $trial !== ""
                    ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::date_br($trial)
                    : \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::trial_period_label(\Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::default_trial_days());
            $tone = "trial";
        } else {
            $title = "Somente leitura";
            $status = "Somente leitura";
            $ico = "lock";
            $body =
                "A consulta aos dados segue disponível. Para voltar a incluir, agendar e movimentar financeiro, regularize a assinatura.";
            $vigencia = "Aguardando pagamento";
            $tone = "pause";
        }
        return '<section class="subscription-status-card subscription-hero ' .
            $kind .
            '">' .
            '<div class="subscription-hero-main">' .
            '<span class="subscription-hero-icon">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($ico) .
            "</span>" .
            '<div class="subscription-hero-copy"><span class="eyebrow">Assinaturas</span><h2>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
            "</h2><p>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($body) .
            "</p></div>" .
            "</div>" .
            '<div class="subscription-metric-row" aria-label="Resumo da assinatura">' .
            '<span class="subscription-metric ' .
            $tone .
            '"><small>Status</small><strong>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($status) .
            "</strong></span>" .
            '<span class="subscription-metric"><small>Mensalidade</small><strong>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::money_br($price)) .
            "</strong></span>" .
            '<span class="subscription-metric"><small>Vigência</small><strong>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($vigencia) .
            "</strong></span>" .
            "</div>" .
            "</section>";
    
    }

    public static function subscription_payment_proof_guard(int $cid, int $uid): void
    
    {
    
        if (
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("payment_proof"), 8, 3600) ||
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_ip_bucket("payment_proof"), 30, 3600) ||
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(
                \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_value_bucket("payment_proof_clinic", (string) $cid),
                20,
                3600,
            )
        ) {
            throw new RuntimeException(
                "Muitas tentativas de envio de comprovante. Aguarde alguns instantes.",
            );
        }
    
    }

    public static function subscription_payment_proof_upload(int $cid, int $uid): ?string
    
    {
    
        if (
            empty($_FILES["payment_proof"]) ||
            !is_array($_FILES["payment_proof"])
        ) {
            return null;
        }
        $f = $_FILES["payment_proof"];
        if ((int) ($f["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) $f["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Falha no envio do comprovante.");
        }
        \Prontoo\Runtime\SubscriptionSettings\SubscriptionSettingsRuntimeOperations01::subscription_payment_proof_guard($cid, $uid);
        $tmp = (string) ($f["tmp_name"] ?? "");
        if ($tmp === "" || !is_uploaded_file($tmp)) {
            throw new RuntimeException("Upload inválido.");
        }
        $size = (int) ($f["size"] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new RuntimeException("Comprovante deve ter até 5 MB.");
        }
        $orig = (string) ($f["name"] ?? "comprovante");
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = [
            "pdf" => "application/pdf",
            "jpg" => "image/jpeg",
            "jpeg" => "image/jpeg",
            "png" => "image/png",
            "webp" => "image/webp",
        ];
        if (!isset($allowed[$ext])) {
            throw new RuntimeException(
                "Formato de comprovante não permitido. Use PDF, JPG, PNG ou WEBP.",
            );
        }
        $finfo = function_exists("finfo_open")
            ? finfo_open(FILEINFO_MIME_TYPE)
            : null;
        $mime = $finfo
            ? (string) finfo_file($finfo, $tmp)
            : (string) ($f["type"] ?? "");
        if ($finfo) {
            finfo_close($finfo);
        }
        if ($mime === "" || $mime === "application/octet-stream") {
            $mime = $allowed[$ext];
        }
        if (
            $mime !== $allowed[$ext] &&
            !($ext === "jpg" && $mime === "image/jpeg")
        ) {
            throw new RuntimeException(
                "O conteúdo do comprovante não confere com a extensão.",
            );
        }
        if ($mime === "application/pdf") {
            \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_proof_validate_pdf($tmp);
        } else {
            \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_payment_proof_validate_image($tmp, $mime);
        }
        $storage = \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_proof_storage(
            $cid,
            $mime !== "application/pdf",
        );
        $dir = (string) $storage["absolute"];
        $baseName =
            "proof_" .
            $uid .
            "_" .
            date("Ymd_His") .
            "_" .
            bin2hex(random_bytes(8));
        if ($mime === "application/pdf") {
            $storedExt = "pdf";
            $name = $baseName . "." . $storedExt;
            $dest = $dir . "/" . $name;
            $stored = move_uploaded_file($tmp, $dest);
        } else {
            $storedExt = "jpg";
            $name = $baseName . ".jpg";
            $dest = $dir . "/" . $name;
            $stored = \Prontoo\Domain\SubscriptionSettings\SubscriptionSettingsDomainOperations01::subscription_payment_proof_reencode_image($tmp, $dest, $mime);
            if (!$stored) {
                @unlink($dest);
                $storedExt = match ($mime) {
                    "image/png" => "png",
                    "image/webp" => "webp",
                    default => "jpg",
                };
                $name = $baseName . "." . $storedExt;
                $dest = $dir . "/" . $name;
                $stored = move_uploaded_file($tmp, $dest);
            }
        }
        if (!$stored) {
            @unlink($dest);
            throw new RuntimeException("Não foi possível salvar o comprovante.");
        }
        @chmod($dest, 0640);
        return (string) $storage["relative"] . "/" . $name;
    
    }

    public static function subscription_payment_proof_view_link(array $payment): string
    
    {
    
        if (mb_trim((string) ($payment["proof_path"] ?? "")) === "") {
            return "";
        }
        return '<a class="ghost small" target="_blank" rel="noopener" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("admin_payment_proof", ["payment_id" => (int) $payment["id"]]) .
            '">' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Visualizar comprovante", "visibility") .
            "</a>";
    
    }

    public static function page_admin_payment_proof(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_clinics");
        $pid = (int) ($_GET["payment_id"] ?? 0);
        $p =
            $pid > 0
                ? \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.subscription_settings.01.page_admin_payment_proof.01', [$pid], [])
                : null;
        if (!$p || mb_trim((string) ($p["proof_path"] ?? "")) === "") {
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("Comprovante não encontrado para este pagamento.", "bad");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_clinics");
        }
        $full = \Prontoo\Infrastructure\SubscriptionSettings\SubscriptionSettingsInfrastructureOperations01::subscription_payment_proof_absolute_path((string) $p["proof_path"]);
        if ($full === null || !is_file($full)) {
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::flash("O arquivo do comprovante não está mais disponível.", "bad");
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::redirect("admin_clinics");
        }
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit(
            "assinatura_comprovante_visualizado",
            "assinatura",
            (int) $p["clinic_id"],
            [
                "pagamento_id" => $pid,
                "audit_body" =>
                    "Desenvolvedor abriu o comprovante enviado para conferência do pagamento.",
            ],
        );
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            "pdf" => "application/pdf",
            "png" => "image/png",
            "jpg", "jpeg" => "image/jpeg",
            "webp" => "image/webp",
            default => "application/octet-stream",
        };
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header("Content-Type: " . $mime);
        header("Content-Length: " . (string) filesize($full));
        header("Cache-Control: private, no-store, max-age=0");
        header("X-Content-Type-Options: nosniff");
        header(
            'Content-Disposition: attachment; filename="comprovante-prontoo-' .
                $pid .
                "." .
                $ext .
                '"',
        );
        readfile($full);
        exit();
    
    }

    public static function clinic_subscription_pending_payment(int $cid): ?array
    
    {
    
        try {
            $row = \Prontoo\Runtime\Operational\OperationalComposition::administration()->row('operational.subscription_settings.01.clinic_subscription_pending_payment.01', [$cid], []);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log("[Prontoo subscription pending] " . $e->getMessage());
            return null;
        }
    
    }
}
