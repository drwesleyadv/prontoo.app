<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function phase2_replace_once(string $path, string $old, string $new): void
{
    $source = (string) file_get_contents($path);
    if (str_contains($source, $new)) {
        return;
    }
    if (substr_count($source, $old) !== 1) {
        throw new RuntimeException('Trecho não encontrado de forma única: ' . $path);
    }
    file_put_contents($path, str_replace($old, $new, $source));
}

function phase2_replace_regex(string $path, string $pattern, string $replacement): void
{
    $source = (string) file_get_contents($path);
    if (str_contains($source, $replacement)) {
        return;
    }
    $updated = preg_replace($pattern, $replacement, $source, 1, $count);
    if (!is_string($updated) || $count !== 1) {
        throw new RuntimeException('Padrão não encontrado de forma única: ' . $path);
    }
    file_put_contents($path, $updated);
}

function phase2_json(string $path, callable $mutate): void
{
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('JSON inválido: ' . $path);
    }
    $data = $mutate($data);
    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
    );
}

$patientsPath = $root . '/app/Domain/Patients/Patients.php';
phase2_replace_once(
    $patientsPath,
    <<<'OLD'
    $rows = q(
        "SELECT id,label,icon_name,sort_order,created_at FROM pi_patient_tabs WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY sort_order ASC,id ASC",
        [$cid, $patientId],
    )->fetchAll();
OLD,
    <<<'NEW'
    $rows = prontoo_patient_tab_active_rows($cid, $patientId);
NEW,
);
phase2_replace_once(
    $patientsPath,
    <<<'OLD'
                $label = (string) safe_val(
                    "SELECT label FROM pi_patient_tabs WHERE id=? LIMIT 1",
                    [$id],
                    "",
                );
OLD,
    <<<'NEW'
                $label = prontoo_patient_tab_label_by_id($id);
NEW,
);
phase2_replace_regex(
    $patientsPath,
    '~function patient_tab_icon_picker\(string \$current = "clinical_notes"\): string\s*\{.*?\n\}\n(?=function patient_guardians_ensure_schema)~s',
    <<<'NEW'
function patient_tab_icon_picker(string $current = "clinical_notes"): string
{
    $current = normalize_patient_tab_icon($current);
    return prontoo_patient_tab_icon_picker(patient_health_icon_options(), $current);
}

NEW,
);

$authPath = $root . '/app/Auth/AuthOnboarding.php';
phase2_replace_regex(
    $authPath,
    '~    return \'<section class="onboarding-tip-card".*?onboarding-tip-button">Entendi</button></form></section>\';\n(?=\})~s',
    <<<'NEW'
    return prontoo_onboarding_tip_render($tip, $key, $return, csrf_field());
NEW,
);

$loaderPath = $root . '/app/Support/ModuleLoader.php';
phase2_replace_once(
    $loaderPath,
    <<<'OLD'
        'Ui/PublicWeb.php',
        'Auth/AuthOnboarding.php',
OLD,
    <<<'NEW'
        'Ui/PublicWeb.php',
        'Presentation/Auth/OnboardingTipView.php',
        'Auth/AuthOnboarding.php',
NEW,
);
phase2_replace_once(
    $loaderPath,
    <<<'OLD'
        'Domain/Patients/Patients.php',
        'Domain/Appointments/Appointments.php',
OLD,
    <<<'NEW'
        'Infrastructure/Patients/PatientTabReadRepository.php',
        'Presentation/Patients/PatientTabView.php',
        'Domain/Patients/Patients.php',
        'Domain/Appointments/Appointments.php',
NEW,
);
phase2_replace_once(
    $loaderPath,
    <<<'OLD'
    $patients = ['patients' => ['Domain/Patients/Patients.php']];
OLD,
    <<<'NEW'
    $patients = ['patients' => [
        'Infrastructure/Patients/PatientTabReadRepository.php',
        'Presentation/Patients/PatientTabView.php',
        'Domain/Patients/Patients.php',
    ]];
NEW,
);

$architectureCheckPath = $root . '/tools/architecture-check.php';
$phaseTwoBlock = <<<'PHP'

require_once $root . '/app/Infrastructure/Patients/PatientTabReadRepository.php';
require_once $root . '/app/Presentation/Patients/PatientTabView.php';
require_once $root . '/app/Presentation/Auth/OnboardingTipView.php';

$phaseTwoFailures = [];
$phaseTwoAssert = static function (bool $condition, string $name) use (&$phaseTwoFailures): void {
    if (!$condition) {
        $phaseTwoFailures[] = $name;
    }
};
$phaseTwoEscape = static fn(string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8',
);
$phaseTwoIcon = static fn(string $name): string => '<i>' . $phaseTwoEscape($name) . '</i>';

$phaseTwoPicker = \Prontoo\Presentation\Patients\PatientTabView::iconPicker(
    ['clinical_notes' => 'Nota & alerta'],
    'clinical_notes',
    $phaseTwoEscape,
    $phaseTwoIcon,
);
$phaseTwoAssert(
    $phaseTwoPicker === '<div class="visual-option-grid patient-health-icon-grid patient-tab-icon-symbol-grid" role="radiogroup" aria-label="Ícone da aba"><label class="visual-option patient-health-icon-choice patient-health-icon-only" title="Nota &amp; alerta" aria-label="Nota &amp; alerta"><input type="radio" name="tab_icon" value="clinical_notes" checked aria-label="Nota &amp; alerta"><span class="patient-tab-icon-symbol"><i>clinical_notes</i></span><span class="sr-only">Nota &amp; alerta</span></label></div>',
    'patient_tab_view_snapshot',
);

$phaseTwoTip = \Prontoo\Presentation\Auth\OnboardingTipView::render(
    [
        'icon' => 'patient_list',
        'title' => 'Título & teste',
        'body' => 'Corpo <seguro>',
    ],
    'patients:role:"',
    '/?r=patients&x="',
    '<input type="hidden" name="_csrf" value="token">',
    $phaseTwoEscape,
    $phaseTwoIcon,
);
$phaseTwoAssert(
    $phaseTwoTip === '<section class="onboarding-tip-card" role="note"><div class="onboarding-tip-main"><div class="onboarding-tip-head"><span class="onboarding-tip-icon"><i>patient_list</i></span><strong>Título &amp; teste</strong></div><p class="onboarding-tip-body">Corpo &lt;seguro&gt;</p></div><form method="post" action="/?r=patients&amp;x=&quot;" class="onboarding-tip-action"><input type="hidden" name="_csrf" value="token"><input type="hidden" name="act" value="onboarding_tip_dismiss"><input type="hidden" name="tip_key" value="patients:role:&quot;"><input type="hidden" name="return_to" value="/?r=patients&amp;x=&quot;"><button type="submit" class="ghost small onboarding-tip-button">Entendi</button></form></section>',
    'onboarding_tip_view_snapshot',
);

$phaseTwoSources = [
    'patients_facade' => (string) file_get_contents($root . '/app/Domain/Patients/Patients.php'),
    'auth_facade' => (string) file_get_contents($root . '/app/Auth/AuthOnboarding.php'),
    'repository' => (string) file_get_contents($root . '/app/Infrastructure/Patients/PatientTabReadRepository.php'),
    'patient_view' => (string) file_get_contents($root . '/app/Presentation/Patients/PatientTabView.php'),
    'onboarding_view' => (string) file_get_contents($root . '/app/Presentation/Auth/OnboardingTipView.php'),
    'loader' => (string) file_get_contents($root . '/app/Support/ModuleLoader.php'),
];
foreach ([
    'patients_facade' => [
        'prontoo_patient_tab_active_rows($cid, $patientId)',
        'prontoo_patient_tab_label_by_id($id)',
        'prontoo_patient_tab_icon_picker(patient_health_icon_options(), $current)',
    ],
    'auth_facade' => [
        'prontoo_onboarding_tip_render($tip, $key, $return, csrf_field())',
    ],
    'repository' => [
        'SELECT id,label,icon_name,sort_order,created_at FROM pi_patient_tabs',
        'SELECT label FROM pi_patient_tabs WHERE id=? LIMIT 1',
    ],
    'loader' => [
        "'Infrastructure/Patients/PatientTabReadRepository.php'",
        "'Presentation/Patients/PatientTabView.php'",
        "'Presentation/Auth/OnboardingTipView.php'",
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($phaseTwoSources[$sourceKey], $requiredToken)) {
            $phaseTwoFailures[] = $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'patients_facade' => [
        'SELECT id,label,icon_name,sort_order,created_at FROM pi_patient_tabs',
        '<div class="visual-option-grid patient-health-icon-grid',
    ],
    'auth_facade' => [
        '<section class="onboarding-tip-card"',
    ],
    'repository' => [
        '<section',
        '<div class=',
        '$_GET',
        '$_POST',
        '$_SESSION',
    ],
    'patient_view' => [
        'SELECT ',
        ' q(',
        ' one(',
        '$_GET',
        '$_POST',
        '$_SESSION',
    ],
    'onboarding_view' => [
        'SELECT ',
        ' q(',
        ' one(',
        '$_GET',
        '$_POST',
        '$_SESSION',
    ],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($phaseTwoSources[$sourceKey], $forbiddenToken)) {
            $phaseTwoFailures[] = $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$phaseTwoCharacterization = [
    'ok' => $phaseTwoFailures === [],
    'failed' => $phaseTwoFailures,
];
PHP;

$architectureSource = (string) file_get_contents($architectureCheckPath);
if (!str_contains($architectureSource, '$phaseTwoCharacterization = [')) {
    $marker = '$result = [';
    if (substr_count($architectureSource, $marker) !== 1) {
        throw new RuntimeException('Marcador de resultado arquitetural divergente.');
    }
    $architectureSource = str_replace($marker, $phaseTwoBlock . PHP_EOL . $marker, $architectureSource);
}
$architectureSource = str_replace(
    "        !empty(\$phaseOneCharacterization['ok']),",
    "        !empty(\$phaseOneCharacterization['ok']) &&\n        !empty(\$phaseTwoCharacterization['ok']),",
    $architectureSource,
);
$architectureSource = str_replace(
    "    'phase_one_characterization' => \$phaseOneCharacterization,",
    "    'phase_one_characterization' => \$phaseOneCharacterization,\n    'phase_two_characterization' => \$phaseTwoCharacterization,",
    $architectureSource,
);
file_put_contents($architectureCheckPath, $architectureSource);

$indexPath = $root . '/docs/index.md';
phase2_replace_once(
    $indexPath,
    "- [Fase 1 — enxugamento estrutural](architecture/phase-1-refactoring.md)\n",
    "- [Fase 1 — enxugamento estrutural](architecture/phase-1-refactoring.md)\n- [Fase 2 — apresentação e leitura](architecture/phase-2-presentation-boundaries.md)\n",
);

$responsibilityPath = $root . '/docs/architecture/responsibility-map.md';
phase2_replace_once(
    $responsibilityPath,
    "| HTML reutilizável e componentes | `app/Ui` |\n",
    "| HTML específico de fluxo e presenters | `app/Presentation` |\n| componentes visuais genéricos | `app/Ui` |\n",
);
$responsibilitySource = (string) file_get_contents($responsibilityPath);
if (!str_contains($responsibilitySource, '## Extrações concluídas na Fase 2')) {
    $addition = <<<'MD'

## Extrações concluídas na Fase 2

### Abas do paciente

`app/Infrastructure/Patients/PatientTabReadRepository.php` concentra as consultas de leitura. `app/Presentation/Patients/PatientTabView.php` concentra o seletor visual. `Patients.php` mantém as funções globais como fachadas.

### Dica de onboarding

`app/Presentation/Auth/OnboardingTipView.php` concentra a renderização do componente. `AuthOnboarding.php` permanece responsável pelas condições de exibição e prepara o modelo de apresentação.
MD;
    $responsibilitySource = str_replace(
        "\n## Inventário inicial de funções puras\n",
        $addition . "\n\n## Inventário inicial de funções puras\n",
        $responsibilitySource,
    );
    file_put_contents($responsibilityPath, $responsibilitySource);
}

$phaseOnePath = $root . '/docs/architecture/phase-1-refactoring.md';
$phaseOneSource = (string) file_get_contents($phaseOnePath);
$phaseOneSource = str_replace(
    "Separar apresentação e leitura de dados, começando por um fluxo de baixo risco e preservando o carregamento seletivo por rota.",
    "Concluída na Fase 2: apresentação e leitura das abas do paciente e apresentação da dica de onboarding foram separadas preservando as fachadas.",
    $phaseOneSource,
);
file_put_contents($phaseOnePath, $phaseOneSource);

$version = '1.7.29.3';
$nowUnix = time();
$now = gmdate('c', $nowUnix);

phase2_json($root . '/version.json', static function (array $data) use ($version, $nowUnix, $now): array {
    $data['version'] = $version;
    $data['release'] = $version;
    $data['generated_at_unix'] = $nowUnix;
    $data['generated_at'] = $now;
    $data['updated_at'] = $now;
    $data['build'] = '1.7.29.3-phase2-presentation-boundaries';
    $data['package_type'] = 'incremental_refactor';
    $data['database_changes'] = false;
    $data['schema_changes'] = false;
    $data['logic_changes'] = false;
    $data['visual_changes'] = false;
    $data['functional_equivalence_policy'] = 'preserves_1_7_29_2_runtime_behavior';
    $data['architecture_native_files_min'] = 20;
    $data['architecture_transitional_files_max'] = 80;
    $data['previous_version'] = '1.7.29.2';
    $data['notes'] = 'Executa a Fase 2 com separação de apresentação e leitura de dados em fluxos de baixo risco.';
    $data['deployment_sync_id'] = 'github-phase2-presentation-boundaries-1-7-29-3';
    $data['deployment_sync_requested_at'] = $now;
    $data['baseline_source'] = '1.7.29.2';
    $data['rewrite_scope'] = 'patient_tab_read_and_selected_presentation_renderers';
    return $data;
});

phase2_json($root . '/app/architecture.manifest.json', static function (array $data) use ($version, $now): array {
    $data['version'] = $version;
    $data['native_files_min'] = 20;
    $data['transitional_files_max'] = 80;
    $data['logic_changes'] = false;
    $data['visual_changes'] = false;
    $data['updated_at'] = $now;
    $data['baseline_source'] = '1.7.29.2';
    $data['phase_two_policy'] = 'sql_in_infrastructure_html_in_presentation_legacy_facades_preserved';
    $data['phase_two_characterization'] = 'tools/architecture-check.php';
    return $data;
});

phase2_json($root . '/app/update.manifest.json', static function (array $data) use ($version, $now): array {
    $data['version'] = $version;
    $data['release'] = $version;
    $data['build'] = '1.7.29.3-phase2-presentation-boundaries';
    $data['package_type'] = 'incremental_refactor';
    $data['generated_at'] = $now;
    $data['database_changes'] = false;
    $data['schema_changes'] = false;
    $data['logic_changes'] = false;
    $data['visual_changes'] = false;
    $data['previous_version'] = '1.7.29.2';
    $data['updated_at'] = $now;
    $data['notes'] = 'Fase 2: separação de apresentação e leitura de dados com fachadas compatíveis e testes de caracterização.';
    $data['deployment_sync_id'] = 'github-phase2-presentation-boundaries-1-7-29-3';
    $data['deployment_sync_requested_at'] = $now;
    $data['baseline_source'] = '1.7.29.2';
    return $data;
});

foreach ([
    $root . '/app/prontoo.php' => 'PRONTOO_VERSION_FALLBACK',
    $root . '/br/index.php' => 'BR_LANDING_VERSION_FALLBACK',
] as $path => $constant) {
    $source = (string) file_get_contents($path);
    $pattern = '/(const\s+' . preg_quote($constant, '/') . '\s*=\s*["\x27])[^"\x27]+(["\x27]\s*;)/';
    $updated = preg_replace($pattern, '$1' . $version . '$2', $source, 1, $count);
    if (!is_string($updated) || $count !== 1) {
        throw new RuntimeException('Fallback de versão não atualizado: ' . $path);
    }
    file_put_contents($path, $updated);
}

$changeLogPath = $root . '/CHANGELOG.md';
$changeLog = (string) file_get_contents($changeLogPath);
if (!str_contains($changeLog, '## 1.7.29.3 — Fase 2')) {
    $entry = <<<'MD'
## 1.7.29.3 — Fase 2: apresentação e leitura

- move a leitura das abas do paciente para infraestrutura;
- move o seletor de ícones das abas para apresentação;
- move a renderização da dica de onboarding para apresentação;
- mantém as funções globais existentes como fachadas compatíveis;
- preserva o carregamento seletivo por rota;
- adiciona snapshots HTML e contratos de fronteira;
- não altera banco, schema, interface, permissões ou regras de negócio.

MD;
    $changeLog = str_replace(
        "# Histórico de versões\n\n",
        "# Histórico de versões\n\n" . $entry,
        $changeLog,
    );
    file_put_contents($changeLogPath, $changeLog);
}

$legacyLogPath = $root . '/ChangeLog.txt';
$legacyLog = (string) file_get_contents($legacyLogPath);
if (!str_starts_with($legacyLog, 'Prontoo 1.7.29.3')) {
    $entry = <<<'TXT'
Prontoo 1.7.29.3 — Fase 2: apresentação e leitura

- Separa o SQL de leitura das abas do paciente.
- Separa o HTML do seletor de abas e da dica de onboarding.
- Mantém assinaturas públicas e comportamento.
- Adiciona testes de caracterização.
- Preserva banco, schema, interface e permissões.
TXT;
    file_put_contents($legacyLogPath, $entry . PHP_EOL . $legacyLog);
}

echo json_encode([
    'ok' => true,
    'version' => $version,
    'phase' => 2,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
