from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]

app_js = root / "public/assets/app.js"
text = app_js.read_text(encoding="utf-8")
old = '''  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", start, { once: true });
  else start();

  function recurrenceComposer(root) {'''
new = '''  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", start, { once: true });
  else start();
})();

(function () {
  "use strict";
  if (window.__prontooAppointmentRecurrenceReady) return;
  window.__prontooAppointmentRecurrenceReady = true;

  function recurrenceComposer(root) {'''
if old not in text:
    raise SystemExit("recurrence boundary not found")
if text.count(old) != 1:
    raise SystemExit("recurrence boundary is not unique")
text = text.replace(old, new, 1)
app_js.write_text(text, encoding="utf-8")

test_fast = root / "tools/test-fast"
test_text = test_fast.read_text(encoding="utf-8")
marker = '''$applicationContract = json_decode(
'''
insert = '''$appointmentUiJs = (string) file_get_contents($root . '/public/assets/app.js');
$recurrenceGuardAt = strpos($appointmentUiJs, 'window.__prontooAppointmentRecurrenceReady = true;');
$recurrenceFunctionAt = strpos($appointmentUiJs, 'function addAppointmentRecurrence(button)');
$floatingGuardAt = strpos($appointmentUiJs, 'window.__prontooFloatingEndFadeReady = true;');
$assert(
    $recurrenceGuardAt !== false && $recurrenceFunctionAt !== false && $recurrenceGuardAt < $recurrenceFunctionAt,
    'presentation.quick_scheduling.recurrence_guard_before_handler',
);
$betweenFloatingAndRecurrence =
    $floatingGuardAt !== false && $recurrenceGuardAt !== false && $recurrenceGuardAt > $floatingGuardAt
        ? substr($appointmentUiJs, $floatingGuardAt, $recurrenceGuardAt - $floatingGuardAt)
        : '';
$assert(
    $floatingGuardAt !== false && str_contains($betweenFloatingAndRecurrence, '})();'),
    'presentation.quick_scheduling.recurrence_isolated_from_floating_module',
);
$assert(
    str_contains($appointmentUiJs, 'var add = closest(ev.target, "[data-recurrence-add]");') &&
        str_contains($appointmentUiJs, 'template.content.cloneNode(true)') &&
        str_contains($appointmentUiJs, 'list.appendChild(fragment);'),
    'presentation.quick_scheduling.recurrence_add_interaction_contract',
);

$applicationContract = json_decode(
'''
if marker not in test_text:
    raise SystemExit("test-fast insertion marker not found")
if "presentation.quick_scheduling.recurrence_guard_before_handler" not in test_text:
    test_text = test_text.replace(marker, insert, 1)
test_fast.write_text(test_text, encoding="utf-8")

version_path = root / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
    "version": "1.9.15.3",
    "release": "1.9.15.3",
    "generated_at_unix": 1789479180,
    "generated_at": "2026-09-15T09:33:00-04:00",
    "updated_at": "2026-09-15T09:33:00-04:00",
    "build": "1.9.15.3-quick-scheduling-recurrence-add-fix",
    "asset_version": "1.9.15.3",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": False,
    "documentation_changes": True,
    "notes": "Corrige a adição de recorrências no Agendamento Rápido ao desacoplar seu listener do módulo de elementos flutuantes e protege o comportamento com contrato automatizado.",
    "deployment_sync_id": "github-prontoo-1.9.15.3-release",
    "deployment_sync_requested_at": "2026-09-15T09:33:00-04:00",
    "release_date": "2026-09-15",
    "changelog": {
        "title": "Correção da adição de recorrências",
        "items": [
            "Isola a inicialização da recorrência do módulo de elementos flutuantes da Agenda.",
            "Garante que o botão de adição registre seu listener de forma própria e idempotente.",
            "Adiciona contrato automatizado para impedir regressão no clique, clonagem e inclusão da linha de recorrência."
        ]
    }
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
