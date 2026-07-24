<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

use Prontoo\Core\Architecture\ArchitectureVerifier;
use Prontoo\Runtime\LayeredKernel;

if (!class_exists('ProntooHttpError')) {
    class ProntooHttpError extends RuntimeException
    {
        public function __construct(public int $status, string $message)
        {
            /*
             * GUIA DE MANUTENÇÃO — ProntooHttpError::__construct
             * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
             * Local arquitetural: tools/architecture-check.php (ferramentas de certificação e manutenção).
             * Chamadores detectados: `page_admin_errors`, `page_admin_security`, `Core.Database.SqlScopeGuard::guard`, `Core.Database.SqlScopeGuard::assertScopedWrite`, `Core.Invariant.Context.MaestroContextInvariant::assertWrite`, `Core.Invariant.Context.TaskContextInvariant::deny`, `Core.Invariant.Mutation.MutationInvariant::deny`, `Core.Invariant.Relation.ForeignKeyGraph::deny` e mais 17.
             * Dependências chamadas: `parent::__construct`.
             * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
             * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
             */
            parent::__construct($message);
        }
    }
}

$root = dirname(__DIR__);
if (!defined('PRONTOO_ROOT')) {
    define('PRONTOO_ROOT', $root);
}
$versionMetadata = json_decode((string) file_get_contents($root . '/version.json'), true, 512, JSON_THROW_ON_ERROR);
if (!defined('PRONTOO_VERSION')) {
    define('PRONTOO_VERSION', (string) ($versionMetadata['version'] ?? ''));
}

require_once $root . '/app/bootstrap_architecture.php';
require_once $root . '/app/Support/ModuleLoader.php';
require_once $root . '/app/Runtime/Runner.php';

$architecture = ArchitectureVerifier::report($root, true);
$selfTest = LayeredKernel::logicSelfTest($root);
$dashboardIconCss = (string) file_get_contents(
    $root . '/public/assets/design-system.css',
);
$dashboardIconFailures = [];
if (str_contains(
    $dashboardIconCss,
    '.stat-card span,.ds-kpi span,.notice-kpi span,.kpi-card span,.mini-stat span{',
)) {
    $dashboardIconFailures[] = 'broad_kpi_span_selector';
}
foreach ([
    '.stat-card span:not(.material-symbols-rounded):not(.pt-icon-glyph)',
    '.ds-kpi span:not(.material-symbols-rounded):not(.pt-icon-glyph)',
    '.notice-kpi span:not(.material-symbols-rounded):not(.pt-icon-glyph)',
    '.kpi-card span:not(.material-symbols-rounded):not(.pt-icon-glyph)',
    '.mini-stat span:not(.material-symbols-rounded):not(.pt-icon-glyph)',
    '.manager-action > .pt-icon-glyph',
    '.material-symbols-rounded{font-family:"Material Symbols Rounded"',
] as $requiredIconContract) {
    if (!str_contains($dashboardIconCss, $requiredIconContract)) {
        $dashboardIconFailures[] = $requiredIconContract;
    }
}
$dashboardIconCascade = [
    'ok' => $dashboardIconFailures === [],
    'failed' => $dashboardIconFailures,
];
$result = [
    'ok' =>
        !empty($architecture['ok']) &&
        !empty($selfTest['ok']) &&
        !empty($dashboardIconCascade['ok']),
    'architecture' => $architecture,
    'self_test' => $selfTest,
    'dashboard_icon_cascade' => $dashboardIconCascade,
];

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
), PHP_EOL;

exit($result['ok'] ? 0 : 1);
