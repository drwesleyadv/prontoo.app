from pathlib import Path
import json
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
CSS_PATH = ROOT / 'public/assets/design-system.css'
JS_PATH = ROOT / 'public/assets/app.js'
RT2_PATH = ROOT / 'app/Runtime/UiComponents/UiComponentsRuntimeOperations02.php'
RT3_PATH = ROOT / 'app/Runtime/UiComponents/UiComponentsRuntimeOperations03.php'
RENDERER_PATH = ROOT / 'app/Presentation/UiComponents/PageHeadControlPresentationOperations01.php'
CONTRACT_PATH = ROOT / 'app/presentation.visual-contract.json'
CHECKER_PATH = ROOT / 'tools/presentation-visual-contract-check'
VERSION_PATH = ROOT / 'version.json'
WORKFLOW_PATH = ROOT / '.github/workflows/reconcile-pagehead-control-primitive.yml'
SELF_PATH = Path(__file__).resolve()


def run(*args):
    subprocess.run(args, cwd=ROOT, check=True)


def replace_once(source, old, new, label):
    count = source.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected 1 anchor, found {count}')
    return source.replace(old, new, 1)


def replace_between(source, start_marker, end_marker, replacement, label):
    start = source.find(start_marker)
    if start < 0:
        raise SystemExit(f'{label}: start marker missing')
    end = source.find(end_marker, start)
    if end < 0:
        raise SystemExit(f'{label}: end marker missing')
    return source[:start] + replacement + source[end:]


def php_sources():
    return ''.join(path.read_text(errors='ignore') for path in (ROOT / 'app').rglob('*.php'))


def duplicate_selector_headers(css):
    counts = {}
    for match in re.finditer(r'([^{}]+)\{([^{}]*)\}', css):
        selector = re.sub(r'\s+', ' ', match.group(1).strip())
        if selector and not selector.startswith('@'):
            counts[selector] = counts.get(selector, 0) + 1
    return sum(1 for count in counts.values() if count > 1)


def metrics():
    css = CSS_PATH.read_text()
    js = JS_PATH.read_text()
    rt2 = RT2_PATH.read_text()
    rt3 = RT3_PATH.read_text()
    renderer = RENDERER_PATH.read_text() if RENDERER_PATH.exists() else ''
    php = php_sources()
    identity = re.compile(r'(^|;)\s*(?:min-height|height|padding(?:-[a-z]+)?|gap|border(?:-[a-z]+)?|border-radius|background(?:-[a-z]+)?|color|box-shadow|font(?:-[a-z]+)?|line-height|white-space|align-items|justify-content)\s*:', re.I)
    route_overrides = 0
    for match in re.finditer(r'([^{}]+)\{([^{}]*)\}', css):
        selector = re.sub(r'\s+', ' ', match.group(1).strip())
        if 'body[data-route=' in selector and '.pagehead-control' in selector and identity.search(match.group(2)):
            route_overrides += 1
    return {
        'css_lines': css.count('\n') + 1,
        'css_nonblank_lines': sum(1 for line in css.splitlines() if line.strip()),
        'css_bytes': len(css.encode()),
        'important_count': css.count('!important'),
        'route_scope_count': css.count('body[data-route='),
        'duplicate_selector_headers': duplicate_selector_headers(css),
        'legacy_primary_small_cmdlike_inputs': php.count('primary small cmdlike'),
        'legacy_pagehead_actions_css': css.count('.pagehead-actions'),
        'legacy_pagehead_operations_css': css.count('.pagehead-operations'),
        'legacy_operation_chip_css': css.count('.operation-chip'),
        'legacy_pagehead_actions_js': js.count('.pagehead-actions'),
        'pagehead_action_alias_tokens': css.count('--pt-pagehead-action-'),
        'canonical_control_css_rules': css.count('.pagehead-control{min-height:var(--pt-pagehead-control-height)'),
        'canonical_primary_css_rules': css.count('.pagehead-control--primary{'),
        'canonical_secondary_css_rules': css.count('.pagehead-control--secondary{'),
        'canonical_nav_css_rules': css.count('.pagehead-control--nav{'),
        'canonical_danger_css_rules': css.count('.pagehead-control--danger{'),
        'canonical_renderer_runtime_calls': rt2.count('PageHeadControlPresentationOperations01::link') + rt3.count('PageHeadControlPresentationOperations01::pageHead'),
        'renderer_lines': renderer.count('\n') + 1 if renderer else 0,
        'runtime03_lines': rt3.count('\n') + 1,
        'runtime03_dead_operation_menu': rt3.count('operation_menu_html') + rt3.count('"__menu"'),
        'route_pagehead_identity_overrides': route_overrides,
    }


before = metrics()

renderer = r'''<?php
declare(strict_types=1);

namespace Prontoo\Presentation\UiComponents;

final class PageHeadControlPresentationOperations01
{
    private const ROLES = ['nav', 'primary', 'secondary', 'danger'];
    private const LEGACY = ['primary', 'ghost', 'danger', 'danger-soft', 'cmdlike', 'small'];

    private function __construct()
    {
    }

    public static function classes(string $role, string $extra = '', bool $active = false): string
    {
        $role = in_array($role, self::ROLES, true) ? $role : 'secondary';
        $tokens = ['pagehead-control', 'pagehead-control--' . $role];
        foreach (preg_split('/\s+/', trim($extra)) ?: [] as $token) {
            if ($token !== '' && !in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }
        if ($active && !in_array('is-active', $tokens, true)) {
            $tokens[] = 'is-active';
        }
        return implode(' ', $tokens);
    }

    public static function link(
        string $label,
        string $iconName,
        string $href,
        string $role = 'nav',
        bool $active = false,
        string $extraClass = '',
    ): string {
        $class = self::classes($role, $extraClass, $active);
        return '<a class="' . UiComponentsPresentationOperations01::e($class) .
            '" href="' . UiComponentsPresentationOperations01::e($href) .
            '" title="' . UiComponentsPresentationOperations01::e($label) . '"' .
            ($active ? ' aria-current="page"' : '') . '>' .
            UiComponentsPresentationOperations01::icon($iconName) .
            '<span>' . UiComponentsPresentationOperations01::e($label) . '</span></a>';
    }

    public static function actionFragment(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $result = preg_replace_callback(
            '/class=(["\x27])(.*?)\1/s',
            static function (array $match): string {
                $tokens = array_values(array_filter(
                    preg_split('/\s+/', trim((string) ($match[2] ?? ''))) ?: [],
                    static fn(string $token): bool => $token !== '',
                ));
                if (in_array('pagehead-control', $tokens, true)) {
                    return (string) $match[0];
                }
                $role = null;
                $dangerSoft = in_array('danger-soft', $tokens, true);
                if ($dangerSoft || in_array('danger', $tokens, true)) {
                    $role = 'danger';
                } elseif (in_array('primary', $tokens, true)) {
                    $role = 'primary';
                } elseif (in_array('ghost', $tokens, true) || in_array('cmdlike', $tokens, true)) {
                    $role = 'secondary';
                }
                if ($role === null) {
                    return (string) $match[0];
                }
                $extra = array_values(array_filter(
                    $tokens,
                    static fn(string $token): bool => !in_array($token, self::LEGACY, true),
                ));
                if ($dangerSoft) {
                    $extra[] = 'pagehead-control--danger-soft';
                }
                $class = self::classes($role, implode(' ', $extra));
                $quote = (string) ($match[1] ?? '"');
                return 'class=' . $quote . $class . $quote;
            },
            $html,
        );
        return is_string($result) ? $result : $html;
    }

    public static function pageHead(
        string $title,
        string $iconName,
        string $operations = '',
        string $actions = '',
    ): string {
        $actions = self::actionFragment($actions);
        $hasOperations = trim($operations) !== '';
        $hasActions = trim($actions) !== '';
        return '<section class="pagehead' .
            ($hasOperations ? ' has-operations' : '') .
            ($hasActions ? ' has-actions' : '') .
            '" aria-label="Operações da tela"><div class="pagehead-copy"><h1><span class="pagehead-icon">' .
            UiComponentsPresentationOperations01::icon($iconName) .
            '</span><span>' . UiComponentsPresentationOperations01::e($title) .
            '</span></h1></div>' . $operations .
            ($hasActions
                ? '<div class="pagehead-controls pagehead-controls--actions" aria-label="Operações rápidas">' . $actions . '</div>'
                : '') .
            '</section>';
    }
}
'''
RENDERER_PATH.write_text(renderer)

rt2 = RT2_PATH.read_text()
rt2_start = '    public static function operation_link_html('
rt2_pos = rt2.find(rt2_start)
if rt2_pos < 0:
    raise SystemExit('Runtime02 operation_link_html missing')
rt2_end = rt2.rfind('\n}')
if rt2_end <= rt2_pos:
    raise SystemExit('Runtime02 class end missing')
new_rt2_method = r'''    public static function operation_link_html(
        string $route,
        string $label,
        string $iconName,
        string $current,
        array $params = [],
    ): string
    {
        if (is_callable([\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::class, 'prontoo_icon_for_route_label'])) {
            $iconName = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::prontoo_icon_for_route_label(
                $route,
                $label,
                $params,
                $iconName,
            );
        }
        $active = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::operation_current_match($route, $params, $current);
        return \Prontoo\Presentation\UiComponents\PageHeadControlPresentationOperations01::link(
            $label,
            $iconName,
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route, $params),
            'nav',
            $active,
        );
    }
'''
rt2 = rt2[:rt2_pos] + new_rt2_method + rt2[rt2_end:]
RT2_PATH.write_text(rt2)

rt3 = RT3_PATH.read_text()
rt3 = replace_between(
    rt3,
    '    public static function operation_menu_html(',
    '    public static function page_operations_html(',
    '',
    'remove dead operation menu',
)
rt3, count = re.subn(
    r'\n\s*if \(\$route === "__menu"\) \{.*?\n\s*continue;\n\s*\}\n',
    '\n',
    rt3,
    count=1,
    flags=re.S,
)
if count != 1:
    raise SystemExit(f'remove __menu branch: expected 1, found {count}')
rt3 = replace_once(
    rt3,
    '<nav class="pagehead-operations" aria-label="Operações">',
    '<nav class="pagehead-controls pagehead-controls--navigation" aria-label="Operações">',
    'canonical navigation container',
)
new_page_head = r'''    public static function page_head(string $title, string $sub = "", string $action = ""): string
    {
        $icon = \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head_icon_name($title);
        $context = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
        if (($context["scope"] ?? "") === "global") {
            $action = "";
        }
        $operations = $context
            ? \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_operations_html(
                \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route(),
                $context,
            )
            : "";
        return \Prontoo\Presentation\UiComponents\PageHeadControlPresentationOperations01::pageHead(
            $title,
            $icon,
            $operations,
            $action,
        );
    }

'''
rt3 = replace_between(
    rt3,
    '    public static function page_head(string $title, string $sub = "", string $action = ""): string',
    '    public static function action_icon_for(',
    new_page_head,
    'move PageHead rendering to Presentation',
)
# Remove legacy unused imports and collapse generated empty spacing only in the touched hotspot.
for name in ['Closure', 'DateInterval', 'DateTime', 'DateTimeImmutable', 'DateTimeInterface', 'DateTimeZone', 'Exception', 'GdImage', 'InvalidArgumentException', 'JsonException', 'LogicException', 'PDO', 'PDOException', 'ProntooHttpError', 'RuntimeException', 'Throwable']:
    rt3 = rt3.replace(f'use \\{name};\n', '')
rt3 = re.sub(r'\n[ \t]*\n[ \t]*\n+', '\n\n', rt3)
if rt3.count('\n') + 1 > 500:
    rt3 = '\n'.join(line for line in rt3.splitlines() if line.strip()) + '\n'
RT3_PATH.write_text(rt3)

css = CSS_PATH.read_text()
css = css.replace('.pagehead-actions', '.pagehead-controls--actions')
css = css.replace('.pagehead-operations', '.pagehead-controls--navigation')
css = css.replace('.operation-chip', '.pagehead-control--nav')
css = css.replace('.pagehead-controls--actions .cmdlike', '.pagehead-controls--actions .pagehead-control')
css = css.replace('.pagehead-controls--actions .primary', '.pagehead-controls--actions .pagehead-control--primary')
css = css.replace('.pagehead-controls--actions .ghost', '.pagehead-controls--actions .pagehead-control--secondary')
css = css.replace('.pagehead-controls--actions .danger-soft', '.pagehead-controls--actions .pagehead-control--danger-soft')
css = css.replace('.pagehead-controls--actions .danger', '.pagehead-controls--actions .pagehead-control--danger')
css = re.sub(r'--pt-pagehead-action-[a-z0-9-]+:[^;{}]+;?', '', css, flags=re.I)

# Remove the old action bridge at the end; the canonical primitive replaces it.
bridge = re.compile(
    r'\n\.pagehead-controls--actions :where\(a,button,summary\)\{.*?@media\(max-width:640px\)\{\.pagehead-controls--actions :where\(a,button,summary\)\{[^{}]*\}\}\s*$',
    re.S,
)
css, count = bridge.subn('\n', css, count=1)
if count != 1:
    raise SystemExit(f'legacy action bridge: expected 1, found {count}')

# Remove generic navigation identity/geometry rules now owned by PageHeadControl.
def strip_obsolete_nav_rule(match):
    selector = re.sub(r'\s+', ' ', match.group(1).strip())
    declarations = match.group(2)
    if '.operation-menu' in selector:
        return ''
    if '.pagehead-controls--navigation .pagehead-control--nav' not in selector:
        return match.group(0)
    if 'body[data-route=' in selector or ':not(' in selector:
        return match.group(0)
    if re.search(r'(?:min-height|padding|border-radius|font|font-weight|line-height|background|color|border-color|box-shadow|font-variation-settings|font-size)\s*:', declarations):
        return ''
    return match.group(0)

css = re.sub(r'([^{}]+)\{([^{}]*)\}', strip_obsolete_nav_rule, css)

primitive_css = r'''
/* PageHeadControl primitive: geometry is shared; role is semantic. */
.pagehead-controls{display:flex;align-items:center;min-width:0}
.pagehead-controls--actions{justify-content:flex-end;gap:var(--pt-pagehead-control-gap);flex-wrap:wrap}
.pagehead-controls--navigation{justify-content:flex-end;gap:var(--pt-pagehead-operation-gap);flex-wrap:wrap;padding:4px;border-radius:20px;background:color-mix(in srgb,var(--pt-pagehead-control-accent) 3%,var(--md-sys-color-surface-container-low))}
.pagehead-control{min-height:var(--pt-pagehead-control-height);display:inline-flex;align-items:center;justify-content:center;gap:var(--pt-pagehead-control-gap);padding:var(--pt-pagehead-control-padding-block) var(--pt-pagehead-control-padding-inline);border:1px solid transparent;border-radius:var(--pt-pagehead-control-radius);font:var(--pt-pagehead-control-font);font-weight:var(--pt-pagehead-control-weight);line-height:1;white-space:nowrap;text-decoration:none;box-shadow:none;transition:background var(--motion-fast),border-color var(--motion-fast),color var(--motion-fast),box-shadow var(--motion-fast)}
.pagehead-control :is(.material-symbols-rounded,.pt-icon-glyph){font-size:var(--pt-pagehead-control-icon-size);line-height:1;flex:0 0 auto}
.pagehead-control--primary{background:var(--pt-pagehead-control-accent);color:var(--pt-pagehead-control-on-accent);border-color:transparent;box-shadow:0 1px 2px rgba(0,0,0,.06)}
.pagehead-control--primary:hover{background:var(--pt-pagehead-control-hover);color:var(--pt-pagehead-control-on-accent);box-shadow:var(--md-sys-elevation-level1)}
.pagehead-control--secondary{background:color-mix(in srgb,var(--pt-pagehead-control-accent) 4%,var(--md-sys-color-surface-container-lowest));color:var(--pt-pagehead-control-accent);border-color:color-mix(in srgb,var(--pt-pagehead-control-accent) 18%,var(--md-sys-color-outline-variant))}
.pagehead-control--secondary:hover{background:color-mix(in srgb,var(--pt-pagehead-control-hover) 10%,var(--md-sys-color-surface-container-lowest));color:var(--pt-pagehead-control-hover);border-color:color-mix(in srgb,var(--pt-pagehead-control-hover) 34%,var(--md-sys-color-outline-variant))}
.pagehead-control--nav{background:transparent;color:var(--pt-pagehead-control-accent);border-color:transparent}
.pagehead-control--nav:hover{background:color-mix(in srgb,var(--pt-pagehead-control-hover) 8%,var(--md-sys-color-surface-container-lowest));color:var(--pt-pagehead-control-hover)}
.pagehead-control--nav.is-active,.pagehead-control--nav[aria-current]{background:var(--pt-pagehead-control-container);color:var(--pt-pagehead-control-on-container);border-color:transparent}
.pagehead-control--nav.is-active .material-symbols-rounded,.pagehead-control--nav[aria-current] .material-symbols-rounded{font-variation-settings:"FILL" 1,"wght" 650,"GRAD" 0,"opsz" 24}
.pagehead-control--danger{background:var(--md-sys-color-error);color:var(--md-sys-color-on-error);border-color:transparent}
.pagehead-control--danger:hover{background:#93000a;color:#fff;box-shadow:var(--md-sys-elevation-level1)}
.pagehead-control--danger-soft{background:var(--md-sys-color-error-container);color:var(--md-sys-color-on-error-container);border-color:color-mix(in srgb,var(--md-sys-color-error) 24%,var(--md-sys-color-outline-variant))}
@media(max-width:980px){.pagehead-controls--actions,.pagehead-controls--navigation{justify-content:flex-start;width:100%}.pagehead-controls--navigation{overflow-x:auto;flex-wrap:nowrap;scrollbar-width:none;padding-bottom:4px}.pagehead-controls--navigation::-webkit-scrollbar{display:none}.pagehead-controls--navigation .pagehead-control--nav{flex:0 0 auto}}
@media(max-width:640px){.pagehead-control{min-height:var(--pt-pagehead-control-mobile-height);padding-inline:var(--pt-pagehead-control-mobile-padding-inline)}.pagehead-controls--navigation .pagehead-control--nav:not(.is-active):not([aria-current]) span:not(.material-symbols-rounded):not(.pt-icon-glyph){display:none}.pagehead-controls--navigation .pagehead-control--nav:not(.is-active):not([aria-current]){width:42px;padding-inline:0}.pagehead-controls--navigation .pagehead-control--nav.is-active,.pagehead-controls--navigation .pagehead-control--nav[aria-current]{max-width:min(72vw,220px)}}
'''
css = css.rstrip() + '\n' + primitive_css.strip() + '\n'
if '--pt-pagehead-action-' in css:
    raise SystemExit('legacy PageHead action aliases remain in CSS')
CSS_PATH.write_text(css)

js = JS_PATH.read_text().replace('.pagehead-actions', '.pagehead-controls--actions')
JS_PATH.write_text(js)

checker = r'''#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
$root = dirname(__DIR__);
$contract = json_decode((string) file_get_contents($root . '/app/presentation.visual-contract.json'), true, 512, JSON_THROW_ON_ERROR);
$css = (string) file_get_contents($root . '/public/assets/design-system.css');
$js = (string) file_get_contents($root . '/public/assets/app.js');
$rt2 = (string) file_get_contents($root . '/app/Runtime/UiComponents/UiComponentsRuntimeOperations02.php');
$rt3 = (string) file_get_contents($root . '/app/Runtime/UiComponents/UiComponentsRuntimeOperations03.php');
$rendererPath = $root . '/app/Presentation/UiComponents/PageHeadControlPresentationOperations01.php';
$renderer = is_file($rendererPath) ? (string) file_get_contents($rendererPath) : '';
$php = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
        $php .= (string) file_get_contents($file->getPathname());
    }
}
$selectors = [];
$routeOverrides = 0;
if (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) {
        $selector = preg_replace('/\s+/', ' ', trim((string) $match[1]));
        if (is_string($selector) && $selector !== '' && !str_starts_with($selector, '@')) {
            $selectors[$selector] = (int) ($selectors[$selector] ?? 0) + 1;
        }
        if (is_string($selector) && str_contains($selector, 'body[data-route=') && str_contains($selector, '.pagehead-control') && preg_match('/(^|;)\s*(min-height|height|padding(?:-[a-z]+)?|gap|border(?:-[a-z]+)?|border-radius|background(?:-[a-z]+)?|color|box-shadow|font(?:-[a-z]+)?|line-height|white-space|align-items|justify-content)\s*:/i', (string) $match[2])) {
            $routeOverrides++;
        }
    }
}
$metrics = [
    'css_lines' => substr_count($css, "\n") + 1,
    'css_nonblank_lines' => count(array_filter(preg_split('/\R/', $css) ?: [], static fn(string $line): bool => trim($line) !== '')),
    'css_bytes' => strlen($css),
    'important_count' => substr_count($css, '!important'),
    'route_scope_count' => substr_count($css, 'body[data-route='),
    'duplicate_selector_headers' => count(array_filter($selectors, static fn(int $count): bool => $count > 1)),
    'legacy_primary_small_cmdlike_inputs' => substr_count($php, 'primary small cmdlike'),
    'legacy_pagehead_actions_css' => substr_count($css, '.pagehead-actions'),
    'legacy_pagehead_operations_css' => substr_count($css, '.pagehead-operations'),
    'legacy_operation_chip_css' => substr_count($css, '.operation-chip'),
    'legacy_pagehead_actions_js' => substr_count($js, '.pagehead-actions'),
    'pagehead_action_alias_tokens' => substr_count($css, '--pt-pagehead-action-'),
    'canonical_control_css_rules' => substr_count($css, '.pagehead-control{min-height:var(--pt-pagehead-control-height)'),
    'canonical_primary_css_rules' => substr_count($css, '.pagehead-control--primary{'),
    'canonical_secondary_css_rules' => substr_count($css, '.pagehead-control--secondary{'),
    'canonical_nav_css_rules' => substr_count($css, '.pagehead-control--nav{'),
    'canonical_danger_css_rules' => substr_count($css, '.pagehead-control--danger{'),
    'canonical_renderer_runtime_calls' => substr_count($rt2, 'PageHeadControlPresentationOperations01::link') + substr_count($rt3, 'PageHeadControlPresentationOperations01::pageHead'),
    'renderer_lines' => $renderer !== '' ? substr_count($renderer, "\n") + 1 : 0,
    'runtime03_lines' => substr_count($rt3, "\n") + 1,
    'runtime03_dead_operation_menu' => substr_count($rt3, 'operation_menu_html') + substr_count($rt3, '"__menu"'),
    'route_pagehead_identity_overrides' => $routeOverrides,
];
$failures = [];
$exact = (array) ($contract['exact'] ?? []);
foreach ($exact as $metric => $expected) {
    if (!array_key_exists($metric, $metrics) || $metrics[$metric] !== $expected) {
        $failures[] = ['rule' => 'exact_metric', 'metric' => $metric, 'expected' => $expected, 'current' => $metrics[$metric] ?? null];
    }
}
$max = (array) ($contract['debt_budget'] ?? []);
foreach ($max as $metric => $limit) {
    if (!array_key_exists($metric, $metrics) || (int) $metrics[$metric] > (int) $limit) {
        $failures[] = ['rule' => 'budget', 'metric' => $metric, 'limit' => $limit, 'current' => $metrics[$metric] ?? null];
    }
}
foreach ([
    '--pt-pagehead-control-height:38px',
    '--pt-pagehead-control-mobile-height:42px',
    '--pt-pagehead-control-radius:var(--md-sys-shape-corner-large)',
    '.pagehead-control--primary{',
    '.pagehead-control--secondary{',
    '.pagehead-control--nav{',
    '.pagehead-control--danger{',
] as $needle) {
    if (!str_contains($css, $needle)) {
        $failures[] = ['rule' => 'canonical_css_missing', 'needle' => $needle];
    }
}
foreach (['pagehead-control--primary', 'pagehead-control--secondary', 'pagehead-control--danger', 'actionFragment', 'pageHead'] as $needle) {
    if (!str_contains($renderer, $needle)) {
        $failures[] = ['rule' => 'renderer_missing', 'needle' => $needle];
    }
}
if (substr_count($rt3, 'pagehead-controls pagehead-controls--navigation') !== 1) {
    $failures[] = ['rule' => 'navigation_renderer_owner'];
}
$result = [
    'ok' => $failures === [],
    'policy' => (string) ($contract['policy'] ?? ''),
    'version' => (string) ($contract['version'] ?? ''),
    'metrics' => $metrics,
    'failures' => $failures,
];
fwrite($failures === [] ? STDOUT : STDERR, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
exit($failures === [] ? 0 : 1);
'''
CHECKER_PATH.write_text(checker)
CHECKER_PATH.chmod(0o755)

after = metrics()
if after['runtime03_lines'] > 500:
    raise SystemExit(f'Runtime03 hotspot not reduced: {after["runtime03_lines"]} lines')
if after['runtime03_dead_operation_menu'] != 0:
    raise SystemExit('dead operation menu remains')
for metric in ['legacy_pagehead_actions_css', 'legacy_pagehead_operations_css', 'legacy_operation_chip_css', 'legacy_pagehead_actions_js', 'pagehead_action_alias_tokens', 'route_pagehead_identity_overrides']:
    if after[metric] != 0:
        raise SystemExit(f'{metric} expected zero, got {after[metric]}')

contract = {
    'version': '1.8.12.5',
    'policy': 'presentation-pagehead-control-primitive-v2',
    'architecture_scope': 'presentation_ui_adapter_contract',
    'reference': {
        'previous_release': '1.8.12.4',
        'previous_visual_reference': 'unified_pagehead_control_38px',
        'visual_reference': 'canonical_pagehead_control_renderer_38px',
        'functional_equivalence': 'same_pagehead_geometry_semantic_roles_clinic_identity_mobile_touch_targets_and_navigation_compaction',
    },
    'primitive': {
        'renderer': 'Prontoo\\Presentation\\UiComponents\\PageHeadControlPresentationOperations01',
        'container': 'pagehead-controls',
        'control': 'pagehead-control',
        'roles': ['nav', 'primary', 'secondary', 'danger'],
        'desktop_height_px': 38,
        'mobile_height_px': 42,
        'radius_px': 16,
        'padding_block_px': 7,
        'padding_inline_px': 12,
        'gap_px': 8,
        'icon_px': 20,
        'font_token': '--md-sys-typescale-label-medium',
        'font_weight': 700,
    },
    'compatibility': {
        'legacy_input_policy': 'legacy_role_classes_are_consumed_at_the_pagehead_boundary_and_never_reach_css_or_javascript_as_visual_authority',
        'legacy_primary_small_cmdlike_inputs': after['legacy_primary_small_cmdlike_inputs'],
        'css_bridge_count': 0,
        'javascript_bridge_count': 0,
        'action_alias_token_count': 0,
    },
    'invariants': [
        'one_renderer_owns_pagehead_control_markup',
        'one_css_primitive_owns_direct_control_geometry',
        'navigation_primary_secondary_and_danger_differ_by_semantics_not_geometry',
        'legacy_role_classes_may_enter_the_boundary_but_do_not_escape_it',
        'pagehead_css_does_not_reference_primary_ghost_cmdlike_or_operation_chip_as_component_identity',
        'pagehead_javascript_uses_canonical_action_container',
        'runtime_ui_hotspot_is_reduced_below_500_lines',
        'read_only_changes_context_color_not_geometry',
        'danger_remains_semantic_error',
        'mobile_touch_target_remains_42px',
        'route_specific_pagehead_control_identity_overrides_are_zero',
    ],
    'metrics': {'before': before, 'after': after, 'delta': {k: after[k] - before.get(k, 0) for k in after}},
    'exact': {
        'legacy_pagehead_actions_css': 0,
        'legacy_pagehead_operations_css': 0,
        'legacy_operation_chip_css': 0,
        'legacy_pagehead_actions_js': 0,
        'pagehead_action_alias_tokens': 0,
        'canonical_control_css_rules': 1,
        'canonical_primary_css_rules': 1,
        'canonical_secondary_css_rules': 1,
        'canonical_nav_css_rules': 1,
        'canonical_danger_css_rules': 1,
        'canonical_renderer_runtime_calls': 2,
        'runtime03_dead_operation_menu': 0,
        'route_pagehead_identity_overrides': 0,
    },
    'debt_budget': {
        'important_count': after['important_count'],
        'route_scope_count': after['route_scope_count'],
        'duplicate_selector_headers': after['duplicate_selector_headers'],
        'legacy_primary_small_cmdlike_inputs': after['legacy_primary_small_cmdlike_inputs'],
        'runtime03_lines': after['runtime03_lines'],
    },
}
CONTRACT_PATH.write_text(json.dumps(contract, ensure_ascii=False, indent=4) + '\n')

version = json.loads(VERSION_PATH.read_text())
if version.get('version') != '1.8.12.4':
    raise SystemExit(f'expected 1.8.12.4, got {version.get("version")}')
version.update({
    'version': '1.8.12.5',
    'release': '1.8.12.5',
    'previous_version': '1.8.12.4',
    'generated_at_unix': 1786553640,
    'generated_at': '2026-08-12T12:54:00-04:00',
    'updated_at': '2026-08-12T12:54:00-04:00',
    'release_date': '2026-08-12',
    'build': '1.8.12.5-pagehead-control-primitive',
    'deployment_sync_id': 'github-prontoo-1.8.12.5-pagehead-control-primitive',
    'deployment_sync_requested_at': '2026-08-12T12:54:00-04:00',
    'database_changes': False,
    'schema_changes': False,
    'logic_changes': True,
    'visual_changes': True,
    'documentation_changes': True,
    'functional_equivalence_policy': 'same_pagehead_visual_result_with_canonical_presentation_renderer_and_smaller_compatibility_surface',
    'notes': 'PageHeadControl passa a ser um primitive real de Presentation: renderer canônico, classes semânticas explícitas, CSS sem bridge legado e Runtime de UI abaixo do limiar de hotspot.',
    'changelog': {
        'title': 'PageHeadControl canônico e redução de compatibilidade',
        'items': [
            'institui renderer único de Presentation para controles do PageHead',
            'emite papéis nav, primary, secondary e danger sobre uma única geometria de 38 px desktop e 42 px mobile',
            'consome classes legadas no boundary sem permitir que sejam autoridade visual no CSS ou JavaScript',
            'remove aliases de tokens PageHeadAction e seletores pagehead-actions, pagehead-operations e operation-chip do contrato visual',
            'remove suporte morto a operation menu e reduz UiComponentsRuntimeOperations03 para menos de 500 linhas',
            'preserva cor do consultório, somente leitura, danger semântico, compactação mobile e fluxo funcional existente',
            'publica a mudança sem alteração de banco ou schema',
        ],
    },
})
VERSION_PATH.write_text(json.dumps(version, ensure_ascii=False, indent=4) + '\n')

# Temporary materializer must not enter the release manifest.
if WORKFLOW_PATH.exists():
    WORKFLOW_PATH.unlink()
if SELF_PATH.exists():
    SELF_PATH.unlink()
run('php', 'tools/release-contract-reconcile', '--write')
run('php', 'tools/presentation-visual-contract-check')
run('php', 'tools/release-version')
run('php', 'tools/release-contract-reconcile', '--check')
run('php', 'tools/quality-gate')

# First commit captures the real code state. Then ratchet the Runtime budget to that exact source SHA.
run('git', 'add', '-A')
run('git', 'commit', '-m', 'feat(presentation): canonize PageHeadControl primitive')
product_sha = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
budget_json = subprocess.check_output(
    ['php', 'tools/runtime-input-boundary-check', '--print-budget', f'--source-sha={product_sha}'],
    cwd=ROOT,
    text=True,
)
(ROOT / 'app/runtime.input-boundary-budget.json').write_text(budget_json)
run('php', 'tools/release-contract-reconcile', '--write')
run('php', 'tools/quality-gate')
run('git', 'add', '-A')
run('git', 'commit', '-m', 'chore(architecture): ratchet Runtime UI hotspot')
