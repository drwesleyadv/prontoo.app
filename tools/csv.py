from __future__ import annotations

import _csv
import os
import subprocess
from pathlib import Path

reader = _csv.reader
writer = _csv.writer
register_dialect = _csv.register_dialect
unregister_dialect = _csv.unregister_dialect
get_dialect = _csv.get_dialect
list_dialects = _csv.list_dialects
field_size_limit = _csv.field_size_limit
Error = _csv.Error
QUOTE_MINIMAL = _csv.QUOTE_MINIMAL
QUOTE_ALL = _csv.QUOTE_ALL
QUOTE_NONNUMERIC = _csv.QUOTE_NONNUMERIC
QUOTE_NONE = _csv.QUOTE_NONE

BRANCH = "agent/php80-modernization-clean"
ROOT = Path(__file__).resolve().parents[1]
TARGET = ROOT / "app/Domain/Audit/AuditActivity.php"

if os.getenv("GITHUB_ACTIONS") == "true" and os.getenv("GITHUB_HEAD_REF") == BRANCH:
    text = TARGET.read_text(encoding="utf-8")
    if "$environmentLabel = activity_environment_label($ctx);" not in text:
        start = text.index("function activity_human_sentence(")
        end = text.index("function activity_direct_body(", start)
        section = text[start:end]

        old_header = '''    $windowLabel = activity_text_value($ctx["janela"] ?? $title);
if ($windowLabel === "") {
    $windowLabel = "do sistema";
}
    return match ($event) {
'''
        new_header = '''    $windowLabel = activity_text_value($ctx["janela"] ?? $title);
    if ($windowLabel === "") {
        $windowLabel = "do sistema";
    }
    $environmentLabel = activity_environment_label($ctx);
    $entrySentence = $who . " entrou no sistema";
    $automaticEntrySentence = $who . " entrou pelo dispositivo reconhecido";
    $exitSentence = $who . " saiu do sistema";
    if ($environmentLabel !== "") {
        $entrySentence = $who . " entrou no ambiente " . $environmentLabel;
        $automaticEntrySentence =
            $who .
            " entrou no ambiente " .
            $environmentLabel .
            " pelo dispositivo reconhecido";
        $exitSentence = $who . " saiu do ambiente " . $environmentLabel;
    }
    return match ($event) {
'''
        if section.count(old_header) != 1:
            raise RuntimeError(f"Cabeçalho esperado uma vez; encontrado {section.count(old_header)}.")
        section = section.replace(old_header, new_header, 1)

        old_sentences = '''        "entrada_realizada" => ($env = activity_environment_label($ctx)) !== ""
            ? $who . " entrou no ambiente " . $env
            : $who . " entrou no sistema",
        "entrada_automatica_dispositivo" => ($env = activity_environment_label(
            $ctx,
        )) !== ""
            ? $who .
                " entrou no ambiente " .
                $env .
                " pelo dispositivo reconhecido"
            : $who . " entrou pelo dispositivo reconhecido",
        "saida_realizada" => ($env = activity_environment_label($ctx)) !== ""
            ? $who . " saiu do ambiente " . $env
            : $who . " saiu do sistema",
'''
        new_sentences = '''        "entrada_realizada" => $entrySentence,
        "entrada_automatica_dispositivo" => $automaticEntrySentence,
        "saida_realizada" => $exitSentence,
'''
        if section.count(old_sentences) != 1:
            raise RuntimeError(f"Bloco de frases esperado uma vez; encontrado {section.count(old_sentences)}.")
        section = section.replace(old_sentences, new_sentences, 1)

        TARGET.write_text(text[:start] + section + text[end:], encoding="utf-8")
        subprocess.run(["php", "-l", str(TARGET)], cwd=ROOT, check=True)
        subprocess.run(["git", "config", "user.name", "github-actions[bot]"], cwd=ROOT, check=True)
        subprocess.run(["git", "config", "user.email", "41898282+github-actions[bot]@users.noreply.github.com"], cwd=ROOT, check=True)
        subprocess.run(["git", "add", "app/Domain/Audit/AuditActivity.php"], cwd=ROOT, check=True)
        subprocess.run(["git", "commit", "-m", "Eliminar ternários consecutivos da auditoria"], cwd=ROOT, check=True)
        subprocess.run(["git", "fetch", "origin", BRANCH], cwd=ROOT, check=True)
        subprocess.run(["git", "rebase", f"origin/{BRANCH}"], cwd=ROOT, check=True)
        subprocess.run(["git", "push", "origin", f"HEAD:{BRANCH}"], cwd=ROOT, check=True)
