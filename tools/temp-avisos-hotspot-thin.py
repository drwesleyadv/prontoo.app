from __future__ import annotations

from collections import Counter, defaultdict
from pathlib import Path
import json
import re

root = Path(__file__).resolve().parents[1]
css_path = root / "design/styles/application.css"
css = css_path.read_text()

source_paths = []
for base in (root / "app", root / "public", root / "br", root / "tests"):
    if not base.exists():
        continue
    for path in base.rglob("*"):
        if path.is_file() and path.suffix.lower() in {".php", ".js", ".mjs", ".html"}:
            if path.as_posix().endswith("public/assets/presentation.css"):
                continue
            source_paths.append(path)

sources = {path.relative_to(root).as_posix(): path.read_text(errors="ignore") for path in source_paths}
all_source = "\n".join(sources.values())
all_source_tokens = set(re.findall(r'[A-Za-z_][A-Za-z0-9_-]{2,}', all_source))

class_attr_re = re.compile(r'class\s*=\s*["\\\']([^"\\\']*)["\\\']')
escaped_class_attr_re = re.compile(r'class\\?=\\?["\\\']([^"\\\']*)["\\\']')
class_token_re = re.compile(r'^[A-Za-z_][A-Za-z0-9_-]*$')
class_usage = Counter()
class_files = defaultdict(set)
class_sets = []

for rel, text in sources.items():
    matches = list(class_attr_re.finditer(text)) + list(escaped_class_attr_re.finditer(text))
    seen_spans = set()
    for match in matches:
        if match.span() in seen_spans:
            continue
        seen_spans.add(match.span())
        tokens = [token for token in re.split(r'\s+', match.group(1).strip()) if class_token_re.match(token)]
        if not tokens:
            continue
        class_sets.append((rel, tuple(tokens)))
        for token in tokens:
            class_usage[token] += 1
            class_files[token].add(rel)

rule_re = re.compile(r'([^{}]+)\{([^{}]*)\}')
class_selector_re = re.compile(r'\.([A-Za-z_][A-Za-z0-9_-]*)')
rules = []
selector_header_counts = Counter()
class_rule_counts = Counter()
important_by_class = Counter()
for match in rule_re.finditer(css):
    selector = re.sub(r'\s+', ' ', match.group(1).strip())
    body = match.group(2)
    if not selector or selector.startswith('@'):
        continue
    selector_header_counts[selector] += 1
    classes = tuple(class_selector_re.findall(selector))
    rules.append((selector, body, classes))
    for cls in set(classes):
        class_rule_counts[cls] += 1
        important_by_class[cls] += body.count('!important')

css_classes = set(class_rule_counts)
source_classes = set(class_usage)

legacy_marker_re = re.compile(r'(?:^|[-_])(gmail|minimal|refined|legacy|deprecated|obsolete|old|classic|previous|v[0-9]+)(?:$|[-_])', re.I)
legacy_active = []
for cls in sorted(source_classes & css_classes):
    if legacy_marker_re.search(cls):
        legacy_active.append({
            "class": cls,
            "uses": class_usage[cls],
            "css_rules": class_rule_counts[cls],
            "files": sorted(class_files[cls])[:20],
        })

canonical_prefixes = ("ds-", "pagehead-", "form-", "field", "card", "pill", "flash", "stat-")
alias_pairs = Counter()
for rel, tokens in class_sets:
    legacy = [token for token in tokens if legacy_marker_re.search(token)]
    canonical = [token for token in tokens if token.startswith(canonical_prefixes)]
    for left in legacy:
        for right in canonical:
            alias_pairs[(left, right, rel)] += 1

raw_unreferenced = []
for cls in sorted(css_classes - source_classes):
    if len(cls) < 4:
        continue
    if cls in {"active", "hidden", "open", "selected", "disabled", "loading", "error", "success"}:
        continue
    if cls in all_source_tokens:
        continue
    raw_unreferenced.append({
        "class": cls,
        "css_rules": class_rule_counts[cls],
        "important": important_by_class[cls],
    })

inline_styles = []
for rel, text in sources.items():
    count = len(re.findall(r'\bstyle\\?=\\?["\\\']', text))
    if count:
        inline_styles.append({"file": rel, "count": count})

page_renderers = []
for rel, text in sources.items():
    if not rel.startswith("app/") or not rel.endswith(".php"):
        continue
    page_calls = text.count("UiComponentsRuntimeOperations02::page(")
    if not page_calls:
        continue
    page_renderers.append({
        "file": rel,
        "page_calls": page_calls,
        "page_head_calls": text.count("UiComponentsRuntimeOperations03::page_head("),
        "raw_pagehead": text.count('class="pagehead'),
        "form_panel": text.count('form-panel'),
        "form_section": text.count('form-section'),
        "ds_empty": text.count('ds-empty'),
        "ds_filter_chip": text.count('ds-filter-chip'),
    })

route_registry = (root / "app/Runtime/Routing/RouteRegistry.php").read_text()
route_re = re.compile(r"'([^']+)'\s*=>\s*\[\\Prontoo\\Runtime\\([^:]+)::class,\s*'([^']+)'")
routes = []
for route, class_path, method in route_re.findall(route_registry):
    file_path = "app/Runtime/" + class_path.replace('\\', '/') + ".php"
    text = sources.get(file_path, "")
    routes.append({
        "route": route,
        "handler": file_path,
        "method": method,
        "json_like": any(token in route for token in ("lookup", "suggest", "wave")),
        "file_has_page_head": "UiComponentsRuntimeOperations03::page_head(" in text,
        "file_has_page": "UiComponentsRuntimeOperations02::page(" in text,
    })

selector_alias_groups = []
body_groups = defaultdict(list)
for selector, body, classes in rules:
    normalized_body = re.sub(r'\s+', ' ', body.strip())
    if not normalized_body or len(normalized_body) < 12:
        continue
    body_groups[normalized_body].append((selector, classes))
for body, group in body_groups.items():
    if len(group) < 2:
        continue
    flat_classes = {cls for _, classes in group for cls in classes}
    has_canonical = any(cls.startswith(("ds-", "pagehead-", "form-")) for cls in flat_classes)
    has_legacy = any(legacy_marker_re.search(cls) for cls in flat_classes)
    if has_canonical and has_legacy:
        selector_alias_groups.append({
            "selectors": [selector for selector, _ in group][:12],
            "classes": sorted(flat_classes)[:24],
        })

report = {
    "policy": "global-design-system-audit-v1",
    "source_files": len(sources),
    "css": {
        "bytes": len(css.encode()),
        "lines": css.count("\n") + 1,
        "important": css.count("!important"),
        "duplicate_selector_headers": sum(count - 1 for count in selector_header_counts.values() if count > 1),
        "unique_classes": len(css_classes),
    },
    "markup": {
        "unique_static_classes": len(source_classes),
        "legacy_active_count": len(legacy_active),
        "legacy_active": legacy_active,
        "legacy_canonical_cooccurrences": [
            {"legacy": left, "canonical": right, "file": rel, "count": count}
            for (left, right, rel), count in alias_pairs.most_common(100)
        ],
        "inline_style_files": sorted(inline_styles, key=lambda item: (-item["count"], item["file"])),
    },
    "css_candidates": {
        "unreferenced_class_count": len(raw_unreferenced),
        "unreferenced_top": sorted(raw_unreferenced, key=lambda item: (-item["css_rules"], -item["important"], item["class"]))[:250],
        "canonical_legacy_identical_rule_groups": selector_alias_groups[:100],
    },
    "page_renderers": sorted(page_renderers, key=lambda item: item["file"]),
    "routes": routes,
}

print(json.dumps(report, ensure_ascii=False, indent=2))
