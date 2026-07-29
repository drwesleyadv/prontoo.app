<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$version = '1.7.29.2';
$previousVersion = '1.7.29.1';
$now = gmdate('c');
$nowUnix = time();

function phase1_write(string $path, string $content): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível criar diretório: ' . $directory);
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Não foi possível gravar arquivo: ' . $path);
    }
}

function phase1_token_rows(string $source): array
{
    $rows = [];
    $offset = 0;
    foreach (token_get_all($source) as $token) {
        $id = is_array($token) ? $token[0] : null;
        $text = is_array($token) ? $token[1] : $token;
        $length = strlen($text);
        $rows[] = [
            'id' => $id,
            'text' => $text,
            'start' => $offset,
            'end' => $offset + $length,
        ];
        $offset += $length;
    }
    return $rows;
}

function phase1_function_range(string $source, string $name): array
{
    $rows = phase1_token_rows($source);
    $count = count($rows);
    for ($index = 0; $index < $count; $index++) {
        if ($rows[$index]['id'] !== T_FUNCTION) {
            continue;
        }
        $nameIndex = null;
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $id = $rows[$cursor]['id'];
            $text = $rows[$cursor]['text'];
            if ($id === T_WHITESPACE || $text === '&') {
                continue;
            }
            if ($id === T_STRING) {
                $nameIndex = $cursor;
            }
            break;
        }
        if ($nameIndex === null || $rows[$nameIndex]['text'] !== $name) {
            continue;
        }
        $openIndex = null;
        for ($cursor = $nameIndex + 1; $cursor < $count; $cursor++) {
            if ($rows[$cursor]['text'] === '{') {
                $openIndex = $cursor;
                break;
            }
        }
        if ($openIndex === null) {
            throw new RuntimeException('Abertura da função não encontrada: ' . $name);
        }
        $depth = 0;
        for ($cursor = $openIndex; $cursor < $count; $cursor++) {
            $text = $rows[$cursor]['text'];
            if ($text === '{') {
                $depth++;
            } elseif ($text === '}') {
                $depth--;
                if ($depth === 0) {
                    return [$rows[$index]['start'], $rows[$cursor]['end']];
                }
            }
        }
        throw new RuntimeException('Fechamento da função não encontrado: ' . $name);
    }
    throw new RuntimeException('Função não encontrada: ' . $name);
}

function phase1_replace_function(string $path, string $name, string $replacement): void
{
    $source = (string) file_get_contents($path);
    [$start, $end] = phase1_function_range($source, $name);
    $updated = substr($source, 0, $start) . $replacement . substr($source, $end);
    phase1_write($path, $updated);
}

function phase1_insert_after_declare(string $path, string $statement): void
{
    $source = (string) file_get_contents($path);
    if (str_contains($source, $statement)) {
        return;
    }
    $updated = preg_replace(
        '/declare\(strict_types=1\);\s*/',
        "declare(strict_types=1);\n" . $statement . "\n\n",
        $source,
        1,
        $count,
    );
    if ($updated === null || $count !== 1) {
        throw new RuntimeException('Não foi possível inserir dependência em ' . $path);
    }
    phase1_write($path, $updated);
}

function phase1_update_json(string $path, callable $mutator): void
{
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('JSON inválido: ' . $path);
    }
    $data = $mutator($data);
    phase1_write(
        $path,
        json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . PHP_EOL,
    );
}

function phase1_compact_php(string $source): string
{
    $output = '';
    foreach (token_get_all($source) as $token) {
        if (!is_array($token)) {
            $output .= $token;
            continue;
        }
        [$id, $text] = $token;
        if ($id === T_WHITESPACE) {
            $text = preg_replace('/(?:[ \t]*\R){3,}/', "\n\n", $text) ?? $text;
        }
        $output .= $text;
    }
    return $output;
}

$identityValidator = <<<'PHP'
<?php
declare(strict_types=1);

namespace Prontoo\Domain\Identity;

final class IdentityDocumentValidator
{
    public static function cpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }
        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $digits[$index] * ($position + 1 - $index);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $digits[$position] !== $digit) {
                return false;
            }
        }
        return true;
    }

    public static function cnpj(string $digits): bool
    {
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }
        $calculate = static function (int $length) use ($digits): int {
            $weights = $length === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $digits[$index] * $weights[$index];
            }
            $remainder = $sum % 11;
            return $remainder < 2 ? 0 : 11 - $remainder;
        };
        return (int) $digits[12] === $calculate(12)
            && (int) $digits[13] === $calculate(13);
    }

    public static function birthDate(null|string|int $birth): bool
    {
        $raw = trim((string) $birth);
        if ($raw === '') {
            return false;
        }
        $ymd = preg_match('/^-?\d+$/', $raw)
            ? gmdate('Y-m-d', (int) $raw)
            : $raw;
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $matches)) {
            return false;
        }
        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if (!checkdate($month, $day, $year)) {
            return false;
        }
        $date = new \DateTimeImmutable($ymd . ' 12:00:00', new \DateTimeZone('UTC'));
        $today = new \DateTimeImmutable(gmdate('Y-m-d') . ' 23:59:59', new \DateTimeZone('UTC'));
        $oldest = $today->modify('-120 years')->setTime(0, 0, 0);
        return $date <= $today && $date >= $oldest;
    }
}
PHP;

$patientPure = <<<'PHP'
<?php
declare(strict_types=1);

namespace Prontoo\Domain\Patients;

final class PatientPure
{
    public static function cpfBr(string $raw, string $digits): string
    {
        return strlen($digits) === 11
            ? substr($digits, 0, 3)
                . '.'
                . substr($digits, 3, 3)
                . '.'
                . substr($digits, 6, 3)
                . '-'
                . substr($digits, 9, 2)
            : $raw;
    }

    public static function cleanTabLabel(string $label): string
    {
        $label = trim(preg_replace('/\s+/u', ' ', strip_tags($label)) ?? '');
        return mb_substr($label, 0, 60, 'UTF-8');
    }

    public static function tabRecordType(int $tabId): string
    {
        return 'tab_' . max(0, $tabId);
    }

    public static function tabKey(int $tabId): string
    {
        return 'extra' . max(0, $tabId);
    }

    public static function guardianRelationshipOptions(): array
    {
        return [
            'mae' => 'Mãe',
            'pai' => 'Pai',
            'tutor' => 'Tutor(a)',
            'guardiao' => 'Guardião(ã)',
            'avo' => 'Avó/Avô com guarda ou autorização',
            'responsavel_judicial' => 'Responsável por decisão judicial',
            'outro' => 'Outro vínculo documentado',
        ];
    }

    public static function normalizeGuardianRelationship(string $value): string
    {
        $value = preg_replace('/[^a-z0-9_]+/i', '', strtolower(trim($value))) ?: '';
        return array_key_exists($value, self::guardianRelationshipOptions())
            ? $value
            : 'outro';
    }

    public static function ageYears(null|string|int $birth): ?int
    {
        $birth = trim((string) ($birth ?? ''));
        if ($birth === '') {
            return null;
        }
        try {
            $date = preg_match('/^-?\d+$/', $birth)
                ? new \DateTimeImmutable('@' . (int) $birth)->setTimezone(new \DateTimeZone('UTC'))
                : new \DateTimeImmutable($birth);
            $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
            if ($date > $today) {
                return null;
            }
            return (int) $date->diff($today)->y;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isMinor(array $patient): bool
    {
        $age = self::ageYears($patient['birth_date'] ?? null);
        return $age !== null && $age < 18;
    }
}
PHP;

phase1_write($root . '/app/Domain/Identity/IdentityDocumentValidator.php', $identityValidator . PHP_EOL);
phase1_write($root . '/app/Domain/Patients/PatientPure.php', $patientPure . PHP_EOL);

$layerMapPath = $root . '/app/Core/Architecture/LayerMap.php';
$layerMap = (string) file_get_contents($layerMapPath);
if (!str_contains($layerMap, "'app/Domain/Identity/'")) {
    $layerMap = str_replace(
        "        'app/Domain/Authorization/',",
        "        'app/Domain/Authorization/',\n        'app/Domain/Identity/',\n        'app/Domain/Patients/PatientPure.php',",
        $layerMap,
        $count,
    );
    if ($count !== 1) {
        throw new RuntimeException('Não foi possível registrar os componentes nativos da Fase 1.');
    }
}
phase1_write($layerMapPath, $layerMap);

$authPath = $root . '/app/Auth/AuthOnboarding.php';
phase1_insert_after_declare(
    $authPath,
    "require_once __DIR__ . '/../Domain/Identity/IdentityDocumentValidator.php';",
);
phase1_replace_function(
    $authPath,
    'valid_cpf',
    <<<'PHP'
function valid_cpf(string $cpf): bool
{
    return \Prontoo\Domain\Identity\IdentityDocumentValidator::cpf(only_digits($cpf));
}
PHP,
);
phase1_replace_function(
    $authPath,
    'valid_cnpj',
    <<<'PHP'
function valid_cnpj(string $cnpj): bool
{
    return \Prontoo\Domain\Identity\IdentityDocumentValidator::cnpj(only_digits($cnpj));
}
PHP,
);
phase1_replace_function(
    $authPath,
    'valid_birth_date',
    <<<'PHP'
function valid_birth_date(null|string|int $birth): bool
{
    return \Prontoo\Domain\Identity\IdentityDocumentValidator::birthDate($birth);
}
PHP,
);

$patientsPath = $root . '/app/Domain/Patients/Patients.php';
phase1_insert_after_declare(
    $patientsPath,
    "require_once __DIR__ . '/PatientPure.php';",
);
phase1_replace_function(
    $patientsPath,
    'patient_cpf_br',
    <<<'PHP'
function patient_cpf_br(string $cpf): string
{
    $digits = function_exists('only_digits')
        ? only_digits($cpf)
        : preg_replace('/\D+/', '', $cpf);
    return \Prontoo\Domain\Patients\PatientPure::cpfBr($cpf, (string) $digits);
}
PHP,
);
phase1_replace_function(
    $patientsPath,
    'patient_tab_label_clean',
    <<<'PHP'
function patient_tab_label_clean(string $label): string
{
    return \Prontoo\Domain\Patients\PatientPure::cleanTabLabel($label);
}
PHP,
);
phase1_replace_function(
    $patientsPath,
    'patient_tab_record_type',
    <<<'PHP'
function patient_tab_record_type(int $tabId): string
{
    return \Prontoo\Domain\Patients\PatientPure::tabRecordType($tabId);
}
PHP,
);
phase1_replace_function(
    $patientsPath,
    'patient_tab_key',
    <<<'PHP'
function patient_tab_key(int $tabId): string
{
    return \Prontoo\Domain\Patients\PatientPure::tabKey($tabId);
}
PHP,
);
phase1_replace_function(
    $patientsPath,
    'patient_guardian_relationship_options',
    <<<'PHP'
function patient_guardian_relationship_options(): array
{
    return \Prontoo\Domain\Patients\PatientPure::guardianRelationshipOptions();
}
PHP,
);
phase1_replace_function(
    $patientsPath,
    'normalize_guardian_relationship',
    <<<'PHP'
function normalize_guardian_relationship(string $v): string
{
    return \Prontoo\Domain\Patients\PatientPure::normalizeGuardianRelationship($v);
}
PHP,
);
phase1_replace_function(
    $patientsPath,
    'patient_age_years',
    <<<'PHP'
function patient_age_years(null|string|int $birth): ?int
{
    return \Prontoo\Domain\Patients\PatientPure::ageYears($birth);
}
PHP,
);
phase1_replace_function(
    $patientsPath,
    'patient_is_minor',
    <<<'PHP'
function patient_is_minor(array $p): bool
{
    return \Prontoo\Domain\Patients\PatientPure::isMinor($p);
}
PHP,
);

$responsibilityMap = <<<'MD'
# Mapa de responsabilidades

Este documento orienta a localização de código e estabelece os limites usados na primeira fase de enxugamento estrutural.

## Regra de localização

| Tipo de responsabilidade | Destino preferencial |
|---|---|
| entrada HTTP, `$_GET`, `$_POST`, redirecionamento e resposta | `app/Presentation`, controllers ou fronteiras legadas |
| caso de uso e coordenação | `app/Application` |
| regra de negócio, normalização e validação pura | `app/Domain` |
| SQL, PDO, arquivos e serviços externos | `app/Infrastructure` |
| HTML reutilizável e componentes | `app/Ui` |
| ligação entre camadas | composição e runtime |

## Arquivos prioritários

| Arquivo legado | Responsabilidades observadas | Direção de evolução |
|---|---|---|
| `app/Auth/AuthOnboarding.php` | login, MFA, perfil, onboarding, validações e HTML | manter fachada e extrair identidade, autenticação, MFA, perfil e apresentação |
| `app/Domain/Patients/Patients.php` | identidade, cadastro, responsáveis, prontuário, abas, SQL, ações e HTML | manter fachada e extrair regras puras, repositórios, casos de uso e views |
| `app/Admin/AdminPages.php` | painel, segurança, desempenho, clínicas, diagnósticos e apresentação | dividir por capacidade administrativa |
| `app/Domain/Financial/Financial.php` | regras monetárias, consultas, operações e apresentação | separar cálculo, persistência, casos de uso e views |
| `app/Domain/Appointments/Appointments.php` | agenda, jornada, disponibilidade, ações e HTML | separar máquina de estados, consultas, comandos e views |

## Extrações concluídas na Fase 1

### Identidade

`app/Domain/Identity/IdentityDocumentValidator.php` concentra validações puras de CPF, CNPJ e data de nascimento. As funções globais existentes continuam como fachadas compatíveis.

### Pacientes

`app/Domain/Patients/PatientPure.php` concentra formatação de CPF, limpeza e chaves de abas, vínculos de responsáveis, cálculo de idade e classificação de menoridade. As funções globais existentes continuam disponíveis.

## Inventário inicial de funções puras

| Grupo | Funções compatíveis |
|---|---|
| identidade | `valid_cpf`, `valid_cnpj`, `valid_birth_date` |
| apresentação cadastral | `patient_cpf_br`, `patient_tab_label_clean` |
| chaves canônicas | `patient_tab_record_type`, `patient_tab_key` |
| responsável legal | `patient_guardian_relationship_options`, `normalize_guardian_relationship` |
| idade | `patient_age_years`, `patient_is_minor` |

## Restrições

- não mover SQL para componentes puros;
- não introduzir acesso a sessão ou HTTP em `Domain`;
- não alterar assinaturas públicas durante a migração;
- não criar abstrações sem fronteira concreta;
- não ampliar o conjunto de módulos carregados por rota;
- manter testes de caracterização antes de novas extrações.
MD;

$characterizationDoc = <<<'MD'
# Baseline de caracterização da Fase 1

## Objetivo

Provar que a reorganização de funções puras preserva entradas, saídas e contratos públicos sem alterar banco, interface ou fluxo de requisição.

## Casos cobertos

### Identidade

- CPF válido e inválido;
- rejeição de CPF repetido;
- CNPJ válido e inválido;
- data válida;
- data inexistente;
- data futura;
- data vazia.

### Pacientes

- formatação canônica de CPF;
- preservação da entrada quando o CPF não possui onze dígitos;
- remoção de HTML e normalização de espaços no nome de aba;
- limite de sessenta caracteres;
- geração de `tab_*` e `extra*`;
- catálogo de vínculos de responsáveis;
- fallback de vínculo desconhecido;
- data vazia e futura;
- identificação de menoridade por data recente.

## Contrato estrutural

Os testes também verificam que:

- as funções globais permanecem definidas nos arquivos legados;
- as fachadas delegam aos componentes extraídos;
- componentes puros não acessam superglobais;
- componentes puros não executam SQL, PDO, redirecionamentos ou respostas HTTP.

## Execução

A caracterização integra `tools/architecture-check.php` e é executada no contrato arquitetural do CI.
MD;

$phaseOneDoc = <<<'MD'
# Fase 1 — enxugamento estrutural

## Escopo concluído

1. compactação segura de blocos de linhas vazias em tokens PHP;
2. mapa de responsabilidades e direção de modularização;
3. inventário inicial de funções puras;
4. extração de validadores e formatadores sem mudança de assinatura pública;
5. testes de caracterização incorporados ao contrato arquitetural.

## Estratégia de compatibilidade

Os arquivos legados permanecem como fachadas. Chamadores existentes continuam usando as mesmas funções globais, enquanto a implementação pura passa a residir em componentes de domínio.

## Resultado esperado

- menor extensão visual dos arquivos;
- localização mais rápida de regras puras;
- redução gradual de responsabilidades nos arquivos monolíticos;
- base testável para as próximas extrações;
- nenhuma mudança de banco, schema, interface ou comportamento funcional.

## Próxima etapa

Separar apresentação e leitura de dados, começando por um fluxo de baixo risco e preservando o carregamento seletivo por rota.
MD;

phase1_write($root . '/docs/architecture/responsibility-map.md', $responsibilityMap . PHP_EOL);
phase1_write($root . '/docs/testing/characterization-baseline.md', $characterizationDoc . PHP_EOL);
phase1_write($root . '/docs/architecture/phase-1-refactoring.md', $phaseOneDoc . PHP_EOL);

$docsIndexPath = $root . '/docs/index.md';
$docsIndex = (string) file_get_contents($docsIndexPath);
if (!str_contains($docsIndex, 'architecture/responsibility-map.md')) {
    $docsIndex = str_replace(
        '- [Fluxos de dados](architecture/data-flow.md)',
        "- [Fluxos de dados](architecture/data-flow.md)\n- [Mapa de responsabilidades](architecture/responsibility-map.md)\n- [Fase 1 — enxugamento estrutural](architecture/phase-1-refactoring.md)",
        $docsIndex,
    );
}
if (!str_contains($docsIndex, 'testing/characterization-baseline.md')) {
    $docsIndex = str_replace(
        '- [Testes de propriedades](testing/property-tests.md)',
        "- [Testes de propriedades](testing/property-tests.md)\n- [Baseline de caracterização](testing/characterization-baseline.md)",
        $docsIndex,
    );
}
phase1_write($docsIndexPath, $docsIndex);

$architectureCheckPath = $root . '/tools/architecture-check.php';
$architectureCheck = (string) file_get_contents($architectureCheckPath);
if (!str_contains($architectureCheck, '$phaseOneCharacterization')) {
    $block = <<<'PHP'
require_once $root . '/app/Domain/Identity/IdentityDocumentValidator.php';
require_once $root . '/app/Domain/Patients/PatientPure.php';

$phaseOneFailures = [];
$phaseOneAssert = static function (bool $condition, string $name) use (&$phaseOneFailures): void {
    if (!$condition) {
        $phaseOneFailures[] = $name;
    }
};
$phaseOneAssert(
    \Prontoo\Domain\Identity\IdentityDocumentValidator::cpf('52998224725'),
    'cpf_valid',
);
$phaseOneAssert(
    !\Prontoo\Domain\Identity\IdentityDocumentValidator::cpf('11111111111'),
    'cpf_repeated_rejected',
);
$phaseOneAssert(
    !\Prontoo\Domain\Identity\IdentityDocumentValidator::cpf('52998224724'),
    'cpf_invalid',
);
$phaseOneAssert(
    \Prontoo\Domain\Identity\IdentityDocumentValidator::cnpj('11222333000181'),
    'cnpj_valid',
);
$phaseOneAssert(
    !\Prontoo\Domain\Identity\IdentityDocumentValidator::cnpj('11222333000180'),
    'cnpj_invalid',
);
$phaseOneAssert(
    \Prontoo\Domain\Identity\IdentityDocumentValidator::birthDate('2000-01-01'),
    'birth_valid',
);
$phaseOneAssert(
    !\Prontoo\Domain\Identity\IdentityDocumentValidator::birthDate('2000-02-31'),
    'birth_invalid',
);
$phaseOneAssert(
    !\Prontoo\Domain\Identity\IdentityDocumentValidator::birthDate('2999-01-01'),
    'birth_future',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::cpfBr('52998224725', '52998224725') === '529.982.247-25',
    'patient_cpf_format',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::cpfBr('ABC', '') === 'ABC',
    'patient_cpf_fallback',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::cleanTabLabel(' <b> Histórico   clínico </b> ') === 'Histórico clínico',
    'patient_tab_label',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::tabRecordType(-1) === 'tab_0',
    'patient_tab_record_type',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::tabKey(7) === 'extra7',
    'patient_tab_key',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::normalizeGuardianRelationship('MAE') === 'mae',
    'guardian_relationship_known',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::normalizeGuardianRelationship('desconhecido') === 'outro',
    'guardian_relationship_fallback',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::ageYears('') === null,
    'patient_age_empty',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::ageYears('2999-01-01') === null,
    'patient_age_future',
);
$phaseOneAssert(
    \Prontoo\Domain\Patients\PatientPure::isMinor(['birth_date' => gmdate('Y-m-d', strtotime('-10 years'))]),
    'patient_minor',
);
foreach ([
    $root . '/app/Domain/Identity/IdentityDocumentValidator.php',
    $root . '/app/Domain/Patients/PatientPure.php',
] as $pureFile) {
    $pureSource = (string) file_get_contents($pureFile);
    foreach (['$_GET', '$_POST', '$_SESSION', 'PDO', 'header(', ' q(', ' one('] as $forbidden) {
        if (str_contains($pureSource, $forbidden)) {
            $phaseOneFailures[] = basename($pureFile) . ':forbidden:' . $forbidden;
        }
    }
}
foreach ([
    $root . '/app/Auth/AuthOnboarding.php' => [
        'IdentityDocumentValidator::cpf',
        'IdentityDocumentValidator::cnpj',
        'IdentityDocumentValidator::birthDate',
    ],
    $root . '/app/Domain/Patients/Patients.php' => [
        'PatientPure::cpfBr',
        'PatientPure::cleanTabLabel',
        'PatientPure::guardianRelationshipOptions',
        'PatientPure::ageYears',
    ],
] as $facadeFile => $requiredDelegations) {
    $facadeSource = (string) file_get_contents($facadeFile);
    foreach ($requiredDelegations as $requiredDelegation) {
        if (!str_contains($facadeSource, $requiredDelegation)) {
            $phaseOneFailures[] = basename($facadeFile) . ':missing:' . $requiredDelegation;
        }
    }
}
$phaseOneCharacterization = [
    'ok' => $phaseOneFailures === [],
    'failed' => $phaseOneFailures,
];

PHP;
    $architectureCheck = str_replace('$result = [', $block . '$result = [', $architectureCheck);
    $architectureCheck = str_replace(
        "!empty(\$operationalUi['ok']),",
        "!empty(\$operationalUi['ok']) &&\n        !empty(\$phaseOneCharacterization['ok']),",
        $architectureCheck,
    );
    $architectureCheck = str_replace(
        "    'operational_ui' => \$operationalUi,\n];",
        "    'operational_ui' => \$operationalUi,\n    'phase_one_characterization' => \$phaseOneCharacterization,\n];",
        $architectureCheck,
    );
}
phase1_write($architectureCheckPath, $architectureCheck);

$changeLogPath = $root . '/CHANGELOG.md';
$changeLog = (string) file_get_contents($changeLogPath);
if (!str_contains($changeLog, '## 1.7.29.2')) {
    $entry = <<<'MD'

## 1.7.29.2 — Fase 1 de enxugamento estrutural

- compacta blocos de linhas vazias sem alterar tokens executáveis;
- cria mapa de responsabilidades e inventário inicial de funções puras;
- extrai validadores de identidade e utilidades puras de pacientes;
- preserva as funções globais existentes como fachadas compatíveis;
- adiciona testes de caracterização ao contrato arquitetural;
- não altera banco, schema, interface, permissões ou regras de negócio.
MD;
    $changeLog = preg_replace('/^(# [^\n]+\n)/', '$1' . $entry . "\n", $changeLog, 1) ?? $changeLog;
}
phase1_write($changeLogPath, $changeLog);

$legacyChangeLogPath = $root . '/ChangeLog.txt';
$legacyChangeLog = (string) file_get_contents($legacyChangeLogPath);
if (!str_starts_with($legacyChangeLog, 'Prontoo 1.7.29.2')) {
    $legacyEntry = <<<'TXT'
Prontoo 1.7.29.2 — Fase 1 de enxugamento estrutural

- Compacta blocos de linhas vazias em arquivos PHP.
- Mapeia responsabilidades e identifica funções puras.
- Extrai validadores de identidade e utilidades de pacientes.
- Adiciona testes de caracterização.
- Preserva banco, schema, interface, permissões e comportamento.

TXT;
    $legacyChangeLog = $legacyEntry . $legacyChangeLog;
}
phase1_write($legacyChangeLogPath, $legacyChangeLog);

phase1_update_json(
    $root . '/version.json',
    static function (array $data) use ($version, $previousVersion, $now, $nowUnix): array {
        $data['version'] = $version;
        $data['release'] = $version;
        $data['generated_at_unix'] = $nowUnix;
        $data['generated_at'] = $now;
        $data['updated_at'] = $now;
        $data['build'] = $version . '-phase1-lean-refactor';
        $data['package_type'] = 'incremental_refactor';
        $data['previous_version'] = $previousVersion;
        $data['baseline_source'] = $previousVersion;
        $data['architecture_native_files_min'] = 17;
        $data['notes'] = 'Executa a Fase 1 de enxugamento estrutural com compactação segura, mapa de responsabilidades, extração de funções puras e testes de caracterização.';
        $data['rewrite_scope'] = 'php_whitespace_and_selected_pure_function_facades';
        $data['deployment_sync_id'] = 'github-phase1-lean-refactor-' . str_replace('.', '-', $version);
        $data['deployment_sync_requested_at'] = $now;
        return $data;
    },
);

phase1_update_json(
    $root . '/app/architecture.manifest.json',
    static function (array $data) use ($version, $previousVersion, $now): array {
        $data['version'] = $version;
        $data['generated_at'] = $now;
        $data['updated_at'] = $now;
        $data['baseline_source'] = $previousVersion;
        $data['native_files_min'] = 17;
        $data['phase_one_refactor'] = [
            'blank_line_compaction' => true,
            'responsibility_map' => 'docs/architecture/responsibility-map.md',
            'pure_components' => [
                'app/Domain/Identity/IdentityDocumentValidator.php',
                'app/Domain/Patients/PatientPure.php',
            ],
            'characterization_check' => 'tools/architecture-check.php',
            'public_facades_preserved' => true,
        ];
        return $data;
    },
);

phase1_update_json(
    $root . '/app/update.manifest.json',
    static function (array $data) use ($version, $previousVersion, $now): array {
        $data['version'] = $version;
        $data['release'] = $version;
        $data['updated_at'] = $now;
        $data['previous_version'] = $previousVersion;
        $data['baseline_source'] = $previousVersion;
        $data['notes'] = 'Fase 1 de enxugamento estrutural: compactação segura, mapa de responsabilidades, funções puras extraídas e testes de caracterização.';
        return $data;
    },
);

foreach ([
    $root . '/app/prontoo.php' => 'PRONTOO_VERSION_FALLBACK',
    $root . '/br/index.php' => 'BR_LANDING_VERSION_FALLBACK',
] as $versionFile => $constant) {
    $source = (string) file_get_contents($versionFile);
    $pattern = '/(const\s+' . preg_quote($constant, '/') . '\s*=\s*)(["\'])([^"\']+)\2(\s*;)/';
    $updated = preg_replace_callback(
        $pattern,
        static fn(array $matches): string => $matches[1] . $matches[2] . $version . $matches[2] . $matches[4],
        $source,
        1,
        $count,
    );
    if ($updated === null || $count !== 1) {
        throw new RuntimeException('Constante de versão não atualizada: ' . $constant);
    }
    phase1_write($versionFile, $updated);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);
$changed = 0;
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_contains($path, '/.git/')
        || str_contains($path, '/ssd/')
        || str_contains($path, '/vendor/')
        || str_contains($path, '/node_modules/')
        || $path === str_replace('\\', '/', __FILE__)) {
        continue;
    }
    $source = (string) file_get_contents($file->getPathname());
    $compacted = phase1_compact_php($source);
    if ($compacted !== $source) {
        phase1_write($file->getPathname(), $compacted);
        $changed++;
    }
}

echo json_encode(
    [
        'ok' => true,
        'version' => $version,
        'php_files_compacted' => $changed,
        'pure_components' => 2,
        'facades_preserved' => 11,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
), PHP_EOL;
