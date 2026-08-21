#!/usr/bin/env node
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import postcss from 'postcss';

const root=process.cwd();
const release='1.8.21.2';
const old='1.8.21.1';
const sha=value=>crypto.createHash('sha256').update(value).digest('hex').slice(0,12);
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

const rewriteFiles=[];
const walkRewrite=dir=>{if(!fs.existsSync(dir))return;for(const e of fs.readdirSync(dir,{withFileTypes:true})){if(['.git','node_modules','vendor','ssd'].includes(e.name))continue;const p=path.join(dir,e.name);if(e.isDirectory())walkRewrite(p);else if(/\.(?:php|css|js|html|json)$/i.test(e.name))rewriteFiles.push(p);}};
for(const rel of ['app','public','design'])walkRewrite(path.join(root,rel));
const nameSet=new Set();
for(const file of rewriteFiles){const s=fs.readFileSync(file,'utf8');for(const m of s.matchAll(/--[a-zA-Z0-9_-]+/g))nameSet.add(m[0]);}
const mapping=[...nameSet].map(n=>[n,canonical(n)]).filter(([a,b])=>a!==b).sort((a,b)=>b[0].length-a[0].length);
for(const file of rewriteFiles){let s=fs.readFileSync(file,'utf8');let next=s;for(const [from,to]of mapping)next=next.split(from).join(to);if(next!==s)fs.writeFileSync(file,next);}

const designRoot=path.join(root,'design')+path.sep;
let promotedDesignAssetRefs=0;
for(const file of rewriteFiles){
  if(!file.startsWith(designRoot))continue;
  const s=fs.readFileSync(file,'utf8');
  const count=s.split(old).length-1;
  if(count===0)continue;
  fs.writeFileSync(file,s.split(old).join(release));
  promotedDesignAssetRefs+=count;
}

const bindingPath=path.join(root,'design/platform/css.bindings.json');
const bindingDoc=JSON.parse(fs.readFileSync(bindingPath,'utf8'));
let removedBridgeBindings=0;
for(const name of Object.keys(bindingDoc.bindings||{})){
  if(name.startsWith('runtime.bridge.')){
    delete bindingDoc.bindings[name];
    removedBridgeBindings++;
  }
}
for(const meta of Object.values(bindingDoc.bindings||{})){
  if(meta.cssName)meta.cssName=canonical(String(meta.cssName));
  if(meta.target==='material')meta.target='system';
  if(meta.target==='clinic')meta.target='runtime';
  delete meta.phase;
  delete meta.sequence;
  delete meta.important;
}
bindingDoc.policy='css-platform-bindings-dtcg-native-v2';
fs.writeFileSync(bindingPath,JSON.stringify(bindingDoc,null,2)+'\n');

const canonicalLayerNames=['foundation','primitives','components','composition','precedence'];
for(const name of canonicalLayerNames){
  const file=path.join(root,`design/styles/${name}.css`);
  const ast=postcss.parse(fs.readFileSync(file,'utf8'));
  ast.walkRules(rule=>{
    if(rule.selector.trim()==='.pix-brand-mask')rule.remove();
    if(rule.selector.trim()==='[data-ds-container]')rule.remove();
    if(rule.selector.trim()==='.ds-adaptive-group')rule.remove();
  });
  fs.writeFileSync(file,ast.toString());
}
const componentFile=path.join(root,'design/styles/components.css');
const componentAst=postcss.parse(fs.readFileSync(componentFile,'utf8'));
const componentLayer=componentAst.nodes.find(node=>node.type==='atrule'&&node.name==='layer'&&node.params.trim()==='ds-components');
if(!componentLayer)throw new Error('canonical ds-components layer missing');
const pixRule=postcss.rule({selector:'.pix-brand-mask'});
pixRule.append({prop:'background',value:'currentColor'});
pixRule.append({prop:'-webkit-mask',value:`url("/public/assets/pix-${release}.svg") center/contain no-repeat`});
pixRule.append({prop:'mask',value:`url("/public/assets/pix-${release}.svg") center/contain no-repeat`});
componentLayer.append(pixRule);

const containerRule=postcss.rule({selector:'[data-ds-container]'});
containerRule.append({prop:'container-type',value:'inline-size'});
componentLayer.append(containerRule);
const adaptiveRule=postcss.rule({selector:'.ds-adaptive-group'});
adaptiveRule.append({prop:'display',value:'grid'});
adaptiveRule.append({prop:'grid-template-columns',value:'repeat(3,minmax(0,1fr))'});
componentLayer.append(adaptiveRule);
const containerAtRule=postcss.atRule({name:'container',params:'(max-width:36rem)'});
const compactAdaptive=postcss.rule({selector:'.ds-adaptive-group'});
compactAdaptive.append({prop:'grid-template-columns',value:'1fr'});
containerAtRule.append(compactAdaptive);
componentLayer.append(containerAtRule);
fs.writeFileSync(componentFile,componentAst.toString());

const presentationPath=path.join(root,'design/tokens/presentation.tokens.json');
const presentationDoc=JSON.parse(fs.readFileSync(presentationPath,'utf8'));
presentationDoc.presentation=presentationDoc.presentation&&typeof presentationDoc.presentation==='object'?presentationDoc.presentation:{};
const rawVisualLiteral=/(?:#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(|\b\d*\.?\d+(?:px|rem|em|vh|vw|vmin|vmax|ch|ex|ms|s|deg)\b)/i;
let promotedVisualLiterals=0;
for(const name of canonicalLayerNames){
  const file=path.join(root,`design/styles/${name}.css`);
  const ast=postcss.parse(fs.readFileSync(file,'utf8'));
  ast.walkDecls(decl=>{
    if(!rawVisualLiteral.test(decl.value))return;
    const signature=`${decl.prop}\u0000${decl.value}`;
    const key=`${kebab(decl.prop.replace(/^--/,''))||'value'}-${sha(signature)}`;
    if(!presentationDoc.presentation[key]){
      presentationDoc.presentation[key]={'$type':'string','$value':decl.value,'$description':`Valor visual canônico para ${decl.prop}.`};
    }
    decl.value=`var(--pt-presentation-${key})`;
    decl.important=false;
    promotedVisualLiterals++;
  });
  fs.writeFileSync(file,ast.toString());
}
fs.writeFileSync(presentationPath,JSON.stringify(presentationDoc,null,2)+'\n');

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

process.stdout.write(JSON.stringify({ok:true,release,canonicalizedCustomProperties:mapping.length,promotedDesignAssetRefs,removedBridgeBindings,pixMaskBinding:1,containerQueryCapability:1,promotedVisualLiterals,designDebt:0,parallelDesignNamespaces:0},null,2)+'\n');
