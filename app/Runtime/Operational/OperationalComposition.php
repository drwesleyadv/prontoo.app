<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Operational;

use Closure;
use Prontoo\Application\Operational\OperationalDataPort;
use Prontoo\Application\Operational\OperationalDatabaseContextPort;
use Prontoo\Application\Operational\OperationalSchemaPort;
use Prontoo\Application\Operational\OperationalUseCaseService;
use Prontoo\Infrastructure\Operational\PdoOperationalDataRepository;
use Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01;

final class OperationalComposition
{
    private static ?OperationalDataPort $port = null;
    private static ?OperationalSchemaPort $schema = null;
    private static ?OperationalDatabaseContextPort $databaseContext = null;
    private static array $services = [];

    private function __construct()
    {
    }

    public static function tasks(): OperationalUseCaseService
    {
        return self::service('tasks');
    }

    public static function appointments(): OperationalUseCaseService
    {
        return self::service('appointments');
    }

    public static function patients(): OperationalUseCaseService
    {
        return self::service('patients');
    }

    public static function documents(): OperationalUseCaseService
    {
        return self::service('documents');
    }

    public static function leads(): OperationalUseCaseService
    {
        return self::service('leads');
    }

    public static function maestro(): OperationalUseCaseService
    {
        return self::service('maestro');
    }

    public static function administration(): OperationalUseCaseService
    {
        return self::service('administration');
    }

    public static function platform(): OperationalUseCaseService
    {
        return self::service('platform');
    }

    public static function configurePorts(
        OperationalDataPort $port,
        OperationalSchemaPort $schema,
        OperationalDatabaseContextPort $databaseContext,
    ): void
    {
        self::$port = $port;
        self::$schema = $schema;
        self::$databaseContext = $databaseContext;
        self::$services = [];
    }

    private static function service(string $scope): OperationalUseCaseService
    {
        return self::$services[$scope] ??= new OperationalUseCaseService(
            self::port(),
            self::schema(),
            self::databaseContext(),
            $scope,
            static fn(\Throwable $error): bool => error_log(
                '[Prontoo operational data] ' . $error->getMessage(),
            ),
        );
    }

    private static function port(): OperationalDataPort
    {
        return self::$port ??= self::repository();
    }

    private static function schema(): OperationalSchemaPort
    {
        return self::$schema ??= self::repository();
    }

    private static function databaseContext(): OperationalDatabaseContextPort
    {
        return self::$databaseContext ??= self::repository();
    }

    private static function repository(): PdoOperationalDataRepository
    {
        static $repository;
        return $repository ??= new PdoOperationalDataRepository(
            Closure::fromCallable([DatabaseSchemaRuntimeOperations01::class, 'q']),
        );
    }
}
