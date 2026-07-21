from __future__ import annotations

import hashlib
import json
import os
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.21.2"
PREVIOUS = "1.7.21.1"
GENERATED_AT = "2026-07-21T14:14:53Z"
GENERATED_AT_UNIX = 1784643293
BRANCH = os.environ.get("GITHUB_HEAD_REF") or "agent/hotfix-1.7.21.2-installer-integrity"


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(path: str, old: str, new: str) -> None:
    file = ROOT / path
    text = file.read_text(encoding="utf-8")
    if text.count(old) != 1:
        raise RuntimeError(f"{path}: trecho esperado não encontrado de forma única")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


replace_once(
    "app/prontoo.php",
    'const PRONTOO_VERSION_FALLBACK = "1.7.21.1";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.21.2";',
)
replace_once(
    "app/prontoo.php",
    'const PRONTOO_PREVIOUS_VERSION = "1.7.20.6";',
    'const PRONTOO_PREVIOUS_VERSION = "1.7.21.1";',
)
replace_once(
    "br/index.php",
    'const BR_LANDING_VERSION_FALLBACK = "1.7.21.1";',
    'const BR_LANDING_VERSION_FALLBACK = "1.7.21.2";',
)

version_file = ROOT / "version.json"
version = json.loads(version_file.read_text(encoding="utf-8"))
version.update(
    {
        "version": VERSION,
        "release": VERSION,
        "generated_at_unix": GENERATED_AT_UNIX,
        "generated_at": GENERATED_AT,
        "updated_at": GENERATED_AT,
        "build": "1.7.21.2-installer-integrity-signature-hotfix",
        "package_type": "clean_install_integrity_signature_hotfix",
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": False,
        "notes": "Corrige a compatibilidade da prova estrutural usada pelo instalador e faz o CI carregar o Guardião no mesmo contexto da instalação real.",
    }
)
version_file.write_text(json.dumps(version, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

for path in [
    "app/Database/operational-schema.contract.json",
    "app/architecture.manifest.json",
]:
    file = ROOT / path
    data = json.loads(file.read_text(encoding="utf-8"))
    data["version"] = VERSION
    if path.endswith("architecture.manifest.json"):
        data["database_changes"] = False
        data["schema_changes"] = False
        data["visual_changes"] = False
    file.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

changelog = ROOT / "ChangeLog.txt"
entry = """Prontoo 1.7.21.2 — corrige integração do instalador com o Guardião

- Normaliza a chamada legada de run_schema_sql() para o contrato atual de PiIntegrity::proveSchemaOperation().
- Extrai o nome da tabela diretamente do CREATE TABLE antes de produzir a prova estrutural.
- Impede que um TypeError do Guardião interrompa a instalação depois do primeiro DDL.
- Faz o CI carregar PiIntegrity no mesmo contexto da instalação real em MySQL 8.
- Mantém as 62 tabelas, o schema SQL e a revisão r7 integralmente inalterados.

"""
current = changelog.read_text(encoding="utf-8")
if not current.startswith("Prontoo 1.7.21.2"):
    changelog.write_text(entry + current, encoding="utf-8")

write(
    "HOTFIX-1.7.21.2.md",
    """# Hotfix Prontoo 1.7.21.2

## Incidente

Na instalação real, `run_schema_sql()` ainda invocava `PiIntegrity::proveSchemaOperation()` no formato anterior: SQL, sucesso e erro. O contrato atual exige operação, nome da tabela, sucesso e erro. Como o segundo argumento recebido era booleano, o PHP 8.4 lançava `TypeError` depois que a primeira tabela já havia sido criada.

## Correção

- o Guardião normaliza a invocação estrutural legada do instalador;
- o nome da tabela é extraído apenas de um `CREATE TABLE` canônico;
- chamadas novas continuam exigindo operação, tabela, sucesso e erro nos tipos corretos;
- o workflow carrega `PiIntegrity` antes de `tools/schema-check.php` e executa `install_fresh_schema()` em MySQL 8 partindo de zero tabelas.

## Escopo

Não há alteração no `schema.sql`, na revisão `prontoo_1_7_20_6_clean_schema_r7_layer2_ledger`, nas 62 tabelas, em dados, interface ou ativos.
""",
)

final_workflow = """name: Architecture Contract

on:
  pull_request:
  push:
    branches: [main]

permissions:
  contents: read

jobs:
  verify:
    runs-on: ubuntu-latest
    timeout-minutes: 12
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: prontoo_schema
        ports:
          - 3306:3306
        options: >-
          --health-cmd=\"mysqladmin ping -h 127.0.0.1 -proot\"
          --health-interval=5s
          --health-timeout=5s
          --health-retries=20
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none
          tools: none
          extensions: pdo_mysql

      - name: PHP syntax
        shell: bash
        run: |
          set -euo pipefail
          while IFS= read -r -d '' file; do
            php -l \"$file\" >/dev/null
          done < <(find . \\
            -path './.git' -prune -o \\
            -path './storage' -prune -o \\
            -path './vendor' -prune -o \\
            -path './node_modules' -prune -o \\
            -name '*.php' -print0)

      - name: JSON contracts
        shell: bash
        run: |
          php -r '
            foreach ([\"version.json\", \"app/update.manifest.json\", \"app/architecture.manifest.json\"] as $file) {
              $json = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
              if (!is_array($json)) { throw new RuntimeException(\"JSON inválido: \" . $file); }
            }
          '

      - name: Release manifest
        shell: bash
        run: |
          php -r '
            $manifest = json_decode((string) file_get_contents(\"app/update.manifest.json\"), true, 512, JSON_THROW_ON_ERROR);
            $files = (array) ($manifest[\"files\"] ?? []);
            if (count($files) !== (int) ($manifest[\"file_count\"] ?? -1)) {
              throw new RuntimeException(\"file_count divergente\");
            }
            $bytes = 0;
            foreach ($files as $file => $expected) {
              if (!is_file($file)) { throw new RuntimeException(\"Arquivo ausente: \" . $file); }
              $actual = hash_file(\"sha256\", $file);
              if (!hash_equals((string) $expected, (string) $actual)) {
                throw new RuntimeException(\"Hash divergente: \" . $file);
              }
              $bytes += (int) filesize($file);
            }
            if ($bytes !== (int) ($manifest[\"total_uncompressed_bytes\"] ?? -1)) {
              throw new RuntimeException(\"total_uncompressed_bytes divergente\");
            }
            foreach ((array) ($manifest[\"removed_files\"] ?? []) as $file) {
              if (file_exists((string) $file)) {
                throw new RuntimeException(\"Arquivo legado ainda presente: \" . $file);
              }
            }
          '

      - name: Static schema contract with Guardian loaded
        shell: bash
        run: |
          php -d auto_prepend_file=app/Core/Integrity/PiIntegrity.php tools/schema-check.php

      - name: Full zero-table installer with Guardian loaded on MySQL 8
        shell: bash
        env:
          PRONTOO_SCHEMA_DSN: mysql:host=127.0.0.1;port=3306;dbname=prontoo_schema;charset=utf8mb4
          PRONTOO_SCHEMA_USER: root
          PRONTOO_SCHEMA_PASS: root
        run: |
          php -d auto_prepend_file=app/Core/Integrity/PiIntegrity.php tools/schema-check.php | tee schema-report.json

      - name: Layered architecture
        shell: bash
        run: |
          set -o pipefail
          php tools/architecture-check.php | tee architecture-report.json

      - name: Upload architecture report
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: architecture-report
          path: |
            architecture-report.json
            schema-report.json
          if-no-files-found: error
          retention-days: 7
"""
write(".github/workflows/architecture.yml", final_workflow)

for temporary in [
    ".github/workflows/agent-hotfix-1.7.21.2.yml",
    ".github/agent-hotfix-1.7.21.2.trigger",
    "tools/agent-hotfix-1.7.21.2.py",
]:
    path = ROOT / temporary
    if path.exists():
        path.unlink()

changed_files = [
    ".github/workflows/architecture.yml",
    "ChangeLog.txt",
    "HOTFIX-1.7.21.2.md",
    "app/Core/Integrity/PiIntegrity.php",
    "app/Database/operational-schema.contract.json",
    "app/architecture.manifest.json",
    "app/prontoo.php",
    "br/index.php",
    "version.json",
]
files: dict[str, str] = {}
total_bytes = 0
for path in changed_files:
    raw = (ROOT / path).read_bytes()
    files[path] = hashlib.sha256(raw).hexdigest()
    total_bytes += len(raw)

manifest_file = ROOT / "app/update.manifest.json"
manifest = json.loads(manifest_file.read_text(encoding="utf-8"))
manifest.update(
    {
        "version": VERSION,
        "release": VERSION,
        "schema_revision": "prontoo_1_7_20_6_clean_schema_r7_layer2_ledger",
        "build": "1.7.21.2-installer-integrity-signature-hotfix",
        "package_type": "clean_install_integrity_signature_hotfix_incremental",
        "generated_at": GENERATED_AT,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "clean_install_schema": True,
        "requires_empty_database": True,
        "installer_policy": "runtime_guardian_and_install_fresh_schema_must_pass_together_on_mysql_with_zero_initial_tables",
        "schema_table_count": 62,
        "operational_tables_verified": 60,
        "file_count": len(files),
        "total_uncompressed_bytes": total_bytes,
        "files": files,
    }
)
manifest_file.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

subprocess.run(["git", "config", "user.name", "github-actions[bot]"], check=True)
subprocess.run(
    [
        "git",
        "config",
        "user.email",
        "41898282+github-actions[bot]@users.noreply.github.com",
    ],
    check=True,
)
subprocess.run(["git", "add", "-A"], check=True)
subprocess.run(
    ["git", "commit", "-m", "Finaliza Prontoo 1.7.21.2"],
    check=True,
)
subprocess.run(["git", "push", "origin", f"HEAD:{BRANCH}"], check=True)
