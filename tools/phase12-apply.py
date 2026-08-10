from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def write(path: str, content: str) -> None:
    p = ROOT / path
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(content)

def replace_once(path: str, old: str, new: str) -> None:
    p = ROOT / path
    source = p.read_text()
    if new in source:
        return
    if source.count(old) != 1:
        raise RuntimeError(f'{path}: trecho esperado não localizado uma única vez')
    p.write_text(source.replace(old, new, 1))

write('app/Application/Financial/FinancialGoalPort.php', '''<?php
declare(strict_types=1);

namespace Prontoo\\Application\\Financial;

interface FinancialGoalPort
{
    public function save(
        int $clinicId,
        string $monthKey,
        int $targetCents,
        string $baseMetric,
        bool $shareWithTeam,
        int $userId,
    ): void;
}
''')

write('app/Application/Financial/FinancialGoalService.php', '''<?php
declare(strict_types=1);

namespace Prontoo\\Application\\Financial;

use InvalidArgumentException;

final class FinancialGoalService
{
    public function __construct(private FinancialGoalPort $port)
    {
    }

    public function save(
        int $clinicId,
        string $monthKey,
        int $targetCents,
        string $baseMetric,
        bool $shareWithTeam,
        int $userId,
    ): array {
        if ($clinicId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Contexto financeiro inválido.');
        }
        if (!preg_match('/^\\d{4}-\\d{2}$/', $monthKey)) {
            throw new InvalidArgumentException('Competência financeira inválida.');
        }
        if ($targetCents < 0) {
            throw new InvalidArgumentException('A meta financeira não pode ser negativa.');
        }
        $baseMetric = in_array($baseMetric, ['prevista', 'efetivada'], true)
            ? $baseMetric
            : 'efetivada';
        $this->port->save(
            $clinicId,
            $monthKey,
            $targetCents,
            $baseMetric,
            $shareWithTeam,
            $userId,
        );
        return [
            'target_cents' => $targetCents,
            'base_metric' => $baseMetric,
            'share_with_team' => $shareWithTeam ? 1 : 0,
            'month_key' => $monthKey,
        ];
    }
}
''')

write('app/Infrastructure/Financial/PdoFinancialGoalRepository.php', '''<?php
declare(strict_types=1);

namespace Prontoo\\Infrastructure\\Financial;

use Prontoo\\Application\\Financial\\FinancialGoalPort;
use Prontoo\\Core\\Temporal\\PiTime;
use Prontoo\\Infrastructure\\DatabaseSchema\\DatabaseSchemaInfrastructureOperations01;

final class PdoFinancialGoalRepository implements FinancialGoalPort
{
    public function save(
        int $clinicId,
        string $monthKey,
        int $targetCents,
        string $baseMetric,
        bool $shareWithTeam,
        int $userId,
    ): void {
        [$sql, $params] = PiTime::prepareRuntimeQuery(
            "INSERT INTO pi_financial_goals (clinic_id,month_key,target_cents,base_metric,share_with_team,updated_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_cents=VALUES(target_cents), base_metric=VALUES(base_metric), share_with_team=VALUES(share_with_team), updated_by=VALUES(updated_by), updated_at=NOW()",
            [$clinicId, $monthKey, $targetCents, $baseMetric, $shareWithTeam ? 1 : 0, $userId],
        );
        $statement = DatabaseSchemaInfrastructureOperations01::pdo()->prepare($sql);
        $statement->execute($params);
        $statement->closeCursor();
    }
}
''')

replace_once(
    'app/Runtime/Financial/FinancialComposition.php',
    '''use Prontoo\\Application\\Financial\\FinancialCashierAttentionService;\nuse Prontoo\\Application\\Financial\\PatientRevenueReceiptService;\nuse Prontoo\\Infrastructure\\Financial\\PdoFinancialCashierAttentionRepository;\nuse Prontoo\\Infrastructure\\Financial\\PdoPatientRevenueReceiptRepository;''',
    '''use Prontoo\\Application\\Financial\\FinancialCashierAttentionService;\nuse Prontoo\\Application\\Financial\\FinancialGoalService;\nuse Prontoo\\Application\\Financial\\PatientRevenueReceiptService;\nuse Prontoo\\Infrastructure\\Financial\\PdoFinancialCashierAttentionRepository;\nuse Prontoo\\Infrastructure\\Financial\\PdoFinancialGoalRepository;\nuse Prontoo\\Infrastructure\\Financial\\PdoPatientRevenueReceiptRepository;''',
)
replace_once(
    'app/Runtime/Financial/FinancialComposition.php',
    '''    private static ?PatientRevenueReceiptService $patientRevenue = null;\n    private static ?FinancialCashierAttentionService $cashierAttention = null;''',
    '''    private static ?PatientRevenueReceiptService $patientRevenue = null;\n    private static ?FinancialCashierAttentionService $cashierAttention = null;\n    private static ?FinancialGoalService $financialGoal = null;''',
)
replace_once(
    'app/Runtime/Financial/FinancialComposition.php',
    '''    public static function cashierAttentionService(): FinancialCashierAttentionService\n    {\n        return self::$cashierAttention ??= new FinancialCashierAttentionService(\n            new PdoFinancialCashierAttentionRepository(),\n        );\n    }\n''',
    '''    public static function cashierAttentionService(): FinancialCashierAttentionService\n    {\n        return self::$cashierAttention ??= new FinancialCashierAttentionService(\n            new PdoFinancialCashierAttentionRepository(),\n        );\n    }\n\n    public static function financialGoalService(): FinancialGoalService\n    {\n        return self::$financialGoal ??= new FinancialGoalService(\n            new PdoFinancialGoalRepository(),\n        );\n    }\n''',
)

old_goal = '''                if ($act === "goal") {\n                    $target = \\Prontoo\\Domain\\Financial\\FinancialDomainOperations01::parse_money_cents((string) ($_POST["target"] ?? "0"));\n                    $share = isset($_POST["share_with_team"]) ? 1 : 0;\n                    $base = (string) ($_POST["base_metric"] ?? "efetivada");\n                    if (!in_array($base, ["prevista", "efetivada"], true)) {\n                        $base = "efetivada";\n                    }\n                    $month = \\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::app_month_in_timezone($cid);\n                    \\Prontoo\\Runtime\\DatabaseSchema\\DatabaseSchemaRuntimeOperations01::q(\n                        "INSERT INTO pi_financial_goals (clinic_id,month_key,target_cents,base_metric,share_with_team,updated_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_cents=VALUES(target_cents), base_metric=VALUES(base_metric), share_with_team=VALUES(share_with_team), updated_by=VALUES(updated_by), updated_at=NOW()",\n                        [$cid, $month, $target, $base, $share, $uid],\n                    );\n                    \\Prontoo\\Runtime\\AuditActivity\\AuditActivityRuntimeOperations04::audit("meta_financeira_salva", "financeiro", $cid, [\n                        "valor" => $target,\n                        "base" => $base,\n                        "compartilhar" => $share,\n                    ]);\n                    \\Prontoo\\Presentation\\SecurityAccess\\SecurityAccessPresentationOperations01::flash("Meta mensal atualizada.");\n                    \\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "meta"]);\n                }'''
new_goal = '''                if ($act === "goal") {\n                    $goal = \\Prontoo\\Runtime\\Financial\\FinancialComposition::financialGoalService()->save(\n                        $cid,\n                        \\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::app_month_in_timezone($cid),\n                        \\Prontoo\\Domain\\Financial\\FinancialDomainOperations01::parse_money_cents((string) ($_POST["target"] ?? "0")),\n                        (string) ($_POST["base_metric"] ?? "efetivada"),\n                        isset($_POST["share_with_team"]),\n                        $uid,\n                    );\n                    \\Prontoo\\Runtime\\AuditActivity\\AuditActivityRuntimeOperations04::audit("meta_financeira_salva", "financeiro", $cid, [\n                        "valor" => $goal["target_cents"],\n                        "base" => $goal["base_metric"],\n                        "compartilhar" => $goal["share_with_team"],\n                    ]);\n                    \\Prontoo\\Presentation\\SecurityAccess\\SecurityAccessPresentationOperations01::flash("Meta mensal atualizada.");\n                    \\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::redirect("financial", ["tab" => "meta"]);\n                }'''
replace_once('app/Runtime/Financial/FinancialRuntimeOperations17.php', old_goal, new_goal)

write('tools/runtime-thinning-check', '''<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
$runtime = (string) file_get_contents($root . '/app/Runtime/Financial/FinancialRuntimeOperations17.php');
$composition = (string) file_get_contents($root . '/app/Runtime/Financial/FinancialComposition.php');
$failures = [];
if (str_contains($runtime, 'INSERT INTO pi_financial_goals')) {
    $failures[] = 'financial_goal_sql_in_runtime';
}
if (!str_contains($runtime, 'FinancialComposition::financialGoalService()->save(')) {
    $failures[] = 'financial_goal_application_delegation_missing';
}
if (!str_contains($composition, 'new PdoFinancialGoalRepository()')) {
    $failures[] = 'financial_goal_composition_missing';
}
$result = [
    'ok' => $failures === [],
    'policy' => 'runtime-controllers-delegate-business-persistence-v1',
    'slice' => 'financial_goal_save',
    'failures' => $failures,
];
fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($failures === [] ? 0 : 1);
''')

quality = ROOT / 'tools/quality-gate'
source = quality.read_text()
needle = "$run('composition-root-boundary', 'tools/composition-root-check');\n"
addition = needle + "$run('runtime-thinning-contract', 'tools/runtime-thinning-check');\n"
if "runtime-thinning-contract" not in source:
    if source.count(needle) != 1:
        raise RuntimeError('quality-gate: insertion point not found')
    quality.write_text(source.replace(needle, addition, 1))

print('phase12 applied')
