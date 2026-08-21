#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const contract = JSON.parse(fs.readFileSync(path.join(root,'design/design-system.contract.json'),'utf8'));
if (contract.policy !== 'dtcg-native-contract-driven-layered-v1') throw new Error('noncanonical design-system policy');
const expectedStyles = ['foundation.css','primitives.css','components.css','composition.css','precedence.css'];
const styleDir = path.join(root,'design/styles');
const actualStyles = fs.readdirSync(styleDir).filter(name=>name.endsWith('.css')).sort();
if (JSON.stringify(actualStyles) !== JSON.stringify([...expectedStyles].sort())) throw new Error(`unmanaged CSS sources: ${actualStyles.join(',')}`);
const forbiddenPaths = [
  'design/styles/application.css','design/styles/standards.css','app/Presentation/Styles','design/tokens/css-bridge.json'
];
for (const rel of forbiddenPaths) if (fs.existsSync(path.join(root,rel))) throw new Error(`noncanonical design resource ${rel}`);
const layerOrder = ['ds-foundation','ds-primitives','ds-components','ds-composition','ds-precedence'];
if (JSON.stringify(contract.cascadeLayers) !== JSON.stringify(layerOrder)) throw new Error('cascade layer contract mismatch');
let css='';
for (const [index,name] of expectedStyles.entries()) {
  const source=fs.readFileSync(path.join(styleDir,name),'utf8');
  const layer=layerOrder[index];
  if (!source.includes(`@layer ${layer}`)) throw new Error(`missing canonical layer ${layer}`);
  css += '\n' + source;
}
if (css.includes('!important')) throw new Error('important declarations must be zero');
if (/body\s*\[\s*data-route/i.test(css)) throw new Error('route-scoped design rules must be zero');
if (/--(?!pt-(?:ref|sys|cmp|runtime|presentation)-)[a-z0-9_-]+/i.test(css)) throw new Error('parallel custom-property namespace detected');
for (const line of css.split(/\r?\n/)) {
  const trimmed=line.trim();
  if (!trimmed || trimmed.startsWith('@container') || trimmed.startsWith('@media') || trimmed.startsWith('@layer')) continue;
  const declarationText = trimmed.includes('{') ? trimmed.slice(trimmed.lastIndexOf('{') + 1) : trimmed;
  if (!declarationText || !declarationText.includes(':')) continue;
  if (/(#[0-9a-f]{3,8}\b|rgba?\(|hsla?\()/i.test(declarationText)) throw new Error(`visual color literal outside DTCG: ${declarationText.slice(0,180)}`);
  if (/:[^;{}]*\b\d+(?:\.\d+)?(?:px|rem|em|vh|vw|vmin|vmax|ch|ex|ms|s|deg)\b/i.test(declarationText) && !/var\(--pt-(?:ref|sys|cmp|runtime|presentation)-/i.test(declarationText)) {
    throw new Error(`visual dimension outside DTCG: ${declarationText.slice(0,180)}`);
  }
}
const runtime = JSON.parse(fs.readFileSync(path.join(root,'design/tokens/runtime.tokens.json'),'utf8'));
if (runtime?.runtime?.residual !== undefined) throw new Error('residual token group detected');
const bindings = JSON.parse(fs.readFileSync(path.join(root,'design/platform/css.bindings.json'),'utf8'));
if (bindings.policy !== 'css-platform-bindings-dtcg-native-v2') throw new Error('noncanonical binding policy');
for (const [token,meta] of Object.entries(bindings.bindings||{})) {
  if ('phase' in meta || 'sequence' in meta || 'important' in meta) throw new Error(`historical binding metadata ${token}`);
  if (!/^--pt-(?:ref|sys|cmp|runtime|presentation)-[a-z0-9-]+$/i.test(String(meta.cssName||''))) throw new Error(`noncanonical css binding ${token}:${meta.cssName}`);
}
const visualContract=JSON.parse(fs.readFileSync(path.join(root,'app/presentation.visual-contract.json'),'utf8'));
for (const key of ['important_count','route_scope_count','duplicate_selector_headers','duplicate_selector_list_entries','factorable_route_scope_groups']) {
  if (Number(visualContract.debt_budget?.[key] ?? -1) !== 0) throw new Error(`design debt budget is not zero: ${key}`);
}
const normativeFiles=['design/README.md','docs/architecture/design-system.md','docs/architecture/design-tokens.md','design/design-system.contract.json'];
const forbiddenVocabulary=['legacyBehavior','legacyCss','ratchet','design/styles/application.css','design/styles/standards.css','css-bridge.json','app/Presentation/Styles'];
for (const rel of normativeFiles) {
  const text=fs.readFileSync(path.join(root,rel),'utf8');
  for (const needle of forbiddenVocabulary) if (text.includes(needle)) throw new Error(`historical design instruction in ${rel}: ${needle}`);
}
if (Number(contract.architectureDebt?.legacyResources ?? -1)!==0 || Number(contract.architectureDebt?.compatibilityModes ?? -1)!==0 || Number(contract.architectureDebt?.historicalDesignInstructions ?? -1)!==0 || Number(contract.architectureDebt?.unmanagedCssSources ?? -1)!==0) throw new Error('architecture debt contract must be zero');
process.stdout.write(`design-system-purity-contract: architecture=dtcg-native-contract-driven-layered legacy-resources=0 compatibility-modes=0 historical-instructions=0 unmanaged-css=0 important=0 route-scopes=0 visual-literals=0\n`);
