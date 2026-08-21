#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const contract = JSON.parse(fs.readFileSync(path.join(root, 'design/design-system.contract.json'), 'utf8'));
if (contract.policy !== 'prontoo-design-system-normative-v1') throw new Error('design system policy mismatch');
if (contract.standards?.tokens !== 'DTCG 2025.10' || contract.standards?.resolver !== 'DTCG Resolver Module 2025.10') throw new Error('DTCG standard mismatch');
if (contract.standards?.accessibility !== 'WCAG 2.2 AA' || contract.standards?.widget_patterns !== 'WAI-ARIA APG') throw new Error('accessibility standard mismatch');

const tokenPaths = new Set();
const collectTokens = (value, parts = []) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return;
  if (Object.hasOwn(value, '$value')) {
    tokenPaths.add(parts.join('.'));
    return;
  }
  for (const [key, child] of Object.entries(value)) {
    if (!key.startsWith('$')) collectTokens(child, [...parts, key]);
  }
};
for (const name of fs.readdirSync(path.join(root, 'design/tokens')).filter(name => name.endsWith('.tokens.json'))) {
  collectTokens(JSON.parse(fs.readFileSync(path.join(root, 'design/tokens', name), 'utf8')));
}

const componentDir = path.join(root, 'design/components');
const files = fs.readdirSync(componentDir).filter(name => name.endsWith('.component.json')).sort();
const required = new Set(['button', 'filter-chip', 'dialog', 'row', 'pagehead-control']);
const lifecycle = new Set(contract.lifecycle || []);
const canonicalStates = new Set(contract.states || []);
const names = new Set();
for (const file of files) {
  const component = JSON.parse(fs.readFileSync(path.join(componentDir, file), 'utf8'));
  if (!component.name || names.has(component.name)) throw new Error(`duplicate or missing component name ${file}`);
  names.add(component.name);
  if (!lifecycle.has(component.status)) throw new Error(`invalid lifecycle ${component.name}:${component.status}`);
  if (!Array.isArray(component.anatomy) || component.anatomy.length === 0) throw new Error(`missing anatomy ${component.name}`);
  if (!Array.isArray(component.states) || component.states.some(state => !canonicalStates.has(state))) throw new Error(`noncanonical state ${component.name}`);
  if (!Array.isArray(component.tokensConsumed)) throw new Error(`tokensConsumed missing ${component.name}`);
  for (const token of component.tokensConsumed) if (!tokenPaths.has(token)) throw new Error(`unknown token ${component.name}:${token}`);
  if (!component.semantics || typeof component.semantics !== 'object') throw new Error(`semantics missing ${component.name}`);
  if (component.status === 'deprecated' && typeof component.replacement !== 'string') throw new Error(`deprecated component without replacement ${component.name}`);
}
for (const name of required) if (!names.has(name)) throw new Error(`required component contract missing ${name}`);

const dialog = JSON.parse(fs.readFileSync(path.join(componentDir, 'dialog.component.json'), 'utf8'));
if (dialog.semantics?.role !== 'dialog' || dialog.semantics?.focusContainmentRequired !== true || dialog.semantics?.focusRestoreRequired !== true) throw new Error('dialog APG contract incomplete');
const filterChip = JSON.parse(fs.readFileSync(path.join(componentDir, 'filter-chip.component.json'), 'utf8'));
if (filterChip.semantics?.navigationState !== 'aria-current' || filterChip.semantics?.toggleState !== 'aria-pressed') throw new Error('filter-chip ARIA state contract incomplete');
const button = JSON.parse(fs.readFileSync(path.join(componentDir, 'button.component.json'), 'utf8'));
if (button.semantics?.nativeButtonPreferred !== true) throw new Error('button native semantics contract missing');
const pagehead = JSON.parse(fs.readFileSync(path.join(componentDir, 'pagehead-control.component.json'), 'utf8'));
const rendererPath = path.join(root, 'app/Presentation/UiComponents/PageHeadControlPresentationOperations01.php');
if (pagehead.renderer !== 'Prontoo\\Presentation\\UiComponents\\PageHeadControlPresentationOperations01' || !fs.existsSync(rendererPath)) throw new Error('pagehead renderer contract mismatch');

const activeSources = [];
const collectFiles = directory => {
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const full = path.join(directory, entry.name);
    if (entry.isDirectory()) collectFiles(full);
    else if (/\.(?:php|css|js|mjs)$/i.test(entry.name)) activeSources.push(full);
  }
};
collectFiles(path.join(root, 'app'));
collectFiles(path.join(root, 'design/styles'));
activeSources.push(path.join(root, 'public/assets/app.js'));
const activeText = activeSources.map(file => fs.readFileSync(file, 'utf8')).join('\n');
for (const alias of contract.removedAliases || []) if (activeText.includes(alias)) throw new Error(`removed design alias reintroduced ${alias}`);

const modernCss = fs.readFileSync(path.join(root, 'design/styles/standards.css'), 'utf8');
const declaredLayers = contract.cascadeLayers || [];
const layerHeader = `@layer ${declaredLayers.join(', ')};`;
if (!modernCss.includes(layerHeader)) throw new Error('cascade layer order mismatch');
if (!modernCss.includes('container-type:inline-size') || !modernCss.includes('@container')) throw new Error('container-query primitive missing');
if (!modernCss.includes('@media (forced-colors:active)') || !modernCss.includes('@media (prefers-reduced-motion:reduce)')) throw new Error('user preference support missing');
if (modernCss.includes('!important') || modernCss.includes('body[data-route=')) throw new Error('modern CSS increased cascade debt');
if (/(#[0-9a-f]{3,8}\b|rgba?\(|hsla?\()/i.test(modernCss)) throw new Error('modern CSS bypasses design tokens with a color literal');

const labPath = path.join(root, 'tests/presentation/ds-lab.html');
if (!fs.existsSync(labPath)) throw new Error('DS Lab missing');
const lab = fs.readFileSync(labPath, 'utf8');
for (const needle of ['ds-filter-chip', 'role="dialog"', 'data-ds-container', 'ds-adaptive-group', 'aria-current="page"', 'aria-pressed="false"']) {
  if (!lab.includes(needle)) throw new Error(`DS Lab coverage missing ${needle}`);
}

process.stdout.write(`design-system-contract: components=${files.length} states=${canonicalStates.size} tokens=${tokenPaths.size} wcag=2.2-AA apg=1 layers=${declaredLayers.length} container-queries=1 forced-colors=1 removed-aliases=0\n`);
