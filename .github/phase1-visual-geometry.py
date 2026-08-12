from pathlib import Path
import re

path = Path('public/assets/design-system.css')
css = path.read_text()

token_needle = '  --motion-med:240ms cubic-bezier(.2,0,0,1);\n}'
if css.count(token_needle) != 1:
    raise SystemExit('geometry token insertion point not unique')
css = css.replace(token_needle, '''  --motion-med:240ms cubic-bezier(.2,0,0,1);
  --pt-pagehead-action-height:34px;
  --pt-pagehead-action-radius:var(--md-sys-shape-corner-large);
  --pt-pagehead-action-padding-block:7px;
  --pt-pagehead-action-padding-inline:12px;
  --pt-pagehead-action-gap:8px;
  --pt-pagehead-action-icon-size:20px;
  --pt-pagehead-action-font:var(--md-sys-typescale-label-medium);
  --pt-pagehead-action-weight:700;
}
''')

for pattern in [
    r'\.pagehead-actions :where\(a,button,summary\)\{[^{}]*\}',
    r'\.pagehead-actions :where\(a\.primary,button\.primary\)\{[^{}]*\}',
    r'\.pagehead-actions :where\(a:hover,button:hover,summary:hover\)\{[^{}]*\}',
    r'body\[data-route=\\"appointments\\"\] \.pagehead-actions \.cmdlike\{[^{}]*\}',
    r'\.pagehead-actions \.ds-pagehead-action\{[^{}]*\}',
    r'\.pagehead\.has-operations \.pagehead-actions \.doc-page-actions \.primary,\s*\.doc-page-actions \.primary\{[^{}]*\}',
]:
    css = re.sub(pattern, '', css)

old = '.pagehead-operations :is(a,button),.pagehead-actions :is(.primary,.ghost,.cmdlike,summary){\n  border-radius:var(--md-sys-shape-corner-large)!important;\n}'
if old in css:
    css = css.replace(old, '.pagehead-operations :is(a,button){\n  border-radius:var(--md-sys-shape-corner-large)!important;\n}')

canonical = '''
.pagehead-actions :where(a,button,summary){min-height:var(--pt-pagehead-action-height);display:inline-flex;align-items:center;justify-content:center;gap:var(--pt-pagehead-action-gap);padding:var(--pt-pagehead-action-padding-block) var(--pt-pagehead-action-padding-inline);border-radius:var(--pt-pagehead-action-radius);font:var(--pt-pagehead-action-font);font-weight:var(--pt-pagehead-action-weight);line-height:1;white-space:nowrap;text-decoration:none;box-shadow:none}
.pagehead-actions :where(.primary){background:var(--md-sys-color-primary);color:var(--md-sys-color-on-primary);border-color:transparent;box-shadow:0 1px 2px rgba(0,0,0,.08)}
.pagehead-actions :where(.ghost,.cmdlike):not(.primary){background:var(--md-sys-color-surface-container-lowest);color:var(--md-sys-color-primary);border-color:color-mix(in srgb,var(--md-sys-color-primary) 18%,var(--md-sys-color-outline-variant));box-shadow:none}
.pagehead-actions :where(.material-symbols-rounded,.pt-icon-glyph){font-size:var(--pt-pagehead-action-icon-size);line-height:1;flex:0 0 auto}
@media(max-width:640px){.pagehead-actions :where(a,button,summary){min-height:42px;padding-inline:13px}}
'''
css = css.rstrip() + '\n\n' + canonical
path.write_text(css)
