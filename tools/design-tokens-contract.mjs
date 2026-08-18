#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
execFileSync(process.execPath, [path.join(root, 'tools/design-tokens-build.mjs'), '--check'], { stdio: 'inherit' });

const tokenSources = [
  'design/tokens/reference.tokens.json',
  'design/tokens/system.tokens.json',
  'design/tokens/component.tokens.json',
  'design/tokens/themes.tokens.json'
];
const bridgeFile = 'design/tokens/css-bridge.json';
const required = [...tokenSources, bridgeFile];
for (const file of required) {
  if (!fs.existsSync(path.join(root, file))) throw new Error(`design token source missing ${file}`);
}

const generatedTargets = {
  clinic: 'app/Presentation/Styles/tokens/clinic.css',
  material: 'app/Presentation/Styles/tokens/material.css',
  semantic: 'app/Presentation/Styles/tokens/semantic.css'
};
const expectedByTarget = new Map(Object.keys(generatedTargets).map(target => [target, new Set()]));
const collectTokens = value => {
  if (!value || typeof value !== 'object') return;
  if (Array.isArray(value)) {
    for (const item of value) collectTokens(item);
    return;
  }
  const meta = value.$extensions?.['com.prontoo'];
  if (Object.hasOwn(value, '$value') && meta && typeof meta.cssName === 'string' && typeof meta.target === 'string') {
    if (!expectedByTarget.has(meta.target)) throw new Error(`unknown DTCG target ${meta.target}:${meta.cssName}`);
    if (!/^--[a-z0-9-]+$/i.test(meta.cssName)) throw new Error(`invalid DTCG cssName ${meta.cssName}`);
    expectedByTarget.get(meta.target).add(meta.cssName);
  }
  for (const [key, child] of Object.entries(value)) {
    if (key !== '$extensions') collectTokens(child);
  }
};
for (const file of tokenSources) {
  collectTokens(JSON.parse(fs.readFileSync(path.join(root, file), 'utf8')));
}

const bridge = JSON.parse(fs.readFileSync(path.join(root, bridgeFile), 'utf8'));
if (bridge?.schema !== 'prontoo-design-token-css-bridge-v2' || bridge?.policy?.authored_css_is_not_source_of_truth !== true || !Array.isArray(bridge?.entries)) {
  throw new Error('design token compatibility bridge policy invalid');
}
for (const entry of bridge.entries) {
  const target = String(entry?.target || '');
  const name = String(entry?.name || '');
  if (!expectedByTarget.has(target)) throw new Error(`unknown bridge target ${target}:${name}`);
  if (!/^--[a-z0-9-]+$/i.test(name)) throw new Error(`invalid bridge cssName ${name}`);
  expectedByTarget.get(target).add(name);
}

for (const [target, file] of Object.entries(generatedTargets)) {
  const full = path.join(root, file);
  if (!fs.existsSync(full)) throw new Error(`generated token artifact missing ${file}`);
  const css = fs.readFileSync(full, 'utf8');
  if (!css.trim()) throw new Error(`generated token artifact empty ${file}`);
  for (const name of expectedByTarget.get(target)) {
    if (!css.includes(`${name}:`)) throw new Error(`generated token missing ${target}:${name}`);
  }
}

const authoredRoots = [
  'app/Presentation/Styles/foundation',
  'app/Presentation/Styles/primitives',
  'app/Presentation/Styles/components',
  'app/Presentation/Styles/patterns',
  'app/Presentation/Styles/routes',
  'app/Presentation/Styles/utilities',
  'app/Presentation/Styles/states'
];
const forbiddenDefinitions = /(^|[;{]\s*)(--pt-(?:ref|sys|cmp)-[a-z0-9-]+)\s*:/gim;
for (const relative of authoredRoots) {
  const base = path.join(root, relative);
  if (!fs.existsSync(base)) continue;
  const stack = [base];
  while (stack.length) {
    const current = stack.pop();
    for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
      const full = path.join(current, entry.name);
      if (entry.isDirectory()) stack.push(full);
      else if (entry.isFile() && entry.name.endsWith('.css')) {
        const source = fs.readFileSync(full, 'utf8');
        forbiddenDefinitions.lastIndex = 0;
        const match = forbiddenDefinitions.exec(source);
        if (match) throw new Error(`canonical token authored outside generator ${path.relative(root, full)}:${match[2]}`);
      }
    }
  }
}

const css = fs.readFileSync(path.join(root, 'public/assets/presentation.css'), 'utf8').replace(/^@import[^;]+;\s*/m, '');
const themes = [
  { name: 'green', accent: '#2f6652', strong: '#1d583f', soft: '#d9f0df' },
  { name: 'blue', accent: '#2563eb', strong: '#1d4ed8', soft: '#eff6ff' },
  { name: 'wine', accent: '#8a2432', strong: '#6f1d2a', soft: '#fff0f2' },
  { name: 'violet', accent: '#6d3bc3', strong: '#53309a', soft: '#f4f1ff' }
];
const markup = `<style>${css}</style><body><main><header class="pagehead has-operations"><span id="pageheadIcon" class="pagehead-icon"><span class="material-symbols-rounded">settings</span></span><nav class="pagehead-controls pagehead-controls--navigation"><a id="nav" class="pagehead-control pagehead-control--nav"><span>Outro</span></a></nav><div class="pagehead-controls--actions"><button id="primary" class="pagehead-control pagehead-control--primary">Salvar</button><button id="danger" class="pagehead-control pagehead-control--danger">Excluir</button></div></header><article id="stat" class="stat-card"><span class="material-symbols-rounded">monitoring</span><div><b>1</b><span>Indicador</span></div></article><div id="ok" class="pill ok">Concluído</div><input id="input" value="x"></main></body>`;
const normalize = value => value.replace(/\s+/g, '').toLowerCase();
const browser = await chromium.launch({ headless: true });
let semanticBaseline = null;
try {
  for (const theme of themes) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
    const page = await context.newPage();
    await page.setContent(markup, { waitUntil: 'load' });
    await page.evaluate(current => {
      const body = document.body;
      body.style.setProperty('--clinic-identity-accent', current.accent);
      body.style.setProperty('--clinic-identity-hover', current.strong);
      body.style.setProperty('--clinic-identity-pressed', current.strong);
      body.style.setProperty('--clinic-identity-on-accent', '#ffffff');
      body.style.setProperty('--clinic-accent', current.accent);
      body.style.setProperty('--clinic-accent-strong', current.strong);
      body.style.setProperty('--clinic-accent-dark', current.strong);
      body.style.setProperty('--clinic-accent-hover', current.strong);
      body.style.setProperty('--clinic-accent-pressed', current.strong);
      body.style.setProperty('--clinic-accent-soft', current.soft);
      body.style.setProperty('--clinic-accent-subtle', current.soft);
      body.style.setProperty('--clinic-on-accent', '#ffffff');
      body.style.setProperty('--md-sys-color-primary', current.accent);
      body.style.setProperty('--md-ref-palette-primary40', current.accent);
      body.style.setProperty('--md-ref-palette-primary30', current.strong);
      body.style.setProperty('--md-sys-color-on-primary', '#ffffff');
      body.style.setProperty('--md-sys-color-primary-container', current.soft);
    }, theme);
    const result = await page.evaluate(() => {
      const nav = getComputedStyle(document.getElementById('nav'));
      const pageheadIcon = getComputedStyle(document.getElementById('pageheadIcon'));
      const statIcon = getComputedStyle(document.querySelector('#stat > .material-symbols-rounded'));
      const ok = getComputedStyle(document.getElementById('ok'));
      const danger = getComputedStyle(document.getElementById('danger'));
      const body = getComputedStyle(document.body);
      return {
        accent: body.getPropertyValue('--pt-sys-color-accent').trim(),
        pageheadComponent: body.getPropertyValue('--pt-cmp-pagehead-accent').trim(),
        pageheadAlias: body.getPropertyValue('--pt-pagehead-control-accent').trim(),
        navColor: nav.color,
        pageheadIconColor: pageheadIcon.color,
        statIconColor: statIcon.color,
        okBg: ok.backgroundColor,
        okColor: ok.color,
        dangerBg: danger.backgroundColor
      };
    });
    const expected = await page.evaluate(color => {
      const probe = document.createElement('div');
      probe.style.color = color;
      document.body.appendChild(probe);
      const value = getComputedStyle(probe).color;
      probe.remove();
      return value;
    }, theme.accent);
    for (const [key, value] of Object.entries({ accent: result.accent, pageheadComponent: result.pageheadComponent, pageheadAlias: result.pageheadAlias })) {
      if (normalize(value) !== normalize(theme.accent)) throw new Error(`${theme.name}:${key} does not follow clinic runtime accent (${value} != ${theme.accent})`);
    }
    for (const [key, value] of Object.entries({ navColor: result.navColor, pageheadIconColor: result.pageheadIconColor, statIconColor: result.statIconColor })) {
      if (normalize(value) !== normalize(expected)) throw new Error(`${theme.name}:${key} does not follow clinic runtime accent (${value} != ${expected})`);
    }
    const semantics = [result.okBg, result.okColor, result.dangerBg].map(normalize);
    if (semanticBaseline === null) semanticBaseline = semantics;
    else if (JSON.stringify(semantics) !== JSON.stringify(semanticBaseline)) throw new Error(`${theme.name}: semantic status colors changed with clinic accent`);
    await context.close();
  }
} finally {
  await browser.close();
}
const canonicalCount = [...expectedByTarget.values()].reduce((sum, names) => sum + names.size, 0);
process.stdout.write(`design-tokens-contract: dtcg sources=${required.length} generated=${Object.keys(generatedTargets).length} mapped=${canonicalCount} themes=${themes.length} semantic-status=invariant runtime=clinic-config\n`);
