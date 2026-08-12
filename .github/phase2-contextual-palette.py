from pathlib import Path

path = Path('public/assets/design-system.css')
css = path.read_text()

needle = '  --pt-pagehead-action-weight:700;\n}'
if css.count(needle) != 1:
    raise SystemExit('pagehead geometry token block not found uniquely')
css = css.replace(needle, '''  --pt-pagehead-action-weight:700;
  --pt-pagehead-action-accent:var(--md-sys-color-primary);
  --pt-pagehead-action-on-accent:var(--md-sys-color-on-primary);
  --pt-pagehead-action-accent-strong:var(--md-ref-palette-primary30);
}
''')

body = 'body{margin:0;min-height:100vh;background:var(--md-sys-color-background);color:var(--md-sys-color-on-background);font:var(--md-sys-typescale-body-large);-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}\n'
if css.count(body) != 1:
    raise SystemExit('body insertion point not found uniquely')
css = css.replace(
    body,
    body + 'body.is-read-only{--pt-pagehead-action-accent:var(--md-sys-color-secondary);--pt-pagehead-action-on-accent:var(--md-sys-color-on-secondary);--pt-pagehead-action-accent-strong:color-mix(in srgb,var(--md-sys-color-secondary) 78%,#000)}\n',
)

replacements = {
    '.pagehead-actions :where(.primary){background:var(--md-sys-color-primary);color:var(--md-sys-color-on-primary);border-color:transparent;box-shadow:0 1px 2px rgba(0,0,0,.08)}':
        '.pagehead-actions :where(.primary){background:var(--pt-pagehead-action-accent);color:var(--pt-pagehead-action-on-accent);border-color:transparent;box-shadow:0 1px 2px rgba(0,0,0,.08)}',
    '.pagehead-actions :where(.ghost,.cmdlike):not(.primary){background:var(--md-sys-color-surface-container-lowest);color:var(--md-sys-color-primary);border-color:color-mix(in srgb,var(--md-sys-color-primary) 18%,var(--md-sys-color-outline-variant));box-shadow:none}':
        '.pagehead-actions :where(.ghost,.cmdlike):not(.primary){background:var(--md-sys-color-surface-container-lowest);color:var(--pt-pagehead-action-accent);border-color:color-mix(in srgb,var(--pt-pagehead-action-accent) 18%,var(--md-sys-color-outline-variant));box-shadow:none}',
}
for old, new in replacements.items():
    if css.count(old) != 1:
        raise SystemExit('canonical pagehead color rule not found uniquely')
    css = css.replace(old, new)

hover = '.pagehead-actions :where(.material-symbols-rounded,.pt-icon-glyph){font-size:var(--pt-pagehead-action-icon-size);line-height:1;flex:0 0 auto}'
if css.count(hover) != 1:
    raise SystemExit('canonical icon rule not found uniquely')
hover_rules = '.pagehead-actions :where(.ghost,.cmdlike):not(.primary):hover{background:color-mix(in srgb,var(--pt-pagehead-action-accent) 8%,transparent);border-color:color-mix(in srgb,var(--pt-pagehead-action-accent) 34%,var(--md-sys-color-outline-variant));color:var(--pt-pagehead-action-accent)}.pagehead-actions :where(.primary):hover{background:var(--pt-pagehead-action-accent-strong);color:var(--pt-pagehead-action-on-accent);box-shadow:var(--md-sys-elevation-level2)}\n'
css = css.replace(hover, hover_rules + hover)

path.write_text(css)
