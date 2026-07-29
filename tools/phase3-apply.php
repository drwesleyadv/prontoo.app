<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$version = '1.7.29.4';
$previousVersion = '1.7.29.3';
$timestamp = '2026-07-29T21:32:29+00:00';
$timestampUnix = 1785360749;

$path = static fn(string $relative): string => $root . '/' . ltrim($relative, '/');
$replace = static function (string $relative, string $old, string $new) use ($path): void {
    $file = $path($relative);
    $source = (string) file_get_contents($file);
    if (substr_count($source, $old) !== 1) {
        throw new RuntimeException('Trecho divergente: ' . $relative);
    }
    file_put_contents($file, str_replace($old, $new, $source));
};
$updateJson = static function (string $relative, callable $mutate) use ($path): void {
    $file = $path($relative);
    $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $data = $mutate($data);
    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
    );
};

$replace(
    'app/Domain/Patients/Patients.php',
    <<<'PHP'
function patient_appointment_registration_block_reason(
    int $cid,
    int $patientId,
): ?string {

    if ($cid <= 0 || $patientId <= 0) {
        return null;
    }
    $p = one(
        "SELECT pp.id,pp.clinic_id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,p.full_name,p.cpf,p.birth_date FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1 LIMIT 1",
        [$patientId, $cid],
    );
    if (!$p) {
        return "Paciente não encontrado no consultório atual.";
    }
    if (patient_invoice_registration_complete($p)) {
        return null;
    }
    return patient_invoice_registration_alert_message($p);
}
PHP,
    <<<'PHP'
function patient_appointment_registration_block_reason(
    int $cid,
    int $patientId,
): ?string {
    return prontoo_patient_appointment_registration_block_reason($cid, $patientId);
}
PHP,
);

$replace(
    'app/Domain/Patients/Patients.php',
    <<<'PHP'
function patient_legal_guardians(int $cid, int $patientId): array
{

    if ($cid <= 0 || $patientId <= 0) {
        return [];
    }
    patient_guardians_ensure_schema();
    $rows = q(
        "SELECT id,full_name,cpf,relationship,phone,email,document_note,notes,is_primary,created_at,updated_at FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY is_primary DESC, id ASC",
        [$cid, $patientId],
    )->fetchAll();
    foreach ($rows as &$r) {
        $r["id"] = (int) $r["id"];
        $r["cpf"] = only_digits((string) ($r["cpf"] ?? ""));
        $r["relationship"] = normalize_guardian_relationship(
            (string) ($r["relationship"] ?? "outro"),
        );
        $r["is_primary"] = (int) ($r["is_primary"] ?? 0);
    }
    unset($r);
    return $rows;
}
PHP,
    <<<'PHP'
function patient_legal_guardians(int $cid, int $patientId): array
{
    if ($cid <= 0 || $patientId <= 0) {
        return [];
    }
    patient_guardians_ensure_schema();
    return prontoo_patient_legal_guardians($cid, $patientId);
}
PHP,
);

$replace(
    'app/Domain/Patients/Patients.php',
    <<<'PHP'
function patient_has_legal_guardian(int $cid, int $patientId): bool
{

    if ($cid <= 0 || $patientId <= 0) {
        return false;
    }
    patient_guardians_ensure_schema();
    return (int) (val(
        "SELECT COUNT(*) FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1",
        [$cid, $patientId],
    ) ?? 0) > 0;
}
PHP,
    <<<'PHP'
function patient_has_legal_guardian(int $cid, int $patientId): bool
{
    if ($cid <= 0 || $patientId <= 0) {
        return false;
    }
    patient_guardians_ensure_schema();
    return prontoo_patient_has_legal_guardian($cid, $patientId);
}
PHP,
);

$replace(
    'app/Support/ModuleLoader.php',
    <<<'PHP'
        'Infrastructure/Patients/PatientTabReadRepository.php',
        'Presentation/Patients/PatientTabView.php',
        'Domain/Patients/Patients.php',
PHP,
    <<<'PHP'
        'Application/Patients/PatientReadPort.php',
        'Application/Patients/PatientReadService.php',
        'Infrastructure/Patients/PdoPatientReadRepository.php',
        'Infrastructure/Patients/PatientTabReadRepository.php',
        'Presentation/Patients/PatientTabView.php',
        'Domain/Patients/Patients.php',
PHP,
);

$replace(
    'app/Support/ModuleLoader.php',
    <<<'PHP'
    $patients = ['patients' => [
        'Infrastructure/Patients/PatientTabReadRepository.php',
        'Presentation/Patients/PatientTabView.php',
        'Domain/Patients/Patients.php',
    ]];
PHP,
    <<<'PHP'
    $patients = ['patients' => [
        'Application/Patients/PatientReadPort.php',
        'Application/Patients/PatientReadService.php',
        'Infrastructure/Patients/PdoPatientReadRepository.php',
        'Infrastructure/Patients/PatientTabReadRepository.php',
        'Presentation/Patients/PatientTabView.php',
        'Domain/Patients/Patients.php',
    ]];
PHP,
);

$runnerFile = $path('app/Runtime/Runner.php');
$runner = (string) file_get_contents($runnerFile);
if (str_contains($runner, 'function prontoo_patient_read_service()')) {
    throw new RuntimeException('Ponte da Fase 3 já existente.');
}
$runner .= <<<'PHP'
function prontoo_patient_read_service(): \Prontoo\Application\Patients\PatientReadService
{
    static $service = null;
    if (!$service instanceof \Prontoo\Application\Patients\PatientReadService) {
        $service = new \Prontoo\Application\Patients\PatientReadService(
            new \Prontoo\Infrastructure\Patients\PdoPatientReadRepository(),
        );
    }
    return $service;
}
function prontoo_patient_appointment_registration_block_reason(
    int $clinicId,
    int $patientId,
): ?string {
    return prontoo_patient_read_service()->appointmentRegistrationBlockReason(
        $clinicId,
        $patientId,
        static fn(array $patient): bool => patient_invoice_registration_complete($patient),
        static fn(array $patient): string => patient_invoice_registration_alert_message($patient),
    );
}
function prontoo_patient_legal_guardians(int $clinicId, int $patientId): array
{
    return prontoo_patient_read_service()->legalGuardians(
        $clinicId,
        $patientId,
        static fn(string $cpf): string => only_digits($cpf),
        static fn(string $relationship): string => normalize_guardian_relationship($relationship),
    );
}
function prontoo_patient_has_legal_guardian(int $clinicId, int $patientId): bool
{
    return prontoo_patient_read_service()->hasLegalGuardian($clinicId, $patientId);
}
PHP;
file_put_contents($runnerFile, rtrim($runner) . PHP_EOL);

$architectureFile = $path('tools/architecture-check.php');
$architectureSource = (string) file_get_contents($architectureFile);
$architectureNeedle = <<<'PHP'
$phaseTwoCharacterization = [
    'ok' => $phaseTwoFailures === [],
    'failed' => $phaseTwoFailures,
];
$result = [
PHP;
$architectureReplacement = <<<'PHP'
$phaseTwoCharacterization = [
    'ok' => $phaseTwoFailures === [],
    'failed' => $phaseTwoFailures,
];

require_once $root . '/app/Application/Patients/PatientReadPort.php';
require_once $root . '/app/Application/Patients/PatientReadService.php';
require_once $root . '/app/Infrastructure/Patients/PdoPatientReadRepository.php';

$phaseThreeFailures = [];
$phaseThreeAssert = static function (bool $condition, string $name) use (&$phaseThreeFailures): void {
    if (!$condition) {
        $phaseThreeFailures[] = $name;
    }
};
$phaseThreePort = new class implements \Prontoo\Application\Patients\PatientReadPort {
    public ?array $patient = null;
    public array $guardians = [];
    public bool $hasGuardian = false;

    public function appointmentRegistration(int $clinicId, int $patientId): ?array
    {
        return $this->patient;
    }

    public function activeLegalGuardians(int $clinicId, int $patientId): array
    {
        return $this->guardians;
    }

    public function hasActiveLegalGuardian(int $clinicId, int $patientId): bool
    {
        return $this->hasGuardian;
    }
};
$phaseThreeService = new \Prontoo\Application\Patients\PatientReadService($phaseThreePort);
$phaseThreeComplete = static fn(array $patient): bool => (string) ($patient['state'] ?? '') === 'complete';
$phaseThreeAlert = static fn(array $patient): string => 'alert:' . (string) ($patient['state'] ?? '');
$phaseThreeDigits = static fn(string $value): string => (string) preg_replace('/\D+/', '', $value);
$phaseThreeRelationship = static fn(string $value): string => strtolower($value) === 'mae' ? 'mae' : 'outro';

$phaseThreeAssert(
    $phaseThreeService->appointmentRegistrationBlockReason(0, 1, $phaseThreeComplete, $phaseThreeAlert) === null,
    'appointment_registration_invalid_scope',
);
$phaseThreeAssert(
    $phaseThreeService->appointmentRegistrationBlockReason(1, 2, $phaseThreeComplete, $phaseThreeAlert) === 'Paciente não encontrado no consultório atual.',
    'appointment_registration_not_found',
);
$phaseThreePort->patient = ['state' => 'complete'];
$phaseThreeAssert(
    $phaseThreeService->appointmentRegistrationBlockReason(1, 2, $phaseThreeComplete, $phaseThreeAlert) === null,
    'appointment_registration_complete',
);
$phaseThreePort->patient = ['state' => 'incomplete'];
$phaseThreeAssert(
    $phaseThreeService->appointmentRegistrationBlockReason(1, 2, $phaseThreeComplete, $phaseThreeAlert) === 'alert:incomplete',
    'appointment_registration_incomplete',
);
$phaseThreeAssert(
    $phaseThreeService->legalGuardians(0, 2, $phaseThreeDigits, $phaseThreeRelationship) === [],
    'legal_guardians_invalid_scope',
);
$phaseThreePort->guardians = [[
    'id' => '7',
    'full_name' => 'Responsável',
    'cpf' => '529.982.247-25',
    'relationship' => 'MAE',
    'is_primary' => '1',
]];
$phaseThreeGuardians = $phaseThreeService->legalGuardians(
    1,
    2,
    $phaseThreeDigits,
    $phaseThreeRelationship,
);
$phaseThreeAssert(
    count($phaseThreeGuardians) === 1 &&
    ($phaseThreeGuardians[0]['id'] ?? null) === 7 &&
    ($phaseThreeGuardians[0]['cpf'] ?? null) === '52998224725' &&
    ($phaseThreeGuardians[0]['relationship'] ?? null) === 'mae' &&
    ($phaseThreeGuardians[0]['is_primary'] ?? null) === 1,
    'legal_guardians_normalization',
);
$phaseThreeAssert(
    !$phaseThreeService->hasLegalGuardian(0, 2),
    'legal_guardian_invalid_scope',
);
$phaseThreePort->hasGuardian = true;
$phaseThreeAssert(
    $phaseThreeService->hasLegalGuardian(1, 2),
    'legal_guardian_exists',
);

$phaseThreeSources = [
    'port' => (string) file_get_contents($root . '/app/Application/Patients/PatientReadPort.php'),
    'service' => (string) file_get_contents($root . '/app/Application/Patients/PatientReadService.php'),
    'repository' => (string) file_get_contents($root . '/app/Infrastructure/Patients/PdoPatientReadRepository.php'),
    'patients_facade' => (string) file_get_contents($root . '/app/Domain/Patients/Patients.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
    'loader' => (string) file_get_contents($root . '/app/Support/ModuleLoader.php'),
];
foreach ([
    'port' => [
        'appointmentRegistration(int $clinicId, int $patientId): ?array',
        'activeLegalGuardians(int $clinicId, int $patientId): array',
        'hasActiveLegalGuardian(int $clinicId, int $patientId): bool',
    ],
    'service' => [
        '$this->port->appointmentRegistration($clinicId, $patientId)',
        '$this->port->activeLegalGuardians($clinicId, $patientId)',
        '$this->port->hasActiveLegalGuardian($clinicId, $patientId)',
    ],
    'repository' => [
        'WHERE pp.id=? AND pp.clinic_id=? AND pp.active=1 LIMIT 1',
        'WHERE clinic_id=? AND patient_link_id=? AND active=1 ORDER BY is_primary DESC, id ASC',
        'SELECT COUNT(*) FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1',
    ],
    'patients_facade' => [
        'prontoo_patient_appointment_registration_block_reason($cid, $patientId)',
        'prontoo_patient_legal_guardians($cid, $patientId)',
        'prontoo_patient_has_legal_guardian($cid, $patientId)',
    ],
    'runner' => [
        'new \\Prontoo\\Infrastructure\\Patients\\PdoPatientReadRepository()',
        'appointmentRegistrationBlockReason(',
        'legalGuardians(',
        'hasLegalGuardian(',
    ],
    'loader' => [
        "'Application/Patients/PatientReadPort.php'",
        "'Application/Patients/PatientReadService.php'",
        "'Infrastructure/Patients/PdoPatientReadRepository.php'",
    ],
] as $sourceKey => $requiredTokens) {
    foreach ($requiredTokens as $requiredToken) {
        if (!str_contains($phaseThreeSources[$sourceKey], $requiredToken)) {
            $phaseThreeFailures[] = $sourceKey . ':missing:' . $requiredToken;
        }
    }
}
foreach ([
    'port' => ['SELECT ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION', '<div', '<section'],
    'service' => ['SELECT ', ' q(', ' one(', ' val(', '$_GET', '$_POST', '$_SESSION', '<div', '<section'],
    'repository' => ['<div', '<section', '$_GET', '$_POST', '$_SESSION'],
    'patients_facade' => [
        'SELECT pp.id,pp.clinic_id,pp.person_id,pp.phone,pp.email,pp.address,pp.address_zip,pp.address_number,pp.address_neighborhood,pp.address_city,pp.address_state,p.full_name,p.cpf,p.birth_date FROM pi_patients',
        'SELECT id,full_name,cpf,relationship,phone,email,document_note,notes,is_primary,created_at,updated_at FROM pi_patient_guardians',
        'SELECT COUNT(*) FROM pi_patient_guardians WHERE clinic_id=? AND patient_link_id=? AND active=1',
    ],
] as $sourceKey => $forbiddenTokens) {
    foreach ($forbiddenTokens as $forbiddenToken) {
        if (str_contains($phaseThreeSources[$sourceKey], $forbiddenToken)) {
            $phaseThreeFailures[] = $sourceKey . ':forbidden:' . $forbiddenToken;
        }
    }
}
$phaseThreeCharacterization = [
    'ok' => $phaseThreeFailures === [],
    'failed' => $phaseThreeFailures,
];
$result = [
PHP;
if (substr_count($architectureSource, $architectureNeedle) !== 1) {
    throw new RuntimeException('Contrato arquitetural divergente.');
}
$architectureSource = str_replace($architectureNeedle, $architectureReplacement, $architectureSource);
$architectureSource = str_replace(
    "        !empty(\$phaseTwoCharacterization['ok']),",
    "        !empty(\$phaseTwoCharacterization['ok']) &&\n        !empty(\$phaseThreeCharacterization['ok']),",
    $architectureSource,
);
$architectureSource = str_replace(
    "    'phase_two_characterization' => \$phaseTwoCharacterization,",
    "    'phase_two_characterization' => \$phaseTwoCharacterization,\n    'phase_three_characterization' => \$phaseThreeCharacterization,",
    $architectureSource,
);
file_put_contents($architectureFile, $architectureSource);

$replace(
    'docs/index.md',
    '- [Fase 2 — apresentação e leitura](architecture/phase-2-presentation-boundaries.md)',
    "- [Fase 2 — apresentação e leitura](architecture/phase-2-presentation-boundaries.md)\n- [Fase 3 — consultas e casos de uso](architecture/phase-3-read-use-cases.md)",
);

$replace(
    'docs/architecture/responsibility-map.md',
    <<<'MD'
### Dica de onboarding

`app/Presentation/Auth/OnboardingTipView.php` concentra a renderização do componente. `AuthOnboarding.php` permanece responsável pelas condições de exibição e prepara o modelo de apresentação.

## Inventário inicial de funções puras
MD,
    <<<'MD'
### Dica de onboarding

`app/Presentation/Auth/OnboardingTipView.php` concentra a renderização do componente. `AuthOnboarding.php` permanece responsável pelas condições de exibição e prepara o modelo de apresentação.

## Extrações concluídas na Fase 3

### Leituras cadastrais do paciente

`app/Application/Patients/PatientReadPort.php` define a fronteira de leitura. `app/Application/Patients/PatientReadService.php` coordena elegibilidade cadastral e normalização de responsáveis legais. `app/Infrastructure/Patients/PdoPatientReadRepository.php` concentra o SQL com isolamento explícito por consultório.

## Inventário inicial de funções puras
MD,
);

$replace('app/prontoo.php', 'const PRONTOO_VERSION_FALLBACK = "1.7.29.3";', 'const PRONTOO_VERSION_FALLBACK = "1.7.29.4";');
$replace('br/index.php', 'const BR_LANDING_VERSION_FALLBACK = "1.7.29.3";', 'const BR_LANDING_VERSION_FALLBACK = "1.7.29.4";');

$updateJson('version.json', static function (array $data) use ($version, $previousVersion, $timestamp, $timestampUnix): array {
    $data['version'] = $version;
    $data['release'] = $version;
    $data['generated_at_unix'] = $timestampUnix;
    $data['generated_at'] = $timestamp;
    $data['updated_at'] = $timestamp;
    $data['build'] = '1.7.29.4-phase3-patient-read-use-cases';
    $data['functional_equivalence_policy'] = 'preserves_1_7_29_3_runtime_behavior';
    $data['architecture_native_files_min'] = 23;
    $data['notes'] = 'Executa a Fase 3 com portas, adaptador PDO e caso de uso para leituras cadastrais do paciente.';
    $data['previous_version'] = $previousVersion;
    $data['deployment_sync_id'] = 'github-phase3-patient-read-use-cases-1-7-29-4';
    $data['deployment_sync_requested_at'] = $timestamp;
    $data['baseline_source'] = $previousVersion;
    $data['rewrite_scope'] = 'patient_registration_and_guardian_read_use_cases';
    return $data;
});

$updateJson('app/architecture.manifest.json', static function (array $data) use ($version, $previousVersion, $timestamp): array {
    $data['version'] = $version;
    $data['native_files_min'] = 23;
    $data['updated_at'] = $timestamp;
    $data['baseline_source'] = $previousVersion;
    $data['phase_three_policy'] = 'application_ports_use_cases_pdo_adapters_and_legacy_facades';
    $data['phase_three_components'] = [
        'app/Application/Patients/PatientReadPort.php',
        'app/Application/Patients/PatientReadService.php',
        'app/Infrastructure/Patients/PdoPatientReadRepository.php',
    ];
    $data['phase_three_characterization'] = 'tools/architecture-check.php';
    return $data;
});

$updateJson('app/update.manifest.json', static function (array $data) use ($version, $previousVersion, $timestamp): array {
    $data['version'] = $version;
    $data['release'] = $version;
    $data['build'] = '1.7.29.4-phase3-patient-read-use-cases';
    $data['generated_at'] = $timestamp;
    $data['previous_version'] = $previousVersion;
    $data['updated_at'] = $timestamp;
    $data['notes'] = 'Fase 3: portas, adaptador PDO e caso de uso para leituras cadastrais do paciente.';
    $data['deployment_sync_id'] = 'github-phase3-patient-read-use-cases-1-7-29-4';
    $data['deployment_sync_requested_at'] = $timestamp;
    $data['baseline_source'] = $previousVersion;
    $data['rewrite_scope'] = 'patient_registration_and_guardian_read_use_cases';
    return $data;
});

$replace(
    'CHANGELOG.md',
    "# Histórico de versões\n\n",
    <<<'MD'
# Histórico de versões

## 1.7.29.4 — Fase 3: consultas e casos de uso

- cria uma porta de leitura de pacientes na camada de aplicação;
- introduz caso de uso para elegibilidade cadastral e responsáveis legais;
- move as consultas correspondentes para adaptador PDO com isolamento por consultório;
- mantém as funções globais existentes como fachadas compatíveis;
- adiciona testes de comportamento, direção de dependências e ausência de SQL na aplicação;
- não altera banco, schema, interface, permissões ou regras de negócio.

MD,
);

$replace(
    'ChangeLog.txt',
    'Prontoo 1.7.29.3 — Fase 2: apresentação e leitura',
    <<<'TXT'
Prontoo 1.7.29.4 — Fase 3: consultas e casos de uso

- Cria porta de leitura de pacientes e caso de uso independente de persistência.
- Move consultas cadastrais e de responsáveis legais para adaptador PDO.
- Mantém assinaturas públicas, mensagens, ordenação e comportamento.
- Adiciona testes de caracterização e direção das dependências.
- Preserva banco, schema, interface e permissões.
Prontoo 1.7.29.3 — Fase 2: apresentação e leitura
TXT,
);

echo json_encode(
    ['ok' => true, 'version' => $version, 'phase' => 3],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
), PHP_EOL;
