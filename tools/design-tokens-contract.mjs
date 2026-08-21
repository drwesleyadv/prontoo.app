#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
execFileSync(process.execPath,[path.join(root,'tools/design-tokens-build.mjs'),'--check'],{stdio:'inherit'});
const resolverPath=path.join(root,'design/resolvers/application.resolver.json');
const bindingPath=path.join(root,'design/platform/css.bindings.json');
const generatedPath=path.join(root,'design/generated/tokens.css');
for(const file of [resolverPath,bindingPath,generatedPath])if(!fs.existsSync(file))throw new Error(`design source missing ${path.relative(root,file)}`);
const resolver=JSON.parse(fs.readFileSync(resolverPath,'utf8'));
if(resolver.$schema!=='https://www.designtokens.org/schemas/2025.10/resolver.json'||resolver.version!=='2025.10')throw new Error('resolver must be DTCG 2025.10');
for(const set of ['reference','system','components','presentation','runtime'])if(!resolver.sets?.[set])throw new Error(`resolver set missing ${set}`);
if(!resolver.modifiers?.surface?.contexts?.application||!resolver.modifiers?.surface?.contexts?.public||resolver.modifiers.surface.default!=='application')throw new Error('resolver surface contexts invalid');
if(!Array.isArray(resolver.resolutionOrder)||!resolver.resolutionOrder.some(v=>v.$ref==='#/sets/presentation'))throw new Error('presentation tokens absent from resolution order');
const tokenRoot=path.join(root,'design/tokens');
const files=fs.readdirSync(tokenRoot).filter(n=>n.endsWith('.tokens.json')).sort();
const tokenPaths=new Set(); const reserved=new Set(['$description','$type','$extends','$deprecated','$extensions','$root']);
const walk=(value,parts=[],inheritedType=null)=>{
  if(!value||typeof value!=='object'||Array.isArray(value))throw new Error(`invalid DTCG object ${parts.join('.')}`);
  if(Object.hasOwn(value,'$value')){const name=parts.join('.'); if(!name)throw new Error('root token without name'); if(value.$extensions?.['com.prontoo']!==undefined)throw new Error(`platform metadata in DTCG ${name}`); if(value.$type!==undefined&&typeof value.$type!=='string')throw new Error(`invalid type ${name}`); if(value.$type===undefined&&inheritedType===null&&!(typeof value.$value==='string'&&/^\{[^{}]+\}$/.test(value.$value)))throw new Error(`untyped token ${name}`); tokenPaths.add(name); return;}
  const next=typeof value.$type==='string'?value.$type:inheritedType;
  for(const [key,child] of Object.entries(value)){if(key.startsWith('$')){if(!reserved.has(key))throw new Error(`unknown DTCG reserved property ${key}`);if(key==='$root')walk(child,[...parts,'$root'],next);continue;}if(/[.{}]/.test(key))throw new Error(`invalid token name ${[...parts,key].join('.')}`);walk(child,[...parts,key],next);}
};
for(const file of files)walk(JSON.parse(fs.readFileSync(path.join(tokenRoot,file),'utf8')));
const runtime=JSON.parse(fs.readFileSync(path.join(tokenRoot,'runtime.tokens.json'),'utf8')); if(runtime?.runtime?.residual!==undefined)throw new Error('residual DTCG group forbidden');
const bindingDoc=JSON.parse(fs.readFileSync(bindingPath,'utf8')); if(bindingDoc.policy!=='css-platform-bindings-dtcg-native-v2')throw new Error('binding policy mismatch');
const bindings=bindingDoc.bindings||{}; const allowedTargets=new Set(['runtime','system','semantic']);
const kebab=v=>String(v).replace(/([a-z0-9])([A-Z])/g,'$1-$2').replace(/[^a-zA-Z0-9]+/g,'-').replace(/^-+|-+$/g,'').toLowerCase(); const derived=name=>`--pt-${name.split('.').map(kebab).join('-')}`;
for(const [name,meta] of Object.entries(bindings)){if(!tokenPaths.has(name))throw new Error(`orphan binding ${name}`);if(!allowedTargets.has(String(meta.target||'')))throw new Error(`unknown target ${name}:${meta.target}`);if('phase'in meta||'sequence'in meta||'important'in meta)throw new Error(`historical binding metadata ${name}`);if(!/^--pt-(?:ref|sys|cmp|runtime|presentation)-[a-z0-9-]+$/i.test(String(meta.cssName||'')))throw new Error(`noncanonical cssName ${name}:${meta.cssName}`);}
const generated=fs.readFileSync(generatedPath,'utf8'); if(!generated.trim())throw new Error('generated token artifact empty'); if(generated.includes('!important'))throw new Error('important token output'); if(/--(?!pt-(?:ref|sys|cmp|runtime|presentation)-)[a-z0-9_-]+/i.test(generated))throw new Error('parallel token namespace in generated CSS');
for(const name of tokenPaths){const cssName=String(bindings[name]?.cssName||derived(name));if(!generated.includes(`${cssName}:`))throw new Error(`generated token missing ${name}:${cssName}`);}
process.stdout.write(`design-tokens-contract: dtcg=2025.10 resolver=1 sources=${files.length} tokens=${tokenPaths.size} bindings=${Object.keys(bindings).length} residual=0 historical-metadata=0 parallel-namespaces=0 parallel-design-targets=0\n`);
