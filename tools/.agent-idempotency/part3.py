def protect_save(section: str) -> str:
    pattern = r'''(\n\s{12}if \(!in_array\(\$stage, \["em_aberto", "aguarda_retorno"\], true\)\) \{.*?\n\s{12}\})(\n\s{12}try \{)'''
    insertion = r'''
            if (
                !security_submission_token_consume(
                    "lead.save",
                    (string) ($_POST["submission_token"] ?? ""),
                )
            ) {
                flash(
                    "Este interessado já foi salvo. Confira a lista antes de enviar novamente.",
                    "warn",
                );
                redirect("leads");
            }'''
    return sub_once(section, pattern, r"\1" + insertion + r"\2", "token do cadastro")


source = mutate_section(
    source,
    '        if ($act === "save") {',
    '    $statusRaw =',
    protect_save,
    "cadastro",
)

# Formulário de novo interessado.
source = replace_once(
    source,
    ' data-lead-create-form>',
    ' data-lead-create-form data-submit-once>',
    "lock do novo interessado",
)
source = replace_once(
    source,
    "              csrf_field() .\n              '<input type=\"hidden\" name=\"act\" value=\"save\">' .",
    "              csrf_field() .\n              '<input type=\"hidden\" name=\"submission_token\" value=\"' .\n              e(security_submission_token_issue(\"lead.save\")) .\n              '\">' .\n              '<input type=\"hidden\" name=\"act\" value=\"save\">' .",
    "token do formulário de cadastro",
)

# Formulário de arquivamento por conflito.
source = replace_once(
    source,
    '<form method="post" class="inline">' + "\n" + "                      csrf_field() .",
    '<form method="post" class="inline" data-submit-once>' + "\n" + "                      csrf_field() .\n                      '<input type=\"hidden\" name=\"submission_token\" value=\"' .\n                      e(security_submission_token_issue(\"lead.archive\", (int) $r[\"id\"])) .\n                      '\">' .",
    "token do formulário de arquivamento",
)

# Formulário de conversão.
source = replace_once(
    source,
    '<form method="post" class="compact lead-convert-form" data-lead-convert-form>' + "\n" + "                  csrf_field() .",
    '<form method="post" class="compact lead-convert-form" data-lead-convert-form data-submit-once>' + "\n" + "                  csrf_field() .\n                  '<input type=\"hidden\" name=\"submission_token\" value=\"' .\n                  e(security_submission_token_issue(\"lead.convert\", (int) $r[\"id\"])) .\n                  '\">' .",
    "token do formulário de conversão",
)

# Formulário de contato.
source = replace_once(
    source,
    '</summary><form method="post" class="compact lead-touch-form">' + "\n" + "              csrf_field() .",
    '</summary><form method="post" class="compact lead-touch-form" data-submit-once>' + "\n" + "              csrf_field() .\n              '<input type=\"hidden\" name=\"submission_token\" value=\"' .\n              e(security_submission_token_issue(\"lead.contact\", (int) $r[\"id\"])) .\n              '\">' .",
    "token do formulário de contato",
)
write(path, source)


# 3) Bloqueio global de reenvio acidental em POST.
path = "public/assets/app.js"
source = read(path)
pattern = r'''  d\.addEventListener\("submit", \(e\) => \{\n.*?\n      const b = form\.querySelector\(\n'''
replacement = '''  d.addEventListener("submit", (e) => {
      const form = e.target;
      if (!validateCpfFields(form)) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }
      // PRONTOO_GLOBAL_POST_SUBMIT_LOCK: todo POST é envio único, salvo opt-out explícito.
      const method = String(form.getAttribute?.("method") || "get").toLowerCase();
      const submitOnce =
        form.matches?.("[data-submit-once]") ||
        (method === "post" && !form.matches?.("[data-submit-repeat]"));
      if (
        submitOnce &&
        (form.dataset.submitting === "1" || form.dataset.submitPending === "1")
      ) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }
      $$("[data-doc-editor-wrap]", form).forEach(syncDocumentEditor);
      if (e.defaultPrevented) return;
      if (submitOnce) {
        form.dataset.submitPending = "1";
        const lock = () => {
          delete form.dataset.submitPending;
          if (e.defaultPrevented) return;
          form.dataset.submitting = "1";
          setTimeout(() => {
            $$(\'button[type="submit"],input[type="submit"]\', form).forEach(
              (button) => {
                button.disabled = true;
                button.setAttribute("aria-disabled", "true");
              },
            );
          }, 0);
        };
        if (typeof w.queueMicrotask === "function") w.queueMicrotask(lock);
        else Promise.resolve().then(lock);
      }
      const b = form.querySelector(
'''
source = sub_once(source, pattern, replacement, "bloqueio global de POST")
write(path, source)


# 4) Contrato permanente no CI.
path = ".github/workflows/architecture.yml"
source = read(path)
source = replace_once(
    source,
    '            if ($functions !== 1938) {\n',
    '            if ($functions !== 1940) {\n',
    "baseline documental",
)
anchor = "      - name: JSON contracts\n"
php_step = r'''<?php
declare(strict_types=1);

$_SESSION = [];
require 'app/Support/SecurityPrivacy.php';
$token = security_submission_token_issue('ci.contact', 17);
if (!security_submission_token_consume('ci.contact', $token, 17)) {
    throw new RuntimeException('Token legítimo não foi aceito.');
}
if (security_submission_token_consume('ci.contact', $token, 17)) {
    throw new RuntimeException('Token repetido foi aceito.');
}
$other = security_submission_token_issue('ci.contact', 17);
if (security_submission_token_consume('ci.other', $other, 17)) {
    throw new RuntimeException('Token foi aceito em escopo divergente.');
}
$leads = (string) file_get_contents('app/Domain/Leads/Leads.php');
foreach (['"lead.save"', '"lead.contact"', '"lead.convert"', '"lead.archive"', 'data-submit-once'] as $required) {
