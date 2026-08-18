#!/usr/bin/env node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import StyleDictionary from 'style-dictionary';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const mode = process.argv[2] || '--check';
if (!['--check', '--write'].includes(mode)) {
  process.stderr.write('design-tokens-build: use --check or --write\n');
  process.exit(2);
}
const sourceGlob = path.join(root, 'design/tokens/**/*.tokens.json');
const generatedPath = path.join(root, 'design/generated/tokens.css');
const targetOrder = ['clinic', 'material', 'semantic'];
const targetRank = new Map(targetOrder.map((target, index) => [target, index]));
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
const render = declarations => {
  const selectorOrder = [];
  const grouped = new Map();
  for (const declaration of declarations) {
    if (!grouped.has(declaration.selector)) {
      grouped.set(declaration.selector, []);
      selectorOrder.push(declaration.selector);
    }
    grouped.get(declaration.selector).push(declaration);
  }
  return selectorOrder.map(selector => {
    const lines = grouped.get(selector).map(item => `  ${item.name}:${item.value}${item.important ? '!important' : ''};`).join('\n');
    return `${selector}{\n${lines}\n}`;
  }).join('\n\n');
};

StyleDictionary.registerFormat({
  name: 'prontoo/dtcg-runtime-bundle',
  format: ({ dictionary }) => {
    const all = [...dictionary.allTokens];
    const names = new Map(all.map(token => [tokenPath(token), cssName(token)]));
    const declarations = all.map(token => {
      const meta = vendorMeta(token);
      const target = String(meta.target || '');
      if (!targetRank.has(target)) throw new Error(`unknown target ${target}:${tokenPath(token)}`);
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
      return {
        selector: String(meta.selector || ':root'),
        name: cssName(token),
        value,
        important: meta.important === true,
        target,
        phase: String(meta.phase || 'canonical'),
        sequence: Number(meta.sequence || 0),
        order: Number(meta.order || 0)
      };
    });
    const residual = declarations.filter(item => item.phase === 'residual').sort((a, b) => a.sequence - b.sequence || a.name.localeCompare(b.name));
    const canonical = declarations.filter(item => item.phase !== 'residual');
    const sections = [];
    if (residual.length) sections.push(render(residual));
    for (const target of targetOrder) {
      const items = canonical.filter(item => item.target === target).sort((a, b) => a.order - b.order || a.name.localeCompare(b.name));
      if (items.length) sections.push(render(items));
    }
    return sections.filter(Boolean).join('\n\n') + '\n';
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
        files: [{ destination: 'tokens.css', format: 'prontoo/dtcg-runtime-bundle', options: { showFileHeader: false } }]
      }
    }
  };
  const sd = new StyleDictionary(config);
  await sd.buildAllPlatforms();
  const generated = fs.readFileSync(path.join(tempRoot, 'tokens.css'), 'utf8');
  if (mode === '--write') {
    fs.mkdirSync(path.dirname(generatedPath), { recursive: true });
    fs.writeFileSync(generatedPath, generated);
  } else {
    const current = fs.existsSync(generatedPath) ? fs.readFileSync(generatedPath, 'utf8') : '';
    if (current !== generated) {
      process.stderr.write('design-tokens-build: generated drift\n');
      process.exit(1);
    }
  }
  process.stdout.write(`design-tokens-build: ${mode === '--write' ? 'written' : 'deterministic'} dtcg=2025.10 generated=1 legacy=0\n`);
} finally {
  fs.rmSync(tempRoot, { recursive: true, force: true });
}
