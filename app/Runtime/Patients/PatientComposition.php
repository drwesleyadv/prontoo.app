<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Patients;

use Prontoo\Application\Patients\PatientContactCommandService;
use Prontoo\Application\Patients\PatientReadService;
use Prontoo\Application\Patients\PatientReceptionHistoryReadService;
use Prontoo\Application\Patients\PatientTabCommandService;
use Prontoo\Infrastructure\Patients\PdoPatientContactCommandRepository;
use Prontoo\Infrastructure\Patients\PdoPatientReadRepository;
use Prontoo\Infrastructure\Patients\PdoPatientReceptionHistoryReadRepository;
use Prontoo\Infrastructure\Patients\PdoPatientTabCommandRepository;
use Prontoo\Infrastructure\Patients\PatientTabReadRepository;

final class PatientComposition
{
    private static ?PatientReadService $read = null;
    private static ?PatientReceptionHistoryReadService $history = null;
    private static ?PatientTabCommandService $tabs = null;
    private static ?PatientContactCommandService $contact = null;

    private function __construct()
    {
    }

    public static function activeTabs(int $clinicId, int $patientId): array
    {
        return PatientTabReadRepository::activeTabs($clinicId, $patientId);
    }

    public static function tabLabel(int $tabId): string
    {
        return PatientTabReadRepository::labelById($tabId);
    }

    public static function readService(): PatientReadService
    {
        return self::$read ??= new PatientReadService(new PdoPatientReadRepository());
    }

    public static function registrationBlockReason(int $clinicId, int $patientId): ?string
    {
        return self::readService()->appointmentRegistrationBlockReason(
            $clinicId,
            $patientId,
            static fn(array $patient): bool => \patient_invoice_registration_complete($patient),
            static fn(array $patient): string => \patient_invoice_registration_alert_message($patient),
        );
    }

    public static function legalGuardians(int $clinicId, int $patientId): array
    {
        return self::readService()->legalGuardians(
            $clinicId,
            $patientId,
            static fn(string $cpf): string => \only_digits($cpf),
            static fn(string $relationship): string => \normalize_guardian_relationship($relationship),
        );
    }

    public static function hasLegalGuardian(int $clinicId, int $patientId): bool
    {
        return self::readService()->hasLegalGuardian($clinicId, $patientId);
    }

    public static function historyService(): PatientReceptionHistoryReadService
    {
        return self::$history ??= new PatientReceptionHistoryReadService(
            new PdoPatientReceptionHistoryReadRepository(),
        );
    }

    public static function receptionHistory(
        int $clinicId,
        int $patientId,
        int $personId,
        string $phoneDigits,
    ): array {
        return self::historyService()->read($clinicId, $patientId, $personId, $phoneDigits);
    }

    public static function tabCommandService(): PatientTabCommandService
    {
        return self::$tabs ??= new PatientTabCommandService(new PdoPatientTabCommandRepository());
    }

    public static function createTab(
        int $clinicId,
        int $patientId,
        string $label,
        string $iconName,
        int $userId,
    ): array {
        return self::tabCommandService()->create($clinicId, $patientId, $label, $iconName, $userId);
    }

    public static function contactCommandService(): PatientContactCommandService
    {
        return self::$contact ??= new PatientContactCommandService(
            new PdoPatientContactCommandRepository(),
        );
    }

    public static function updateContact(
        int $clinicId,
        int $patientId,
        int $userId,
        array $contact,
    ): array {
        return self::contactCommandService()->update($clinicId, $patientId, $userId, $contact);
    }
}
