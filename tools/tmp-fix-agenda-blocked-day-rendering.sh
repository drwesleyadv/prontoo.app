#!/usr/bin/env bash
set -euo pipefail

python3 - <<'PY'
from pathlib import Path

runtime_path = Path('app/Runtime/Appointments/AppointmentsRuntimeOperations05.php')
runtime = runtime_path.read_text()
old_runtime = "</div><div class=\"agenda-day-canvas' . ($dayBlocked ? ' is-day-blocked' : '') . ' data-agenda-canvas data-day-blocked=\"'"
new_runtime = "</div><div class=\"agenda-day-canvas' . ($dayBlocked ? ' is-day-blocked' : '') . '\" data-agenda-canvas data-day-blocked=\"'"
if runtime.count(old_runtime) != 1:
    raise SystemExit(f'daily canvas anchor count={runtime.count(old_runtime)}')
runtime = runtime.replace(old_runtime, new_runtime, 1)
runtime_path.write_text(runtime)

js_path = Path('public/assets/app.js')
js = js_path.read_text()
start_marker = '  function syncAgendaBlockedDayMarkers(root = d) {'
end_marker = '  function initWeekScroll(root) {'
start = js.find(start_marker)
end = js.find(end_marker, start)
if start < 0 or end < 0:
    raise SystemExit('agenda blocked marker function anchors not found')
replacement = '''  function agendaBlockedQueryAll(root, selector) {
    var scope = root && root.querySelectorAll ? root : document;
    var nodes = Array.prototype.slice.call(scope.querySelectorAll(selector));
    if (scope !== document && scope.matches && scope.matches(selector))
      nodes.unshift(scope);
    return nodes;
  }
  function syncAgendaBlockedDayMarkers(root) {
    agendaBlockedQueryAll(
      root || document,
      ".agenda-day-shell[data-agenda-day]",
    ).forEach(function (shell) {
      var canvas = shell.querySelector("[data-agenda-canvas]");
      if (!canvas) return;
      var slots = Math.max(
        0,
        Number.parseInt(
          shell.style.getPropertyValue("--agenda-slots") || "0",
          10,
        ) || 0,
      );
      var slotMinutes = Math.max(
        1,
        Number.parseInt(
          shell.dataset.slotMinutes || canvas.dataset.slotMinutes || "15",
          10,
        ) || 15,
      );
      var blocked =
        canvas.dataset.dayBlocked === "1" ||
        shell.classList.contains("is-day-blocked");
      var fullDayBlock = null;
      if (
        !blocked &&
        shell.classList.contains("agenda-week-day-shell") &&
        slots > 0
      ) {
        fullDayBlock = agendaBlockedQueryAll(
          canvas,
          ".agenda-day-event-block",
        ).find(function (event) {
          var top =
            Number.parseFloat(event.style.getPropertyValue("--event-top")) || 0;
          var height =
            Number.parseFloat(event.style.getPropertyValue("--event-height")) || 0;
          return top <= 0.25 && top + height >= slots - 0.25;
        });
        blocked = !!fullDayBlock;
      }
      if (!blocked || slots <= 0) return;
      shell.classList.add("is-day-blocked");
      canvas.classList.add("is-day-blocked");
      canvas.dataset.dayBlocked = "1";
      if (fullDayBlock) {
        fullDayBlock.classList.add("is-full-day-block");
        fullDayBlock.hidden = true;
      }
      agendaBlockedQueryAll(canvas, ".agenda-day-empty").forEach(
        function (empty) {
          empty.hidden = true;
        },
      );
      agendaBlockedQueryAll(canvas, ".agenda-day-blocked-watermark").forEach(
        function (marker) {
          marker.remove();
        },
      );
      agendaBlockedQueryAll(shell, ".agenda-day-slot[href]").forEach(
        function (slot) {
          slot.removeAttribute("href");
          slot.setAttribute("aria-disabled", "true");
          slot.tabIndex = -1;
        },
      );
      if (!canvas.querySelector("[data-day-blocked-status]")) {
        var status = document.createElement("span");
        status.className = "sr-only";
        status.dataset.dayBlockedStatus = "1";
        status.setAttribute("role", "status");
        status.textContent = "Dia bloqueado";
        canvas.prepend(status);
      }
      for (var index = 0; index < slots; index += 1) {
        if ((index * slotMinutes) % 30 !== 0) continue;
        var marker = document.createElement("div");
        marker.className = "agenda-day-blocked-watermark";
        marker.style.setProperty("--blocked-slot-index", String(index));
        marker.setAttribute("aria-hidden", "true");
        var icon = document.createElement("span");
        icon.className = "material-symbols-rounded";
        icon.textContent = "lock";
        var label = document.createElement("span");
        label.textContent = "Dia bloqueado";
        marker.append(icon, label);
        canvas.append(marker);
      }
    });
  }
'''
js = js[:start] + replacement + js[end:]
old_observer = '            if (n && n.nodeType === 1) initWeekScroll(n);'
new_observer = '''            if (n && n.nodeType === 1) {
              syncAgendaBlockedDayMarkers(n);
              initWeekScroll(n);
            }'''
if js.count(old_observer) != 1:
    raise SystemExit(f'agenda observer anchor count={js.count(old_observer)}')
js = js.replace(old_observer, new_observer, 1)
js_path.write_text(js)

test_path = Path('tools/test-fast')
test = test_path.read_text()
old_test = """    str_contains($agendaBlockedPresentationJs, 'function syncAgendaBlockedDayMarkers(') &&
        str_contains($agendaBlockedPresentationJs, '(index * slotMinutes) % 30 !== 0') &&
        str_contains($agendaBlockedPresentationJs, 'agenda-week-day-shell') &&
        str_contains($agendaBlockedPresentationJs, 'fullDayBlock.hidden = true') &&
        str_contains($agendaBlockedPresentationJs, 'slot.removeAttribute(\"href\")') &&
        str_contains($agendaBlockedPresentationCss, '--blocked-slot-index,0') &&
        str_contains($agendaBlockedPresentationCss, 'left:12px;right:auto'),
"""
new_test = """    str_contains($agendaBlockedPresentationJs, 'function agendaBlockedQueryAll(') &&
        str_contains($agendaBlockedPresentationJs, 'function syncAgendaBlockedDayMarkers(') &&
        str_contains($agendaBlockedPresentationJs, 'shell.querySelector(\"[data-agenda-canvas]\")') &&
        str_contains($agendaBlockedPresentationJs, '(index * slotMinutes) % 30 !== 0') &&
        str_contains($agendaBlockedPresentationJs, 'agenda-week-day-shell') &&
        str_contains($agendaBlockedPresentationJs, 'top + height >= slots - 0.25') &&
        str_contains($agendaBlockedPresentationJs, 'fullDayBlock.hidden = true') &&
        str_contains($agendaBlockedPresentationJs, 'slot.removeAttribute(\"href\")') &&
        str_contains($agendaBlockedPresentationJs, 'document.createElement(\"div\")') &&
        str_contains($agendaBlockedPresentationJs, 'syncAgendaBlockedDayMarkers(n);') &&
        str_contains($agendaBlockedPresentationCss, '--blocked-slot-index,0') &&
        str_contains($agendaBlockedPresentationCss, 'left:12px;right:auto'),
"""
if test.count(old_test) != 1:
    raise SystemExit(f'blocked presentation test anchor count={test.count(old_test)}')
test = test.replace(old_test, new_test, 1)
old_runtime_test = """        str_contains($agendaRuntimeSource, 'agenda-day-blocked-watermark') &&
        str_contains($agendaRuntimeSource, 'data-day-blocked=\"') &&
"""
new_runtime_test = """        str_contains($agendaRuntimeSource, 'agenda-day-blocked-watermark') &&
        str_contains($agendaRuntimeSource, 'data-day-blocked=\"') &&
        str_contains($agendaRuntimeSource, "'\\\" data-agenda-canvas data-day-blocked=\\\"'") &&
"""
if test.count(old_runtime_test) != 1:
    raise SystemExit(f'daily canvas regression test anchor count={test.count(old_runtime_test)}')
test = test.replace(old_runtime_test, new_runtime_test, 1)
test_path.write_text(test)
PY

node --check public/assets/app.js
php tools/test-fast

DATE="$(TZ=America/Cuiaba date +%F)"
TIMESTAMP="$(TZ=America/Cuiaba date --iso-8601=seconds)"
cat >/tmp/prontoo-release.json <<'JSON'
{
  "build": "1.9.16.2-agenda-blocked-day-rendering-fix",
  "package_type": "presentation",
  "notes": "Corrige a execução dos marcadores de dia bloqueado para repetir cadeado e texto a cada 30 minutos na visão diária e aplicar o mesmo padrão visual na visão semanal.",
  "logic_changes": true,
  "visual_changes": true,
  "documentation_changes": true,
  "changelog": {
    "title": "Dias bloqueados consistentes na Agenda",
    "items": [
      "Corrige o atributo data-agenda-canvas da visão diária para que o canvas bloqueado seja reconhecido corretamente",
      "Remove dependências de helpers fora do escopo da rotina JavaScript de dia bloqueado",
      "Repete cadeado e Dia bloqueado a cada 30 minutos durante todo o expediente bloqueado na visão diária",
      "Substitui na visão semanal o cartão de bloqueio integral pelo mesmo marcador discreto usado na visão diária",
      "Mantém indisponíveis os atalhos de criação enquanto o expediente inteiro estiver bloqueado"
    ]
  },
  "deployment_sync_id": "github-prontoo-1.9.16.2-agenda-blocked-day-rendering-fix"
}
JSON
php tools/release-version --write --date="$DATE" --timestamp="$TIMESTAMP" --expected-current=1.9.16.1 --metadata-file=/tmp/prontoo-release.json
php tools/release-contract-reconcile --write
php tools/release-contract-reconcile --write
php tools/release-contract-reconcile --check
php tools/release-version

rm -f .github/workflows/fix-agenda-blocked-day-rendering.yml tools/tmp-fix-agenda-blocked-day-rendering.sh

node --check public/assets/app.js
php tools/test-fast
php tools/version-asset-contract-check.php
php tools/quality-gate --fast
php tools/documentation-check
git diff --check

git config user.name 'github-actions[bot]'
git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
git add -A
git commit -m 'Corrige repetição visual de dias bloqueados na Agenda'
git push origin HEAD:fix/agenda-blocked-day-rendering
