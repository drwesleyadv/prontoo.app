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

for old_trim, new_trim in {
    'trim($extra)': 'mb_trim($extra)',
    'trim($html)': 'mb_trim($html)',
    "trim((string) ($match[2] ?? ''))": "mb_trim((string) ($match[2] ?? ''))",
    'trim($operations)': 'mb_trim($operations)',
    'trim($actions)': 'mb_trim($actions)',
}.items():
    if source.count(old_trim) != 1:
        raise SystemExit(f'multibyte renderer anchor drift: {old_trim}')
    source = source.replace(old_trim, new_trim, 1)

write_call = "run('php', 'tools/release-contract-reconcile', '--write')"
if source.count(write_call) != 2:
    raise SystemExit(f'expected 2 release write calls, found {source.count(write_call)}')
source = source.replace(write_call, write_call + "\n" + write_call)

css_comment = '/* PageHeadControl primitive: geometry is shared; role is semantic. */'
if source.count(css_comment) != 1:
    raise SystemExit('PageHead CSS comment anchor drift')
source = source.replace(css_comment, '', 1)

budget_anchor = "(ROOT / 'app/runtime.input-boundary-budget.json').write_text(budget_json)\nrun('php', 'tools/release-contract-reconcile', '--write')"
budget_insert = r"""(ROOT / 'app/runtime.input-boundary-budget.json').write_text(budget_json)
new_budget = json.loads(budget_json)
new_totals = new_budget['totals']
consolidation_path = ROOT / 'tools/architecture-consolidation-check'
consolidation = consolidation_path.read_text()
ratchets = {
    "'runtime_input_adapter_infrastructure_references_max' => 615": "'runtime_input_adapter_infrastructure_references_max' => " + str(new_totals['infrastructure_references']),
    "'runtime_generic_data_gateway_calls_max' => 784": "'runtime_generic_data_gateway_calls_max' => " + str(new_totals['generic_data_gateway_calls']),
    "'runtime_input_adapter_hotspots_over_500_max' => 39": "'runtime_input_adapter_hotspots_over_500_max' => " + str(new_totals['hotspots_over_500']),
}
for old_target, new_target in ratchets.items():
    if consolidation.count(old_target) != 1:
        raise SystemExit(f'architecture consolidation target drift: {old_target}')
    consolidation = consolidation.replace(old_target, new_target, 1)
consolidation_path.write_text(consolidation)
architecture_path = ROOT / 'app/architecture.manifest.json'
architecture = json.loads(architecture_path.read_text())
architecture_targets = architecture['architecture_contract_targets']
architecture_targets['runtime_input_adapter_infrastructure_references_max'] = new_totals['infrastructure_references']
architecture_targets['runtime_generic_data_gateway_calls_max'] = new_totals['generic_data_gateway_calls']
architecture_targets['runtime_input_adapter_hotspots_over_500_max'] = new_totals['hotspots_over_500']
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + '\n')
run('php', 'tools/release-contract-reconcile', '--write')"""
if source.count(budget_anchor) != 1:
    raise SystemExit('runtime budget ratchet anchor drift')
source = source.replace(budget_anchor, budget_insert, 1)

path.write_text(source)
Path(__file__).unlink()
