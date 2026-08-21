from __future__ import annotations

from collections import Counter
from pathlib import Path
import re
import subprocess

root = Path(__file__).resolve().parents[1]
css_path = root / "design/styles/application.css"
base_css = subprocess.check_output(
    ["git", "show", "origin/prontoo:design/styles/application.css"],
    cwd=root,
    text=True,
)

RENAME_CLASSES = {
    "ds-collaborator-row-v31": "ds-collaborator-row",
    "notice-minimal-icon": "ds-section-icon",
}
DROP_CLASS_PATTERNS = (
    re.compile(r"^procedure-kpis-refined$"),
    re.compile(r"^notice-gmail-[A-Za-z0-9_-]+$"),
    re.compile(r"^notice-minimal-[A-Za-z0-9_-]+$"),
    re.compile(r"^notice-form-refined$"),
    re.compile(r"^notice-filter-chips$"),
    re.compile(r"^agenda-route-(?:dialog|overlay|backdrop)-retired$"),
)
CLASS_TOKEN = re.compile(r"\.([A-Za-z_][A-Za-z0-9_-]*)")


def is_drop_class(name: str) -> bool:
    return any(pattern.fullmatch(name) for pattern in DROP_CLASS_PATTERNS)


def split_top_level_commas(text: str) -> list[str]:
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


def rewrite_function_lists(selector: str) -> str | None:
    output: list[str] = []
    cursor = 0
    while cursor < len(selector):
        match = re.search(r":(?:where|is|not|has)\(", selector[cursor:])
        if not match:
            output.append(selector[cursor:])
            break
        start = cursor + match.start()
        open_paren = cursor + match.end() - 1
        close_paren = matching_paren(selector, open_paren)
        if close_paren < 0:
            return None
        output.append(selector[cursor:open_paren + 1])
        inner = selector[open_paren + 1:close_paren]
        cleaned_inner = clean_selector_list(inner)
        if cleaned_inner == "":
            return None
        output.append(cleaned_inner)
        output.append(")")
        cursor = close_paren + 1
    return "".join(output)


def clean_selector(selector: str) -> str | None:
    candidate = selector.strip()
    if candidate == "":
        return None
    candidate = rewrite_function_lists(candidate)
    if candidate is None:
        return None
    for old, new in RENAME_CLASSES.items():
        candidate = re.sub(rf"\.{re.escape(old)}(?![A-Za-z0-9_-])", "." + new, candidate)
    for class_name in CLASS_TOKEN.findall(candidate):
        if is_drop_class(class_name):
            return None
    return candidate


def clean_selector_list(text: str) -> str:
    kept: list[str] = []
    seen: set[str] = set()
    for part in split_top_level_commas(text):
        cleaned = clean_selector(part)
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
    in_comment = False
    i = start
    while i < len(text):
        pair = text[i:i + 2]
        if in_comment:
            if pair == "*/":
                in_comment = False
                i += 2
                continue
            i += 1
            continue
        if not quote and pair == "/*":
            in_comment = True
            i += 2
            continue
        ch = text[i]
        if escape:
            escape = False
            i += 1
            continue
        if ch == "\\":
            escape = True
            i += 1
            continue
        if quote:
            if ch == quote:
                quote = None
            i += 1
            continue
        if ch in ('"', "'"):
            quote = ch
            i += 1
            continue
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return -1


def header_has_target(header: str) -> bool:
    for old in RENAME_CLASSES:
        if f".{old}" in header:
            return True
    for class_name in CLASS_TOKEN.findall(header):
        if is_drop_class(class_name):
            return True
    return False


def process_block(text: str) -> str:
    out: list[str] = []
    cursor = 0
    while True:
        open_brace = text.find("{", cursor)
        if open_brace < 0:
            out.append(text[cursor:])
            break
        close_brace = find_matching_brace(text, open_brace)
        if close_brace < 0:
            raise SystemExit("unbalanced CSS brace")
        header = text[cursor:open_brace]
        body = text[open_brace + 1:close_brace]
        leading = re.match(r"\s*", header).group(0)
        core = header[len(leading):]
        if core.lstrip().startswith("@"):
            out.append(header + "{" + process_block(body) + "}")
        elif header_has_target(core):
            cleaned = clean_selector_list(core)
            if cleaned:
                out.append(leading + cleaned + "{" + body + "}")
            else:
                out.append(leading)
        else:
            out.append(header + "{" + body + "}")
        cursor = close_brace + 1
    return "".join(out)


css = process_block(base_css)

notice_css = r'''

/* Avisos — Design System canônico */
.notice-screen{display:grid;gap:var(--pt-ds-gap-md);min-width:0;background:transparent;border:0;box-shadow:none}
.notice-list-card,.notice-system-card,.notice-reader-card{display:grid;gap:0;min-width:0;background:var(--pt-ds-surface);border:var(--pt-ds-border);border-radius:var(--pt-ds-radius-lg);box-shadow:var(--md-sys-elevation-level1)}
.notice-list{display:grid;gap:8px}.notice-list-card .section-head,.notice-system-card .section-head{align-items:flex-start;margin:0 0 12px;padding:0 0 12px}.notice-section-count{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:30px;padding:0 10px;border-radius:999px;background:var(--md-sys-color-surface-container-high);color:var(--md-sys-color-on-surface);font:var(--md-sys-typescale-label-medium);font-weight:900}.ds-notice-filters .ds-filter-chip em{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;padding:0 7px;border-radius:999px;background:var(--md-sys-color-surface-container-high);color:var(--md-sys-color-on-surface-variant);font:var(--md-sys-typescale-label-small);font-style:normal;font-weight:900}.ds-notice-filters .ds-filter-chip.is-active em{background:color-mix(in srgb,var(--md-sys-color-primary) 16%,var(--md-sys-color-primary-container));color:var(--md-sys-color-on-primary-container)}
.notice-row:hover{color:var(--md-sys-color-on-surface);text-decoration:none}.notice-row-icon,.ds-section-icon{width:40px;height:40px;min-width:40px;display:inline-flex;align-items:center;justify-content:center;border-radius:15px;background:color-mix(in srgb,var(--md-sys-color-primary) 10%,var(--md-sys-color-surface-container-lowest));color:var(--md-sys-color-primary);border:1px solid color-mix(in srgb,var(--md-sys-color-primary) 12%,transparent)}.notice-row-main{display:grid;gap:3px;min-width:0}.notice-row-title{min-width:0;color:var(--md-sys-color-on-surface);font:var(--md-sys-typescale-title-small);font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.notice-row-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;min-width:0;color:var(--md-sys-color-on-surface-variant);font:var(--md-sys-typescale-body-small);line-height:1.3}.notice-row-time{justify-self:end;color:var(--md-sys-color-on-surface-variant);font:var(--md-sys-typescale-body-small);white-space:nowrap}.notice-row.is-unread{border-left:4px solid var(--md-sys-color-primary);background:color-mix(in srgb,var(--md-sys-color-primary) 4%,var(--md-sys-color-surface-container-lowest))}.notice-row.is-unread .notice-row-title{font-weight:900}.notice-row.is-archived{opacity:.74}
.notice-system-row{background:color-mix(in srgb,var(--md-sys-color-tertiary) 3%,var(--md-sys-color-surface-container-lowest))}.notice-system-row .notice-row-icon{background:var(--md-sys-color-tertiary-container);color:var(--md-sys-color-on-tertiary-container);border-color:color-mix(in srgb,var(--md-sys-color-tertiary) 14%,transparent)}.notice-status-pill{display:inline-flex;align-items:center;min-height:24px;padding:4px 8px;border-radius:999px;background:var(--md-sys-color-tertiary-container);color:var(--md-sys-color-on-tertiary-container);font:var(--md-sys-typescale-label-small);font-weight:900}.notice-status-pill.severity-warning{background:var(--pt-color-warning-container);color:var(--pt-color-on-warning-container)}.notice-status-pill.severity-critical{background:var(--md-sys-color-error-container);color:var(--md-sys-color-on-error-container)}.notice-system-row.severity-warning{border-left:4px solid var(--pt-color-warning)}.notice-system-row.severity-critical{border-left:4px solid var(--md-sys-color-error)}
.notice-reader-card{padding:var(--pt-ds-section-pad)}.notice-reader{display:grid;gap:0}.notice-reader-head.section-head{align-items:flex-start;margin:0;padding:0 0 14px}.notice-reader-body{padding:18px 0;border:0;border-radius:0;background:transparent;color:var(--md-sys-color-on-surface);font:var(--md-sys-typescale-body-large);line-height:1.62;white-space:normal}
.notice-empty{display:grid;justify-items:center;align-content:center;gap:8px;min-height:152px;text-align:center}.notice-empty-icon{width:46px;height:46px;display:inline-flex;align-items:center;justify-content:center;border-radius:17px;background:var(--md-sys-color-primary-container);color:var(--md-sys-color-primary)}.notice-empty strong{color:var(--md-sys-color-on-surface);font:var(--md-sys-typescale-title-small);font-weight:900}.notice-empty>span:not(.notice-empty-icon){max-width:58ch;color:var(--md-sys-color-on-surface-variant);font:var(--md-sys-typescale-body-medium)}.notice-empty-actions{display:flex;justify-content:center;gap:8px;margin-top:4px}
.readonly-support-screen{display:grid;gap:var(--pt-ds-gap-lg)}.notice-support-compose{display:grid;gap:0}.notice-support-compose .section-head{align-items:flex-start}.notice-support-compose .notice-form{gap:12px}.notice-support-row .notice-row-icon{background:var(--md-sys-color-secondary-container);color:var(--md-sys-color-on-secondary-container);border-color:color-mix(in srgb,var(--md-sys-color-secondary) 14%,transparent)}
@media(max-width:760px){.notice-row{grid-template-columns:auto minmax(0,1fr);align-items:start}.notice-row-time{grid-column:2;justify-self:start}.notice-row-title{white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.notice-reader-footer{align-items:stretch}.notice-reader-meta{width:100%}.notice-actions{width:100%;margin-left:0;justify-content:stretch}.notice-actions form,.notice-actions button{width:100%}.ds-notice-filters{overflow-x:auto;flex-wrap:nowrap;scrollbar-width:none}.ds-notice-filters::-webkit-scrollbar{display:none}.ds-notice-filters .ds-filter-chip{flex:0 0 auto}}
'''

css = css.rstrip() + notice_css

legacy_tokens = (
    "ds-collaborator-row-v31",
    "procedure-kpis-refined",
    "notice-gmail-",
    "notice-minimal-",
    "notice-form-refined",
    "notice-filter-chips",
    "agenda-route-dialog-retired",
    "agenda-route-overlay-retired",
    "agenda-route-backdrop-retired",
)
for token in legacy_tokens:
    if token in css:
        raise SystemExit(f"legacy selector remains after surgical CSS restoration: {token}")

rule_re = re.compile(r"([^{}]+)\{([^{}]*)\}")

def duplicate_headers(source: str) -> int:
    counts: Counter[str] = Counter()
    for match in rule_re.finditer(source):
        selector = re.sub(r"\s+", " ", match.group(1).strip())
        if selector and not selector.startswith("@"):
            counts[selector] += 1
    return sum(count - 1 for count in counts.values() if count > 1)

base_duplicates = duplicate_headers(base_css)
current_duplicates = duplicate_headers(css)
if current_duplicates > base_duplicates:
    raise SystemExit(
        f"surgical CSS restoration increased duplicate selector headers: base={base_duplicates} current={current_duplicates}"
    )

css_path.write_text(css)
print(
    "design-system-finalize: base-restored=1 targeted-legacy=removed "
    f"duplicates={current_duplicates}/{base_duplicates}"
)
