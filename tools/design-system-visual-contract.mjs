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
const css = fs.readFileSync(path.join(root, 'public/assets/presentation.css'), 'utf8').replace(/^@import[^;]+;\s*/m, '');
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
const browser = await chromium.launch({ headless: true });
const cases = {};
try {
  for (const [themeName, theme] of Object.entries(themes)) {
    for (const [viewportName, viewport] of Object.entries(viewports)) {
      const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
      const page = await context.newPage();
      await page.setContent(html, { waitUntil: 'domcontentloaded' });
      await page.addStyleTag({ content: css });
      await page.evaluate(current => {
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
      }, theme);
      const screenshot = await page.screenshot({ fullPage: true, animations: 'disabled' });
      cases[`${themeName}-${viewportName}`] = crypto.createHash('sha256').update(screenshot).digest('hex');
      await context.close();
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
  throw new Error(`design system visual drift ${drift.join(',')}`);
}
process.stdout.write(`design-system-visual-contract: deterministic cases=${Object.keys(cases).length}\n`);
