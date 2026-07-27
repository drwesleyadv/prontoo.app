    $ttlSeconds = max(60, min(86400, $ttlSeconds));
    $issuedAt = (int) $parts[2];
    $now = time();
    if ($issuedAt < $now - $ttlSeconds || $issuedAt > $now + 60) {
        return false;
    }
    $consumed = $_SESSION["prontoo_consumed_submission_tokens"] ?? [];
    if (!is_array($consumed)) {
        $consumed = [];
    }
    foreach ($consumed as $fingerprint => $consumedAt) {
        if ((int) $consumedAt < $now - $ttlSeconds) {
            unset($consumed[$fingerprint]);
        }
    }
    $fingerprint = hash("sha256", $token);
    if (isset($consumed[$fingerprint])) {
        return false;
    }
    $consumed[$fingerprint] = $now;
    if (count($consumed) > 512) {
        asort($consumed, SORT_NUMERIC);
        $consumed = array_slice($consumed, -384, null, true);
    }
    $_SESSION["prontoo_consumed_submission_tokens"] = $consumed;
    return true;
}

'''
source = source.replace(anchor, block + anchor, 1)
write(path, source)


# 2) Interessados: mutações de uso único.
path = "app/Domain/Leads/Leads.php"
source = read(path)


def protect_convert(section: str) -> str:
    pattern = r'''(\n\s{12}if \(!\$lead\) \{\n\s+flash\("Interessado não encontrado\.", "bad"\);\n\s+redirect\("leads"\);\n\s{12}\})(\n\s{12}try \{)'''
    insertion = r'''
            if (
                !security_submission_token_consume(
                    "lead.convert",
                    (string) ($_POST["submission_token"] ?? ""),
                    $leadId,
                )
            ) {
                flash(
                    "Esta conversão já foi processada. Confira a ficha antes de tentar novamente.",
                    "warn",
                );
                redirect("leads");
            }'''
    return sub_once(section, pattern, r"\1" + insertion + r"\2", "token da conversão")


source = mutate_section(
    source,
    '        if ($act === "convert") {',
    '        if ($act === "archive_lead") {',
    protect_convert,
    "conversão",
)


def protect_archive(section: str) -> str:
    pattern = r'''(\n\s{12}if \(!\$lead\) \{\n\s+flash\("Interessado não encontrado\.", "bad"\);\n\s+redirect\("leads"\);\n\s{12}\})(\n\s{12}\$oldStage = lead_stage_normalize\()'''
    insertion = r'''
            if (
                !security_submission_token_consume(
                    "lead.archive",
                    (string) ($_POST["submission_token"] ?? ""),
                    $leadId,
                )
            ) {
                flash(
                    "Este arquivamento já foi processado. Confira o interessado antes de tentar novamente.",
                    "warn",
                );
                redirect("leads", ["status" => "arquivado"]);
            }'''
    return sub_once(section, pattern, r"\1" + insertion + r"\2", "token do arquivamento")


source = mutate_section(
    source,
    '        if ($act === "archive_lead") {',
    '        if ($act === "update") {',
    protect_archive,
    "arquivamento",
)


def protect_contact(section: str) -> str:
    section = sub_once(
        section,
        r'''(\n\s{12}\$notes = trim\(\(string\) \(\$_POST\["notes"\] \?\? ""\)\);)(\n\s{12}q\()''',
        r'''\1
            $isContactEvent = isset($_POST["contact_event"]);
            if (
                $isContactEvent &&
                !security_submission_token_consume(
                    "lead.contact",
                    (string) ($_POST["submission_token"] ?? ""),
                    $leadId,
                )
            ) {
                flash(
                    "Este contato já foi registrado. Confira o histórico antes de enviar novamente.",
                    "warn",
                );
                redirect("leads", [
                    "status" => lead_stage_normalize(
                        (string) ($lead["stage"] ?? "em_aberto"),
                    ),
                ]);
            }\2''',
        "token do contato",
    )
    section = replace_once(
        section,
        '            if (isset($_POST["contact_event"])) {\n',
        '            if ($isContactEvent) {\n',
        "condição do contato",
    )
    section = replace_once(
        section,
        '                "audit_body" => isset($_POST["contact_event"])\n',
        '                "audit_body" => $isContactEvent\n',
        "auditoria do contato",
    )
    return section


source = mutate_section(
    source,
    '        if ($act === "update") {',
    '        if ($act === "save") {',
    protect_contact,
    "contato",
)


