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
const resolverPath = path.join(root, 'design/resolvers/application.resolver.json');
const bindingsPath = path.join(root, 'design/platform/css.bindings.json');
const generatedPath = path.join(root, 'design/generated/tokens.css');
if (!fs.existsSync(resolverPath) || !fs.existsSync(bindingsPath)) throw new Error('design resolver or css bindings missing');
const resolver = JSON.parse(fs.readFileSync(resolverPath, 'utf8'));
if (resolver.version !== '2025.10' || !Array.isArray(resolver.resolutionOrder)) throw new Error('invalid DTCG resolver');
const resolverDir = path.dirname(resolverPath);
const referencedFiles = new Set();
const collectRefs = value => {
  if (!value || typeof value !== 'object') return;
  if (Array.isArray(value)) { for (const item of value) collectRefs(item); return; }
  if (typeof value.$ref === 'string' && !value.$ref.startsWith('#')) referencedFiles.add(path.resolve(resolverDir, value.$ref.split('#')[0]));
  for (const child of Object.values(value)) collectRefs(child);
};
collectRefs(resolver.sets); collectRefs(resolver.modifiers);
const sourceFiles = [...referencedFiles].sort();
if (sourceFiles.length === 0 || sourceFiles.some(file => !fs.existsSync(file))) throw new Error('resolver references missing token sources');
const bindingDocument = JSON.parse(fs.readFileSync(bindingsPath, 'utf8'));
const bindings = bindingDocument.bindings && typeof bindingDocument.bindings === 'object' ? bindingDocument.bindings : {};
const targetOrder = ['runtime', 'system', 'semantic'];
const targetRank = new Map(targetOrder.map((target, index) => [target, index]));
const originalValue = token => token?.original?.$value ?? token?.$value ?? token?.original?.value ?? token?.value;
const transformedValue = token => token?.value ?? token?.$value;
const tokenPath = token => Array.isArray(token.path) ? token.path.join('.') : String(token.name || '');
const kebab = value => String(value).replace(/([a-z0-9])([A-Z])/g, '$1-$2').replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-+|-+$/g, '').toLowerCase();
const derivedBinding = name => ({ cssName:`--pt-${name.split('.').map(kebab).join('-')}`, target:'semantic', selector:':root', order:10000 });
const bindingFor = token => ({ ...derivedBinding(tokenPath(token)), ...(bindings[tokenPath(token)] || {}) });
const cssName = token => {
  const value = String(bindingFor(token).cssName || '').trim();
  if (!/^--pt-(?:ref|sys|cmp|runtime|presentation)-[a-z0-9-]+$/i.test(value)) throw new Error(`noncanonical cssName ${tokenPath(token)}:${value}`);
  return value;
};
const cssLiteral = value => {
  if (typeof value === 'string' || typeof value === 'number') return String(value);
  if (value && typeof value === 'object' && typeof value.hex === 'string') return String(value.hex);
  if (value && typeof value === 'object' && typeof value.value === 'number' && typeof value.unit === 'string') return `${value.value}${value.unit}`;
  throw new Error(`unsupported transformed token value ${JSON.stringify(value)}`);
};
const render = declarations => {
  const selectorOrder=[]; const grouped=new Map();
  for (const declaration of declarations) {
    if (!grouped.has(declaration.selector)) { grouped.set(declaration.selector,[]); selectorOrder.push(declaration.selector); }
    grouped.get(declaration.selector).push(declaration);
  }
  return selectorOrder.map(selector => `${selector}{\n${grouped.get(selector).map(item=>`  ${item.name}:${item.value};`).join('\n')}\n}`).join('\n\n');
};
StyleDictionary.registerFormat({
  name:'prontoo/dtcg-runtime-bundle',
  format:({dictionary})=>{
    const all=[...dictionary.allTokens];
    const names=new Map(all.map(token=>[tokenPath(token),cssName(token)]));
    const declarations=all.map(token=>{
      const meta=bindingFor(token);
      const target=String(meta.target||'');
      if (!targetRank.has(target)) throw new Error(`unknown target ${target}:${tokenPath(token)}`);
      if ('phase' in meta || 'sequence' in meta || 'important' in meta) throw new Error(`historical binding metadata ${tokenPath(token)}`);
      const raw=originalValue(token);
      let value=String(meta.cssValue||'').trim();
      if (!value) {
        if (typeof raw==='string' && /^\{[^{}]+\}$/.test(raw.trim())) {
          const refName=names.get(raw.trim().slice(1,-1));
          if (!refName) throw new Error(`unknown token reference ${raw}`);
          value=`var(${refName})`;
        } else value=cssLiteral(transformedValue(token));
      }
      value=value.replace(/\{([^{}]+)\}/g,(_,ref)=>{ const refName=names.get(ref); if(!refName) throw new Error(`unknown embedded token reference ${ref}`); return `var(${refName})`; });
      return {selector:String(meta.selector||':root'),name:cssName(token),value,target,order:Number(meta.order||0)};
    });
    const sections=[];
    for (const target of targetOrder) {
      const items=declarations.filter(item=>item.target===target).sort((a,b)=>a.order-b.order||a.name.localeCompare(b.name));
      if (items.length) sections.push(render(items));
    }
    return sections.filter(Boolean).join('\n\n')+'\n';
  }
});
const tempRoot=fs.mkdtempSync(path.join(os.tmpdir(),'prontoo-design-tokens-'));
try {
  const sd=new StyleDictionary({source:sourceFiles,usesDtcg:true,platforms:{css:{transformGroup:'css',buildPath:`${tempRoot}/`,files:[{destination:'tokens.css',format:'prontoo/dtcg-runtime-bundle',options:{showFileHeader:false}}]}}});
  await sd.buildAllPlatforms();
  const generated=fs.readFileSync(path.join(tempRoot,'tokens.css'),'utf8');
  if (generated.includes('!important')) throw new Error('generated token artifact contains important declaration');
  if (mode==='--write') { fs.mkdirSync(path.dirname(generatedPath),{recursive:true}); fs.writeFileSync(generatedPath,generated); }
  else {
    const current=fs.existsSync(generatedPath)?fs.readFileSync(generatedPath,'utf8'):'';
    if (current!==generated) { process.stderr.write('design-tokens-build: generated drift\n'); process.exit(1); }
  }
  process.stdout.write(`design-tokens-build: ${mode==='--write'?'written':'deterministic'} dtcg=2025.10 resolver=1 sources=${sourceFiles.length} bindings=${Object.keys(bindings).length} generated=1 historical-phases=0 important=0 parallel-design-targets=0\n`);
} finally { fs.rmSync(tempRoot,{recursive:true,force:true}); }
