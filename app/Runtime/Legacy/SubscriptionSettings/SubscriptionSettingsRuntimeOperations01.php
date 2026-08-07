<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\SubscriptionSettings;

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
    
        $v = meta_get("default_monthly_price_cents", PRONTOO_MONTHLY_PRICE_CENTS);
        $c = (int) $v;
        return $c > 0 ? $c : PRONTOO_MONTHLY_PRICE_CENTS;
    
    }

    public static function default_trial_days(): int
    
    {
    
        $v = meta_get("default_trial_days", PRONTOO_TRIAL_DAYS);
        $days = (int) $v;
        $policyRevision = "trial_30_days_2026_07_14";
        $storedRevision = (string) meta_get("trial_days_policy_revision", "");
        if ($days === 90 && $storedRevision !== $policyRevision) {
            try {
                meta_set("default_trial_days", (string) PRONTOO_TRIAL_DAYS);
                meta_set("trial_days_policy_revision", $policyRevision);
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
    
        $key = mb_trim((string) meta_get("subscription_pix_key", "pix@prontoo.app"));
        return $key !== "" ? $key : "pix@prontoo.app";
    
    }

    public static function subscription_time_ts(
        null|string|int $value,
        bool $endOfDay = false,
    ): int 
    {
    
        return app_storage_timestamp($value, $endOfDay);
    
    }

    public static function subscription_trial_end_from_start(
        null|string|int $start = null,
        ?int $days = null,
    ): int 
    {
    
        $days = $days ?? default_trial_days();
        if ($days <= 0) {
            $days = PRONTOO_TRIAL_DAYS;
        }
        if ($days <= 0) {
            $days = 30;
        }
        $startTs = subscription_time_ts($start);
        if ($startTs <= 0) {
            $startTs = time();
        }
        return $startTs + $days * 86400;
    
    }

    public static function subscription_trial_is_active(null|string|int $trialEnd): bool
    
    {
    
        $ts = subscription_time_ts($trialEnd, true);
        return $ts <= 0 || $ts >= time();
    
    }

    public static function subscription_paid_is_active(
        null|string|int $paidUntil,
        int $clinicId = 0,
        ?array $context = null,
    ): bool 
    {
    
        $ts = app_date_only_end_timestamp($paidUntil, $clinicId, $context);
        return $ts > 0 && $ts >= time();
    
    }

    public static function ensure_clinic_trial_active(
        int $clinicId,
        bool $onlyIfOnboardingPending = true,
    ): void 
    {
    
        if ($clinicId <= 0 || !has_cfg()) {
            return;
        }
        $fn = function () use ($clinicId, $onlyIfOnboardingPending): void {
    
            $cl = one(
                "SELECT id,onboarding_done,created_at,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents FROM pi_clinics WHERE id=? LIMIT 1",
                [$clinicId],
            );
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
                subscription_paid_is_active($cl["paid_until"] ?? null, $clinicId)
            ) {
                return;
            }
            $startedTs = subscription_time_ts($cl["trial_started_at"] ?? null);
            if ($startedTs <= 0) {
                $startedTs = subscription_time_ts($cl["created_at"] ?? null);
            }
            if ($startedTs <= 0) {
                $startedTs = time();
            }
            $trialTs = subscription_time_ts($cl["trial_ends_at"] ?? null, true);
            $minTrialTs = subscription_trial_end_from_start(
                $startedTs,
                default_trial_days(),
            );
            if ($trialTs < time()) {
                $trialTs = max(
                    $minTrialTs,
                    subscription_trial_end_from_start(time(), default_trial_days()),
                );
            }
            $price = (int) ($cl["monthly_price_cents"] ?? 0);
            if ($price <= 0) {
                $price = default_monthly_price_cents();
            }
            q(
                "UPDATE pi_clinics SET subscription_status='trial', trial_started_at=?, trial_ends_at=?, monthly_price_cents=?, paid_until=NULL, updated_at=NOW() WHERE id=?",
                [$startedTs, $trialTs, $price, $clinicId],
            );
        };
        if (function_exists("with_read_only_guard_disabled")) {
            with_read_only_guard_disabled($fn);
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
                subscription_paid_is_active($paid, (int) ($cl["id"] ?? 0), $cl))
        ) {
            return "active";
        }
        if (subscription_paid_is_active($paid, (int) ($cl["id"] ?? 0), $cl)) {
            return "active";
        }
        if ($status === "trial" && subscription_trial_is_active($trial)) {
            return "trial";
        }
        return "pause";
    
    }

    public static function clinic_subscription_status_card(array $cl): string
    
    {
    
        $kind = clinic_subscription_kind($cl);
        $price =
            (int) ($cl["monthly_price_cents"] ?? default_monthly_price_cents());
        if ($price <= 0) {
            $price = default_monthly_price_cents();
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
                $until !== "" ? date_br($until) : "Sem vencimento informado";
            $tone = "ok";
        } elseif ($kind === "trial") {
            $title = "Assinatura ativa";
            $status = "Ativo";
            $ico = "verified";
            $body =
                "O consultório está liberado durante o período inicial. Ao final do prazo, regularize a mensalidade para manter a operação liberada.";
            $vigencia =
                $trial !== ""
                    ? date_br($trial)
                    : trial_period_label(default_trial_days());
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
            icon($ico) .
            "</span>" .
            '<div class="subscription-hero-copy"><span class="eyebrow">Assinaturas</span><h2>' .
            e($title) .
            "</h2><p>" .
            e($body) .
            "</p></div>" .
            "</div>" .
            '<div class="subscription-metric-row" aria-label="Resumo da assinatura">' .
            '<span class="subscription-metric ' .
            $tone .
            '"><small>Status</small><strong>' .
            e($status) .
            "</strong></span>" .
            '<span class="subscription-metric"><small>Mensalidade</small><strong>' .
            e(money_br($price)) .
            "</strong></span>" .
            '<span class="subscription-metric"><small>Vigência</small><strong>' .
            e($vigencia) .
            "</strong></span>" .
            "</div>" .
            "</section>";
    
    }

    public static function subscription_payment_proof_guard(int $cid, int $uid): void
    
    {
    
        if (
            security_rate_limit(security_client_bucket("payment_proof"), 8, 3600) ||
            security_rate_limit(security_ip_bucket("payment_proof"), 30, 3600) ||
            security_rate_limit(
                security_value_bucket("payment_proof_clinic", (string) $cid),
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
        subscription_payment_proof_guard($cid, $uid);
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
            subscription_payment_proof_validate_pdf($tmp);
        } else {
            subscription_payment_proof_validate_image($tmp, $mime);
        }
        $storage = subscription_payment_proof_storage(
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
            $stored = subscription_payment_proof_reencode_image($tmp, $dest, $mime);
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
            href("admin_payment_proof", ["payment_id" => (int) $payment["id"]]) .
            '">' .
            action_summary_label("Visualizar comprovante", "visibility") .
            "</a>";
    
    }

    public static function page_admin_payment_proof(): void
    
    {
    
        require_can("admin_painel");
        $pid = (int) ($_GET["payment_id"] ?? 0);
        $p =
            $pid > 0
                ? one(
                    "SELECT sp.id,sp.clinic_id,sp.status,sp.proof_path,c.display_name FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.id=?",
                    [$pid],
                )
                : null;
        if (!$p || mb_trim((string) ($p["proof_path"] ?? "")) === "") {
            flash("Comprovante não encontrado para este pagamento.", "bad");
            redirect("admin_painel");
        }
        $full = subscription_payment_proof_absolute_path((string) $p["proof_path"]);
        if ($full === null || !is_file($full)) {
            flash("O arquivo do comprovante não está mais disponível.", "bad");
            redirect("admin_painel");
        }
        audit(
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
            $row = one(
                "SELECT id,amount_cents,proof_path,applied_until,created_at FROM pi_subscription_payments WHERE clinic_id=? AND status='pending_admin' ORDER BY id DESC LIMIT 1",
                [$cid],
            );
            return $row ?: null;
        } catch (Throwable $e) {
            error_log("[Prontoo subscription pending] " . $e->getMessage());
            return null;
        }
    
    }
}
