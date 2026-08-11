<?php
declare(strict_types=1);

namespace Prontoo\Runtime\SecurityAccess;

use Closure;
use Prontoo\Application\Identity\ClinicOnboardingService;
use Prontoo\Application\Identity\ClinicRegistrationService;
use Prontoo\Application\Identity\IdentityDataPort;
use Prontoo\Application\Identity\IdentityDataService;
use Prontoo\Application\Identity\UserCredentialService;
use Prontoo\Application\Identity\UserPermissionService;
use Prontoo\Application\SecurityAccess\MfaRecordPort;
use Prontoo\Application\SecurityAccess\MfaRecordService;
use Prontoo\Application\SecurityAccess\SecurityIncidentService;
use Prontoo\Infrastructure\Identity\PdoIdentityDataRepository;
use Prontoo\Infrastructure\SecurityAccess\PdoMfaRecordRepository;
use Prontoo\Infrastructure\SecurityAccess\PdoSecurityIncidentRepository;
use Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01;

final class SecurityAccessComposition
{
    private static ?IdentityDataService $data = null;
    private static ?ClinicOnboardingService $clinicOnboarding = null;
    private static ?ClinicRegistrationService $clinicRegistration = null;
    private static ?UserCredentialService $userCredential = null;
    private static ?UserPermissionService $userPermission = null;
    private static ?SecurityIncidentService $securityIncident = null;
    private static ?MfaRecordService $mfaRecord = null;

    private function __construct()
    {
    }

    public static function dataService(): IdentityDataService
    {
        return self::$data ??= new IdentityDataService(
            new PdoIdentityDataRepository(
                Closure::fromCallable([DatabaseSchemaRuntimeOperations01::class, 'q']),
            ),
            static fn(\Throwable $error): bool => error_log(
                '[Prontoo identity data] ' . $error->getMessage(),
            ),
        );
    }

    public static function configureDataPort(IdentityDataPort $port): void
    {
        self::$data = new IdentityDataService($port);
        self::$clinicOnboarding = null;
        self::$clinicRegistration = null;
        self::$userCredential = null;
        self::$userPermission = null;
    }

    public static function clinicRegistration(): ClinicRegistrationService
    {
        return self::$clinicRegistration ??= new ClinicRegistrationService(
            self::dataService(),
        );
    }

    public static function clinicOnboarding(): ClinicOnboardingService
    {
        return self::$clinicOnboarding ??= new ClinicOnboardingService(
            self::dataService(),
        );
    }

    public static function userCredential(): UserCredentialService
    {
        return self::$userCredential ??= new UserCredentialService(
            self::dataService(),
        );
    }

    public static function userPermission(): UserPermissionService
    {
        return self::$userPermission ??= new UserPermissionService(
            self::dataService(),
        );
    }

    public static function securityIncidentService(): SecurityIncidentService
    {
        return self::$securityIncident ??= new SecurityIncidentService(
            new PdoSecurityIncidentRepository(),
        );
    }

    public static function mfaRecordService(): MfaRecordService
    {
        return self::$mfaRecord ??= new MfaRecordService(
            new PdoMfaRecordRepository(
                Closure::fromCallable([DatabaseSchemaRuntimeOperations01::class, 'q']),
            ),
        );
    }

    public static function configureMfaRecordPort(MfaRecordPort $port): void
    {
        self::$mfaRecord = new MfaRecordService($port);
    }
}
