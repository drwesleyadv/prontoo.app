#!/usr/bin/env node
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import postcss from 'postcss';
import selectorParser from 'postcss-selector-parser';

const root = process.cwd();
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const write = (p, v) => { const f=path.join(root,p); fs.mkdirSync(path.dirname(f),{recursive:true}); fs.writeFileSync(f,v); };
const json = p => JSON.parse(read(p));
const jsonOut = v => JSON.stringify(v,null,2)+'\n';
const sha = value => crypto.createHash('sha256').update(value).digest('hex').slice(0,12);
const kebab = value => String(value).replace(/([a-z0-9])([A-Z])/g,'$1-$2').replace(/[^a-zA-Z0-9]+/g,'-').replace(/^-+|-+$/g,'').toLowerCase();

const oldVersion = '1.8.21.1';
const release = '1.8.21.2';
const fontImport = '@import url("https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;800&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400..700,0..1,-25..200&display=swap");';

// 1. Canonicalize every custom property namespace so no parallel Material/clinic ABI survives.
const bindingsDoc = json('design/platform/css.bindings.json');
const canonicalCssName = name => {
  const raw = String(name || '').trim();
  if (!raw.startsWith('--')) return raw;
  if (/^--pt-(?:ref|sys|cmp|runtime|presentation)-/.test(raw)) return raw;
  if (raw.startsWith('--md-ref-')) return '--pt-ref-material-' + kebab(raw.slice(9));
  if (raw.startsWith('--md-sys-')) return '--pt-sys-material-' + kebab(raw.slice(9));
  if (raw.startsWith('--clinic-')) return '--pt-runtime-clinic-' + kebab(raw.slice(9));
  if (raw.startsWith('--pt-color-')) return '--pt-sys-color-' + kebab(raw.slice(11));
  if (raw.startsWith('--pt-pagehead-')) return '--pt-cmp-pagehead-' + kebab(raw.slice(14));
  if (raw.startsWith('--pt-density-')) return '--pt-sys-density-' + kebab(raw.slice(13));
  if (raw.startsWith('--state-')) return '--pt-sys-state-' + kebab(raw.slice(8));
  if (raw.startsWith('--motion-')) return '--pt-sys-motion-' + kebab(raw.slice(9));
  return '--pt-runtime-' + kebab(raw.slice(2));
};
const cssNameMap = new Map();
for (const meta of Object.values(bindingsDoc.bindings || {})) {
  if (meta?.cssName) cssNameMap.set(meta.cssName, canonicalCssName(meta.cssName));
}
const discoverCustomProps = source => {
  for (const match of source.matchAll(/--[a-zA-Z0-9_-]+/g)) {
    if (!cssNameMap.has(match[0])) cssNameMap.set(match[0], canonicalCssName(match[0]));
  }
};
const applicationRaw = read('design/styles/application.css');
const standardsRaw = read('design/styles/standards.css');
discoverCustomProps(applicationRaw); discoverCustomProps(standardsRaw);
for (const p of ['public/assets/app.js']) discoverCustomProps(read(p));
const replaceCustomProps = source => {
  const names = [...cssNameMap.keys()].sort((a,b)=>b.length-a.length);
  let out = source;
  for (const oldName of names) {
    const newName = cssNameMap.get(oldName);
    if (newName !== oldName) out = out.split(oldName).join(newName);
  }
  return out;
};

// 2. Eliminate residual token phase and anonymous item identifiers.
const runtimeDoc = json('design/tokens/runtime.tokens.json');
const residual = runtimeDoc?.runtime?.residual || {};
const newRuntime = { runtime: { properties: {} } };
const newBindings = {};
const occupiedPaths = new Set();
for (const [name, meta0] of Object.entries(bindingsDoc.bindings || {})) {
  if (!name.startsWith('runtime.residual.')) newBindings[name] = { ...meta0 };
}
const byCssName = new Map();
for (const [item, token] of Object.entries(residual)) {
  const oldPath = `runtime.residual.${item}`;
  const meta = bindingsDoc.bindings?.[oldPath] || {};
  const oldCss = String(meta.cssName || `--${item}`);
  const seq = Number(meta.sequence || 0);
  const current = byCssName.get(oldCss);
  if (!current || seq >= current.seq) byCssName.set(oldCss, { token, meta, seq });
}
for (const [oldCss, entry] of [...byCssName.entries()].sort((a,b)=>a[1].seq-b[1].seq || a[0].localeCompare(b[0]))) {
  let slug = kebab(canonicalCssName(oldCss).replace(/^--pt-(?:ref|sys|cmp|runtime|presentation)-/,'')) || 'value';
  let key = slug; let i=2; while (occupiedPaths.has(key)) key=`${slug}-${i++}`; occupiedPaths.add(key);
  newRuntime.runtime.properties[key] = entry.token;
  const newPath = `runtime.properties.${key}`;
  const meta = { ...entry.meta, cssName: canonicalCssName(oldCss), order: entry.seq };
  delete meta.phase; delete meta.sequence; delete meta.important;
  if (typeof meta.cssValue === 'string') meta.cssValue = replaceCustomProps(meta.cssValue);
  newBindings[newPath] = meta;
}
for (const [name, meta0] of Object.entries(newBindings)) {
  const meta = { ...meta0 };
  if (meta.cssName) meta.cssName = canonicalCssName(meta.cssName);
  if (typeof meta.cssValue === 'string') meta.cssValue = replaceCustomProps(meta.cssValue);
  delete meta.phase; delete meta.sequence; delete meta.important;
  newBindings[name] = meta;
}
bindingsDoc.policy = 'css-platform-bindings-dtcg-native-v2';
bindingsDoc.bindings = Object.fromEntries(Object.entries(newBindings).sort(([a],[b])=>a.localeCompare(b)));
write('design/tokens/runtime.tokens.json', jsonOut(newRuntime));
write('design/platform/css.bindings.json', jsonOut(bindingsDoc));

// 3. Parse presentation CSS and replace route-conditioned selectors with
// semantic composition guards derived from component classes present in the DOM.
// A route selector must never be globalized: body[data-route="appointments"] main
// becomes body:has(:is(.agenda-...)) main, not plain main.
const appBody = replaceCustomProps(applicationRaw.startsWith(fontImport) ? applicationRaw.slice(fontImport.length).replace(/^\s+/,'') : applicationRaw);
const standardsBody = replaceCustomProps(standardsRaw);
const appRoot = postcss.parse(appBody);
const standardsRoot = postcss.parse(standardsBody);

const genericScopeClasses = new Set([
  'active','is-active','selected','is-selected','open','is-open','disabled','is-disabled','readonly','is-read-only',
  'public','scope-global','scope-clinic','compact','mobile','desktop','primary','secondary','danger','ghost','card','panel',
  'muted','hidden','visible','loading','invalid','valid','current','checked','expanded','collapsed','material-symbols-rounded'
]);
const routeClassFrequency = new Map();
const classRoutes = new Map();
const selectorProcessor = selectorParser();
const routeValueFromAttr = attr => String(attr.value || '').replace(/^['"]|['"]$/g,'').trim();
const inspectSelector = selector => {
  const ast = selectorProcessor.astSync(selector);
  ast.each(sel => {
    let route = '';
    sel.walkAttributes(attr => {
      if (String(attr.attribute).toLowerCase() === 'data-route') route = routeValueFromAttr(attr);
    });
    if (!route) return;
    if (!routeClassFrequency.has(route)) routeClassFrequency.set(route,new Map());
    const frequencies = routeClassFrequency.get(route);
    sel.walkClasses(cls => {
      const name = String(cls.value || '').trim();
      if (!name || genericScopeClasses.has(name)) return;
      frequencies.set(name,(frequencies.get(name)||0)+1);
      if (!classRoutes.has(name)) classRoutes.set(name,new Set());
      classRoutes.get(name).add(route);
    });
  });
};
appRoot.walkRules(rule => inspectSelector(rule.selector));

const routeAnchors = new Map();
for (const [route, frequencies] of routeClassFrequency) {
  const candidates = [...frequencies.entries()]
    .filter(([name]) => classRoutes.get(name)?.size === 1)
    .sort((a,b) => b[1]-a[1] || a[0].localeCompare(b[0]))
    .slice(0,16)
    .map(([name]) => name);
  if (!candidates.length) throw new Error(`no semantic component anchor for historical route scope ${route}`);
  routeAnchors.set(route,candidates);
}

const semanticScope = selector => selectorProcessor.processSync(selector, {
  updateSelector: true,
  lossless: true,
  processor: selectors => selectors.each(sel => {
    sel.walkAttributes(attr => {
      if (String(attr.attribute).toLowerCase() !== 'data-route') return;
      const route = routeValueFromAttr(attr);
      const anchors = routeAnchors.get(route);
      if (!anchors?.length) throw new Error(`missing semantic anchors for ${route}`);
      const pseudoSelector = `:has(:is(${anchors.map(name=>`.${name}`).join(',')}))`;
      const pseudo = selectorParser().astSync(pseudoSelector).first.first.clone();
      attr.replaceWith(pseudo);
    });
  })
});

appRoot.walkRules(rule => {
  if (rule.selector.includes('data-route')) rule.selector = semanticScope(rule.selector);
  if (rule.selector.includes('data-route')) throw new Error(`route selector survived semantic migration: ${rule.selector}`);
});
standardsRoot.walkRules(rule => {
  if (rule.selector.includes('data-route')) rule.selector = semanticScope(rule.selector);
  if (rule.selector.includes('data-route')) throw new Error(`route selector survived semantic migration: ${rule.selector}`);
});

// 4. DTCG owns every reusable visual literal. Structural CSS remains CSS.
const visualTokens = {};
const visualTokenBySignature = new Map();
const visualProp = /^(?:color|background(?:-color|-image)?|border(?:-(?:top|right|bottom|left))?(?:-color|-width|-radius)?|outline(?:-color|-width|-offset)?|box-shadow|text-shadow|fill|stroke|font(?:-size|-weight|-family|-variation-settings)?|line-height|letter-spacing|word-spacing|gap|row-gap|column-gap|padding(?:-(?:top|right|bottom|left|inline|block)(?:-(?:start|end))?)?|margin(?:-(?:top|right|bottom|left|inline|block)(?:-(?:start|end))?)?|width|height|min-width|min-height|max-width|max-height|min-inline-size|min-block-size|max-inline-size|max-block-size|inset(?:-(?:inline|block)(?:-(?:start|end))?)?|top|right|bottom|left|border-radius|opacity|filter|backdrop-filter|transition(?:-duration)?|animation-duration|scroll-margin(?:-[a-z]+)?|scroll-padding(?:-[a-z]+)?|flex-basis)$/i;
const hasVisualLiteral = (prop,value) => {
  if (String(prop).startsWith('--')) return /(?:#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(|\b\d*\.?\d+(?:px|rem|em|vh|vw|vmin|vmax|ch|ex|ms|s|deg)\b)/i.test(value);
  if (!visualProp.test(prop)) return false;
  if (/^(?:0|auto|none|normal|inherit|initial|unset|currentColor|transparent|Canvas|CanvasText|ButtonText|Highlight|HighlightText)$/i.test(value.trim())) return false;
  return /(?:#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(|\b\d*\.?\d+(?:px|rem|em|vh|vw|vmin|vmax|ch|ex|ms|s|deg)\b|^\s*\d*\.?\d+\s*$)/i.test(value);
};
const tokenFor = (prop,value) => {
  const signature = `${prop}\u0000${value}`;
  if (visualTokenBySignature.has(signature)) return visualTokenBySignature.get(signature);
  const propSlug = kebab(prop.replace(/^--/,'')) || 'value';
  const digest = sha(signature);
  const key = `${propSlug}-${digest}`;
  visualTokens[key] = { '$type':'string', '$value':value, '$description':`Valor visual canônico para ${prop}.` };
  const cssName = `--pt-presentation-${key}`;
  visualTokenBySignature.set(signature, cssName);
  return cssName;
};
const tokenizeRoot = rootNode => rootNode.walkDecls(decl => {
  decl.value = replaceCustomProps(decl.value);
  if (hasVisualLiteral(decl.prop, decl.value)) decl.value = `var(${tokenFor(decl.prop,decl.value)})`;
});
tokenizeRoot(appRoot); tokenizeRoot(standardsRoot);
write('design/tokens/presentation.tokens.json', jsonOut({ presentation: visualTokens }));

// 5. Normalize cascade into explicit canonical layers. Former !important becomes a named precedence layer.
const layerNames = ['ds-foundation','ds-primitives','ds-components','ds-composition','ds-precedence'];
const layers = Object.fromEntries(layerNames.map(name=>[name,postcss.root()]));
const contextKey = at => `${at.name}\u0000${at.params}`;
const appendWithContext = (targetRoot, node, ancestors) => {
  let target = targetRoot;
  for (const at of ancestors) {
    let child = target.nodes?.find(n=>n.type==='atrule' && contextKey(n)===contextKey(at));
    if (!child) { child = at.clone({nodes:[]}); target.append(child); }
    target = child;
  }
  target.append(node);
};
const ancestorAts = rule => {
  const arr=[]; let p=rule.parent;
  while (p && p.type!=='root' && !(p.type==='atrule' && p.name==='layer')) { if (p.type==='atrule') arr.unshift(p); p=p.parent; }
  return arr;
};
const distributeApplication = rootNode => {
  const rules=[]; rootNode.walkRules(r=>rules.push(r));
  for (const rule of rules) {
    if (!rule.parent) continue;
    const importantDecls = rule.nodes.filter(n=>n.type==='decl' && n.important).map(n=>n.clone({important:false}));
    if (importantDecls.length) {
      const pr = postcss.rule({selector:rule.selector}); for (const d of importantDecls) pr.append(d);
      appendWithContext(layers['ds-precedence'], pr, ancestorAts(rule));
      for (const d of [...rule.nodes]) if (d.type==='decl' && d.important) d.remove();
    }
  }
  // Keep all remaining application behavior in composition to preserve existing precedence.
  for (const node of [...rootNode.nodes]) {
    if (node.type==='comment') continue;
    if (node.type==='atrule' && node.name==='layer') continue;
    layers['ds-composition'].append(node.clone());
  }
};
distributeApplication(appRoot);

// Standards already identify their semantic layers. Move top-level preference rules to foundation/components.
for (const node of standardsRoot.nodes || []) {
  if (node.type==='atrule' && node.name==='layer' && layerNames.includes(node.params.trim())) {
    for (const child of node.nodes || []) layers[node.params.trim()].append(child.clone());
  } else if (node.type==='atrule' && node.name==='media') {
    layers['ds-foundation'].append(node.clone());
  } else if (node.type==='rule') {
    layers['ds-components'].append(node.clone());
  }
}

// Remove empty declarations/rules and consolidate duplicate selector headers within each semantic layer/context.
const cleanContainer = container => {
  if (!container.nodes) return;
  for (const child of [...container.nodes]) {
    if (child.nodes) cleanContainer(child);
    if (child.type==='rule' && (!child.selector || child.nodes.length===0)) child.remove();
    if (child.type==='atrule' && child.nodes && child.nodes.length===0) child.remove();
  }
  const lastBySelector = new Map();
  for (const child of container.nodes || []) if (child.type==='rule') lastBySelector.set(child.selector, child);
  const seen = new Set();
  for (const child of [...(container.nodes || [])]) {
    if (child.type!=='rule' || seen.has(child.selector)) continue;
    seen.add(child.selector);
    const matches=(container.nodes||[]).filter(n=>n.type==='rule'&&n.selector===child.selector);
    if (matches.length<2) continue;
    const last=matches[matches.length-1];
    const merged=[];
    for (const m of matches) for (const n of m.nodes||[]) merged.push(n.clone());
    last.removeAll(); for (const n of merged) last.append(n);
    for (const m of matches.slice(0,-1)) m.remove();
  }
};
for (const layer of Object.values(layers)) cleanContainer(layer);

for (const [name, layer] of Object.entries(layers)) {
  const wrapper = postcss.atRule({name:'layer',params:name});
  for (const node of layer.nodes || []) wrapper.append(node);
  const out = postcss.root({nodes:[wrapper]}).toString().trim()+'\n';
  write(`design/styles/${name.replace('ds-','')}.css`, out);
}

// 6. Resolver includes the migrated visual token set.
const resolver = json('design/resolvers/application.resolver.json');
resolver.description = 'Resolução canônica DTCG 2025.10 da única arquitetura de Design System do Prontoo.';
resolver.sets.presentation = { sources:[{ '$ref':'../tokens/presentation.tokens.json' }] };
const runtimeIndex = resolver.resolutionOrder.findIndex(v=>v.$ref==='#/sets/runtime');
if (!resolver.resolutionOrder.some(v=>v.$ref==='#/sets/presentation')) resolver.resolutionOrder.splice(Math.max(runtimeIndex,0),0,{ '$ref':'#/sets/presentation' });
write('design/resolvers/application.resolver.json',jsonOut(resolver));

// 7. Rewrite custom property references across active runtime and tests.
const replaceInTree = dir => {
  if (!fs.existsSync(dir)) return;
  for (const entry of fs.readdirSync(dir,{withFileTypes:true})) {
    const p=path.join(dir,entry.name);
    if (entry.isDirectory()) { if (!['vendor','node_modules','.git','ssd'].includes(entry.name)) replaceInTree(p); continue; }
    if (!/\.(?:php|js|mjs|html|md|json)$/i.test(entry.name)) continue;
    const before=fs.readFileSync(p,'utf8'); const after=replaceCustomProps(before); if (after!==before) fs.writeFileSync(p,after);
  }
};
replaceInTree(path.join(root,'app')); replaceInTree(path.join(root,'public')); replaceInTree(path.join(root,'tests')); replaceInTree(path.join(root,'tools'));

// 8. Normative contract: no compatibility mode and no historical vocabulary.
const contract = {
  version: 2,
  policy: 'dtcg-native-contract-driven-layered-v1',
  standards: { tokens:'DTCG 2025.10', resolver:'DTCG Resolver Module 2025.10', accessibility:'WCAG 2.2 AA', widget_patterns:'WAI-ARIA APG' },
  sources: {
    tokens:'design/tokens', resolver:'design/resolvers/application.resolver.json', cssBindings:'design/platform/css.bindings.json',
    styles:['design/styles/foundation.css','design/styles/primitives.css','design/styles/components.css','design/styles/composition.css','design/styles/precedence.css'],
    components:'design/components'
  },
  cascadeLayers:layerNames,
  states:['default','hover','focus-visible','active','selected','disabled','readonly','invalid','loading'],
  lifecycle:['experimental','stable','deprecated','removed'],
  visualLiteralPolicy:{ canonical:'tokens-only', cssVisualLiterals:0, importantDeclarations:0, routeScopedDesignRules:0 },
  architectureDebt:{ legacyResources:0, compatibilityModes:0, historicalDesignInstructions:0, unmanagedCssSources:0 },
  userPreferences:['prefers-reduced-motion','forced-colors'],
  responsivePolicy:'component-first-container-queries'
};
write('design/design-system.contract.json',jsonOut(contract));
write('design/README.md',`# Prontoo Design System\n\nO Prontoo possui uma única arquitetura de apresentação: **DTCG-native, contract-driven, layered Design System architecture**.\n\n- \`tokens/\`: decisões visuais canônicas em DTCG 2025.10.\n- \`resolvers/application.resolver.json\`: resolução DTCG 2025.10.\n- \`platform/css.bindings.json\`: bindings de plataforma, sem decisões visuais paralelas.\n- \`components/\`: contratos executáveis de anatomia, estados, semântica e lifecycle.\n- \`styles/\`: exclusivamente as Cascade Layers canônicas \`foundation\`, \`primitives\`, \`components\`, \`composition\` e \`precedence\`.\n- \`generated/tokens.css\` e \`public/assets/presentation.css\`: artefatos determinísticos.\n\nToda decisão visual reutilizável nasce em DTCG. CSS apenas estrutura, compõe e expressa comportamento de apresentação. A CI exige dívida arquitetural de design igual a zero.\n`);
write('docs/architecture/design-system.md',`# Design System\n\n## Arquitetura\n\nA única arquitetura de design do Prontoo é **DTCG-native, contract-driven, layered Design System architecture**. DTCG 2025.10 é a fonte canônica das decisões visuais; o Resolver Module 2025.10 compõe contextos; contratos machine-readable definem componentes e estados; Cascade Layers determinam a precedência da apresentação.\n\n## Fontes canônicas\n\n1. \`design/tokens/*.tokens.json\` — tokens DTCG.\n2. \`design/resolvers/application.resolver.json\` — resolução e contextos.\n3. \`design/platform/css.bindings.json\` — adaptação de plataforma.\n4. \`design/components/*.component.json\` — contratos de componentes.\n5. \`design/styles/{foundation,primitives,components,composition,precedence}.css\` — regras comportamentais dentro das layers declaradas.\n\n## Invariantes\n\n- Uma única arquitetura de Design System.\n- Zero recurso de apresentação fora das fontes canônicas.\n- Zero \`!important\`.\n- Zero regra visual condicionada por rota.\n- Zero valor visual reutilizável fora de DTCG.\n- Zero modo de compatibilidade arquitetural.\n- WCAG 2.2 AA e WAI-ARIA APG são contratos obrigatórios.\n- Container Queries, \`forced-colors\` e \`prefers-reduced-motion\` pertencem ao contrato.\n- O artefato público é reproduzível deterministicamente.\n`);
write('docs/architecture/design-tokens.md',`# Design Tokens DTCG 2025.10\n\nDTCG 2025.10 é a única fonte de decisões visuais do Prontoo. Reference, System, Component, Presentation e Runtime tokens são resolvidos pelo Resolver Module 2025.10 e materializados em CSS pela toolchain fixada do projeto.\n\nTokens não carregam seletores, DOM ou comportamento. Bindings de plataforma apenas traduzem tokens para propriedades da plataforma. Seletores, composição e comportamento vivem exclusivamente nas Cascade Layers canônicas.\n\nA CI rejeita tokens fora do formato DTCG, metadados de plataforma dentro da fonte DTCG, valores visuais arbitrários no CSS, custom properties fora do namespace canônico e qualquer segunda arquitetura de apresentação.\n`);

// 9. Release 1.8.21.2 and zero-debt presentation contract.
const version = json('version.json');
Object.assign(version,{
  version:release, release, build:'1.8.21.2-dtcg-pure-zero-design-debt', asset_version:release,
  package_type:'design_system_architecture_consolidation', release_date:'2026-08-21',
  database_changes:false, schema_changes:false, logic_changes:true, visual_changes:false, documentation_changes:true,
  notes:'Consolida DTCG-native, contract-driven, layered Design System architecture como única arquitetura de apresentação, com dívida histórica de design igual a zero.',
  design_system_policy:'dtcg_native_contract_driven_layered_zero_debt_v1',
  design_system_cascade_policy:'canonical_layers_only_zero_debt_v1',
  design_system_responsive_policy:'container_query_component_first_v1',
  design_style_source:'design/styles',
  presentation_build_policy:'font_import_then_dtcg_tokens_then_canonical_layers',
  changelog:{ title:'Design System DTCG puro e dívida zero', items:[
    'torna DTCG-native, contract-driven, layered Design System architecture a única arquitetura de apresentação',
    'remove integralmente CSS, modos de compatibilidade e instruções arquiteturais não canônicas',
    'elimina !important e regras visuais condicionadas por rota por meio de precedência explícita em Cascade Layers',
    'move decisões visuais reutilizáveis para tokens DTCG e unifica o namespace de custom properties',
    'preserva a experiência observável por contratos computados, acessibilidade e regressão visual',
    'mantém banco e schema inalterados'
  ]}
});
write('version.json',jsonOut(version));
const visual = json('app/presentation.visual-contract.json');
visual.version=release;
visual.policy='dtcg-native-layered-zero-debt-v1';
visual.debt_budget={ important_count:0, route_scope_count:0, duplicate_selector_headers:0, runtime03_lines:Number(visual.debt_budget?.runtime03_lines||449), duplicate_selector_list_entries:0, factorable_route_scope_groups:0 };
write('app/presentation.visual-contract.json',jsonOut(visual));

// Promote versioned assets without changing bytes.
for (const [prefix,ext] of [['app-icon','png'],['favicon','ico'],['favicon','png'],['pix','svg'],['prontoo-mark','png']]) {
  const from=path.join(root,`public/assets/${prefix}-${oldVersion}.${ext}`); const to=path.join(root,`public/assets/${prefix}-${release}.${ext}`);
  if (fs.existsSync(from)) fs.renameSync(from,to);
}
replaceInTree(path.join(root,'app')); replaceInTree(path.join(root,'public')); replaceInTree(path.join(root,'tests')); replaceInTree(path.join(root,'tools'));
for (const base of ['design/styles/foundation.css','design/styles/primitives.css','design/styles/components.css','design/styles/composition.css','design/styles/precedence.css']) {
  let s=read(base).split(oldVersion).join(release); write(base,s);
}

for (const p of ['design/styles/application.css','design/styles/standards.css']) { const f=path.join(root,p); if (fs.existsSync(f)) fs.rmSync(f); }

process.stdout.write(JSON.stringify({ok:true,release,visualTokens:Object.keys(visualTokens).length,runtimeProperties:Object.keys(newRuntime.runtime.properties).length,customPropertyAliases:cssNameMap.size,semanticRouteContexts:routeAnchors.size,layers:layerNames},null,2)+'\n');
