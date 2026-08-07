<?php
declare(strict_types=1);

namespace Prontoo\Domain\Legacy\SubscriptionSettings;

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

final class SubscriptionSettingsDomainOperations01
{
    private function __construct()
    {
    }

    public static function trial_period_label(int $days): string
    
    {
    
        if ($days === 90) {
            return "três meses";
        }
        if ($days === 60) {
            return "dois meses";
        }
        if ($days === 30) {
            return "30 dias";
        }
        if ($days === 1) {
            return "1 dia";
        }
        if ($days <= 0) {
            return "sem gratuidade";
        }
        return $days . " dias";
    
    }

    public static function normalize_subscription_pix_key(string $key): string
    
    {
    
        $key = mb_trim($key);
        if ($key === "") {
            throw new RuntimeException("Informe a chave Pix da assinatura.");
        }
        if (mb_strlen($key) > 140) {
            throw new RuntimeException(
                "A chave Pix deve ter no máximo 140 caracteres.",
            );
        }
        return $key;
    
    }

    public static function clinic_subscription_action_label(string $kind): string
    
    {
    
        return $kind === "trial"
            ? "Realizar assinatura"
            : ($kind === "pause"
                ? "Reativar assinatura"
                : "Adicionar mais um mês");
    
    }

    public static function subscription_payment_proof_validate_image(
        string $tmp,
        string $mime,
    ): array 
    {
    
        $info = @getimagesize($tmp);
        if (!$info || empty($info[0]) || empty($info[1])) {
            throw new RuntimeException("Imagem inválida.");
        }
        if ((int) $info[0] > 6000 || (int) $info[1] > 6000) {
            throw new RuntimeException("Imagem muito grande em dimensões.");
        }
        $detectedMime = strtolower(mb_trim((string) ($info["mime"] ?? "")));
        if (!in_array($detectedMime, ["image/jpeg", "image/png", "image/webp"], true)) {
            throw new RuntimeException("Formato de imagem não permitido.");
        }
        if ($detectedMime !== $mime) {
            throw new RuntimeException(
                "O conteúdo do comprovante não confere com o formato informado.",
            );
        }
        return [(int) $info[0], (int) $info[1], $detectedMime];
    
    }

    public static function subscription_payment_proof_reencode_image(
        string $tmp,
        string $dest,
        string $mime,
    ): bool 
    {
    
        if (!function_exists("imagejpeg")) {
            return false;
        }
        $img = null;
        if ($mime === "image/jpeg" && function_exists("imagecreatefromjpeg")) {
            $img = @imagecreatefromjpeg($tmp);
        } elseif ($mime === "image/png" && function_exists("imagecreatefrompng")) {
            $img = @imagecreatefrompng($tmp);
        } elseif (
            $mime === "image/webp" &&
            function_exists("imagecreatefromwebp")
        ) {
            $img = @imagecreatefromwebp($tmp);
        }
        if (!$img) {
            return false;
        }
        $ok = @imagejpeg($img, $dest, 88);
        if (is_resource($img) || $img instanceof GdImage) {
            @imagedestroy($img);
        }
        return (bool) $ok;
    
    }

    public static function subscription_payment_is_proof_review(array $payment): bool
    
    {
    
        return mb_trim((string) ($payment["proof_path"] ?? "")) !== "";
    
    }

    public static function subscription_trust_release_until(): string
    
    {
    
        return date("Y-m-d", strtotime("+" . PRONTOO_TRUST_RELEASE_DAYS . " days"));
    
    }

    public static function subscription_renewal_until(array $cl): string
    
    {
    
        $base = mb_trim((string) ($cl["paid_until"] ?? ""));
        $baseTs = $base !== "" ? strtotime($base . " 23:59:59") : 0;
        $startDate =
            $baseTs !== false && $baseTs >= strtotime("today")
                ? $base
                : date("Y-m-d");
        return date("Y-m-d", strtotime($startDate . " +30 days"));
    
    }

    public static function later_date(?string $a, ?string $b): string
    
    {
    
        $a = mb_trim((string) $a);
        $b = mb_trim((string) $b);
        if ($a === "") {
            return $b;
        }
        if ($b === "") {
            return $a;
        }
        $ta = strtotime($a . " 23:59:59");
        $tb = strtotime($b . " 23:59:59");
        if ($ta === false) {
            return $b;
        }
        if ($tb === false) {
            return $a;
        }
        return $ta >= $tb ? $a : $b;
    
    }
}
