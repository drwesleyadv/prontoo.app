<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Authorization;

use Prontoo\Application\Authorization\ActionDefinitionRegistry;
use Prontoo\Application\Authorization\ActionDefinitionSource;
use Prontoo\Application\Authorization\Definitions\AdminActionDefinitions;
use Prontoo\Application\Authorization\Definitions\AuthActionDefinitions;
use Prontoo\Application\Authorization\Definitions\DocumentTaskActionDefinitions;
use Prontoo\Application\Authorization\Definitions\FinancialActionDefinitions;
use Prontoo\Application\Authorization\Definitions\PatientActionDefinitions;
use Prontoo\Application\Authorization\Definitions\SchedulingActionDefinitions;
use Prontoo\Application\Authorization\Definitions\WorkforceActionDefinitions;

final class ActionCatalogComposition
{
    private function __construct()
    {
    }

    public static function source(): ActionDefinitionSource
    {
        return new ActionDefinitionRegistry([
            new AuthActionDefinitions(),
            new PatientActionDefinitions(),
            new SchedulingActionDefinitions(),
            new DocumentTaskActionDefinitions(),
            new WorkforceActionDefinitions(),
            new FinancialActionDefinitions(),
            new AdminActionDefinitions(),
        ]);
    }
}
