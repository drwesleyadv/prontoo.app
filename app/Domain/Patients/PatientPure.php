<?php
declare(strict_types=1);

namespace Prontoo\Domain\Patients;

final class PatientPure
{
    public static function cpfBr(string $raw, string $digits): string
    {
        return strlen($digits) === 11
            ? substr($digits, 0, 3)
                . '.'
                . substr($digits, 3, 3)
                . '.'
                . substr($digits, 6, 3)
                . '-'
                . substr($digits, 9, 2)
            : $raw;
    }

    public static function cleanTabLabel(string $label): string
    {
        $label = trim(preg_replace('/\s+/u', ' ', strip_tags($label)) ?? '');
        return mb_substr($label, 0, 60, 'UTF-8');
    }

    public static function tabRecordType(int $tabId): string
    {
        return 'tab_' . max(0, $tabId);
    }

    public static function tabKey(int $tabId): string
    {
        return 'extra' . max(0, $tabId);
    }

    public static function guardianRelationshipOptions(): array
    {
        return [
            'mae' => 'Mãe',
            'pai' => 'Pai',
            'tutor' => 'Tutor(a)',
            'guardiao' => 'Guardião(ã)',
            'avo' => 'Avó/Avô com guarda ou autorização',
            'responsavel_judicial' => 'Responsável por decisão judicial',
            'outro' => 'Outro vínculo documentado',
        ];
    }

    public static function normalizeGuardianRelationship(string $value): string
    {
        $value = preg_replace('/[^a-z0-9_]+/i', '', strtolower(trim($value))) ?: '';
        return array_key_exists($value, self::guardianRelationshipOptions())
            ? $value
            : 'outro';
    }

    public static function ageYears(null|string|int $birth): ?int
    {
        $birth = trim((string) ($birth ?? ''));
        if ($birth === '') {
            return null;
        }
        try {
            $date = preg_match('/^-?\d+$/', $birth)
                ? new \DateTimeImmutable('@' . (int) $birth)->setTimezone(new \DateTimeZone('UTC'))
                : new \DateTimeImmutable($birth);
            $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
            if ($date > $today) {
                return null;
            }
            return (int) $date->diff($today)->y;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isMinor(array $patient): bool
    {
        $age = self::ageYears($patient['birth_date'] ?? null);
        return $age !== null && $age < 18;
    }
}
