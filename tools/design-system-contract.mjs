#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
execFileSync(process.execPath,[path.join(root,'tools/design-system-purity-contract.mjs')],{stdio:'inherit'});
const contract=JSON.parse(fs.readFileSync(path.join(root,'design/design-system.contract.json'),'utf8'));
if (contract.policy!=='dtcg-native-contract-driven-layered-v1') throw new Error('design system policy mismatch');
if (contract.standards?.tokens!=='DTCG 2025.10'||contract.standards?.resolver!=='DTCG Resolver Module 2025.10') throw new Error('DTCG standard mismatch');
if (contract.standards?.accessibility!=='WCAG 2.2 AA'||contract.standards?.widget_patterns!=='WAI-ARIA APG') throw new Error('accessibility standard mismatch');
const tokenPaths=new Set();
const collect=(value,parts=[])=>{ if(!value||typeof value!=='object'||Array.isArray(value))return; if(Object.hasOwn(value,'$value')){tokenPaths.add(parts.join('.'));return;} for(const [k,v] of Object.entries(value)) if(!k.startsWith('$')) collect(v,[...parts,k]); };
for(const name of fs.readdirSync(path.join(root,'design/tokens')).filter(n=>n.endsWith('.tokens.json'))) collect(JSON.parse(fs.readFileSync(path.join(root,'design/tokens',name),'utf8')));
const componentDir=path.join(root,'design/components');
const files=fs.readdirSync(componentDir).filter(n=>n.endsWith('.component.json')).sort();
const required=new Set(['button','filter-chip','dialog','row','pagehead-control']);
const lifecycle=new Set(contract.lifecycle||[]); const states=new Set(contract.states||[]); const names=new Set();
for(const file of files){ const c=JSON.parse(fs.readFileSync(path.join(componentDir,file),'utf8')); if(!c.name||names.has(c.name))throw new Error(`duplicate/missing component ${file}`); names.add(c.name); if(!lifecycle.has(c.status))throw new Error(`invalid lifecycle ${c.name}`); if(!Array.isArray(c.anatomy)||!c.anatomy.length)throw new Error(`missing anatomy ${c.name}`); if(!Array.isArray(c.states)||c.states.some(s=>!states.has(s)))throw new Error(`noncanonical state ${c.name}`); if(!Array.isArray(c.tokensConsumed))throw new Error(`tokensConsumed missing ${c.name}`); for(const token of c.tokensConsumed)if(!tokenPaths.has(token))throw new Error(`unknown token ${c.name}:${token}`); if(!c.semantics||typeof c.semantics!=='object')throw new Error(`semantics missing ${c.name}`); }
for(const name of required)if(!names.has(name))throw new Error(`required component missing ${name}`);
const dialog=JSON.parse(fs.readFileSync(path.join(componentDir,'dialog.component.json'),'utf8')); if(dialog.semantics?.role!=='dialog'||dialog.semantics?.focusContainmentRequired!==true||dialog.semantics?.focusRestoreRequired!==true)throw new Error('dialog APG contract incomplete');
const chip=JSON.parse(fs.readFileSync(path.join(componentDir,'filter-chip.component.json'),'utf8')); if(chip.semantics?.navigationState!=='aria-current'||chip.semantics?.toggleState!=='aria-pressed')throw new Error('filter chip APG contract incomplete');
const button=JSON.parse(fs.readFileSync(path.join(componentDir,'button.component.json'),'utf8')); if(button.semantics?.nativeButtonPreferred!==true)throw new Error('button semantics missing');
const pagehead=JSON.parse(fs.readFileSync(path.join(componentDir,'pagehead-control.component.json'),'utf8')); if(pagehead.renderer!=='Prontoo\\Presentation\\UiComponents\\PageHeadControlPresentationOperations01')throw new Error('pagehead renderer contract mismatch');
const css=contract.sources.styles.map(rel=>fs.readFileSync(path.join(root,rel),'utf8')).join('\n');
if(!css.includes('container-type:inline-size')||!css.includes('@container'))throw new Error('container query capability missing');
if(!css.includes('forced-colors:active')||!css.includes('prefers-reduced-motion:reduce'))throw new Error('user preference support missing');
const lab=fs.readFileSync(path.join(root,'tests/presentation/ds-lab.html'),'utf8');
for(const needle of ['ds-filter-chip','role="dialog"','data-ds-container','ds-adaptive-group','aria-current="page"','aria-pressed="false"']) if(!lab.includes(needle))throw new Error(`DS Lab coverage missing ${needle}`);
process.stdout.write(`design-system-contract: architecture=dtcg-native-contract-driven-layered components=${files.length} states=${states.size} tokens=${tokenPaths.size} wcag=2.2-AA apg=1 debt=0\n`);
