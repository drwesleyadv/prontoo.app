from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def write(path: str, content: str) -> None:
    p = ROOT / path
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(content)

write('app/query.budgets.json', '''{
    "schema": "prontoo-query-budgets-v1",
    "policy": "critical_repository_query_counts_are_measured_on_real_mysql_and_must_not_increase",
    "scenarios": {
        "patient_registration_read": {
            "max_selects": 1
        },
        "patient_read_bundle": {
            "max_selects": 3
        }
    }
}
''')

write('tools/query-budget-contract-check', '''<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
$contract = json_decode(
    (string) file_get_contents($root . '/app/query.budgets.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$ceilings = [
    'patient_registration_read' => 1,
    'patient_read_bundle' => 3,
];
$failures = [];
if (($contract['schema'] ?? '') !== 'prontoo-query-budgets-v1') {
    $failures[] = 'schema';
}
foreach ($ceilings as $scenario => $maximum) {
    $actual = (int) ($contract['scenarios'][$scenario]['max_selects'] ?? -1);
    if ($actual < 0 || $actual > $maximum) {
        $failures[] = 'budget_weakened:' . $scenario;
    }
}
$result = [
    'ok' => $failures === [],
    'policy' => (string) ($contract['policy'] ?? ''),
    'scenarios' => array_keys($ceilings),
    'failures' => $failures,
];
fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($failures === [] ? 0 : 1);
''')

write('tools/mysql-query-budget-check', '''<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
$tempRoot = sys_get_temp_dir() . '/prontoo-query-budget-' . getmypid();
if (!is_dir($tempRoot) && !mkdir($tempRoot, 0750, true) && !is_dir($tempRoot)) {
    throw new RuntimeException('Não foi possível preparar o config temporário do query budget.');
}
$configPath = $tempRoot . '/config.php';
$config = [
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('DB_DATABASE') ?: 'prontoo_schema',
    'db_user' => getenv('DB_USERNAME') ?: 'root',
    'db_pass' => getenv('DB_PASSWORD') ?: 'root',
    'secret' => 'prontoo-query-budget-ci-secret',
    'debug' => true,
    'app_env' => 'development',
];
file_put_contents($configPath, '<?php return ' . var_export($config, true) . ';' . PHP_EOL, LOCK_EX);
putenv('PRONTOO_CONFIG_PATH=' . $configPath);

require $root . '/app/prontoo.php';
\Prontoo\Runtime\Modules\RuntimeModuleComposition::loader()->loadFullRuntime();
$pdo = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
$repository = new \Prontoo\Infrastructure\Patients\PdoPatientReadRepository();
$contract = json_decode(
    (string) file_get_contents($root . '/app/query.budgets.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);

$selects = static function (PDO $pdo): int {
    $row = $pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch(PDO::FETCH_NUM);
    return (int) ($row[1] ?? 0);
};
$measure = static function (callable $operation) use ($pdo, $selects): int {
    $before = $selects($pdo);
    $operation();
    $after = $selects($pdo);
    return max(0, $after - $before);
};

$registration = $measure(static function () use ($repository): void {
    $repository->appointmentRegistration(987654321, 987654321);
});
$bundle = $measure(static function () use ($repository): void {
    $repository->appointmentRegistration(987654321, 987654321);
    $repository->activeLegalGuardians(987654321, 987654321);
    $repository->hasActiveLegalGuardian(987654321, 987654321);
});
$actual = [
    'patient_registration_read' => $registration,
    'patient_read_bundle' => $bundle,
];
$failures = [];
foreach ($actual as $scenario => $count) {
    $maximum = (int) ($contract['scenarios'][$scenario]['max_selects'] ?? -1);
    if ($maximum < 0 || $count > $maximum) {
        $failures[] = $scenario . ':' . $count . '>' . $maximum;
    }
}
$result = [
    'ok' => $failures === [],
    'policy' => 'real-mysql-query-budget-v1',
    'metric' => 'Com_select',
    'actual' => $actual,
    'budgets' => $contract['scenarios'] ?? [],
    'failures' => $failures,
];
fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
@unlink($configPath);
@rmdir($tempRoot);
exit($failures === [] ? 0 : 1);
''')

quality = ROOT / 'tools/quality-gate'
source = quality.read_text()
needle = "$run('performance-budget-contract', 'tools/performance-budget-check');\n"
addition = needle + "$run('query-budget-contract', 'tools/query-budget-contract-check');\n"
if 'query-budget-contract' not in source:
    if source.count(needle) != 1:
        raise RuntimeError('quality gate insertion point not found')
    quality.write_text(source.replace(needle, addition, 1))

print('phase14 applied')
