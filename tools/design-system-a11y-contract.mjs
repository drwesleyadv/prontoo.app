#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import axe from 'axe-core';
import { chromium } from 'playwright';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const html = fs.readFileSync(path.join(root, 'tests/presentation/ds-lab.html'), 'utf8');
const css = fs.readFileSync(path.join(root, 'public/assets/presentation.css'), 'utf8').replace(/^@import[^;]+;\s*/m, '');
const browser = await chromium.launch({ headless: true });
try {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  const page = await context.newPage();
  await page.setContent(html, { waitUntil: 'domcontentloaded' });
  await page.addStyleTag({ content: css });
  await page.addScriptTag({ content: axe.source });
  const results = await page.evaluate(async () => await globalThis.axe.run(document, {
    runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'] }
  }));
  if (results.violations.length > 0) {
    const summary = results.violations.map(item => `${item.id}:${item.nodes.length}`).join(',');
    throw new Error(`axe violations ${summary}`);
  }
  const targets = await page.locator('a,button:not([disabled]),[role="button"],[tabindex]:not([tabindex="-1"])').evaluateAll(nodes => nodes.map(node => {
    const rect = node.getBoundingClientRect();
    const style = getComputedStyle(node);
    return {
      tag: node.tagName,
      width: rect.width,
      height: rect.height,
      text: (node.textContent || '').trim().slice(0, 40),
      className: String(node.className || ''),
      display: style.display,
      boxSizing: style.boxSizing,
      minBlockSize: style.minBlockSize,
      minInlineSize: style.minInlineSize,
      minHeight: style.minHeight,
      minWidth: style.minWidth,
      lineHeight: style.lineHeight,
      touchToken: style.getPropertyValue('--pt-sys-touch-minimum').trim()
    };
  }));
  for (const target of targets) {
    if (target.width < 24 || target.height < 24) throw new Error(`WCAG 2.2 target-size ${JSON.stringify(target)}`);
  }
  await page.emulateMedia({ forcedColors: 'active', reducedMotion: 'reduce' });
  await page.locator('.ds-filter-chip[aria-current="page"]').focus();
  const preferences = await page.locator('.ds-filter-chip[aria-current="page"]').evaluate(element => {
    const style = getComputedStyle(element);
    return {
      forcedColorAdjust: style.forcedColorAdjust,
      outlineStyle: style.outlineStyle,
      outlineWidth: style.outlineWidth
    };
  });
  if (preferences.forcedColorAdjust !== 'auto') throw new Error('forced-colors must preserve user-agent color adaptation');
  if (preferences.outlineStyle === 'none' || preferences.outlineWidth === '0px') throw new Error('forced-colors focus indicator missing');
  const reduced = await page.locator('[data-ds-motion]').count();
  await context.close();
  process.stdout.write(`design-system-a11y-contract: axe=4.12.1 violations=0 targets=${targets.length} wcag=2.2-AA forced-colors=1 reduced-motion-contract=${reduced >= 0 ? 1 : 0}\n`);
} finally {
  await browser.close();
}
