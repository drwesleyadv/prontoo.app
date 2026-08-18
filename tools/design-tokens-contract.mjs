#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
execFileSync(process.execPath, [path.join(root, 'tools/design-tokens-build.mjs'), '--check'], { stdio: 'inherit' });

const required = [
  'design/tokens/reference.tokens.json',
  'design/tokens/system.tokens.json',
  'design/tokens/component.tokens.json',
  'design/tokens/themes.tokens.json',
  'design/tokens/css-bridge.json'
];
for (const file of required) {
  if (!fs.existsSync(path.join(root, file))) throw new Error(`design token source missing ${file}`);
}
const generated = [
  'app/Presentation/Styles/tokens/clinic.css',
  'app/Presentation/Styles/tokens/material.css',
  'app/Presentation/Styles/tokens/semantic.css'
];
for (const file of generated) {
  const css = fs.readFileSync(path.join(root, file), 'utf8');
  if (!css.includes('--pt-ref-') || !css.includes('--pt-sys-') && file.endsWith('clinic.css')) {
    throw new Error(`generated token surface incomplete ${file}`);
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
const markup = `<style>${css}</style><body><main><header class="pagehead has-operations"><nav class="pagehead-controls pagehead-controls--navigation"><a id="nav" class="pagehead-control pagehead-control--nav" aria-current="page"><span>Ativo</span></a></nav><div class="pagehead-controls--actions"><button id="primary" class="pagehead-control pagehead-control--primary">Salvar</button><button id="danger" class="pagehead-control pagehead-control--danger">Excluir</button></div></header><article id="stat" class="stat-card"><span class="material-symbols-rounded">monitoring</span><div><b>1</b><span>Indicador</span></div></article><div id="ok" class="pill ok">Concluído</div><input id="input" value="x"></main></body>`;
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
      body.style.setProperty('--pt-theme-accent', current.accent);
      body.style.setProperty('--pt-theme-accent-strong', current.strong);
      body.style.setProperty('--pt-theme-accent-hover', current.strong);
      body.style.setProperty('--pt-theme-accent-soft', current.soft);
      body.style.setProperty('--pt-theme-on-accent', '#ffffff');
      body.style.setProperty('--pt-theme-identity-accent', current.accent);
      body.style.setProperty('--pt-theme-identity-hover', current.strong);
      body.style.setProperty('--pt-theme-identity-on-accent', '#ffffff');
    }, theme);
    const result = await page.evaluate(() => {
      const primary = getComputedStyle(document.getElementById('primary'));
      const nav = getComputedStyle(document.getElementById('nav'));
      const icon = getComputedStyle(document.querySelector('#stat > .material-symbols-rounded'));
      const ok = getComputedStyle(document.getElementById('ok'));
      const danger = getComputedStyle(document.getElementById('danger'));
      const body = getComputedStyle(document.body);
      return {
        accent: body.getPropertyValue('--pt-sys-color-accent').trim(),
        primaryBg: primary.backgroundColor,
        navColor: nav.color,
        iconColor: icon.color,
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
    for (const [key, value] of Object.entries({ accent: result.accent, primaryBg: result.primaryBg, navColor: result.navColor, iconColor: result.iconColor })) {
      if (normalize(value) !== normalize(expected)) throw new Error(`${theme.name}:${key} does not follow accent (${value} != ${expected})`);
    }
    const semantics = [result.okBg, result.okColor, result.dangerBg].map(normalize);
    if (semanticBaseline === null) semanticBaseline = semantics;
    else if (JSON.stringify(semantics) !== JSON.stringify(semanticBaseline)) throw new Error(`${theme.name}: semantic status colors changed with clinic accent`);
    await context.close();
  }
} finally {
  await browser.close();
}
process.stdout.write(`design-tokens-contract: dtcg sources=${required.length} generated=${generated.length} themes=${themes.length} semantic-status=invariant\n`);
