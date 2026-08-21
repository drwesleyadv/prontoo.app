from __future__ import annotations

from pathlib import Path
import re
import subprocess

root = Path(__file__).resolve().parents[1]
css_path = root / "design/styles/application.css"
source_ref = "39390a961b0d49417506c92623112e4f3318c268"
css = subprocess.check_output(
    ["git", "show", f"{source_ref}:design/styles/application.css"],
    cwd=root,
    text=True,
)

css = css.replace(".ds-collaborator-row-v31", ".ds-collaborator-row")
css = css.replace(".notice-minimal-icon", ".ds-section-icon")

legacy_patterns = (
    re.compile(r"\.procedure-kpis-refined(?![A-Za-z0-9_-])"),
    re.compile(r"\.notice-gmail-[A-Za-z0-9_-]+"),
    re.compile(r"\.notice-minimal-[A-Za-z0-9_-]+"),
    re.compile(r"\.agenda-route-(?:dialog|overlay|backdrop)-retired(?![A-Za-z0-9_-])"),
)


def has_legacy(text: str) -> bool:
    return any(pattern.search(text) for pattern in legacy_patterns)


def split_commas(text: str) -> list[str]:
    parts: list[str] = []
    start = 0
    paren = 0
    bracket = 0
    quote: str | None = None
    escape = False
    for i, ch in enumerate(text):
        if escape:
            escape = False
            continue
        if ch == "\\":
            escape = True
            continue
        if quote:
            if ch == quote:
                quote = None
            continue
        if ch in ('"', "'"):
            quote = ch
            continue
        if ch == "(":
            paren += 1
        elif ch == ")" and paren:
            paren -= 1
        elif ch == "[":
            bracket += 1
        elif ch == "]" and bracket:
            bracket -= 1
        elif ch == "," and paren == 0 and bracket == 0:
            parts.append(text[start:i])
            start = i + 1
    parts.append(text[start:])
    return parts


def matching_paren(text: str, start: int) -> int:
    depth = 0
    quote: str | None = None
    escape = False
    for i in range(start, len(text)):
        ch = text[i]
        if escape:
            escape = False
            continue
        if ch == "\\":
            escape = True
            continue
        if quote:
            if ch == quote:
                quote = None
            continue
        if ch in ('"', "'"):
            quote = ch
            continue
        if ch == "(":
            depth += 1
        elif ch == ")":
            depth -= 1
            if depth == 0:
                return i
    return -1


def clean_segment(segment: str) -> str | None:
    out: list[str] = []
    i = 0
    while i < len(segment):
        ch = segment[i]
        if ch == "(":
            end = matching_paren(segment, i)
            if end < 0:
                return None if has_legacy(segment) else segment.strip()
            inner = segment[i + 1:end]
            cleaned_inner = clean_selector_list(inner)
            if cleaned_inner == "":
                return None
            out.append("(" + cleaned_inner + ")")
            i = end + 1
            continue
        out.append(ch)
        i += 1
    candidate = "".join(out).strip()
    if not candidate or has_legacy(candidate):
        return None
    return candidate


def clean_selector_list(selector_text: str) -> str:
    kept: list[str] = []
    seen: set[str] = set()
    for raw in split_commas(selector_text):
        cleaned = clean_segment(raw)
        if cleaned is None:
            continue
        normalized = re.sub(r"\s+", " ", cleaned).strip()
        if normalized in seen:
            continue
        seen.add(normalized)
        kept.append(cleaned)
    return ",".join(kept)


def find_matching_brace(text: str, start: int) -> int:
    depth = 0
    quote: str | None = None
    escape = False
    for i in range(start, len(text)):
        ch = text[i]
        if escape:
            escape = False
            continue
        if ch == "\\":
            escape = True
            continue
        if quote:
            if ch == quote:
                quote = None
            continue
        if ch in ('"', "'"):
            quote = ch
            continue
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return i
    return -1


def process_block(text: str) -> str:
    output: list[str] = []
    cursor = 0
    while True:
        open_brace = text.find("{", cursor)
        if open_brace < 0:
            output.append(text[cursor:])
            break
        close_brace = find_matching_brace(text, open_brace)
        if close_brace < 0:
            raise SystemExit("unbalanced CSS brace")
        header = text[cursor:open_brace]
        body = text[open_brace + 1:close_brace]
        leading_match = re.match(r"\s*", header)
        leading = leading_match.group(0) if leading_match else ""
        core = header[len(leading):].strip()
        if core.startswith("@"):
            processed_body = process_block(body) if "{" in body else body
            output.append(leading + core + "{" + processed_body + "}")
        else:
            cleaned = clean_selector_list(core)
            if cleaned:
                output.append(leading + cleaned + "{" + body + "}")
            else:
                output.append(leading)
        cursor = close_brace + 1
    return "".join(output)


css = process_block(css)

for token in (
    "ds-collaborator-row-v31",
    "procedure-kpis-refined",
    "notice-gmail-",
    "notice-minimal-",
    "agenda-route-dialog-retired",
    "agenda-route-overlay-retired",
    "agenda-route-backdrop-retired",
):
    if token in css:
        raise SystemExit(f"legacy selector remains after CSS repair: {token}")

if ".ds-section-icon" not in css:
    raise SystemExit("canonical section icon selector was not migrated")

css_path.write_text(css)
print("design-system-css-repair: selective legacy removal complete")
