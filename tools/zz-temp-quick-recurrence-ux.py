from pathlib import Path
import json

runtime = Path('app/Runtime/Appointments/AppointmentsRuntimeOperations05.php')
s = runtime.read_text()

old_subtitle = 'Confirme os horários sugeridos abaixo e selecione Profissional, Paciente e Procedimento.'
if old_subtitle not in s:
    raise SystemExit('subtitle marker not found')
s = s.replace(old_subtitle, 'Defina os dados do agendamento e, se necessário, inclua outras datas.', 1)

anchor = s.index('            $createSubtitle =')
a = s.index('            $initialHour =', anchor)
b = s.index('            $returnHiddenOperation =', a)
s = s[:a] + s[b:]

a = s.index('            $quickHeader =', anchor)
b = s.index('            $recurrenceProcedureOptions =', a)
s = s[:a] + s[b:]

a = s.index('            $recurrenceFieldset =', anchor)
b = s.index('            $form =', a)
recurrence = r'''            $recurrenceFieldset =
                '<fieldset class="agenda-quick-section agenda-quick-recurrence" data-appointment-recurrence><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("repeat") .
                '<span>Recorrência (opcional)</span></legend><div class="agenda-recurrence-list" data-recurrence-list></div><template data-recurrence-template><div class="agenda-recurrence-row" data-recurrence-row><label class="field"><span>Procedimento</span><select name="recurrence_procedure[]" required data-recurrence-procedure>' .
                $recurrenceProcedureOptions .
                '</select></label><label class="field"><span>Data/Hora</span><input type="datetime-local" name="recurrence_start_at[]" required data-recurrence-start></label><button type="button" class="ghost agenda-recurrence-remove" data-recurrence-remove aria-label="Remover recorrência" title="Remover recorrência">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("delete") .
                '<span class="sr-only">Remover recorrência</span></button></div></template><div class="agenda-recurrence-composer" data-recurrence-composer><label class="field"><span>Procedimento</span><select data-recurrence-composer-procedure>' .
                $recurrenceProcedureOptions .
                '</select></label><label class="field"><span>Data/Hora</span><input type="datetime-local" data-recurrence-composer-start></label><button type="button" class="primary agenda-recurrence-add" data-recurrence-add aria-label="Adicionar recorrência" title="Adicionar recorrência">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("add") .
                '<span class="sr-only">Adicionar recorrência</span></button></div></fieldset>';
'''
s = s[:a] + recurrence + s[b:]

form_start = s.index('            $form =', anchor)
form_end = s.index('            \\Prontoo\\Runtime\\UiComponents\\UiComponentsRuntimeOperations02::page(', form_start)
form = r'''            $form =
                '<section class="form-panel agenda-route-form agenda-quick-form-panel"><form method="post" class="compact agenda-create-form agenda-quick-form" data-appointment-create-form>' .
                \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
                $returnHiddenOperation .
                '<input type="hidden" name="act" value="create"><fieldset class="agenda-quick-section agenda-quick-main"><legend>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("edit_calendar") .
                '<span>Agendamento</span></legend><div class="agenda-quick-grid">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                    "Paciente",
                    "patient_link_id",
                    $pats,
                    $prePatientId ?: null,
                    "required",
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::select_label(
                    \Prontoo\Runtime\ClinicConfig\ClinicConfigRuntimeOperations01::role_label_for("medico", $cid),
                    "doctor_user_id",
                    $formDoctorOptions,
                    $initialDoctor ?: null,
                    "required",
                ) .
                \Prontoo\Runtime\Appointments\AppointmentsRuntimeOperations03::procedure_select_html($cid, "reason", "", true) .
                '</div><div class="agenda-quick-time-grid">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Início",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "start_at",
                        "datetime-local",
                        $initialStart,
                        "required data-appointment-start",
                    ),
                ) .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Fim",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "end_at",
                        "datetime-local",
                        $initialEnd,
                        "required data-appointment-end",
                    ),
                ) .
                '</div>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                    "Observações (opcional)",
                    \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                        "notes",
                        "text",
                        "",
                        'placeholder="Informação relevante para o atendimento"',
                    ),
                ) .
                '</fieldset>' .
                $recurrenceFieldset .
                \Prontoo\Runtime\Financial\FinancialRuntimeOperations01::appointment_payment_form_html($cid) .
                '<div class="form-actions agenda-quick-actions"><a class="ghost" href="' .
                \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("appointments", $cancelParams) .
                '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
                '<span>Cancelar</span></a><button type="submit" class="primary">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("event_available") .
                '<span>Agendar</span></button></div></form></section>';
'''
s = s[:form_start] + form + s[form_end:]
runtime.write_text(s)

js_path = Path('public/assets/app.js')
s = js_path.read_text()
start = s.index('  function updateAppointmentRecurrenceDuration(select) {')
end = s.index('})();', start)
js = '''  function recurrenceComposer(root) {\n    return {\n      procedure: root && root.querySelector("[data-recurrence-composer-procedure]"),\n      start: root && root.querySelector("[data-recurrence-composer-start]"),\n    };\n  }\n  function seedRecurrenceComposer(root) {\n    if (!root) return;\n    var composer = recurrenceComposer(root);\n    var form = root.closest("form");\n    var mainProcedure = form && form.querySelector("[data-procedure-select]");\n    if (composer.procedure && !composer.procedure.value && mainProcedure && mainProcedure.value)\n      composer.procedure.value = mainProcedure.value;\n  }\n  function initRecurrenceComposers() {\n    document.querySelectorAll("[data-appointment-recurrence]").forEach(seedRecurrenceComposer);\n  }\n  function addAppointmentRecurrence(button) {\n    var root = button && button.closest("[data-appointment-recurrence]");\n    if (!root) return false;\n    var list = root.querySelector("[data-recurrence-list]");\n    var template = root.querySelector("template[data-recurrence-template]");\n    var composer = recurrenceComposer(root);\n    if (!list || !template || !template.content || !composer.procedure || !composer.start) return false;\n    composer.procedure.setCustomValidity("");\n    composer.start.setCustomValidity("");\n    if (!composer.procedure.value) {\n      composer.procedure.setCustomValidity("Selecione o procedimento.");\n      composer.procedure.reportValidity();\n      return false;\n    }\n    if (!composer.start.value) {\n      composer.start.setCustomValidity("Informe a data e hora.");\n      composer.start.reportValidity();\n      return false;\n    }\n    var fragment = template.content.cloneNode(true);\n    var row = fragment.querySelector("[data-recurrence-row]");\n    var select = row && row.querySelector("[data-recurrence-procedure]");\n    var start = row && row.querySelector("[data-recurrence-start]");\n    if (select) select.value = composer.procedure.value;\n    if (start) start.value = composer.start.value;\n    list.appendChild(fragment);\n    composer.start.value = "";\n    composer.start.focus();\n    return true;\n  }\n  if (document.readyState === "loading")\n    document.addEventListener("DOMContentLoaded", initRecurrenceComposers, { once: true });\n  else initRecurrenceComposers();\n  d.addEventListener("click", function (ev) {\n    var add = closest(ev.target, "[data-recurrence-add]");\n    if (add) {\n      ev.preventDefault();\n      addAppointmentRecurrence(add);\n      return;\n    }\n    var remove = closest(ev.target, "[data-recurrence-remove]");\n    if (remove) {\n      ev.preventDefault();\n      var row = remove.closest("[data-recurrence-row]");\n      if (row) row.remove();\n    }\n  });\n  d.addEventListener("change", function (ev) {\n    var mainProcedure = closest(ev.target, "[data-appointment-create-form] [data-procedure-select]");\n    if (!mainProcedure) return;\n    var form = mainProcedure.closest("form");\n    var root = form && form.querySelector("[data-appointment-recurrence]");\n    var composer = recurrenceComposer(root);\n    if (composer.procedure && !composer.procedure.value) composer.procedure.value = mainProcedure.value;\n  });\n  d.addEventListener("submit", function (ev) {\n    var form = closest(ev.target, "[data-appointment-create-form]");\n    if (!form) return;\n    var root = form.querySelector("[data-appointment-recurrence]");\n    if (!root) return;\n    var composer = recurrenceComposer(root);\n    if (!composer.procedure || !composer.start || !composer.start.value) return;\n    ev.preventDefault();\n    if (!addAppointmentRecurrence(root.querySelector("[data-recurrence-add]"))) return;\n    if (form.requestSubmit) form.requestSubmit();\n    else form.submit();\n  });\n'''
s = s[:start] + js + s[end:]
js_path.write_text(s)

css_path = Path('design/styles/application.css')
s = css_path.read_text()
start = s.index('.agenda-quick-recurrence{')
marker = '@media(max-width:720px){.agenda-quick-recurrence .agenda-recurrence-row{grid-template-columns:1fr}.agenda-quick-recurrence .agenda-recurrence-remove{justify-self:start}}'
end = s.index(marker, start) + len(marker)
css = '''.agenda-quick-recurrence{display:grid;gap:10px}\n.agenda-recurrence-list{display:grid;gap:8px}\n.agenda-recurrence-row,.agenda-recurrence-composer{display:grid;grid-template-columns:minmax(0,1fr) minmax(220px,.72fr) 42px;gap:10px;align-items:end}\n.agenda-recurrence-row{padding-bottom:8px;border-bottom:1px solid color-mix(in srgb,var(--md-sys-color-outline-variant) 78%,transparent)}\n.agenda-recurrence-row .field,.agenda-recurrence-composer .field{margin:0;min-width:0}\n.agenda-recurrence-row :is(select,input),.agenda-recurrence-composer :is(select,input){min-width:0;width:100%}\n.agenda-recurrence-remove,.agenda-recurrence-add{width:42px;min-width:42px;height:42px;min-height:42px;padding:0;align-self:end;border-radius:14px}\n@media(max-width:720px){.agenda-quick-recurrence .agenda-recurrence-row,.agenda-quick-recurrence .agenda-recurrence-composer{grid-template-columns:minmax(0,1fr) minmax(0,1fr) 40px;gap:8px}.agenda-quick-recurrence .agenda-recurrence-remove,.agenda-quick-recurrence .agenda-recurrence-add{width:40px;min-width:40px;height:40px;min-height:40px}}'''
css_path.write_text(s[:start] + css + s[end:])

metadata = {
    'build': '1.9.15.2-quick-scheduling-recurrence-ux',
    'package_type': 'micro_ux',
    'notes': 'Simplifica o Agendamento Rápido, reduzindo informação repetida e tornando a inclusão de recorrências uma composição inline por Procedimento e Data/Hora com ação de adição no fim da linha.',
    'logic_changes': True,
    'visual_changes': True,
    'documentation_changes': True,
    'changelog': {
        'title': 'Agendamento Rápido mais simples e recorrência inline',
        'items': [
            'Substitui o botão textual Adicionar recorrência por uma linha inline com Procedimento, Data/Hora e ícone de adição.',
            'Mantém uma linha de composição sempre disponível e permite adicionar sucessivas recorrências sem limite artificial.',
            'Remove o cabeçalho e o resumo duplicados dentro do card, preservando título, contexto e retorno no PageHead.',
            'Consolida dados principais e horários em uma única seção Agendamento, reduzindo fragmentação visual.',
            'Move Observações para o grupo principal como campo opcional e elimina um fieldset exclusivo redundante.',
            'Renomeia as ações finais para Cancelar e Agendar, com linguagem mais direta e operacional.',
        ],
    },
}
Path('/tmp/prontoo-release.json').write_text(json.dumps(metadata, ensure_ascii=False, indent=2) + '\n')
