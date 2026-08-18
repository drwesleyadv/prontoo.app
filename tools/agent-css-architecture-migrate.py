#!/usr/bin/env python3
import base64
import json
import re
import shutil
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

ROOT = Path(__file__).resolve().parents[1]
OLD_VERSION = "1.8.18.5"
NEW_VERSION = "1.8.18.6"
STYLE_ROOT = ROOT / "app/Presentation/Styles"
MANIFEST_PATH = STYLE_ROOT / "styles.manifest.json"
BRIDGE_PATH = ROOT / "design/tokens/css-bridge.json"
APPLICATION_PATH = ROOT / "design/styles/application.css"
RUNTIME_TOKENS_PATH = ROOT / "design/tokens/runtime.tokens.json"


def declarations(css: str):
    block_re = re.compile(r"([^{}]+)\{([^{}]*)\}", re.S)
    decl_re = re.compile(r"(--[A-Za-z0-9_-]+)\s*:\s*([^;]+?)(!important)?\s*;", re.S)
    items = []
    for selector, body in block_re.findall(css):
        selector = " ".join(selector.split())
        for name, value, important in decl_re.findall(body):
            items.append((selector, name, " ".join(value.split()), bool(important)))
    return items


def materialize_design_sources():
    manifest = json.loads(MANIFEST_PATH.read_text())
    bridge = json.loads(BRIDGE_PATH.read_text())
    sources = {
        source["path"]: (STYLE_ROOT / source["path"]).read_bytes()
        for source in manifest["sources"]
    }
    behavior = bytearray()
    for segment in manifest["sequence"]:
        if segment["source"].startswith("tokens/"):
            continue
        data = sources[segment["source"]]
        offset = int(segment["offset"])
        length = int(segment["bytes"])
        behavior.extend(data[offset:offset + length])
    APPLICATION_PATH.parent.mkdir(parents=True, exist_ok=True)
    APPLICATION_PATH.write_bytes(bytes(behavior))

    runtime = {"runtime": {"residual": {}, "bridge": {}}}
    sequence = 0
    for target in ("clinic", "material", "semantic"):
        legacy = base64.b64decode(bridge["legacy"][target]["base64"]).decode()
        for selector, name, value, important in declarations(legacy):
            sequence += 1
            runtime["runtime"]["residual"][f"item{sequence:04d}"] = {
                "$type": "string",
                "$value": value,
                "$extensions": {
                    "com.prontoo": {
                        "cssName": name,
                        "cssValue": value,
                        "target": target,
                        "selector": selector,
                        "important": important,
                        "phase": "residual",
                        "sequence": sequence,
                    }
                },
            }
    for index, entry in enumerate(bridge.get("entries", []), start=1):
        value = str(entry.get("value", ""))
        runtime["runtime"]["bridge"][f"item{index:04d}"] = {
            "$type": "string",
            "$value": value,
            "$extensions": {
                "com.prontoo": {
                    "cssName": str(entry.get("name", "")),
                    "cssValue": value,
                    "target": str(entry.get("target", "")),
                    "selector": str(entry.get("selector", "body")),
                    "important": entry.get("important") is True,
                    "order": int(entry.get("order", 0)),
                    "phase": "canonical",
                }
            },
        }
    RUNTIME_TOKENS_PATH.write_text(json.dumps(runtime, ensure_ascii=False, indent=2) + "\n")
    return len(behavior), sequence, len(bridge.get("entries", []))


def remove_legacy_architecture():
    shutil.rmtree(STYLE_ROOT)
    BRIDGE_PATH.unlink()


def bump_release():
    version_path = ROOT / "version.json"
    data = json.loads(version_path.read_text())
    now = datetime.now(ZoneInfo("America/Cuiaba"))
    stamp = now.isoformat(timespec="seconds")
    data.update({
        "version": NEW_VERSION,
        "release": NEW_VERSION,
        "generated_at_unix": int(now.timestamp()),
        "generated_at": stamp,
        "updated_at": stamp,
        "build": "1.8.18.6-design-source-independent",
        "asset_version": NEW_VERSION,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": False,
        "documentation_changes": True,
        "functional_equivalence_policy": "design_sources_independent_from_legacy_css_architecture_with_observable_visual_equivalence",
        "notes": "Design System independente da arquitetura CSS anterior: DTCG gera um bundle único de tokens, estilos comportamentais são lineares em design/styles e presentation.css é montado deterministicamente sem bridge, slices ou manifesto legado.",
        "deployment_sync_id": "github-prontoo-1.8.18.6-design-source-independent",
        "deployment_sync_requested_at": stamp,
        "release_date": now.date().isoformat(),
        "design_token_runtime_policy": "generated_design_token_bundle_without_legacy_bridge",
        "design_token_artifact": "design/generated/tokens.css",
        "design_style_source": "design/styles/application.css",
        "presentation_build_policy": "font_import_then_tokens_plus_application_css",
        "changelog": {
            "title": "Arquitetura de design independente do CSS legado",
            "items": [
                "remove integralmente app/Presentation/Styles e o manifesto de 1.074 slices da arquitetura CSS anterior",
                "substitui o payload Base64 de compatibilidade por tokens runtime DTCG estruturados e auditáveis",
                "consolida os tokens gerados em design/generated/tokens.css e os estilos comportamentais em design/styles/application.css",
                "simplifica presentation.css para uma montagem determinística sem offsets, hashes por slice ou bridge legado",
                "mantém o contrato multitema e o baseline computado sem alteração observável de geometria, tipografia, espaçamento ou fluxos",
                "preserva banco, schema e regras de negócio enquanto reduz arquivos-fonte, metadados e custo de manutenção do Design System",
            ],
        },
    })
    version_path.write_text(json.dumps(data, ensure_ascii=False, indent=4) + "\n")


def republish_assets():
    assets = ROOT / "public/assets"
    pairs = [
        (f"app-icon-{OLD_VERSION}.png", f"app-icon-{NEW_VERSION}.png"),
        (f"prontoo-mark-{OLD_VERSION}.png", f"prontoo-mark-{NEW_VERSION}.png"),
        (f"favicon-{OLD_VERSION}.png", f"favicon-{NEW_VERSION}.png"),
        (f"favicon-{OLD_VERSION}.ico", f"favicon-{NEW_VERSION}.ico"),
        (f"pix-{OLD_VERSION}.svg", f"pix-{NEW_VERSION}.svg"),
    ]
    for old, new in pairs:
        shutil.copyfile(assets / old, assets / new)
        (assets / old).unlink()
    application = APPLICATION_PATH.read_text()
    application = application.replace(f"pix-{OLD_VERSION}.svg", f"pix-{NEW_VERSION}.svg")
    APPLICATION_PATH.write_text(application)
    bootstrap = ROOT / "app/prontoo.php"
    source = bootstrap.read_text()
    expected = f'const PRONTOO_ASSET_REV_FALLBACK = "{OLD_VERSION}";'
    replacement = f'const PRONTOO_ASSET_REV_FALLBACK = "{NEW_VERSION}";'
    if expected not in source:
        raise SystemExit("asset fallback source mismatch")
    bootstrap.write_text(source.replace(expected, replacement, 1))


def update_docs():
    architecture = ROOT / "docs/architecture/design-tokens.md"
    architecture.write_text("""# Design System canônico

## Arquitetura

O Design System usa DTCG 2025.10 como fonte canônica de valores. Os arquivos `design/tokens/*.tokens.json` concentram Reference, System, Component, temas e tokens runtime necessários à ABI do produto. O compilador fixado em `style-dictionary@5.5.0` produz um único artefato `design/generated/tokens.css`.

Os seletores, estados, layouts e regras comportamentais que não são tokens vivem em `design/styles/application.css`. O artefato público `public/assets/presentation.css` é montado deterministicamente com a importação canônica de fontes no topo, seguida do bundle DTCG e do CSS comportamental.

A arquitetura anterior de múltiplos arquivos em `app/Presentation/Styles`, o manifesto de slices e o bridge CSS codificado não fazem parte da árvore canônica. O build falha se qualquer um desses artefatos reaparecer.

## Auditoria de migração

A arquitetura anterior possuía 23 arquivos CSS, 809.496 bytes de fontes, 1.074 slices de reconstrução, manifesto de 347.023 bytes e bridge de 32.369 bytes. O bridge continha 16.817 bytes de CSS codificado. A auditoria demonstrou que os 22 slices de tokens podiam ser movidos para o início da folha sem drift e que 316 declarações runtime ainda necessárias podiam ser representadas como DTCG normal.

O CSS comportamental resultante possui 786.011 bytes lineares. O baseline computado permaneceu estável em 12 cenários por 33 superfícies tanto com os tokens movidos quanto com as declarações residuais estruturadas.

## Invariantes

- Tokens `--pt-ref-*`, `--pt-sys-*` e `--pt-cmp-*` só podem ser definidos pela fonte DTCG.
- A identidade do consultório é resolvida em runtime por custom properties no `body`.
- Sucesso, aviso, erro e informação permanecem semânticos e independentes da cor de identidade.
- O contrato multitema cobre verde, azul, vinho e violeta.
- O baseline computado continua bloqueando regressões de geometria, tipografia, espaçamento e fluxos.
- `presentation.css` deve ser reproduzível exatamente pelas fontes canônicas sob `design/`.
- Payloads Base64 de CSS, offsets de slices e manifestos de cascata são proibidos.

## Fluxo de alteração

Alterações de valor visual entram em `design/tokens/*.tokens.json`. Alterações de seletor ou comportamento entram em `design/styles/application.css`. Depois execute `node tools/design-tokens-build.mjs --write` e `php tools/presentation-css-build --write`. Os modos `--check` são usados pela CI e não podem aceitar drift.
""")
    replacements = {
        "app/Presentation/Styles/styles.manifest.json": "design/styles/application.css",
        "app/Presentation/Styles": "design/styles",
        "design/tokens/css-bridge.json": "design/tokens/runtime.tokens.json",
        "tokens/clinic.css": "design/generated/tokens.css",
        "tokens/material.css": "design/generated/tokens.css",
        "tokens/semantic.css": "design/generated/tokens.css",
    }
    for relative in ("AGENTS.md", "docs/index.md"):
        path = ROOT / relative
        if not path.exists():
            continue
        text = path.read_text()
        for old, new in replacements.items():
            text = text.replace(old, new)
        path.write_text(text)


def main():
    if not STYLE_ROOT.is_dir() or not MANIFEST_PATH.is_file() or not BRIDGE_PATH.is_file():
        raise SystemExit("legacy architecture source unavailable")
    behavior_bytes, residual_count, bridge_count = materialize_design_sources()
    remove_legacy_architecture()
    bump_release()
    republish_assets()
    update_docs()
    print(json.dumps({
        "ok": True,
        "behavior_bytes": behavior_bytes,
        "runtime_residual_tokens": residual_count,
        "runtime_bridge_tokens": bridge_count,
        "legacy_style_root_removed": not STYLE_ROOT.exists(),
        "legacy_bridge_removed": not BRIDGE_PATH.exists(),
        "release": NEW_VERSION,
    }, indent=2))


if __name__ == "__main__":
    main()
