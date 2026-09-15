from pathlib import Path


def replace_once(path, old, new):
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected one occurrence, got {count}')
    p.write_text(text.replace(old, new, 1))


replace_once(
    'tools/test-fast',
    'str_contains($agendaRuntimeSource, \'if ($eventHtml === "" && !$dayBlocked)\')',
    'str_contains($agendaRuntimeSource, \'if ($eventHtml === "" && $timedNoteHtml === "" && !$dayBlocked)\')',
)

replace_once(
    'app/Runtime/Appointments/AppointmentsRuntimeOperations05.php',
    '''                ($creatorName !== ""
                    ? "Por " . \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e(\\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::first_name($creatorName))
                    : "Aviso da Agenda") .
                $noteDelete .
                "</em></article>";''',
    '''                ($creatorName !== ""
                    ? "Por " . \\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::e(\\Prontoo\\Presentation\\UiComponents\\UiComponentsPresentationOperations01::first_name($creatorName))
                    : "Aviso da Agenda") .
                "</em>" .
                $noteDelete .
                "</article>";''',
)

replace_once(
    'design/styles/application.css',
    'body[data-route="appointments"] .agenda-day-event strong{',
    'body[data-route="appointments"] :is(.agenda-day-event,.agenda-day-event-note) strong{',
)
replace_once(
    'design/styles/application.css',
    'body[data-route="appointments"] .agenda-day-event small{',
    'body[data-route="appointments"] :is(.agenda-day-event,.agenda-day-event-note) small{',
)
replace_once(
    'design/styles/application.css',
    'body[data-route="appointments"] .agenda-day-event em{',
    'body[data-route="appointments"] :is(.agenda-day-event,.agenda-day-event-note) em{',
)
