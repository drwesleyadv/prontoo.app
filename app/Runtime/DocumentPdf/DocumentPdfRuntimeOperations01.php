<?php
declare(strict_types=1);

namespace Prontoo\Runtime\DocumentPdf;

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

final class DocumentPdfRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function document_pdf_link(int $docId, string $class = "ghost small"): string
    
    {
    
        return '<a class="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($class) .
            '" href="' .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("document_pdf", ["id" => $docId]) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("picture_as_pdf") .
            "<span>Gerar PDF</span></a>";
    
    }

    public static function document_pdf_dir(int $cid = 0): string
    
    {
    
        \Prontoo\Infrastructure\DocumentPdf\DocumentPdfInfrastructureOperations01::document_pdf_public_router_dir();
        $dir = \Prontoo\Infrastructure\DocumentPdf\DocumentPdfInfrastructureOperations01::document_pdf_storage_dir();
        return $dir;
    
    }

    public static function document_pdf_cleanup(?int $cid = null): void
    
    {
    
        try {
            $ttl = \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_ttl_seconds();
            $dir = \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_dir((int) ($cid ?? 0));
            if (is_dir($dir)) {
                $cut = time() - $ttl;
                foreach (glob($dir . "/*.pdf") ?: [] as $file) {
                    if (
                        is_file($file) &&
                        filemtime($file) !== false &&
                        filemtime($file) < $cut
                    ) {
                        @unlink($file);
                        @unlink($file . ".json");
                    }
                }
                foreach (glob($dir . "/*.tmp") ?: [] as $file) {
                    if (
                        is_file($file) &&
                        filemtime($file) !== false &&
                        filemtime($file) < time() - 3600
                    ) {
                        @unlink($file);
                    }
                }
            }
            if (\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
                $cutDb = gmdate("Y-m-d H:i:s", time() - $ttl);
                if ($cid !== null && (int) $cid > 0) {
                    \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.document_pdf.01.document_pdf_cleanup.01', [(int) $cid, $cutDb], []);
                } else {
                    \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.document_pdf.01.document_pdf_cleanup.02', [
                        $cutDb,
                    ], []);
                }
            }
        } catch (Throwable $e) {
            error_log("[Prontoo document_pdf_cleanup] " . $e->getMessage());
        }
    
    }

    public static function document_pdf_cleanup_due(): void
    
    {
    
        try {
            $flag = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache/document_pdf_cleanup.flag");
            $last = is_file($flag) ? (int) filemtime($flag) : 0;
            if ($last > time() - 3600) {
                return;
            }
            if (!is_dir(dirname($flag))) {
                @mkdir(dirname($flag), 0750, true);
            }
            @touch($flag);
            \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_cleanup(null);
        } catch (Throwable $e) {
            error_log("[Prontoo document_pdf_cleanup_due] " . $e->getMessage());
        }
    
    }

    public static function document_pdf_token_secret(): string
    
    {
    
        $config = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()
            ? \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cfg()
            : [];
        $seed = mb_trim((string) ($config["secret"] ?? ""));
        if (strlen($seed) < 32) {
            throw new RuntimeException(
                "Segredo criptográfico da instalação indisponível para proteger o PDF.",
            );
        }
        return hash_hmac("sha256", "prontoo|document-pdf-token|v2", $seed);
    
    }

    public static function document_pdf_token(
        string $fileName,
        int $cid,
        int $uid,
        int $expires,
    ): string 
    {
    
        $payload = basename($fileName) . "|" . $cid . "|" . $uid . "|" . $expires;
        return hash_hmac("sha256", $payload, \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_token_secret());
    
    }

    public static function document_pdf_token_valid(
        string $fileName,
        int $cid,
        int $uid,
        string $token,
        int $expires,
    ): bool 
    {
    
        if ($expires < time() || $token === "") {
            return false;
        }
        return hash_equals(
            \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_token($fileName, $cid, $uid, $expires),
            $token,
        );
    
    }

    public static function document_pdf_delete_pair(string $fileName, int $cid): void
    
    {
    
        try {
            $path = \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_absolute_path($fileName);
            if (is_file($path)) {
                @unlink($path);
            }
            if (is_file($path . ".json")) {
                @unlink($path . ".json");
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " . __FUNCTION__ . "] " . $e->getMessage(),
            );
        }
        try {
            if ($cid > 0 && \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
                \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.document_pdf.01.document_pdf_delete_pair.01', [$cid, basename($fileName)], []);
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo recoverable " . __FUNCTION__ . "] " . $e->getMessage(),
            );
        }
    }

    public static function document_pdf_absolute_path(string $fileName): string
    
    {
    
        $fileName = basename($fileName);
        if (!\Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_file_name_valid($fileName)) {
            throw new RuntimeException("Nome de PDF inválido.");
        }
        return \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_dir() . "/" . $fileName;
    }

    public static function document_pdf_register_file(
        array $c,
        array $doc,
        string $path,
        string $name,
        int $generatedAt,
    ): void 
    {
    
        try {
            $cid = (int) ($c["clinic_id"] ?? 0);
            $hash = is_file($path) ? (hash_file("sha256", $path) ?: "") : "";
            if ($hash === "") {
                $hash = str_repeat("0", 64);
            }
            $size = is_file($path) ? max(0, (int) filesize($path)) : 0;
            \Prontoo\Runtime\Operational\OperationalComposition::documents()->result('operational.document_pdf.01.document_pdf_register_file.01', [
                    $cid,
                    (int) $doc["id"],
                    (int) ($c["user"]["id"] ?? 0),
                    \Prontoo\Domain\Documents\DocumentIdentifierPolicy::document_identifier_display($doc["document_identifier"] ?? ""),
                    $generatedAt,
                    $name,
                    \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_public_path($name),
                    $hash,
                    $size,
                    $generatedAt,
                ], []);
        } catch (Throwable $e) {
            error_log("[Prontoo document_pdf_register_file] " . $e->getMessage());
        }
    }

    public static function document_pdf_create_file(array $c, array $doc): string
    
    {
    
        $cid = (int) ($c["clinic_id"] ?? 0);
        \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_cleanup($cid);
        $dir = \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_dir($cid);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new RuntimeException(
                "Não foi possível preparar o armazenamento do PDF.",
            );
        }
        $generatedAt = time();
        $name = "";
        $path = "";
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_file_name($doc, $generatedAt, $cid);
            $candidatePath = $dir . "/" . $candidate;
            if (!is_file($candidatePath) && !\Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_catalog_row($candidate)) {
                $name = $candidate;
                $path = $candidatePath;
                break;
            }
        }
        if ($name === "" || $path === "") {
            throw new RuntimeException(
                "Não foi possível reservar um nome único para o PDF.",
            );
        }
        $pdf = \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_build_simple(
            \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_body_to_html((string) ($doc["content"] ?? "")),
            [
                "title" => $doc["title"] ?? "Documento",
                "document_identifier" => $doc["document_identifier"] ?? null,
            ],
        );
        $tmp = $path . "." . bin2hex(random_bytes(4)) . ".tmp";
        if (file_put_contents($tmp, $pdf, LOCK_EX) === false) {
            throw new RuntimeException("Não foi possível gerar o PDF.");
        }
        @chmod($tmp, 0640);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Não foi possível finalizar o PDF gerado.");
        }
        @chmod($path, 0640);
        $hash = hash_file("sha256", $path) ?: "";
        $meta = [
            "clinic_id" => $cid,
            "document_id" => (int) $doc["id"],
            "generated_by" => (int) ($c["user"]["id"] ?? 0),
            "generated_at" => date("Y-m-d H:i:s", $generatedAt),
            "generated_unix" => $generatedAt,
            "path" => \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_public_path($name),
            "download_name" => $name,
            "file_sha256" => $hash,
            "file_size" => is_file($path) ? (int) filesize($path) : 0,
            "font_family" => "Fira Sans",
            "identifier_font_family" => "Science Gothic",
            "document_identifier" => \Prontoo\Domain\Documents\DocumentIdentifierPolicy::document_identifier_display(
                $doc["document_identifier"] ?? "",
            ),
        ];
        @file_put_contents(
            $path . ".json",
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
        @chmod($path . ".json", 0640);
        \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_register_file($c, $doc, $path, $name, $generatedAt);
        return $path;
    }

    public static function document_pdf_catalog_row(string $fileName): ?array
    
    {
    
        if (!\Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_file_name_valid($fileName)) {
            return null;
        }
        try {
            $r = \Prontoo\Runtime\Operational\OperationalComposition::documents()->row('operational.document_pdf.01.document_pdf_catalog_row.01', [$fileName], []);
            if ($r) {
                return $r;
            }
        } catch (Throwable $e) {
            error_log("[Prontoo document_pdf_catalog_row] " . $e->getMessage());
        }
        $metaPath = \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_dir() . "/" . $fileName . ".json";
        if (is_file($metaPath)) {
            $raw = json_decode((string) file_get_contents($metaPath), true);
            if (is_array($raw)) {
                return [
                    "clinic_id" => (int) ($raw["clinic_id"] ?? 0),
                    "document_id" => (int) ($raw["document_id"] ?? 0),
                    "generated_by" => (int) ($raw["generated_by"] ?? 0),
                    "document_identifier" =>
                        (string) ($raw["document_identifier"] ?? ""),
                    "generated_unix" => (int) ($raw["generated_unix"] ?? 0),
                    "file_name" => $fileName,
                    "file_path" => \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_public_path($fileName),
                    "file_sha256" => (string) ($raw["file_sha256"] ?? ""),
                    "file_size" => (int) ($raw["file_size"] ?? 0),
                    "created_at" => (string) ($raw["generated_at"] ?? ""),
                ];
            }
        }
        return null;
    }

    public static function document_pdf_send_file(string $path, string $downloadName): void
    
    {
    
        if (!is_file($path)) {
            throw new RuntimeException("PDF não encontrado.");
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header("Content-Type: application/pdf");
            header(
                'Content-Disposition: attachment; filename="' .
                    str_replace('"', "", basename($downloadName)) .
                    '"',
            );
            header("Content-Length: " . filesize($path));
            header("Cache-Control: private, no-store, max-age=0");
            header("X-Content-Type-Options: nosniff");
        }
        readfile($path);
        exit();
    }

    public static function page_document_pdf_file(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        if (($c["scope"] ?? "") !== "clinic") {
            throw new ProntooHttpError(
                403,
                "PDF disponível apenas no contexto do consultório.",
            );
        }
        $cid = (int) ($c["clinic_id"] ?? 0);
        $uid = (int) ($c["user"]["id"] ?? ($c["user_id"] ?? 0));
        $file = basename((string) ($_GET["file"] ?? ""));
        if (!\Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_file_name_valid($file)) {
            throw new ProntooHttpError(404, "PDF não encontrado.");
        }
        $path = \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_absolute_path($file);
        if (!is_file($path)) {
            throw new ProntooHttpError(404, "PDF não encontrado.");
        }
        $row = \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_catalog_row($file);
        if (!$row || (int) ($row["clinic_id"] ?? 0) !== $cid) {
            throw new ProntooHttpError(
                404,
                "PDF não encontrado para esta credencial.",
            );
        }
        if (\Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_expired_row($row)) {
            \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_delete_pair($file, $cid);
            throw new ProntooHttpError(
                410,
                "PDF expirado. Gere uma nova pré-visualização.",
            );
        }
        $expires = (int) ($_GET["exp"] ?? 0);
        $token = (string) ($_GET["t"] ?? "");
        if (!\Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_token_valid($file, $cid, $uid, $token, $expires)) {
            throw new ProntooHttpError(
                403,
                "Link de PDF expirado ou inválido. Gere uma nova pré-visualização.",
            );
        }
        $doc = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::fetch_document_for_current_user(
            $c,
            (int) ($row["document_id"] ?? 0),
        );
        if (!$doc) {
            throw new ProntooHttpError(
                404,
                "Documento não encontrado para esta credencial.",
            );
        }
        if ((string) ($doc["document_status"] ?? "") !== "emitido") {
            throw new ProntooHttpError(
                403,
                "PDF disponível apenas para documentos já emitidos.",
            );
        }
        $types = \Prontoo\Domain\Documents\DocumentTypePolicy::document_type_options();
        $actualHash = hash_file("sha256", $path) ?: "";
        $knownHash = (string) ($row["file_sha256"] ?? "");
        if ($knownHash !== "" && !hash_equals($knownHash, $actualHash)) {
            throw new ProntooHttpError(
                409,
                "O PDF gerado não confere com o registro de integridade.",
            );
        }
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("documento_pdf_baixado", "documento", (int) $doc["id"], [
            "titulo" => $doc["title"] ?? "",
            "document_type" => (string) ($doc["type_key"] ?? ""),
            "document_type_label" =>
                $types[(string) ($doc["type_key"] ?? "")] ?? "Documento",
            "patient_link_id" => (int) ($doc["patient_link_id"] ?? 0),
            "patient_name" => $doc["patient_name"] ?? "",
            "arquivo" => \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_public_path($file),
            "generated_unix" => (int) ($row["generated_unix"] ?? 0),
            "file_sha256" => $actualHash,
            "audit_body" =>
                "PDF servido por /pdfs/ após validação da sessão, consultório, permissão documental, token temporário, expiração e hash do arquivo.",
        ]);
        \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_send_file($path, $file);
    
    }

    public static function page_document_pdf(): void
    
    {
    
        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::need_login();
        if (($c["scope"] ?? "") !== "clinic") {
            throw new ProntooHttpError(
                403,
                "Documento disponível apenas no contexto do consultório.",
            );
        }
        $doc = \Prontoo\Runtime\Documents\DocumentsRuntimeOperations03::fetch_document_for_current_user($c, (int) ($_GET["id"] ?? 0));
        if (!$doc) {
            http_response_code(404);
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
                "PDF do documento",
                '<div class="empty">Documento não encontrado para esta credencial.</div>',
            );
            return;
        }
        if ((string) ($doc["document_status"] ?? "") !== "emitido") {
            throw new ProntooHttpError(
                403,
                "PDF disponível apenas para documentos já emitidos.",
            );
        }
        if (!is_callable([\Prontoo\Presentation\Documents\DocumentsPresentationOperations01::class, 'document_print_document_shell_html'])) {
            throw new RuntimeException(
                "Motor de impressão documental indisponível.",
            );
        }
        $types = \Prontoo\Domain\Documents\DocumentTypePolicy::document_type_options();
        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("documento_pdf_preparado", "documento", (int) $doc["id"], [
            "titulo" => $doc["title"] ?? "",
            "document_type" => (string) ($doc["type_key"] ?? ""),
            "document_type_label" =>
                $types[(string) ($doc["type_key"] ?? "")] ?? "Documento",
            "patient_link_id" => (int) ($doc["patient_link_id"] ?? 0),
            "patient_name" => $doc["patient_name"] ?? "",
            "audit_body" =>
                "PDF preparado a partir do mesmo HTML/CSS da pré-visualização. O navegador abre o modo de impressão para salvar em PDF ou imprimir com layout idêntico ao conferido.",
        ]);
        if (!headers_sent()) {
            header("Content-Type: text/html; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
        }
        echo \Prontoo\Presentation\Documents\DocumentsPresentationOperations01::document_print_document_shell_html($doc, true, "pdf");
        exit();
    }
}
