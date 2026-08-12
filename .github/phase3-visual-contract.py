from pathlib import Path
import json
import re
import subprocess

root = Path('.')
baseline_sha = 'da597d5af26eae90b66b5a473150ce619310e527'
phase1_sha = '5c8a450d4808eb052e483a31488f1dcc33c69707'
phase2_sha = '485f8cca8f292adfc4b8510ea95294963f647e0e'
release = '1.8.12.1'
css_path = root / 'public/assets/design-system.css'
identity_re = re.compile(r'(^|;)\s*(?:min-height|height|padding(?:-[a-z]+)?|gap|border(?:-[a-z]+)?|border-radius|background(?:-[a-z]+)?|color|box-shadow|font(?:-[a-z]+)?|line-height|white-space|align-items|justify-content)\s*:', re.I)


def rules(text):
    return [(m.group(1).strip(), m.group(2)) for m in re.finditer(r'([^{}]+)\{([^{}]*)\}', text)]


def is_route_descendant_identity(selector, declarations):
    return 'body[data-route=' in selector and re.search(r'\.pagehead-actions\s+(?:[>+~]|\.)', selector) is not None and identity_re.search(declarations) is not None


def is_legacy_direct_identity(selector, declarations):
    return re.search(r'\.pagehead-actions\s*>\s*(?:a|button|summary)', selector) is not None and identity_re.search(declarations) is not None


def metrics(text):
    selectors = []
    for selector, declarations in rules(text):
        normalized = re.sub(r'\s+', ' ', selector)
        if normalized and not normalized.startswith('@'):
            selectors.append(normalized)
    counts = {}
    for selector in selectors:
        counts[selector] = counts.get(selector, 0) + 1
    return {
        'lines': text.count('\n') + 1,
        'nonblank_lines': sum(1 for line in text.splitlines() if line.strip()),
        'bytes': len(text.encode()),
        'important_count': text.count('!important'),
        'route_scope_count': text.count('body[data-route='),
        'duplicate_selector_headers': sum(1 for count in counts.values() if count > 1),
        'pagehead_route_identity_overrides': sum(1 for selector, declarations in rules(text) if is_route_descendant_identity(selector, declarations)),
        'pagehead_legacy_direct_identity_overrides': sum(1 for selector, declarations in rules(text) if is_legacy_direct_identity(selector, declarations)),
        'doc_pagehead_primary_overrides': text.count('.doc-page-actions .primary{'),
        'pagehead_ds_action_overrides': text.count('.pagehead-actions .ds-pagehead-action{'),
        'canonical_pagehead_geometry_rules': text.count('.pagehead-actions :where(a,button,summary){min-height:var(--pt-pagehead-action-height)'),
    }


def legacy_count(ref=None):
    if ref is None:
        return sum(path.read_text(errors='ignore').count('primary small cmdlike') for path in (root / 'app').rglob('*.php'))
    result = subprocess.run(['git', 'grep', '-o', '-F', 'primary small cmdlike', ref, '--', 'app'], capture_output=True, text=True)
    if result.returncode not in (0, 1):
        raise SystemExit('legacy visual debt scan failed')
    return len([line for line in result.stdout.splitlines() if line.strip()])


css = css_path.read_text()

def strip_route_identity(match):
    selector = match.group(1)
    declarations = match.group(2)
    return '' if is_route_descendant_identity(selector, declarations) else match.group(0)

css = re.sub(r'([^{}]+)\{([^{}]*)\}', strip_route_identity, css)
legacy_group = '.pagehead .actions > a,\n.pagehead .actions > button,\n.pagehead-actions > a,\n.pagehead-actions > button,\n.cmdbar-actions > a,\n.cmdbar-actions > button{'
lean_group = '.pagehead .actions > a,\n.pagehead .actions > button,\n.cmdbar-actions > a,\n.cmdbar-actions > button{'
if legacy_group not in css:
    raise SystemExit('legacy direct PageHead identity group not found')
css = css.replace(legacy_group, lean_group, 1)
needle = '  --pt-pagehead-action-icon-size:20px;\n  --pt-pagehead-action-font:var(--md-sys-typescale-label-medium);'
if css.count(needle) != 1:
    raise SystemExit('PageHead token insertion point not unique')
css = css.replace(needle, '  --pt-pagehead-action-icon-size:20px;\n  --pt-pagehead-action-mobile-height:42px;\n  --pt-pagehead-action-mobile-padding-inline:13px;\n  --pt-pagehead-action-font:var(--md-sys-typescale-label-medium);')
old_mobile = '@media(max-width:640px){.pagehead-actions :where(a,button,summary){min-height:42px;padding-inline:13px}}'
new_mobile = '@media(max-width:640px){.pagehead-actions :where(a,button,summary){min-height:var(--pt-pagehead-action-mobile-height);padding-inline:var(--pt-pagehead-action-mobile-padding-inline)}}'
if css.count(old_mobile) != 1:
    raise SystemExit('PageHead mobile canonical rule not unique')
css = css.replace(old_mobile, new_mobile)
css_path.write_text(css)

ui_path = root / 'app/Runtime/UiComponents/UiComponentsRuntimeOperations03.php'
ui = ui_path.read_text()
marker = '<div class="pagehead-actions" aria-label="Operações rápidas">'
replacement = '<div class="pagehead-actions" data-ui-contract="pagehead-actions-v1" aria-label="Operações rápidas">'
if ui.count(marker) != 1:
    raise SystemExit('PageHead DOM contract point not unique')
ui_path.write_text(ui.replace(marker, replacement))

baseline_css = subprocess.check_output(['git', 'show', baseline_sha + ':public/assets/design-system.css'], text=True)
current_css = css_path.read_text()
before = metrics(baseline_css)
after = metrics(current_css)
before['legacy_primary_small_cmdlike'] = legacy_count(baseline_sha)
after['legacy_primary_small_cmdlike'] = legacy_count()
delta = {key: after[key] - before[key] for key in before}

contract = {
    'version': release,
    'policy': 'presentation-visual-contract-v1',
    'architecture_scope': 'presentation_ui_adapter_contract',
    'reference': {
        'baseline_sha': baseline_sha,
        'phase_1_merge_sha': phase1_sha,
        'phase_2_merge_sha': phase2_sha,
        'visual_reference': 'existing_primary_small_pagehead_action',
        'ux_policy': 'preserve_daily_use_memory_layout_flow_information_hierarchy_and_touch_targets',
    },
    'pagehead_action': {
        'desktop': {
            'min_height_px': 34,
            'radius_px': 16,
            'padding_block_px': 7,
            'padding_inline_px': 12,
            'gap_px': 8,
            'icon_px': 20,
            'font_token': '--md-sys-typescale-label-medium',
            'font_weight': 700,
            'line_height': 1,
            'white_space': 'nowrap',
            'display': 'inline-flex',
            'align_items': 'center',
            'justify_content': 'center',
            'ratios': {
                'icon_to_height': 0.5882,
                'radius_to_height': 0.4706,
                'padding_inline_to_height': 0.3529,
                'gap_to_height': 0.2353,
            },
        },
        'mobile': {
            'breakpoint_max_px': 640,
            'min_height_px': 42,
            'padding_inline_px': 13,
            'ratios': {
                'icon_to_height': 0.4762,
                'radius_to_height': 0.3810,
                'padding_inline_to_height': 0.3095,
            },
        },
        'color_contract': {
            'clinic_accent_chain': '--clinic-accent -> --md-ref-palette-primary40 -> --md-sys-color-primary -> --pt-pagehead-action-accent',
            'clinic_accent_strong_chain': '--clinic-accent-strong -> --md-ref-palette-primary30 -> --pt-pagehead-action-accent-strong',
            'read_only_scope': 'body.is-read-only',
            'read_only_chain': '--md-sys-color-primary -> --md-sys-color-secondary -> --pt-pagehead-action-accent',
            'read_only_secondary_mix': '46% primary + neutral #5d6b63',
            'semantic_error_palette': '--md-sys-color-error',
            'geometry_may_change_with_context': False,
        },
    },
    'invariants': [
        'pagehead_action_geometry_has_single_css_owner',
        'primary_action_wins_over_legacy_cmdlike_composition',
        'clinic_palette_changes_color_not_geometry',
        'read_only_state_changes_context_color_not_geometry',
        'danger_remains_semantic_error_not_clinic_accent',
        'mobile_preserves_42px_touch_target',
        'route_layout_may_change_but_route_action_identity_may_not',
        'module_specific_pagehead_primary_overrides_are_zero',
        'visual_debt_may_not_increase_without_contract_change',
    ],
    'css_statistics': {'before': before, 'after': after, 'delta': delta},
    'debt_budget': {
        'important_count_max': after['important_count'],
        'route_scope_count_max': after['route_scope_count'],
        'duplicate_selector_headers_max': after['duplicate_selector_headers'],
        'legacy_primary_small_cmdlike_max': after['legacy_primary_small_cmdlike'],
        'pagehead_route_identity_overrides_max': 0,
        'pagehead_legacy_direct_identity_overrides_max': 0,
        'doc_pagehead_primary_overrides_max': 0,
        'pagehead_ds_action_overrides_max': 0,
        'canonical_pagehead_geometry_rules_exact': 1,
    },
}
(root / 'app/presentation.visual-contract.json').write_text(json.dumps(contract, indent=4, ensure_ascii=False) + '\n')

checker = r'''<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
$contract = json_decode((string) file_get_contents($root . '/app/presentation.visual-contract.json'), true, 512, JSON_THROW_ON_ERROR);
$version = json_decode((string) file_get_contents($root . '/version.json'), true, 512, JSON_THROW_ON_ERROR);
$css = (string) file_get_contents($root . '/public/assets/design-system.css');
$ui = (string) file_get_contents($root . '/app/Runtime/UiComponents/UiComponentsRuntimeOperations03.php');
$php = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
        $php .= (string) file_get_contents($file->getPathname());
    }
}
$identityPattern = '/(^|;)\s*(?:min-height|height|padding(?:-[a-z]+)?|gap|border(?:-[a-z]+)?|border-radius|background(?:-[a-z]+)?|color|box-shadow|font(?:-[a-z]+)?|line-height|white-space|align-items|justify-content)\s*:/i';
$routeIdentity = 0;
$legacyDirectIdentity = 0;
$selectorCounts = [];
if (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) {
        $selector = preg_replace('/\s+/', ' ', trim((string) $match[1]));
        $declarations = (string) $match[2];
        if (is_string($selector) && $selector !== '' && !str_starts_with($selector, '@')) {
            $selectorCounts[$selector] = ($selectorCounts[$selector] ?? 0) + 1;
        }
        if (str_contains((string) $selector, 'body[data-route=') && preg_match('/\.pagehead-actions\s+(?:[>+~]|\.)/', (string) $selector) === 1 && preg_match($identityPattern, $declarations) === 1) {
            $routeIdentity++;
        }
        if (preg_match('/\.pagehead-actions\s*>\s*(?:a|button|summary)/', (string) $selector) === 1 && preg_match($identityPattern, $declarations) === 1) {
            $legacyDirectIdentity++;
        }
    }
}
$metrics = [
    'lines' => substr_count($css, "\n") + 1,
    'nonblank_lines' => count(array_filter(explode("\n", $css), static fn(string $line): bool => trim($line) !== '')),
    'bytes' => strlen($css),
    'important_count' => substr_count($css, '!important'),
    'route_scope_count' => substr_count($css, 'body[data-route='),
    'duplicate_selector_headers' => count(array_filter($selectorCounts, static fn(int $count): bool => $count > 1)),
    'legacy_primary_small_cmdlike' => substr_count($php, 'primary small cmdlike'),
    'pagehead_route_identity_overrides' => $routeIdentity,
    'pagehead_legacy_direct_identity_overrides' => $legacyDirectIdentity,
    'doc_pagehead_primary_overrides' => substr_count($css, '.doc-page-actions .primary{'),
    'pagehead_ds_action_overrides' => substr_count($css, '.pagehead-actions .ds-pagehead-action{'),
    'canonical_pagehead_geometry_rules' => substr_count($css, '.pagehead-actions :where(a,button,summary){min-height:var(--pt-pagehead-action-height)'),
];
$required = [
    '--md-ref-palette-primary40: var(--clinic-accent,#2f6652)',
    '--md-sys-color-secondary:color-mix(in srgb,var(--md-sys-color-primary) 46%,#5d6b63)',
    '--pt-pagehead-action-height:34px',
    '--pt-pagehead-action-radius:var(--md-sys-shape-corner-large)',
    '--pt-pagehead-action-padding-block:7px',
    '--pt-pagehead-action-padding-inline:12px',
    '--pt-pagehead-action-gap:8px',
    '--pt-pagehead-action-icon-size:20px',
    '--pt-pagehead-action-mobile-height:42px',
    '--pt-pagehead-action-mobile-padding-inline:13px',
    '--pt-pagehead-action-font:var(--md-sys-typescale-label-medium)',
    '--pt-pagehead-action-weight:700',
    '--pt-pagehead-action-accent:var(--md-sys-color-primary)',
    '--pt-pagehead-action-on-accent:var(--md-sys-color-on-primary)',
    'body.is-read-only{--pt-pagehead-action-accent:var(--md-sys-color-secondary)',
    '.pagehead-actions :where(.primary){background:var(--pt-pagehead-action-accent);color:var(--pt-pagehead-action-on-accent)',
    '.pagehead-actions :where(.ghost,.cmdlike):not(.primary){background:var(--md-sys-color-surface-container-lowest);color:var(--pt-pagehead-action-accent)',
    '@media(max-width:640px){.pagehead-actions :where(a,button,summary){min-height:var(--pt-pagehead-action-mobile-height);padding-inline:var(--pt-pagehead-action-mobile-padding-inline)}}',
];
$failures = [];
foreach ($required as $token) {
    if (!str_contains($css, $token)) {
        $failures[] = 'missing:' . $token;
    }
}
if (!str_contains($ui, 'data-ui-contract="pagehead-actions-v1"')) {
    $failures[] = 'missing:pagehead-dom-contract';
}
if (($contract['version'] ?? '') !== ($version['version'] ?? '')) {
    $failures[] = 'version';
}
$desktop = (array) (($contract['pagehead_action'] ?? [])['desktop'] ?? []);
$mobile = (array) (($contract['pagehead_action'] ?? [])['mobile'] ?? []);
foreach ([
    'desktop_height' => [(int) ($desktop['min_height_px'] ?? 0), 34],
    'desktop_radius' => [(int) ($desktop['radius_px'] ?? 0), 16],
    'desktop_padding_block' => [(int) ($desktop['padding_block_px'] ?? 0), 7],
    'desktop_padding_inline' => [(int) ($desktop['padding_inline_px'] ?? 0), 12],
    'desktop_gap' => [(int) ($desktop['gap_px'] ?? 0), 8],
    'desktop_icon' => [(int) ($desktop['icon_px'] ?? 0), 20],
    'desktop_weight' => [(int) ($desktop['font_weight'] ?? 0), 700],
    'mobile_height' => [(int) ($mobile['min_height_px'] ?? 0), 42],
    'mobile_padding_inline' => [(int) ($mobile['padding_inline_px'] ?? 0), 13],
] as $name => [$actual, $expected]) {
    if ($actual !== $expected) {
        $failures[] = $name . ':' . $actual . '!=' . $expected;
    }
}
$budget = (array) ($contract['debt_budget'] ?? []);
foreach ([
    'important_count' => 'important_count_max',
    'route_scope_count' => 'route_scope_count_max',
    'duplicate_selector_headers' => 'duplicate_selector_headers_max',
    'legacy_primary_small_cmdlike' => 'legacy_primary_small_cmdlike_max',
    'pagehead_route_identity_overrides' => 'pagehead_route_identity_overrides_max',
    'pagehead_legacy_direct_identity_overrides' => 'pagehead_legacy_direct_identity_overrides_max',
    'doc_pagehead_primary_overrides' => 'doc_pagehead_primary_overrides_max',
    'pagehead_ds_action_overrides' => 'pagehead_ds_action_overrides_max',
] as $metric => $limit) {
    if ($metrics[$metric] > (int) ($budget[$limit] ?? -1)) {
        $failures[] = $metric . ':' . $metrics[$metric] . '>' . (int) ($budget[$limit] ?? -1);
    }
}
if ($metrics['canonical_pagehead_geometry_rules'] !== (int) ($budget['canonical_pagehead_geometry_rules_exact'] ?? 1)) {
    $failures[] = 'canonical_pagehead_geometry_rules';
}
fwrite(STDOUT, json_encode([
    'ok' => $failures === [],
    'policy' => (string) ($contract['policy'] ?? ''),
    'version' => (string) ($version['version'] ?? ''),
    'metrics' => $metrics,
    'budget' => $budget,
    'pagehead_action' => (array) ($contract['pagehead_action'] ?? []),
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($failures === [] ? 0 : 1);
'''
checker_path = root / 'tools/presentation-visual-contract-check'
checker_path.write_text(checker)
checker_path.chmod(0o755)

qg_path = root / 'tools/quality-gate'
qg = qg_path.read_text()
needle = "$run('operation-gateway-budget', 'tools/operation-gateway-budget-check');\n"
if qg.count(needle) != 1 or 'presentation-visual-contract-check' in qg:
    raise SystemExit('quality gate insertion point invalid')
qg_path.write_text(qg.replace(needle, needle + "$run('presentation-visual-contract', 'tools/presentation-visual-contract-check');\n"))

arch_path = root / 'app/architecture.manifest.json'
arch = json.loads(arch_path.read_text())
arch['presentation_visual_policy'] = 'presentation-visual-contract-v1'
arch['presentation_visual_contract'] = 'app/presentation.visual-contract.json'
arch['presentation_visual_check'] = 'tools/presentation-visual-contract-check'
arch['presentation_visual_reference_sha'] = baseline_sha
arch['presentation_visual_phase_1_sha'] = phase1_sha
arch['presentation_visual_phase_2_sha'] = phase2_sha
arch_path.write_text(json.dumps(arch, indent=4, ensure_ascii=False) + '\n')
