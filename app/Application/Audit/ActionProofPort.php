<?php
declare(strict_types=1);

namespace Prontoo\Application\Audit;

use Prontoo\Core\Invariant\Decision;

interface ActionProofPort
{
    public /* GUIA DE MANUTENÇÃO — contrato sem corpo: Valida e executa a mutação “write”, preservando as invariantes do módulo de casos de uso e contratos de aplicação. Dependências esperadas: nenhuma dependência direta detectada estaticamente. */ function write(string $route, array $context, Decision $decision): bool;
}
