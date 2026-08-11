<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Boot;

use PDO;
use Prontoo\Runtime\Modules\RuntimeModuleComposition;
use RuntimeException;
use Throwable;

final class RuntimeBootCoordinator
{
    private static bool $postPasswordMaintenanceDone = false;

    private function __construct()
    {
    }

    public static function schemaMarkerPath(): string
    {
        $dir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path('cache');
        if (!is_dir($dir)) {
            \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_mkdir($dir);
        }
        return $dir . '/prontoo_schema_boot_' . hash(
            'sha256',
            (defined('PRONTOO_SCHEMA_REV') ? PRONTOO_SCHEMA_REV : 'schema') . '|' .
            (defined('PRONTOO_VERSION') ? PRONTOO_VERSION : 'version'),
        ) . '.json';
    }

    public static function readinessMarkerPath(): string
    {
        $dir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path('cache');
        if (!is_dir($dir)) {
            \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_mkdir($dir);
        }
        return $dir . '/prontoo_runtime_readiness_' . hash(
            'sha256',
            (defined('PRONTOO_SCHEMA_REV') ? PRONTOO_SCHEMA_REV : 'schema') . '|' .
            (defined('PRONTOO_VERSION') ? PRONTOO_VERSION : 'version'),
        ) . '.json';
    }

    private static function markerValid(string $file, int $ttlSeconds): bool
    {
        if (!is_file($file) || time() - filemtime($file) > $ttlSeconds) {
            return false;
        }
        $raw = \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_read($file, false);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($json) &&
            !empty($json['ok']) &&
            ($json['rev'] ?? '') === (defined('PRONTOO_SCHEMA_REV') ? PRONTOO_SCHEMA_REV : '') &&
            ($json['version'] ?? '') === (defined('PRONTOO_VERSION') ? PRONTOO_VERSION : '');
    }

    public static function schemaMarkerValid(int $ttlSeconds = 0): bool
    {
        if ($ttlSeconds <= 0) {
            $ttlSeconds = defined('PRONTOO_RUNTIME_DEEP_BOOT_TTL_SECONDS')
                ? (int) PRONTOO_RUNTIME_DEEP_BOOT_TTL_SECONDS
                : 43200;
        }
        return self::markerValid(self::schemaMarkerPath(), $ttlSeconds);
    }

    public static function readinessMarkerValid(int $ttlSeconds = 0): bool
    {
        if ($ttlSeconds <= 0) {
            $ttlSeconds = defined('PRONTOO_RUNTIME_DEEP_BOOT_TTL_SECONDS')
                ? (int) PRONTOO_RUNTIME_DEEP_BOOT_TTL_SECONDS
                : 43200;
        }
        return self::markerValid(self::readinessMarkerPath(), $ttlSeconds);
    }

    private static function writeMarker(string $file, string $mode): void
    {
        $payload = json_encode([
            'ok' => true,
            'mode' => $mode,
            'rev' => PRONTOO_SCHEMA_REV,
            'version' => PRONTOO_VERSION,
            'at' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            throw new RuntimeException('Não foi possível serializar o marcador de runtime.');
        }
        \Prontoo\Infrastructure\SupportRuntime\SupportRuntimeInfrastructureOperations01::prontoo_fs_write($file, $payload);
    }

    public static function markSchemaOk(string $mode): void
    {
        self::writeMarker(self::schemaMarkerPath(), $mode);
    }

    public static function markReadinessOk(string $mode): void
    {
        self::writeMarker(self::readinessMarkerPath(), $mode);
    }

    public static function bootDatabaseForRoute(string $route, bool $publicLight, bool $forceDeep): void
    {
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        if ($publicLight && !$forceDeep) {
            if (class_exists('\Prontoo\Infrastructure\\Integrity\\PiIntegrity')) {
                \Prontoo\Infrastructure\Integrity\PiIntegrity::bootIndexLightcheck();
            }
            return;
        }
        if ($forceDeep) {
            $maintenance = self::runMaintenanceCycle('forced_deep');
            if (empty($maintenance['ok'])) {
                throw new RuntimeException('Manutenção profunda do runtime não pôde ser concluída.');
            }
            return;
        }
        $readiness = self::runReadinessCycle('route_readiness');
        if (empty($readiness['ok'])) {
            throw new RuntimeException('Prontidão mínima do runtime não pôde ser confirmada.');
        }
    }

    private static function configureModelClinic(): void
    {
        $clinicId = (int) (\Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.boot.runtime.boot_database_for_route.01', [], []) ?: 0);
        \Prontoo\Core\Tenant\TenantRegistry::configureModelClinicId($clinicId);
    }

    private static function executeReadinessChecks(
        string $mode,
        int $uid,
        float $startedAt,
        bool $writeMarker,
        string $reason = '',
    ): array {
        $result = ['ok' => false, 'mode' => $mode, 'uid' => $uid, 'steps' => []];
        RuntimeModuleComposition::loader()->loadFullRuntime();
        \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::ensure_runtime_schema_minimum();
        self::configureModelClinic();
        $result['steps'][] = 'schema_contract';
        if (class_exists('\Prontoo\Infrastructure\Integrity\PiIntegrity')) {
            \Prontoo\Infrastructure\Integrity\PiIntegrity::bootIndexLightcheck();
            $result['steps'][] = 'integrity_lightcheck';
        }
        if ($writeMarker) {
            self::markReadinessOk($mode);
        }
        $result['ok'] = true;
        $result['ran'] = true;
        if ($reason !== '') {
            $result['reason'] = $reason;
        }
        $result['duration_ms'] = (int) round(
            (microtime(true) - $startedAt) * 1000,
            0,
            \RoundingMode::HalfAwayFromZero,
        );
        return $result;
    }

    public static function runReadinessCycle(string $mode = 'route_readiness', int $uid = 0): array
    {
        $startedAt = microtime(true);
        $result = ['ok' => false, 'mode' => $mode, 'uid' => $uid, 'steps' => []];
        if (self::readinessMarkerValid()) {
            return array_merge($result, ['ok' => true, 'ran' => false, 'reason' => 'readiness_marker_fresh']);
        }
        $lockDir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path('cache/locks');
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0750, true) && !is_dir($lockDir)) {
            error_log('[Prontoo runtime readiness] lock indisponível; executando prontidão sem cache.');
            return self::executeReadinessChecks(
                $mode,
                $uid,
                $startedAt,
                false,
                'readiness_uncached_lock_unavailable',
            );
        }
        $lockHandle = @fopen(
            $lockDir . '/runtime-readiness-' . hash('sha256', PRONTOO_SCHEMA_REV) . '.lock',
            'c+',
        );
        if (!is_resource($lockHandle)) {
            error_log('[Prontoo runtime readiness] arquivo de lock indisponível; executando prontidão sem cache.');
            return self::executeReadinessChecks(
                $mode,
                $uid,
                $startedAt,
                false,
                'readiness_uncached_lock_unavailable',
            );
        }
        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            error_log('[Prontoo runtime readiness] lock não adquirido; executando prontidão sem cache.');
            return self::executeReadinessChecks(
                $mode,
                $uid,
                $startedAt,
                false,
                'readiness_uncached_lock_failed',
            );
        }
        try {
            if (self::readinessMarkerValid()) {
                return array_merge($result, ['ok' => true, 'ran' => false, 'reason' => 'readiness_completed_concurrently']);
            }
            return self::executeReadinessChecks($mode, $uid, $startedAt, true);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public static function runMaintenanceCycle(string $mode = 'forced_deep', int $uid = 0): array
    {
        $startedAt = microtime(true);
        $result = ['ok' => false, 'mode' => $mode, 'uid' => $uid, 'steps' => []];
        $lockDir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path('cache/locks');
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0750, true) && !is_dir($lockDir)) {
            return $result + ['ran' => false, 'reason' => 'maintenance_lock_unavailable'];
        }
        $lockHandle = @fopen(
            $lockDir . '/runtime-maintenance-' . hash('sha256', PRONTOO_SCHEMA_REV) . '.lock',
            'c+',
        );
        if (!is_resource($lockHandle) || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if (is_resource($lockHandle)) {
                fclose($lockHandle);
            }
            return array_merge($result, ['ok' => true, 'ran' => false, 'reason' => 'maintenance_in_progress']);
        }
        try {
            if (self::schemaMarkerValid()) {
                return array_merge($result, ['ok' => true, 'ran' => false, 'reason' => 'maintenance_already_completed']);
            }
            RuntimeModuleComposition::loader()->loadFullRuntime();
            \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::ensure_runtime_schema_minimum();
            self::configureModelClinic();
            $result['steps'][] = 'schema_contract';
            if (is_callable([\Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::class, 'maestro_ensure_schema'])) {
                \Prontoo\Runtime\Maestro\MaestroRuntimeOperations01::maestro_ensure_schema();
                $result['steps'][] = 'maestro_contract';
            }
            if (class_exists('\Prontoo\Infrastructure\\Integrity\\PiIntegrity')) {
                \Prontoo\Infrastructure\Integrity\PiIntegrity::bootIndexAutotest();
                $result['steps'][] = 'integrity_autotest';
            }
            \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::runtime_self_check();
            $result['steps'][] = 'runtime_self_check';
            if (is_callable([\Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::class, 'document_pdf_cleanup_due'])) {
                \Prontoo\Runtime\DocumentPdf\DocumentPdfRuntimeOperations01::document_pdf_cleanup_due();
                $result['steps'][] = 'pdf_cleanup';
            }
            self::markSchemaOk($mode);
            self::markReadinessOk($mode);
            $result['ok'] = true;
            $result['ran'] = true;
            $result['duration_ms'] = (int) round(
                (microtime(true) - $startedAt) * 1000,
                0,
                \RoundingMode::HalfAwayFromZero,
            );
            return $result;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public static function postPasswordMaintenance(int $uid): array
    {
        if (self::$postPasswordMaintenanceDone) {
            return ['ok' => true, 'ran' => false, 'reason' => 'already_ran_request'];
        }
        self::$postPasswordMaintenanceDone = true;
        if (self::readinessMarkerValid()) {
            return [
                'ok' => true,
                'ran' => false,
                'reason' => 'runtime_readiness_fresh',
                'uid' => $uid,
                'steps' => [],
            ];
        }
        try {
            $result = self::runReadinessCycle('post_password_login', $uid);
            if (empty($result['ok'])) {
                throw new RuntimeException('Prontidão mínima do runtime não pôde ser confirmada após a senha.');
            }
            if (is_callable([\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::class, 'audit'])) {
                try {
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit('login_manutencao_pos_senha', 'plataforma', $uid, [
                        'duration_ms' => (int) ($result['duration_ms'] ?? 0),
                        'steps' => $result['steps'] ?? [],
                        'audit_body' => 'Prontidão mínima do runtime confirmada após a senha; tarefas pesadas permanecem fora do caminho crítico de autenticação.',
                    ]);
                } catch (Throwable $error) {
                    error_log('[Prontoo post password readiness audit] ' . $error->getMessage());
                }
            }
            return $result;
        } catch (Throwable $error) {
            error_log('[Prontoo post password readiness] ' . $error->getMessage());
            if (is_callable([\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::class, 'audit'])) {
                try {
                    \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit('login_manutencao_pos_senha_falhou', 'plataforma', $uid, [
                        'erro_hash' => hash('sha256', $error->getMessage()),
                        'audit_body' => 'A verificação mínima de prontidão pós-senha falhou antes da liberação da sessão autenticada.',
                    ]);
                } catch (Throwable $auditError) {
                    error_log('[Prontoo post password readiness fail audit] ' . $auditError->getMessage());
                }
            }
            throw $error;
        }
    }

    public static function flushIntegrityBeforeRender(): void
    {
        try {
            if (is_callable([\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::class, 'pdo']) && \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
                if (!\Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_in_transaction() &&
                    class_exists('\Prontoo\Infrastructure\\Integrity\\PiIntegrity') &&
                    method_exists('\Prontoo\Infrastructure\\Integrity\\PiIntegrity', 'flushFastEvents')) {
                    \Prontoo\Infrastructure\Integrity\PiIntegrity::flushFastEvents();
                }
            }
        } catch (Throwable $error) {
            error_log('[Prontoo render integrity flush] ' . $error->getMessage());
        }
    }
}
