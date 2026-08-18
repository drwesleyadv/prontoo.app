#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';

const roots = {
  clinic: 'app/Presentation/Styles/tokens/clinic.css',
  material: 'app/Presentation/Styles/tokens/material.css',
  semantic: 'app/Presentation/Styles/tokens/semantic.css'
};
const tokenSources = Object.values(roots).map(value => value.replace('app/Presentation/Styles/', ''));
const tokenSet = new Set(tokenSources);
const bridge = { schema: 'prontoo-design-token-css-bridge-v1', entries: [], rules: [] };
const canonical = {
  '--md-ref-palette-primary40':'var(--pt-sys-color-accent)',
  '--md-ref-palette-primary30':'var(--pt-sys-color-accent-strong)',
  '--md-sys-color-primary':'var(--pt-sys-color-accent)',
  '--md-sys-color-on-primary':'var(--pt-sys-color-on-accent)',
  '--md-sys-color-primary-container':'var(--pt-sys-color-accent-container)',
  '--md-sys-color-on-primary-container':'var(--pt-sys-color-on-accent-container)',
  '--md-sys-color-secondary':'var(--pt-sys-color-secondary)',
  '--md-sys-color-on-secondary':'var(--pt-sys-color-on-secondary)',
  '--md-sys-color-secondary-container':'var(--pt-sys-color-secondary-container)',
  '--md-sys-color-on-secondary-container':'var(--pt-sys-color-on-secondary-container)',
  '--md-sys-color-tertiary':'var(--pt-sys-color-tertiary)',
  '--md-sys-color-on-tertiary':'var(--pt-sys-color-on-tertiary)',
  '--md-sys-color-tertiary-container':'var(--pt-sys-color-tertiary-container)',
  '--md-sys-color-on-tertiary-container':'var(--pt-sys-color-on-tertiary-container)',
  '--md-sys-color-error':'var(--pt-sys-color-error)',
  '--md-sys-color-on-error':'var(--pt-sys-color-on-error)',
  '--md-sys-color-error-container':'var(--pt-sys-color-error-container)',
  '--md-sys-color-on-error-container':'var(--pt-sys-color-on-error-container)',
  '--brand':'var(--pt-sys-color-accent)',
  '--brand-dark':'var(--pt-sys-color-accent-strong)',
  '--brand-soft':'var(--pt-sys-color-accent-container)',
  '--brand-soft-2':'var(--pt-sys-color-accent-subtle)',
  '--on-brand':'var(--pt-sys-color-on-accent)',
  '--pt-color-success':'var(--pt-sys-color-success)',
  '--pt-color-on-success':'var(--pt-sys-color-on-success)',
  '--pt-color-success-container':'var(--pt-sys-color-success-container)',
  '--pt-color-on-success-container':'var(--pt-sys-color-on-success-container)',
  '--pt-color-warning':'var(--pt-sys-color-warning)',
  '--pt-color-on-warning':'var(--pt-sys-color-on-warning)',
  '--pt-color-warning-container':'var(--pt-sys-color-warning-container)',
  '--pt-color-on-warning-container':'var(--pt-sys-color-on-warning-container)',
  '--pt-color-info':'var(--pt-sys-color-info)',
  '--pt-color-info-container':'var(--pt-sys-color-info-container)',
  '--pt-color-on-info-container':'var(--pt-sys-color-on-info-container)',
  '--pt-pagehead-control-accent':'var(--pt-cmp-pagehead-accent)',
  '--pt-pagehead-control-on-accent':'var(--pt-cmp-pagehead-on-accent)',
  '--pt-pagehead-control-hover':'var(--pt-cmp-pagehead-hover)',
  '--pt-pagehead-control-container':'var(--pt-cmp-pagehead-container)',
  '--pt-pagehead-control-on-container':'var(--pt-cmp-pagehead-on-container)',
  '--pt-ds-accent':'var(--pt-sys-color-accent)'
};
const runtimePath = 'app/Domain/ClinicConfig/ClinicConfigDomainOperations02.php';
const runtime = fs.readFileSync(runtimePath, 'utf8');
const dynamic = new Set([...runtime.matchAll(/--([a-z0-9-]+):/gi)].map(match => `--${match[1]}`));
const declarations = [];
let order = 10000;
for (const [target, file] of Object.entries(roots)) {
  const css = fs.readFileSync(file, 'utf8');
  const blockRe = /([^{}]+)\{([^{}]*)\}/g;
  let block;
  while ((block = blockRe.exec(css))) {
    const selector = block[1].trim();
    const body = block[2];
    const propRe = /(--[a-z0-9-]+)\s*:\s*([^;]+);/gi;
    const ranges = [];
    let prop;
    while ((prop = propRe.exec(body))) {
      let value = prop[2].trim();
      const important = /!important\s*$/i.test(value);
      value = value.replace(/!important\s*$/i, '').trim();
      declarations.push({ target, selector, name: prop[1], value, important, order: order++ });
      ranges.push([prop.index, prop.index + prop[0].length]);
    }
    let remainder = '';
    let cursor = 0;
    for (const [start, end] of ranges) {
      remainder += body.slice(cursor, start);
      cursor = end;
    }
    remainder += body.slice(cursor);
    const cleaned = remainder.replace(/\s+/g, ' ').trim().replace(/^;+|;+$/g, '').trim();
    if (cleaned) bridge.rules.push({ target, selector, declarations: cleaned, order: order++ });
  }
}
let changed = true;
while (changed) {
  changed = false;
  for (const item of declarations.filter(value => value.selector === ':root')) {
    if (dynamic.has(item.name)) continue;
    const deps = [...item.value.matchAll(/var\((--[a-z0-9-]+)/gi)].map(match => match[1]);
    if (deps.some(dep => dep.startsWith('--clinic-') || dynamic.has(dep))) {
      dynamic.add(item.name);
      changed = true;
    }
  }
}
for (const item of declarations) {
  bridge.entries.push({
    ...item,
    selector: item.selector === ':root' && dynamic.has(item.name) ? 'body' : item.selector,
    value: canonical[item.name] || item.value
  });
}
bridge.policy = {
  source: 'migrated-from-pre-dtcg-token-css',
  authored_css_is_not_source_of_truth: true,
  runtime_sensitive_aliases_resolve_on_body: true
};
fs.mkdirSync('design/tokens', { recursive: true });
fs.writeFileSync('design/tokens/css-bridge.json', JSON.stringify(bridge, null, 2) + '\n');

const color = hex => {
  const h = hex.replace('#', '').toLowerCase();
  const r = parseInt(h.slice(0, 2), 16);
  const g = parseInt(h.slice(2, 4), 16);
  const b = parseInt(h.slice(4, 6), 16);
  return { colorSpace: 'srgb', components: [r / 255, g / 255, b / 255], hex: `#${h}` };
};
const ext = (cssName, target = 'clinic', selector = ':root', tokenOrder = -10000, cssValue = null, important = false) => ({
  'com.prontoo': { cssName, target, selector, order: tokenOrder, ...(cssValue ? { cssValue } : {}), ...(important ? { important: true } : {}) }
});
const token = (value, cssName, target = 'clinic', selector = ':root', tokenOrder = -10000, cssValue = null, important = false) => ({
  $type: 'color', $value: value, $extensions: ext(cssName, target, selector, tokenOrder, cssValue, important)
});
const reference = { ref: { color: {
  brand: {
    default: token(color('#2f6652'), '--pt-ref-color-brand-default'),
    strong: token(color('#1d583f'), '--pt-ref-color-brand-strong'),
    soft: token(color('#d9f0df'), '--pt-ref-color-brand-soft'),
    subtle: token(color('#f6fbf7'), '--pt-ref-color-brand-subtle'),
    global: token(color('#238763'), '--pt-ref-color-brand-global'),
    globalStrong: token(color('#105e44'), '--pt-ref-color-brand-global-strong'),
    public: token(color('#2d6f55'), '--pt-ref-color-brand-public')
  },
  neutral: { white: token(color('#ffffff'), '--pt-ref-color-neutral-white') },
  semantic: {
    success: token(color('#146c43'), '--pt-ref-color-success'),
    successContainer: token(color('#d8f5e5'), '--pt-ref-color-success-container'),
    successInk: token(color('#05391f'), '--pt-ref-color-success-ink'),
    warning: token(color('#865900'), '--pt-ref-color-warning'),
    warningContainer: token(color('#ffefc2'), '--pt-ref-color-warning-container'),
    warningInk: token(color('#3d2b00'), '--pt-ref-color-warning-ink'),
    info: token(color('#286b73'), '--pt-ref-color-info'),
    infoContainer: token(color('#d6f4f7'), '--pt-ref-color-info-container'),
    infoInk: token(color('#063b42'), '--pt-ref-color-info-ink'),
    error: token(color('#ba1a1a'), '--pt-ref-color-error'),
    errorContainer: token(color('#ffdad6'), '--pt-ref-color-error-container'),
    errorInk: token(color('#410002'), '--pt-ref-color-error-ink')
  }
} } };
const system = { sys: { color: {
  accent: token('{ref.color.brand.default}', '--pt-sys-color-accent', 'clinic', 'body', -9000, 'var(--pt-theme-accent,var(--pt-ref-color-brand-default))'),
  accentStrong: token('{ref.color.brand.strong}', '--pt-sys-color-accent-strong', 'clinic', 'body', -8999, 'var(--pt-theme-accent-strong,var(--pt-ref-color-brand-strong))'),
  onAccent: token('{ref.color.neutral.white}', '--pt-sys-color-on-accent', 'clinic', 'body', -8998, 'var(--pt-theme-on-accent,var(--pt-ref-color-neutral-white))'),
  accentContainer: token('{ref.color.brand.soft}', '--pt-sys-color-accent-container', 'clinic', 'body', -8997, 'var(--pt-theme-accent-soft,var(--pt-ref-color-brand-soft))'),
  onAccentContainer: token('{ref.color.brand.strong}', '--pt-sys-color-on-accent-container', 'clinic', 'body', -8996, 'var(--pt-theme-accent-strong,var(--pt-ref-color-brand-strong))'),
  accentSubtle: token('{ref.color.brand.subtle}', '--pt-sys-color-accent-subtle', 'clinic', 'body', -8995, 'var(--pt-theme-accent-subtle,var(--pt-ref-color-brand-subtle))'),
  secondary: token('{ref.color.brand.strong}', '--pt-sys-color-secondary', 'clinic', 'body', -8994, 'var(--pt-theme-accent-strong,var(--pt-ref-color-brand-strong))'),
  onSecondary: token('{ref.color.neutral.white}', '--pt-sys-color-on-secondary', 'clinic', 'body', -8993, 'var(--pt-theme-on-accent,var(--pt-ref-color-neutral-white))'),
  secondaryContainer: token('{ref.color.brand.subtle}', '--pt-sys-color-secondary-container', 'clinic', 'body', -8992, 'var(--pt-theme-accent-subtle,var(--pt-ref-color-brand-subtle))'),
  onSecondaryContainer: token('{ref.color.brand.strong}', '--pt-sys-color-on-secondary-container', 'clinic', 'body', -8991, 'var(--pt-theme-accent-strong,var(--pt-ref-color-brand-strong))'),
  tertiary: token('{ref.color.brand.default}', '--pt-sys-color-tertiary', 'clinic', 'body', -8990, 'var(--pt-theme-accent-hover,var(--pt-theme-accent,var(--pt-ref-color-brand-default)))'),
  onTertiary: token('{ref.color.neutral.white}', '--pt-sys-color-on-tertiary', 'clinic', 'body', -8989, 'var(--pt-theme-on-accent,var(--pt-ref-color-neutral-white))'),
  tertiaryContainer: token('{ref.color.brand.soft}', '--pt-sys-color-tertiary-container', 'clinic', 'body', -8988, 'var(--pt-theme-accent-muted,var(--pt-ref-color-brand-soft))'),
  onTertiaryContainer: token('{ref.color.brand.strong}', '--pt-sys-color-on-tertiary-container', 'clinic', 'body', -8987, 'var(--pt-theme-accent-strong,var(--pt-ref-color-brand-strong))'),
  success: token('{ref.color.semantic.success}', '--pt-sys-color-success'),
  onSuccess: token('{ref.color.neutral.white}', '--pt-sys-color-on-success'),
  successContainer: token('{ref.color.semantic.successContainer}', '--pt-sys-color-success-container'),
  onSuccessContainer: token('{ref.color.semantic.successInk}', '--pt-sys-color-on-success-container'),
  warning: token('{ref.color.semantic.warning}', '--pt-sys-color-warning'),
  onWarning: token('{ref.color.neutral.white}', '--pt-sys-color-on-warning'),
  warningContainer: token('{ref.color.semantic.warningContainer}', '--pt-sys-color-warning-container'),
  onWarningContainer: token('{ref.color.semantic.warningInk}', '--pt-sys-color-on-warning-container'),
  info: token('{ref.color.semantic.info}', '--pt-sys-color-info'),
  infoContainer: token('{ref.color.semantic.infoContainer}', '--pt-sys-color-info-container'),
  onInfoContainer: token('{ref.color.semantic.infoInk}', '--pt-sys-color-on-info-container'),
  error: token('{ref.color.semantic.error}', '--pt-sys-color-error'),
  onError: token('{ref.color.neutral.white}', '--pt-sys-color-on-error'),
  errorContainer: token('{ref.color.semantic.errorContainer}', '--pt-sys-color-error-container'),
  onErrorContainer: token('{ref.color.semantic.errorInk}', '--pt-sys-color-on-error-container')
} } };
const component = { cmp: { pagehead: {
  accent: token('{sys.color.accent}', '--pt-cmp-pagehead-accent', 'clinic', 'body', -8000, 'var(--pt-theme-identity-accent,var(--pt-sys-color-accent))'),
  onAccent: token('{sys.color.onAccent}', '--pt-cmp-pagehead-on-accent', 'clinic', 'body', -7999, 'var(--pt-theme-identity-on-accent,var(--pt-sys-color-on-accent))'),
  hover: token('{sys.color.accentStrong}', '--pt-cmp-pagehead-hover', 'clinic', 'body', -7998, 'var(--pt-theme-identity-hover,var(--pt-sys-color-accent-strong))'),
  container: token('{sys.color.accentContainer}', '--pt-cmp-pagehead-container', 'clinic', 'body', -7997, 'color-mix(in srgb,var(--pt-cmp-pagehead-accent) 15%,var(--md-sys-color-surface-container-lowest))'),
  onContainer: token('{sys.color.accentStrong}', '--pt-cmp-pagehead-on-container', 'clinic', 'body', -7996, 'color-mix(in srgb,var(--pt-cmp-pagehead-accent) 74%,#102018)')
} } };
const themeToken = (name, hex, selector, tokenOrder) => token(color(hex), name, 'material', selector, tokenOrder, null, true);
const themes = { theme: {
  global: {
    accent: themeToken('--pt-theme-accent', '#238763', 'body.scope-global', -7000),
    strong: themeToken('--pt-theme-accent-strong', '#105e44', 'body.scope-global', -6999),
    hover: themeToken('--pt-theme-accent-hover', '#105e44', 'body.scope-global', -6998),
    soft: themeToken('--pt-theme-accent-soft', '#dff3ea', 'body.scope-global', -6997),
    subtle: themeToken('--pt-theme-accent-subtle', '#f1fbf6', 'body.scope-global', -6996),
    muted: themeToken('--pt-theme-accent-muted', '#dff3ea', 'body.scope-global', -6995),
    on: themeToken('--pt-theme-on-accent', '#ffffff', 'body.scope-global', -6994),
    identity: themeToken('--pt-theme-identity-accent', '#238763', 'body.scope-global', -6993),
    identityHover: themeToken('--pt-theme-identity-hover', '#105e44', 'body.scope-global', -6992),
    identityOn: themeToken('--pt-theme-identity-on-accent', '#ffffff', 'body.scope-global', -6991)
  },
  public: {
    accent: themeToken('--pt-theme-accent', '#2d6f55', 'body.public', -6980),
    strong: themeToken('--pt-theme-accent-strong', '#1d583f', 'body.public', -6979),
    hover: themeToken('--pt-theme-accent-hover', '#1d583f', 'body.public', -6978),
    soft: themeToken('--pt-theme-accent-soft', '#d9f0df', 'body.public', -6977),
    subtle: themeToken('--pt-theme-accent-subtle', '#f6fbf7', 'body.public', -6976),
    muted: themeToken('--pt-theme-accent-muted', '#d9f0df', 'body.public', -6975),
    on: themeToken('--pt-theme-on-accent', '#ffffff', 'body.public', -6974),
    identity: themeToken('--pt-theme-identity-accent', '#2d6f55', 'body.public', -6973),
    identityHover: themeToken('--pt-theme-identity-hover', '#1d583f', 'body.public', -6972),
    identityOn: themeToken('--pt-theme-identity-on-accent', '#ffffff', 'body.public', -6971)
  }
} };
fs.writeFileSync('design/tokens/reference.tokens.json', JSON.stringify(reference, null, 2) + '\n');
fs.writeFileSync('design/tokens/system.tokens.json', JSON.stringify(system, null, 2) + '\n');
fs.writeFileSync('design/tokens/component.tokens.json', JSON.stringify(component, null, 2) + '\n');
fs.writeFileSync('design/tokens/themes.tokens.json', JSON.stringify(themes, null, 2) + '\n');

let php = runtime;
const needle = '        return [\n            "brand" => $base,';
if (!php.includes(needle)) throw new Error('runtime theme anchor missing');
const runtimeBlock = `        $css .=\n            "--pt-theme-identity-accent:" . $base .\n            ";--pt-theme-identity-hover:" . $hover .\n            ";--pt-theme-identity-on-accent:" . $on .\n            ";--pt-theme-accent:" . $base .\n            ";--pt-theme-accent-strong:" . $strong .\n            ";--pt-theme-accent-hover:" . $hover .\n            ";--pt-theme-accent-soft:" . $soft .\n            ";--pt-theme-accent-subtle:" . $subtle .\n            ";--pt-theme-accent-muted:" . $muted .\n            ";--pt-theme-on-accent:" . $on .\n            ";";\n`;
php = php.replace(needle, runtimeBlock + needle);
fs.writeFileSync(runtimePath, php);
const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));
pkg.scripts = {
  ...(pkg.scripts || {}),
  'tokens:build': 'node tools/design-tokens-build.mjs --write',
  'tokens:check': 'node tools/design-tokens-build.mjs --check',
  'tokens:contract': 'node tools/design-tokens-contract.mjs'
};
fs.writeFileSync('package.json', JSON.stringify(pkg, null, 4) + '\n');
execFileSync(process.execPath, ['tools/design-tokens-build.mjs', '--write'], { stdio: 'inherit' });

const manifestPath = 'app/Presentation/Styles/styles.manifest.json';
const sourceRoot = 'app/Presentation/Styles/';
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
manifest.token_source_model = 'dtcg-2025.10-style-dictionary-v5';
manifest.token_source_root = 'design/tokens';
manifest.token_compiler = 'tools/design-tokens-build.mjs';
manifest.token_generated_sources = tokenSources;
for (const source of manifest.sources) {
  const css = fs.readFileSync(sourceRoot + source.path, 'utf8');
  source.sha256 = crypto.createHash('sha256').update(css).digest('hex');
  source.bytes = Buffer.byteLength(css);
  source.important_count = (css.match(/!important/g) || []).length;
  source.route_scope_count = (css.match(/body\[data-route=/g) || []).length;
}
const inserted = new Set();
const sequence = [];
for (const segment of manifest.sequence) {
  if (tokenSet.has(segment.source)) {
    if (inserted.has(segment.source)) continue;
    inserted.add(segment.source);
    const css = fs.readFileSync(sourceRoot + segment.source, 'utf8');
    sequence.push({
      source: segment.source,
      offset: 0,
      bytes: Buffer.byteLength(css),
      sha256: crypto.createHash('sha256').update(css).digest('hex'),
      important_count: (css.match(/!important/g) || []).length,
      route_scope_count: (css.match(/body\[data-route=/g) || []).length
    });
    continue;
  }
  const css = fs.readFileSync(sourceRoot + segment.source, 'utf8');
  const slice = Buffer.from(css).subarray(segment.offset, segment.offset + segment.bytes).toString();
  if (Buffer.byteLength(slice) !== segment.bytes) throw new Error(`sequence slice outside source ${segment.source}`);
  sequence.push({
    ...segment,
    sha256: crypto.createHash('sha256').update(slice).digest('hex'),
    important_count: (slice.match(/!important/g) || []).length,
    route_scope_count: (slice.match(/body\[data-route=/g) || []).length
  });
}
for (const source of tokenSources) if (!inserted.has(source)) throw new Error(`missing token sequence source ${source}`);
sequence.forEach((segment, index) => { segment.order = index + 1; });
manifest.sequence = sequence;
manifest.sequence_segment_count = sequence.length;
let built = '';
for (const segment of sequence) {
  const css = fs.readFileSync(sourceRoot + segment.source, 'utf8');
  built += Buffer.from(css).subarray(segment.offset, segment.offset + segment.bytes).toString();
}
manifest.artifact_contract = {
  ...(manifest.artifact_contract || {}),
  sha256: crypto.createHash('sha256').update(built).digest('hex'),
  bytes: Buffer.byteLength(built),
  deterministic_source_required: true
};
fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 4) + '\n');
fs.writeFileSync('public/assets/presentation.css', built);
execFileSync('php', ['tools/presentation-css-build', '--lint'], { stdio: 'inherit' });
execFileSync('php', ['tools/presentation-css-build', '--check'], { stdio: 'inherit' });
