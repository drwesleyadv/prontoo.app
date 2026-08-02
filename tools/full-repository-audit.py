#!/usr/bin/env python3
"""Auditoria determinística de todos os arquivos rastreados do repositório."""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
import os
import re
import subprocess
import sys
import zipfile
from collections import Counter, defaultdict
from html.parser import HTMLParser
from pathlib import Path
from typing import Any
from xml.etree import ElementTree

try:
    import yaml  # type: ignore
except Exception:  # pragma: no cover
    yaml = None

try:
    from PIL import Image  # type: ignore
except Exception:  # pragma: no cover
    Image = None

ROOT = Path(__file__).resolve().parents[1]
MANIFEST_PATH = ROOT / "app/update.manifest.json"
BINARY_EXTENSIONS = {
    ".png", ".jpg", ".jpeg", ".gif", ".webp", ".ico", ".bmp", ".avif",
    ".pdf", ".zip", ".gz", ".tgz", ".woff", ".woff2", ".ttf", ".otf",
    ".mp3", ".mp4", ".mov", ".webm", ".sqlite", ".db",
}
IMAGE_EXTENSIONS = {".png", ".jpg", ".jpeg", ".gif", ".webp", ".ico", ".bmp"}
TEXT_EXTENSIONS = {
    ".php", ".js", ".mjs", ".cjs", ".css", ".scss", ".html", ".htm",
    ".json", ".yml", ".yaml", ".xml", ".svg", ".md", ".txt", ".sql",
    ".sh", ".bash", ".csv", ".ini", ".conf", ".htaccess", ".gitignore",
    ".gitattributes", ".editorconfig", ".lock",
}
CONFLICT_RE = re.compile(r"^(<<<<<<<|=======|>>>>>>>)", re.MULTILINE)
SECRET_PATTERNS = {
    "private_key": re.compile(r"-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----"),
    "github_token": re.compile(r"\b(?:ghp_[A-Za-z0-9]{36}|github_pat_[A-Za-z0-9_]{50,})\b"),
    "aws_access_key": re.compile(r"\bAKIA[0-9A-Z]{16}\b"),
    "stripe_live_key": re.compile(r"\bsk_live_[A-Za-z0-9]{20,}\b"),
}
SENSITIVE_BASENAMES = {
    ".env", ".env.local", ".env.production", "id_rsa", "id_dsa", "id_ed25519",
}


class LenientHTMLParser(HTMLParser):
    def error(self, message: str) -> None:  # pragma: no cover
        raise ValueError(message)


def run(command: list[str], timeout: int = 90) -> tuple[int, str]:
    proc = subprocess.run(
        command,
        cwd=ROOT,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        encoding="utf-8",
        errors="replace",
        timeout=timeout,
        check=False,
    )
    return proc.returncode, proc.stdout.strip()


def tracked_files() -> list[str]:
    raw = subprocess.check_output(["git", "ls-files", "-z"], cwd=ROOT)
    return sorted(part.decode("utf-8") for part in raw.split(b"\0") if part)


def tracked_modes() -> dict[str, str]:
    code, output = run(["git", "ls-files", "-s"])
    if code != 0:
        raise RuntimeError(output)
    modes: dict[str, str] = {}
    for line in output.splitlines():
        match = re.match(r"^(\d+)\s+[0-9a-f]+\s+\d+\t(.+)$", line)
        if match:
            modes[match.group(2)] = match.group(1)
    return modes


def is_probably_text(path: Path, data: bytes) -> bool:
    if path.suffix.lower() in BINARY_EXTENSIONS:
        return False
    if path.suffix.lower() in TEXT_EXTENSIONS or path.name in {"Dockerfile", "LICENSE", "README"}:
        return True
    return b"\0" not in data[:8192]


def css_balanced(text: str) -> tuple[bool, str]:
    depth = 0
    quote: str | None = None
    escaped = False
    comment = False
    index = 0
    while index < len(text):
        char = text[index]
        nxt = text[index + 1] if index + 1 < len(text) else ""
        if comment:
            if char == "*" and nxt == "/":
                comment = False
                index += 2
                continue
            index += 1
            continue
        if quote:
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == quote:
                quote = None
            index += 1
            continue
        if char == "/" and nxt == "*":
            comment = True
            index += 2
            continue
        if char in {"'", '"'}:
            quote = char
        elif char == "{":
            depth += 1
        elif char == "}":
            depth -= 1
            if depth < 0:
                return False, "chave de fechamento sem abertura"
        index += 1
    if comment:
        return False, "comentário CSS não encerrado"
    if quote:
        return False, "string CSS não encerrada"
    if depth != 0:
        return False, f"saldo de chaves CSS: {depth}"
    return True, ""


def markdown_local_links(path: Path, text: str) -> list[str]:
    broken: list[str] = []
    for raw_target in re.findall(r"!?\[[^\]]*\]\(([^)]+)\)", text):
        target = raw_target.strip().split()[0].strip("<>")
        if not target or target.startswith(("#", "http://", "https://", "mailto:", "tel:", "data:")):
            continue
        target = target.split("#", 1)[0].split("?", 1)[0]
        if not target:
            continue
        resolved = (path.parent / target).resolve()
        try:
            resolved.relative_to(ROOT.resolve())
        except ValueError:
            broken.append(raw_target)
            continue
        if not resolved.exists():
            broken.append(raw_target)
    return broken


def manifest_eligible(paths: list[str]) -> list[str]:
    excluded_prefixes = ("ssd/", "vendor/", "node_modules/", ".git/")
    return [
        p for p in paths
        if p != "app/update.manifest.json" and not p.startswith(excluded_prefixes)
    ]


def audit_manifest(paths: list[str], failures: list[dict[str, str]]) -> dict[str, Any]:
    result: dict[str, Any] = {"checked": False}
    if not MANIFEST_PATH.is_file():
        failures.append({"file": "app/update.manifest.json", "check": "manifest", "message": "manifesto ausente"})
        return result
    try:
        manifest = json.loads(MANIFEST_PATH.read_text(encoding="utf-8"))
    except Exception as exc:
        failures.append({"file": "app/update.manifest.json", "check": "manifest", "message": str(exc)})
        return result
    declared = manifest.get("files")
    if not isinstance(declared, dict):
        failures.append({"file": "app/update.manifest.json", "check": "manifest", "message": "campo files inválido"})
        return result
    expected = manifest_eligible(paths)
    declared_paths = sorted(str(p) for p in declared)
    missing = sorted(set(expected) - set(declared_paths))
    extra = sorted(set(declared_paths) - set(expected))
    mismatched: list[str] = []
    total_bytes = 0
    for rel in expected:
        full = ROOT / rel
        total_bytes += full.stat().st_size
        digest = hashlib.sha256(full.read_bytes()).hexdigest()
        if str(declared.get(rel, "")) != digest:
            mismatched.append(rel)
    if missing:
        failures.append({"file": "app/update.manifest.json", "check": "manifest", "message": "arquivos ausentes: " + ", ".join(missing[:20])})
    if extra:
        failures.append({"file": "app/update.manifest.json", "check": "manifest", "message": "entradas excedentes: " + ", ".join(extra[:20])})
    if mismatched:
        failures.append({"file": "app/update.manifest.json", "check": "manifest", "message": "hash divergente: " + ", ".join(mismatched[:20])})
    if int(manifest.get("file_count", -1)) != len(expected):
        failures.append({"file": "app/update.manifest.json", "check": "manifest", "message": f"file_count={manifest.get('file_count')} esperado={len(expected)}"})
    if int(manifest.get("total_uncompressed_bytes", -1)) != total_bytes:
        failures.append({"file": "app/update.manifest.json", "check": "manifest", "message": f"total_uncompressed_bytes={manifest.get('total_uncompressed_bytes')} esperado={total_bytes}"})
    result.update({
        "checked": True,
        "expected_files": len(expected),
        "declared_files": len(declared_paths),
        "missing": missing,
        "extra": extra,
        "hash_mismatches": mismatched,
        "expected_bytes": total_bytes,
    })
    return result


def audit() -> dict[str, Any]:
    paths = tracked_files()
    modes = tracked_modes()
    failures: list[dict[str, str]] = []
    warnings: list[dict[str, str]] = []
    counts: Counter[str] = Counter()
    bytes_by_type: Counter[str] = Counter()
    checks: Counter[str] = Counter()
    tools: list[str] = []

    if not paths:
        failures.append({"file": ".", "check": "inventory", "message": "git ls-files não retornou arquivos"})

    for rel in paths:
        path = ROOT / rel
        suffix = path.suffix.lower() or "[sem extensão]"
        counts[suffix] += 1
        if rel.startswith("tools/"):
            tools.append(rel)
        mode = modes.get(rel, "")
        if mode == "120000":
            failures.append({"file": rel, "check": "symlink", "message": "link simbólico rastreado"})
            continue
        if not path.exists() or not path.is_file():
            failures.append({"file": rel, "check": "existence", "message": "arquivo rastreado ausente ou não regular"})
            continue
        data = path.read_bytes()
        bytes_by_type[suffix] += len(data)
        checks["inventário"] += 1
        if path.name in SENSITIVE_BASENAMES or path.suffix.lower() in {".pem", ".key", ".p12", ".pfx"}:
            failures.append({"file": rel, "check": "sensitive_file", "message": "arquivo sensível não deve ser versionado"})
        if is_probably_text(path, data):
            try:
                text = data.decode("utf-8")
            except UnicodeDecodeError as exc:
                failures.append({"file": rel, "check": "utf8", "message": str(exc)})
                continue
            checks["UTF-8"] += 1
            if CONFLICT_RE.search(text):
                failures.append({"file": rel, "check": "merge_markers", "message": "marcador de conflito Git encontrado"})
            if "\x00" in text:
                failures.append({"file": rel, "check": "nul", "message": "byte NUL em arquivo textual"})
            for name, pattern in SECRET_PATTERNS.items():
                if pattern.search(text):
                    failures.append({"file": rel, "check": "secret", "message": f"padrão de segredo de alta confiança: {name}"})
            trailing = sum(1 for line in text.splitlines() if line.endswith((" ", "\t")))
            if trailing:
                warnings.append({"file": rel, "check": "trailing_whitespace", "message": f"{trailing} linha(s)"})
            if data.startswith(b"\xef\xbb\xbf"):
                warnings.append({"file": rel, "check": "bom", "message": "UTF-8 BOM"})
            if suffix == ".md":
                broken = markdown_local_links(path, text)
                if broken:
                    warnings.append({"file": rel, "check": "markdown_links", "message": "links locais não resolvidos: " + ", ".join(broken[:10])})
            if suffix == ".css":
                ok, message = css_balanced(text)
                checks["CSS lexical"] += 1
                if not ok:
                    failures.append({"file": rel, "check": "css", "message": message})
            if suffix in {".html", ".htm"}:
                try:
                    parser = LenientHTMLParser(convert_charrefs=True)
                    parser.feed(text)
                    parser.close()
                    checks["HTML parser"] += 1
                except Exception as exc:
                    failures.append({"file": rel, "check": "html", "message": str(exc)})

        if suffix == ".php":
            code, output = run(["php", "-l", rel])
            checks["PHP lint"] += 1
            if code != 0:
                failures.append({"file": rel, "check": "php_lint", "message": output[-1000:]})
        elif suffix == ".json":
            try:
                json.loads(data.decode("utf-8-sig"))
                checks["JSON parser"] += 1
            except Exception as exc:
                failures.append({"file": rel, "check": "json", "message": str(exc)})
        elif suffix in {".yml", ".yaml"}:
            if yaml is None:
                failures.append({"file": rel, "check": "yaml", "message": "PyYAML não instalado"})
            else:
                try:
                    yaml.safe_load(data.decode("utf-8-sig"))
                    checks["YAML parser"] += 1
                except Exception as exc:
                    failures.append({"file": rel, "check": "yaml", "message": str(exc)})
        elif suffix in {".js", ".mjs", ".cjs"}:
            code, output = run(["node", "--check", rel])
            checks["JavaScript syntax"] += 1
            if code != 0:
                failures.append({"file": rel, "check": "javascript", "message": output[-1000:]})
        elif suffix in {".sh", ".bash"} or data.startswith(b"#!/usr/bin/env bash") or data.startswith(b"#!/bin/bash"):
            code, output = run(["bash", "-n", rel])
            checks["Bash syntax"] += 1
            if code != 0:
                failures.append({"file": rel, "check": "bash", "message": output[-1000:]})
        elif suffix in {".xml", ".svg"}:
            try:
                ElementTree.fromstring(data)
                checks["XML parser"] += 1
            except Exception as exc:
                failures.append({"file": rel, "check": "xml", "message": str(exc)})
        elif suffix == ".csv":
            try:
                rows = list(csv.reader(data.decode("utf-8-sig").splitlines()))
                widths = {len(row) for row in rows if row}
                checks["CSV parser"] += 1
                if len(widths) > 1:
                    warnings.append({"file": rel, "check": "csv_columns", "message": f"quantidades de colunas: {sorted(widths)}"})
            except Exception as exc:
                failures.append({"file": rel, "check": "csv", "message": str(exc)})
        elif suffix in IMAGE_EXTENSIONS:
            if Image is None:
                failures.append({"file": rel, "check": "image", "message": "Pillow não instalado"})
            else:
                try:
                    with Image.open(path) as image:
                        image.verify()
                    checks["Imagem"] += 1
                except Exception as exc:
                    failures.append({"file": rel, "check": "image", "message": str(exc)})
        elif suffix == ".zip":
            try:
                with zipfile.ZipFile(path) as archive:
                    bad = archive.testzip()
                    if bad:
                        raise ValueError(f"entrada corrompida: {bad}")
                checks["ZIP"] += 1
            except Exception as exc:
                failures.append({"file": rel, "check": "zip", "message": str(exc)})
        elif suffix == ".pdf":
            checks["PDF header"] += 1
            if not data.startswith(b"%PDF-"):
                failures.append({"file": rel, "check": "pdf", "message": "cabeçalho PDF inválido"})

    manifest = audit_manifest(paths, failures)
    return {
        "schema": 1,
        "repository": os.getenv("GITHUB_REPOSITORY", "drwesleyadv/prontoo.app"),
        "tracked_files": len(paths),
        "tracked_bytes": sum((ROOT / rel).stat().st_size for rel in paths if (ROOT / rel).is_file()),
        "extensions": dict(sorted(counts.items())),
        "bytes_by_extension": dict(sorted(bytes_by_type.items())),
        "checks": dict(sorted(checks.items())),
        "manifest": manifest,
        "tool_files": sorted(tools),
        "failures": failures,
        "warnings": warnings,
        "status": "failed" if failures else "passed",
    }


def markdown_report(report: dict[str, Any]) -> str:
    lines = [
        "# Auditoria integral do repositório",
        "",
        f"- Status: **{report['status']}**",
        f"- Arquivos rastreados auditados: **{report['tracked_files']}**",
        f"- Bytes rastreados: **{report['tracked_bytes']}**",
        f"- Falhas: **{len(report['failures'])}**",
        f"- Avisos: **{len(report['warnings'])}**",
        "",
        "## Validações executadas",
        "",
    ]
    for name, count in report["checks"].items():
        lines.append(f"- {name}: {count}")
    lines.extend(["", "## Falhas", ""])
    if report["failures"]:
        for item in report["failures"]:
            lines.append(f"- `{item['file']}` — **{item['check']}**: {item['message']}")
    else:
        lines.append("Nenhuma falha detectada.")
    lines.extend(["", "## Avisos", ""])
    if report["warnings"]:
        for item in report["warnings"][:200]:
            lines.append(f"- `{item['file']}` — **{item['check']}**: {item['message']}")
        if len(report["warnings"]) > 200:
            lines.append(f"- ... {len(report['warnings']) - 200} aviso(s) adicional(is) no JSON.")
    else:
        lines.append("Nenhum aviso.")
    lines.extend(["", "## Ferramentas existentes", ""])
    for tool in report["tool_files"]:
        lines.append(f"- `{tool}`")
    return "\n".join(lines) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--json", default="audit-output/full-repository-audit.json")
    parser.add_argument("--markdown", default="audit-output/full-repository-audit.md")
    args = parser.parse_args()
    report = audit()
    json_path = ROOT / args.json
    md_path = ROOT / args.markdown
    json_path.parent.mkdir(parents=True, exist_ok=True)
    md_path.parent.mkdir(parents=True, exist_ok=True)
    json_path.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    md_path.write_text(markdown_report(report), encoding="utf-8")
    print(markdown_report(report))
    return 1 if report["failures"] else 0


if __name__ == "__main__":
    sys.exit(main())
