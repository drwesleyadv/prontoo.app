#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';

const root = 'app/Presentation/Styles/';
const targets = {
  clinic: 'tokens/clinic.css',
  material: 'tokens/material.css',
  semantic: 'tokens/semantic.css'
};
const tokenSources = Object.values(targets);
const legacyBytes = {};
const legacy = {};
for (const [target, relative] of Object.entries(targets)) {
  const css = fs.readFileSync(root + relative, 'utf8');
  legacyBytes[relative] = Buffer.byteLength(css);
  legacy[target] = {
    sha256: crypto.createHash('sha256').update(css).digest('hex'),
    base64: Buffer.from(css).toString('base64')
  };
}
const runtimePath = 'app/Domain/ClinicConfig/ClinicConfigDomainOperations02.php';
const runtime = fs.readFileSync(runtimePath, 'utf8');
const dynamic = new Set([...runtime.matchAll(/--([a-z0-9-]+):/gi)].map(match => `--${match[1]}`));
const rootDeclarations = [];
for (const [target, relative] of Object.entries(targets)) {
  const css = fs.readFileSync(root + relative, 'utf8');
  const rootRe = /:root\s*\{([^{}]*)\}/g;
  let block;
  while ((block = rootRe.exec(css))) {
    const propRe = /(--[a-z0-9-]+)\s*:\s*([^;]+);/gi;
    let prop;
    while ((prop = propRe.exec(block[1]))) {
      let value = prop[2].trim();
      const important = /!important\s*$/i.test(value);
      value = value.replace(/!important\s*$/i, '').trim();
      rootDeclarations.push({ target, name: prop[1], value, important });
    }
  }
}
let changed = true;
while (changed) {
  changed = false;
  for (const item of rootDeclarations) {
    if (dynamic.has(item.name)) continue;
    const deps = [...item.value.matchAll(/var\((--[a-z0-9-]+)/gi)].map(match => match[1]);
    if (deps.some(dep => dep.startsWith('--clinic-') || dynamic.has(dep))) {
      dynamic.add(item.name);
      changed = true;
    }
  }
}
let bridgeOrder = 10000;
const entries = rootDeclarations
  .filter(item => dynamic.has(item.name))
  .map(item => ({
    target: item.target,
    selector: 'body',
    name: item.name,
    value: item.value,
    important: item.important,
    order: bridgeOrder++
  }));
const bridge = {
  schema: 'prontoo-design-token-css-bridge-v2',
  policy: {
    legacy_abi: 'generated_frozen_payload',
    authored_css_is_not_source_of_truth: true,
    runtime_sensitive_aliases_resolve_on_body: true
  },
  legacy,
  entries
};
fs.mkdirSync('design/tokens', { recursive: true });
fs.writeFileSync('design/tokens/css-bridge.json', JSON.stringify(bridge, null, 2) + '\n');

const color = hex => {
  const h = hex.replace('#', '').toLowerCase();
  return {
    colorSpace: 'srgb',
    components: [parseInt(h.slice(0, 2), 16) / 255, parseInt(h.slice(2, 4), 16) / 255, parseInt(h.slice(4, 6), 16) / 255],
    hex: `#${h}`
  };
};
const meta = (cssName, target = 'clinic', selector = ':root', order = -10000, cssValue = null) => ({
  'com.prontoo': { cssName, target, selector, order, ...(cssValue ? { cssValue } : {}) }
});
const token = (value, cssName, target = 'clinic', selector = ':root', order = -10000, cssValue = null) => ({
  $type: 'color', $value: value, $extensions: meta(cssName, target, selector, order, cssValue)
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
  accent: token('{ref.color.brand.default}', '--pt-sys-color-accent', 'clinic', 'body', -9000, 'var(--pt-theme-accent,var(--clinic-accent,var(--pt-ref-color-brand-default)))'),
  accentStrong: token('{ref.color.brand.strong}', '--pt-sys-color-accent-strong', 'clinic', 'body', -8999, 'var(--pt-theme-accent-strong,var(--clinic-accent-strong,var(--clinic-accent-dark,var(--pt-ref-color-brand-strong))))'),
  onAccent: token('{ref.color.neutral.white}', '--pt-sys-color-on-accent', 'clinic', 'body', -8998, 'var(--pt-theme-on-accent,var(--clinic-on-accent,var(--pt-ref-color-neutral-white)))'),
  accentContainer: token('{ref.color.brand.soft}', '--pt-sys-color-accent-container', 'clinic', 'body', -8997, 'var(--pt-theme-accent-soft,var(--clinic-accent-soft,var(--pt-ref-color-brand-soft)))'),
  accentSubtle: token('{ref.color.brand.subtle}', '--pt-sys-color-accent-subtle', 'clinic', 'body', -8996, 'var(--pt-theme-accent-subtle,var(--clinic-accent-subtle,var(--pt-ref-color-brand-subtle)))'),
  success: token('{ref.color.semantic.success}', '--pt-sys-color-success'),
  successContainer: token('{ref.color.semantic.successContainer}', '--pt-sys-color-success-container'),
  onSuccessContainer: token('{ref.color.semantic.successInk}', '--pt-sys-color-on-success-container'),
  warning: token('{ref.color.semantic.warning}', '--pt-sys-color-warning'),
  warningContainer: token('{ref.color.semantic.warningContainer}', '--pt-sys-color-warning-container'),
  onWarningContainer: token('{ref.color.semantic.warningInk}', '--pt-sys-color-on-warning-container'),
  info: token('{ref.color.semantic.info}', '--pt-sys-color-info'),
  infoContainer: token('{ref.color.semantic.infoContainer}', '--pt-sys-color-info-container'),
  onInfoContainer: token('{ref.color.semantic.infoInk}', '--pt-sys-color-on-info-container'),
  error: token('{ref.color.semantic.error}', '--pt-sys-color-error'),
  errorContainer: token('{ref.color.semantic.errorContainer}', '--pt-sys-color-error-container'),
  onErrorContainer: token('{ref.color.semantic.errorInk}', '--pt-sys-color-on-error-container')
} } };
const component = { cmp: {
  pagehead: {
    accent: token('{sys.color.accent}', '--pt-cmp-pagehead-accent', 'clinic', 'body', -8000, 'var(--clinic-identity-accent,var(--pt-sys-color-accent))'),
    hover: token('{sys.color.accentStrong}', '--pt-cmp-pagehead-hover', 'clinic', 'body', -7999, 'var(--clinic-identity-hover,var(--pt-sys-color-accent-strong))'),
    onAccent: token('{sys.color.onAccent}', '--pt-cmp-pagehead-on-accent', 'clinic', 'body', -7998, 'var(--clinic-identity-on-accent,var(--pt-sys-color-on-accent))')
  },
  icon: { foreground: token('{sys.color.accent}', '--pt-cmp-icon-foreground', 'clinic', 'body', -7997, 'var(--pt-sys-color-accent)') }
} };
const themeToken = (hex, name, selector, order) => token(color(hex), name, 'material', selector, order);
const themes = { theme: {
  global: {
    accent: themeToken('#238763', '--pt-theme-accent', 'body.scope-global', -7000),
    strong: themeToken('#105e44', '--pt-theme-accent-strong', 'body.scope-global', -6999),
    on: themeToken('#ffffff', '--pt-theme-on-accent', 'body.scope-global', -6998)
  },
  public: {
    accent: themeToken('#2d6f55', '--pt-theme-accent', 'body.public', -6980),
    strong: themeToken('#1d583f', '--pt-theme-accent-strong', 'body.public', -6979),
    on: themeToken('#ffffff', '--pt-theme-on-accent', 'body.public', -6978)
  }
} };
fs.writeFileSync('design/tokens/reference.tokens.json', JSON.stringify(reference, null, 2) + '\n');
fs.writeFileSync('design/tokens/system.tokens.json', JSON.stringify(system, null, 2) + '\n');
fs.writeFileSync('design/tokens/component.tokens.json', JSON.stringify(component, null, 2) + '\n');
fs.writeFileSync('design/tokens/themes.tokens.json', JSON.stringify(themes, null, 2) + '\n');

let php = runtime;
const anchor = '        return [\n            "brand" => $base,';
if (!php.includes(anchor)) throw new Error('clinic theme return anchor missing');
php = php.replace(anchor, `        $css .=\n            "--pt-theme-accent:" . $base .\n            ";--pt-theme-accent-strong:" . $strong .\n            ";--pt-theme-on-accent:" . $on .\n            ";";\n` + anchor);
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

const manifestPath = root + 'styles.manifest.json';
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
manifest.token_source_model = 'dtcg-2025.10-style-dictionary-v5';
manifest.token_source_root = 'design/tokens';
manifest.token_compiler = 'tools/design-tokens-build.mjs';
manifest.token_generated_sources = tokenSources;
for (const source of manifest.sources) {
  const css = fs.readFileSync(root + source.path, 'utf8');
  source.sha256 = crypto.createHash('sha256').update(css).digest('hex');
  source.bytes = Buffer.byteLength(css);
  source.important_count = (css.match(/!important/g) || []).length;
  source.route_scope_count = (css.match(/body\[data-route=/g) || []).length;
}
const sequence = manifest.sequence.map(segment => ({ ...segment }));
for (const relative of tokenSources) {
  const css = fs.readFileSync(root + relative, 'utf8');
  const total = Buffer.byteLength(css);
  const start = legacyBytes[relative];
  if (total <= start) throw new Error(`generated tail missing ${relative}`);
  const tail = Buffer.from(css).subarray(start).toString();
  sequence.push({
    source: relative,
    offset: start,
    bytes: Buffer.byteLength(tail),
    sha256: crypto.createHash('sha256').update(tail).digest('hex'),
    important_count: (tail.match(/!important/g) || []).length,
    route_scope_count: (tail.match(/body\[data-route=/g) || []).length
  });
}
sequence.forEach((segment, index) => { segment.order = index + 1; });
manifest.sequence = sequence;
manifest.sequence_segment_count = sequence.length;
let built = '';
for (const segment of sequence) {
  const css = fs.readFileSync(root + segment.source, 'utf8');
  const slice = Buffer.from(css).subarray(segment.offset, segment.offset + segment.bytes).toString();
  if (Buffer.byteLength(slice) !== segment.bytes) throw new Error(`sequence slice outside source ${segment.source}`);
  const hash = crypto.createHash('sha256').update(slice).digest('hex');
  if (segment.sha256 !== hash) segment.sha256 = hash;
  built += slice;
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
