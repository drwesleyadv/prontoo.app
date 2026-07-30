from pathlib import Path
import json
import re
import time
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.6"
PREVIOUS = "1.7.30.5"
BUILD = "1.7.30.6-server-phase6-patient-contact-view"
NOW = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
NOW_UNIX = int(time.time())


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def update_json(path: str, values: dict) -> None:
    target = ROOT / path
    data = json.loads(target.read_text(encoding="utf-8"))
    data.update(values)
    target.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")


write("app/Presentation/Patients/PatientContactView.php", """<?php
declare(strict_types=1);

namespace Prontoo\\Presentation\\Patients;

final class PatientContactView
{
    public static function editForm(
        array $patient,
        int $clinicId,
        callable $actionLabel,
        callable $csrfField,
        callable $input,
        callable $formRow,
        callable $addressFields,
        callable $formActions,
    ): string {
        return '<details class="patient-edit patient-contact-edit"><summary class="primary small cmdlike">' .
            $actionLabel('Atualizar contato', 'contact_phone') .
            '</summary><form method="post" class="compact patient-record-form">' .
            $csrfField() .
            '<input type="hidden" name="act" value="update_patient_contact">' .
            '<div class="two">' .
            $formRow(
                'Telefone',
                $input(
                    'phone',
                    'text',
                    $patient['phone'] ?? '',
                    'required inputmode="tel"',
                ),
            ) .
            $formRow(
                'E-mail',
                $input('email', 'email', $patient['email'] ?? '', 'required'),
            ) .
            '</div>' .
            $addressFields($clinicId, $patient) .
            $formActions('Salvar contato') .
            '</form></details>';
    }
}
""")

patients_path = ROOT / "app/Domain/Patients/Patients.php"
patients = patients_path.read_text(encoding="utf-8")
pattern = re.compile(
    r'(?m)^(?P<indent>\s*)\$patientContactEdit = \$canUpdatePatientContact\n'
    r'(?:(?!^(?P=indent)\$[A-Za-z_]).)*?'
    r'^(?P=indent)\s*: "";\n',
    re.DOTALL,
)
matches = list(pattern.finditer(patients))
if len(matches) != 2:
    raise SystemExit(f"Esperadas 2 renderizações de contato, encontradas {len(matches)}")

def replacement(match: re.Match) -> str:
    indent = match.group('indent')
    return (
        f'{indent}$patientContactEdit = $canUpdatePatientContact\n'
        f'{indent}    ? prontoo_patient_contact_edit_form($p, $cid)\n'
        f'{indent}    : "";\n'
    )
patients = pattern.sub(replacement, patients)
patients_path.write_text(patients, encoding="utf-8")

runner_path = ROOT / "app/Runtime/Runner.php"
runner = runner_path.read_text(encoding="utf-8")
marker = "function prontoo_patient_contact_command_service(): \\Prontoo\\Application\\Patients\\PatientContactCommandService\n"
if runner.count(marker) != 1:
    raise SystemExit("Marcador de composição do contato divergente")
bridge = '''function prontoo_patient_contact_edit_form(array $patient, int $clinicId): string
{
    return \\Prontoo\\Presentation\\Patients\\PatientContactView::editForm(
        $patient,
        $clinicId,
        static fn(string $label, string $iconName): string => action_summary_label($label, $iconName),
        static fn(): string => csrf_field(),
        static fn(string $name, string $type, mixed $value, string $attributes): string => input(
            $name,
            $type,
            $value,
            $attributes,
        ),
        static fn(string $label, string $control): string => form_row($label, $control),
        static fn(int $cid, array $value): string => patient_address_fields($cid, $value),
        static fn(string $label): string => form_actions($label),
    );
}

'''
runner_path.write_text(runner.replace(marker, bridge + marker, 1), encoding="utf-8")

loader_path = ROOT / "app/Support/ModuleLoader.php"
loader = loader_path.read_text(encoding="utf-8")
needle = "        'Presentation/Patients/PatientTabView.php',\n"
addition = "        'Presentation/Patients/PatientContactView.php',\n"
if loader.count(needle) != 2:
    raise SystemExit(f"ModuleLoader: esperadas 2 views de pacientes, encontrado {loader.count(needle)}")
loader_path.write_text(loader.replace(needle, needle + addition), encoding="utf-8")

architecture_path = ROOT / "tools/architecture-check.php"
source = architecture_path.read_text(encoding="utf-8")
marker = "$result = [\n"
index = source.rfind(marker)
if index < 0:
    raise SystemExit("Marcador final do contrato arquitetural divergente")
block = r'''require_once $root . '/app/Presentation/Patients/PatientContactView.php';

$serverPhaseSixFailures = [];
$contactViewHtml = \Prontoo\Presentation\Patients\PatientContactView::editForm(
    ['phone' => '65999990000', 'email' => 'contato@example.com'],
    7,
    static fn(string $label, string $iconName): string => '<action>' . $label . ':' . $iconName . '</action>',
    static fn(): string => '<csrf>',
    static fn(string $name, string $type, mixed $value, string $attributes): string =>
        '<input-helper>' . $name . ':' . $type . ':' . $value . ':' . $attributes . '</input-helper>',
    static fn(string $label, string $control): string => '<row>' . $label . $control . '</row>',
    static fn(int $clinicId, array $patient): string => '<address>' . $clinicId . ':' . ($patient['phone'] ?? '') . '</address>',
    static fn(string $label): string => '<actions>' . $label . '</actions>',
);
$expectedContactViewHtml = '<details class="patient-edit patient-contact-edit"><summary class="primary small cmdlike"><action>Atualizar contato:contact_phone</action></summary><form method="post" class="compact patient-record-form"><csrf><input type="hidden" name="act" value="update_patient_contact"><div class="two"><row>Telefone<input-helper>phone:text:65999990000:required inputmode="tel"</input-helper></row><row>E-mail<input-helper>email:email:contato@example.com:required</input-helper></row></div><address>7:65999990000</address><actions>Salvar contato</actions></form></details>';
if ($contactViewHtml !== $expectedContactViewHtml) {
    $serverPhaseSixFailures[] = 'patient_contact_view_snapshot';
}
$serverPhaseSixSources = [
    'view' => (string) file_get_contents($root . '/app/Presentation/Patients/PatientContactView.php'),
    'patients' => (string) file_get_contents($root . '/app/Domain/Patients/Patients.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
    'loader' => (string) file_get_contents($root . '/app/Support/ModuleLoader.php'),
];
foreach (['SELECT ', 'INSERT ', 'UPDATE ', 'DELETE ', '$_GET', '$_POST', '$_SESSION'] as $token) {
    if (str_contains($serverPhaseSixSources['view'], $token)) {
        $serverPhaseSixFailures[] = 'contact_view:forbidden:' . $token;
    }
}
if (substr_count($serverPhaseSixSources['patients'], 'prontoo_patient_contact_edit_form($p, $cid)') !== 2) {
    $serverPhaseSixFailures[] = 'contact_view_facade_delegations';
}
if (str_contains($serverPhaseSixSources['patients'], '<details class="patient-edit patient-contact-edit">')) {
    $serverPhaseSixFailures[] = 'contact_view_markup_still_duplicated';
}
foreach ([
    'runner' => ['PatientContactView::editForm(', 'patient_address_fields($cid, $value)'],
    'loader' => ["'Presentation/Patients/PatientContactView.php'"],
] as $sourceKey => $tokens) {
    foreach ($tokens as $token) {
        if (!str_contains($serverPhaseSixSources[$sourceKey], $token)) {
            $serverPhaseSixFailures[] = $sourceKey . ':missing:' . $token;
        }
    }
}
$serverPhaseSixCharacterization = [
    'ok' => $serverPhaseSixFailures === [],
    'failed' => $serverPhaseSixFailures,
];
'''
source = source[:index] + block + source[index:]
source = source.replace(
    "        !empty($serverPhaseFiveCharacterization['ok']),",
    "        !empty($serverPhaseFiveCharacterization['ok']) &&\n        !empty($serverPhaseSixCharacterization['ok']),",
    1,
)
source = source.replace(
    "    'server_phase_five_characterization' => $serverPhaseFiveCharacterization,",
    "    'server_phase_five_characterization' => $serverPhaseFiveCharacterization,\n    'server_phase_six_characterization' => $serverPhaseSixCharacterization,",
    1,
)
architecture_path.write_text(source, encoding="utf-8")

write("docs/architecture/patient-contact-view.md", """# View de contato do paciente

## Objetivo

A Fase 6 remove duas cópias idênticas do formulário de contato da fachada `Patients.php` e estabelece uma única implementação em apresentação.

## Fronteira

A view recebe o paciente, o consultório e callbacks dos componentes visuais existentes. Ela não acessa HTTP, sessão, SQL ou persistência. O runtime faz a composição e a fachada mantém apenas a decisão de permissão.

## Compatibilidade

A estrutura HTML, classes, nomes dos campos, atributos obrigatórios, CSRF, endereço e ação de envio são preservados por snapshot determinístico.

## Manutenção

Mudanças futuras no formulário passam a ocorrer em um único arquivo. A fachada continua compatível durante a migração e não recebe nova lógica de apresentação.
""")

changelog = ROOT / "CHANGELOG.md"
text = changelog.read_text(encoding="utf-8")
entry = """\n## 1.7.30.6 — Evolução do servidor, Fase 6: view de contato do paciente

- remove duas renderizações duplicadas do formulário de contato;
- move o HTML específico para uma view de apresentação;
- preserva componentes, campos, atributos, CSRF e ação do formulário;
- adiciona snapshot determinístico e contrato de fronteira;
- não altera banco, schema, interface ou comportamento.\n"""
if entry.strip() not in text:
    changelog.write_text(text.replace("# Histórico de versões\n", "# Histórico de versões\n" + entry, 1), encoding="utf-8")
change_txt = ROOT / "ChangeLog.txt"
text = change_txt.read_text(encoding="utf-8")
entry = """Prontoo 1.7.30.6 — Evolução do servidor, Fase 6: view de contato do paciente

- Centraliza o formulário de contato em apresentação e remove duplicação da fachada.
- Mantém banco, schema, interface, permissões e comportamento funcional.
"""
if not text.startswith("Prontoo 1.7.30.6"):
    change_txt.write_text(entry + text, encoding="utf-8")
docs_index = ROOT / "docs/index.md"
text = docs_index.read_text(encoding="utf-8")
line = "- [View de contato do paciente](architecture/patient-contact-view.md)\n"
if line not in text:
    docs_index.write_text(text.rstrip() + "\n" + line, encoding="utf-8")

for path, constant in [("app/prontoo.php", "PRONTOO_VERSION_FALLBACK"), ("br/index.php", "BR_LANDING_VERSION_FALLBACK")]:
    target = ROOT / path
    current = target.read_text(encoding="utf-8")
    pattern_version = rf"(const\s+{constant}\s*=\s*['\"])[^'\"]+(['\"]\s*;)"
    current, count = re.subn(pattern_version, rf"\g<1>{VERSION}\g<2>", current, count=1)
    if count != 1:
        raise SystemExit(f"Fallback não localizado: {path}")
    target.write_text(current, encoding="utf-8")

common = {
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "package_type": "incremental_refactor",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": False,
    "visual_changes": False,
    "functional_equivalence_policy": "preserves_1_7_30_5_runtime_behavior",
    "previous_version": PREVIOUS,
    "documentation_changes": True,
    "notes": "Centraliza o formulário de contato em view de apresentação e remove duplicação legada.",
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "patient_contact_presentation_extraction",
    "deployment_sync_id": "github-server-phase6-patient-contact-view-1-7-30-6",
    "deployment_sync_requested_at": NOW,
}
update_json("version.json", common)
update_json("app/update.manifest.json", common)
update_json("app/architecture.manifest.json", {
    "version": VERSION,
    "generated_at": NOW,
    "updated_at": NOW,
    "baseline_source": PREVIOUS,
    "native_files_min": 37,
    "transitional_files_max": 80,
    "server_evolution_phase_six_policy": "duplicated_patient_contact_markup_is_one_presentation_view_with_snapshot",
    "server_evolution_phase_six_components": ["app/Presentation/Patients/PatientContactView.php"],
    "server_evolution_phase_six_check": "tools/architecture-check.php",
})
