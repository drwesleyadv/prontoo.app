from pathlib import Path

path = Path('tools/tmp-pagehead-primitive.py')
source = path.read_text()
old = ':where\\(a,button,summary\\)'
new = ':is\\(a,button,summary\\)'
count = source.count(old)
if count != 2:
    raise SystemExit(f'expected 2 bridge selector anchors, found {count}')
source = source.replace(old, new)
anchor = "css = re.sub(r'([^{}]+)\\{([^{}]*)\\}', strip_obsolete_nav_rule, css)\n\nprimitive_css = r'''"
insert = r"""css = re.sub(r'([^{}]+)\{([^{}]*)\}', strip_obsolete_nav_rule, css)

# Route adapters may retain layout (width/flex/overflow/display), never control identity.
def strip_route_control_identity(match):
    selector = re.sub(r'\s+', ' ', match.group(1).strip())
    declarations = match.group(2)
    if 'body[data-route=' not in selector or '.pagehead-control' not in selector:
        return match.group(0)
    identity_exact = {
        'min-height', 'height', 'gap', 'border', 'border-radius', 'background',
        'color', 'box-shadow', 'font', 'line-height', 'white-space',
        'align-items', 'justify-content',
    }
    identity_prefixes = ('padding', 'border-', 'background-', 'font-')
    kept = []
    for declaration in declarations.split(';'):
        item = declaration.strip()
        if not item or ':' not in item:
            continue
        prop = item.split(':', 1)[0].strip().lower()
        if prop in identity_exact or prop.startswith(identity_prefixes):
            continue
        kept.append(item)
    if not kept:
        return ''
    return match.group(1) + '{' + ';'.join(kept) + '}'

css = re.sub(r'([^{}]+)\{([^{}]*)\}', strip_route_control_identity, css)

primitive_css = r'''"""
if source.count(anchor) != 1:
    raise SystemExit('route identity cleanup anchor drift')
source = source.replace(anchor, insert, 1)

nav_metric_py = "'canonical_nav_css_rules': css.count('.pagehead-control--nav{'),"
nav_metric_php = "'canonical_nav_css_rules' => substr_count($css, '.pagehead-control--nav{'),"
nav_signature = ".pagehead-control--nav{background:transparent;color:var(--pt-pagehead-control-accent);border-color:transparent}"
if source.count(nav_metric_py) != 1 or source.count(nav_metric_php) != 1:
    raise SystemExit('canonical nav metric anchor drift')
source = source.replace(nav_metric_py, f"'canonical_nav_css_rules': css.count('{nav_signature}'),", 1)
source = source.replace(nav_metric_php, f"'canonical_nav_css_rules' => substr_count($css, '{nav_signature}'),", 1)
renderer_old = "foreach (['pagehead-control--primary', 'pagehead-control--secondary', 'pagehead-control--danger', 'actionFragment', 'pageHead'] as $needle) {"
renderer_new = "foreach ([\"private const ROLES = ['nav', 'primary', 'secondary', 'danger'];\", 'pagehead-control', 'actionFragment', 'pageHead'] as $needle) {"
if source.count(renderer_old) != 1:
    raise SystemExit('renderer contract anchor drift')
source = source.replace(renderer_old, renderer_new, 1)

path.write_text(source)
Path(__file__).unlink()
