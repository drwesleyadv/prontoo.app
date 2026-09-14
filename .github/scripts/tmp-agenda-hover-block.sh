#!/usr/bin/env bash
set -euo pipefail

python3 - <<'PY'
from pathlib import Path
import json

js_path = Path('public/assets/app.js')
js = js_path.read_text()
marker = '  function procedureDuration(select) {'
if marker not in js:
    raise SystemExit('procedureDuration marker missing')
helpers = r'''  function agendaSlotIndexFromPointer(canvas, ev) {
    var rect = canvas.getBoundingClientRect();
    var y = Math.max(0, Math.min(rect.height, ev.clientY - rect.top));
    var slotHeight =
      parseFloat(
        getComputedStyle(canvas).getPropertyValue("--agenda-slot-height"),
      ) || 34;
    return Math.max(0, Math.floor(y / slotHeight));
  }
  function agendaSlotOccupied(canvas, slotIndex) {
    return Array.prototype.some.call(
      canvas.querySelectorAll(".agenda-day-event"),
      function (event) {
        var top = parseFloat(
          event.style.getPropertyValue("--event-top") || "NaN",
        );
        var height = parseFloat(
          event.style.getPropertyValue("--event-height") || "NaN",
        );
        return (
          Number.isFinite(top) &&
          Number.isFinite(height) &&
          top < slotIndex + 1 &&
          top + height > slotIndex
        );
      },
    );
  }
  function agendaBlockActionBase() {
    var action = document.querySelector(
      'body[data-route="appointments"] .pagehead a[href*="mode=block"]',
    );
    return action ? action.href : "";
  }
  function agendaBlockHoverLink(canvas) {
    var link = canvas.querySelector(".agenda-day-block-hover");
    if (link) return link;
    link = document.createElement("a");
    link.className = "agenda-day-block-hover";
    link.setAttribute("title", "Bloquear horário");
    link.innerHTML =
      '<span class="material-symbols-rounded pt-icon-glyph" aria-hidden="true">lock</span>';
    canvas.appendChild(link);
    return link;
  }
  function hideAgendaBlockHover(exceptCanvas) {
    document
      .querySelectorAll(".agenda-day-block-hover.is-visible")
      .forEach(function (link) {
        if (!exceptCanvas || link.parentElement !== exceptCanvas)
          link.classList.remove("is-visible");
      });
  }
'''
js = js.replace(marker, helpers + marker, 1)
section_start = js.index('  function roundSlotFromClick(canvas, ev) {')
click_marker = '  document.addEventListener("click", function (ev) {'
click_pos = js.index(click_marker, section_start)
hover = r'''  document.addEventListener("mousemove", function (ev) {
    var canvas = ev.target.closest && ev.target.closest("[data-agenda-canvas]");
    hideAgendaBlockHover(canvas || null);
    if (!canvas || canvas.closest(".agenda-week-day-shell")) return;
    var base = agendaBlockActionBase();
    if (!base) return;
    var slotIndex = agendaSlotIndexFromPointer(canvas, ev);
    var link = agendaBlockHoverLink(canvas);
    if (agendaSlotOccupied(canvas, slotIndex)) {
      link.classList.remove("is-visible");
      return;
    }
    var startValue = roundSlotFromClick(canvas, ev);
    var url = new URL(base, window.location.href);
    url.searchParams.set("start", startValue);
    var slotHeight =
      parseFloat(
        getComputedStyle(canvas).getPropertyValue("--agenda-slot-height"),
      ) || 34;
    link.href = url.toString();
    link.style.setProperty(
      "--agenda-block-hover-y",
      slotIndex * slotHeight + "px",
    );
    link.setAttribute(
      "aria-label",
      "Bloquear horário às " + startValue.slice(11, 16),
    );
    link.classList.add("is-visible");
  });
'''
js = js[:click_pos] + hover + js[click_pos:]
js_path.write_text(js)

css_path = Path('design/styles/application.css')
css = css_path.read_text().replace('pix-1.9.14.1.svg', 'pix-1.9.14.2.svg')
if '.agenda-day-block-hover{' in css:
    raise SystemExit('agenda hover css already exists')
css_add = r'''
.agenda-day-block-hover{position:absolute;right:8px;top:0;z-index:8;display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:var(--md-sys-shape-corner-full);background:var(--md-sys-color-surface-container-lowest);border:1px solid color-mix(in srgb,var(--md-sys-color-primary) 24%,var(--md-sys-color-outline-variant));color:var(--md-sys-color-primary);box-shadow:var(--md-sys-elevation-level2);opacity:0;pointer-events:none;transform:translateY(var(--agenda-block-hover-y,0));transition:opacity var(--motion-fast),background var(--motion-fast),border-color var(--motion-fast),color var(--motion-fast)}
.agenda-day-block-hover.is-visible{opacity:1;pointer-events:auto}
.agenda-day-block-hover:hover{background:var(--md-sys-color-primary-container);border-color:color-mix(in srgb,var(--md-sys-color-primary) 42%,var(--md-sys-color-outline-variant));color:var(--md-sys-color-on-primary-container)}
.agenda-day-block-hover .material-symbols-rounded,.agenda-day-block-hover .pt-icon-glyph{font-size:18px}
@media(hover:none){.agenda-day-block-hover{display:none}}
'''
css_path.write_text(css.rstrip() + '\n' + css_add.lstrip())

meta = {
    'build': '1.9.14.2-agenda-hover-block',
    'package_type': 'micro_ux',
    'notes': 'Adiciona na Agenda diária um cadeado contextual para abrir Bloquear Horário com o intervalo apontado pré-selecionado, sem alterar regras de agendamento, bloqueio ou schema.',
    'logic_changes': True,
    'visual_changes': True,
    'documentation_changes': False,
    'changelog': {
        'title': 'Bloqueio contextual na Agenda diária',
        'items': [
            'exibe um cadeado discreto na lateral direita do intervalo apontado na visualização diária',
            'mostra o atalho somente quando o intervalo sob o cursor não está ocupado por consulta ou bloqueio existente',
            'reutiliza a operação canônica Bloquear Horário sem criar novo endpoint ou regra de domínio',
            'abre o formulário de bloqueio com o horário apontado já pré-selecionado',
            'preserva permissões, agendamentos existentes, persistência, schema e demais visualizações da Agenda'
        ]
    }
}
Path('/tmp/release.json').write_text(json.dumps(meta, ensure_ascii=False, indent=2) + '\n')
PY

node --check public/assets/app.js
php tools/release-version --write --date=2026-09-14 --timestamp=2026-09-14T16:15:00-04:00 --expected-current=1.9.14.1 --metadata-file=/tmp/release.json
python3 - <<'PY'
import json
from pathlib import Path
p = Path('version.json')
data = json.loads(p.read_text())
data['asset_version'] = '1.9.14.2'
p.write_text(json.dumps(data, ensure_ascii=False, indent=4) + '\n')
PY

git mv public/assets/app-icon-1.9.14.1.png public/assets/app-icon-1.9.14.2.png
git mv public/assets/favicon-1.9.14.1.ico public/assets/favicon-1.9.14.2.ico
git mv public/assets/favicon-1.9.14.1.png public/assets/favicon-1.9.14.2.png
git mv public/assets/pix-1.9.14.1.svg public/assets/pix-1.9.14.2.svg
git mv public/assets/prontoo-mark-1.9.14.1.png public/assets/prontoo-mark-1.9.14.2.png

php tools/presentation-css-build --lint
php tools/presentation-css-build --write
php tools/presentation-css-build --check
php tools/release-contract-reconcile --write
php tools/release-contract-reconcile --check
php tools/release-version
php tools/version-asset-contract-check.php
php tools/test-fast
node --check public/assets/app.js
git diff --check

git config user.name github-actions[bot]
git config user.email 41898282+github-actions[bot]@users.noreply.github.com
git add -A
git reset .github/workflows/tmp-agenda-hover-block.yml .github/scripts/tmp-agenda-hover-block.sh .tmp-agenda-hover-trigger || true
git commit -m "Adiciona bloqueio contextual na Agenda diária [agenda-hover-ready]"
git push
