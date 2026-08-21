#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
execFileSync(process.execPath, [path.join(root, 'tools/design-tokens-build.mjs'), '--check'], { stdio: 'inherit' });

const resolverPath = path.join(root, 'design/resolvers/application.resolver.json');
const bindingPath = path.join(root, 'design/platform/css.bindings.json');
const generatedPath = path.join(root, 'design/generated/tokens.css');
const applicationPath = path.join(root, 'design/styles/application.css');
const standardsPath = path.join(root, 'design/styles/standards.css');
for (const file of [resolverPath, bindingPath, generatedPath, applicationPath, standardsPath]) {
  if (!fs.existsSync(file)) throw new Error(`design source missing ${path.relative(root, file)}`);
}
if (fs.existsSync(path.join(root, 'app/Presentation/Styles'))) throw new Error('legacy presentation styles directory still exists');
if (fs.existsSync(path.join(root, 'design/tokens/css-bridge.json'))) throw new Error('legacy css bridge still exists');

const resolver = JSON.parse(fs.readFileSync(resolverPath, 'utf8'));
if (resolver.$schema !== 'https://www.designtokens.org/schemas/2025.10/resolver.json') throw new Error('resolver schema must be DTCG 2025.10');
if (resolver.version !== '2025.10') throw new Error('resolver version must be 2025.10');
if (!resolver.sets || typeof resolver.sets !== 'object' || !resolver.modifiers || typeof resolver.modifiers !== 'object') throw new Error('resolver sets/modifiers missing');
if (!Array.isArray(resolver.resolutionOrder) || resolver.resolutionOrder.length === 0) throw new Error('resolver resolutionOrder missing');
const surfaceContexts = resolver.modifiers?.surface?.contexts;
if (!surfaceContexts?.application || !surfaceContexts?.public || resolver.modifiers.surface.default !== 'application') throw new Error('resolver surface contexts invalid');

const tokenRoot = path.join(root, 'design/tokens');
const tokenFiles = fs.readdirSync(tokenRoot).filter(name => name.endsWith('.tokens.json')).sort().map(name => path.join(tokenRoot, name));
const tokenPaths = new Set();
const reservedGroupKeys = new Set(['$description', '$type', '$extends', '$deprecated', '$extensions', '$root']);
const validateDeprecated = (value, name) => {
  if (value !== undefined && typeof value !== 'boolean' && typeof value !== 'string') throw new Error(`invalid $deprecated ${name}`);
};
const walkTokens = (value, parts = [], inheritedType = null) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error(`invalid DTCG object ${parts.join('.')}`);
  if (Object.hasOwn(value, '$value')) {
    const name = parts.join('.');
    if (!name) throw new Error('root token without name');
    if (value.$extensions?.['com.prontoo'] !== undefined) throw new Error(`platform metadata leaked into DTCG ${name}`);
    validateDeprecated(value.$deprecated, name);
    if (value.$type !== undefined && typeof value.$type !== 'string') throw new Error(`invalid $type ${name}`);
    if (value.$type === undefined && inheritedType === null && !(typeof value.$value === 'string' && /^\{[^{}]+\}$/.test(value.$value))) throw new Error(`untyped token ${name}`);
    tokenPaths.add(name);
    return;
  }
  const nextType = typeof value.$type === 'string' ? value.$type : inheritedType;
  validateDeprecated(value.$deprecated, parts.join('.') || 'root');
  if (value.$extensions?.['com.prontoo'] !== undefined) throw new Error(`platform metadata leaked into DTCG group ${parts.join('.')}`);
  for (const [key, child] of Object.entries(value)) {
    if (key.startsWith('$')) {
      if (!reservedGroupKeys.has(key)) throw new Error(`unknown DTCG reserved property ${parts.join('.')}:${key}`);
      if (key === '$root') walkTokens(child, [...parts, '$root'], nextType);
      continue;
    }
    if (/[.{}]/.test(key)) throw new Error(`invalid DTCG token/group name ${[...parts, key].join('.')}`);
    walkTokens(child, [...parts, key], nextType);
  }
};
for (const file of tokenFiles) walkTokens(JSON.parse(fs.readFileSync(file, 'utf8')));

const bindingDocument = JSON.parse(fs.readFileSync(bindingPath, 'utf8'));
if (bindingDocument.policy !== 'css-platform-bindings-outside-dtcg-v1') throw new Error('css binding policy mismatch');
const bindings = bindingDocument.bindings || {};
const allowedTargets = new Set(['clinic', 'material', 'semantic']);
const kebab = value => String(value).replace(/([a-z0-9])([A-Z])/g, '$1-$2').replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-+|-+$/g, '').toLowerCase();
const derivedName = name => `--pt-${name.split('.').map(kebab).join('-')}`;
for (const [name, meta] of Object.entries(bindings)) {
  if (!tokenPaths.has(name)) throw new Error(`orphan css binding ${name}`);
  if (!meta || typeof meta !== 'object' || !/^--[a-z0-9-]+$/i.test(String(meta.cssName || ''))) throw new Error(`invalid css binding ${name}`);
  if (!allowedTargets.has(String(meta.target || ''))) throw new Error(`unknown css target ${name}`);
}

const generated = fs.readFileSync(generatedPath, 'utf8');
if (!generated.trim()) throw new Error('generated token artifact empty');
for (const name of tokenPaths) {
  const cssName = String(bindings[name]?.cssName || derivedName(name));
  if (!generated.includes(`${cssName}:`)) throw new Error(`generated token missing ${name}:${cssName}`);
}

const application = fs.readFileSync(applicationPath, 'utf8');
const standards = fs.readFileSync(standardsPath, 'utf8');
for (const [name, source] of [['application', application], ['standards', standards]]) {
  if (/(^|[;{]\s*)(--pt-(?:ref|sys|cmp)-[a-z0-9-]+)\s*:/im.test(source)) throw new Error(`canonical token authored in ${name} css`);
}
for (const required of ['@layer ds-foundation, ds-primitives, ds-components, ds-composition;', '@container', 'forced-colors:active', 'prefers-reduced-motion:reduce']) {
  if (!standards.includes(required)) throw new Error(`modern css capability missing ${required}`);
}
if (standards.includes('!important') || standards.includes('body[data-route=')) throw new Error('modern css cannot introduce cascade debt');
if (/(#[0-9a-f]{3,8}\b|rgba?\(|hsla?\()/i.test(standards)) throw new Error('modern css contains arbitrary color literal');
for (const line of standards.split(/\R/)) {
  if (line.includes('@container')) continue;
  if (/:[^;{}]*\b\d+(?:\.\d+)?(?:px|rem|em|ms|s)\b/i.test(line)) throw new Error(`modern css contains arbitrary visual dimension: ${line.trim()}`);
}

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
    const semantics = { okBg: normalize(result.okBg), okColor: normalize(result.okColor), dangerBg: normalize(result.dangerBg) };
    if (semanticBaseline === null) semanticBaseline = semantics;
    else for (const [key, value] of Object.entries(semantics)) if (value !== semanticBaseline[key]) throw new Error(`${theme.name}: semantic ${key} changed with clinic accent`);
    await context.close();
  }
} finally {
  await browser.close();
}

process.stdout.write(`design-tokens-contract: dtcg=2025.10 resolver=1 sources=${tokenFiles.length} tokens=${tokenPaths.size} bindings=${Object.keys(bindings).length} themes=${themes.length} semantic-status=invariant platform-metadata-in-dtcg=0\n`);
