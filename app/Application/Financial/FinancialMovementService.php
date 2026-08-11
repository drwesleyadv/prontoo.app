<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

use Closure;
use RuntimeException;

final class FinancialMovementService
{
    public function __construct(private FinancialDataService $data)
    {
    }

    public function update(
        int $clinicId,
        int $movementId,
        string $type,
        int $amountCents,
        ?int $fromLocationId,
        ?int $toLocationId,
        ?int $sessionId,
        int $userId,
        string $title,
        string $paymentMethod,
        string $notes,
        string $status,
        Closure $validate,
    ): void {
        $this->data->atomic(function () use (
            $clinicId,
            $movementId,
            $type,
            $amountCents,
            $fromLocationId,
            $toLocationId,
            $sessionId,
            $userId,
            $title,
            $paymentMethod,
            $notes,
            $status,
            $validate,
        ): void {
            $existing = $this->data->row(
                'financial.06.update_existing_movement.01',
                [$movementId, $clinicId],
            );
            if (!$existing) {
                throw new RuntimeException('Movimento financeiro não encontrado.');
            }
            $validate(
                $clinicId,
                $type,
                $amountCents,
                $fromLocationId,
                $toLocationId,
                $sessionId,
                $status,
                $movementId,
            );
            $this->data->result(
                'financial.06.update_existing_movement.02',
                [
                    $type,
                    $status,
                    $amountCents,
                    trim($paymentMethod) ?: null,
                    $fromLocationId ?: null,
                    $toLocationId ?: null,
                    $sessionId ?: null,
                    trim($title),
                    trim($notes) ?: null,
                    $status,
                    $userId ?: null,
                    $status,
                    $movementId,
                    $clinicId,
                ],
            );
        });
    }

    public function create(
        int $clinicId,
        string $type,
        int $amountCents,
        ?int $fromLocationId,
        ?int $toLocationId,
        ?int $sessionId,
        int $userId,
        string $title,
        string $paymentMethod,
        string $notes,
        string $status,
        string $sourceEntity,
        int $sourceId,
        Closure $validate,
        Closure $defaultTitle,
    ): int {
        return (int) $this->data->atomic(function () use (
            $clinicId,
            $type,
            $amountCents,
            $fromLocationId,
            $toLocationId,
            $sessionId,
            $userId,
            $title,
            $paymentMethod,
            $notes,
            $status,
            $sourceEntity,
            $sourceId,
            $validate,
            $defaultTitle,
        ): int {
            $validate(
                $clinicId,
                $type,
                $amountCents,
                $fromLocationId,
                $toLocationId,
                $sessionId,
                $status,
                0,
            );
            $normalizedTitle = trim($title) ?: (string) $defaultTitle($type);
            $normalizedMethod = mb_substr(trim($paymentMethod), 0, 40);
            $this->data->result(
                'financial.06.create_movement.01',
                [
                    $clinicId,
                    $type,
                    $status,
                    $amountCents,
                    $normalizedMethod ?: null,
                    $fromLocationId ?: null,
                    $toLocationId ?: null,
                    $sessionId ?: null,
                    $sourceEntity ?: null,
                    $sourceId ?: null,
                    $normalizedTitle,
                    trim($notes) ?: null,
                    $userId ?: null,
                    $status === 'confirmed' ? ($userId ?: null) : null,
                    $status,
                ],
            );
            return $this->data->lastInsertId();
        });
    }
}
