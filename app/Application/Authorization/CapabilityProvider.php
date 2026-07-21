<?php
declare(strict_types=1);

namespace Prontoo\Application\Authorization;

use Prontoo\Domain\Authorization\ActionContract;

interface CapabilityProvider
{
    public /* GUIA DE MANUTENÇÃO — contrato sem corpo: Implementa a responsabilidade “grants” dentro do módulo de casos de uso e contratos de aplicação. Dependências esperadas: nenhuma dependência direta detectada estaticamente. */ function grants(
        ActionContract $contract,
        string $capability,
        array $context,
    ): bool;
}
