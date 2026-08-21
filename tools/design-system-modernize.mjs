#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const tokenRoot = path.join(root, 'design/tokens');
const bindingPath = path.join(root, 'design/platform/css.bindings.json');
const allowedBindingKeys = new Set(['cssName', 'target', 'selector', 'order', 'cssValue', 'important', 'phase', 'sequence']);
const bindings = {};

const files = fs.readdirSync(tokenRoot)
  .filter(name => name.endsWith('.tokens.json'))
  .sort()
  .map(name => path.join(tokenRoot, name));

const clean = (value, parts = []) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return;
  if (Object.hasOwn(value, '$value')) {
    const vendor = value.$extensions?.['com.prontoo'];
    if (vendor !== undefined) {
      if (!vendor || typeof vendor !== 'object' || Array.isArray(vendor)) throw new Error(`invalid binding ${parts.join('.')}`);
      for (const key of Object.keys(vendor)) {
        if (!allowedBindingKeys.has(key)) throw new Error(`unknown binding metadata ${parts.join('.')}:${key}`);
      }
      bindings[parts.join('.')] = vendor;
      delete value.$extensions['com.prontoo'];
      if (Object.keys(value.$extensions).length === 0) delete value.$extensions;
    }
  }
  for (const [key, child] of Object.entries(value)) {
    if (key.startsWith('$')) continue;
    clean(child, [...parts, key]);
  }
};

for (const file of files) {
  const data = JSON.parse(fs.readFileSync(file, 'utf8'));
  clean(data);
  fs.writeFileSync(file, JSON.stringify(data, null, 2) + '\n');
}

const orderedBindings = Object.fromEntries(Object.entries(bindings).sort(([a], [b]) => a.localeCompare(b)));
fs.mkdirSync(path.dirname(bindingPath), { recursive: true });
fs.writeFileSync(bindingPath, JSON.stringify({
  version: 1,
  policy: 'css-platform-bindings-outside-dtcg-v1',
  bindings: orderedBindings
}, null, 2) + '\n');

const release = '1.8.21.1';
const timestamp = '2026-08-21T16:14:00-04:00';
const versionPath = path.join(root, 'version.json');
const version = JSON.parse(fs.readFileSync(versionPath, 'utf8'));
Object.assign(version, {
  version: release,
  release,
  generated_at_unix: 1787343240,
  generated_at: timestamp,
  updated_at: timestamp,
  build: '1.8.21.1-modern-design-system-contracts',
  package_type: 'incremental_design_system_modernization',
  database_changes: false,
  schema_changes: false,
  logic_changes: true,
  visual_changes: true,
  documentation_changes: true,
  functional_equivalence_policy: 'default_product_visuals_preserved_while_new_design_system_primitives_are_token_driven_accessible_and_contract_verified',
  notes: 'Moderniza o Design System com DTCG Resolver 2025.10, bindings CSS externos aos tokens, contratos de componentes, WCAG 2.2 AA, APG, cascade layers, container queries, forced-colors, DS Lab, axe-core e regressão visual.',
  release_date: '2026-08-21',
  deployment_sync_id: 'github-prontoo-1.8.21.1-modern-design-system-contracts',
  deployment_sync_requested_at: timestamp,
  design_system_policy: 'dtcg_2025_10_resolver_component_contracts_wcag22_apg_v1',
  design_system_resolver: 'design/resolvers/application.resolver.json',
  design_system_css_bindings: 'design/platform/css.bindings.json',
  design_system_component_contract: 'design/design-system.contract.json',
  design_system_accessibility_policy: 'wcag_2_2_aa_wai_aria_apg_axe_playwright',
  design_system_cascade_policy: 'layered_new_primitives_legacy_ratchet_v1',
  design_system_responsive_policy: 'container_query_component_first_v1'
});
version.changelog = {
  title: 'Design System moderno e normativo',
  items: [
    'adota o Resolver Module DTCG 2025.10 e separa metadados de plataforma CSS da fonte de tokens',
    'formaliza contratos executáveis para componentes, estados, acessibilidade WCAG 2.2 AA e padrões WAI-ARIA APG',
    'introduz Cascade Layers, Container Queries, forced-colors e reduced-motion nas novas primitivas canônicas',
    'adiciona catálogo executável DS Lab com Playwright, axe-core e regressão visual determinística',
    'impede novos valores visuais arbitrários no CSS moderno e mantém a folha legada sob ratchet sem aumentar dívida',
    'preserva banco, schema e o baseline visual padrão do produto'
  ]
};
fs.writeFileSync(versionPath, JSON.stringify(version, null, 4) + '\n');

const visualPath = path.join(root, 'app/presentation.visual-contract.json');
const visual = JSON.parse(fs.readFileSync(visualPath, 'utf8'));
visual.version = release;
fs.writeFileSync(visualPath, JSON.stringify(visual, null, 4) + '\n');

const docsIndexPath = path.join(root, 'docs/index.md');
let docsIndex = fs.readFileSync(docsIndexPath, 'utf8');
docsIndex = docsIndex.replace(/`1\.8\.18\.7`/g, '`1.8.21.1`');
fs.writeFileSync(docsIndexPath, docsIndex);

process.stdout.write(`design-system-modernize: tokens=${files.length} bindings=${Object.keys(orderedBindings).length} release=${release}\n`);
