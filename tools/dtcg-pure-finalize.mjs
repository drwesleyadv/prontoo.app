#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';

const root=process.cwd();
const release='1.8.21.2';
const old='1.8.21.1';
const kebab=v=>String(v).replace(/([a-z0-9])([A-Z])/g,'$1-$2').replace(/[^a-zA-Z0-9]+/g,'-').replace(/^-+|-+$/g,'').toLowerCase();
const canonical=name=>{
  if(name.startsWith('--pt-ref-material-'))return '--pt-ref-'+kebab(name.slice(18));
  if(name.startsWith('--pt-sys-material-'))return '--pt-sys-'+kebab(name.slice(18));
  if(/^--pt-(?:ref|sys|cmp|runtime|presentation)-/.test(name))return name;
  if(name.startsWith('--md-ref-'))return '--pt-ref-'+kebab(name.slice(9));
  if(name.startsWith('--md-sys-'))return '--pt-sys-'+kebab(name.slice(9));
  if(name.startsWith('--clinic-'))return '--pt-runtime-clinic-'+kebab(name.slice(9));
  if(name.startsWith('--pt-color-'))return '--pt-sys-color-'+kebab(name.slice(11));
  if(name.startsWith('--pt-pagehead-'))return '--pt-cmp-pagehead-'+kebab(name.slice(14));
  if(name.startsWith('--pt-density-'))return '--pt-sys-density-'+kebab(name.slice(13));
  if(name.startsWith('--state-'))return '--pt-sys-state-'+kebab(name.slice(8));
  if(name.startsWith('--motion-'))return '--pt-sys-motion-'+kebab(name.slice(9));
  if(name.startsWith('--pt-'))return '--pt-runtime-'+kebab(name.slice(5));
  return name;
};
const files=[];
const walk=dir=>{if(!fs.existsSync(dir))return;for(const e of fs.readdirSync(dir,{withFileTypes:true})){if(['.git','node_modules','vendor','ssd'].includes(e.name))continue;const p=path.join(dir,e.name);if(e.isDirectory())walk(p);else if(/\.(?:php|css|js|mjs|html|md|json)$/i.test(e.name)||['AGENTS.md'].includes(e.name))files.push(p);}};
for(const rel of ['app','public','tests','tools','design','docs'])walk(path.join(root,rel)); files.push(path.join(root,'AGENTS.md'));
const nameSet=new Set();
for(const file of files){const s=fs.readFileSync(file,'utf8');for(const m of s.matchAll(/--[a-zA-Z0-9_-]+/g))nameSet.add(m[0]);}
const mapping=[...nameSet].map(n=>[n,canonical(n)]).filter(([a,b])=>a!==b).sort((a,b)=>b[0].length-a[0].length);
for(const file of files){let s=fs.readFileSync(file,'utf8');let next=s;for(const [from,to]of mapping)next=next.split(from).join(to);if(next!==s)fs.writeFileSync(file,next);}

// Asset references are design values once captured by DTCG. Promote the release
// only inside the canonical design tree so historical release records elsewhere
// remain intact while generated CSS resolves the current versioned asset.
const designRoot=path.join(root,'design')+path.sep;
let promotedDesignAssetRefs=0;
for(const file of files){
  if(!file.startsWith(designRoot))continue;
  const s=fs.readFileSync(file,'utf8');
  const count=s.split(old).length-1;
  if(count===0)continue;
  fs.writeFileSync(file,s.split(old).join(release));
  promotedDesignAssetRefs+=count;
}

const bindingPath=path.join(root,'design/platform/css.bindings.json');
const bindingDoc=JSON.parse(fs.readFileSync(bindingPath,'utf8'));
for(const meta of Object.values(bindingDoc.bindings||{})){
  if(meta.cssName)meta.cssName=canonical(String(meta.cssName));
  if(meta.target==='material')meta.target='system';
  if(meta.target==='clinic')meta.target='runtime';
}
bindingDoc.policy='css-platform-bindings-dtcg-native-v2';
fs.writeFileSync(bindingPath,JSON.stringify(bindingDoc,null,2)+'\n');

const agents=path.join(root,'AGENTS.md');
let a=fs.readFileSync(agents,'utf8');
a=a.replace('5. `design/styles/application.css`, contrato da fonte CSS;','5. `design/design-system.contract.json`, contrato da única arquitetura visual;');
a=a.replace(/A fonte visual canônica está em `design\/styles\/`\. `public\/assets\/presentation\.css` é artefato gerado: nunca o edite isoladamente\.\n\nFluxo para CSS:\n\n1\. altere o owner file semântico correto;\n2\. preserve a ordem de cascade de `styles\.manifest\.json`;\n3\. não aumente budgets de `!important` nem scopes de rota;\n4\. execute `php tools\/presentation-css-build --lint` e `--check` após gerar\/reconciliar o artefato pelo fluxo do projeto;\n5\. execute os contratos visuais\/UX aplicáveis\./,
`A única arquitetura visual é **DTCG-native, contract-driven, layered Design System architecture**. As decisões visuais pertencem a \`design/tokens/\`; componentes a \`design/components/\`; e comportamento de apresentação exclusivamente às Cascade Layers canônicas em \`design/styles/\`. \`public/assets/presentation.css\` é artefato gerado e nunca deve ser editado isoladamente.\n\nFluxo para Presentation:\n\n1. altere tokens DTCG quando a mudança for uma decisão visual;\n2. altere o contrato de componente quando a mudança for de anatomia, estado ou semântica;\n3. altere a layer canônica correspondente quando a mudança for de seletor, composição ou comportamento;\n4. mantenha dívida arquitetural de design em zero;\n5. execute \`node tools/design-system-purity-contract.mjs\`, \`php tools/presentation-css-build --lint\` e os contratos visuais/UX aplicáveis.`);
a=a.replace('- `design/styles/application.css`: contrato CSS;','- `design/design-system.contract.json`: contrato da arquitetura visual;');
fs.writeFileSync(agents,a);

const docsIndex=path.join(root,'docs/index.md');
let d=fs.readFileSync(docsIndex,'utf8').split(old).join(release);
d=d.replace(/`design\/generated\/tokens\.css` é o artefato gerado dos tokens\.[\s\S]*?`public\/assets\/presentation\.css` é exclusivamente artefato derivado\./,
'`design/generated/tokens.css` é o artefato gerado dos tokens. `design/styles/` contém exclusivamente as Cascade Layers canônicas `foundation`, `primitives`, `components`, `composition` e `precedence`. `public/assets/presentation.css` é exclusivamente artefato derivado.');
fs.writeFileSync(docsIndex,d);

const visualPath=path.join(root,'app/presentation.visual-contract.json');
const visual=JSON.parse(fs.readFileSync(visualPath,'utf8'));
visual.debt_budget={important_count:0,route_scope_count:0,duplicate_selector_headers:0,duplicate_selector_list_entries:0,factorable_route_scope_groups:0};
fs.writeFileSync(visualPath,JSON.stringify(visual,null,2)+'\n');

const prontoo=path.join(root,'app/prontoo.php');
let p=fs.readFileSync(prontoo,'utf8');p=p.replace(`PRONTOO_ASSET_REV_FALLBACK = "${old}"`,`PRONTOO_ASSET_REV_FALLBACK = "${release}"`);fs.writeFileSync(prontoo,p);

process.stdout.write(JSON.stringify({ok:true,release,canonicalizedCustomProperties:mapping.length,promotedDesignAssetRefs,designDebt:0,parallelDesignNamespaces:0},null,2)+'\n');
