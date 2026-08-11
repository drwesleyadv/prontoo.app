<?php
declare(strict_types=1);

namespace Prontoo\Application\Financial;

final class FinancialDrawerService
{
    public function __construct(private FinancialDataService $data)
    {
    }

    public function assignUser(
        int $clinicId,
        int $drawerId,
        int $cashierUserId,
        int $administratorUserId,
    ): void {
        $this->data->atomic(function () use (
            $clinicId,
            $drawerId,
            $cashierUserId,
            $administratorUserId,
        ): void {
            $this->data->result(
                'financial.03.link_drawer_user.02',
                [$clinicId, $cashierUserId],
            );
            $this->data->result(
                'financial.03.link_drawer_user.03',
                [
                    $clinicId,
                    $drawerId,
                    $cashierUserId,
                    1,
                    $administratorUserId ?: null,
                ],
            );
        });
    }
}
