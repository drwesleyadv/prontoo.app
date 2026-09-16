#!/usr/bin/env bash
set -euo pipefail

python3 - <<'PY'
from pathlib import Path

path = Path('tools/presentation-ux-contract.mjs')
source = path.read_text()
if 'presentation-ux-contract: blocked agenda daily/weekly half-hour markers' not in source:
    marker = "process.stdout.write('presentation-ux-contract: developer metrics 1280=4x1 390=2x2\\n');"
    if source.count(marker) != 1:
        raise SystemExit(f'presentation UX output anchor count={source.count(marker)}')
    block = r'''const agendaAppJs = fs.readFileSync(path.join(root, 'public/assets/app.js'), 'utf8');
const agendaFunctionAt = agendaAppJs.indexOf('function agendaBlockedQueryAll(');
const agendaIifeStart = agendaAppJs.lastIndexOf('\n(function () {', agendaFunctionAt);
const agendaIifeEnd = agendaAppJs.indexOf('\n})();', agendaFunctionAt);
if (agendaFunctionAt < 0 || agendaIifeStart < 0 || agendaIifeEnd < 0) {
  throw new Error('blocked agenda presentation IIFE not found');
}
const agendaScript = agendaAppJs.slice(agendaIifeStart + 1, agendaIifeEnd + 6);
const agendaSlots = Array.from({ length: 36 }, (_, index) =>
  `<a class="agenda-day-slot" href="/create/${index}" style="--slot-index:${index}" data-agenda-slot="1"><span>${index}</span></a>`
).join('');
const agendaBrowser = await chromium.launch({ headless: true });
try {
  const context = await agendaBrowser.newContext({ viewport: { width: 1280, height: 1400 }, deviceScaleFactor: 1 });
  const page = await context.newPage();
  await page.setContent(`<body data-route="appointments">
    <section id="blocked-daily" class="agenda-day-shell is-day-blocked" style="--agenda-slots:36" data-agenda-day="2026-09-16" data-slot-minutes="15">
      <div class="agenda-day-scale">${agendaSlots}</div>
      <div class="agenda-day-canvas is-day-blocked" data-agenda-canvas data-day-blocked="1" data-slot-minutes="15">
        <div class="agenda-day-blocked-watermark"><span>Dia bloqueado</span></div>
      </div>
    </section>
    <section id="blocked-weekly" class="agenda-day-shell agenda-week-day-shell" style="--agenda-slots:36" data-agenda-day="2026-09-17" data-slot-minutes="15">
      <div class="agenda-day-scale">${agendaSlots}</div>
      <div class="agenda-day-canvas" data-agenda-canvas data-slot-minutes="15">
        <article class="agenda-day-event agenda-day-event-block" style="--event-top:0;--event-height:36"><strong>Bloqueado</strong></article>
      </div>
    </section>
  </body>`, { waitUntil: 'load' });
  await page.addScriptTag({ content: agendaScript });
  const agendaResult = await page.evaluate(() => {
    const inspect = id => {
      const shell = document.getElementById(id);
      const canvas = shell.querySelector('[data-agenda-canvas]');
      return {
        shellBlocked: shell.classList.contains('is-day-blocked'),
        canvasBlocked: canvas.classList.contains('is-day-blocked') && canvas.dataset.dayBlocked === '1',
        markerIndexes: Array.from(canvas.querySelectorAll('.agenda-day-blocked-watermark')).map(marker =>
          Number(marker.style.getPropertyValue('--blocked-slot-index'))
        ),
        activeCreateLinks: shell.querySelectorAll('.agenda-day-slot[href]').length,
        fullDayCardHidden: Boolean(canvas.querySelector('.agenda-day-event-block')?.hidden)
      };
    };
    return { daily: inspect('blocked-daily'), weekly: inspect('blocked-weekly') };
  });
  const expectedIndexes = Array.from({ length: 18 }, (_, index) => index * 2);
  for (const [surface, result] of Object.entries(agendaResult)) {
    if (!result.shellBlocked || !result.canvasBlocked) {
      throw new Error(`${surface} blocked state was not applied`);
    }
    if (JSON.stringify(result.markerIndexes) !== JSON.stringify(expectedIndexes)) {
      throw new Error(`${surface} expected 18 half-hour markers, got ${result.markerIndexes.join(',')}`);
    }
    if (result.activeCreateLinks !== 0) {
      throw new Error(`${surface} retained ${result.activeCreateLinks} create links while blocked`);
    }
  }
  if (!agendaResult.weekly.fullDayCardHidden) {
    throw new Error('weekly full-day block card remained visible instead of using blocked-day markers');
  }
  await context.close();
} finally {
  await agendaBrowser.close();
}
process.stdout.write('presentation-ux-contract: blocked agenda daily/weekly half-hour markers stable\n');
'''
    source = source.replace(marker, block + marker, 1)
    path.write_text(source)
PY

rm -f .github/workflows/add-agenda-blocked-browser-contract.yml tools/tmp-add-agenda-blocked-browser-contract.sh

php tools/release-contract-reconcile --write
php tools/release-contract-reconcile --write
php tools/release-contract-reconcile --check
php tools/release-version
npm ci --ignore-scripts
npx playwright install --with-deps chromium
node tools/presentation-ux-contract.mjs --check
php tools/test-fast
php tools/version-asset-contract-check.php
php tools/quality-gate --fast
git diff --check

git config user.name 'github-actions[bot]'
git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
git add -A
git commit -m 'Adiciona regressão comportamental para dias bloqueados'
git push origin HEAD:fix/agenda-blocked-day-rendering
