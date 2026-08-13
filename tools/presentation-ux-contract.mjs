import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const fixture = path.join(root, 'tests/presentation/ux-fixture.html');
const baselinePath = path.join(root, 'app/presentation.ux-baseline.json');
const mode = process.argv[2] || '--check';
const targets = [
  'topbar','cmdbar','pagehead','nav-group','nav-idle','nav-active','primary','secondary','danger',
  'grid','card','stat','form-card','field','input','form-actions','feedback','pill',
  'finance-workspace','finance-guides','finance-guide','finance-form-stack','finance-row',
  'patient-hero','patient-tabs-shell','patient-tab-nav','patient-panel','patient-data-list',
  'procedure-card','procedure-form-panel','procedure-hero','procedure-summary','procedure-form'
];
const properties = [
  'display','position','top','zIndex','boxSizing','width','height','minWidth','minHeight','maxWidth','maxHeight',
  'paddingTop','paddingRight','paddingBottom','paddingLeft','marginTop','marginRight','marginBottom','marginLeft',
  'gap','rowGap','columnGap','borderTopWidth','borderRightWidth','borderBottomWidth','borderLeftWidth',
  'borderTopStyle','borderRightStyle','borderBottomStyle','borderLeftStyle','borderTopColor','borderRightColor',
  'borderBottomColor','borderLeftColor','borderTopLeftRadius','borderTopRightRadius','borderBottomRightRadius',
  'borderBottomLeftRadius','backgroundColor','color','boxShadow','opacity','visibility','overflow','overflowX','overflowY',
  'fontFamily','fontSize','fontWeight','lineHeight','letterSpacing','textAlign','whiteSpace','alignItems','justifyContent',
  'flexDirection','flexWrap','gridTemplateColumns','transform'
];
const customProperties = [
  '--md-sys-color-primary','--md-sys-color-on-primary','--md-sys-color-primary-container','--md-sys-color-on-primary-container',
  '--clinic-identity-accent','--clinic-identity-hover','--clinic-identity-readonly','--pt-pagehead-control-height',
  '--pt-pagehead-control-radius','--pt-pagehead-control-gap','--pt-pagehead-control-icon-size'
];
const scenarios = [
  { name: 'desktop', width: 1280, height: 1200 },
  { name: 'appointments', width: 1280, height: 1200, route: 'appointments' },
  { name: 'readonly', width: 1280, height: 1200, readonly: true },
  { name: 'primary-hover', width: 1280, height: 1200, hover: '#primary' },
  { name: 'mobile', width: 390, height: 1200 },
  { name: 'mobile-appointments', width: 390, height: 1200, route: 'appointments' },
  { name: 'financial', width: 1280, height: 1600, route: 'financial' },
  { name: 'mobile-financial', width: 390, height: 1800, route: 'financial' },
  { name: 'patient', width: 1280, height: 1800, route: 'patient' },
  { name: 'mobile-patient', width: 390, height: 2200, route: 'patient' },
  { name: 'procedures', width: 1280, height: 1800, route: 'procedures' },
  { name: 'mobile-procedures', width: 390, height: 2200, route: 'procedures' }
];

function stable(value) {
  if (Array.isArray(value)) return value.map(stable);
  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.keys(value).sort().map(key => [key, stable(value[key])]));
  }
  return value;
}

const browser = await chromium.launch({ headless: true });
const output = {
  policy: 'presentation-computed-style-ux-v2',
  playwright: '1.59.1',
  fixture: 'tests/presentation/ux-fixture.html',
  scenarios: {}
};
try {
  for (const scenario of scenarios) {
    const context = await browser.newContext({ viewport: { width: scenario.width, height: scenario.height }, deviceScaleFactor: 1 });
    const page = await context.newPage();
    await page.route('https://fonts.googleapis.com/**', route => route.fulfill({ status: 200, contentType: 'text/css', body: '' }));
    await page.route('https://fonts.gstatic.com/**', route => route.fulfill({ status: 204, body: '' }));
    await page.goto(pathToFileURL(fixture).href, { waitUntil: 'load' });
    await page.evaluate(({ route, readonly }) => {
      document.body.style.setProperty('--clinic-identity-accent', '#2f6652');
      document.body.style.setProperty('--clinic-identity-hover', '#295a48');
      document.body.style.setProperty('--clinic-identity-pressed', '#244d40');
      document.body.style.setProperty('--clinic-identity-on-accent', '#ffffff');
      document.body.style.setProperty('--clinic-identity-readonly', '#52645a');
      document.body.style.setProperty('--clinic-identity-readonly-hover', '#48584f');
      document.body.style.setProperty('--clinic-identity-on-readonly', '#ffffff');
      if (route) document.body.dataset.route = route;
      if (readonly) document.body.classList.add('is-read-only');
    }, { route: scenario.route || '', readonly: Boolean(scenario.readonly) });
    await page.addStyleTag({ content: '*,*::before,*::after{transition:none!important;animation:none!important;caret-color:transparent!important}' });
    if (scenario.hover) await page.hover(scenario.hover);
    const snapshot = await page.evaluate(({ ids, props, custom }) => {
      const result = {};
      for (const id of ids) {
        const element = document.getElementById(id);
        if (!element) throw new Error(`missing fixture target ${id}`);
        const style = getComputedStyle(element);
        const values = {};
        for (const prop of props) values[prop] = style[prop];
        const variables = {};
        for (const prop of custom) variables[prop] = style.getPropertyValue(prop).trim();
        const rect = element.getBoundingClientRect();
        result[id] = {
          rect: {
            x: Number(rect.x.toFixed(3)), y: Number(rect.y.toFixed(3)), width: Number(rect.width.toFixed(3)), height: Number(rect.height.toFixed(3))
          },
          style: values,
          variables
        };
      }
      return result;
    }, { ids: targets, props: properties, custom: customProperties });
    output.scenarios[scenario.name] = snapshot;
    await context.close();
  }
} finally {
  await browser.close();
}

const normalized = JSON.stringify(stable(output), null, 2) + '\n';
if (mode === '--write') {
  fs.writeFileSync(baselinePath, normalized);
  process.stdout.write(`presentation-ux-contract: baseline written ${baselinePath}\n`);
  process.exit(0);
}
if (mode !== '--check') {
  process.stderr.write('presentation-ux-contract: use --check or --write\n');
  process.exit(1);
}
if (!fs.existsSync(baselinePath)) {
  process.stderr.write('presentation-ux-contract: baseline missing\n');
  process.exit(1);
}
const expected = fs.readFileSync(baselinePath, 'utf8');
if (expected !== normalized) {
  const expectedJson = JSON.parse(expected);
  const actualJson = JSON.parse(normalized);
  const differences = [];
  for (const scenario of Object.keys(actualJson.scenarios)) {
    const expectedScenario = expectedJson.scenarios?.[scenario] || {};
    const actualScenario = actualJson.scenarios[scenario];
    for (const id of Object.keys(actualScenario)) {
      const e = JSON.stringify(expectedScenario[id]);
      const a = JSON.stringify(actualScenario[id]);
      if (e !== a) differences.push(`${scenario}:${id}`);
    }
  }
  process.stderr.write(`presentation-ux-contract: computed-style drift ${differences.slice(0, 40).join(', ')}\n`);
  process.exit(1);
}
const telemetryCss = fs.readFileSync(path.join(root, 'public/assets/presentation.css'), 'utf8').replace(/^@import[^;]+;\s*/m, '');
const telemetryCards = tag => Array.from({ length: 4 }, (_, index) => `<${tag} class="stat-card telemetry-kpi-card"><span class="material-symbols-rounded">speed</span><div><b>${index + 1}</b><span>KPI</span></div></${tag}>`).join('');
const telemetryFixtures = {
  status: `<body class="public scope-global status-public"><main id="conteudo"><div id="telemetry-layout-target" class="wide global-telemetry-grid">${telemetryCards('article')}</div></main></body>`,
  developer: `<body class="scope-global"><main id="conteudo"><section id="telemetry-layout-target" class="card admin-telemetry-card"><h2 class="wide">Telemetria do sistema</h2>${telemetryCards('a')}</section></main></body>`
};
const telemetryExpectations = [
  { width: 1280, columns: 4, rows: 1 },
  { width: 390, columns: 2, rows: 2 }
];
const telemetryBrowser = await chromium.launch({ headless: true });
try {
  for (const [surface, markup] of Object.entries(telemetryFixtures)) {
    for (const expectedLayout of telemetryExpectations) {
      const context = await telemetryBrowser.newContext({ viewport: { width: expectedLayout.width, height: 900 }, deviceScaleFactor: 1 });
      const page = await context.newPage();
      await page.setContent(`<style>${telemetryCss}</style>${markup}`, { waitUntil: 'load' });
      const result = await page.evaluate(() => {
        const target = document.getElementById('telemetry-layout-target');
        const cards = Array.from(target.children).filter(element => element.classList.contains('telemetry-kpi-card'));
        return {
          columns: getComputedStyle(target).gridTemplateColumns.trim().split(/\s+/).filter(Boolean).length,
          tops: cards.map(element => Math.round(element.getBoundingClientRect().top))
        };
      });
      const rows = [...new Set(result.tops)].length;
      if (result.columns !== expectedLayout.columns || rows !== expectedLayout.rows) {
        throw new Error(`telemetry layout ${surface} ${expectedLayout.width}px: expected ${expectedLayout.columns} columns/${expectedLayout.rows} rows, got ${result.columns}/${rows}: ${result.tops.join(',')}`);
      }
      if (expectedLayout.rows === 1 && !result.tops.every(top => top === result.tops[0])) {
        throw new Error(`telemetry layout ${surface} desktop cards are not in one row`);
      }
      if (expectedLayout.rows === 2 && !(result.tops[0] === result.tops[1] && result.tops[2] === result.tops[3] && result.tops[2] > result.tops[0])) {
        throw new Error(`telemetry layout ${surface} mobile cards are not 2x2: ${result.tops.join(',')}`);
      }
      await context.close();
    }
  }
} finally {
  await telemetryBrowser.close();
}
process.stdout.write('presentation-ux-contract: telemetry status/developer 1280=4x1 390=2x2\n');
process.stdout.write(`presentation-ux-contract: ${scenarios.length} scenarios x ${targets.length} targets stable\n`);
