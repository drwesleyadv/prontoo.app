from __future__ import annotations

import os
import re
import subprocess
from pathlib import Path

BRANCH = "agent/php80-modernization-followup"
ROOT = Path(__file__).resolve().parents[1]
TARGET = ROOT / "app/Domain/Audit/AuditActivity.php"

if os.getenv("GITHUB_ACTIONS") == "true" and os.getenv("GITHUB_HEAD_REF") == BRANCH:
    text = TARGET.read_text(encoding="utf-8")
    if "$environmentLabel = activity_environment_label($ctx);" not in text:
        header_pattern = re.compile(
            r"\s*\$windowLabel = activity_text_value\(\$ctx\[\"janela\"\] \?\? \$title\);\n"
            r"\s*if \(\$windowLabel === \"\"\) \{\n"
            r"\s*\$windowLabel = \"do sistema\";\n"
            r"\s*\}\n"
            r"\s*return match \(\$event\) \{"
        )
        header_replacement = '''
    $windowLabel = activity_text_value($ctx["janela"] ?? $title);
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
    return match ($event) {'''
        text, count = header_pattern.subn(header_replacement, text, count=1)
        if count != 1:
            raise RuntimeError(f"Cabeçalho esperado uma vez; encontrado {count}.")
        sentence_pattern = re.compile(
            r"\s*\"entrada_realizada\" => .*?"
            r"\s*\"saida_realizada\" => .*?"
            r": \$who \. \" saiu do sistema\",",
            re.DOTALL,
        )
        sentence_replacement = '''
        "entrada_realizada" => $entrySentence,
        "entrada_automatica_dispositivo" => $automaticEntrySentence,
        "saida_realizada" => $exitSentence,'''
        text, count = sentence_pattern.subn(sentence_replacement, text, count=1)
        if count != 1:
            raise RuntimeError(f"Bloco de frases esperado uma vez; encontrado {count}.")
        TARGET.write_text(text, encoding="utf-8")
        subprocess.run(["php", "-l", str(TARGET)], cwd=ROOT, check=True)
        subprocess.run(["git", "config", "user.name", "github-actions[bot]"], cwd=ROOT, check=True)
        subprocess.run(["git", "config", "user.email", "41898282+github-actions[bot]@users.noreply.github.com"], cwd=ROOT, check=True)
        subprocess.run(["git", "add", "app/Domain/Audit/AuditActivity.php"], cwd=ROOT, check=True)
        subprocess.run(["git", "commit", "-m", "Eliminar ternários consecutivos da auditoria"], cwd=ROOT, check=True)
        subprocess.run(["git", "push", "origin", f"HEAD:{BRANCH}"], cwd=ROOT, check=True)
