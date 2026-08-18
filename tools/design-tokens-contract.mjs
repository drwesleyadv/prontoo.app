#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
execFileSync(process.execPath, [path.join(root, 'tools/design-tokens-build.mjs'), '--check'], { stdio: 'inherit' });

const tokenSources = [
  'design/tokens/reference.tokens.json',
  'design/tokens/system.tokens.json',
  'design/tokens/component.tokens.json',
  'design/tokens/themes.tokens.json',
  'design/tokens/runtime.tokens.json'
];
const generatedFile = 'design/generated/tokens.css';
const applicationFile = 'design/styles/application.css';
for (const file of [...tokenSources, generatedFile, applicationFile]) {
  if (!fs.existsSync(path.join(root, file))) throw new Error(`design source missing ${file}`);
}
if (fs.existsSync(path.join(root, 'app/Presentation/Styles'))) throw new Error('legacy presentation styles directory still exists');
if (fs.existsSync(path.join(root, 'design/tokens/css-bridge.json'))) throw new Error('legacy css bridge still exists');

const expected = new Set();
const allowedTargets = new Set(['clinic', 'material', 'semantic']);
const collectTokens = value => {
  if (!value || typeof value !== 'object') return;
  if (Array.isArray(value)) {
    for (const item of value) collectTokens(item);
    return;
  }
  const meta = value.$extensions?.['com.prontoo'];
  if (Object.hasOwn(value, '$value') && meta && typeof meta.cssName === 'string') {
    if (!allowedTargets.has(String(meta.target || ''))) throw new Error(`unknown DTCG target ${meta.target}:${meta.cssName}`);
    if (!/^--[a-z0-9-]+$/i.test(meta.cssName)) throw new Error(`invalid DTCG cssName ${meta.cssName}`);
    expected.add(meta.cssName);
  }
  for (const [key, child] of Object.entries(value)) {
    if (key !== '$extensions') collectTokens(child);
  }
};
for (const file of tokenSources) collectTokens(JSON.parse(fs.readFileSync(path.join(root, file), 'utf8')));

const runtimeSource = fs.readFileSync(path.join(root, 'design/tokens/runtime.tokens.json'), 'utf8');
if (/"base64"\s*:/.test(runtimeSource)) throw new Error('encoded legacy css payload is forbidden');
const generated = fs.readFileSync(path.join(root, generatedFile), 'utf8');
if (!generated.trim()) throw new Error('generated token artifact empty');
for (const name of expected) {
  if (!generated.includes(`${name}:`)) throw new Error(`generated token missing ${name}`);
}
const application = fs.readFileSync(path.join(root, applicationFile), 'utf8');
if (/(^|[;{]\s*)(--pt-(?:ref|sys|cmp)-[a-z0-9-]+)\s*:/im.test(application)) throw new Error('canonical token authored outside DTCG generator');

const css = fs.readFileSync(path.join(root, 'public/assets/presentation.css'), 'utf8').replace(/^@import[^;]+;\s*/m, '');
const themes = [
  { name: 'green', accent: '#2f6652', strong: '#1d583f', soft: '#d9f0df' },
  { name: 'blue', accent: '#2563eb', strong: '#1d4ed8', soft: '#eff6ff' },
  { name: 'wine', accent: '#8a2432', strong: '#6f1d2a', soft: '#fff0f2' },
  { name: 'violet', accent: '#6d3bc3', strong: '#53309a', soft: '#f4f1ff' }
];
const markup = `<style>${css}</style><body><main><header class="pagehead has-operations"><span id="pageheadIcon" class="pagehead-icon"><span class="material-symbols-rounded">settings</span></span><nav class="pagehead-controls pagehead-controls--navigation"><a id="nav" class="pagehead-control pagehead-control--nav"><span>Outro</span></a></nav><div class="pagehead-controls--actions"><button id="primary" class="pagehead-control pagehead-control--primary">Salvar</button><button id="danger" class="pagehead-control pagehead-control--danger">Excluir</button></div></header><article id="stat" class="stat-card"><span class="material-symbols-rounded">monitoring</span><div><b>1</b><span>Indicador</span></div></article><div id="ok" class="doc-ds-chip ok">Concluído</div><input id="input" value="x"></main></body>`;
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
    const expectedColor = await page.evaluate(color => {
      const probe = document.createElement('div');
      probe.style.color = color;
      document.body.appendChild(probe);
      const value = getComputedStyle(probe).color;
      probe.remove();
      return value;
    }, theme.accent);
    for (const [key, value] of Object.entries({ accent: result.accent, pageheadComponent: result.pageheadComponent, pageheadAlias: result.pageheadAlias })) {
      if (normalize(value) !== normalize(theme.accent)) throw new Error(`${theme.name}:${key} does not follow clinic runtime accent`);
    }
    for (const [key, value] of Object.entries({ navColor: result.navColor, pageheadIconColor: result.pageheadIconColor, statIconColor: result.statIconColor })) {
      if (normalize(value) !== normalize(expectedColor)) throw new Error(`${theme.name}:${key} does not follow clinic runtime accent`);
    }
    const semantics = {
      okBg: normalize(result.okBg),
      okColor: normalize(result.okColor),
      dangerBg: normalize(result.dangerBg)
    };
    if (semanticBaseline === null) semanticBaseline = semantics;
    else {
      for (const [key, value] of Object.entries(semantics)) {
        if (value !== semanticBaseline[key]) throw new Error(`${theme.name}: semantic ${key} changed with clinic accent`);
      }
    }
    await context.close();
  }
} finally {
  await browser.close();
}
process.stdout.write(`design-tokens-contract: dtcg sources=${tokenSources.length} generated=1 mapped=${expected.size} themes=${themes.length} semantic-status=invariant legacy=0 runtime=clinic-config\n`);
