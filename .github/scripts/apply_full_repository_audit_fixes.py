#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]

READONLY_FILES = [
    "app/Application/Authorization/AuthorizationService.php",
    "app/Core/Invariant/Decision.php",
    "app/Core/Invariant/Workflow/StateMachine.php",
    "app/Domain/Authorization/ActionContract.php",
    "app/Presentation/Http/ActionMiddleware.php",
]

CHAIN_FILES = [
    "app/Admin/AdminPages.php",
    "app/Core/Temporal/PiTime.php",
    "app/Domain/Appointments/Appointments.php",
    "app/Domain/Financial/Financial.php",
    "app/Domain/Patients/PatientPure.php",
    "app/Domain/Patients/Patients.php",
    "app/Support/Telemetry.php",
]

NEW_RE = re.compile(r"\bnew\s+\\?[A-Za-z_][A-Za-z0-9_\\]*\s*\(")


def scan_close(text: str, open_index: int) -> int | None:
    depth = 0
    state = "code"
    i = open_index
    while i < len(text):
        ch = text[i]
        nxt = text[i + 1] if i + 1 < len(text) else ""
        if state == "single":
            if ch == "\\":
                i += 2
                continue
            if ch == "'":
                state = "code"
            i += 1
            continue
        if state == "double":
            if ch == "\\":
                i += 2
                continue
            if ch == '"':
                state = "code"
            i += 1
            continue
        if state == "line":
            if ch == "\n":
                state = "code"
            i += 1
            continue
        if state == "block":
            if ch == "*" and nxt == "/":
                state = "code"
                i += 2
                continue
            i += 1
            continue
        if ch == "'":
            state = "single"
        elif ch == '"':
            state = "double"
        elif ch == "/" and nxt == "/":
            state = "line"
            i += 2
            continue
        elif ch == "#":
            state = "line"
        elif ch == "/" and nxt == "*":
            state = "block"
            i += 2
            continue
        elif ch == "(":
            depth += 1
        elif ch == ")":
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return None


def wrap_chained_new(text: str) -> tuple[str, int]:
    out = []
    cursor = 0
    search_from = 0
    changes = 0
    while True:
        match = NEW_RE.search(text, search_from)
        if not match:
            break
        open_index = text.find("(", match.start(), match.end())
        close_index = scan_close(text, open_index)
        if close_index is None:
            search_from = match.end()
            continue
        j = close_index + 1
        while j < len(text) and text[j].isspace():
            j += 1
        if text[j:j + 2] != "->":
            search_from = close_index + 1
            continue
        out.append(text[cursor:match.start()])
        out.append("(")
        out.append(text[match.start():close_index + 1])
        out.append(")")
        cursor = close_index + 1
        search_from = close_index + 1
        changes += 1
    if changes == 0:
        return text, 0
    out.append(text[cursor:])
    return "".join(out), changes


changed = []
for rel in READONLY_FILES:
    path = ROOT / rel
    text = path.read_text(encoding="utf-8")
    updated = text.replace("final readonly class ", "final class ")
    if updated == text:
        raise SystemExit(f"readonly class expected but not found: {rel}")
    path.write_text(updated, encoding="utf-8")
    changed.append(f"{rel}: readonly class removed")

for rel in CHAIN_FILES:
    path = ROOT / rel
    text = path.read_text(encoding="utf-8")
    updated, count = wrap_chained_new(text)
    if count == 0:
        raise SystemExit(f"no chained new expression found: {rel}")
    path.write_text(updated, encoding="utf-8")
    changed.append(f"{rel}: {count} chained new expression(s) wrapped")

for item in changed:
    print(item)
