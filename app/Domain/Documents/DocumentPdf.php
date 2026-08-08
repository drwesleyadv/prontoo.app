<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/Runtime/Autoload/ProntooAutoloader.php';
function document_pdf_link(int $docId, string $class = "ghost small"): string
{
    return \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_link($docId, $class);
}
function document_pdf_public_router_dir(): string
{
    return \Prontoo\Infrastructure\DocumentPdf\DocumentPdfInfrastructureOperations01::document_pdf_public_router_dir();
}
function document_pdf_storage_dir(): string
{
    return \Prontoo\Infrastructure\DocumentPdf\DocumentPdfInfrastructureOperations01::document_pdf_storage_dir();
}
function document_pdf_dir(int $cid = 0): string
{
    return \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_dir($cid);
}
function document_pdf_cleanup(?int $cid = null): void
{
    \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_cleanup($cid);
}

function document_pdf_cleanup_due(): void
{
    \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_cleanup_due();
}
function document_pdf_safe_code(array $doc): string
{
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_safe_code($doc);
}
function document_pdf_file_name(
    array $doc,
    ?int $generatedAt = null,
    int $clinicId = 0,
): string {
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_file_name($doc, $generatedAt, $clinicId);
}
function document_pdf_file_name_valid(string $file): bool
{
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_file_name_valid($file);
}
function document_pdf_public_path(string $fileName): string
{
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_public_path($fileName);
}
function document_pdf_ttl_seconds(): int
{
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_ttl_seconds();
}
function document_pdf_token_secret(): string
{
    return \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_token_secret();
}
function document_pdf_token(
    string $fileName,
    int $cid,
    int $uid,
    int $expires,
): string {
    return \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_token($fileName, $cid, $uid, $expires);
}
function document_pdf_token_valid(
    string $fileName,
    int $cid,
    int $uid,
    string $token,
    int $expires,
): bool {
    return \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_token_valid($fileName, $cid, $uid, $token, $expires);
}
function document_pdf_row_timestamp(array $row): int
{
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_row_timestamp($row);
}

function document_pdf_expired_row(array $row): bool
{
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_expired_row($row);
}
function document_pdf_delete_pair(string $fileName, int $cid): void
{
    \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_delete_pair($fileName, $cid);
}

function document_pdf_public_url(
    string $fileName,
    ?string $token = null,
    ?int $expires = null,
): string {
    return \Prontoo\Presentation\DocumentPdf\DocumentPdfPresentationOperations01::document_pdf_public_url($fileName, $token, $expires);
}

function document_pdf_absolute_path(string $fileName): string
{
    return \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_absolute_path($fileName);
}
function document_pdf_text_width(string $text, float $fontSize = 12.0): float
{
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_text_width($text, $fontSize);
}
function document_pdf_html_blocks(string $html): array
{
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_html_blocks($html);
}
function document_pdf_wrap_text(
    string $text,
    float $maxWidth,
    float $fontSize = 12.0,
): array {
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_wrap_text($text, $maxWidth, $fontSize);
}
function document_pdf_escape(string $text): string
{
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_escape($text);
}
function document_pdf_identifier_footer_stream(
    string $identifier,
    float $pageW,
    float $marginLeft,
    float $marginRight,
    float $marginBottom,
): string {
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_identifier_footer_stream($identifier, $pageW, $marginLeft, $marginRight, $marginBottom);
}
function document_pdf_build_simple(string $html, array $meta = []): string
{
    return \Prontoo\Domain\DocumentPdf\DocumentPdfDomainOperations01::document_pdf_build_simple($html, $meta);
}
function document_pdf_register_file(
    array $c,
    array $doc,
    string $path,
    string $name,
    int $generatedAt,
): void {
    \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_register_file($c, $doc, $path, $name, $generatedAt);
}
function document_pdf_create_file(array $c, array $doc): string
{
    return \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_create_file($c, $doc);
}
function document_pdf_catalog_row(string $fileName): ?array
{
    return \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_catalog_row($fileName);
}
function document_pdf_send_file(string $path, string $downloadName): void
{
    \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_send_file($path, $downloadName);
}
function page_document_pdf_file(): void
{
    \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::page_document_pdf_file();
}

function page_document_pdf(): void
{
    \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::page_document_pdf();
}
