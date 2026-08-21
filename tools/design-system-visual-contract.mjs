#!/usr/bin/env node
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const mode = process.argv[2] || '--check';
if (!['--check', '--write'].includes(mode)) {
  process.stderr.write('design-system-visual-contract: use --check or --write\n');
  process.exit(2);
}
const baselinePath = path.join(root, 'app/design-system.visual-baseline.json');
const html = fs.readFileSync(path.join(root, 'tests/presentation/ds-lab.html'), 'utf8');
const stripImport = source => source.replace(/^@import[^;]+;\s*/m, '');
const css = stripImport(fs.readFileSync(path.join(root, 'public/assets/presentation.css'), 'utf8'));
const beforePath = '/tmp/prontoo-presentation-before-migration.css';
const beforeCss = fs.existsSync(beforePath) ? stripImport(fs.readFileSync(beforePath, 'utf8')) : null;
const themes = {
  green: { accent: '#2f6652', strong: '#1d583f', soft: '#d9f0df' },
  blue: { accent: '#2563eb', strong: '#1d4ed8', soft: '#eff6ff' },
  wine: { accent: '#8a2432', strong: '#6f1d2a', soft: '#fff0f2' },
  violet: { accent: '#6d3bc3', strong: '#53309a', soft: '#f4f1ff' }
};
const viewports = {
  desktop: { width: 1280, height: 900 },
  compact: { width: 390, height: 844 }
};
const applyTheme = current => {
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
};
const styleProps = [
  'display','position','width','height','minWidth','minHeight','maxWidth','maxHeight',
  'marginTop','marginRight','marginBottom','marginLeft','paddingTop','paddingRight','paddingBottom','paddingLeft',
  'gap','rowGap','columnGap','color','backgroundColor','backgroundImage','borderTopWidth','borderTopStyle','borderTopColor',
  'borderRadius','fontFamily','fontSize','fontWeight','lineHeight','letterSpacing','boxShadow','opacity','transform','outlineStyle','outlineWidth'
];
const capture = async (browser, sheet, theme, viewport, includeStyles = false) => {
  const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
  const page = await context.newPage();
  await page.setContent(html, { waitUntil: 'domcontentloaded' });
  await page.addStyleTag({ content: sheet });
  await page.evaluate(applyTheme, theme);
  const screenshot = await page.screenshot({ fullPage: true, animations: 'disabled' });
  const result = { hash: crypto.createHash('sha256').update(screenshot).digest('hex') };
  if (includeStyles) {
    result.styles = await page.locator('body,main,[class*="ds-"]').evaluateAll((nodes, props) => nodes.map((node, index) => {
      const style = getComputedStyle(node);
      const rect = node.getBoundingClientRect();
      const values = {};
      for (const prop of props) values[prop] = style[prop];
      return {
        key: `${index}:${node.tagName.toLowerCase()}.${String(node.className || '').trim().replace(/\s+/g,'.')}`,
        rect: [rect.x,rect.y,rect.width,rect.height].map(value => Math.round(value * 100) / 100),
        values
      };
    }), styleProps);
    result.ruleTrace = await page.evaluate(() => {
      const targets = {
        main: 'main#ds-lab',
        primary: 'button.primary',
        filter: '.ds-filter-chip'
      };
      const relevant = new Set([
        'min-height','min-block-size','height','padding','padding-top','padding-right','padding-bottom','padding-left','padding-inline',
        'gap','row-gap','column-gap','border-radius','width','max-width','margin','margin-left','margin-right','margin-top','margin-bottom'
      ]);
      const output = {};
      const describeGroup = rule => {
        const text = String(rule.cssText || '');
        const brace = text.indexOf('{');
        return (brace >= 0 ? text.slice(0, brace) : text).trim().replace(/\s+/g,' ').slice(0,240);
      };
      const walk = (rules, element, chain, rows, order) => {
        for (const rule of Array.from(rules || [])) {
          const nextOrder = order.value++;
          if (rule.selectorText) {
            let matches = false;
            try { matches = element.matches(rule.selectorText); } catch {}
            if (matches) {
              const declarations = {};
              for (const prop of Array.from(rule.style || [])) {
                if (!relevant.has(prop)) continue;
                declarations[prop] = {
                  value: rule.style.getPropertyValue(prop).trim(),
                  important: rule.style.getPropertyPriority(prop) === 'important'
                };
              }
              if (Object.keys(declarations).length) rows.push({order:nextOrder, selector:rule.selectorText, chain, declarations});
            }
          }
          if (rule.cssRules) walk(rule.cssRules, element, [...chain, describeGroup(rule)], rows, order);
        }
      };
      for (const [name, selector] of Object.entries(targets)) {
        const element = document.querySelector(selector);
        const rows = [];
        if (element) {
          const order = {value:0};
          for (const sheet of Array.from(document.styleSheets)) walk(sheet.cssRules, element, [], rows, order);
          const computed = getComputedStyle(element);
          output[name] = {
            computed: {
              minHeight:computed.minHeight,
              minBlockSize:computed.minBlockSize,
              height:computed.height,
              padding:computed.padding,
              gap:computed.gap,
              borderRadius:computed.borderRadius,
              width:computed.width,
              margin:computed.margin
            },
            rules: rows
          };
        }
      }
      return output;
    });
  }
  await context.close();
  return result;
};
const browser = await chromium.launch({ headless: true });
const cases = {};
const beforeCases = {};
const diagnostics = {};
const ruleDiagnostics = {};
try {
  for (const [themeName, theme] of Object.entries(themes)) {
    for (const [viewportName, viewport] of Object.entries(viewports)) {
      const key = `${themeName}-${viewportName}`;
      const after = await capture(browser, css, theme, viewport, beforeCss !== null && themeName === 'green');
      cases[key] = after.hash;
      if (beforeCss !== null) {
        const before = await capture(browser, beforeCss, theme, viewport, themeName === 'green');
        beforeCases[key] = before.hash;
        if (themeName === 'green') {
          const diffs = [];
          const max = Math.max(before.styles?.length || 0, after.styles?.length || 0);
          for (let index = 0; index < max; index++) {
            const a = before.styles?.[index];
            const b = after.styles?.[index];
            if (!a || !b) { diffs.push({ index, before:a?.key || null, after:b?.key || null }); continue; }
            const changed = {};
            if (JSON.stringify(a.rect) !== JSON.stringify(b.rect)) changed.rect = [a.rect,b.rect];
            for (const prop of styleProps) if (a.values[prop] !== b.values[prop]) changed[prop] = [a.values[prop],b.values[prop]];
            if (Object.keys(changed).length) diffs.push({ key:a.key, changed });
          }
          diagnostics[key] = diffs.slice(0, 40);
          ruleDiagnostics[key] = {before:before.ruleTrace,after:after.ruleTrace};
        }
      }
    }
  }
} finally {
  await browser.close();
}
const current = {
  policy: 'ds-lab-playwright-visual-regression-v1',
  playwright: '1.59.1',
  cases
};
if (mode === '--write') {
  fs.writeFileSync(baselinePath, JSON.stringify(current, null, 2) + '\n');
  process.stdout.write(`design-system-visual-contract: written cases=${Object.keys(cases).length}\n`);
  process.exit(0);
}
if (!fs.existsSync(baselinePath)) throw new Error('design system visual baseline missing');
const expected = JSON.parse(fs.readFileSync(baselinePath, 'utf8'));
if (JSON.stringify(expected) !== JSON.stringify(current)) {
  const drift = Object.keys(cases).filter(key => expected.cases?.[key] !== cases[key]);
  if (beforeCss !== null) {
    process.stderr.write(`design-system-visual-before-matches-baseline=${Object.keys(beforeCases).every(key => expected.cases?.[key] === beforeCases[key]) ? 1 : 0}\n`);
    process.stderr.write(`design-system-visual-hashes=${JSON.stringify({expected:expected.cases,before:beforeCases,after:cases})}\n`);
    process.stderr.write(`design-system-visual-style-diff=${JSON.stringify(diagnostics)}\n`);
    process.stderr.write(`design-system-visual-rule-trace=${JSON.stringify(ruleDiagnostics)}\n`);
  }
  throw new Error(`design system visual drift ${drift.join(',')}`);
}
process.stdout.write(`design-system-visual-contract: deterministic cases=${Object.keys(cases).length}\n`);
