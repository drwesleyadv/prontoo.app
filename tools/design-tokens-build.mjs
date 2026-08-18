#!/usr/bin/env node
import crypto from 'node:crypto';
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
if (!bridge || bridge.schema !== 'prontoo-design-token-css-bridge-v2' || !bridge.legacy || !Array.isArray(bridge.entries)) {
  process.stderr.write('design-tokens-build: invalid css bridge\n');
  process.exit(1);
}
const vendorMeta = token => token?.original?.$extensions?.['com.prontoo'] ?? token?.$extensions?.['com.prontoo'] ?? {};
const originalValue = token => token?.original?.$value ?? token?.$value ?? token?.original?.value ?? token?.value;
const transformedValue = token => token?.value ?? token?.$value;
const tokenPath = token => Array.isArray(token.path) ? token.path.join('.') : String(token.name || '');
const cssName = token => {
  const value = String(vendorMeta(token).cssName || '').trim();
  if (!/^--[a-z0-9-]+$/i.test(value)) throw new Error(`invalid cssName for ${tokenPath(token)}`);
  return value;
};
const cssLiteral = value => {
  if (typeof value === 'string' || typeof value === 'number') return String(value);
  if (value && typeof value === 'object' && typeof value.hex === 'string') return String(value.hex);
  throw new Error(`unsupported transformed token value ${JSON.stringify(value)}`);
};
const legacyCss = target => {
  const record = bridge.legacy[target];
  if (!record || typeof record.base64 !== 'string' || typeof record.sha256 !== 'string') throw new Error(`legacy payload missing ${target}`);
  const css = Buffer.from(record.base64, 'base64').toString('utf8');
  const hash = crypto.createHash('sha256').update(css).digest('hex');
  if (hash !== record.sha256) throw new Error(`legacy payload hash mismatch ${target}`);
  return css;
};

StyleDictionary.registerFormat({
  name: 'prontoo/dtcg-with-legacy-abi',
  format: ({ dictionary, options }) => {
    const target = String(options.target || '');
    if (!Object.hasOwn(targets, target)) throw new Error(`unknown token target ${target}`);
    const all = [...dictionary.allTokens];
    const names = new Map(all.map(token => [tokenPath(token), cssName(token)]));
    const declarations = [];
    for (const token of all) {
      const meta = vendorMeta(token);
      if (String(meta.target || '') !== target) continue;
      const raw = originalValue(token);
      let value = String(meta.cssValue || '').trim();
      if (!value) {
        if (typeof raw === 'string' && /^\{[^{}]+\}$/.test(raw.trim())) {
          const refName = names.get(raw.trim().slice(1, -1));
          if (!refName) throw new Error(`unknown token reference ${raw}`);
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
        selector: String(meta.selector || ':root'),
        name: cssName(token),
        value,
        important: meta.important === true,
        order: Number(meta.order || 0)
      });
    }
    for (const entry of bridge.entries) {
      if (String(entry.target || '') !== target) continue;
      declarations.push({
        selector: String(entry.selector || 'body'),
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
      if (!/^--[a-z0-9-]+$/i.test(declaration.name)) throw new Error(`invalid generated token ${declaration.name}`);
      if (!grouped.has(declaration.selector)) {
        grouped.set(declaration.selector, []);
        selectorOrder.push(declaration.selector);
      }
      grouped.get(declaration.selector).push(declaration);
    }
    const blocks = selectorOrder.map(selector => {
      const lines = grouped.get(selector).map(item => `  ${item.name}:${item.value}${item.important ? '!important' : ''};`).join('\n');
      return `${selector}{\n${lines}\n}`;
    }).join('\n\n');
    const legacy = legacyCss(target);
    const separator = legacy.endsWith('\n') ? '\n' : '\n\n';
    return legacy + separator + blocks + '\n';
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
          format: 'prontoo/dtcg-with-legacy-abi',
          options: { target, showFileHeader: false }
        }))
      }
    }
  };
  const sd = new StyleDictionary(config);
  await sd.buildAllPlatforms();
  let failures = 0;
  for (const [target, destination] of Object.entries(targets)) {
    const generated = fs.readFileSync(path.join(tempRoot, path.basename(destination)), 'utf8');
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
  process.stdout.write(`design-tokens-build: ${mode === '--write' ? 'written' : 'deterministic'} dtcg=2025.10 legacy-abi=generated targets=${Object.keys(targets).length}\n`);
} finally {
  fs.rmSync(tempRoot, { recursive: true, force: true });
}
