#!/usr/bin/env node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import StyleDictionary from 'style-dictionary';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const mode = process.argv[2] || '--check';
if (!['--check', '--write'].includes(mode)) {
  process.stderr.write('design-tokens-build: use --check or --write\n');
  process.exit(2);
}

const sourceGlob = path.join(root, 'design/tokens/**/*.tokens.json');
const bridgePath = path.join(root, 'design/tokens/css-bridge.json');
const targets = {
  clinic: 'app/Presentation/Styles/tokens/clinic.css',
  material: 'app/Presentation/Styles/tokens/material.css',
  semantic: 'app/Presentation/Styles/tokens/semantic.css'
};
if (!fs.existsSync(bridgePath)) {
  process.stderr.write('design-tokens-build: css bridge missing\n');
  process.exit(1);
}
const bridge = JSON.parse(fs.readFileSync(bridgePath, 'utf8'));
if (!bridge || bridge.schema !== 'prontoo-design-token-css-bridge-v1' || !Array.isArray(bridge.entries) || !Array.isArray(bridge.rules || [])) {
  process.stderr.write('design-tokens-build: invalid css bridge\n');
  process.exit(1);
}

const vendorMeta = token => token?.original?.$extensions?.['com.prontoo'] ?? token?.$extensions?.['com.prontoo'] ?? {};
const originalValue = token => token?.original?.$value ?? token?.$value ?? token?.original?.value ?? token?.value;
const transformedValue = token => token?.value ?? token?.$value;
const cssName = token => {
  const meta = vendorMeta(token);
  const value = String(meta.cssName || '').trim();
  if (!/^--[a-z0-9-]+$/i.test(value)) throw new Error(`invalid cssName for ${token.path?.join('.') || token.name}`);
  return value;
};
const cssLiteral = value => {
  if (typeof value === 'string' || typeof value === 'number') return String(value);
  throw new Error(`unsupported transformed token value ${JSON.stringify(value)}`);
};
const tokenPath = token => Array.isArray(token.path) ? token.path.join('.') : String(token.name || '');

StyleDictionary.registerFormat({
  name: 'prontoo/scoped-css-variables',
  format: ({ dictionary, options }) => {
    const target = String(options.target || '');
    if (!Object.hasOwn(targets, target)) throw new Error(`unknown token target ${target}`);
    const all = [...dictionary.allTokens];
    const names = new Map(all.map(token => [tokenPath(token), cssName(token)]));
    const declarations = [];
    for (const token of all) {
      const meta = vendorMeta(token);
      if (String(meta.target || '') !== target) continue;
      const selector = String(meta.selector || ':root').trim();
      const raw = originalValue(token);
      let value = String(meta.cssValue || '').trim();
      if (!value) {
        if (typeof raw === 'string' && /^\{[^{}]+\}$/.test(raw.trim())) {
          const ref = raw.trim().slice(1, -1);
          const refName = names.get(ref);
          if (!refName) throw new Error(`unknown token reference ${ref}`);
          value = `var(${refName})`;
        } else {
          value = cssLiteral(transformedValue(token));
        }
      }
      value = value.replace(/\{([^{}]+)\}/g, (_, ref) => {
        const refName = names.get(ref);
        if (!refName) throw new Error(`unknown embedded token reference ${ref}`);
        return `var(${refName})`;
      });
      declarations.push({
        selector,
        name: cssName(token),
        value,
        important: meta.important === true,
        order: Number(meta.order || 0)
      });
    }
    for (const entry of bridge.entries) {
      if (String(entry.target || '') !== target) continue;
      declarations.push({
        selector: String(entry.selector || ':root'),
        name: String(entry.name || ''),
        value: String(entry.value || ''),
        important: entry.important === true,
        order: Number(entry.order || 0)
      });
    }
    declarations.sort((a, b) => a.order - b.order || a.name.localeCompare(b.name));
    const selectorOrder = [];
    const grouped = new Map();
    for (const declaration of declarations) {
      if (!/^--[a-z0-9-]+$/i.test(declaration.name)) throw new Error(`invalid bridge token ${declaration.name}`);
      if (!grouped.has(declaration.selector)) {
        grouped.set(declaration.selector, []);
        selectorOrder.push(declaration.selector);
      }
      grouped.get(declaration.selector).push(declaration);
    }
    const blocks = selectorOrder.map(selector => {
      const lines = grouped.get(selector).map(item => `  ${item.name}:${item.value}${item.important ? '!important' : ''};`).join('\n');
      return `${selector}{\n${lines}\n}`;
    });
    const rawRules = (bridge.rules || [])
      .filter(rule => String(rule.target || '') === target)
      .sort((a, b) => Number(a.order || 0) - Number(b.order || 0));
    for (const rule of rawRules) {
      const selector = String(rule.selector || '').trim();
      const declarationsText = String(rule.declarations || '').trim();
      if (!selector || !declarationsText || /--[a-z0-9-]+\s*:/i.test(declarationsText)) throw new Error(`invalid non-token bridge rule for ${target}`);
      blocks.push(`${selector}{${declarationsText}}`);
    }
    return blocks.join('\n\n') + '\n';
  }
});

const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'prontoo-design-tokens-'));
try {
  const config = {
    source: [sourceGlob],
    usesDtcg: true,
    platforms: {
      css: {
        transformGroup: 'css',
        buildPath: `${tempRoot}/`,
        files: Object.entries(targets).map(([target, destination]) => ({
          destination: path.basename(destination),
          format: 'prontoo/scoped-css-variables',
          options: { target, showFileHeader: false }
        }))
      }
    }
  };
  const sd = new StyleDictionary(config);
  await sd.buildAllPlatforms();
  let failures = 0;
  for (const [target, destination] of Object.entries(targets)) {
    const generatedPath = path.join(tempRoot, path.basename(destination));
    const generated = fs.readFileSync(generatedPath, 'utf8');
    const finalPath = path.join(root, destination);
    if (mode === '--write') {
      fs.mkdirSync(path.dirname(finalPath), { recursive: true });
      fs.writeFileSync(finalPath, generated);
    } else {
      const current = fs.existsSync(finalPath) ? fs.readFileSync(finalPath, 'utf8') : '';
      if (current !== generated) {
        failures++;
        process.stderr.write(`design-tokens-build: generated drift ${target}\n`);
      }
    }
  }
  if (failures > 0) process.exit(1);
  process.stdout.write(`design-tokens-build: ${mode === '--write' ? 'written' : 'deterministic'} dtcg=2025.10 targets=${Object.keys(targets).length}\n`);
} finally {
  fs.rmSync(tempRoot, { recursive: true, force: true });
}
