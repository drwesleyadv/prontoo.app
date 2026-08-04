<?php
declare(strict_types=1);
const PRONTOO_FINANCIAL_MAX_CENTS = 2147483647;
function parse_money_cents(string $v): int
{

    $v = trim(str_replace("\u{00A0}", " ", $v));
    if ($v === "") {
        return 0;
    }
    $v = str_replace(["R$", " "], "", $v);
    if (str_contains($v, ",")) {
        if (
            preg_match(
                '/^(?:\d+|\d{1,3}(?:\.\d{3})+),(\d{1,2})$/',
                $v,
                $match,
            ) !== 1
        ) {
            throw new RuntimeException(
                "Informe o valor em reais com no máximo duas casas decimais.",
            );
        }
        [$whole, $fraction] = explode(",", $v, 2);
        $whole = str_replace(".", "", $whole);
    } elseif (preg_match('/^\d+(?:\.(\d{1,2}))?$/', $v, $match) === 1) {
        [$whole, $fraction] = array_pad(explode(".", $v, 2), 2, "");
    } else {
        throw new RuntimeException(
            "Informe o valor em reais com no máximo duas casas decimais.",
        );
    }
    $whole = ltrim($whole, "0");
    $whole = $whole === "" ? "0" : $whole;
    $fraction = str_pad($fraction, 2, "0");
    $maxWhole = (string) intdiv(PRONTOO_FINANCIAL_MAX_CENTS, 100);
    if (
        strlen($whole) > strlen($maxWhole) ||
        (strlen($whole) === strlen($maxWhole) && strcmp($whole, $maxWhole) > 0)
    ) {
        throw new RuntimeException(
            "O valor informado ultrapassa o limite financeiro seguro.",
        );
    }
    $cents = ((int) $whole * 100) + (int) $fraction;
    return financial_assert_amount_cents($cents);
}
function financial_assert_amount_cents(
    int $value,
    string $label = "Valor",
): int {

    if ($value < 0 || $value > PRONTOO_FINANCIAL_MAX_CENTS) {
        throw new RuntimeException(
            $label . " ultrapassa o domínio monetário seguro.",
        );
    }
    return $value;
}
function financial_assert_balance_cents(
    int $value,
    string $label = "Saldo",
): int {

    if (
        $value < -PRONTOO_FINANCIAL_MAX_CENTS ||
        $value > PRONTOO_FINANCIAL_MAX_CENTS
    ) {
        throw new RuntimeException(
            $label . " ultrapassa o domínio monetário seguro.",
        );
    }
    return $value;
}
function financial_checked_add(
    int $left,
    int $right,
    string $label = "Saldo",
): int {

    return financial_assert_balance_cents($left + $right, $label);
}
function financial_movement_delta_for_location(
    int $amount,
    ?int $from,
    ?int $to,
    int $locationId,
): int {

    financial_assert_amount_cents($amount);
    return ($to === $locationId ? $amount : 0) -
        ($from === $locationId ? $amount : 0);
}
function financial_validate_movement_topology(
    string $type,
    ?int $from,
    ?int $to,
): void {

    $from = ($from ?? 0) > 0 ? $from : null;
    $to = ($to ?? 0) > 0 ? $to : null;
    $valid = match ($type) {
        "receipt", "cash_opening" => $from === null && $to !== null,
        "payment", "refund" => $from !== null && $to === null,
        "transfer", "deposit", "cash_closing" =>
            $from !== null && $to !== null && $from !== $to,
        "adjustment" => ($from === null) !== ($to === null),
        default => false,
    };
    if (!$valid) {
        throw new RuntimeException(
            "Origem e destino não correspondem à natureza do movimento financeiro.",
        );
    }
}
function financial_closing_equation(
    int $expected,
    int $declared,
    int $kept,
    int $transferred,
    int $difference,
): bool {

    return $declared === $kept + $transferred &&
        $difference === $declared - $expected;
}
function payment_methods_options(): array
{

    return [
        "pix" => "PIX",
        "dinheiro" => "Dinheiro",
        "debito" => "Cartão de débito",
        "credito" => "Cartão de crédito",
        "transferencia" => "Transferência bancária",
        "boleto" => "Boleto bancário",
        "cheque" => "Cheque",
        "outro" => "Outro",
    ];
}
function payment_method_type_options(): array
{

    return [
        "pix" => "PIX",
        "dinheiro" => "Dinheiro",
        "cartao_debito" => "Cartão de débito",
        "cartao_credito" => "Cartão de crédito",
        "transferencia" => "Transferência bancária",
        "boleto" => "Boleto bancário",
        "cheque" => "Cheque",
        "outro" => "Outro",
    ];
}
function normalize_payment_method(string $v): string
{

    $v = strtolower(trim($v));
    return array_key_exists($v, payment_methods_options()) ? $v : "";
}
function financial_goal_base_options(): array
{

    return [
        "efetivada" => "Receita Efetivada",
        "prevista" => "Receita Prevista",
    ];
}
function financial_goal_base_label(string $base): string
{

    $opts = financial_goal_base_options();
    return $opts[$base] ?? $opts["efetivada"];
}
function financial_expense_category_options(): array
{

    return [
        "administrativa" => "Despesas administrativas",
        "pessoal" => "Pessoal e encargos",
        "servicos" => "Serviços tomados",
        "insumos" => "Materiais e insumos",
        "aluguel_condominio" => "Aluguel e condomínio",
        "tributos" => "Tributos e taxas",
        "financeira" => "Despesas financeiras",
        "marketing" => "Marketing e captação",
        "manutencao" => "Manutenção e tecnologia",
        "outras" => "Outras despesas",
    ];
}
function financial_account_type_options(): array
{

    return [
        "conta_corrente" => "Conta corrente",
        "conta_poupanca" => "Conta poupança",
        "conta_pagamento" => "Conta de pagamento",
        "caixa_interno" => "Em espécie",
        "investimento" => "Conta de investimento",
    ];
}
function financial_seed_payment_methods(int $cid, int $uid = 0): void
{

    if ($cid <= 0) {
        return;
    }
    try {
        $count =
            (int) (val(
                "SELECT COUNT(*) FROM pi_financial_payment_methods WHERE clinic_id=?",
                [$cid],
            ) ?? 0);
        if ($count > 0) {
            return;
        }
        foreach (
            [
                ["PIX", "pix", 0],
                ["Dinheiro", "dinheiro", 0],
                ["Cartão de débito", "cartao_debito", 1],
                ["Cartão de crédito", "cartao_credito", 30],
                ["Transferência bancária", "transferencia", 0],
                ["Boleto bancário", "boleto", 2],
                ["Cheque", "cheque", 2],
            ]
            as $m
        ) {
            q(
                "INSERT INTO pi_financial_payment_methods (clinic_id,name,method_type,settlement_days,active,created_by,created_at) VALUES (?,?,?,?,1,?,NOW()) ON DUPLICATE KEY UPDATE active=VALUES(active)",
                [$cid, $m[0], $m[1], $m[2], $uid ?: null],
            );
        }
    } catch (Throwable $e) {
        error_log("[Prontoo payment methods seed] " . $e->getMessage());
    }
}
function financial_payment_method_options(
    int $cid,
    bool $withEmpty = true,
): array {

    financial_seed_payment_methods($cid);
    $out = $withEmpty ? ["" => "Não informada"] : [];
    try {
        $rows = q(
            "SELECT id,name,method_type FROM pi_financial_payment_methods WHERE clinic_id=? AND active=1 ORDER BY FIELD(method_type,'pix','dinheiro','cartao_debito','cartao_credito','transferencia','boleto','cheque','outro'), name",
            [$cid],
        )->fetchAll();
        foreach ($rows as $r) {
            $out[(int) $r["id"]] = $r["name"];
        }
    } catch (Throwable $e) {
        foreach (payment_methods_options() as $k => $v) {
            $out[$k] = $v;
        }
    }
    return $out;
}
function financial_payment_method_from_post(int $cid): array
{

    $id = (int) ($_POST["payment_method_id"] ?? 0);
    if ($id > 0) {
        $r = one(
            "SELECT id,name,method_type FROM pi_financial_payment_methods WHERE id=? AND clinic_id=? AND active=1",
            [$id, $cid],
        );
        if ($r) {
            return [
                (int) $r["id"],
                (string) $r["method_type"],
                (string) $r["name"],
            ];
        }
    }
    return [null, "", ""];
}
function appointment_payment_destination_options(
    int $cid,
    bool $withEmpty = true,
): array {

    $out = $withEmpty ? ["" => "Selecione o destino"] : [];
    if ($cid <= 0) {
        return $out;
    }
    financial_operational_schema_ready();
    financial_ensure_admin_safe($cid, (int) ($_SESSION["uid"] ?? 0));
    try {
        $accounts = q(
            "SELECT id FROM pi_financial_accounts WHERE clinic_id=? AND active=1 AND account_type IN ('conta_corrente','conta_poupanca','conta_pagamento','investimento') ORDER BY id LIMIT 80",
            [$cid],
        )->fetchAll();
        foreach ($accounts as $acc) {
            financial_ensure_bank_location(
                $cid,
                (int) $acc["id"],
                (int) ($_SESSION["uid"] ?? 0),
            );
        }
    } catch (Throwable $e) {
        error_log("[Prontoo appointment destinations] " . $e->getMessage());
    }
    try {
        $rows = q(
            "SELECT id,name,location_type FROM pi_financial_locations WHERE clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') ORDER BY FIELD(location_type,'admin_safe','bank_account'), name,id",
            [$cid],
        )->fetchAll();
        foreach ($rows as $r) {
            $kind = (string) ($r["location_type"] ?? "");
            $label = $kind === "bank_account" ? "Banco" : "Cofre";
            $out[(int) $r["id"]] = $label . " · " . (string) $r["name"];
        }
    } catch (Throwable $e) {
        error_log(
            "[Prontoo appointment destinations list] " . $e->getMessage(),
        );
    }
    return $out;
}
function appointment_payment_existing_destination(int $cid, array $appt): int
{

    $appointmentId = (int) ($appt["id"] ?? 0);
    if ($cid <= 0 || $appointmentId <= 0) {
        return 0;
    }
    try {
        return (int) (val(
            "SELECT to_location_id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' AND status='confirmed' ORDER BY id DESC LIMIT 1",
            [$cid, $appointmentId],
        ) ?:
        0);
    } catch (Throwable $e) {
        return 0;
    }
}
function appointment_payment_form_html(int $cid, array $appt = []): string
{

    $amount = (int) ($appt["payment_amount_cents"] ?? 0);
    if ($amount <= 0 && !empty($appt["procedure_id"])) {
        try {
            $amount =
                (int) (val(
                    "SELECT price_cents FROM pi_procedures WHERE id=? AND clinic_id=?",
                    [(int) $appt["procedure_id"], $cid],
                ) ?:
                0);
        } catch (Throwable $e) {
            $amount = 0;
        }
    }
    $paid =
        !empty($appt["payment_confirmed_at"]) ||
        ($appt["payment_status"] ?? "") === "efetivada";
    $method = (string) ($appt["payment_method"] ?? "");
    $dest = appointment_payment_existing_destination($cid, $appt);
    $detailsAttr = $paid ? "" : " hidden";
    $destAttr =
        $paid && $method !== "" && $method !== "dinheiro" ? "" : " hidden";
    $amountText = $amount > 0 ? money_br($amount) : 'R$ 0,00';
    $fields =
        '<section class="agenda-payment-fields" data-appointment-payment><h4>' .
        icon("payments") .
        '<span>Pagamento</span></h4><label class="checkline"><input type="checkbox" name="payment_confirmed" value="1" data-appointment-paid-toggle ' .
        ($paid ? "checked" : "") .
        '><span>Paciente Pagou</span></label><div class="agenda-payment-details" data-appointment-payment-details' .
        $detailsAttr .
        ">" .
        form_row(
            "Valor fixado no agendamento",
            '<input type="text" value="' .
                e($amountText) .
                '" readonly aria-readonly="true" data-appointment-payment-amount-display><input type="hidden" name="payment_amount" value="' .
                e($amountText) .
                '" data-appointment-payment-amount-hidden>',
        ) .
        select_label(
            "Forma de pagamento",
            "payment_method",
            ["" => "Selecione"] + payment_methods_options(),
            $method,
            "data-appointment-payment-method",
        ) .
        "<div data-appointment-payment-destination" .
        $destAttr .
        ">" .
        select_label(
            "Destino do recebimento",
            "payment_destination_location_id",
            appointment_payment_destination_options($cid),
            $dest,
        ) .
        "</div></div></section>";
    return $fields;
}
function financial_sync_appointment(
    int $cid,
    int $appointmentId,
    int $userId,
    int $paymentDestinationLocationId = 0,
): void {

    $a = one(
        "SELECT id,patient_link_id,procedure_id,start_at,reason,payment_amount_cents,payment_method,payment_status,payment_confirmed_at,revenue_id FROM pi_appointments WHERE id=? AND clinic_id=?",
        [$appointmentId, $cid],
    );
    if (!$a) {
        return;
    }
    $amount = (int) ($a["payment_amount_cents"] ?? 0);
    $procId = (int) ($a["procedure_id"] ?? 0);
    if ($amount <= 0 && $procId > 0) {
        $pr = one(
            "SELECT price_cents FROM pi_procedures WHERE id=? AND clinic_id=?",
            [$procId, $cid],
        );
        $amount = (int) ($pr["price_cents"] ?? 0);
        if ($amount > 0) {
            q(
                "UPDATE pi_appointments SET payment_amount_cents=? WHERE id=? AND clinic_id=?",
                [$amount, $appointmentId, $cid],
            );
        }
    }
    if ($amount <= 0) {
        return;
    }
    $paid =
        !empty($a["payment_confirmed_at"]) ||
        ($a["payment_status"] ?? "") === "efetivada";
    $status = $paid ? "efetivada" : "prevista";
    $title = trim((string) ($a["reason"] ?? "Atendimento agendado"));
    if ($title === "") {
        $title = "Atendimento agendado";
    }
    $received = $paid ? ($a["payment_confirmed_at"] ?: now()) : null;
    $method =
        normalize_payment_method((string) ($a["payment_method"] ?? "")) ?: null;
    $existing = one(
        "SELECT id FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=?",
        [$cid, $appointmentId],
    );
    if ($existing) {
        q(
            "UPDATE pi_financial_revenues SET procedure_id=?, patient_link_id=?, title=?, amount_cents=?, status=?, payment_method=?, expected_at=?, received_at=?, updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
            [
                $procId ?: null,
                (int) ($a["patient_link_id"] ?? 0) ?: null,
                $title,
                $amount,
                $status,
                $method,
                (string) $a["start_at"],
                $received,
                $userId,
                (int) $existing["id"],
                $cid,
            ],
        );
        q(
            "UPDATE pi_appointments SET revenue_id=? WHERE id=? AND clinic_id=?",
            [(int) $existing["id"], $appointmentId, $cid],
        );
    } else {
        q(
            "INSERT INTO pi_financial_revenues (clinic_id,appointment_id,procedure_id,patient_link_id,title,amount_cents,status,payment_method,expected_at,received_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                $appointmentId,
                $procId ?: null,
                (int) ($a["patient_link_id"] ?? 0) ?: null,
                $title,
                $amount,
                $status,
                $method,
                (string) $a["start_at"],
                $received,
                $userId,
            ],
        );
        $rid = db_last_insert_id();
        q(
            "UPDATE pi_appointments SET revenue_id=? WHERE id=? AND clinic_id=?",
            [$rid, $appointmentId, $cid],
        );
    }
    try {
        financial_register_appointment_payment_movement(
            $cid,
            $appointmentId,
            $userId,
            $amount,
            (string) ($method ?: ""),
            $title,
            $paid,
            $paymentDestinationLocationId,
        );
    } catch (Throwable $e) {
        error_log("[Prontoo appointment cash movement] " . $e->getMessage());
        throw $e;
    }
}
function monthly_goal_status(int $cid): array
{

    $month = app_month_in_timezone($cid);
    $loader = static function () use ($cid, $month): array {

        [$start, $next] = app_local_month_utc_range($month, $cid);
        $goal = one(
            "SELECT target_cents,base_metric,share_with_team FROM pi_financial_goals WHERE clinic_id=? AND month_key=?",
            [$cid, $month],
        ) ?: [
            "target_cents" => 0,
            "base_metric" => "efetivada",
            "share_with_team" => 0,
        ];
        $target = (int) ($goal["target_cents"] ?? 0);
        $base = (string) ($goal["base_metric"] ?? "efetivada");
        if (!in_array($base, ["prevista", "efetivada"], true)) {
            $base = "efetivada";
        }
        if ($base === "prevista") {
            $done =
                (int) (val(
                    "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.expected_at>=? AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))",
                    [$cid, $start, $next],
                ) ?? 0);
        } else {
            $done =
                (int) (val(
                    "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?",
                    [$cid, $start, $next],
                ) ?? 0);
        }
        $pct = $target > 0
            ? min(999, round(($done / $target) * 100, 1))
            : 0;
        return [
            "month" => $month,
            "target_cents" => $target,
            "done_cents" => $done,
            "percent" => $pct,
            "share" => (int) ($goal["share_with_team"] ?? 0) === 1,
            "base_metric" => $base,
            "base_label" => financial_goal_base_label($base),
            "values" => money_br($done) . " de " . money_br($target),
        ];
    };
    if (function_exists("server_json_cache_remember")) {
        return server_json_cache_remember(
            "financial",
            server_json_cache_safe_key("monthly_goal_status", [$cid, $month]),
            20,
            $loader,
            ["clinic:" . $cid, "goal:" . $month],
        );
    }
    return $loader();
}
function monthly_goal_card(array $c, string $variant = "compact"): string
{

    if (($c["scope"] ?? "") !== "clinic") {
        return "";
    }
    $cid = (int) ($c["clinic_id"] ?? 0);
    if ($cid <= 0) {
        return "";
    }
    $st = monthly_goal_status($cid);
    if (!$st["share"] || $st["target_cents"] <= 0) {
        return "";
    }
    $pct = (float) $st["percent"];
    $bar = max(0, min(100, $pct));
    $pctLabel = number_format($pct, 0, ",", ".") . "%";
    $cls = $variant === "rolebar" ? " goal-card-rolebar" : " goal-card-compact";
    return '<section class="goal-card' .
        $cls .
        '" data-monthly-goal-card aria-label="Meta mensal: ' .
        e($pctLabel) .
        '"><span class="material-symbols-rounded goal-mini-icon" aria-hidden="true">flag</span><span class="sr-only">Meta mensal</span><div class="goal-bar" aria-hidden="true"><i data-goal-bar style="width:' .
        $bar .
        '%"></i></div><b data-goal-percent>' .
        e($pctLabel) .
        "</b></section>";
}
function page_goal_status(): void
{

    $c = need_login();
    if (($c["scope"] ?? "") !== "clinic") {
        http_response_code(403);
        exit();
    }
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    $st = monthly_goal_status((int) $c["clinic_id"]);
    $shared = (bool) ($st["share"] ?? false);
    $percent = $shared ? (float) ($st["percent"] ?? 0) : 0.0;
    echo json_encode(
        [
            "ok" => true,
            "share" => $shared,
            "percent" => $percent,
            "bar" => max(0, min(100, $percent)),
        ],
        JSON_UNESCAPED_UNICODE,
    );
    exit();
}
function page_operations(): void
{

    $c = require_can("operations");
    $items = [
        [
            "leads",
            "Interessados",
            "person_search",
            "Captar e acompanhar contatos antes do cadastro.",
        ],
        [
            "patients",
            "Pacientes",
            "patient_list",
            "Abrir cadastros, prontuários e documentos.",
        ],
        [
            "appointments",
            "Agenda",
            "calendar_month",
            "Conduzir horários, chegadas e pagamentos.",
        ],
        ["tasks", "Tarefas", "task_alt", "Distribuir e acompanhar pendências."],
        [
            "maestro",
            "Rotinas",
            "event_repeat",
            "Automatizar tarefas e avisos recorrentes do consultório.",
        ],
        [
            "audit",
            "Atividades",
            "history",
            "Consultar o histórico operacional.",
        ],
    ];
    $html = '<div class="admin-grid operations-grid">';
    foreach ($items as [$route, $title, $ico, $desc]) {
        if (can($route)) {
            $html .=
                '<a class="admin-tile" href="' .
                href($route) .
                '">' .
                icon($ico) .
                "<b>" .
                e($title) .
                "</b><span>" .
                e($desc) .
                "</span></a>";
        }
    }
    $html .= "</div>";
    page("Fluxo", page_head("Fluxo", "") . card($html, "operations-card"));
}
function counterparty_autosuggest_datalist(
    int $cid,
    string $id = "prontoo_counterparty_suggestions",
): string {

    $rows = q(
        "SELECT fc.id,p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.active=1 ORDER BY p.full_name ASC LIMIT 800",
        [$cid],
    )->fetchAll();
    $h = '<datalist id="' . e($id) . '">';
    foreach ($rows as $r) {
        $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
        $masked = mask($doc);
        $value =
            (string) $r["full_name"] . ($masked !== "" ? " · " . $masked : "");
        $label =
            $masked !== "" ? "CPF/CNPJ " . $masked : "Documento não informado";
        $h .=
            '<option value="' .
            e($value) .
            '" label="' .
            e($label) .
            '" data-counterparty-id="' .
            (int) $r["id"] .
            '" data-counterparty-name="' .
            e((string) $r["full_name"]) .
            '" data-counterparty-doc="' .
            e($masked) .
            '" data-counterparty-doc-raw="' .
            e($doc) .
            '"></option>';
    }
    return $h . "</datalist>";
}
function counterparty_lookup_field(
    int $cid,
    string $hiddenName = "counterparty_id",
    string $hiddenValue = "",
    string $inputName = "counterparty_search",
): string {

    $display = "";
    $id = (int) $hiddenValue;
    if ($id > 0) {
        $r = one(
            "SELECT p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.id=? AND fc.clinic_id=? AND fc.active=1 LIMIT 1",
            [$id, $cid],
        );
        if ($r) {
            $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
            $display =
                (string) $r["full_name"] .
                ($doc !== "" ? " · " . mask($doc) : "");
        }
    }
    return '<input type="hidden" name="' .
        e($hiddenName) .
        '" value="' .
        e($hiddenValue) .
        '" data-counterparty-id-target><input type="search" name="' .
        e($inputName) .
        '" value="' .
        e($display) .
        '" list="prontoo_counterparty_suggestions" placeholder="Digite nome, CPF ou CNPJ" autocomplete="off" spellcheck="false" required data-ds-lookup="counterparty" aria-label="Buscar pessoa, fornecedor ou credor" data-counterparty-document-suggest data-counterparty-suggest-url="' .
        e(href("counterparty_suggest")) .
        '" aria-autocomplete="list">';
}
function posted_counterparty_search_value(): string
{

    foreach ($_POST as $k => $v) {
        if (is_string($k) && str_starts_with($k, "counterparty_search")) {
            return trim((string) $v);
        }
    }
    return trim((string) ($_POST["counterparty_search"] ?? ""));
}
function resolve_counterparty_lookup_id(
    int $cid,
    int $postedId,
    string $search = "",
): int {

    if ($postedId > 0) {
        $ok = (int) val(
            "SELECT id FROM pi_financial_counterparties WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
            [$postedId, $cid],
        );
        if ($ok > 0) {
            return $ok;
        }
    }
    $search = trim($search);
    if ($search === "") {
        return 0;
    }
    $clean = mb_strtolower($search, "UTF-8");
    $digits = only_digits($search);
    $rows = q(
        "SELECT fc.id,p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.active=1 ORDER BY p.full_name ASC LIMIT 1000",
        [$cid],
    )->fetchAll();
    $exact = [];
    $nameExact = [];
    $starts = [];
    foreach ($rows as $r) {
        $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
        $masked = mask($doc);
        $display = mb_strtolower(
            (string) $r["full_name"] . ($masked !== "" ? " · " . $masked : ""),
            "UTF-8",
        );
        $name = mb_strtolower((string) $r["full_name"], "UTF-8");
        $docDigits = only_digits($doc);
        if (
            $display === $clean ||
            ($digits !== "" && $docDigits !== "" && $digits === $docDigits)
        ) {
            $exact[] = (int) $r["id"];
        }
        if ($name === $clean) {
            $nameExact[] = (int) $r["id"];
        }
        if ($search !== "" && str_starts_with($name, $clean)) {
            $starts[] = (int) $r["id"];
        }
    }
    if (count($exact) === 1) {
        return $exact[0];
    }
    if (count($nameExact) === 1) {
        return $nameExact[0];
    }
    if (count($starts) === 1) {
        return $starts[0];
    }
    return 0;
}
function page_counterparty_lookup(): void
{

    $c = require_can("financial");
    $cid = (int) $c["clinic_id"];
    $doc = only_digits((string) ($_GET["doc"] ?? ($_GET["cpf"] ?? "")));
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    if (
        security_rate_limit(
            security_client_bucket("counterparty_lookup_" . $cid),
            40,
            300,
        )
    ) {
        http_response_code(429);
        echo json_encode(
            [
                "ok" => false,
                "found" => false,
                "message" => "Aguarde alguns instantes.",
            ],
            JSON_UNESCAPED_UNICODE,
        );
        return;
    }
    $isCpf = strlen($doc) === 11;
    $isCnpj = strlen($doc) === 14;
    if (
        ($isCpf && !valid_cpf($doc)) ||
        ($isCnpj && !valid_cnpj($doc)) ||
        (!$isCpf && !$isCnpj)
    ) {
        echo json_encode(
            [
                "ok" => false,
                "found" => false,
                "message" => "Informe CPF ou CNPJ válido.",
            ],
            JSON_UNESCAPED_UNICODE,
        );
        return;
    }
    $p = $isCpf
        ? one(
            "SELECT id,full_name,cpf,birth_date,legal_document FROM pi_persons WHERE cpf=? LIMIT 1",
            [$doc],
        )
        : one(
            "SELECT id,full_name,cpf,birth_date,legal_document FROM pi_persons WHERE legal_document=? LIMIT 1",
            [$doc],
        );
    if (!$p) {
        echo json_encode(
            [
                "ok" => true,
                "found" => false,
                "message" => "Documento válido. Continue o cadastro do credor.",
            ],
            JSON_UNESCAPED_UNICODE,
        );
        return;
    }
    $link = one(
        "SELECT id FROM pi_financial_counterparties WHERE clinic_id=? AND person_id=? AND kind='credor' AND active=1 LIMIT 1",
        [$cid, (int) $p["id"]],
    );
    if (!$link) {
        echo json_encode(
            [
                "ok" => true,
                "found" => false,
                "message" => "Documento válido. Continue o cadastro do credor.",
            ],
            JSON_UNESCAPED_UNICODE,
        );
        return;
    }
    echo json_encode(
        [
            "ok" => true,
            "found" => true,
            "already_counterparty" => true,
            "counterparty_id" => (int) $link["id"],
            "message" =>
                "Este credor já está cadastrado. Os dados foram recuperados.",
            "name" => (string) ($p["full_name"] ?? ""),
            "document" => mask(
                (string) ($p["cpf"] ?? "" ?: $p["legal_document"] ?? ""),
            ),
            "birth_date" => app_date_input_from_storage($p["birth_date"] ?? ""),
        ],
        JSON_UNESCAPED_UNICODE,
    );
}
function page_counterparty_suggest(): void
{

    $c = require_can("financial");
    $cid = (int) $c["clinic_id"];
    $q = trim((string) ($_GET["q"] ?? ""));
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    if ($q === "") {
        echo json_encode(["ok" => true, "items" => []], JSON_UNESCAPED_UNICODE);
        return;
    }
    $limit = max(1, min(80, (int) ($_GET["limit"] ?? 12)));
    $digits = only_digits($q);
    $params = [$cid];
    $where = "fc.clinic_id=? AND fc.active=1";
    if ($digits !== "" && mb_strlen($q, "UTF-8") >= 2) {
        $where .=
            " AND (p.full_name LIKE ? OR p.cpf LIKE ? OR p.legal_document LIKE ?)";
        $params[] = $q . "%";
        $params[] = "%" . $digits . "%";
        $params[] = "%" . $digits . "%";
    } else {
        $where .= " AND p.full_name LIKE ?";
        $params[] = $q . "%";
    }
    $rows = q(
        "SELECT fc.id,p.full_name,p.cpf,p.legal_document FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE $where ORDER BY p.full_name ASC, fc.id DESC LIMIT " .
            (int) $limit,
        $params,
    )->fetchAll();
    $items = [];
    foreach ($rows as $r) {
        $doc = (string) ($r["cpf"] ?? "" ?: $r["legal_document"] ?? "");
        $masked = mask($doc);
        $items[] = [
            "id" => (int) $r["id"],
            "name" => (string) $r["full_name"],
            "document" => $masked,
            "value" =>
                (string) $r["full_name"] .
                ($masked !== "" ? " · " . $masked : ""),
        ];
    }
    echo json_encode(["ok" => true, "items" => $items], JSON_UNESCAPED_UNICODE);
    return;
}
function counterparty_options(int $cid): array
{

    $rows = q(
        "SELECT fc.id,p.full_name FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.active=1 ORDER BY p.full_name LIMIT 300",
        [$cid],
    )->fetchAll();
    $out = ["" => "Selecione o credor"];
    foreach ($rows as $r) {
        $out[(int) $r["id"]] = $r["full_name"];
    }
    return $out;
}
function financial_account_options(int $cid): array
{

    $rows = q(
        "SELECT id,name FROM pi_financial_accounts WHERE clinic_id=? AND active=1 ORDER BY name LIMIT 200",
        [$cid],
    )->fetchAll();
    $out = ["" => "Caixa geral"];
    foreach ($rows as $r) {
        $out[(int) $r["id"]] = $r["name"];
    }
    return $out;
}
function financial_date_or_null(string $date, bool $end = false): ?string
{

    $date = trim($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    return $date . ($end ? " 23:59:59" : " 12:00:00");
}
function financial_account_label_options(
    int $cid,
    string $empty = "Escolha a conta",
): array {

    $rows = q(
        "SELECT id,name FROM pi_financial_accounts WHERE clinic_id=? AND active=1 ORDER BY FIELD(account_type,'caixa_interno','conta_corrente','conta_pagamento','conta_poupanca','investimento'), name LIMIT 200",
        [$cid],
    )->fetchAll();
    $out = ["" => $empty];
    foreach ($rows as $r) {
        $out[(int) $r["id"]] = $r["name"];
    }
    return $out;
}
function financial_ensure_default_accounts(int $cid, int $uid): void
{

    if ($cid <= 0 || clinic_read_only_db($cid)) {
        return;
    }
    try {
        financial_operational_schema_ready();
        financial_ensure_admin_safe($cid, $uid);
        $cashId =
            (int) (val(
                "SELECT id FROM pi_financial_accounts WHERE clinic_id=? AND account_type='caixa_interno' AND LOWER(name)=LOWER('Cofre do Consultório') LIMIT 1",
                [$cid],
            ) ?:
            0);
        if ($cashId <= 0) {
            q(
                "INSERT INTO pi_financial_accounts (clinic_id,name,bank_name,account_type,opening_balance_cents,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())",
                [
                    $cid,
                    "Cofre do Consultório",
                    null,
                    "caixa_interno",
                    0,
                    $uid,
                ],
            );
        }
    } catch (Throwable $e) {
        error_log("[Prontoo finance default accounts] " . $e->getMessage());
    }
}
function financial_account_icon(array $account): string
{

    return ($account["account_type"] ?? "") === "caixa_interno"
        ? "payments"
        : "account_balance";
}
function financial_account_belongs(int $cid, int $accountId): bool
{

    return $accountId > 0 &&
        (int) (val(
            "SELECT id FROM pi_financial_accounts WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
            [$accountId, $cid],
        ) ?:
            0) > 0;
}
function financial_counterparty_light(
    int $cid,
    int $uid,
    string $name,
    string $doc = "",
    string $notes = "",
): int {

    $name = trim(preg_split("/\s+·\s+/", trim($name), 2)[0] ?? $name);
    $doc = only_digits($doc);
    if ($name === "") {
        return 0;
    }
    $pid = 0;
    if ($doc !== "" && in_array(strlen($doc), [11, 14], true)) {
        try {
            $pid = save_person_by_document($name, $doc, null);
        } catch (Throwable $e) {
            $pid = 0;
        }
    }
    if ($pid <= 0) {
        $pid =
            (int) (val(
                "SELECT p.id FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND LOWER(p.full_name)=LOWER(?) AND p.cpf IS NULL AND p.legal_document IS NULL ORDER BY fc.id ASC LIMIT 1",
                [$cid, $name],
            ) ?:
            0);
        if ($pid > 0) {
            q(
                "UPDATE pi_persons SET full_name=?, updated_at=NOW() WHERE id=?",
                [$name, $pid],
            );
        } else {
            q(
                "INSERT INTO pi_persons (full_name,cpf,birth_date,created_at) VALUES (?,NULL,NULL,NOW())",
                [$name],
            );
            $pid = db_last_insert_id();
        }
    }
    if ($pid <= 0) {
        return 0;
    }
    q(
        "INSERT INTO pi_financial_counterparties (clinic_id,person_id,kind,notes,created_by,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE notes=COALESCE(NULLIF(VALUES(notes),''),notes), active=1, updated_at=NOW()",
        [$cid, $pid, "credor", $notes, $uid],
    );
    return (int) (val(
        "SELECT id FROM pi_financial_counterparties WHERE clinic_id=? AND person_id=? AND kind='credor' LIMIT 1",
        [$cid, $pid],
    ) ?:
    0);
}
function financial_counterparty_from_post(int $cid, int $uid): int
{

    $id = resolve_counterparty_lookup_id(
        $cid,
        (int) ($_POST["counterparty_id"] ?? 0),
        posted_counterparty_search_value(),
    );
    if ($id > 0) {
        return $id;
    }
    return financial_counterparty_light(
        $cid,
        $uid,
        posted_counterparty_search_value(),
        (string) ($_POST["counterparty_doc"] ?? ""),
        (string) ($_POST["counterparty_notes"] ?? ""),
    );
}
function financial_account_balances(int $cid): array
{

    ensure_financial_operational_schema();
    $rows = q(
        "SELECT id,name,account_type,bank_name,opening_balance_cents,active FROM pi_financial_accounts WHERE clinic_id=? ORDER BY active DESC, FIELD(account_type,'caixa_interno','conta_corrente','conta_pagamento','conta_poupanca','investimento'), name LIMIT 200",
        [$cid],
    )->fetchAll();
    $ids = [];
    foreach ($rows as $r) {
        $ids[] = (int) $r["id"];
    }
    $map = function (string $sql) use ($cid): array {

        $out = [];
        foreach (q($sql, [$cid])->fetchAll() as $r) {
            $out[(int) $r["account_id"]] = (int) $r["total"];
        }
        return $out;
    };
    $revenues = $map(
        "SELECT account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND account_id IS NOT NULL GROUP BY account_id",
    );
    $expenses = $map(
        "SELECT account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_expenses WHERE clinic_id=? AND status='paga' AND account_id IS NOT NULL GROUP BY account_id",
    );
    $tin = $map(
        "SELECT account_to_id account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_transfers WHERE clinic_id=? GROUP BY account_to_id",
    );
    $tout = $map(
        "SELECT account_from_id account_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_transfers WHERE clinic_id=? GROUP BY account_from_id",
    );
    $out = [];
    $total = 0;
    foreach ($rows as $r) {
        $id = (int) $r["id"];
        $balance =
            (int) $r["opening_balance_cents"] +
            ($revenues[$id] ?? 0) -
            ($expenses[$id] ?? 0) +
            ($tin[$id] ?? 0) -
            ($tout[$id] ?? 0);
        $r["balance_cents"] = $balance;
        $r["received_cents"] = $revenues[$id] ?? 0;
        $r["paid_cents"] = $expenses[$id] ?? 0;
        $r["transfer_in_cents"] = $tin[$id] ?? 0;
        $r["transfer_out_cents"] = $tout[$id] ?? 0;
        if ((int) $r["active"] === 1) {
            $total += $balance;
        }
        $out[] = $r;
    }
    return ["rows" => $out, "total_cents" => $total];
}
function financial_dashboard_numbers(int $cid): array
{

    $today = app_today_in_timezone($cid);
    [$todayStart, $todayEnd] = app_local_day_utc_range($today, $cid);
    $zone = new DateTimeZone(app_context_timezone(null, $cid));
    $baseDay = new DateTimeImmutable($today . " 00:00:00", $zone);
    $d7End = (string) $baseDay
        ->modify("+8 days")
        ->setTimezone(new DateTimeZone("UTC"))
        ->getTimestamp();
    $d30End = (string) $baseDay
        ->modify("+31 days")
        ->setTimezone(new DateTimeZone("UTC"))
        ->getTimestamp();
    $bal = financial_account_balances($cid);
    $rev =
        one(
            "SELECT COALESCE(SUM(CASE WHEN status='prevista' AND expected_at IS NOT NULL AND expected_at<? THEN amount_cents ELSE 0 END),0) receive30, COALESCE(SUM(CASE WHEN status='prevista' AND expected_at IS NOT NULL AND expected_at<? THEN amount_cents ELSE 0 END),0) receive7, COALESCE(SUM(CASE WHEN status='prevista' AND expected_at>=? AND expected_at<? THEN amount_cents ELSE 0 END),0) receiveToday, COALESCE(SUM(CASE WHEN status='prevista' AND expected_at<? THEN amount_cents ELSE 0 END),0) overRec FROM pi_financial_revenues WHERE clinic_id=?",
            [$d30End, $d7End, $todayStart, $todayEnd, $todayStart, $cid],
        ) ?:
        [];
    $exp =
        one(
            "SELECT COALESCE(SUM(CASE WHEN status='prevista' AND due_at IS NOT NULL AND due_at<? THEN amount_cents ELSE 0 END),0) pay30, COALESCE(SUM(CASE WHEN status='prevista' AND due_at IS NOT NULL AND due_at<? THEN amount_cents ELSE 0 END),0) pay7, COALESCE(SUM(CASE WHEN status='prevista' AND due_at>=? AND due_at<? THEN amount_cents ELSE 0 END),0) payToday, COALESCE(SUM(CASE WHEN status='prevista' AND due_at<? THEN amount_cents ELSE 0 END),0) overPay FROM pi_financial_expenses WHERE clinic_id=?",
            [$d30End, $d7End, $todayStart, $todayEnd, $todayStart, $cid],
        ) ?:
        [];
    $receive30 = (int) ($rev["receive30"] ?? 0);
    $receive7 = (int) ($rev["receive7"] ?? 0);
    $receiveToday = (int) ($rev["receiveToday"] ?? 0);
    $overRec = (int) ($rev["overRec"] ?? 0);
    $pay30 = (int) ($exp["pay30"] ?? 0);
    $pay7 = (int) ($exp["pay7"] ?? 0);
    $payToday = (int) ($exp["payToday"] ?? 0);
    $overPay = (int) ($exp["overPay"] ?? 0);
    $projected = (int) $bal["total_cents"] + $receive30 - $pay30;
    $health = "Boa";
    $healthClass = "good";
    $healthMsg =
        "O saldo atual cobre as despesas previstas dos próximos 30 dias.";
    if ((int) $bal["total_cents"] + $receive7 < $pay7 || $overPay > 0) {
        $health = "Crítica";
        $healthClass = "bad";
        $healthMsg =
            "Há risco no caixa: despesas próximas ou vencidas superam a cobertura disponível.";
    } elseif ((int) $bal["total_cents"] + $receive30 < $pay30 || $overRec > 0) {
        $health = "Atenção";
        $healthClass = "warn";
        $healthMsg =
            "Revise recebimentos previstos e despesas dos próximos 30 dias.";
    }
    return compact(
        "bal",
        "receive30",
        "pay30",
        "receive7",
        "pay7",
        "receiveToday",
        "payToday",
        "overRec",
        "overPay",
        "projected",
        "health",
        "healthClass",
        "healthMsg",
    );
}
function financial_recent_operations_timeline(int $cid): string
{

    $from = date("Y-m-d H:i:s", strtotime("-7 days"));
    try {
        $rows = q(
            "SELECT kind,title,amount_cents,happened_at,account_name,extra_name FROM (
 SELECT 'revenue' kind, r.title title, r.amount_cents amount_cents, r.received_at happened_at, COALESCE(a.name,'sem conta') account_name, NULL extra_name
 FROM pi_financial_revenues r LEFT JOIN pi_financial_accounts a ON a.id=r.account_id AND a.clinic_id=r.clinic_id
 WHERE r.clinic_id=? AND r.status='efetivada' AND r.received_at IS NOT NULL AND r.received_at>=?
 UNION ALL
 SELECT 'expense' kind, e.title title, e.amount_cents amount_cents, e.paid_at happened_at, COALESCE(a.name,'sem conta') account_name, COALESCE(p.full_name,'sem credor') extra_name
 FROM pi_financial_expenses e LEFT JOIN pi_financial_accounts a ON a.id=e.account_id AND a.clinic_id=e.clinic_id LEFT JOIN pi_financial_counterparties fc ON fc.id=e.counterparty_id AND fc.clinic_id=e.clinic_id LEFT JOIN pi_persons p ON p.id=fc.person_id
 WHERE e.clinic_id=? AND e.status='paga' AND e.paid_at IS NOT NULL AND e.paid_at>=?
 UNION ALL
 SELECT 'transfer' kind, 'Transferência entre contas' title, t.amount_cents amount_cents, t.transfer_at happened_at, COALESCE(af.name,'origem') account_name, COALESCE(atc.name,'destino') extra_name
 FROM pi_financial_transfers t LEFT JOIN pi_financial_accounts af ON af.id=t.account_from_id AND af.clinic_id=t.clinic_id LEFT JOIN pi_financial_accounts atc ON atc.id=t.account_to_id AND atc.clinic_id=t.clinic_id
 WHERE t.clinic_id=? AND t.transfer_at>=?
 ) ops ORDER BY happened_at DESC LIMIT 20",
            [$cid, $from, $cid, $from, $cid, $from],
        )->fetchAll();
    } catch (Throwable $e) {
        error_log("[Prontoo finance timeline] " . $e->getMessage());
        return '<div class="empty">Não foi possível carregar a timeline financeira.</div>';
    }
    $items = [];
    foreach ($rows as $r) {
        $kind = (string) $r["kind"];
        $amount = money_br((int) $r["amount_cents"]);
        $account = (string) ($r["account_name"] ?? "");
        $extra = (string) ($r["extra_name"] ?? "");
        if ($kind === "revenue") {
            $items[] = [
                "icon" => "add_card",
                "time" => dt_br($r["happened_at"]),
                "title" => "Receita recebida",
                "body" => (string) $r["title"],
                "meta" => $amount . " · " . $account,
                "class" => "finance-op revenue",
            ];
        } elseif ($kind === "expense") {
            $items[] = [
                "icon" => "receipt_long",
                "time" => dt_br($r["happened_at"]),
                "title" => "Despesa paga",
                "body" => (string) $r["title"],
                "meta" => $amount . " · " . $extra . " · " . $account,
                "class" => "finance-op expense",
            ];
        } else {
            $items[] = [
                "icon" => "sync_alt",
                "time" => dt_br($r["happened_at"]),
                "title" => "Transferência de saldo",
                "body" => $account . " → " . $extra,
                "meta" => $amount . " · não altera receita nem despesa",
                "class" => "finance-op transfer",
            ];
        }
    }
    return timeline($items, "Nenhuma operação efetivada nos últimos 7 dias.");
}
function financial_status_pill(
    string $status,
    ?string $date = null,
    string $type = "revenue",
): string {

    $label = match ($status) {
        "efetivada" => "Recebida",
        "paga" => "Paga",
        "cancelada" => "Cancelada",
        default => $type === "expense" ? "A pagar" : "Prevista",
    };
    $cls = "pill";
    if ($status === "cancelada") {
        $cls .= " muted";
    } elseif (in_array($status, ["efetivada", "paga"], true)) {
        $cls .= " ok";
    } elseif ($date && strtotime($date) < strtotime("today")) {
        $label = $type === "expense" ? "Vencida" : "Atrasada";
        $cls .= " bad";
    }
    return '<span class="' . $cls . '">' . e($label) . "</span>";
}
function financial_report_line(string $label, int $value): string
{

    return '<article class="finance-row mini"><span>' .
        e($label) .
        "</span><b>" .
        money_br($value) .
        "</b></article>";
}
function financial_location_type_options(): array
{

    return [
        "pos" => "Gaveta",
        "admin_safe" => "Cofre do Consultório",
        "bank_account" => "Conta Bancária",
    ];
}
function financial_location_type_label(string $type): string
{

    $o = financial_location_type_options();
    return $o[$type] ?? "Local financeiro";
}
function financial_money_input(
    string $name,
    string $value = "",
    string $extra = "",
): string {

    return input($name, "text", $value, 'inputmode="decimal" ' . $extra);
}
function financial_cashier_roles(): array
{

    return ["recepcionista"];
}
function financial_is_cashier(array $c): bool
{

    return ($c["scope"] ?? "") === "clinic" &&
        in_array((string) ($c["role"] ?? ""), financial_cashier_roles(), true);
}
function financial_today(int $cid = 0): string
{

    return app_today_in_timezone($cid);
}
function financial_human_session_status(string $status): string
{

    return match ($status) {
        "open" => "Aberta",
        "kept_closed" => "Mantido fechado",
        "opening_pending_review" => "Abertura da Gaveta aguardando autorização",
        "opening_rejected" => "Abertura da Gaveta recusada",
        "closed_pending_review" => "Fechada, aguardando conferência",
        "approved" => "Conferida",
        "rejected" => "Devolvida para correção",
        default => "Em análise",
    };
}
function financial_human_movement_type(string $type): string
{

    return match ($type) {
        "receipt" => "Recebi",
        "payment" => "Paguei",
        "transfer" => "Transferência",
        "deposit" => "Depósito bancário",
        "cash_opening" => "Saldo inicial",
        "cash_closing" => "Fechamento",
        "adjustment" => "Ajuste",
        "refund" => "Devolução",
        default => "Movimentação",
    };
}
function financial_human_movement_status(string $status): string
{

    return match ($status) {
        "confirmed" => "Confirmado",
        "pending_review" => "Aguardando conferência",
        "cancelled" => "Cancelado",
        "rejected" => "Rejeitado",
        default => $status !== ""
            ? ucfirst(str_replace("_", " ", $status))
            : "Em análise",
    };
}
function financial_movement_icon(string $type): string
{

    return match ($type) {
        "receipt" => "add_card",
        "payment" => "payments",
        "refund" => "keyboard_return",
        "transfer" => "sync_alt",
        "deposit" => "account_balance",
        "cash_opening" => "lock_open",
        "cash_closing" => "lock",
        default => "receipt_long",
    };
}
function financial_operational_schema_ready(): void
{

    return;
}
function financial_ensure_admin_safe(int $cid, int $uid = 0): int
{

    if ($cid <= 0) {
        return 0;
    }
    financial_operational_schema_ready();
    $id =
        (int) (val(
            "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='admin_safe' AND active=1 ORDER BY id ASC LIMIT 1",
            [$cid],
        ) ?:
        0);
    if ($id > 0) {
        return $id;
    }
    if (clinic_read_only_db($cid)) {
        return 0;
    }
    q(
        "INSERT INTO pi_financial_locations (clinic_id,location_type,name,user_id,account_id,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())",
        [$cid, "admin_safe", "Cofre do Consultório", null, null, $uid ?: null],
    );
    return db_last_insert_id();
}
function financial_ensure_cashier_location(int $cid, int $uid): int
{

    return financial_cashier_location_for_user($cid, $uid);
}
function financial_ensure_bank_location(
    int $cid,
    int $accountId,
    int $uid = 0,
): int {

    if ($cid <= 0 || $accountId <= 0) {
        return 0;
    }
    financial_operational_schema_ready();
    $id =
        (int) (val(
            "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='bank_account' AND account_id=? AND active=1 ORDER BY id ASC LIMIT 1",
            [$cid, $accountId],
        ) ?:
        0);
    if ($id > 0) {
        return $id;
    }
    $acc = one(
        "SELECT id,name FROM pi_financial_accounts WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
        [$accountId, $cid],
    );
    if (!$acc) {
        return 0;
    }
    q(
        "INSERT INTO pi_financial_locations (clinic_id,location_type,name,user_id,account_id,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())",
        [
            $cid,
            "bank_account",
            (string) $acc["name"],
            null,
            $accountId,
            $uid ?: null,
        ],
    );
    return db_last_insert_id();
}
function financial_cashier_user_options(int $cid, bool $withEmpty = true): array
{

    $out = $withEmpty ? ["" => "Escolha o colaborador"] : [];
    if ($cid <= 0) {
        return $out;
    }
    $rows = q(
        "SELECT DISTINCT u.id,u.name FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.clinic_id=? AND ur.active=1 AND ur.role_code IN ('recepcionista') ORDER BY u.name LIMIT 300",
        [$cid],
    )->fetchAll();
    foreach ($rows as $r) {
        $out[(int) $r["id"]] = (string) $r["name"];
    }
    return $out;
}
function financial_drawer_location_options(
    int $cid,
    bool $withEmpty = true,
): array {

    $out = $withEmpty ? ["" => "Escolha a gaveta"] : [];
    if ($cid <= 0) {
        return $out;
    }
    $rows = q(
        "SELECT id,name FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND active=1 ORDER BY name,id LIMIT 200",
        [$cid],
    )->fetchAll();
    foreach ($rows as $r) {
        $out[(int) $r["id"]] = (string) $r["name"];
    }
    return $out;
}
function financial_cashier_assigned_locations(int $cid, int $uid): array
{

    if ($cid <= 0 || $uid <= 0) {
        return [];
    }
    $rows = q(
        "SELECT l.id,l.name FROM pi_financial_locations l JOIN pi_financial_location_users lu ON lu.location_id=l.id AND lu.clinic_id=l.clinic_id AND lu.user_id=? AND lu.active=1 WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,l.id LIMIT 20",
        [$uid, $cid],
    )->fetchAll();
    if ($rows) {
        return $rows;
    }
    return q(
        "SELECT id,name FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND user_id=? AND active=1 ORDER BY id ASC LIMIT 20",
        [$cid, $uid],
    )->fetchAll();
}
function financial_cashier_location_for_user(int $cid, int $uid): int
{

    $rows = financial_cashier_assigned_locations($cid, $uid);
    return $rows ? (int) $rows[0]["id"] : 0;
}
function financial_cashier_drawer_name(int $cid, int $uid): string
{

    $rows = financial_cashier_assigned_locations($cid, $uid);
    return $rows ? (string) $rows[0]["name"] : "";
}
function financial_create_drawer(int $cid, int $uid, string $name): int
{

    $name = trim($name);
    if ($cid <= 0) {
        throw new RuntimeException("Consultório inválido.");
    }
    if ($name === "") {
        throw new RuntimeException("Informe o nome da Gaveta.");
    }
    if (mb_strlen($name) > 120) {
        $name = mb_substr($name, 0, 120);
    }
    $exists =
        (int) (val(
            "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND name=? AND active=1 LIMIT 1",
            [$cid, $name],
        ) ?:
        0);
    if ($exists > 0) {
        return $exists;
    }
    q(
        "INSERT INTO pi_financial_locations (clinic_id,location_type,name,user_id,account_id,active,created_by,created_at) VALUES (?,?,?,?,?,1,?,NOW())",
        [$cid, "pos", $name, null, null, $uid ?: null],
    );
    $id = db_last_insert_id();
    audit("gaveta_criada", "financeiro", $id, [
        "nome" => $name,
        "audit_body" =>
            "Administrativo criou Gaveta para uso do Caixa do Atendimento.",
    ]);
    return $id;
}
function financial_rename_drawer(
    int $cid,
    int $drawerId,
    int $adminUid,
    string $name,
): void {

    $name = trim($name);
    if ($cid <= 0 || $drawerId <= 0) {
        throw new RuntimeException("Gaveta inválida.");
    }
    if ($name === "") {
        throw new RuntimeException("Informe o novo nome da Gaveta.");
    }
    if (mb_strlen($name) > 120) {
        $name = mb_substr($name, 0, 120);
    }
    $drawer = one(
        "SELECT id,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND location_type='pos' AND active=1 LIMIT 1",
        [$drawerId, $cid],
    );
    if (!$drawer) {
        throw new RuntimeException("Gaveta não encontrada.");
    }
    $duplicate =
        (int) (val(
            "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND active=1 AND name=? AND id<>? LIMIT 1",
            [$cid, $name, $drawerId],
        ) ?:
        0);
    if ($duplicate > 0) {
        throw new RuntimeException("Já existe uma Gaveta ativa com este nome.");
    }
    if ($name === (string) $drawer["name"]) {
        return;
    }
    q(
        "UPDATE pi_financial_locations SET name=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'",
        [$name, $drawerId, $cid],
    );
    audit("gaveta_renomeada", "financeiro", $drawerId, [
        "nome_anterior" => (string) $drawer["name"],
        "nome_novo" => $name,
        "audit_body" =>
            "Administrativo renomeou Gaveta do Caixa do Atendimento.",
    ]);
}
function financial_link_drawer_user(
    int $cid,
    int $drawerId,
    int $cashierUid,
    int $adminUid,
): void {

    if ($cid <= 0 || $drawerId <= 0 || $cashierUid <= 0) {
        throw new RuntimeException(
            "Escolha a Gaveta e o colaborador do Atendimento.",
        );
    }
    if (!financial_location_belongs($cid, $drawerId)) {
        throw new RuntimeException("Gaveta inválida.");
    }
    $loc = one(
        "SELECT id,location_type,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
        [$drawerId, $cid],
    );
    if (!$loc || (string) $loc["location_type"] !== "pos") {
        throw new RuntimeException("Escolha uma Gaveta válida.");
    }
    if (!clinic_user_exists($cid, $cashierUid, financial_cashier_roles())) {
        throw new RuntimeException(
            "Escolha um colaborador ativo do Atendimento.",
        );
    }
    db_tx(function () use ($cid, $drawerId, $cashierUid, $adminUid): void {

        q(
            "UPDATE pi_financial_location_users SET active=0, updated_at=NOW() WHERE clinic_id=? AND user_id=? AND active=1",
            [$cid, $cashierUid],
        );
        q(
            "INSERT INTO pi_financial_location_users (clinic_id,location_id,user_id,active,created_by,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE active=1, updated_at=NOW(), created_by=VALUES(created_by)",
            [$cid, $drawerId, $cashierUid, 1, $adminUid ?: null],
        );
    });
    audit("gaveta_vinculada", "financeiro", $drawerId, [
        "colaborador" => $cashierUid,
        "audit_body" =>
            "Administrativo vinculou colaborador do Atendimento à Gaveta.",
    ]);
}
function financial_unlink_drawer_user(
    int $cid,
    int $linkId,
    int $adminUid,
): void {

    if ($cid <= 0 || $linkId <= 0) {
        throw new RuntimeException("Vínculo inválido.");
    }
    $link = one(
        "SELECT * FROM pi_financial_location_users WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
        [$linkId, $cid],
    );
    if (!$link) {
        throw new RuntimeException("Vínculo não encontrado.");
    }
    q(
        "UPDATE pi_financial_location_users SET active=0, updated_at=NOW() WHERE id=? AND clinic_id=?",
        [$linkId, $cid],
    );
    audit("gaveta_desvinculada", "financeiro", (int) $link["location_id"], [
        "colaborador" => (int) $link["user_id"],
        "audit_body" =>
            "Administrativo removeu vínculo de colaborador com Gaveta.",
    ]);
}
function financial_deactivate_drawer(
    int $cid,
    int $drawerId,
    int $adminUid,
): void {

    if ($cid <= 0 || $drawerId <= 0) {
        throw new RuntimeException("Gaveta inválida.");
    }
    $open = one(
        "SELECT s.id,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id=? AND s.status='open' LIMIT 1",
        [$cid, $drawerId],
    );
    if ($open) {
        throw new RuntimeException(
            "Esta Gaveta está aberta por " .
                first_name((string) ($open["user_name"] ?? "Atendimento")) .
                ". Feche o caixa antes de desativar.",
        );
    }
    q(
        "UPDATE pi_financial_locations SET active=0, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'",
        [$drawerId, $cid],
    );
    q(
        "UPDATE pi_financial_location_users SET active=0, updated_at=NOW() WHERE clinic_id=? AND location_id=?",
        [$cid, $drawerId],
    );
    audit("gaveta_desativada", "financeiro", $drawerId, [
        "audit_body" => "Administrativo desativou Gaveta do Atendimento.",
    ]);
}
function financial_drawer_row(int $cid, int $drawerId): ?array
{

    if ($cid <= 0 || $drawerId <= 0) {
        return null;
    }
    return one(
        "SELECT * FROM pi_financial_locations WHERE id=? AND clinic_id=? AND location_type='pos' AND active=1 LIMIT 1",
        [$drawerId, $cid],
    );
}
function financial_drawer_auto_unlock_if_due(int $cid, int $drawerId): ?array
{

    $d = financial_drawer_row($cid, $drawerId);
    if (!$d) {
        return null;
    }
    return financial_drawer_auto_unlock_row_if_due($cid, $d);
}
function financial_drawer_auto_unlock_row_if_due(int $cid, array $d): array
{

    $drawerId = (int) ($d["id"] ?? 0);
    if ($cid <= 0 || $drawerId <= 0) {
        return $d;
    }
    $status = (string) ($d["drawer_lock_status"] ?? "unlocked");
    $unlockAt = trim((string) ($d["drawer_unlock_at"] ?? ""));
    if ($status === "locked" && $unlockAt !== "") {
        $unlock = app_parse_db_utc($unlockAt);
        $now = app_now_utc();
        $lockedDay = (string) ($d["drawer_locked_business_date"] ?? "");
        $today = financial_today($cid);
        if (
            $unlock &&
            $unlock <= $now &&
            ($lockedDay === "" || $today > $lockedDay)
        ) {
            q(
                "UPDATE pi_financial_locations SET drawer_lock_status='unlocked', drawer_unlocked_at=NOW(), updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'",
                [$drawerId, $cid],
            );
            audit(
                "gaveta_destrancada_automaticamente",
                "financeiro",
                $drawerId,
                [
                    "locked_business_date" => $lockedDay,
                    "unlock_at" => $unlockAt,
                    "audit_body" =>
                        "Gaveta destrancada automaticamente após horário definido pela Gerência.",
                ],
            );
            $d["drawer_lock_status"] = "unlocked";
            $d["drawer_unlocked_at"] = app_now_utc()->format(
                "Y-m-d H:i:s",
            );
        }
    }
    return $d;
}
function financial_drawer_lock_label(array $drawer, int $cid): string
{

    $status = (string) ($drawer["drawer_lock_status"] ?? "unlocked");
    if ($status !== "locked") {
        return "Destrancada";
    }
    $unlock = trim((string) ($drawer["drawer_unlock_at"] ?? ""));
    if ($unlock !== "") {
        return "Trancada até " . dt_br($unlock);
    }
    return "Trancada para conferência";
}
function financial_drawer_guard_can_use(int $cid, int $drawerId): void
{

    $d = financial_drawer_auto_unlock_if_due($cid, $drawerId);
    if (!$d) {
        throw new RuntimeException("Gaveta inválida.");
    }
    if ((string) ($d["drawer_lock_status"] ?? "unlocked") === "locked") {
        $lockedDay = (string) ($d["drawer_locked_business_date"] ?? "");
        $unlock = trim((string) ($d["drawer_unlock_at"] ?? ""));
        if ($unlock !== "") {
            throw new RuntimeException(
                "Esta Gaveta está trancada para conferência da Gerência até " .
                    dt_br($unlock) .
                    ". Somente depois deste horário ela poderá ser aberta novamente.",
            );
        }
        throw new RuntimeException(
            "Esta Gaveta está trancada para conferência da Gerência. Aguarde a conferência e o agendamento do destravamento.",
        );
    }
}
function financial_notify_drawer_locked(
    int $cid,
    int $drawerId,
    int $sessionId,
    int $uid,
): void {

    try {
        $drawer = financial_drawer_row($cid, $drawerId);
        $name = $drawer ? (string) $drawer["name"] : "Gaveta";
        q(
            "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                "Gaveta trancada para conferência",
                "A Gaveta " .
                $name .
                " foi trancada após o fechamento do último caixa. Confira os fechamentos, confirme a destinação das retiradas e escolha o horário de destravamento para o dia seguinte.",
                1,
                "role",
                "gerente",
                null,
                $uid ?: null,
            ],
        );
        if (function_exists("counter_inc")) {
            counter_inc("notices_total");
        }
        if (function_exists("clinic_metric_inc")) {
            clinic_metric_inc($cid, "notices");
        }
    } catch (Throwable $e) {
        error_log("[Prontoo gaveta aviso] " . $e->getMessage());
    }
}
function financial_drawer_lock_after_close(
    int $cid,
    int $drawerId,
    string $businessDate,
    int $sessionId,
    int $uid,
): void {

    if ($cid <= 0 || $drawerId <= 0) {
        return;
    }
    $open = financial_drawer_open_session($cid, $drawerId, 0);
    if ($open) {
        return;
    }
    $current = financial_drawer_row($cid, $drawerId);
    if (
        !$current ||
        (string) ($current["drawer_lock_status"] ?? "unlocked") === "locked"
    ) {
        return;
    }
    $linked =
        (int) (val(
            "SELECT COUNT(*) FROM pi_financial_location_users WHERE clinic_id=? AND location_id=? AND active=1",
            [$cid, $drawerId],
        ) ?:
        0);
    if ($linked <= 0 && (int) ($current["user_id"] ?? 0) > 0) {
        $linked = 1;
    }
    if ($linked <= 0) {
        $linked = 1;
    }
    $done =
        (int) (val(
            "SELECT COUNT(DISTINCT user_id) FROM pi_cash_sessions WHERE clinic_id=? AND location_id=? AND business_date=? AND status IN ('closed_pending_review','approved','rejected','kept_closed')",
            [$cid, $drawerId, $businessDate],
        ) ?:
        0);
    if ($done < $linked) {
        audit(
            "gaveta_permanece_destrancada_turnos_pendentes",
            "financeiro",
            $drawerId,
            [
                "cash_session_id" => $sessionId,
                "business_date" => $businessDate,
                "colaboradores_vinculados" => $linked,
                "colaboradores_com_sessao_finalizada" => $done,
                "audit_body" =>
                    "Gaveta permaneceu disponível porque ainda há colaboradores vinculados sem fechamento no dia.",
            ],
        );
        return;
    }
    q(
        "UPDATE pi_financial_locations SET drawer_lock_status='locked', drawer_locked_business_date=?, drawer_locked_at=NOW(), drawer_unlock_at=NULL, drawer_unlocked_at=NULL, drawer_reviewed_by=NULL, drawer_reviewed_at=NULL, drawer_review_notes=NULL, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'",
        [$businessDate, $drawerId, $cid],
    );
    audit("gaveta_trancada_para_conferencia", "financeiro", $drawerId, [
        "cash_session_id" => $sessionId,
        "business_date" => $businessDate,
        "colaboradores_vinculados" => $linked,
        "audit_body" =>
            "Gaveta trancada automaticamente após o último colaborador vinculado finalizar o Caixa do dia.",
    ]);
    financial_notify_drawer_locked($cid, $drawerId, $sessionId, $uid);
}
function financial_default_drawer_unlock_local(
    int $cid,
    int $drawerId,
    ?array $drawer = null,
): string
{

    $d = $drawer ?: financial_drawer_row($cid, $drawerId);
    $locked =
        (string) ($d["drawer_locked_business_date"] ?? financial_today($cid));
    try {
        $dt = new DateTimeImmutable(
            $locked . " 08:00:00",
            new DateTimeZone(app_context_timezone(null, $cid)),
        )->modify("+1 day");
    } catch (Throwable $e) {
        $dt = app_now_in_timezone($cid)->modify("+1 day")->setTime(8, 0);
    }
    return $dt->format("Y-m-d\TH:i");
}
function financial_schedule_drawer_unlock(
    int $cid,
    int $drawerId,
    int $adminUid,
    string $unlockLocal,
    string $notes = "",
): void {

    $d = financial_drawer_row($cid, $drawerId);
    if (!$d) {
        throw new RuntimeException("Gaveta inválida.");
    }
    if ((string) ($d["drawer_lock_status"] ?? "unlocked") !== "locked") {
        throw new RuntimeException(
            "Esta Gaveta não está trancada para conferência.",
        );
    }
    $lockedDay = (string) ($d["drawer_locked_business_date"] ?? "");
    $unlockLocal = trim($unlockLocal);
    if ($unlockLocal === "") {
        throw new RuntimeException(
            "Informe o horário em que a Gaveta será destrancada.",
        );
    }
    try {
        $tz = new DateTimeZone(app_context_timezone(null, $cid));
        $localDt = new DateTimeImmutable($unlockLocal, $tz);
        if ($lockedDay !== "" && $localDt->format("Y-m-d") <= $lockedDay) {
            throw new RuntimeException(
                "O destravamento deve ser agendado para o dia seguinte ao fechamento, no mínimo.",
            );
        }
        $unlockUtc = $localDt
            ->setTimezone(new DateTimeZone("UTC"))
            ->format("Y-m-d H:i:s");
    } catch (RuntimeException $e) {
        throw $e;
    } catch (Throwable $e) {
        throw new RuntimeException(
            "Informe um horário de destravamento válido.",
        );
    }
    q(
        "UPDATE pi_financial_locations SET drawer_unlock_at=?, drawer_reviewed_by=?, drawer_reviewed_at=NOW(), drawer_review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND location_type='pos'",
        [$unlockUtc, $adminUid, trim($notes) ?: null, $drawerId, $cid],
    );
    audit("gaveta_destravamento_agendado", "financeiro", $drawerId, [
        "unlock_at" => $unlockUtc,
        "locked_business_date" => $lockedDay,
        "observacao" => trim($notes),
        "audit_body" => "Gerência conferiu a Gaveta e agendou o destravamento.",
    ]);
}
function financial_drawer_open_session(
    int $cid,
    int $locationId,
    int $excludeUid = 0,
): ?array {

    if ($cid <= 0 || $locationId <= 0) {
        return null;
    }
    $params = [$cid, $locationId];
    $sql =
        "SELECT s.*,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id=? AND s.status='open'";
    if ($excludeUid > 0) {
        $sql .= " AND s.user_id<>?";
        $params[] = $excludeUid;
    }
    $sql .= " ORDER BY s.opened_at DESC,s.id DESC LIMIT 1";
    return one($sql, $params);
}
function financial_latest_drawer_session(
    int $cid,
    int $locationId,
    string $maxDate = "",
    int $ignoreSessionId = 0,
): ?array {

    if ($cid <= 0 || $locationId <= 0) {
        return null;
    }
    $params = [$cid, $locationId];
    $sql =
        "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND location_id=? AND status IN ('closed_pending_review','approved','kept_closed')";
    if ($maxDate !== "") {
        $sql .= " AND business_date<=?";
        $params[] = $maxDate;
    }
    if ($ignoreSessionId > 0) {
        $sql .= " AND id<>?";
        $params[] = $ignoreSessionId;
    }
    $sql .=
        " ORDER BY business_date DESC, COALESCE(closed_at,kept_closed_at,created_at) DESC, id DESC LIMIT 1";
    return one($sql, $params);
}
function financial_drawer_previous_balance(
    int $cid,
    int $locationId,
    string $businessDate = "",
    int $ignoreSessionId = 0,
): int {

    $businessDate = $businessDate ?: financial_today($cid);
    $last = financial_latest_drawer_session(
        $cid,
        $locationId,
        $businessDate,
        $ignoreSessionId,
    );
    if (!$last) {
        return 0;
    }
    return (int) ($last["keep_in_drawer_cents"] ?? 0);
}
function financial_drawer_pending_previous_review(
    int $cid,
    int $locationId,
    string $today = "",
): ?array {

    $today = $today ?: financial_today($cid);
    if ($cid <= 0 || $locationId <= 0) {
        return null;
    }
    return one(
        "SELECT s.*,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id=? AND s.business_date<? AND s.status IN ('closed_pending_review','rejected') ORDER BY s.business_date DESC,s.id DESC LIMIT 1",
        [$cid, $locationId, $today],
    );
}
function financial_drawer_balance(int $cid, int $locationId): int
{

    $open = financial_drawer_open_session($cid, $locationId, 0);
    if ($open) {
        return financial_session_expected($open);
    }
    $last = financial_latest_drawer_session($cid, $locationId, "", 0);
    return $last ? (int) ($last["keep_in_drawer_cents"] ?? 0) : 0;
}
function financial_drawer_balance_snapshot(
    int $cid,
    array $locationIds,
): array {

    static $requestCache = [];
    $locationIds = array_values(
        array_unique(
            array_filter(
                array_map("intval", $locationIds),
                static  fn(int $id): bool => $id > 0,
            ),
        ),
    );
    $balances = array_fill_keys($locationIds, 0);
    $openByLocation = [];
    if ($cid <= 0 || !$locationIds) {
        return ["balances" => $balances, "open" => $openByLocation];
    }
    sort($locationIds, SORT_NUMERIC);
    $requestKey = $cid . ":" . implode(",", $locationIds);
    if (isset($requestCache[$requestKey])) {
        return $requestCache[$requestKey];
    }
    $locationPh = implode(",", array_fill(0, count($locationIds), "?"));
    $openRows = q(
        "SELECT s.id,s.location_id,s.opening_balance_cents,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.location_id IN ($locationPh) AND s.status='open' ORDER BY s.location_id,s.opened_at DESC,s.id DESC",
        array_merge([$cid], $locationIds),
    )->fetchAll();
    $sessionToLocation = [];
    foreach ($openRows as $open) {
        $locationId = (int) ($open["location_id"] ?? 0);
        if ($locationId <= 0 || isset($openByLocation[$locationId])) {
            continue;
        }
        $openByLocation[$locationId] = $open;
        $sessionToLocation[(int) $open["id"]] = $locationId;
        $balances[$locationId] = (int) $open["opening_balance_cents"];
    }
    if ($sessionToLocation) {
        $sessionIds = array_keys($sessionToLocation);
        $sessionPh = implode(",", array_fill(0, count($sessionIds), "?"));
        $movementRows = q(
            "SELECT cash_session_id,from_location_id,to_location_id,COALESCE(SUM(amount_cents),0) total FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id IN ($sessionPh) AND status IN ('confirmed','pending_review') GROUP BY cash_session_id,from_location_id,to_location_id",
            array_merge([$cid], $sessionIds),
        )->fetchAll();
        foreach ($movementRows as $movement) {
            $locationId =
                $sessionToLocation[(int) ($movement["cash_session_id"] ?? 0)] ??
                0;
            if ($locationId <= 0) {
                continue;
            }
            $amount = (int) ($movement["total"] ?? 0);
            $balances[$locationId] = financial_checked_add(
                (int) $balances[$locationId],
                financial_movement_delta_for_location(
                    $amount,
                    (int) ($movement["from_location_id"] ?? 0) ?: null,
                    (int) ($movement["to_location_id"] ?? 0) ?: null,
                    $locationId,
                ),
                "Saldo da Gaveta",
            );
        }
    }
    $closedLocationIds = array_values(
        array_diff($locationIds, array_keys($openByLocation)),
    );
    if ($closedLocationIds) {
        $closedPh = implode(",", array_fill(0, count($closedLocationIds), "?"));
        $closedRows = q(
            "SELECT location_id,keep_in_drawer_cents FROM (SELECT location_id,keep_in_drawer_cents,ROW_NUMBER() OVER (PARTITION BY location_id ORDER BY business_date DESC,COALESCE(closed_at,kept_closed_at,created_at) DESC,id DESC) row_rank FROM pi_cash_sessions WHERE clinic_id=? AND location_id IN ($closedPh) AND status IN ('closed_pending_review','approved','kept_closed')) ranked WHERE row_rank=1",
            array_merge([$cid], $closedLocationIds),
        )->fetchAll();
        foreach ($closedRows as $closed) {
            $balances[(int) $closed["location_id"]] =
                (int) $closed["keep_in_drawer_cents"];
        }
    }
    return $requestCache[$requestKey] = [
        "balances" => $balances,
        "open" => $openByLocation,
    ];
}
function financial_drawer_daily_totals(
    int $cid,
    int $locationId,
    string $date,
): array {

    $map = financial_drawer_daily_totals_map($cid, [$locationId], [$date]);
    return $map[$locationId . "|" . $date] ??
        financial_drawer_daily_totals_empty();
}
function financial_drawer_daily_totals_empty(): array
{

    return [
        "sessions" => 0,
        "opening" => 0,
        "receipts" => 0,
        "payments" => 0,
        "withdrawn" => 0,
        "transfers" => 0,
        "declared" => 0,
        "kept" => 0,
        "open_count" => 0,
        "pending_count" => 0,
    ];
}
function financial_drawer_daily_totals_map(
    int $cid,
    array $locationIds,
    array $dates,
): array {

    $locationIds = array_values(
        array_unique(
            array_filter(
                array_map("intval", $locationIds),
                static  fn(int $id): bool => $id > 0,
            ),
        ),
    );
    $dates = array_values(
        array_unique(
            array_filter(
                array_map("strval", $dates),
                static  fn(string $date): bool =>
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1,
            ),
        ),
    );
    if ($cid <= 0 || !$locationIds || !$dates) {
        return [];
    }
    $out = [];
    foreach ($locationIds as $locationId) {
        foreach ($dates as $date) {
            $out[$locationId . "|" . $date] =
                financial_drawer_daily_totals_empty();
        }
    }
    $locationPh = implode(",", array_fill(0, count($locationIds), "?"));
    $datePh = implode(",", array_fill(0, count($dates), "?"));
    $storageDates = array_map(
        static  fn(string $date): int =>
            \Prontoo\Core\Temporal\PiTime::dateOnlyToTimestamp($date),
        $dates,
    );
    $params = array_merge([$cid], $locationIds, $storageDates);
    $sessions = q(
        "SELECT id,location_id,business_date,opening_balance_cents,keep_in_drawer_cents,transfer_to_safe_cents,declared_closing_cents,status FROM pi_cash_sessions WHERE clinic_id=? AND location_id IN ($locationPh) AND business_date IN ($datePh) ORDER BY location_id,business_date,id ASC",
        $params,
    )->fetchAll();
    foreach ($sessions as $session) {
        $key =
            (int) ($session["location_id"] ?? 0) .
            "|" .
            app_date_input_from_storage($session["business_date"] ?? "");
        if (!isset($out[$key])) {
            continue;
        }
        if ((int) $out[$key]["sessions"] === 0) {
            $out[$key]["opening"] = (int) $session["opening_balance_cents"];
        }
        $out[$key]["sessions"]++;
        $out[$key]["kept"] = (int) $session["keep_in_drawer_cents"];
        $out[$key]["withdrawn"] +=
            (int) $session["transfer_to_safe_cents"];
        $out[$key]["declared"] +=
            (int) $session["declared_closing_cents"];
        if ((string) $session["status"] === "open") {
            $out[$key]["open_count"]++;
        }
        if ((string) $session["status"] === "closed_pending_review") {
            $out[$key]["pending_count"]++;
        }
    }
    $movements = q(
        "SELECT s.location_id,s.business_date,m.movement_type,COALESCE(SUM(m.amount_cents),0) total FROM pi_financial_movements m JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND s.location_id IN ($locationPh) AND s.business_date IN ($datePh) AND m.status IN ('confirmed','pending_review') GROUP BY s.location_id,s.business_date,m.movement_type",
        $params,
    )->fetchAll();
    $movementKeys = [
        "receipt" => "receipts",
        "payment" => "payments",
        "transfer" => "transfers",
    ];
    foreach ($movements as $movement) {
        $key =
            (int) ($movement["location_id"] ?? 0) .
            "|" .
            app_date_input_from_storage($movement["business_date"] ?? "");
        $metric = $movementKeys[(string) ($movement["movement_type"] ?? "")] ??
            null;
        if ($metric !== null && isset($out[$key])) {
            $out[$key][$metric] = (int) $movement["total"];
        }
    }
    return $out;
}
function financial_location_belongs(int $cid, int $locationId): bool
{

    return $locationId > 0 &&
        (int) (val(
            "SELECT id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
            [$locationId, $cid],
        ) ?:
            0) > 0;
}
function financial_admin_location_belongs(int $cid, int $locationId): bool
{

    if ($locationId <= 0) {
        return false;
    }
    return (int) (val(
        "SELECT id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') LIMIT 1",
        [$locationId, $cid],
    ) ?:
        0) > 0;
}
function financial_admin_location_select_options(int $cid): array
{

    financial_operational_schema_ready();
    $rows = q(
        "SELECT id,name,location_type FROM pi_financial_locations WHERE clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') ORDER BY FIELD(location_type,'admin_safe','bank_account'), name,id",
        [$cid],
    )->fetchAll();
    $out = ["" => "Selecione"];
    foreach ($rows as $r) {
        $label =
            (string) ($r["location_type"] ?? "") === "bank_account"
                ? "Banco"
                : "Cofre";
        $out[(int) $r["id"]] = $label . " · " . (string) $r["name"];
    }
    return $out;
}
function financial_daily_closing_ensure_schema(): void
{

    static $validated = false;
    if ($validated) {
        return;
    }
    if (!db_table_exists("pi_financial_daily_closings")) {
        throw new RuntimeException(
            "Schema incompleto: consolidação financeira diária indisponível.",
        );
    }
    $validated = true;
}

function financial_day_is_consolidated(
    int $cid,
    string $businessDate = "",
): bool {

    try {
        financial_daily_closing_ensure_schema();
        $businessDate = $businessDate ?: financial_today($cid);
        return (int) (val(
            "SELECT id FROM pi_financial_daily_closings WHERE clinic_id=? AND business_date=? AND status='consolidado' LIMIT 1",
            [$cid, $businessDate],
        ) ?:
            0) > 0;
    } catch (Throwable $e) {
        error_log("[Prontoo daily closing check] " . $e->getMessage());
        throw $e;
    }
}
function financial_daily_metrics(int $cid, string $businessDate): array
{

    [$dayStart, $dayEnd] = app_local_day_utc_range($businessDate, $cid);
    $activeAppointment =
        "(a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu','reagendado'))";
    $expected = (int) (val(
        "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status IN ('prevista','efetivada') AND r.amount_cents>0 AND r.expected_at>=? AND r.expected_at<? AND " .
            $activeAppointment,
        [$cid, $dayStart, $dayEnd],
    ) ?? 0);
    $received = (int) (val(
        "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='efetivada' AND r.amount_cents>0 AND r.expected_at>=? AND r.expected_at<? AND " .
            $activeAppointment,
        [$cid, $dayStart, $dayEnd],
    ) ?? 0);
    $pending = (int) (val(
        "SELECT COALESCE(SUM(r.amount_cents),0) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.expected_at>=? AND r.expected_at<? AND " .
            $activeAppointment,
        [$cid, $dayStart, $dayEnd],
    ) ?? 0);
    $movements = (int) (val(
        "SELECT COUNT(DISTINCT m.id) FROM pi_financial_movements m LEFT JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND m.status='confirmed' AND ((m.created_at>=? AND m.created_at<?) OR s.business_date=?)",
        [$cid, $dayStart, $dayEnd, $businessDate],
    ) ?? 0);
    $expected = financial_assert_balance_cents(
        $expected,
        "Receita prevista diária",
    );
    $received = financial_assert_balance_cents(
        $received,
        "Receita recebida diária",
    );
    $pending = financial_assert_balance_cents(
        $pending,
        "Receita pendente diária",
    );
    if (
        $expected !==
        financial_checked_add(
            $received,
            $pending,
            "Partição da receita diária",
        )
    ) {
        throw new RuntimeException(
            "A receita diária não fecha entre valores recebidos e pendentes.",
        );
    }
    return [
        "expected_cents" => $expected,
        "received_cents" => $received,
        "pending_cents" => $pending,
        "movement_count" => max(0, $movements),
        "day_start" => $dayStart,
        "day_end" => $dayEnd,
    ];
}
function financial_daily_reconciliation(
    int $cid,
    string $businessDate,
    int $uid = 0,
    bool $prepareLegacy = false,
): array {

    $issues = [];
    $sessions = q(
        "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND status IN ('approved','kept_closed') ORDER BY id",
        [$cid, $businessDate],
    )->fetchAll();
    foreach ($sessions as $session) {
        try {
            if ($prepareLegacy && (string) $session["status"] === "approved") {
                financial_ensure_closing_adjustment(
                    $session,
                    $uid,
                    "confirmed",
                );
            }
            financial_assert_session_reconciled($session);
        } catch (Throwable $error) {
            $issues[] =
                "Sessão #" .
                (int) ($session["id"] ?? 0) .
                ": " .
                $error->getMessage();
        }
    }
    [$dayStart, $dayEnd] = app_local_day_utc_range($businessDate, $cid);
    $movements = q(
        "SELECT DISTINCT m.id,m.movement_type,m.status,m.amount_cents,m.from_location_id,m.to_location_id,m.cash_session_id,m.source_entity,m.source_id FROM pi_financial_movements m LEFT JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND m.status='confirmed' AND ((m.created_at>=? AND m.created_at<?) OR s.business_date=?) ORDER BY m.id",
        [$cid, $dayStart, $dayEnd, $businessDate],
    )->fetchAll();
    foreach ($movements as $movement) {
        try {
            $type = (string) ($movement["movement_type"] ?? "");
            $from = (int) ($movement["from_location_id"] ?? 0) ?: null;
            $to = (int) ($movement["to_location_id"] ?? 0) ?: null;
            financial_assert_amount_cents(
                (int) ($movement["amount_cents"] ?? 0),
            );
            financial_validate_movement_topology($type, $from, $to);
            $sessionId = (int) ($movement["cash_session_id"] ?? 0);
            if ($sessionId > 0) {
                $session = one(
                    "SELECT location_id FROM pi_cash_sessions WHERE id=? AND clinic_id=? LIMIT 1",
                    [$sessionId, $cid],
                );
                if (
                    !$session ||
                    financial_movement_delta_for_location(
                        (int) $movement["amount_cents"],
                        $from,
                        $to,
                        (int) $session["location_id"],
                    ) === 0
                ) {
                    throw new RuntimeException(
                        "extremos incompatíveis com a sessão",
                    );
                }
            }
        } catch (Throwable $error) {
            $issues[] =
                "Movimento #" .
                (int) ($movement["id"] ?? 0) .
                ": " .
                $error->getMessage();
        }
    }
    $metrics = financial_daily_metrics($cid, $businessDate);
    $position = financial_global_position($cid);
    foreach (
        [
            "drawer_cents" => (int) ($position["pos_cents"] ?? 0),
            "safe_cents" => (int) ($position["safe_cents"] ?? 0),
            "bank_cents" => (int) ($position["bank_cents"] ?? 0),
            "total_cents" => (int) ($position["total_cents"] ?? 0),
        ] as $label => $value
    ) {
        try {
            financial_assert_balance_cents($value, $label);
        } catch (Throwable $error) {
            $issues[] = $error->getMessage();
        }
    }
    $payload = [
        "policy" => "financial-reconciliation-v2",
        "clinic_id" => $cid,
        "business_date" => $businessDate,
        "metrics" => $metrics,
        "position" => [
            "drawer_cents" => (int) ($position["pos_cents"] ?? 0),
            "safe_cents" => (int) ($position["safe_cents"] ?? 0),
            "bank_cents" => (int) ($position["bank_cents"] ?? 0),
            "total_cents" => (int) ($position["total_cents"] ?? 0),
        ],
        "sessions" => $sessions,
        "movements" => $movements,
    ];
    $canonical = class_exists("\\Prontoo\\Core\\Integrity\\PiIntegrity")
        ? \Prontoo\Core\Integrity\PiIntegrity::canonicalJson($payload)
        : (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: "{}");
    return [
        "ok" => $issues === [],
        "issues" => $issues,
        "hash" => hash("sha256", $canonical),
        "metrics" => $metrics,
        "position" => $position,
        "session_count" => count($sessions),
        "movement_count" => count($movements),
    ];
}
function financial_daily_consolidation_state(
    int $cid,
    string $businessDate = "",
): array {

    financial_operational_schema_ready();
    financial_daily_closing_ensure_schema();
    $businessDate = $businessDate ?: financial_today($cid);
    [$dayStart, $dayEnd] = app_local_day_utc_range($businessDate, $cid);
    $closure = financial_daily_drawer_closure_state($cid, $businessDate);
    $pendingOpen = (int) ($closure["blocking_count"] ?? 0);
    $pendingReview = (int) (val(
        "SELECT COUNT(*) FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND status IN ('closed_pending_review','rejected','opening_pending_review','opening_rejected')",
        [$cid, $businessDate],
    ) ?? 0);
    $pendingMovements = (int) (val(
        "SELECT COUNT(DISTINCT m.id) FROM pi_financial_movements m LEFT JOIN pi_cash_sessions s ON s.id=m.cash_session_id AND s.clinic_id=m.clinic_id WHERE m.clinic_id=? AND m.status IN ('pending_review','rejected') AND ((m.created_at>=? AND m.created_at<?) OR s.business_date=?)",
        [$cid, $dayStart, $dayEnd, $businessDate],
    ) ?? 0);
    $consolidated = financial_day_is_consolidated($cid, $businessDate);
    $reconciliation = [
        "ok" => false,
        "issues" => [],
        "hash" => "",
    ];
    if (
        $pendingOpen === 0 &&
        $pendingReview === 0 &&
        $pendingMovements === 0 &&
        !$consolidated
    ) {
        $reconciliation = financial_daily_reconciliation(
            $cid,
            $businessDate,
        );
    }
    $blockers = [];
    if ($pendingOpen > 0) {
        $blockers[] =
            $pendingOpen .
            " colaborador(es) vinculado(s) ainda sem fechamento de gaveta";
    }
    if ($pendingReview > 0) {
        $blockers[] =
            $pendingReview .
            " conferência(s) de gaveta pendente(s) ou devolvida(s)";
    }
    if ($pendingMovements > 0) {
        $blockers[] = $pendingMovements . " movimento(s) ainda em revisão";
    }
    if ($consolidated) {
        $blockers[] = "dia financeiro já consolidado";
    }
    if (
        !$consolidated &&
        $pendingOpen === 0 &&
        $pendingReview === 0 &&
        $pendingMovements === 0 &&
        empty($reconciliation["ok"])
    ) {
        $blockers[] =
            "a reconciliação matemática encontrou " .
            max(1, count((array) ($reconciliation["issues"] ?? []))) .
            " inconsistência(s)";
    }
    return [
        "business_date" => $businessDate,
        "closure" => $closure,
        "pending_open_drawers" => $pendingOpen,
        "pending_reviews" => $pendingReview,
        "pending_movements" => $pendingMovements,
        "consolidated" => $consolidated,
        "reconciliation" => $reconciliation,
        "blockers" => $blockers,
        "conference_released" => $pendingOpen === 0,
        "can_consolidate" =>
            $pendingOpen === 0 &&
            $pendingReview === 0 &&
            $pendingMovements === 0 &&
            !empty($reconciliation["ok"]) &&
            !$consolidated,
    ];
}
function financial_daily_consolidation_blockers_html(array $state): string
{

    $blockers = $state["blockers"] ?? [];
    if (!$blockers) {
        return "";
    }
    $h = '<div class="finance-conference-blocking-list">';
    foreach ($blockers as $b) {
        $h .=
            "<div><b>" .
            icon("lock") .
            "<span>Pendente</span></b><span>" .
            e((string) $b) .
            "</span></div>";
    }
    return $h . "</div>";
}
function financial_patient_pending_revenue_count(int $cid, int $patientId): int
{

    if ($patientId <= 0) {
        return 0;
    }
    return (int) safe_val(
        "SELECT COUNT(*) FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.patient_link_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.appointment_id IS NOT NULL AND a.status NOT IN ('cancelado','nao_compareceu','reagendado')",
        [$cid, $patientId],
        0,
    );
}
function financial_pending_revenue_belongs_to_patient(
    int $cid,
    int $revenueId,
    int $patientId,
): bool {

    if ($revenueId <= 0 || $patientId <= 0) {
        return false;
    }
    return (int) (val(
        "SELECT r.id FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.id=? AND r.clinic_id=? AND r.patient_link_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.appointment_id IS NOT NULL AND a.status NOT IN ('cancelado','nao_compareceu','reagendado') LIMIT 1",
        [$revenueId, $cid, $patientId],
    ) ?:
        0) > 0;
}
function financial_cancel_appointment_revenue(
    int $cid,
    int $appointmentId,
    int $uid,
    string $reason = "",
): void {

    if ($cid <= 0 || $appointmentId <= 0) {
        return;
    }
    financial_operational_schema_ready();
    $reason =
        trim($reason) ?:
        "Atendimento cancelado ou ausência registrada; cobrança prevista cancelada pela regra operacional.";
    try {
        db_tx(function () use ($cid, $appointmentId, $uid, $reason): void {

            $rev = one(
                "SELECT id,status FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=? FOR UPDATE",
                [$cid, $appointmentId],
            );
            if (!$rev || (string) ($rev["status"] ?? "") !== "prevista") {
                return;
            }
            q(
                "UPDATE pi_financial_revenues SET status='cancelada', updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND status='prevista'",
                [$uid, (int) $rev["id"], $cid],
            );
            q(
                "UPDATE pi_financial_movements SET status='cancelled', confirmed_by=NULL, confirmed_at=NULL, reviewed_at=NOW(), notes=CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='', '', ' | '), ?) WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' AND status<>'confirmed'",
                [$reason, $cid, $appointmentId],
            );
            audit(
                "receita_prevista_cancelada",
                "financeiro",
                (int) $rev["id"],
                [
                    "appointment_id" => $appointmentId,
                    "motivo" => $reason,
                    "audit_body" =>
                        "Receita prevista do agendamento foi cancelada por cancelamento, ausência ou remarcação sem cobrança.",
                ],
            );
        });
    } catch (Throwable $e) {
        error_log("[Prontoo cancel appointment revenue] " . $e->getMessage());
    }
}
function financial_admin_receive_expected_revenue(
    int $cid,
    int $uid,
    int $revenueId,
    string $method,
    int $destinationLocationId,
    string $notes = "",
): int {

    financial_operational_schema_ready();
    $method = normalize_payment_method($method);
    if ($method === "") {
        throw new RuntimeException("Informe a forma de recebimento.");
    }
    if (!financial_admin_location_belongs($cid, $destinationLocationId)) {
        throw new RuntimeException(
            "Recebimento pelo Administrador deve entrar em Cofre ou Banco do Consultório. Gaveta pertence ao fluxo da Recepção.",
        );
    }
    return (int) db_tx(function () use (
        $cid,
        $uid,
        $revenueId,
        $method,
        $destinationLocationId,
        $notes,
    ): int {

        $rev = one(
            "SELECT r.*,a.status appointment_status,a.start_at,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.id=? AND r.clinic_id=? AND r.status='prevista' AND r.appointment_id IS NOT NULL AND r.amount_cents>0 AND a.status NOT IN ('cancelado','nao_compareceu','reagendado') FOR UPDATE",
            [$revenueId, $cid],
        );
        if (!$rev) {
            throw new RuntimeException(
                "Selecione uma pendência de agendamento ainda não recebida.",
            );
        }
        $amount = (int) $rev["amount_cents"];
        $appointmentId = (int) $rev["appointment_id"];
        $dest = one(
            "SELECT id,account_id,location_type,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') LIMIT 1",
            [$destinationLocationId, $cid],
        );
        if (!$dest) {
            throw new RuntimeException("Destino financeiro inválido.");
        }
        $accountId =
            (string) $dest["location_type"] === "bank_account"
                ? ((int) ($dest["account_id"] ?? 0) ?:
                null)
                : null;
        $title =
            "Recebimento · " .
            trim(
                (string) ($rev["procedure_title"] ?:
                $rev["title"] ?:
                "Atendimento agendado"),
            );
        $movementNotes =
            "Administrador recebeu pendência de agendamento em Cofre/Banco; não movimenta Gaveta." .
            (trim($notes) !== "" ? " " . trim($notes) : "");
        $existing = one(
            "SELECT id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' ORDER BY id DESC LIMIT 1 FOR UPDATE",
            [$cid, $appointmentId],
        );
        if ($existing) {
            financial_update_existing_movement(
                $cid,
                (int) $existing["id"],
                "receipt",
                $amount,
                null,
                $destinationLocationId,
                null,
                $uid,
                $title,
                $method,
                $movementNotes,
                "confirmed",
            );
            $movementId = (int) $existing["id"];
        } else {
            $movementId = financial_create_movement(
                $cid,
                "receipt",
                $amount,
                null,
                $destinationLocationId,
                null,
                $uid,
                $title,
                $method,
                $movementNotes,
                "confirmed",
                "appointment",
                $appointmentId,
            );
        }
        q(
            "UPDATE pi_financial_revenues SET status='efetivada', payment_method=?, account_id=?, received_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
            [$method, $accountId, $uid, $revenueId, $cid],
        );
        q(
            "UPDATE pi_appointments SET payment_status='efetivada', payment_method=?, payment_amount_cents=?, payment_confirmed_at=NOW(), revenue_id=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
            [$method, $amount, $revenueId, $appointmentId, $cid],
        );
        audit(
            "recebimento_administrativo_pendencia_agendamento",
            "financeiro",
            $revenueId,
            [
                "appointment_id" => $appointmentId,
                "movement_id" => $movementId,
                "valor" => $amount,
                "forma" => $method,
                "destino" => $destinationLocationId,
                "audit_body" =>
                    "Administrador recebeu pendência financeira vinculada ao agendamento, evitando receita avulsa duplicada.",
            ],
        );
        return $movementId;
    });
}
function financial_session_position_cents(
    array $session,
    int $excludeMovementId = 0,
): int {

    $cid = (int) ($session["clinic_id"] ?? 0);
    $sessionId = (int) ($session["id"] ?? 0);
    $locationId = (int) ($session["location_id"] ?? 0);
    $balance = financial_assert_balance_cents(
        (int) ($session["opening_balance_cents"] ?? 0),
        "Saldo inicial da Gaveta",
    );
    $params = [$cid, $sessionId];
    $sql =
        "SELECT id,amount_cents,from_location_id,to_location_id FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND status IN ('confirmed','pending_review')";
    if ($excludeMovementId > 0) {
        $sql .= " AND id<>?";
        $params[] = $excludeMovementId;
    }
    $sql .= " ORDER BY id";
    foreach (q($sql, $params)->fetchAll() as $movement) {
        $balance = financial_checked_add(
            $balance,
            financial_movement_delta_for_location(
                (int) ($movement["amount_cents"] ?? 0),
                (int) ($movement["from_location_id"] ?? 0) ?: null,
                (int) ($movement["to_location_id"] ?? 0) ?: null,
                $locationId,
            ),
            "Saldo potencial da Gaveta",
        );
    }
    return $balance;
}
function financial_location_potential_balance(
    int $cid,
    int $locationId,
    int $excludeMovementId = 0,
): int {

    $params = [$locationId, $cid, $locationId, $cid];
    $excludeTo = "";
    $excludeFrom = "";
    if ($excludeMovementId > 0) {
        $excludeTo = " AND id<>?";
        $excludeFrom = " AND id<>?";
        $params = [
            $locationId,
            $cid,
            $excludeMovementId,
            $locationId,
            $cid,
            $excludeMovementId,
        ];
    }
    $balance = (int) (val(
        "SELECT COALESCE(SUM(delta_cents),0) FROM (" .
            "SELECT amount_cents delta_cents FROM pi_financial_movements WHERE to_location_id=? AND clinic_id=? AND status IN ('confirmed','pending_review')" .
            $excludeTo .
            " UNION ALL SELECT -amount_cents delta_cents FROM pi_financial_movements WHERE from_location_id=? AND clinic_id=? AND status IN ('confirmed','pending_review')" .
            $excludeFrom .
            ") financial_potential",
        $params,
    ) ?:
        0);
    return financial_assert_balance_cents(
        $balance,
        "Saldo potencial do local financeiro",
    );
}
function financial_validate_movement_invariants(
    int $cid,
    string $type,
    int $amount,
    ?int $from,
    ?int $to,
    ?int $sessionId,
    string $status,
    int $excludeMovementId = 0,
): void {

    financial_assert_amount_cents($amount);
    if ($cid <= 0 || $amount <= 0) {
        throw new RuntimeException("Informe um valor financeiro válido.");
    }
    q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
    $allowedTypes = [
        "receipt",
        "payment",
        "transfer",
        "deposit",
        "cash_opening",
        "cash_closing",
        "adjustment",
        "refund",
    ];
    if (!in_array($type, $allowedTypes, true)) {
        throw new RuntimeException("Natureza de movimento financeiro inválida.");
    }
    if (
        !in_array(
            $status,
            ["confirmed", "pending_review", "cancelled", "rejected"],
            true,
        )
    ) {
        throw new RuntimeException("Estado de movimento financeiro inválido.");
    }
    $from = ($from ?? 0) > 0 ? $from : null;
    $to = ($to ?? 0) > 0 ? $to : null;
    financial_validate_movement_topology($type, $from, $to);
    $locationIds = array_values(
        array_unique(
            array_filter(
                [$from, $to],
                static  fn(?int $id): bool =>
                    $id !== null,
            ),
        ),
    );
    sort($locationIds, SORT_NUMERIC);
    if ($locationIds) {
        $placeholders = implode(",", array_fill(0, count($locationIds), "?"));
        $locked = q(
            "SELECT id FROM pi_financial_locations WHERE clinic_id=? AND id IN ($placeholders) AND active=1 ORDER BY id FOR UPDATE",
            array_merge([$cid], $locationIds),
        )->fetchAll();
        if (count($locked) !== count($locationIds)) {
            throw new RuntimeException("Origem ou destino financeiro inválido.");
        }
    }
    $session = null;
    $businessDate = financial_today($cid);
    if (($sessionId ?? 0) > 0) {
        $session = one(
            "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? FOR UPDATE",
            [$sessionId, $cid],
        );
        if (!$session) {
            throw new RuntimeException(
                "Sessão de gaveta inválida para o lançamento.",
            );
        }
        $businessDate = app_date_input_from_storage(
            $session["business_date"] ?? "",
        ) ?: $businessDate;
        $sessionLocation = (int) ($session["location_id"] ?? 0);
        if (
            financial_movement_delta_for_location(
                $amount,
                $from,
                $to,
                $sessionLocation,
            ) === 0
        ) {
            throw new RuntimeException(
                "O movimento da sessão deve partir ou chegar à Gaveta vinculada.",
            );
        }
        if (
            (string) ($session["status"] ?? "") === "approved" &&
            $status === "pending_review"
        ) {
            throw new RuntimeException(
                "A sessão de gaveta já foi conferida. Novo lançamento exige ajuste próprio.",
            );
        }
    }
    if (financial_day_is_consolidated($cid, $businessDate)) {
        throw new RuntimeException(
            "O dia financeiro já foi consolidado e seus movimentos são imutáveis.",
        );
    }
    if (in_array($status, ["confirmed", "pending_review"], true)) {
        foreach ($locationIds as $locationId) {
            financial_checked_add(
                financial_location_potential_balance(
                    $cid,
                    $locationId,
                    $excludeMovementId,
                ),
                financial_movement_delta_for_location(
                    $amount,
                    $from,
                    $to,
                    $locationId,
                ),
                "Saldo potencial do local financeiro",
            );
        }
        if (is_array($session)) {
            financial_checked_add(
                financial_session_position_cents(
                    $session,
                    $excludeMovementId,
                ),
                financial_movement_delta_for_location(
                    $amount,
                    $from,
                    $to,
                    (int) $session["location_id"],
                ),
                "Saldo potencial da sessão",
            );
        }
    }
}
function financial_update_existing_movement(
    int $cid,
    int $movementId,
    string $type,
    int $amount,
    ?int $from,
    ?int $to,
    ?int $sessionId,
    int $uid,
    string $title,
    string $paymentMethod,
    string $notes,
    string $status,
): void {

    db_tx(function () use (
        $cid,
        $movementId,
        $type,
        $amount,
        $from,
        $to,
        $sessionId,
        $uid,
        $title,
        $paymentMethod,
        $notes,
        $status,
    ): void {

        $existing = one(
            "SELECT id FROM pi_financial_movements WHERE id=? AND clinic_id=? FOR UPDATE",
            [$movementId, $cid],
        );
        if (!$existing) {
            throw new RuntimeException("Movimento financeiro não encontrado.");
        }
        financial_validate_movement_invariants(
            $cid,
            $type,
            $amount,
            $from,
            $to,
            $sessionId,
            $status,
            $movementId,
        );
        q(
            "UPDATE pi_financial_movements SET movement_type=?,status=?,amount_cents=?,payment_method=?,from_location_id=?,to_location_id=?,cash_session_id=?,title=?,notes=?,updated_at=NOW(),confirmed_by=IF(?='confirmed',COALESCE(confirmed_by,?),NULL),confirmed_at=IF(?='confirmed',COALESCE(confirmed_at,NOW()),NULL),reviewed_by=NULL,reviewed_at=NULL WHERE id=? AND clinic_id=?",
            [
                $type,
                $status,
                $amount,
                trim($paymentMethod) ?: null,
                $from ?: null,
                $to ?: null,
                $sessionId ?: null,
                trim($title),
                trim($notes) ?: null,
                $status,
                $uid ?: null,
                $status,
                $movementId,
                $cid,
            ],
        );
    });
}
function financial_create_movement(
    int $cid,
    string $type,
    int $amount,
    ?int $from,
    ?int $to,
    ?int $sessionId,
    int $uid,
    string $title,
    string $paymentMethod = "",
    string $notes = "",
    string $status = "confirmed",
    string $sourceEntity = "",
    int $sourceId = 0,
): int {

    return (int) db_tx(function () use (
        $cid,
        $type,
        $amount,
        $from,
        $to,
        $sessionId,
        $uid,
        $title,
        $paymentMethod,
        $notes,
        $status,
        $sourceEntity,
        $sourceId,
    ): int {

        financial_operational_schema_ready();
        financial_validate_movement_invariants(
            $cid,
            $type,
            $amount,
            $from,
            $to,
            $sessionId,
            $status,
        );
        $title = trim($title) ?: financial_human_movement_type($type);
        $paymentMethod = mb_substr(trim($paymentMethod), 0, 40);
        q(
            "INSERT INTO pi_financial_movements (clinic_id,movement_type,status,amount_cents,payment_method,from_location_id,to_location_id,cash_session_id,source_entity,source_id,title,notes,created_by,created_at,confirmed_by,confirmed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,IF(?='confirmed',NOW(),NULL))",
            [
                $cid,
                $type,
                $status,
                $amount,
                $paymentMethod ?: null,
                $from ?: null,
                $to ?: null,
                $sessionId ?: null,
                $sourceEntity ?: null,
                $sourceId ?: null,
                $title,
                trim($notes) ?: null,
                $uid ?: null,
                $status === "confirmed" ? ($uid ?: null) : null,
                $status,
            ],
        );
        return db_last_insert_id();
    });
}
function financial_session_for_date(int $cid, int $uid, string $date): ?array
{

    financial_operational_schema_ready();
    return one(
        "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date=? LIMIT 1",
        [$cid, $uid, $date],
    );
}
function financial_latest_session(int $cid, int $uid): ?array
{

    financial_operational_schema_ready();
    return one(
        "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? ORDER BY business_date DESC,id DESC LIMIT 1",
        [$cid, $uid],
    );
}
function financial_previous_drawer_balance(
    int $cid,
    int $uid,
    string $beforeDate = "",
): int {

    financial_operational_schema_ready();
    $loc = financial_cashier_location_for_user($cid, $uid);
    if ($loc <= 0) {
        return 0;
    }
    return financial_drawer_previous_balance(
        $cid,
        $loc,
        $beforeDate ?: financial_today($cid),
    );
}
function financial_expected_opening_balance(
    int $cid,
    int $uid,
    string $businessDate = "",
    int $locationId = 0,
    int $ignoreSessionId = 0,
): int {

    $businessDate = $businessDate ?: financial_today($cid);
    $locationId =
        $locationId > 0
            ? $locationId
            : financial_cashier_location_for_user($cid, $uid);
    if ($locationId <= 0) {
        return 0;
    }
    return financial_drawer_previous_balance(
        $cid,
        $locationId,
        $businessDate,
        $ignoreSessionId,
    );
}
function financial_cashier_name(int $uid): string
{

    $name = trim(
        (string) (val("SELECT name FROM pi_users WHERE id=? LIMIT 1", [$uid]) ?:
        "Atendimento"),
    );
    return $name !== "" ? $name : "Atendimento";
}
function financial_notify_opening_authorization_request(
    int $cid,
    int $cashierUid,
    int $sessionId,
    int $expected,
    int $informed,
    int $createdBy,
): void {

    try {
        $cashier = financial_cashier_name($cashierUid);
        $diff = financial_checked_add(
            $informed,
            -$expected,
            "Diferença da abertura",
        );
        $title = "Autorizar abertura de caixa";
        $body =
            "A Recepção/Atendimento informou um Saldo Inicial diferente do saldo não retirado no último fechamento." .
            "\n\nColaborador: " .
            $cashier .
            "\nEsperado: " .
            money_br($expected) .
            "\nSaldo informado: " .
            money_br($informed) .
            "\nDiferença: " .
            money_br($diff) .
            "\n\nAbra Financeiro > Gavetas para autorizar ou recusar esta abertura.";
        q(
            "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                $title,
                $body,
                1,
                "role",
                "gerente",
                null,
                $createdBy ?: null,
            ],
        );
        $noticeId = db_last_insert_id();
        if (function_exists("counter_inc")) {
            counter_inc("notices_total");
        }
        if (function_exists("clinic_metric_inc")) {
            clinic_metric_inc($cid, "notices");
        }
        audit(
            "notificacao_autorizacao_abertura_caixa",
            "comunicado",
            $noticeId,
            [
                "cash_session_id" => $sessionId,
                "usuario_caixa" => $cashierUid,
                "esperado" => $expected,
                "informado" => $informed,
                "diferenca" => $diff,
                "audit_body" =>
                    "Aviso automática criada para o Administrativo autorizar abertura de caixa com saldo divergente.",
            ],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo abertura caixa aviso] " . $e->getMessage());
    }
}
function financial_request_opening_authorization(
    int $cid,
    int $uid,
    int $loc,
    string $today,
    int $expected,
    int $informed,
    ?array $existing = null,
): int {

    $diff = financial_checked_add(
        $informed,
        -$expected,
        "Diferença da abertura",
    );
    $notes =
        "Abertura bloqueada: Saldo Inicial informado diverge do saldo não retirado do último fechamento.";
    if ($existing && (int) ($existing["id"] ?? 0) > 0) {
        $sid = (int) $existing["id"];
        $oldStatus = (string) ($existing["status"] ?? "");
        q(
            "UPDATE pi_cash_sessions SET location_id=?, opened_at=NULL, kept_closed_at=NULL, opening_balance_cents=?, expected_closing_cents=?, declared_closing_cents=?, keep_in_drawer_cents=?, transfer_to_safe_cents=0, difference_cents=?, status='opening_pending_review', closing_notes=?, reviewed_by=NULL, reviewed_at=NULL, review_status=NULL, review_notes=NULL, updated_at=NOW() WHERE id=? AND clinic_id=? AND user_id=?",
            [
                $loc,
                $informed,
                $expected,
                $informed,
                $expected,
                $diff,
                $notes,
                $sid,
                $cid,
                $uid,
            ],
        );
        audit("abertura_caixa_divergente_atualizada", "financeiro", $sid, [
            "esperado" => $expected,
            "informado" => $informed,
            "diferenca" => $diff,
            "status_anterior" => $oldStatus,
            "audit_body" =>
                "Atendimento atualizou solicitação de abertura de caixa com Saldo Inicial divergente.",
        ]);
        if ($oldStatus !== "opening_pending_review") {
            financial_notify_opening_authorization_request(
                $cid,
                $uid,
                $sid,
                $expected,
                $informed,
                $uid,
            );
        }
        return $sid;
    }
    q(
        "INSERT INTO pi_cash_sessions (clinic_id,user_id,location_id,business_date,opened_at,kept_closed_at,opening_balance_cents,expected_closing_cents,declared_closing_cents,keep_in_drawer_cents,transfer_to_safe_cents,difference_cents,status,closing_notes,created_at,updated_at) VALUES (?,?,?,?,NULL,NULL,?,?,?,?,0,?,'opening_pending_review',?,NOW(),NOW())",
        [
            $cid,
            $uid,
            $loc,
            $today,
            $informed,
            $expected,
            $informed,
            $expected,
            $diff,
            $notes,
        ],
    );
    $sid = db_last_insert_id();
    audit("abertura_caixa_divergente_solicitada", "financeiro", $sid, [
        "esperado" => $expected,
        "informado" => $informed,
        "diferenca" => $diff,
        "audit_body" =>
            "Atendimento solicitou abertura de caixa com Saldo Inicial divergente do saldo não retirado anterior.",
    ]);
    financial_notify_opening_authorization_request(
        $cid,
        $uid,
        $sid,
        $expected,
        $informed,
        $uid,
    );
    return $sid;
}
function financial_unclosed_previous_session(
    int $cid,
    int $uid,
    string $today = "",
): ?array {

    $today = $today ?: financial_today($cid);
    financial_operational_schema_ready();
    return one(
        "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date<? AND status='open' ORDER BY business_date DESC,id DESC LIMIT 1",
        [$cid, $uid, $today],
    );
}
function financial_keep_closed(int $cid, int $uid): int
{

    financial_operational_schema_ready();
    if ($cid <= 0 || $uid <= 0) {
        throw new RuntimeException("Usuário ou consultório inválido.");
    }
    $today = financial_today($cid);
    return (int) db_tx(function () use ($cid, $uid, $today) {

        q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
        $loc = financial_cashier_location_for_user($cid, $uid);
        if ($loc <= 0) {
            throw new RuntimeException(
                "Você não é responsável por nenhuma gaveta ainda. Aguarde até que receba autorização para gerenciar gavetas.",
            );
        }
        financial_drawer_guard_can_use($cid, $loc);
        $other = financial_drawer_open_session($cid, $loc, $uid);
        if ($other) {
            throw new RuntimeException(
                "Esta Gaveta já está aberta por " .
                    first_name(
                        (string) ($other["user_name"] ?? "outro colaborador"),
                    ) .
                    ". Aguarde o fechamento antes de abrir ou manter fechado.",
            );
        }
        $pending = financial_drawer_pending_previous_review($cid, $loc, $today);
        if ($pending) {
            throw new RuntimeException(
                "Esta Gaveta possui fechamento anterior aguardando conferência da Gerência. Aguarde o destravamento para usá-la.",
            );
        }
        $prev = financial_unclosed_previous_session($cid, $uid, $today);
        if ($prev) {
            throw new RuntimeException(
                "Há uma Gaveta de caixa anterior sem fechamento. Feche a sessão pendente antes de manter o caixa fechado hoje.",
            );
        }
        $exists = financial_session_for_date($cid, $uid, $today);
        if ($exists) {
            if ((string) $exists["status"] === "kept_closed") {
                return (int) $exists["id"];
            }
            if ((string) $exists["status"] === "open") {
                throw new RuntimeException("O caixa de hoje já está aberto.");
            }
            throw new RuntimeException(
                "O caixa de hoje já foi fechado ou está em conferência.",
            );
        }
        $balance = financial_drawer_previous_balance($cid, $loc, $today);
        q(
            "INSERT INTO pi_cash_sessions (clinic_id,user_id,location_id,business_date,opened_at,kept_closed_at,opening_balance_cents,closed_at,expected_closing_cents,declared_closing_cents,keep_in_drawer_cents,transfer_to_safe_cents,difference_cents,status,closing_notes,created_at,updated_at) VALUES (?,?,?,?,NULL,NOW(),?,NULL,?,?,?,0,0,'kept_closed',?,NOW(),NOW())",
            [
                $cid,
                $uid,
                $loc,
                $today,
                $balance,
                $balance,
                $balance,
                $balance,
                "Gaveta mantida fechada pelo Atendimento; saldo físico preservado na Gaveta.",
            ],
        );
        $sid = db_last_insert_id();
        audit("caixa_atendimento_mantido_fechado", "financeiro", $sid, [
            "gaveta" => $loc,
            "audit_body" =>
                "Atendimento optou por manter a Gaveta fechada no dia, preservando o saldo físico.",
        ]);
        return $sid;
    });
}
function financial_open_session(int $cid, int $uid, int $openingBalance): int
{

    financial_operational_schema_ready();
    if ($cid <= 0 || $uid <= 0) {
        throw new RuntimeException("Usuário ou consultório inválido.");
    }
    $today = financial_today($cid);
    $authorizationRequested = false;
    $authorizationMessage = "";
    $result = (int) db_tx(function () use (
        $cid,
        $uid,
        $openingBalance,
        $today,
        &$authorizationRequested,
        &$authorizationMessage,
    ) {

        q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
        $loc = financial_cashier_location_for_user($cid, $uid);
        if ($loc <= 0) {
            throw new RuntimeException(
                "Você não é responsável por nenhuma gaveta ainda. Aguarde até que receba autorização para gerenciar gavetas.",
            );
        }
        financial_drawer_guard_can_use($cid, $loc);
        $other = financial_drawer_open_session($cid, $loc, $uid);
        if ($other) {
            throw new RuntimeException(
                "Esta Gaveta já está aberta por " .
                    first_name(
                        (string) ($other["user_name"] ?? "outro colaborador"),
                    ) .
                    ". Aguarde o fechamento antes de abrir a Gaveta.",
            );
        }
        $pending = financial_drawer_pending_previous_review($cid, $loc, $today);
        if ($pending) {
            throw new RuntimeException(
                "Esta Gaveta possui fechamento anterior aguardando conferência da Gerência. Aguarde o destravamento para usá-la.",
            );
        }
        $prev = financial_unclosed_previous_session($cid, $uid, $today);
        if ($prev) {
            throw new RuntimeException(
                "Há uma Gaveta de caixa anterior sem fechamento. Feche a sessão pendente antes de abrir uma nova.",
            );
        }
        $exists = financial_session_for_date($cid, $uid, $today);
        $status = $exists ? (string) $exists["status"] : "";
        if ($exists && $status === "open") {
            throw new RuntimeException(
                "A sessão de caixa de hoje já foi aberta.",
            );
        }
        if (
            $exists &&
            in_array(
                $status,
                ["closed_pending_review", "approved", "rejected"],
                true,
            )
        ) {
            throw new RuntimeException(
                "O caixa de hoje já foi fechado ou está em conferência.",
            );
        }
        $openingBalance = financial_assert_amount_cents(
            max(0, $openingBalance),
            "Saldo inicial",
        );
        $expected = financial_expected_opening_balance(
            $cid,
            $uid,
            $today,
            $loc,
            $exists ? (int) $exists["id"] : 0,
        );
        if ($exists && $status === "kept_closed") {
            $expected = (int) ($exists["opening_balance_cents"] ?? $expected);
        }
        if ($openingBalance !== $expected) {
            $sid = financial_request_opening_authorization(
                $cid,
                $uid,
                $loc,
                $today,
                $expected,
                $openingBalance,
                $exists ?: null,
            );
            $authorizationRequested = true;
            $authorizationMessage =
                "O Saldo Inicial informado não coincide com o valor esperado para esta Gaveta. O Administrativo recebeu aviso para autorizar a abertura com saldo diferente.";
            return $sid;
        }
        if ($exists) {
            if (
                in_array(
                    $status,
                    [
                        "kept_closed",
                        "opening_pending_review",
                        "opening_rejected",
                    ],
                    true,
                )
            ) {
                q(
                    "UPDATE pi_cash_sessions SET location_id=?, opened_at=NOW(), kept_closed_at=NULL, opening_balance_cents=?, closed_at=NULL, expected_closing_cents=0, declared_closing_cents=0, keep_in_drawer_cents=0, transfer_to_safe_cents=0, difference_cents=0, status='open', closing_notes=NULL, reviewed_by=NULL, reviewed_at=NULL, review_status=NULL, review_notes=NULL, updated_at=NOW() WHERE id=? AND clinic_id=? AND user_id=?",
                    [$loc, $openingBalance, (int) $exists["id"], $cid, $uid],
                );
                audit(
                    "caixa_atendimento_aberto",
                    "financeiro",
                    (int) $exists["id"],
                    [
                        "gaveta" => $loc,
                        "saldo_inicial" => $openingBalance,
                        "audit_body" =>
                            "Gaveta aberta pelo Atendimento com valor inicial coincidente.",
                    ],
                );
                return (int) $exists["id"];
            }
            throw new RuntimeException(
                "A situação atual do caixa não permite abertura.",
            );
        }
        q(
            "INSERT INTO pi_cash_sessions (clinic_id,user_id,location_id,business_date,opened_at,opening_balance_cents,status,created_at,updated_at) VALUES (?,?,?,?,NOW(),?,'open',NOW(),NOW())",
            [$cid, $uid, $loc, $today, $openingBalance],
        );
        $sid = db_last_insert_id();
        audit("caixa_atendimento_aberto", "financeiro", $sid, [
            "gaveta" => $loc,
            "saldo_inicial" => $openingBalance,
            "audit_body" => "Gaveta aberta pelo Atendimento.",
        ]);
        return $sid;
    });
    if ($authorizationRequested) {
        throw new RuntimeException($authorizationMessage);
    }
    return $result;
}
function financial_session_movement_totals(int $cid, int $sessionId): array
{

    financial_operational_schema_ready();
    $rows = q(
        "SELECT movement_type,COALESCE(SUM(amount_cents),0) total FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND status IN ('confirmed','pending_review') GROUP BY movement_type",
        [$cid, $sessionId],
    )->fetchAll();
    $out = [
        "receipt" => 0,
        "payment" => 0,
        "refund" => 0,
        "transfer" => 0,
        "deposit" => 0,
        "adjustment" => 0,
    ];
    foreach ($rows as $r) {
        $out[(string) $r["movement_type"]] = (int) $r["total"];
    }
    return $out;
}
function financial_session_expected(array $session): int
{

    return financial_session_position_cents($session);
}
function financial_record_cash_difference(
    int $cid,
    int $uid,
    int $sessionId,
    int $locationId,
    int $difference,
    string $phase,
    string $status,
    string $notes = "",
    bool $linkSession = true,
): int {

    if ($difference === 0) {
        return 0;
    }
    $positive = $difference > 0;
    return financial_create_movement(
        $cid,
        "adjustment",
        abs($difference),
        $positive ? null : $locationId,
        $positive ? $locationId : null,
        $linkSession ? $sessionId : null,
        $uid,
        ($positive ? "Sobra" : "Falta") .
            " na reconciliação de " .
            ($phase === "opening" ? "abertura" : "fechamento"),
        "",
        trim($notes),
        $status,
        "cash_" . $phase . "_adjustment",
        $sessionId,
    );
}
function financial_ensure_closing_adjustment(
    array $session,
    int $uid,
    string $status,
): void {

    $difference = (int) ($session["difference_cents"] ?? 0);
    if ($difference === 0) {
        return;
    }
    $existing = (int) (val(
        "SELECT COUNT(*) FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND movement_type='adjustment' AND source_entity='cash_closing_adjustment' AND source_id=? AND status IN ('confirmed','pending_review')",
        [
            (int) $session["clinic_id"],
            (int) $session["id"],
            (int) $session["id"],
        ],
    ) ?:
        0);
    if ($existing === 0) {
        financial_record_cash_difference(
            (int) $session["clinic_id"],
            $uid,
            (int) $session["id"],
            (int) $session["location_id"],
            $difference,
            "closing",
            $status,
            trim((string) ($session["closing_notes"] ?? "")),
            true,
        );
    }
}
function financial_current_open_session(int $cid, int $uid): ?array
{

    financial_operational_schema_ready();
    return one(
        "SELECT * FROM pi_cash_sessions WHERE clinic_id=? AND user_id=? AND business_date=? AND status='open' LIMIT 1",
        [$cid, $uid, financial_today($cid)],
    );
}
function financial_require_open_session(int $cid, int $uid): array
{

    $s = financial_current_open_session($cid, $uid);
    if (!$s) {
        $today = financial_session_for_date($cid, $uid, financial_today($cid));
        if ($today && (string) $today["status"] === "kept_closed") {
            throw new RuntimeException(
                "A Gaveta foi mantida fechada. Abra a Gaveta antes de registrar movimentos.",
            );
        }
        throw new RuntimeException(
            "Abra a Gaveta antes de registrar movimentos do Atendimento.",
        );
    }
    return $s;
}
function financial_close_session(
    int $cid,
    int $uid,
    int $sessionId,
    int $declared,
    int $withdrawalAmount,
    int $withdrawalDestinationId = 0,
    string $notes = "",
): void {

    financial_operational_schema_ready();
    db_tx(function () use (
        $cid,
        $uid,
        $sessionId,
        $declared,
        $withdrawalAmount,
        $withdrawalDestinationId,
        $notes,
    ): void {

        $s = one(
            "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? AND user_id=? AND status='open' FOR UPDATE",
            [$sessionId, $cid, $uid],
        );
        if (!$s) {
            throw new RuntimeException("Não há Gaveta aberta para fechamento.");
        }
        $expected = financial_session_expected($s);
        $declared = financial_assert_amount_cents(max(0, $declared));
        $withdrawalAmount = max(0, min($withdrawalAmount, $declared));
        $keep = max(0, $declared - $withdrawalAmount);
        $diff = financial_checked_add(
            $declared,
            -$expected,
            "Diferença do fechamento",
        );
        $destinationId = 0;
        if ($withdrawalAmount > 0) {
            if ($withdrawalDestinationId > 0) {
                if (
                    !financial_office_destination_belongs(
                        $cid,
                        $withdrawalDestinationId,
                    )
                ) {
                    throw new RuntimeException(
                        "Informe um Destino da Retirada válido entre as contas do Consultório.",
                    );
                }
                $destinationId = $withdrawalDestinationId;
            } else {
                $destinationId = financial_ensure_admin_safe($cid, $uid);
            }
            if ($destinationId <= 0) {
                throw new RuntimeException(
                    "Não foi possível preparar o Destino da Retirada.",
                );
            }
        }
        q(
            "UPDATE pi_cash_sessions SET closed_at=NOW(), expected_closing_cents=?, declared_closing_cents=?, keep_in_drawer_cents=?, transfer_to_safe_cents=?, difference_cents=?, closing_notes=?, status='closed_pending_review', updated_at=NOW() WHERE id=? AND clinic_id=? AND user_id=?",
            [
                $expected,
                $declared,
                $keep,
                $withdrawalAmount,
                $diff,
                trim($notes) ?: null,
                $sessionId,
                $cid,
                $uid,
            ],
        );
        if ($withdrawalAmount > 0) {
            financial_create_movement(
                $cid,
                "transfer",
                $withdrawalAmount,
                (int) $s["location_id"],
                $destinationId,
                $sessionId,
                $uid,
                "Retirada do fechamento da Gaveta",
                "",
                trim($notes),
                "pending_review",
                "cash_session",
                $sessionId,
            );
        }
        financial_record_cash_difference(
            $cid,
            $uid,
            $sessionId,
            (int) $s["location_id"],
            $diff,
            "closing",
            "pending_review",
            trim($notes),
            true,
        );
        financial_drawer_lock_after_close(
            $cid,
            (int) $s["location_id"],
            (string) $s["business_date"],
            $sessionId,
            $uid,
        );
        audit("caixa_atendimento_fechado", "financeiro", $sessionId, [
            "esperado" => $expected,
            "declarado" => $declared,
            "fazer_retirada" => $withdrawalAmount,
            "destino_retirada" => $destinationId,
            "manter_gaveta" => $keep,
            "diferenca" => $diff,
        ]);
    });
}
function financial_review_opening_request(
    int $cid,
    int $adminUid,
    int $sessionId,
    string $decision,
    string $notes = "",
): void {

    financial_operational_schema_ready();
    db_tx(function () use (
        $cid,
        $adminUid,
        $sessionId,
        $decision,
        $notes,
    ): void {

        $s = one(
            "SELECT s.*,u.name user_name FROM pi_cash_sessions s LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.id=? AND s.clinic_id=? AND s.status='opening_pending_review' FOR UPDATE",
            [$sessionId, $cid],
        );
        if (!$s) {
            throw new RuntimeException(
                "A solicitação de abertura não está pendente de autorização.",
            );
        }
        $decision = $decision === "reject" ? "reject" : "approve";
        $expected = (int) ($s["expected_closing_cents"] ?? 0);
        $informed = (int) ($s["opening_balance_cents"] ?? 0);
        $diff = financial_checked_add(
            $informed,
            -$expected,
            "Diferença da abertura",
        );
        $cleanNotes = trim($notes);
        if ($decision === "reject") {
            q(
                "UPDATE pi_cash_sessions SET status='opening_rejected', reviewed_by=?, reviewed_at=NOW(), review_status='rejected', review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$adminUid, $cleanNotes ?: null, $sessionId, $cid],
            );
            q(
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    "Abertura de caixa recusada",
                    "O Administrativo recusou a abertura de caixa com saldo diferente. Esperado: " .
                    money_br($expected) .
                    ". Saldo informado: " .
                    money_br($informed) .
                    ($cleanNotes !== ""
                        ? "\n\nObservação: " . $cleanNotes
                        : ""),
                    1,
                    "user",
                    null,
                    (int) $s["user_id"],
                    $adminUid,
                ],
            );
            audit(
                "abertura_caixa_divergente_recusada",
                "financeiro",
                $sessionId,
                [
                    "usuario_caixa" => (int) $s["user_id"],
                    "esperado" => $expected,
                    "informado" => $informed,
                    "diferenca" => $diff,
                    "motivo" => $cleanNotes,
                ],
            );
            return;
        }
        q(
            "UPDATE pi_cash_sessions SET opened_at=NOW(), kept_closed_at=NULL, closed_at=NULL, expected_closing_cents=0, declared_closing_cents=0, keep_in_drawer_cents=0, transfer_to_safe_cents=0, difference_cents=0, status='open', reviewed_by=?, reviewed_at=NOW(), review_status='approved', review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
            [$adminUid, $cleanNotes ?: null, $sessionId, $cid],
        );
        financial_record_cash_difference(
            $cid,
            $adminUid,
            $sessionId,
            (int) $s["location_id"],
            $diff,
            "opening",
            "confirmed",
            $cleanNotes,
            false,
        );
        q(
            "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                "Abertura de caixa autorizada",
                "O Administrativo autorizou a abertura de caixa com saldo diferente. Esperado: " .
                money_br($expected) .
                ". Saldo autorizado: " .
                money_br($informed) .
                ($cleanNotes !== "" ? "\n\nObservação: " . $cleanNotes : ""),
                1,
                "user",
                null,
                (int) $s["user_id"],
                $adminUid,
            ],
        );
        audit(
            "abertura_caixa_divergente_autorizada",
            "financeiro",
            $sessionId,
            [
                "usuario_caixa" => (int) $s["user_id"],
                "esperado" => $expected,
                "informado" => $informed,
                "diferenca" => $diff,
                "observacao" => $cleanNotes,
                "audit_body" =>
                    "Gerência autorizou abertura da Gaveta com valor inicial divergente.",
            ],
        );
    });
}
function financial_review_session(
    int $cid,
    int $uid,
    int $sessionId,
    string $decision,
    string $notes = "",
    string $drawerUnlockLocal = "",
): void {

    financial_operational_schema_ready();
    db_tx(function () use (
        $cid,
        $uid,
        $sessionId,
        $decision,
        $notes,
        $drawerUnlockLocal,
    ): void {

        $s = one(
            "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? AND status='closed_pending_review' FOR UPDATE",
            [$sessionId, $cid],
        );
        if (!$s) {
            throw new RuntimeException(
                "Fechamento não está pendente de conferência.",
            );
        }
        if ($decision === "reject") {
            q(
                "UPDATE pi_cash_sessions SET status='rejected', review_status='rejected', reviewed_by=?, reviewed_at=NOW(), review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
                [$uid, trim($notes) ?: null, $sessionId, $cid],
            );
            q(
                "UPDATE pi_financial_movements SET status='rejected', reviewed_by=?, reviewed_at=NOW(), notes=CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='', '', ' | '), ?) WHERE clinic_id=? AND cash_session_id=? AND status='pending_review'",
                [$uid, trim($notes), $cid, $sessionId],
            );
            q(
                "INSERT INTO pi_cash_closing_reviews (clinic_id,cash_session_id,reviewed_by,decision,expected_cents,declared_cents,approved_transfer_cents,difference_cents,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    $sessionId,
                    $uid,
                    "rejected",
                    (int) $s["expected_closing_cents"],
                    (int) $s["declared_closing_cents"],
                    0,
                    (int) $s["difference_cents"],
                    trim($notes) ?: null,
                ],
            );
            audit("fechamento_caixa_devolvido", "financeiro", $sessionId, [
                "motivo" => $notes,
            ]);
            return;
        }
        financial_ensure_closing_adjustment(
            $s,
            $uid,
            "pending_review",
        );
        $s = one(
            "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? FOR UPDATE",
            [$sessionId, $cid],
        ) ?: $s;
        financial_assert_session_reconciled($s);
        $remaining =
            (int) (val(
                "SELECT COUNT(*) FROM pi_cash_sessions WHERE clinic_id=? AND location_id=? AND business_date=? AND status='closed_pending_review' AND id<>?",
                [
                    $cid,
                    (int) $s["location_id"],
                    (string) $s["business_date"],
                    $sessionId,
                ],
            ) ?:
            0);
        if ($remaining === 0 && (string) ($s["location_id"] ?? "") !== "0") {
            $drawer = financial_drawer_row($cid, (int) $s["location_id"]);
            if (
                $drawer &&
                (string) ($drawer["drawer_lock_status"] ?? "unlocked") ===
                    "locked" &&
                trim($drawerUnlockLocal) === ""
            ) {
                throw new RuntimeException(
                    "Informe o horário do dia seguinte em que a Gaveta será destrancada.",
                );
            }
        }
        q(
            "UPDATE pi_cash_sessions SET status='approved', review_status='approved', reviewed_by=?, reviewed_at=NOW(), review_notes=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
            [$uid, trim($notes) ?: null, $sessionId, $cid],
        );
        q(
            "UPDATE pi_financial_movements SET status='confirmed', confirmed_by=?, confirmed_at=NOW(), reviewed_by=?, reviewed_at=NOW() WHERE clinic_id=? AND cash_session_id=? AND status='pending_review'",
            [$uid, $uid, $cid, $sessionId],
        );
        q(
            "INSERT INTO pi_cash_closing_reviews (clinic_id,cash_session_id,reviewed_by,decision,expected_cents,declared_cents,approved_transfer_cents,difference_cents,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())",
            [
                $cid,
                $sessionId,
                $uid,
                "approved",
                (int) $s["expected_closing_cents"],
                (int) $s["declared_closing_cents"],
                (int) $s["transfer_to_safe_cents"],
                (int) $s["difference_cents"],
                trim($notes) ?: null,
            ],
        );
        if ($remaining === 0 && (int) $s["location_id"] > 0) {
            financial_schedule_drawer_unlock(
                $cid,
                (int) $s["location_id"],
                $uid,
                $drawerUnlockLocal,
                trim($notes),
            );
        }
        audit("fechamento_caixa_conferido", "financeiro", $sessionId, [
            "retirada" => (int) $s["transfer_to_safe_cents"],
            "diferenca" => (int) $s["difference_cents"],
            "destravar_em" => $drawerUnlockLocal,
        ]);
    });
}
function financial_assert_session_reconciled(array $session): void
{

    $cid = (int) ($session["clinic_id"] ?? 0);
    $sessionId = (int) ($session["id"] ?? 0);
    $locationId = (int) ($session["location_id"] ?? 0);
    $expected = (int) ($session["expected_closing_cents"] ?? 0);
    $declared = (int) ($session["declared_closing_cents"] ?? 0);
    $kept = (int) ($session["keep_in_drawer_cents"] ?? 0);
    $transferred = (int) ($session["transfer_to_safe_cents"] ?? 0);
    $difference = (int) ($session["difference_cents"] ?? 0);
    if (
        !financial_closing_equation(
            $expected,
            $declared,
            $kept,
            $transferred,
            $difference,
        )
    ) {
        throw new RuntimeException(
            "O fechamento não satisfaz a equação de conservação monetária.",
        );
    }
    if (financial_session_position_cents($session) !== $kept) {
        throw new RuntimeException(
            "A posição dos movimentos da Gaveta diverge do saldo mantido.",
        );
    }
    $movements = q(
        "SELECT movement_type,status,amount_cents,from_location_id,to_location_id,cash_session_id,source_entity,source_id FROM pi_financial_movements WHERE clinic_id=? AND cash_session_id=? AND status IN ('confirmed','pending_review') ORDER BY id",
        [$cid, $sessionId],
    )->fetchAll();
    $transferTotal = 0;
    $adjustments = [];
    foreach ($movements as $movement) {
        $type = (string) ($movement["movement_type"] ?? "");
        $from = (int) ($movement["from_location_id"] ?? 0) ?: null;
        $to = (int) ($movement["to_location_id"] ?? 0) ?: null;
        financial_validate_movement_topology($type, $from, $to);
        if (
            financial_movement_delta_for_location(
                (int) ($movement["amount_cents"] ?? 0),
                $from,
                $to,
                $locationId,
            ) === 0
        ) {
            throw new RuntimeException(
                "Movimento da sessão não alcança a Gaveta reconciliada.",
            );
        }
        if (
            $type === "transfer" &&
            (string) ($movement["source_entity"] ?? "") === "cash_session" &&
            (int) ($movement["source_id"] ?? 0) === $sessionId
        ) {
            $transferTotal = financial_checked_add(
                $transferTotal,
                (int) $movement["amount_cents"],
                "Total de retiradas",
            );
        }
        if (
            $type === "adjustment" &&
            (string) ($movement["source_entity"] ?? "") ===
                "cash_closing_adjustment" &&
            (int) ($movement["source_id"] ?? 0) === $sessionId
        ) {
            $adjustments[] = $movement;
        }
    }
    if ($transferTotal !== $transferred) {
        throw new RuntimeException(
            "A retirada declarada diverge dos movimentos da sessão.",
        );
    }
    if ($difference === 0 && $adjustments !== []) {
        throw new RuntimeException(
            "Fechamento sem diferença contém ajuste compensatório indevido.",
        );
    }
    if ($difference !== 0) {
        if (
            count($adjustments) !== 1 ||
            (int) $adjustments[0]["amount_cents"] !== abs($difference)
        ) {
            throw new RuntimeException(
                "A diferença do fechamento não possui compensação única e exata.",
            );
        }
        $adjustment = $adjustments[0];
        $from = (int) ($adjustment["from_location_id"] ?? 0) ?: null;
        $to = (int) ($adjustment["to_location_id"] ?? 0) ?: null;
        $expectedDelta = $difference > 0 ? abs($difference) : -abs($difference);
        if (
            financial_movement_delta_for_location(
                (int) $adjustment["amount_cents"],
                $from,
                $to,
                $locationId,
            ) !== $expectedDelta
        ) {
            throw new RuntimeException(
                "O ajuste compensatório possui orientação incompatível com a diferença.",
            );
        }
    }
}
function financial_location_movement_balance(int $cid, int $locationId): int
{

    $balances = financial_location_movement_balances($cid, [$locationId]);
    return (int) ($balances[$locationId] ?? 0);
}
function financial_location_movement_balances(
    int $cid,
    array $locationIds,
): array {

    static $requestCache = [];
    $locationIds = array_values(
        array_unique(
            array_filter(
                array_map("intval", $locationIds),
                static  fn(int $id): bool => $id > 0,
            ),
        ),
    );
    if ($cid <= 0 || !$locationIds) {
        return [];
    }
    $known = $requestCache[$cid] ?? [];
    $missing = array_values(
        array_filter(
            $locationIds,
            static  fn(int $id): bool => !array_key_exists($id, $known),
        ),
    );
    if ($missing) {
        $ph = implode(",", array_fill(0, count($missing), "?"));
        foreach ($missing as $locationId) {
            $known[$locationId] = 0;
        }
        $rows = q(
            "SELECT location_id,COALESCE(SUM(delta_cents),0) balance_cents FROM (SELECT to_location_id location_id,amount_cents delta_cents FROM pi_financial_movements WHERE clinic_id=? AND to_location_id IN ($ph) AND status='confirmed' UNION ALL SELECT from_location_id location_id,-amount_cents delta_cents FROM pi_financial_movements WHERE clinic_id=? AND from_location_id IN ($ph) AND status='confirmed') movement_totals GROUP BY location_id",
            array_merge([$cid], $missing, [$cid], $missing),
        )->fetchAll();
        foreach ($rows as $row) {
            $known[(int) $row["location_id"]] =
                financial_assert_balance_cents(
                    (int) $row["balance_cents"],
                    "Saldo do local financeiro",
                );
        }
        $requestCache[$cid] = $known;
    }
    return array_intersect_key($known, array_flip($locationIds));
}
function financial_pos_balance_for_user(int $cid, int $uid): int
{

    $loc = financial_cashier_location_for_user($cid, $uid);
    return $loc > 0 ? financial_drawer_balance($cid, $loc) : 0;
}
function financial_global_position(int $cid): array
{

    financial_operational_schema_ready();
    $safe = financial_ensure_admin_safe($cid, (int) ($_SESSION["uid"] ?? 0));
    $safeBalance = financial_location_movement_balance($cid, $safe);
    $pending =
        (int) (val(
            "SELECT COALESCE(SUM(transfer_to_safe_cents),0) FROM pi_cash_sessions WHERE clinic_id=? AND status='closed_pending_review'",
            [$cid],
        ) ?:
        0);
    $posRows = q(
        "SELECT l.id,l.user_id,l.name FROM pi_financial_locations l WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,l.id",
        [$cid],
    )->fetchAll();
    $posIds = array_map(
        static  fn(array $row): int => (int) $row["id"],
        $posRows,
    );
    $posSnapshot = financial_drawer_balance_snapshot($cid, $posIds);
    $posBalances = (array) ($posSnapshot["balances"] ?? []);
    $posOpen = (array) ($posSnapshot["open"] ?? []);
    $linkedByLocation = [];
    if ($posIds) {
        $posPh = implode(",", array_fill(0, count($posIds), "?"));
        foreach (
            q(
                "SELECT location_id,COUNT(*) total FROM pi_financial_location_users WHERE clinic_id=? AND location_id IN ($posPh) AND active=1 GROUP BY location_id",
                array_merge([$cid], $posIds),
            )->fetchAll()
            as $linkRow
        ) {
            $linkedByLocation[(int) $linkRow["location_id"]] =
                (int) $linkRow["total"];
        }
    }
    $pos = [];
    $posTotal = 0;
    foreach ($posRows as $r) {
        $locationId = (int) $r["id"];
        $bal = (int) ($posBalances[$locationId] ?? 0);
        $open = $posOpen[$locationId] ?? null;
        $links = (int) ($linkedByLocation[$locationId] ?? 0);
        $r["balance_cents"] = $bal;
        $r["open_user_name"] = $open ? (string) ($open["user_name"] ?? "") : "";
        $r["linked_users"] = $links;
        $posTotal = financial_checked_add(
            $posTotal,
            $bal,
            "Total das Gavetas",
        );
        $pos[] = $r;
    }
    $bankRows = q(
        "SELECT l.id,l.name,l.account_id,a.bank_name,a.account_type FROM pi_financial_locations l LEFT JOIN pi_financial_accounts a ON a.id=l.account_id AND a.clinic_id=l.clinic_id WHERE l.clinic_id=? AND l.location_type='bank_account' AND l.active=1 ORDER BY l.name",
        [$cid],
    )->fetchAll();
    $bankBalances = financial_location_movement_balances(
        $cid,
        array_map(static  fn(array $row): int => (int) $row["id"], $bankRows),
    );
    $banks = [];
    $bankTotal = 0;
    foreach ($bankRows as $r) {
        $bal = (int) ($bankBalances[(int) $r["id"]] ?? 0);
        $r["balance_cents"] = $bal;
        $bankTotal = financial_checked_add(
            $bankTotal,
            $bal,
            "Total dos Bancos",
        );
        $banks[] = $r;
    }
    return [
        "safe_id" => $safe,
        "safe_cents" => $safeBalance,
        "pending_cents" => $pending,
        "pos_rows" => $pos,
        "pos_cents" => $posTotal,
        "bank_rows" => $banks,
        "bank_cents" => $bankTotal,
        "total_cents" => financial_checked_add(
            financial_checked_add(
                $safeBalance,
                $posTotal,
                "Posição financeira global",
            ),
            $bankTotal,
            "Posição financeira global",
        ),
    ];
}
function financial_cashier_requires_attention(array $c): bool
{

    if (!financial_is_cashier($c)) {
        return false;
    }
    if (clinic_read_only_db((int) $c["clinic_id"])) {
        return false;
    }
    try {
        financial_operational_schema_ready();
        $cid = (int) $c["clinic_id"];
        $uid = (int) $c["user"]["id"];
        $today = financial_today($cid);
        if (financial_cashier_location_for_user($cid, $uid) <= 0) {
            return false;
        }
        return (bool) financial_unclosed_previous_session($cid, $uid, $today);
    } catch (Throwable $e) {
        error_log("[Prontoo financeiro caixa atenção] " . $e->getMessage());
        return false;
    }
}
function financial_register_appointment_payment_movement(
    int $cid,
    int $appointmentId,
    int $userId,
    int $amount,
    string $method,
    string $title,
    bool $paid,
    int $paymentDestinationLocationId = 0,
): void {

    financial_operational_schema_ready();
    $existing = one(
        "SELECT id,status FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' ORDER BY id DESC LIMIT 1",
        [$cid, $appointmentId],
    );
    if (!$paid) {
        if ($existing) {
            q(
                "UPDATE pi_financial_movements SET status='cancelled', confirmed_by=NULL, confirmed_at=NULL, notes=CONCAT(COALESCE(notes,''), IF(COALESCE(notes,'')='', '', ' | '), 'Pagamento desmarcado no agendamento.'), reviewed_at=NOW() WHERE id=? AND clinic_id=?",
                [(int) $existing["id"], $cid],
            );
        }
        return;
    }
    if ($amount <= 0) {
        return;
    }
    $method = normalize_payment_method($method);
    if ($method === "") {
        throw new RuntimeException("Selecione a forma de pagamento.");
    }
    $to = 0;
    $sessionId = null;
    $movementNotes = "Recebimento vinculado ao agendamento.";
    if ($method === "dinheiro") {
        $s = financial_require_open_session($cid, $userId);
        $sessionId = (int) $s["id"];
        $to = (int) $s["location_id"];
        $movementNotes =
            "Recebimento em dinheiro vinculado ao agendamento; compõe a conferência da Gaveta aberta.";
    } else {
        if ($paymentDestinationLocationId <= 0) {
            throw new RuntimeException("Selecione o destino do recebimento.");
        }
        if (
            function_exists("financial_office_destination_belongs")
                ? !financial_office_destination_belongs(
                    $cid,
                    $paymentDestinationLocationId,
                )
                : !financial_location_belongs(
                    $cid,
                    $paymentDestinationLocationId,
                )
        ) {
            throw new RuntimeException(
                "Selecione um destino financeiro válido para este consultório.",
            );
        }
        $to = $paymentDestinationLocationId;
        $movementNotes =
            "Recebimento sem dinheiro físico vinculado ao agendamento; creditado no destino selecionado.";
    }
    if ($to <= 0) {
        throw new RuntimeException(
            "Não foi possível identificar o destino financeiro do recebimento.",
        );
    }
    if ($existing) {
        financial_update_existing_movement(
            $cid,
            (int) $existing["id"],
            "receipt",
            $amount,
            null,
            $to,
            $sessionId,
            $userId,
            $title,
            $method,
            $movementNotes,
            "confirmed",
        );
    } else {
        financial_create_movement(
            $cid,
            "receipt",
            $amount,
            null,
            $to,
            $sessionId,
            $userId,
            $title,
            $method,
            $movementNotes,
            "confirmed",
            "appointment",
            $appointmentId,
        );
    }
}
function financial_revenue_id_for_appointment(
    int $cid,
    int $appointmentId,
    int $uid = 0,
): int {

    if ($cid <= 0 || $appointmentId <= 0) {
        return 0;
    }
    financial_operational_schema_ready();
    $rid =
        (int) (val(
            "SELECT id FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=? LIMIT 1",
            [$cid, $appointmentId],
        ) ?:
        0);
    if ($rid > 0) {
        return $rid;
    }
    try {
        financial_sync_appointment($cid, $appointmentId, $uid);
    } catch (Throwable $e) {
        error_log("[Prontoo financial revenue sync link] " . $e->getMessage());
    }
    return (int) (val(
        "SELECT id FROM pi_financial_revenues WHERE clinic_id=? AND appointment_id=? LIMIT 1",
        [$cid, $appointmentId],
    ) ?:
    0);
}
function financial_appointment_payment_state(array $a): array
{

    $amount = (int) ($a["payment_amount_cents"] ?? 0);
    $status = mb_strtolower(trim((string) ($a["payment_status"] ?? "")));
    $method = normalize_payment_method((string) ($a["payment_method"] ?? ""));
    $paid =
        !empty($a["payment_confirmed_at"]) ||
        in_array($status, ["efetivada", "recebido", "pago"], true);
    $forgiven = in_array($status, ["isento", "cortesia", "sem_cobranca"], true);
    if ($amount <= 0 || $forgiven) {
        return [
            "code" => "sem_cobranca",
            "label" => $forgiven ? "Cortesia / isento" : "Sem cobrança",
            "class" => "neutral",
            "icon" => "money_off",
            "amount" => $amount,
            "method" => $method,
        ];
    }
    if ($paid) {
        return [
            "code" => "recebido",
            "label" => "Recebido",
            "class" => "ok",
            "icon" => "check_circle",
            "amount" => $amount,
            "method" => $method,
        ];
    }
    $journey = function_exists("appointment_status_code")
        ? appointment_status_code($a)
        : (string) ($a["status"] ?? "");
    if (in_array($journey, ["atendimento_concluido", "finalizado"], true)) {
        return [
            "code" => "aguardando_pagamento",
            "label" => "Aguardando pagamento",
            "class" => "warn",
            "icon" => "payments",
            "amount" => $amount,
            "method" => $method,
        ];
    }
    return [
        "code" => "previsto",
        "label" => "Valor previsto",
        "class" => "info",
        "icon" => "payments",
        "amount" => $amount,
        "method" => $method,
    ];
}
function financial_appointment_operational_chip_html(
    int $cid,
    array $a,
    string $role = "",
): string {

    $st = financial_appointment_payment_state($a);
    $amount = (int) ($st["amount"] ?? 0);
    $title = $amount > 0 ? money_br($amount) : "Sem valor financeiro";
    $method = (string) ($st["method"] ?? "");
    $methodLabel =
        $method !== "" ? payment_methods_options()[$method] ?? $method : "";
    $chip =
        '<span class="finance-appointment-chip finance-appointment-chip-' .
        e((string) $st["code"]) .
        " pill " .
        e((string) $st["class"]) .
        '" title="' .
        e($title . ($methodLabel !== "" ? " · " . $methodLabel : "")) .
        '">' .
        icon((string) $st["icon"]) .
        "<span>" .
        e((string) $st["label"]) .
        "</span>" .
        ($amount > 0 ? "<b>" . e(money_br($amount)) . "</b>" : "") .
        "</span>";
    $canReceive =
        function_exists("appointment_journey_role_matches") &&
        (appointment_journey_role_matches($role, "recepcionista") ||
            appointment_journey_role_matches($role, "gerente"));
    if (
        $canReceive &&
        (string) $st["code"] === "aguardando_pagamento" &&
        (int) ($a["id"] ?? 0) > 0
    ) {
        $chip .=
            '<a class="finance-appointment-receive small primary" href="' .
            href("financial", [
                "op" => "receber",
                "appointment_id" => (int) $a["id"],
            ]) .
            '">' .
            icon("point_of_sale") .
            "<span>Receber</span></a>";
    }
    return '<span class="finance-appointment-inline">' . $chip . "</span>";
}
function financial_daily_drawer_closure_state(
    int $cid,
    string $businessDate = "",
): array {

    financial_operational_schema_ready();
    $businessDate = $businessDate ?: financial_today($cid);
    $expected = [];
    $linked = q(
        "SELECT l.id location_id,COALESCE(l.name,'Gaveta') drawer_name,u.id user_id,COALESCE(u.name,'Colaborador') user_name FROM pi_financial_locations l JOIN pi_financial_location_users lu ON lu.location_id=l.id AND lu.clinic_id=l.clinic_id AND lu.active=1 JOIN pi_users u ON u.id=lu.user_id AND u.active=1 WHERE l.clinic_id=? AND l.location_type='pos' AND l.active=1 ORDER BY l.name,u.name",
        [$cid],
    )->fetchAll();
    foreach ($linked as $r) {
        $key = (int) $r["location_id"] . ":" . (int) $r["user_id"];
        $expected[$key] = $r;
    }
    $sessions = q(
        "SELECT s.id,s.status,s.opened_at,s.closed_at,s.business_date,s.location_id,s.user_id,s.opening_balance_cents,s.expected_closing_cents,s.declared_closing_cents,s.keep_in_drawer_cents,s.transfer_to_safe_cents,s.difference_cents,COALESCE(l.name,'Gaveta') drawer_name,COALESCE(u.name,'Colaborador') user_name FROM pi_cash_sessions s LEFT JOIN pi_financial_locations l ON l.id=s.location_id AND l.clinic_id=s.clinic_id LEFT JOIN pi_users u ON u.id=s.user_id WHERE s.clinic_id=? AND s.business_date=? ORDER BY COALESCE(l.name,''),COALESCE(u.name,''),s.id",
        [$cid, $businessDate],
    )->fetchAll();
    $sessionByKey = [];
    foreach ($sessions as $srow) {
        $key = (int) $srow["location_id"] . ":" . (int) $srow["user_id"];
        $sessionByKey[$key] = $srow;
        if (!isset($expected[$key])) {
            $expected[$key] = [
                "location_id" => (int) $srow["location_id"],
                "drawer_name" => (string) ($srow["drawer_name"] ?? "Gaveta"),
                "user_id" => (int) $srow["user_id"],
                "user_name" => (string) ($srow["user_name"] ?? "Colaborador"),
            ];
        }
    }
    $rows = [];
    $blocking = [];
    $closedStatuses = [
        "closed_pending_review",
        "approved",
        "rejected",
        "kept_closed",
    ];
    foreach ($expected as $key => $base) {
        $r = $sessionByKey[$key] ?? [];
        $status = $r ? (string) ($r["status"] ?? "") : "not_started";
        $row = array_merge($base, $r);
        $row["status"] = $status;
        $row["has_session"] = $r ? 1 : 0;
        $isBlocking = !$r || !in_array($status, $closedStatuses, true);
        $row["blocking"] = $isBlocking ? 1 : 0;
        $rows[] = $row;
        if ($isBlocking) {
            $blocking[] = $row;
        }
    }
    return [
        "business_date" => $businessDate,
        "opened_count" => count($rows),
        "expected_count" => count($expected),
        "blocking_count" => count($blocking),
        "blocking_rows" => $blocking,
        "rows" => $rows,
        "all_closed" => count($blocking) === 0,
    ];
}
function financial_daily_drawer_closure_blocking_html(array $state): string
{

    $rows = $state["blocking_rows"] ?? [];
    if (!$rows) {
        return "";
    }
    $h = '<div class="finance-conference-blocking-list">';
    foreach ($rows as $r) {
        $status = (string) ($r["status"] ?? "not_started");
        $label =
            $status === "not_started"
                ? "Aguardando abertura/fechamento"
                : financial_human_session_status($status);
        $h .=
            "<div><b>" .
            e((string) ($r["drawer_name"] ?? "Gaveta")) .
            "</b><span>" .
            e((string) ($r["user_name"] ?? "Colaborador")) .
            " · " .
            e($label) .
            "</span></div>";
    }
    return $h . "</div>";
}
function financial_admin_daily_consolidation_html(int $cid): string
{

    financial_operational_schema_ready();
    $today = financial_today($cid);
    [$dayStart, $dayEnd] = app_local_day_utc_range($today, $cid);
    $expected = (int) safe_val(
        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND amount_cents>0 AND expected_at>=? AND expected_at<?",
        [$cid, $dayStart, $dayEnd],
        0,
    );
    $received = (int) safe_val(
        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='efetivada' AND received_at>=? AND received_at<?",
        [$cid, $dayStart, $dayEnd],
        0,
    );
    $pending = (int) safe_val(
        "SELECT COALESCE(SUM(amount_cents),0) FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND amount_cents>0 AND expected_at<?",
        [$cid, $dayEnd],
        0,
    );
    $state = financial_daily_consolidation_state($cid, $today);
    $openDrawers = (int) ($state["pending_open_drawers"] ?? 0);
    $pos = financial_global_position($cid);
    $cards =
        '<div class="finance-consolidation-kpis"><article>' .
        icon("event_available") .
        "<b>" .
        money_br($expected) .
        "</b><span>Previsto hoje</span></article><article>" .
        icon("task_alt") .
        "<b>" .
        money_br($received) .
        "</b><span>Recebido hoje</span></article><article>" .
        icon("pending_actions") .
        "<b>" .
        money_br($pending) .
        "</b><span>Pendente</span></article><article>" .
        icon("point_of_sale") .
        "<b>" .
        money_br((int) $pos["pos_cents"]) .
        "</b><span>Em gavetas</span></article></div>";
    if (!empty($state["consolidated"])) {
        $footer =
            '<footer class="finance-consolidation-footer"><button class="ghost small" type="button" disabled aria-disabled="true">' .
            icon("verified") .
            "<span>Dia consolidado</span></button></footer>";
    } elseif ($openDrawers > 0) {
        $footer =
            '<footer class="finance-consolidation-footer"><button class="ghost small" type="button" disabled aria-disabled="true">' .
            icon("point_of_sale") .
            "<span>Aguardando fechamentos</span></button></footer>";
    } else {
        $footer =
            '<footer class="finance-consolidation-footer"><a class="primary small" href="' .
            href("financial", ["tab" => "consolidacao"]) .
            '">' .
            icon("fact_check") .
            "<span>Conferência liberada</span></a></footer>";
    }
    return '<section class="finance-consolidation-panel"><header><span>' .
        icon("monitoring") .
        "</span><div><h2>Consolidação do dia</h2></div></header>" .
        $cards .
        $footer .
        "</section>";
}
function financial_cashier_pending_receipts_html(int $cid): string
{

    financial_operational_schema_ready();
    $today = financial_today($cid);
    [$dayStart, $dayEnd] = app_local_day_utc_range($today, $cid);
    $rows = q(
        "SELECT r.id,r.amount_cents,r.title,a.id appointment_id,a.start_at,a.status,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.clinic_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.expected_at<? AND a.status NOT IN ('cancelado','nao_compareceu') ORDER BY COALESCE(a.start_at,r.expected_at,NOW()) ASC,r.id ASC LIMIT 8",
        [$cid, $dayEnd],
    )->fetchAll();
    if (!$rows) {
        return '<div class="empty">Nenhum atendimento com pagamento pendente até agora.</div>';
    }
    $h = '<div class="finance-reception-pending">';
    foreach ($rows as $r) {
        $patient =
            trim((string) ($r["patient_name"] ?? "Paciente")) ?: "Paciente";
        $proc =
            trim(
                (string) ($r["procedure_title"] ??
                    ($r["title"] ?? "Procedimento")),
            ) ?:
            "Procedimento";
        $h .=
            '<a href="' .
            href("financial", [
                "op" => "receber",
                "revenue_id" => (int) $r["id"],
            ]) .
            '"><span>' .
            icon("payments") .
            "<b>" .
            e($patient) .
            "</b></span><small>" .
            e(app_time_br((string) $r["start_at"]) . " · " . $proc) .
            "</small><em>" .
            e(money_br((int) $r["amount_cents"])) .
            "</em></a>";
    }
    return $h . "</div>";
}
function financial_location_select_options(int $cid, string $type = ""): array
{

    financial_operational_schema_ready();
    $params = [$cid];
    $where = "clinic_id=? AND active=1";
    if ($type !== "") {
        $where .= " AND location_type=?";
        $params[] = $type;
    }
    $rows = q(
        "SELECT id,name,location_type FROM pi_financial_locations WHERE $where ORDER BY FIELD(location_type,'admin_safe','pos','bank_account'), name",
        $params,
    )->fetchAll();
    $out = ["" => "Selecione"];
    foreach ($rows as $r) {
        $out[(int) $r["id"]] =
            financial_location_type_label((string) $r["location_type"]) .
            " · " .
            (string) $r["name"];
    }
    return $out;
}
function financial_cashier_receipt_method_options(): array
{

    return [
        "dinheiro" => "Dinheiro",
        "pix" => "PIX",
        "debito" => "Cartão de débito",
        "credito" => "Cartão de crédito",
        "transferencia" => "Transferência bancária",
        "boleto" => "Boleto bancário",
        "cheque" => "Cheque",
        "outro" => "Outro",
    ];
}
function financial_office_destination_options(int $cid, int $uid = 0): array
{

    financial_operational_schema_ready();
    financial_ensure_admin_safe($cid, $uid);
    try {
        $accounts = q(
            "SELECT id FROM pi_financial_accounts WHERE clinic_id=? AND active=1 AND account_type IN ('conta_corrente','conta_poupanca','conta_pagamento','investimento') ORDER BY name",
            [$cid],
        )->fetchAll();
        foreach ($accounts as $a) {
            financial_ensure_bank_location($cid, (int) $a["id"], $uid);
        }
    } catch (Throwable $e) {
        error_log("[Prontoo destinos financeiros] " . $e->getMessage());
    }
    $rows = q(
        "SELECT l.id,l.name,l.location_type,a.bank_name FROM pi_financial_locations l LEFT JOIN pi_financial_accounts a ON a.id=l.account_id AND a.clinic_id=l.clinic_id WHERE l.clinic_id=? AND l.active=1 AND l.location_type IN ('admin_safe','bank_account') ORDER BY FIELD(l.location_type,'admin_safe','bank_account'), l.name",
        [$cid],
    )->fetchAll();
    $out = ["" => "Selecione o destino"];
    foreach ($rows as $r) {
        $type = (string) $r["location_type"];
        $label =
            $type === "admin_safe"
                ? "Cofre do Consultório"
                : "Conta do Consultório";
        $detail = trim((string) ($r["name"] ?? ""));
        if ($type === "bank_account" && !empty($r["bank_name"])) {
            $detail .= " · " . (string) $r["bank_name"];
        }
        $out[(int) $r["id"]] = $label . ($detail !== "" ? " · " . $detail : "");
    }
    return $out;
}
function financial_office_destination_belongs(int $cid, int $locationId): bool
{

    if ($cid <= 0 || $locationId <= 0) {
        return false;
    }
    return (int) (val(
        "SELECT id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 AND location_type IN ('admin_safe','bank_account') LIMIT 1",
        [$locationId, $cid],
    ) ?:
        0) > 0;
}
function financial_expected_appointment_revenue_options(int $cid): array
{

    financial_operational_schema_ready();
    $rows = q(
        "SELECT r.id,r.amount_cents,r.title,r.expected_at,a.start_at,a.status,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.clinic_id=? AND r.status='prevista' AND r.appointment_id IS NOT NULL AND r.procedure_id IS NOT NULL AND r.amount_cents>0 AND a.status NOT IN ('cancelado','nao_compareceu') ORDER BY CASE WHEN a.status IN ('atendimento_concluido','finalizado') THEN 0 ELSE 1 END, COALESCE(a.start_at,r.expected_at,NOW()) ASC,r.id ASC LIMIT 200",
        [$cid],
    )->fetchAll();
    $out = ["" => "Selecione o atendimento"];
    foreach ($rows as $r) {
        $patient =
            trim((string) ($r["patient_name"] ?? "Paciente")) ?: "Paciente";
        $proc =
            trim(
                (string) ($r["procedure_title"] ??
                    ($r["title"] ?? "Procedimento")),
            ) ?:
            "Procedimento";
        $when = (string) ($r["start_at"] ?: $r["expected_at"] ?: "");
        $date = $when !== "" ? dt_br($when) : "sem data";
        $status = in_array(
            (string) ($r["status"] ?? ""),
            ["atendimento_concluido", "finalizado"],
            true,
        )
            ? "aguardando pagamento"
            : "previsto";
        $out[(int) $r["id"]] =
            $patient .
            " · " .
            $proc .
            " · " .
            $date .
            " · " .
            money_br((int) $r["amount_cents"]) .
            " · " .
            $status;
    }
    return $out;
}
function financial_receive_expected_appointment_revenue(
    int $cid,
    int $uid,
    int $revenueId,
    string $method,
    int $destinationLocationId = 0,
    string $notes = "",
): int {

    financial_operational_schema_ready();
    $method = normalize_payment_method($method);
    if ($method === "") {
        throw new RuntimeException("Informe a forma de recebimento.");
    }
    $s = financial_require_open_session($cid, $uid);
    return (int) db_tx(function () use (
        $cid,
        $uid,
        $revenueId,
        $method,
        $destinationLocationId,
        $notes,
        $s,
    ): int {

        $session = one(
            "SELECT * FROM pi_cash_sessions WHERE id=? AND clinic_id=? AND user_id=? AND status='open' FOR UPDATE",
            [(int) $s["id"], $cid, $uid],
        );
        if (!$session) {
            throw new RuntimeException(
                "Não há Gaveta aberta para registrar recebimento.",
            );
        }
        $rev = one(
            "SELECT r.*,a.status appointment_status,a.start_at,pr.title procedure_title,p.full_name patient_name FROM pi_financial_revenues r JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id LEFT JOIN pi_procedures pr ON pr.id=r.procedure_id AND pr.clinic_id=r.clinic_id LEFT JOIN pi_patients pp ON pp.id=r.patient_link_id AND pp.clinic_id=r.clinic_id LEFT JOIN pi_persons p ON p.id=pp.person_id WHERE r.id=? AND r.clinic_id=? AND r.status='prevista' AND r.appointment_id IS NOT NULL AND r.procedure_id IS NOT NULL AND r.amount_cents>0 AND a.status NOT IN ('cancelado') FOR UPDATE",
            [$revenueId, $cid],
        );
        if (!$rev) {
            throw new RuntimeException(
                "Selecione uma receita prevista de Procedimento Agendado ainda não recebida.",
            );
        }
        $amount = (int) $rev["amount_cents"];
        $appointmentId = (int) $rev["appointment_id"];
        $to = (int) $session["location_id"];
        $sessionId = (int) $session["id"];
        $accountId = null;
        $extraNote =
            "Recebimento em dinheiro vinculado ao agendamento; compõe a conferência da Gaveta.";
        if ($method !== "dinheiro") {
            if (
                !financial_office_destination_belongs(
                    $cid,
                    $destinationLocationId,
                )
            ) {
                throw new RuntimeException(
                    "Informe o Destino entre as contas do Consultório para recebimentos que não forem em Dinheiro.",
                );
            }
            $dest = one(
                "SELECT id,account_id,location_type,name FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
                [$destinationLocationId, $cid],
            );
            $to = (int) $destinationLocationId;
            $sessionId = null;
            $accountId =
                $dest && (string) $dest["location_type"] === "bank_account"
                    ? ((int) ($dest["account_id"] ?? 0) ?:
                    null)
                    : null;
            $extraNote =
                "Recebimento sem dinheiro físico vinculado ao agendamento; direcionado ao Consultório e fora da conferência da Gaveta.";
        }
        $title =
            "Recebimento · " .
            trim(
                (string) ($rev["procedure_title"] ?:
                $rev["title"] ?:
                "Procedimento agendado"),
            );
        $cleanNotes = trim($notes);
        $movementNotes =
            $extraNote . ($cleanNotes !== "" ? " " . $cleanNotes : "");
        $movementStatus =
            $method === "dinheiro" ? "pending_review" : "confirmed";
        $existing = one(
            "SELECT id FROM pi_financial_movements WHERE clinic_id=? AND source_entity='appointment' AND source_id=? AND movement_type='receipt' ORDER BY id DESC LIMIT 1 FOR UPDATE",
            [$cid, $appointmentId],
        );
        if ($existing) {
            financial_update_existing_movement(
                $cid,
                (int) $existing["id"],
                "receipt",
                $amount,
                null,
                $to,
                $sessionId,
                $uid,
                $title,
                $method,
                $movementNotes,
                $movementStatus,
            );
            $movementId = (int) $existing["id"];
        } else {
            $movementId = financial_create_movement(
                $cid,
                "receipt",
                $amount,
                null,
                $to,
                $sessionId,
                $uid,
                $title,
                $method,
                $movementNotes,
                $movementStatus,
                "appointment",
                $appointmentId,
            );
        }
        q(
            "UPDATE pi_financial_revenues SET status='efetivada', payment_method=?, account_id=?, received_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
            [$method, $accountId, $uid, $revenueId, $cid],
        );
        q(
            "UPDATE pi_appointments SET payment_status='efetivada', payment_method=?, payment_amount_cents=?, payment_confirmed_at=NOW(), revenue_id=?, updated_at=NOW() WHERE id=? AND clinic_id=?",
            [$method, $amount, $revenueId, $appointmentId, $cid],
        );
        audit("recebimento_atendimento_agendado", "financeiro", $revenueId, [
            "appointment_id" => $appointmentId,
            "movement_id" => $movementId,
            "valor" => $amount,
            "forma" => $method,
            "conta_na_gaveta" => $method === "dinheiro",
            "audit_body" =>
                "Recepção registrou recebimento de receita prevista vinculada a Procedimento Agendado.",
        ]);
        return $movementId;
    });
}
function financial_tabs_html(array $tabs, string $active): string
{

    $h =
        '<nav class="finance-admin-primary-nav" aria-label="Financeiro do Consultório">';
    foreach ($tabs as $key => $meta) {
        $h .=
            '<a class="ghost small' .
            ($active === $key ? " active" : "") .
            '" href="' .
            href("financial", ["tab" => $key]) .
            '">' .
            icon($meta[1]) .
            "<span>" .
            e($meta[0]) .
            "</span></a>";
    }
    return $h . "</nav>";
}
function financial_cash_exception_debug(
    Throwable $e,
    array $c,
    string $act = "",
): string {

    $lines = [];
    $lines[] = "PRONTOO_CAIXA_DEBUG";
    $lines[] = "timestamp_utc=" . gmdate("c");
    $lines[] = "route=" . (function_exists("route") ? route() : "indefinida");
    $lines[] = "http_method=" . (string) ($_SERVER["REQUEST_METHOD"] ?? "");
    $lines[] =
        "action=" . ($act !== "" ? $act : (string) ($_POST["act"] ?? ""));
    $lines[] = "clinic_id=" . (string) ($c["clinic_id"] ?? "");
    $lines[] = "user_id=" . (string) ($c["user"]["id"] ?? "");
    $lines[] =
        "role=" . (string) ($c["role"] ?? ($_SESSION["role_code"] ?? ""));
    $lines[] = "exception_class=" . get_class($e);
    $lines[] = "exception_code=" . (string) $e->getCode();
    $lines[] = "message=" . $e->getMessage();
    $lines[] = "file=" . $e->getFile();
    $lines[] = "line=" . (string) $e->getLine();
    if ($e->getPrevious()) {
        $p = $e->getPrevious();
        $lines[] = "previous_class=" . get_class($p);
        $lines[] = "previous_code=" . (string) $p->getCode();
        $lines[] = "previous_message=" . $p->getMessage();
        $lines[] = "previous_file=" . $p->getFile();
        $lines[] = "previous_line=" . (string) $p->getLine();
    }
    $post = [];
    foreach ($_POST as $k => $v) {
        $key = (string) $k;
        if (in_array($key, ["csrf", "_token", "password", "senha"], true)) {
            continue;
        }
        $post[$key] = is_scalar($v) ? (string) $v : gettype($v);
    }
    $lines[] =
        "post=" .
        json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $lines[] = "php_version=" . PHP_VERSION;
    $lines[] = "trace=";
    $trace = explode("\n", $e->getTraceAsString());
    $lines = array_merge($lines, array_slice($trace, 0, 12));
    return implode("\n", $lines);
}
function financial_cash_debug_details_enabled(): bool
{

    return function_exists("app_debug") && app_debug();
}
function financial_cash_store_debug(
    Throwable $e,
    array $c,
    string $act = "",
): void {

    $debug = financial_cash_exception_debug($e, $c, $act);
    $_SESSION["financial_cash_debug_error"] = $debug;
    $_SESSION["financial_cash_error_at"] = gmdate("d/m/Y H:i:s") . " UTC";
    error_log(
        "[Prontoo caixa atendimento] " . str_replace("\n", " | ", $debug),
    );
}
function financial_cash_debug_error_html(): string
{

    $debug = (string) ($_SESSION["financial_cash_debug_error"] ?? "");
    $when = (string) ($_SESSION["financial_cash_error_at"] ?? "");
    if ($debug === "") {
        return "";
    }
    unset(
        $_SESSION["financial_cash_debug_error"],
        $_SESSION["financial_cash_error_at"],
    );
    if (!financial_cash_debug_details_enabled()) {
        $ref =
            $when !== ""
                ? " Horário técnico: <strong>" . e($when) . "</strong>."
                : "";
        return card(
            '<h2>Erro operacional da Gaveta</h2><p class="muted">Não foi possível concluir a ação da Gaveta. O erro foi registrado no log técnico do sistema.' .
                $ref .
                "</p>",
            "finance-alert-card",
        );
    }
    return card(
        '<h2>Erro técnico da Gaveta</h2><p class="muted">Modo debug ativo. Copie todo o conteúdo abaixo para análise técnica.</p><textarea class="tech-debug-copy" rows="16" readonly onclick="this.select()">' .
            e($debug) .
            "</textarea>",
        "finance-alert-card",
    );
}
function financial_cash_debug_request_active(): bool
{

    if ((string) ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
        return false;
    }
    if (function_exists("route") && route() !== "financial") {
        return false;
    }
    $act = (string) ($_POST["act"] ?? "");
    return in_array(
        $act,
        [
            "cash_open",
            "cash_keep_closed",
            "cash_receipt",
            "cash_payment",
            "cash_close",
        ],
        true,
    );
}
function financial_cashier_pagehead_link(
    string $op,
    string $label,
    string $iconName,
    bool $enabled,
    string $active,
): string {

    $cls =
        "ghost small finance-cash-action cash-action-" .
        $op .
        ($active === $op ? " active" : "");
    $content = icon($iconName) . "<span>" . e($label) . "</span>";
    if ($enabled) {
        return '<a class="' .
            $cls .
            '" href="' .
            href("financial", ["op" => $op]) .
            '">' .
            $content .
            "</a>";
    }
    return '<span class="' .
        $cls .
        ' disabled" aria-disabled="true" tabindex="-1">' .
        $content .
        "</span>";
}
function financial_cashier_pagehead_actions(
    bool $canOpen,
    bool $canPayReceive,
    bool $canClose,
    string $active = "",
): string {

    return '<nav class="finance-pagehead-nav finance-cash-pagehead-actions" aria-label="Ações da Gaveta do Atendimento">' .
        financial_cashier_pagehead_link(
            "abrir",
            "Abrir Gaveta",
            "lock_open",
            $canOpen,
            $active,
        ) .
        financial_cashier_pagehead_link(
            "receber",
            "Recebi",
            "move_to_inbox",
            $canPayReceive,
            $active,
        ) .
        financial_cashier_pagehead_link(
            "pagar",
            "Paguei",
            "outbox",
            $canPayReceive,
            $active,
        ) .
        financial_cashier_pagehead_link(
            "fechar",
            "Fechar Gaveta",
            "lock",
            $canClose,
            $active,
        ) .
        "</nav>";
}
function financial_cashier_drawer_summary(
    ?array $session = null,
    ?array $prev = null,
    int $fallbackOpening = 0,
    ?array $drawer = null,
): string {

    $source = $prev ?: $session;
    $isOpen = $source && (string) ($source["status"] ?? "") === "open";
    $opening = $source
        ? (int) ($source["opening_balance_cents"] ?? 0)
        : max(0, $fallbackOpening);
    $receipts = 0;
    $payments = 0;
    $expected = $opening;
    if ($source) {
        $totals = financial_session_movement_totals(
            (int) $source["clinic_id"],
            (int) $source["id"],
        );
        $receipts = (int) $totals["receipt"];
        $payments = (int) $totals["payment"] + (int) $totals["refund"];
        if ((string) ($source["status"] ?? "") === "open") {
            $expected = financial_session_expected($source);
        } elseif (
            isset($source["expected_closing_cents"]) &&
            (int) $source["expected_closing_cents"] > 0
        ) {
            $expected = (int) $source["expected_closing_cents"];
        } else {
            $expected = $opening + $receipts - $payments;
        }
    }
    $rawStatus = $source ? (string) ($source["status"] ?? "") : "";
    $drawerName = trim((string) ($drawer["name"] ?? ""));
    $drawerLocked =
        $drawer &&
        (string) ($drawer["drawer_lock_status"] ?? "unlocked") === "locked";
    if ($drawerLocked) {
        $statusLabel = "Trancada";
        $statusTitle = "Trancada para conferência";
        $statusClass = "locked";
        $statusIcon = "lock_clock";
    } else {
        $statusLabel = $isOpen ? "Aberta" : "Fechada";
        $statusTitle = $isOpen
            ? "Aberta"
            : ($rawStatus === "opening_pending_review"
                ? "Aguardando autorização"
                : ($rawStatus === "opening_rejected"
                    ? "Abertura da Gaveta recusada"
                    : "Fechada"));
        $statusClass = $isOpen
            ? "open"
            : ($rawStatus === "opening_pending_review"
                ? "pending"
                : "closed");
        $statusIcon = $isOpen ? "currency_exchange" : "lock_clock";
    }
    $subtitle = $drawerName !== "" ? "<em>" . e($drawerName) . "</em>" : "";
    return card(
        '<div class="cash-drawer-line"><div class="cash-drawer-title"><span class="cash-drawer-status ' .
            $statusClass .
            '" title="Gaveta ' .
            $statusTitle .
            '">' .
            icon($statusIcon) .
            "</span><strong>Minha Gaveta</strong><small>" .
            e($statusLabel) .
            "</small>" .
            $subtitle .
            '</div><div class="cash-drawer-pills"><div class="cash-drawer-metric"><span>Saldo Inicial</span><b>' .
            money_br($opening) .
            '</b></div><div class="cash-drawer-metric"><span>Recebi</span><b>' .
            money_br($receipts) .
            '</b></div><div class="cash-drawer-metric"><span>Paguei</span><b>' .
            money_br($payments) .
            '</b></div><div class="cash-drawer-metric"><span>Esperado</span><b>' .
            money_br($expected) .
            "</b></div></div></div>",
        "finance-dashboard-card cash-drawer-card",
    );
}
function financial_cash_debug_failure_page(Throwable $e): void
{

    $ctx = [];
    $ctxErr = null;
    try {
        if (function_exists("ctx")) {
            $ctx = ctx();
        }
    } catch (Throwable $ce) {
        $ctxErr = $ce;
    }
    $debug = function_exists("financial_cash_exception_debug")
        ? financial_cash_exception_debug(
            $e,
            is_array($ctx) ? $ctx : [],
            (string) ($_POST["act"] ?? ""),
        )
        : $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    if ($ctxErr) {
        $debug .=
            PHP_EOL . PHP_EOL . "CTX_EXCEPTION_CLASS=" . get_class($ctxErr);
        $debug .= PHP_EOL . "CTX_EXCEPTION_MESSAGE=" . $ctxErr->getMessage();
        $debug .= PHP_EOL . "CTX_EXCEPTION_FILE=" . $ctxErr->getFile();
        $debug .= PHP_EOL . "CTX_EXCEPTION_LINE=" . $ctxErr->getLine();
    }
    error_log(
        "[Prontoo caixa atendimento] " . str_replace(PHP_EOL, " | ", $debug),
    );
    if (!headers_sent()) {
        http_response_code(
            $e instanceof ProntooHttpError ? (int) $e->status : 500,
        );
        header("Content-Type: text/html; charset=utf-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    }
    $version = defined("PRONTOO_VERSION") ? PRONTOO_VERSION : (string) time();
    $back = function_exists("href") ? href("financial") : "/?r=financial";
    if (!financial_cash_debug_details_enabled()) {
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Minha Gaveta · Prontoo</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#334155"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
            rawurlencode($version) .
            '"></head><body class="app"><main><section class="auth widebox finance-alert-card"><h1>Não foi possível concluir a ação da Gaveta</h1><p>O erro foi registrado no log técnico do sistema. Tente novamente após revisar a conexão e, se persistir, informe o horário da tentativa ao suporte.</p><p><a class="primary" href="' .
            e($back) .
            '">Voltar à Gaveta</a></p></section></main></body></html>';
        exit();
    }
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Erro técnico da Gaveta · Prontoo</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#334155"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
        rawurlencode($version) .
        '"></head><body class="app"><main><section class="auth widebox finance-alert-card"><h1>Erro técnico da Gaveta</h1><p>Modo debug ativo. Copie todo o conteúdo abaixo para análise técnica.</p><textarea class="tech-debug-copy" rows="22" readonly onclick="this.select()">' .
        e($debug) .
        '</textarea><p><a class="primary" href="' .
        e($back) .
        '">Voltar à Gaveta</a></p></section></main></body></html>';
    exit();
}
function financial_cashier_page(array $c): void
{

    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    $drawerId = 0;
    try {
        financial_operational_schema_ready();
        $drawerId = financial_cashier_location_for_user($cid, $uid);
        financial_ensure_admin_safe($cid, $uid);
    } catch (Throwable $e) {
        financial_cash_store_debug(
            $e,
            $c,
            (string) ($_POST["act"] ?? "preparo"),
        );
        $msg =
            "Não foi possível preparar sua Gaveta. O erro foi registrado no log técnico do sistema.";
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            flash($msg, "bad");
            redirect("financial");
        }
        page(
            "Caixa",
            page_head("Minha Gaveta", "") .
                financial_cash_debug_error_html() .
                card(
                    '<h2>Revisão da Gaveta necessária</h2><p class="muted">' .
                        e($msg) .
                        "</p>",
                    "finance-alert-card",
                ),
        );
        return;
    }
    if ($drawerId <= 0) {
        $body =
            financial_cash_debug_error_html() .
            card(
                '<h2>Você não é responsável por nenhuma gaveta ainda.</h2><p class="muted">Aguarde até que receba autorização para gerenciar gavetas.</p>',
                "finance-alert-card",
            );
        page("Caixa", page_head("Minha Gaveta", "") . $body);
        return;
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "");
        try {
            if ($act === "cash_open") {
                financial_open_session(
                    $cid,
                    $uid,
                    parse_money_cents(
                        (string) ($_POST["opening_balance"] ?? "0"),
                    ),
                );
                flash("Gaveta aberta para movimentação.");
                redirect("financial");
            }
            if ($act === "cash_keep_closed") {
                financial_keep_closed($cid, $uid);
                flash(
                    "Gaveta mantida fechada. Você pode abri-la mais tarde, se houver movimento.",
                );
                redirect("financial");
            }
            if ($act === "cash_receipt") {
                financial_receive_expected_appointment_revenue(
                    $cid,
                    $uid,
                    (int) ($_POST["revenue_id"] ?? 0),
                    (string) ($_POST["payment_method"] ?? ""),
                    (int) ($_POST["destination_location_id"] ?? 0),
                    trim((string) ($_POST["notes"] ?? "")),
                );
                $method = normalize_payment_method(
                    (string) ($_POST["payment_method"] ?? ""),
                );
                flash(
                    $method === "dinheiro"
                        ? "Valor recebido em dinheiro e guardado na sua Gaveta."
                        : "Valor recebido fora da Gaveta e enviado ao destino do Consultório.",
                );
                redirect("financial", ["op" => "receber"]);
            }
            if ($act === "cash_payment") {
                $s = financial_require_open_session($cid, $uid);
                $amount = parse_money_cents((string) ($_POST["amount"] ?? "0"));
                $title = trim(
                    (string) ($_POST["title"] ?? "Pagamento do Atendimento"),
                );
                financial_create_movement(
                    $cid,
                    "payment",
                    $amount,
                    (int) $s["location_id"],
                    null,
                    (int) $s["id"],
                    $uid,
                    $title,
                    "dinheiro",
                    trim((string) ($_POST["notes"] ?? "")),
                    "pending_review",
                    "cash_session",
                    (int) $s["id"],
                );
                flash(
                    "Pagamento em dinheiro registrado como saída da sua Gaveta para conferência.",
                );
                redirect("financial", ["op" => "pagar"]);
            }
            if ($act === "cash_close") {
                financial_close_session(
                    $cid,
                    $uid,
                    (int) ($_POST["session_id"] ?? 0),
                    parse_money_cents(
                        (string) ($_POST["declared_balance"] ?? "0"),
                    ),
                    parse_money_cents(
                        (string) ($_POST["withdrawal_amount"] ??
                            ($_POST["transfer_to_safe"] ?? "0")),
                    ),
                    (int) ($_POST["withdrawal_destination_location_id"] ?? 0),
                    trim((string) ($_POST["notes"] ?? "")),
                );
                flash("Gaveta enviada para conferência da Gerência.");
                redirect("financial");
            }
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (
                $act === "cash_open" &&
                str_starts_with($msg, "O Saldo Inicial informado não coincide")
            ) {
                flash($msg, "bad");
                redirect("financial");
            }
            financial_cash_store_debug($e, $c, $act);
            flash(
                "Não foi possível concluir a ação da Gaveta. O erro foi registrado no log técnico do sistema.",
                "bad",
            );
            redirect("financial");
        }
    }
    $today = financial_today($cid);
    $drawerState = financial_drawer_auto_unlock_if_due($cid, $drawerId);
    $drawerLocked =
        $drawerState &&
        (string) ($drawerState["drawer_lock_status"] ?? "unlocked") ===
            "locked";
    $prev = financial_unclosed_previous_session($cid, $uid, $today);
    $session = financial_session_for_date($cid, $uid, $today);
    $sessionStatus = $session ? (string) $session["status"] : "";
    $canOpen =
        !$drawerLocked &&
        !$prev &&
        (!$session ||
            in_array(
                $sessionStatus,
                ["kept_closed", "opening_rejected"],
                true,
            ));
    $canPayReceive = !$prev && $session && $sessionStatus === "open";
    $canClose = (bool) $prev || $canPayReceive;
    $op = (string) ($_GET["op"] ?? "");
    if (!in_array($op, ["abrir", "pagar", "receber", "fechar"], true)) {
        $op = "";
    }
    $preselectRevenue = (int) ($_GET["revenue_id"] ?? 0);
    $preselectAppointment = (int) ($_GET["appointment_id"] ?? 0);
    if ($op === "abrir" && !$canOpen) {
        $op = "";
    }
    if (($op === "pagar" || $op === "receber") && !$canPayReceive) {
        $op = "";
    }
    if ($op === "fechar" && !$canClose) {
        $op = "";
    }
    if (
        $op === "receber" &&
        $canPayReceive &&
        $preselectRevenue <= 0 &&
        $preselectAppointment > 0
    ) {
        $preselectRevenue = financial_revenue_id_for_appointment(
            $cid,
            $preselectAppointment,
            $uid,
        );
    }
    $actions = financial_cashier_pagehead_actions(
        $canOpen,
        $canPayReceive,
        $canClose,
        $op,
    );
    $fallbackOpening = 0;
    $body = financial_cash_debug_error_html();
    $body .= financial_cashier_drawer_summary(
        $session,
        $prev,
        $fallbackOpening,
        $drawerState ?: null,
    );
    if ($drawerLocked) {
        $unlock = trim((string) ($drawerState["drawer_unlock_at"] ?? ""));
        $lockedMsg =
            $unlock !== ""
                ? "A Gaveta está trancada para conferência da Gerência e será destrancada em <strong>" .
                    e(dt_br($unlock)) .
                    "</strong>."
                : "A Gaveta está trancada para conferência da Gerência. Aguarde o destravamento para abri-la.";
        $body .= card(
            '<h2>Gaveta trancada</h2><p class="muted">' . $lockedMsg . "</p>",
            "finance-alert-card",
        );
    }
    if ($drawerLocked && !$prev) {
    } elseif ($prev) {
        $expected = financial_session_expected($prev);
        if ($op === "fechar") {
            $withdrawalDestOptions = financial_office_destination_options(
                $cid,
                $uid,
            );
            $form =
                '<form method="post" class="compact finance-lite-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="cash_close"><input type="hidden" name="session_id" value="' .
                (int) $prev["id"] .
                '"><p class="muted">Existe uma Gaveta aberta em ' .
                date_br((string) $prev["business_date"]) .
                '. Feche esta Gaveta antes de abrir uma nova movimentação.</p><div class="three">' .
                form_row(
                    "Valor esperado",
                    '<strong class="finance-big-value">' .
                        money_br($expected) .
                        "</strong>",
                ) .
                form_row(
                    "Dinheiro contado",
                    financial_money_input(
                        "declared_balance",
                        money_br($expected),
                        "required",
                    ),
                ) .
                form_row(
                    "Fazer Retirada",
                    financial_money_input(
                        "withdrawal_amount",
                        money_br($expected),
                        "required",
                    ),
                ) .
                "</div>" .
                select_label(
                    "Destino da Retirada",
                    "withdrawal_destination_location_id",
                    $withdrawalDestOptions,
                    "",
                    "required",
                ) .
                form_row(
                    "Observação",
                    input(
                        "notes",
                        "text",
                        "",
                        'placeholder="Explique diferenças, se houver"',
                    ),
                ) .
                form_actions("Fechar Gaveta") .
                "</form>";
            $body .= card(
                "<h2>Fechar Gaveta</h2>" . $form,
                "finance-form finance-alert-card",
            );
        } else {
            $body .= card(
                '<h2>Gaveta anterior pendente</h2><p class="muted">Há uma Gaveta de ' .
                    date_br((string) $prev["business_date"]) .
                    " aberta. Use <strong>Fechar Gaveta</strong> no cabeçalho para regularizar antes de abrir ou movimentar a Gaveta de hoje.</p>",
                "finance-alert-card",
            );
        }
    } elseif ($session && $sessionStatus === "opening_pending_review") {
        $informed = (int) ($session["opening_balance_cents"] ?? 0);
        $body .= card(
            '<h2>Abertura da Gaveta aguardando autorização</h2><p class="muted">O valor informado não coincidiu com o controle da Gaveta. A Gerência já recebeu aviso para autorizar ou recusar a abertura.</p><div class="finance-mini-grid"><span>Informado <b>' .
                money_br($informed) .
                "</b></span><span>Situação <b>Aguardando autorização</b></span></div>",
            "finance-alert-card",
        );
    } elseif (
        !$session ||
        in_array($sessionStatus, ["kept_closed", "opening_rejected"], true)
    ) {
        $keptToday = $session && $sessionStatus === "kept_closed";
        $rejectedToday = $session && $sessionStatus === "opening_rejected";
        $suggest = $keptToday
            ? (int) ($session["opening_balance_cents"] ?? 0)
            : ($rejectedToday
                ? (int) ($session["expected_closing_cents"] ?? $fallbackOpening)
                : $fallbackOpening);
        if ($keptToday && $op !== "abrir") {
            $body .= card(
                '<h2>Gaveta mantida fechada</h2><p class="muted">A Gaveta permanece fechada. Se houver movimento hoje, use <strong>Abrir Gaveta</strong> no cabeçalho e informe o dinheiro encontrado fisicamente.</p>',
                "finance-dashboard-card",
            );
        }
        if ($rejectedToday && $op !== "abrir") {
            $body .= card(
                '<h2>Abertura da Gaveta recusada</h2><p class="muted">A Gerência recusou a abertura com valor diferente. Use <strong>Abrir Gaveta</strong> e informe novamente o valor encontrado fisicamente, ou solicite nova autorização.</p>',
                "finance-alert-card",
            );
        }
        if ($op === "abrir") {
            $openActions =
                '<div class="form-actions"><button type="submit" class="primary">' .
                icon("playlist_add_check") .
                "<span>Abrir Gaveta</span></button></div>";
            $openHint =
                "Conte o dinheiro físico da Gaveta e informe o valor encontrado. O sistema valida internamente se coincide com o último saldo não retirado, sem revelar o valor anterior.";
            $openForm =
                '<form method="post" class="compact finance-lite-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="cash_open"><p class="muted">' .
                $openHint .
                "</p>" .
                form_row(
                    "Dinheiro encontrado na Gaveta",
                    financial_money_input(
                        "opening_balance",
                        "",
                        'required placeholder="R$ 0,00"',
                    ),
                ) .
                $openActions .
                "</form>";
            $keepForm = "";
            if (!$keptToday) {
                $keepForm =
                    '<form method="post" class="compact finance-lite-form cash-secondary-form">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="cash_keep_closed"><p class="muted">Sem movimento agora? Mantenha a Gaveta fechada e abra mais tarde, se necessário.</p><div class="form-actions"><button type="submit" class="ghost">' .
                    icon("lock") .
                    "<span>Manter Gaveta fechada</span></button></div></form>";
            }
            $body .= card(
                "<h2>Abrir Gaveta</h2>" . $openForm . $keepForm,
                "finance-form",
            );
        } else {
            $body .= card(
                '<h2>Gaveta fechada</h2><p class="muted">Use <strong>Abrir Gaveta</strong> no cabeçalho para iniciar o uso da Gaveta no Atendimento.</p>',
                "finance-dashboard-card",
            );
        }
    } else {
        $expected =
            $sessionStatus === "open"
                ? financial_session_expected($session)
                : (int) ($session["declared_closing_cents"] ?? 0);
        if ($canPayReceive && $op === "receber") {
            $revenueOptions = financial_expected_appointment_revenue_options(
                $cid,
            );
            $destOptions = financial_office_destination_options($cid, $uid);
            $pendingPanel = financial_cashier_pending_receipts_html($cid);
            if (count($revenueOptions) <= 1) {
                $body .= card(
                    '<h2>Receber atendimento</h2><p class="muted">Quando o profissional concluir uma consulta com valor pendente, ela aparecerá aqui para baixa simples pela Recepção.</p>' .
                        $pendingPanel,
                    "finance-form",
                );
            } else {
                $receipt =
                    '<form method="post" class="compact finance-lite-form">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="cash_receipt"><p class="muted">Baixe aqui o valor de um atendimento. Dinheiro físico fica na sua Gaveta; PIX, cartão ou transferência seguem para o destino do Consultório e não entram na contagem da Gaveta.</p>' .
                    $pendingPanel .
                    select_label(
                        "Atendimento",
                        "revenue_id",
                        $revenueOptions,
                        $preselectRevenue ?: "",
                        "required",
                    ) .
                    '<div class="two">' .
                    select_label(
                        "Forma",
                        "payment_method",
                        financial_cashier_receipt_method_options(),
                        "dinheiro",
                        "required",
                    ) .
                    select_label(
                        "Destino se não for Dinheiro",
                        "destination_location_id",
                        $destOptions,
                        "",
                    ) .
                    "</div>" .
                    form_row(
                        "Observação",
                        input("notes", "text", "", 'placeholder="Opcional"'),
                    ) .
                    form_actions("Confirmar recebimento") .
                    "</form>";
                $body .= card(
                    "<h2>Receber atendimento</h2>" . $receipt,
                    "finance-form",
                );
            }
        }
        if ($canPayReceive && $op === "pagar") {
            $payment =
                '<form method="post" class="compact finance-lite-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="cash_payment"><input type="hidden" name="payment_method" value="dinheiro"><p class="muted">Use esta ação somente para pagamento autorizado em dinheiro. O valor sai fisicamente da sua Gaveta e compõe a conferência do fechamento.</p><div class="two">' .
                form_row(
                    "Descrição",
                    input(
                        "title",
                        "text",
                        "",
                        'required placeholder="Ex.: devolução, pagamento autorizado"',
                    ),
                ) .
                form_row(
                    "Valor",
                    financial_money_input(
                        "amount",
                        "",
                        'required placeholder="R$ 0,00"',
                    ),
                ) .
                '</div><div class="two">' .
                form_row(
                    "Forma",
                    '<strong class="finance-big-value">Dinheiro</strong>',
                ) .
                form_row(
                    "Observação",
                    input(
                        "notes",
                        "text",
                        "",
                        'required placeholder="Obrigatório para pagamentos"',
                    ),
                ) .
                "</div>" .
                form_actions("Confirmar pagamento", "danger") .
                "</form>";
            $body .= card("<h2>Paguei</h2>" . $payment, "finance-form");
        }
        if ($canClose && $op === "fechar") {
            $withdrawalDestOptions = financial_office_destination_options(
                $cid,
                $uid,
            );
            $close =
                '<form method="post" class="compact finance-lite-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="cash_close"><input type="hidden" name="session_id" value="' .
                (int) $session["id"] .
                '"><div class="three">' .
                form_row(
                    "Esperado",
                    '<strong class="finance-big-value">' .
                        money_br($expected) .
                        "</strong>",
                ) .
                form_row(
                    "Dinheiro contado",
                    financial_money_input(
                        "declared_balance",
                        money_br($expected),
                        "required",
                    ),
                ) .
                form_row(
                    "Fazer Retirada",
                    financial_money_input(
                        "withdrawal_amount",
                        money_br($expected),
                        "required",
                    ),
                ) .
                "</div>" .
                select_label(
                    "Destino da Retirada",
                    "withdrawal_destination_location_id",
                    $withdrawalDestOptions,
                    "",
                    "required",
                ) .
                form_row(
                    "Observação da Gaveta",
                    input(
                        "notes",
                        "text",
                        "",
                        'placeholder="Informe diferenças ou deixe em branco"',
                    ),
                ) .
                form_actions("Fechar Gaveta") .
                "</form>";
            $body .= card("<h2>Fechar Gaveta</h2>" . $close, "finance-form");
        }
        if ($sessionStatus !== "open") {
            $body .= card(
                '<h2>Gaveta fechada</h2><p class="muted">A Gaveta de hoje já foi fechada ou ainda está em conferência.</p>',
                "finance-dashboard-card",
            );
        }
    }
    $extractSession = $prev ?: $session;
    $list = "";
    if ($extractSession) {
        $extractDate = (string) ($extractSession["business_date"] ?? $today);
        $rows = q(
            "SELECT m.movement_type,m.cash_session_id,m.source_entity,m.status,m.title,m.created_at,m.payment_method,m.amount_cents,lt.name to_name FROM pi_financial_movements m LEFT JOIN pi_financial_locations lt ON lt.id=m.to_location_id AND lt.clinic_id=m.clinic_id WHERE m.clinic_id=? AND (m.cash_session_id=? OR (m.cash_session_id IS NULL AND m.created_by=? AND DATE(m.created_at)=? AND m.movement_type='receipt' AND m.source_entity='appointment')) ORDER BY m.created_at DESC,m.id DESC LIMIT 100",
            [$cid, (int) $extractSession["id"], $uid, $extractDate],
        )->fetchAll();
        foreach ($rows as $r) {
            $type = (string) $r["movement_type"];
            $outside = (int) ($r["cash_session_id"] ?? 0) <= 0;
            $place = $outside
                ? " · fora da gaveta" .
                    (!empty($r["to_name"])
                        ? " · " . (string) $r["to_name"]
                        : "")
                : "";
            $isWithdrawal =
                $type === "transfer" &&
                (string) ($r["source_entity"] ?? "") === "cash_session";
            $iconClass = $isWithdrawal
                ? "withdrawal"
                : (in_array($type, ["receipt", "payment"], true)
                    ? $type
                    : "other");
            $rowClass = $isWithdrawal
                ? "withdrawal"
                : (in_array($type, ["receipt", "payment"], true)
                    ? $type
                    : "other");
            $movementLabel = $isWithdrawal
                ? "Retirada"
                : financial_human_movement_type($type);
            $movementIcon = $isWithdrawal
                ? "move_up"
                : financial_movement_icon($type);
            $status = (string) $r["status"];
            $statusHtml =
                $status === "confirmed"
                    ? '<span class="pill ok status-icon-only" title="Confirmado" aria-label="Confirmado">' .
                        icon("check_circle") .
                        "</span>"
                    : '<span class="pill ' .
                        ($status === "pending_review" ? "warn" : "ok") .
                        '">' .
                        e(financial_human_movement_status($status)) .
                        "</span>";
            $list .=
                '<article class="finance-row finance-extract-row finance-extract-' .
                $rowClass .
                '"><span class="finance-movement-icon ' .
                $iconClass .
                '" aria-hidden="true">' .
                icon($movementIcon) .
                "</span><div><strong>" .
                e($movementLabel . " · " . (string) $r["title"]) .
                "</strong><small>" .
                dt_br((string) $r["created_at"]) .
                " · " .
                e((string) ($r["payment_method"] ?: "sem forma") . $place) .
                "</small></div><b>" .
                money_br((int) $r["amount_cents"]) .
                "</b>" .
                $statusHtml .
                "</article>";
        }
    }
    $body .= card(
        "<h2>Movimento da Gaveta</h2>" .
            ($list ?:
                '<div class="empty">Nenhum movimento registrado na Gaveta hoje.</div>'),
        "finance-list",
    );
    page("Caixa", page_head("Minha Gaveta", "", $actions) . $body);
}
function financial_admin_drawers_panel(int $cid, int $uid): string
{

    $drawerOptions = financial_drawer_location_options($cid, true);
    $cashierOptions = financial_cashier_user_options($cid, true);
    $create =
        '<form method="post" class="compact finance-lite-form finance-drawer-create-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="drawer_create"><div class="two">' .
        form_row(
            "Nome da Gaveta",
            input(
                "drawer_name",
                "text",
                "",
                'required placeholder="Ex.: Gaveta Recepção 1"',
            ),
        ) .
        form_row(
            "Uso",
            '<input type="text" value="Dinheiro do Caixa do Atendimento" readonly>',
        ) .
        "</div>" .
        form_actions("Criar Gaveta") .
        "</form>";
    $assign =
        '<form method="post" class="compact finance-lite-form finance-drawer-assign-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="drawer_assign"><div class="two">' .
        select_label("Gaveta", "drawer_id", $drawerOptions, "", "required") .
        select_label(
            "Colaborador do Atendimento",
            "cashier_user_id",
            $cashierOptions,
            "",
            "required",
        ) .
        '</div><p class="muted">Mais de um colaborador pode estar vinculado à mesma Gaveta, mas ela só pode ficar aberta por um colaborador por vez.</p>' .
        form_actions("Vincular colaborador") .
        "</form>";
    $today = financial_today($cid);
    try {
        $yesterday = new DateTimeImmutable($today)
            ->modify("-1 day")
            ->format("Y-m-d");
    } catch (Throwable $e) {
        $yesterday = date("Y-m-d", strtotime("-1 day"));
    }
    $drawers = q(
        "SELECT id,name,drawer_lock_status,drawer_locked_business_date,drawer_unlock_at FROM pi_financial_locations WHERE clinic_id=? AND location_type='pos' AND active=1 ORDER BY name,id LIMIT 200",
        [$cid],
    )->fetchAll();
    $drawerIds = array_values(
        array_filter(
            array_map(
                static  fn(array $drawer): int => (int) ($drawer["id"] ?? 0),
                $drawers,
            ),
        ),
    );
    $linksByDrawer = [];
    if ($drawerIds) {
        $drawerPh = implode(",", array_fill(0, count($drawerIds), "?"));
        $linkRows = q(
            "SELECT id,location_id,name FROM (SELECT lu.id,lu.location_id,u.name,ROW_NUMBER() OVER (PARTITION BY lu.location_id ORDER BY u.name,lu.id) row_rank FROM pi_financial_location_users lu JOIN pi_users u ON u.id=lu.user_id WHERE lu.clinic_id=? AND lu.location_id IN ($drawerPh) AND lu.active=1) ranked WHERE row_rank<=80 ORDER BY location_id,name,id",
            array_merge([$cid], $drawerIds),
        )->fetchAll();
        foreach ($linkRows as $link) {
            $linksByDrawer[(int) $link["location_id"]][] = $link;
        }
    }
    $dailyTotals = financial_drawer_daily_totals_map(
        $cid,
        $drawerIds,
        [$today, $yesterday],
    );
    $balanceSnapshot = financial_drawer_balance_snapshot($cid, $drawerIds);
    $balances = (array) ($balanceSnapshot["balances"] ?? []);
    $openByDrawer = (array) ($balanceSnapshot["open"] ?? []);
    $list = "";
    foreach ($drawers as $d) {
        $did = (int) $d["id"];
        $name = (string) $d["name"];
        $links = $linksByDrawer[$did] ?? [];
        $linkHtml = "";
        foreach ($links as $l) {
            $remove = "";
            if ((int) ($l["id"] ?? 0) > 0) {
                $remove =
                    '<form method="post" class="inline finance-chip-action">' .
                    csrf_field() .
                    '<input type="hidden" name="act" value="drawer_unassign"><input type="hidden" name="link_id" value="' .
                    (int) $l["id"] .
                    '"><button class="ghost small icon-only" type="submit" title="Remover vínculo" aria-label="Remover vínculo">' .
                    icon("close") .
                    "</button></form>";
            }
            $linkHtml .=
                '<span class="pill finance-drawer-user-chip">' .
                icon("badge") .
                "<span>" .
                e(first_name((string) $l["name"])) .
                "</span>" .
                $remove .
                "</span>";
        }
        if ($linkHtml === "") {
            $linkHtml =
                '<span class="pill warn">Sem colaborador vinculado</span>';
        }
        $open = $openByDrawer[$did] ?? null;
        $drawerState = financial_drawer_auto_unlock_row_if_due($cid, $d);
        $locked =
            (string) ($drawerState["drawer_lock_status"] ?? "unlocked") ===
            "locked";
        if ($open) {
            $status =
                '<span class="pill warn">Aberta por ' .
                e(first_name((string) ($open["user_name"] ?? "Atendimento"))) .
                "</span>";
        } elseif ($locked) {
            $unlock = trim((string) ($drawerState["drawer_unlock_at"] ?? ""));
            $status =
                '<span class="pill bad">' .
                icon("lock") .
                " " .
                ($unlock !== ""
                    ? "Trancada até " . e(app_time_br($unlock, $cid))
                    : "Trancada") .
                "</span>";
        } else {
            $status =
                '<span class="pill ok">' .
                icon("lock_open") .
                " Destrancada</span>";
        }
        $todayTotals =
            $dailyTotals[$did . "|" . $today] ??
            financial_drawer_daily_totals_empty();
        $yTotals =
            $dailyTotals[$did . "|" . $yesterday] ??
            financial_drawer_daily_totals_empty();
        $balance = (int) ($balances[$did] ?? 0);
        $metrics =
            '<div class="finance-drawer-metrics">' .
            '<span class="finance-drawer-metric"><small>Sessões hoje</small><b>' .
            (int) $todayTotals["sessions"] .
            "</b></span>" .
            '<span class="finance-drawer-metric"><small>Recebi</small><b>' .
            money_br((int) $todayTotals["receipts"]) .
            "</b></span>" .
            '<span class="finance-drawer-metric"><small>Paguei</small><b>' .
            money_br((int) $todayTotals["payments"]) .
            "</b></span>" .
            '<span class="finance-drawer-metric"><small>Retiradas</small><b>' .
            money_br((int) $todayTotals["withdrawn"]) .
            "</b></span>" .
            '<span class="finance-drawer-metric"><small>Saldo atual</small><b>' .
            money_br($balance) .
            "</b></span>" .
            '<span class="finance-drawer-metric"><small>Ontem</small><b>' .
            money_br((int) $yTotals["kept"]) .
            "</b></span>" .
            "</div>";
        $schedule = "";
        if ($locked) {
            $schedule =
                '<form method="post" class="compact finance-drawer-unlock-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="drawer_schedule_unlock"><input type="hidden" name="drawer_id" value="' .
                $did .
                '">' .
                form_row(
                    "Destravar em",
                    input(
                        "drawer_unlock_at",
                        "datetime-local",
                        financial_default_drawer_unlock_local(
                            $cid,
                            $did,
                            $drawerState,
                        ),
                        "required",
                    ),
                ) .
                form_row(
                    "Conferência / destino das retiradas",
                    input(
                        "notes",
                        "text",
                        "",
                        'placeholder="Ex.: retirada enviada ao cofre / banco"',
                    ),
                ) .
                '<button class="primary small" type="submit">' .
                icon("lock_clock") .
                "<span>Agendar destravamento</span></button></form>";
        }
        $rename =
            '<form method="post" class="compact finance-drawer-rename-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="drawer_rename"><input type="hidden" name="drawer_id" value="' .
            $did .
            '">' .
            form_row(
                "Novo nome",
                input("drawer_name", "text", $name, 'required maxlength="120"'),
            ) .
            '<button class="primary small" type="submit">' .
            icon("save") .
            "<span>Salvar nome</span></button></form>";
        $deactivate =
            '<form method="post" class="compact finance-drawer-danger-form" onsubmit="return confirm(&quot;Desativar esta Gaveta? Os históricos serão preservados.&quot;)">' .
            csrf_field() .
            '<input type="hidden" name="act" value="drawer_deactivate"><input type="hidden" name="drawer_id" value="' .
            $did .
            '"><button class="danger small" type="submit">' .
            icon("inventory_2") .
            "<span>Desativar Gaveta</span></button></form>";
        $actions =
            '<details class="finance-drawer-actions"><summary class="ghost small">' .
            icon("more_horiz") .
            "<span>Ações</span></summary><div>" .
            $schedule .
            $rename .
            $deactivate .
            "</div></details>";
        $lockInfo = "";
        if ($locked) {
            $unlock = trim((string) ($drawerState["drawer_unlock_at"] ?? ""));
            $lockInfo =
                " · trancada desde " .
                date_br(
                    (string) ($drawerState["drawer_locked_business_date"] ??
                        ""),
                ) .
                ($unlock !== ""
                    ? " · destrava em " . dt_br($unlock)
                    : " · aguardando conferência");
        }
        $list .=
            '<article class="finance-drawer-admin-card' .
            ($locked ? " drawer-locked" : "") .
            '"><header><div class="finance-drawer-heading"><span class="finance-drawer-avatar">' .
            icon($locked ? "lock" : "point_of_sale") .
            "</span><div><strong>" .
            e($name) .
            '</strong><small>Gaveta física do Caixa do Atendimento</small></div></div><div class="finance-drawer-card-actions"><b>' .
            money_br($balance) .
            "</b>" .
            $status .
            $actions .
            "</div></header>" .
            $metrics .
            '<section class="finance-drawer-users"><small>Colaboradores vinculados</small><div class="finance-inline-pills">' .
            $linkHtml .
            "</div></section><footer><span>Dia anterior: sessões " .
            (int) $yTotals["sessions"] .
            " · pendentes " .
            (int) $yTotals["pending_count"] .
            " · saldo final " .
            money_br((int) $yTotals["kept"]) .
            e($lockInfo) .
            "</span></footer></article>";
    }
    if ($list === "") {
        $list =
            '<div class="empty">Nenhuma Gaveta criada. Crie pelo menos uma e vincule os colaboradores do Atendimento.</div>';
    }
    $setup =
        '<div class="finance-drawer-setup-grid">' .
        card(
            '<h2>Criar Gaveta</h2><p class="muted">Cadastre cada gaveta física usada para guardar dinheiro do Caixa do Atendimento.</p>' .
                $create,
            "finance-form finance-drawer-setup-card",
        ) .
        card(
            "<h2>Vincular colaborador</h2>" . $assign,
            "finance-form finance-drawer-setup-card",
        ) .
        "</div>";
    return card(
        '<h2>Gavetas do Atendimento</h2><p class="muted">Gerencie nomes, vínculos e status das Gavetas. Vínculos e histórico permanecem atrelados ao ID da Gaveta, mesmo após renomear.</p>',
        "finance-report-card finance-drawer-intro",
    ) .
        $setup .
        card(
            '<h2>Controle das Gavetas</h2><div class="finance-drawer-admin-grid">' .
                $list .
                "</div>",
            "finance-list finance-drawer-admin-list",
        );
}
function financial_admin_daily_ledger_timeline(int $cid): string
{

    $day = financial_today($cid);
    [$startUtc, $endUtc] = app_local_day_utc_range($day, $cid);
    try {
        $rows = q(
            "SELECT m.movement_type,m.status,m.payment_method,m.created_at,m.title,m.amount_cents,lf.name from_name,lt.name to_name,u.name user_name FROM pi_financial_movements m LEFT JOIN pi_financial_locations lf ON lf.id=m.from_location_id AND lf.clinic_id=m.clinic_id LEFT JOIN pi_financial_locations lt ON lt.id=m.to_location_id AND lt.clinic_id=m.clinic_id LEFT JOIN pi_users u ON u.id=m.created_by WHERE m.clinic_id=? AND m.created_at>=? AND m.created_at<? AND m.status IN ('confirmed','pending_review') ORDER BY m.created_at ASC,m.id ASC LIMIT 240",
            [$cid, $startUtc, $endUtc],
        )->fetchAll();
    } catch (Throwable $e) {
        error_log("[Prontoo financeiro razonete diário] " . $e->getMessage());
        return '<div class="empty">Não foi possível carregar as movimentações do dia.</div>';
    }
    if (!$rows) {
        return '<div class="empty">Nenhuma movimentação financeira registrada hoje.</div>';
    }
    $items = [];
    foreach ($rows as $r) {
        $type = (string) ($r["movement_type"] ?? "");
        $status = (string) ($r["status"] ?? "");
        $from = trim((string) ($r["from_name"] ?? ""));
        $to = trim((string) ($r["to_name"] ?? ""));
        $path =
            ($from !== "" ? $from : "Origem externa") .
            " → " .
            ($to !== "" ? $to : "Destino externo");
        $kind = "other";
        if ($type === "receipt") {
            $kind = "receipt";
        } elseif ($type === "payment") {
            $kind = "payment";
        } elseif (
            in_array($type, ["transfer", "deposit", "cash_closing"], true)
        ) {
            $kind = "transfer";
        }
        $method = trim((string) ($r["payment_method"] ?? ""));
        $user = first_name((string) ($r["user_name"] ?? ""));
        $statusPill =
            $status === "confirmed"
                ? '<span class="pill icon-only ok" title="Confirmado" aria-label="Confirmado">' .
                    icon("check_circle") .
                    "</span>"
                : '<span class="pill warn">' .
                    e(financial_human_movement_status($status)) .
                    "</span>";
        $items[] = [
            "icon" => financial_movement_icon($type),
            "class" => "finance-ledger-entry finance-ledger-" . $kind,
            "time" => app_time_br((string) ($r["created_at"] ?? ""), $cid),
            "title" =>
                financial_human_movement_type($type) .
                " · " .
                ((string) ($r["title"] ?? "Movimentação")),
            "body" => $path,
            "meta" =>
                ($user !== "" ? "Registrado por " . $user . " · " : "") .
                ($method !== ""
                    ? ucfirst(str_replace("_", " ", $method))
                    : "Sem forma informada"),
            "html" =>
                '<div class="finance-ledger-activity-pills"><span class="pill">' .
                money_br((int) ($r["amount_cents"] ?? 0)) .
                "</span>" .
                $statusPill .
                "</div>",
        ];
    }
    return '<div class="activity-timeline finance-ledger-activity">' .
        timeline($items, "Nenhuma movimentação financeira registrada hoje.") .
        "</div>";
}
function financial_admin_reviews_panel(int $cid, int $uid): string
{

    $openRows = q(
        "SELECT s.id,s.status,s.business_date,s.opening_balance_cents,s.expected_closing_cents,u.name user_name,l.name location_name FROM pi_cash_sessions s JOIN pi_users u ON u.id=s.user_id LEFT JOIN pi_financial_locations l ON l.id=s.location_id AND l.clinic_id=s.clinic_id WHERE s.clinic_id=? AND s.status IN ('opening_pending_review','opening_rejected') ORDER BY FIELD(s.status,'opening_pending_review','opening_rejected'), s.business_date DESC,s.id DESC LIMIT 80",
        [$cid],
    )->fetchAll();
    $openList = "";
    foreach ($openRows as $s) {
        $expected = (int) ($s["expected_closing_cents"] ?? 0);
        $informed = (int) ($s["opening_balance_cents"] ?? 0);
        $diff = $informed - $expected;
        $form = "";
        if ((string) $s["status"] === "opening_pending_review") {
            $form =
                '<form method="post" class="finance-inline-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="review_opening"><input type="hidden" name="session_id" value="' .
                (int) $s["id"] .
                '"><input type="hidden" name="decision" value="approve">' .
                input(
                    "notes",
                    "text",
                    "",
                    'placeholder="Observação opcional"',
                ) .
                '<button type="submit" class="primary small">' .
                icon("verified") .
                '<span>Autorizar</span></button></form><form method="post" class="finance-inline-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="review_opening"><input type="hidden" name="session_id" value="' .
                (int) $s["id"] .
                '"><input type="hidden" name="decision" value="reject">' .
                input(
                    "notes",
                    "text",
                    "",
                    'required placeholder="Motivo da recusa"',
                ) .
                '<button type="submit" class="danger small">' .
                icon("block") .
                "<span>Recusar</span></button></form>";
        }
        $openList .=
            '<article class="finance-row finance-action-row"><div><strong>' .
            e(first_name((string) $s["user_name"])) .
            " · " .
            e((string) ($s["location_name"] ?? "Gaveta")) .
            " · " .
            date_br((string) $s["business_date"]) .
            "</strong><small>Esperado " .
            money_br($expected) .
            " · Saldo informado " .
            money_br($informed) .
            " · Diferença " .
            money_br($diff) .
            "</small></div><b>" .
            money_br($informed) .
            '</b><span class="pill ' .
            ((string) $s["status"] === "opening_pending_review"
                ? "warn"
                : "bad") .
            '">' .
            e(financial_human_session_status((string) $s["status"])) .
            "</span>" .
            $form .
            "</article>";
    }
    $rows = q(
        "SELECT s.id,s.location_id,s.status,s.business_date,s.expected_closing_cents,s.declared_closing_cents,s.difference_cents,s.transfer_to_safe_cents,u.name user_name,l.name location_name,l.drawer_locked_business_date FROM pi_cash_sessions s JOIN pi_users u ON u.id=s.user_id LEFT JOIN pi_financial_locations l ON l.id=s.location_id AND l.clinic_id=s.clinic_id WHERE s.clinic_id=? AND s.status IN ('closed_pending_review','approved','rejected') ORDER BY FIELD(s.status,'closed_pending_review','rejected','approved'), s.business_date DESC,s.id DESC LIMIT 120",
        [$cid],
    )->fetchAll();
    $list = "";
    foreach ($rows as $s) {
        $diff = (int) $s["difference_cents"];
        $form = "";
        if ((string) $s["status"] === "closed_pending_review") {
            $unlockDefault = financial_default_drawer_unlock_local(
                $cid,
                (int) $s["location_id"],
                [
                    "drawer_locked_business_date" =>
                        $s["drawer_locked_business_date"] ?? null,
                ],
            );
            $form =
                '<form method="post" class="finance-inline-form finance-drawer-review-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                (int) $s["id"] .
                '"><input type="hidden" name="decision" value="approve">' .
                input(
                    "notes",
                    "text",
                    "",
                    'placeholder="Confirme a destinação das retiradas / observação"',
                ) .
                input(
                    "drawer_unlock_at",
                    "datetime-local",
                    $unlockDefault,
                    'required title="Horário de destravamento da Gaveta"',
                ) .
                '<button type="submit" class="primary small">' .
                icon("lock_clock") .
                '<span>Conferir e agendar</span></button></form><form method="post" class="finance-inline-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                (int) $s["id"] .
                '"><input type="hidden" name="decision" value="reject">' .
                input(
                    "notes",
                    "text",
                    "",
                    'required placeholder="Motivo da devolução"',
                ) .
                '<button type="submit" class="danger small">' .
                icon("undo") .
                "<span>Devolver</span></button></form>";
        }
        $list .=
            '<article class="finance-row finance-action-row"><div><strong>' .
            e(first_name((string) $s["user_name"])) .
            " · " .
            e((string) ($s["location_name"] ?? "Gaveta")) .
            " · " .
            date_br((string) $s["business_date"]) .
            "</strong><small>Esperado " .
            money_br((int) $s["expected_closing_cents"]) .
            " · Declarado " .
            money_br((int) $s["declared_closing_cents"]) .
            " · Diferença " .
            money_br($diff) .
            "</small></div><b>" .
            money_br((int) $s["transfer_to_safe_cents"]) .
            '</b><span class="pill ' .
            ((string) $s["status"] === "closed_pending_review"
                ? "warn"
                : "ok") .
            '">' .
            e(financial_human_session_status((string) $s["status"])) .
            "</span>" .
            $form .
            "</article>";
    }
    return card(
        '<h2>Autorizações de Abertura</h2><p class="muted">Autorize apenas quando o Atendimento justificar Saldo Inicial diferente do saldo não retirado no último fechamento.</p>' .
            ($openList ?:
                '<div class="empty">Nenhuma abertura divergente aguardando autorização.</div>'),
        "finance-list",
    ) .
        card(
            '<h2>Fechamentos do Atendimento</h2><p class="muted">Conferir confirma a destinação das retiradas e agenda o horário de destravamento da Gaveta para o dia seguinte. Devolver mantém a Gaveta trancada.</p>' .
                ($list ?:
                    '<div class="empty">Nenhum fechamento pendente.</div>'),
            "finance-list",
        );
}
function financial_location_account_id(int $cid, int $locationId): ?int
{

    if ($cid <= 0 || $locationId <= 0) {
        return null;
    }
    try {
        $r = one(
            "SELECT account_id FROM pi_financial_locations WHERE id=? AND clinic_id=? AND active=1 LIMIT 1",
            [$locationId, $cid],
        );
        $acc = (int) ($r["account_id"] ?? 0);
        return $acc > 0 ? $acc : null;
    } catch (Throwable $e) {
        return null;
    }
}
function financial_admin_balance_kpis_html(array $pos): string
{

    return '<div class="kpis finance-kpis finance-balance-kpis" aria-label="Saldos financeiros do consultório"><article class="finance-balance-card finance-balance-total">' .
        icon("savings") .
        "<p><b>" .
        money_br((int) $pos["total_cents"]) .
        '</b><span>Total disponível</span></p></article><article class="finance-balance-card finance-balance-drawers">' .
        icon("point_of_sale") .
        "<p><b>" .
        money_br((int) $pos["pos_cents"]) .
        '</b><span>Gavetas</span></p></article><article class="finance-balance-card finance-balance-safes">' .
        icon("account_balance_wallet") .
        "<p><b>" .
        money_br((int) $pos["safe_cents"]) .
        '</b><span>Cofre</span></p></article><article class="finance-balance-card finance-balance-banks">' .
        icon("account_balance") .
        "<p><b>" .
        money_br((int) $pos["bank_cents"]) .
        "</b><span>Bancos</span></p></article></div>";
}
function financial_admin_locations_panel(int $cid, int $uid, array $pos): string
{

    $safe = (int) $pos["safe_id"];
    $safeCard = card(
        "<h2>" .
            icon("account_balance_wallet") .
            '<span>Cofre</span></h2><p class="muted">Local de guarda do Consultório antes de levar valores ao banco. Saldo atual: <strong>' .
            money_br((int) $pos["safe_cents"]) .
            "</strong>.</p>",
        "finance-location-card finance-safe-card",
    );
    $banks = q(
        "SELECT a.id,a.name,a.bank_name,l.id location_id FROM pi_financial_accounts a LEFT JOIN pi_financial_locations l ON l.account_id=a.id AND l.clinic_id=a.clinic_id AND l.location_type='bank_account' WHERE a.clinic_id=? AND a.active=1 AND a.account_type IN ('conta_corrente','conta_poupanca','conta_pagamento','investimento') ORDER BY a.name",
        [$cid],
    )->fetchAll();
    $opts = ["" => "Escolha a conta"];
    $resolvedBanks = [];
    foreach ($banks as $b) {
        $loc =
            (int) ($b["location_id"] ?:
            financial_ensure_bank_location($cid, (int) $b["id"], $uid));
        $opts[(int) $b["id"]] = (string) $b["name"];
        $b["resolved_location_id"] = $loc;
        $resolvedBanks[] = $b;
    }
    $bankBalances = financial_location_movement_balances(
        $cid,
        array_map(
            static  fn(array $bank): int =>
                (int) ($bank["resolved_location_id"] ?? 0),
            $resolvedBanks,
        ),
    );
    $cards = '<div class="finance-account-cards">';
    foreach ($resolvedBanks as $b) {
        $bal = (int) (
            $bankBalances[(int) ($b["resolved_location_id"] ?? 0)] ?? 0
        );
        $cards .=
            "<article><span>" .
            icon("account_balance") .
            "</span><div><h3>" .
            e((string) $b["name"]) .
            "</h3><p>" .
            e((string) ($b["bank_name"] ?: "Conta Bancária")) .
            "</p></div><b>" .
            money_br($bal) .
            "</b></article>";
    }
    $cards .= $resolvedBanks
        ? "</div>"
        : '<div class="empty">Nenhuma conta bancária cadastrada. O Consultório pode funcionar com Gavetas e Cofre.</div>';
    $new =
        '<form method="post" class="compact finance-lite-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="bank_account"><div class="two">' .
        form_row(
            "Nome da conta",
            input(
                "name",
                "text",
                "",
                'required placeholder="Ex.: Sicredi, Banco do Brasil"',
            ),
        ) .
        form_row(
            "Banco",
            input("bank_name", "text", "", 'placeholder="Opcional"'),
        ) .
        "</div>" .
        form_actions("Cadastrar banco") .
        "</form>";
    $dep =
        '<form method="post" class="compact finance-lite-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="deposit_bank"><div class="three">' .
        select_label("Banco de destino", "account_id", $opts, "", "required") .
        form_row("Valor", financial_money_input("amount", "", "required")) .
        form_row(
            "Observação",
            input("notes", "text", "", 'placeholder="Opcional"'),
        ) .
        "</div>" .
        form_actions("Depositar do Cofre") .
        "</form>";
    $banksPanel =
        card(
            "<h2>" .
                icon("account_balance") .
                "<span>Bancos</span></h2>" .
                $cards,
            "finance-accounts-panel",
        ) .
        '<div class="finance-form-stack finance-bank-actions">' .
        card(
            "<h2>" .
                icon("add_business") .
                '<span>Novo banco</span></h2><p class="muted">Cadastre apenas locais reais de destino para valores do Consultório.</p>' .
                $new,
            "finance-form finance-context-form",
        ) .
        card(
            "<h2>" .
                icon("move_down") .
                '<span>Depósito do Cofre</span></h2><p class="muted">Move valor do Cofre para um Banco do Consultório.</p>' .
                $dep,
            "finance-form finance-context-form",
        ) .
        "</div>";
    return '<div class="finance-workspace finance-locations-workspace">' .
        financial_admin_drawers_panel($cid, $uid) .
        financial_admin_reviews_panel($cid, $uid) .
        $safeCard .
        $banksPanel .
        "</div>";
}
function financial_admin_movements_panel(int $cid, int $limit = 180): string
{

    $rows = q(
        "SELECT m.movement_type,m.title,m.created_at,m.amount_cents,m.status,lf.name from_name,lt.name to_name,u.name user_name FROM pi_financial_movements m LEFT JOIN pi_financial_locations lf ON lf.id=m.from_location_id AND lf.clinic_id=m.clinic_id LEFT JOIN pi_financial_locations lt ON lt.id=m.to_location_id AND lt.clinic_id=m.clinic_id LEFT JOIN pi_users u ON u.id=m.created_by WHERE m.clinic_id=? ORDER BY m.created_at DESC,m.id DESC LIMIT " .
            max(10, min(300, $limit)),
        [$cid],
    )->fetchAll();
    $list = "";
    foreach ($rows as $r) {
        $type = (string) $r["movement_type"];
        $path = trim(
            (string) ($r["from_name"] ?? "") .
                " → " .
                (string) ($r["to_name"] ?? ""),
            " →",
        );
        $list .=
            '<article class="finance-row"><div><strong>' .
            e(
                financial_human_movement_type($type) .
                    " · " .
                    (string) $r["title"],
            ) .
            "</strong><small>" .
            dt_br((string) $r["created_at"]) .
            " · " .
            e($path ?: "sem local") .
            " · " .
            e(first_name((string) ($r["user_name"] ?? ""))) .
            "</small></div><b>" .
            money_br((int) $r["amount_cents"]) .
            '</b><span class="pill">' .
            e(financial_human_movement_status((string) $r["status"])) .
            "</span></article>";
    }
    return card(
        '<h2>Movimentos</h2><p class="muted">Registro simples das entradas, saídas e transferências do Consultório.</p>' .
            ($list ?:
                '<div class="empty">Nenhuma movimentação registrada.</div>'),
        "finance-list finance-ledger-card",
    );
}
function financial_daily_drawer_partials_html(
    int $cid,
    int $uid,
    array $state,
): string {

    $rows = $state["rows"] ?? [];
    if (!$rows) {
        return '<div class="empty">Nenhuma gaveta vinculada a colaborador para esta data.</div>';
    }
    $h = '<div class="finance-drawer-partials">';
    foreach ($rows as $r) {
        $sid = (int) ($r["id"] ?? 0);
        $status = (string) ($r["status"] ?? "not_started");
        $statusLabel =
            $status === "not_started"
                ? "Aguardando fechamento"
                : financial_human_session_status($status);
        $tot =
            $sid > 0
                ? financial_session_movement_totals($cid, $sid)
                : [
                    "receipt" => 0,
                    "payment" => 0,
                    "refund" => 0,
                    "transfer" => 0,
                    "deposit" => 0,
                    "adjustment" => 0,
                ];
        $cls = in_array($status, ["closed_pending_review"], true)
            ? "warn"
            : (in_array($status, ["approved", "kept_closed"], true)
                ? "ok"
                : ($status === "rejected"
                    ? "bad"
                    : "muted"));
        $metrics =
            '<div class="finance-drawer-partial-metrics"><span><small>Inicial</small><b>' .
            money_br((int) ($r["opening_balance_cents"] ?? 0)) .
            "</b></span><span><small>Recebido</small><b>" .
            money_br((int) ($tot["receipt"] ?? 0)) .
            "</b></span><span><small>Pagamentos</small><b>" .
            money_br((int) ($tot["payment"] ?? 0)) .
            "</b></span><span><small>Retirada</small><b>" .
            money_br((int) ($r["transfer_to_safe_cents"] ?? 0)) .
            "</b></span><span><small>Declarado</small><b>" .
            money_br((int) ($r["declared_closing_cents"] ?? 0)) .
            "</b></span><span><small>Diferença</small><b>" .
            money_br((int) ($r["difference_cents"] ?? 0)) .
            "</b></span></div>";
        $actions = "";
        if ($sid > 0 && $status === "closed_pending_review") {
            $unlockDefault = financial_default_drawer_unlock_local(
                $cid,
                (int) ($r["location_id"] ?? 0),
            );
            $actions =
                '<div class="finance-drawer-partial-actions"><form method="post" class="finance-inline-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                $sid .
                '"><input type="hidden" name="decision" value="approve">' .
                input(
                    "notes",
                    "text",
                    "",
                    'placeholder="Observação ou ajuste justificado"',
                ) .
                input(
                    "drawer_unlock_at",
                    "datetime-local",
                    $unlockDefault,
                    'required title="Horário de destravamento da Gaveta"',
                ) .
                '<button class="primary small" type="submit">' .
                icon("verified") .
                '<span>Está tudo certo</span></button></form><form method="post" class="finance-inline-form">' .
                csrf_field() .
                '<input type="hidden" name="act" value="review_close"><input type="hidden" name="session_id" value="' .
                $sid .
                '"><input type="hidden" name="decision" value="reject">' .
                input(
                    "notes",
                    "text",
                    "",
                    'required placeholder="Justifique a correção necessária"',
                ) .
                '<button class="danger small" type="submit">' .
                icon("edit_note") .
                "<span>Corrigir</span></button></form></div>";
        }
        $h .=
            '<article class="finance-row finance-action-row finance-drawer-partial-row"><div><strong>' .
            e((string) ($r["drawer_name"] ?? "Gaveta")) .
            " · " .
            e(first_name((string) ($r["user_name"] ?? "Colaborador"))) .
            "</strong><small>" .
            e($statusLabel) .
            "</small>" .
            $metrics .
            '</div><span class="pill ' .
            $cls .
            '">' .
            e($statusLabel) .
            "</span>" .
            $actions .
            "</article>";
    }
    return $h . "</div>";
}
function financial_admin_daily_conference_panel(int $cid, int $uid): string
{

    financial_operational_schema_ready();
    $today = financial_today($cid);
    $state = financial_daily_consolidation_state($cid, $today);
    $metrics = financial_daily_metrics($cid, $today);
    $expected = (int) $metrics["expected_cents"];
    $received = (int) $metrics["received_cents"];
    $pending = (int) $metrics["pending_cents"];
    $movements = (int) $metrics["movement_count"];
    $diffs = (int) safe_val(
        "SELECT COALESCE(SUM(ABS(difference_cents)),0) FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND difference_cents<>0",
        [$cid, $today],
        0,
    );
    $pos = financial_global_position($cid);
    $summary =
        '<div class="finance-conference-summary"><article><span>Previsto</span><b>' .
        money_br($expected) .
        "</b></article><article><span>Recebido</span><b>" .
        money_br($received) .
        "</b></article><article><span>Pendente</span><b>" .
        money_br($pending) .
        "</b></article><article><span>Movimentos</span><b>" .
        n($movements) .
        "</b></article></div>";
    $where =
        '<div class="finance-conference-summary finance-conference-places"><article><span>Gavetas</span><b>' .
        money_br((int) $pos["pos_cents"]) .
        "</b></article><article><span>Cofre</span><b>" .
        money_br((int) $pos["safe_cents"]) .
        "</b></article><article><span>Bancos</span><b>" .
        money_br((int) $pos["bank_cents"]) .
        "</b></article><article><span>Divergências</span><b>" .
        money_br($diffs) .
        "</b></article></div>";
    $partials = card(
        "<h2>" .
            icon("point_of_sale") .
            '<span>Parciais por Colaborador</span></h2><p class="muted">A conferência diária considera cada colaborador vinculado à Gaveta. Movimentos da Recepção só se tornam definitivos após confirmação do Administrativo.</p>' .
            financial_daily_drawer_partials_html($cid, $uid, (array) $state),
        "finance-list finance-drawer-partials-card",
    );
    if (!empty($state["consolidated"])) {
        $action =
            '<div class="finance-conference-lock"><span class="pill ok">' .
            icon("verified") .
            'Dia já consolidado</span><p class="muted">Novos lançamentos do dia ficam bloqueados para preservar a conferência.</p><a class="ghost small" href="' .
            href("financial", ["tab" => "painel"]) .
            '">' .
            icon("arrow_back") .
            "<span>Voltar ao Painel</span></a></div>";
    } elseif (empty($state["conference_released"])) {
        $action =
            '<div class="finance-conference-lock"><span class="pill warn">' .
            icon("point_of_sale") .
            n((int) ($state["pending_open_drawers"] ?? 0)) .
            ' colaborador(es) ainda sem fechamento</span><p class="muted">A conferência só é liberada quando todos os colaboradores vinculados à mesma Gaveta tiverem fechado ou mantido a Gaveta fechada no expediente.</p>' .
            financial_daily_drawer_closure_blocking_html(
                (array) $state["closure"],
            ) .
            '<a class="ghost small" href="' .
            href("financial", ["tab" => "locais"]) .
            '">' .
            icon("arrow_back") .
            "<span>Ver locais</span></a></div>";
    } elseif (empty($state["can_consolidate"])) {
        $action =
            '<div class="finance-conference-lock"><span class="pill warn">' .
            icon("fact_check") .
            'Conferência liberada</span><p class="muted">Revise as parciais por colaborador. Confirme o que estiver correto ou devolva com justificativa para correção antes da consolidação definitiva.</p>' .
            financial_daily_consolidation_blockers_html($state) .
            "</div>";
    } else {
        $action =
            '<form method="post" class="finance-conference-action">' .
            csrf_field() .
            '<input type="hidden" name="act" value="daily_consolidate"><button class="primary" type="submit">' .
            icon("verified") .
            '<span>Confirmar consolidação definitiva</span></button><a class="ghost" href="' .
            href("financial", ["tab" => "painel"]) .
            '">' .
            icon("arrow_back") .
            "<span>Voltar ao Painel</span></a></form>";
    }
    return card(
        "<h2>" .
            icon("fact_check") .
            "<span>Resumo do dia</span></h2>" .
            $summary .
            $where .
            $action,
        "finance-report-card finance-daily-conference-card",
    ) .
        $partials .
        card(
            '<h2>Movimentos do dia</h2><p class="muted">Revise entradas, saídas e transferências antes de confirmar a consolidação.</p>' .
                financial_admin_daily_ledger_timeline($cid),
            "finance-ledger-card",
        );
}
function financial_admin_daily_consolidate(int $cid, int $uid): void
{

    financial_operational_schema_ready();
    financial_daily_closing_ensure_schema();
    db_tx(function () use ($cid, $uid): void {

        $today = financial_today($cid);
        q("SELECT id FROM pi_clinics WHERE id=? FOR UPDATE", [$cid]);
        q(
            "SELECT id FROM pi_financial_daily_closings WHERE clinic_id=? AND business_date=? FOR UPDATE",
            [$cid, $today],
        );
        financial_daily_reconciliation($cid, $today, $uid, true);
        $state = financial_daily_consolidation_state($cid, $today);
        if (!empty($state["consolidated"])) {
            throw new RuntimeException(
                "Este dia financeiro já foi consolidado.",
            );
        }
        if (empty($state["conference_released"])) {
            throw new RuntimeException(
                "A conferência diária só é liberada quando todas as gavetas abertas hoje, por todos os colaboradores, estiverem fechadas.",
            );
        }
        if (empty($state["can_consolidate"])) {
            throw new RuntimeException(
                "Ainda existem conferências, devoluções ou movimentos pendentes. Resolva tudo antes de consolidar o dia.",
            );
        }
        $reconciliation = (array) ($state["reconciliation"] ?? []);
        if (empty($reconciliation["ok"])) {
            throw new RuntimeException(
                "A reconciliação matemática do dia não foi confirmada.",
            );
        }
        $metrics = (array) $reconciliation["metrics"];
        $expected = (int) $metrics["expected_cents"];
        $received = (int) $metrics["received_cents"];
        $pending = (int) $metrics["pending_cents"];
        $movements = (int) $metrics["movement_count"];
        $pos = (array) $reconciliation["position"];
        $integrityNote =
            "integrity:financial-reconciliation-v2:" .
            (string) $reconciliation["hash"];
        q(
            "INSERT INTO pi_financial_daily_closings (clinic_id,business_date,status,expected_cents,received_cents,pending_cents,drawer_cents,safe_cents,bank_cents,movement_count,closed_drawer_count,notes,consolidated_by,consolidated_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE status='consolidado', expected_cents=VALUES(expected_cents), received_cents=VALUES(received_cents), pending_cents=VALUES(pending_cents), drawer_cents=VALUES(drawer_cents), safe_cents=VALUES(safe_cents), bank_cents=VALUES(bank_cents), movement_count=VALUES(movement_count), closed_drawer_count=VALUES(closed_drawer_count), notes=VALUES(notes), consolidated_by=VALUES(consolidated_by), consolidated_at=NOW(), updated_at=NOW()",
            [
                $cid,
                $today,
                "consolidado",
                $expected,
                $received,
                $pending,
                (int) $pos["pos_cents"],
                (int) $pos["safe_cents"],
                (int) $pos["bank_cents"],
                $movements,
                (int) ($state["closure"]["opened_count"] ?? 0),
                $integrityNote,
                $uid,
            ],
        );
        audit("financeiro_dia_consolidado", "financeiro", $cid, [
            "business_date" => $today,
            "opened_drawers_checked" =>
                (int) ($state["closure"]["opened_count"] ?? 0),
            "movimentos" => $movements,
            "reconciliation_hash" => (string) $reconciliation["hash"],
            "audit_body" =>
                "Administrador conferiu e consolidou os lançamentos financeiros do dia após fechamento e revisão das gavetas abertas.",
        ]);
    });
}
function financial_admin_save_receipt(int $cid, int $uid): void
{

    $patientId = function_exists("resolve_patient_lookup_id")
        ? resolve_patient_lookup_id(
            $cid,
            (int) ($_POST["patient_link_id"] ?? 0),
            function_exists("posted_patient_search_value")
                ? posted_patient_search_value()
                : "",
        )
        : 0;
    if ($patientId <= 0) {
        throw new RuntimeException(
            "Todo recebimento precisa estar vinculado a um Paciente devedor.",
        );
    }
    $revenueId = (int) ($_POST["expected_revenue_id"] ?? 0);
    $method =
        normalize_payment_method((string) ($_POST["payment_method"] ?? "")) ?:
        "pix";
    $to = (int) ($_POST["to_location_id"] ?? 0);
    if ($to <= 0 || !financial_admin_location_belongs($cid, $to)) {
        throw new RuntimeException(
            "Recebimento pelo Administrador deve entrar em Cofre ou Banco do Consultório. Gaveta pertence ao fluxo da Recepção.",
        );
    }
    if ($revenueId > 0) {
        if (
            !financial_pending_revenue_belongs_to_patient(
                $cid,
                $revenueId,
                $patientId,
            )
        ) {
            throw new RuntimeException(
                "A pendência selecionada não pertence ao Paciente informado ou já foi recebida.",
            );
        }
        financial_admin_receive_expected_revenue(
            $cid,
            $uid,
            $revenueId,
            $method,
            $to,
            trim((string) ($_POST["notes"] ?? "")),
        );
        return;
    }
    $pending = financial_patient_pending_revenue_count($cid, $patientId);
    $avulso =
        isset($_POST["receipt_without_appointment"]) &&
        (string) ($_POST["receipt_without_appointment"] ?? "") === "1";
    $notes = trim((string) ($_POST["notes"] ?? ""));
    if (!$avulso) {
        throw new RuntimeException(
            "Selecione uma pendência de agendamento ou marque recebimento sem agendamento vinculado com motivo.",
        );
    }
    if ($pending > 0 && !$avulso) {
        throw new RuntimeException(
            "Este Paciente possui pendência de agendamento. Selecione a pendência ou marque recebimento sem agendamento vinculado com motivo.",
        );
    }
    if ($avulso && $notes === "") {
        throw new RuntimeException(
            "Informe o motivo do recebimento sem agendamento vinculado.",
        );
    }
    $amount = parse_money_cents((string) ($_POST["amount"] ?? "0"));
    if ($amount <= 0) {
        throw new RuntimeException("Informe o valor recebido.");
    }
    $title = trim(
        (string) ($_POST["title"] ?? "Recebimento sem agendamento vinculado"),
    );
    if ($title === "") {
        $title = "Recebimento sem agendamento vinculado";
    }
    $accountId = financial_location_account_id($cid, $to);
    q(
        "INSERT INTO pi_financial_revenues (clinic_id,patient_link_id,title,amount_cents,status,payment_method,account_id,received_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())",
        [
            $cid,
            $patientId,
            $title,
            $amount,
            "efetivada",
            $method,
            $accountId,
            date("Y-m-d H:i:s"),
            $uid,
        ],
    );
    $rid = db_last_insert_id();
    financial_create_movement(
        $cid,
        "receipt",
        $amount,
        null,
        $to,
        null,
        $uid,
        $title,
        $method,
        $notes,
        "confirmed",
        "financial_revenue",
        $rid,
    );
    audit("recebimento_administrativo_avulso", "financeiro", $rid, [
        "patient_link_id" => $patientId,
        "valor" => $amount,
        "audit_body" =>
            "Administrador registrou recebimento sem agendamento vinculado, com motivo obrigatório quando havia pendências do paciente.",
    ]);
}
function financial_admin_save_payment(int $cid, int $uid): void
{

    $creditorId = (int) ($_POST["counterparty_id"] ?? 0);
    $cred =
        $creditorId > 0
            ? one(
                "SELECT fc.id,p.full_name FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.id=? AND fc.clinic_id=? AND fc.kind='credor' AND fc.active=1 LIMIT 1",
                [$creditorId, $cid],
            )
            : null;
    if (!$cred) {
        throw new RuntimeException("Escolha um Credor cadastrado em Pessoas.");
    }
    $from = (int) ($_POST["from_location_id"] ?? 0);
    if ($from <= 0 || !financial_admin_location_belongs($cid, $from)) {
        throw new RuntimeException(
            "Pagamento pelo Administrador deve sair de Cofre ou Banco do Consultório. Gaveta pertence ao fluxo da Recepção/Caixa.",
        );
    }
    $amount = parse_money_cents((string) ($_POST["amount"] ?? "0"));
    if ($amount <= 0) {
        throw new RuntimeException("Informe o valor da despesa.");
    }
    $method =
        normalize_payment_method((string) ($_POST["payment_method"] ?? "")) ?:
        "pix";
    $category = (string) ($_POST["expense_category"] ?? "outras");
    if (!array_key_exists($category, financial_expense_category_options())) {
        $category = "outras";
    }
    $title = trim((string) ($_POST["title"] ?? "Pagamento ao credor"));
    if ($title === "") {
        $title = "Pagamento ao credor";
    }
    $notes = trim((string) ($_POST["notes"] ?? ""));
    $accountId = financial_location_account_id($cid, $from);
    q(
        "INSERT INTO pi_financial_expenses (clinic_id,counterparty_id,account_id,title,expense_category,amount_cents,status,due_at,paid_at,payment_method,notes,created_by,created_at) VALUES (?,?,?,?,?,?,'paga',CURDATE(),NOW(),?,?,?,NOW())",
        [
            $cid,
            $creditorId,
            $accountId,
            $title,
            $category,
            $amount,
            $method,
            $notes ?: null,
            $uid,
        ],
    );
    $eid = db_last_insert_id();
    financial_create_movement(
        $cid,
        "payment",
        $amount,
        $from,
        null,
        null,
        $uid,
        $title . " · " . (string) $cred["full_name"],
        $method,
        $notes,
        "confirmed",
        "financial_expense",
        $eid,
    );
    audit("pagamento_administrativo", "financeiro", $eid, [
        "counterparty_id" => $creditorId,
        "valor" => $amount,
        "from_location_id" => $from,
        "categoria" => $category,
        "audit_body" =>
            "Administrador registrou pagamento de despesa informando Credor e Cofre/Banco de saída.",
    ]);
}
function financial_admin_save_transfer(int $cid, int $uid): void
{

    $from = (int) ($_POST["from_location_id"] ?? 0);
    $to = (int) ($_POST["to_location_id"] ?? 0);
    if ($from <= 0 || !financial_admin_location_belongs($cid, $from)) {
        throw new RuntimeException(
            "Transferência administrativa deve sair de Cofre ou Banco. Gaveta é movimentada pelo fluxo de Caixa/fechamento.",
        );
    }
    if ($to <= 0 || !financial_admin_location_belongs($cid, $to)) {
        throw new RuntimeException(
            "Transferência administrativa deve ir para Cofre ou Banco. Gaveta é movimentada pelo fluxo de Caixa/fechamento.",
        );
    }
    if ($from === $to) {
        throw new RuntimeException("Origem e destino precisam ser diferentes.");
    }
    $amount = parse_money_cents((string) ($_POST["amount"] ?? "0"));
    if ($amount <= 0) {
        throw new RuntimeException("Informe o valor da transferência.");
    }
    $title = trim((string) ($_POST["title"] ?? "Transferência entre locais"));
    if ($title === "") {
        $title = "Transferência entre locais";
    }
    $notes = trim((string) ($_POST["notes"] ?? ""));
    $mid = financial_create_movement(
        $cid,
        "transfer",
        $amount,
        $from,
        $to,
        null,
        $uid,
        $title,
        "transferencia",
        $notes,
        "confirmed",
        "financial_transfer",
        0,
    );
    audit("transferencia_financeira_administrativa", "financeiro", $mid, [
        "from_location_id" => $from,
        "to_location_id" => $to,
        "valor" => $amount,
        "audit_body" =>
            "Administrador transferiu valor apenas entre Cofre/Banco do Consultório.",
    ]);
}
function financial_admin_operations_panel(int $cid, int $uid): string
{

    $locations = financial_admin_location_select_options($cid);
    $pendingOptions = financial_expected_appointment_revenue_options($cid);
    $op = (string) ($_GET["op"] ?? "");
    if (!in_array($op, ["receber", "pagar", "transferir"], true)) {
        $op = "";
    }
    $receipt =
        '<form method="post" class="compact finance-lite-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="admin_receive"><div class="two">' .
        form_row(
            "Paciente devedor",
            patient_lookup_field($cid, "patient_link_id", "", "patient_search"),
        ) .
        select_label(
            "Pendência de agendamento",
            "expected_revenue_id",
            $pendingOptions,
            "",
        ) .
        '</div><div class="two">' .
        form_row(
            "Valor recebido",
            financial_money_input(
                "amount",
                "",
                'placeholder="Use apenas para recebimento sem agendamento"',
            ),
        ) .
        select_label(
            "Entrou em qual local?",
            "to_location_id",
            $locations,
            "",
            "required",
        ) .
        '</div><div class="two">' .
        select_label(
            "Forma",
            "payment_method",
            payment_methods_options(),
            "pix",
            "required",
        ) .
        form_row(
            "Descrição",
            input(
                "title",
                "text",
                "",
                'placeholder="Ex.: recebimento sem agendamento"',
            ),
        ) .
        '</div><label class="checkline"><input type="checkbox" name="receipt_without_appointment" value="1"><span>Recebimento sem agendamento vinculado</span></label>' .
        form_row(
            "Motivo / observação",
            input(
                "notes",
                "text",
                "",
                'placeholder="Obrigatório se houver pendência e o recebimento for avulso"',
            ),
        ) .
        (function_exists("patient_autosuggest_datalist")
            ? patient_autosuggest_datalist($cid)
            : "") .
        '<p class="muted">Regra do Prontoo: se o Paciente tem pendência de agendamento, receba a pendência. Avulso só com motivo.</p>' .
        form_actions("Registrar recebimento") .
        "</form>";
    $payment =
        '<form method="post" class="compact finance-lite-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="admin_payment"><div class="two">' .
        select_label(
            "Credor",
            "counterparty_id",
            counterparty_options($cid),
            "",
            "required",
        ) .
        form_row(
            "Valor pago",
            financial_money_input("amount", "", "required"),
        ) .
        '</div><div class="two">' .
        select_label(
            "Saiu de qual local?",
            "from_location_id",
            $locations,
            "",
            "required",
        ) .
        select_label(
            "Forma",
            "payment_method",
            payment_methods_options(),
            "pix",
            "required",
        ) .
        '</div><div class="two">' .
        select_label(
            "Motivo",
            "expense_category",
            financial_expense_category_options(),
            "outras",
            "required",
        ) .
        form_row(
            "Descrição",
            input(
                "title",
                "text",
                "",
                'required placeholder="Ex.: aluguel, material, contador"',
            ),
        ) .
        "</div>" .
        form_row(
            "Observação",
            input("notes", "text", "", 'placeholder="Opcional"'),
        ) .
        '<p class="muted">Credores são cadastrados em Pessoas › Credores. Pagamentos do Administrador saem apenas de Cofre ou Banco.</p>' .
        form_actions("Registrar pagamento", "danger") .
        "</form>";
    $transfer =
        '<form method="post" class="compact finance-lite-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="admin_transfer"><div class="two">' .
        select_label(
            "Saiu de",
            "from_location_id",
            $locations,
            "",
            "required",
        ) .
        select_label("Foi para", "to_location_id", $locations, "", "required") .
        '</div><div class="two">' .
        form_row(
            "Valor transferido",
            financial_money_input("amount", "", "required"),
        ) .
        form_row(
            "Motivo",
            input(
                "title",
                "text",
                "Transferência entre Cofre/Banco",
                "required",
            ),
        ) .
        "</div>" .
        form_row(
            "Observação",
            input(
                "notes",
                "text",
                "",
                'placeholder="Ex.: depósito do Cofre no Banco"',
            ),
        ) .
        '<p class="muted">Transferências com Gaveta são tratadas no fechamento da Recepção, não por operação administrativa direta.</p>' .
        form_actions("Registrar transferência") .
        "</form>";
    if ($op !== "") {
        $defs = [
            "receber" => [
                "Receber de Paciente",
                "add_card",
                "Entrada de paciente, preferencialmente vinculada a uma pendência de agendamento.",
                $receipt,
            ],
            "pagar" => [
                "Pagar Credor",
                "payments",
                "Saída para credor, despesa ou obrigação do Consultório.",
                $payment,
            ],
            "transferir" => [
                "Transferir Valor",
                "swap_horiz",
                "Movimentação interna entre Cofre e Banco.",
                $transfer,
            ],
        ];
        $d = $defs[$op];
        $back =
            '<div class="subtle-actions"><a class="ghost small" href="' .
            href("financial", ["tab" => "operacoes"]) .
            '">' .
            icon("arrow_back") .
            "<span>Operações</span></a></div>";
        return '<div class="finance-workspace finance-operations-workspace">' .
            $back .
            card(
                "<h2>" .
                    icon($d[1]) .
                    "<span>" .
                    e($d[0]) .
                    '</span></h2><p class="muted">' .
                    e($d[2]) .
                    "</p>" .
                    $d[3],
                "finance-form finance-admin-operation-card finance-context-form finance-operation-screen-card",
            ) .
            "</div>";
    }
    $rows = "";
    foreach (
        [
            "receber" => [
                "Receber de Paciente",
                "add_card",
                "Registrar entrada de paciente, vinculando pendência quando houver.",
                "Abrir recebimento",
            ],
            "pagar" => [
                "Pagar Credor",
                "payments",
                "Registrar saída para credor, despesa ou obrigação do Consultório.",
                "Abrir pagamento",
            ],
            "transferir" => [
                "Transferir Valor",
                "swap_horiz",
                "Mover valores internamente entre Cofre e Banco.",
                "Abrir transferência",
            ],
        ]
        as $key => $d
    ) {
        $rows .=
            '<a class="patient-card-row ds-person-row finance-operation-row" href="' .
            href("financial", ["tab" => "operacoes", "op" => $key]) .
            '"><span class="patient-card-avatar ds-person-avatar" aria-hidden="true">' .
            icon($d[1]) .
            '</span><div class="patient-card-main ds-person-main"><div class="patient-card-title ds-person-title"><strong>' .
            e($d[0]) .
            '</strong><span class="pill">Financeiro</span></div><div class="patient-card-meta ds-person-meta"><span>' .
            e($d[2]) .
            '</span></div></div><div class="patient-card-actions ds-person-actions"><span class="ghost small">' .
            e($d[3]) .
            "</span></div></a>";
    }
    return '<div class="finance-workspace finance-operations-workspace">' .
        card(
            "<h2>" .
                icon("payments") .
                '<span>Operações financeiras</span></h2><p class="muted">Escolha uma operação para abrir a tela de lançamento. A lista permanece limpa e os formulários não ficam soltos na página.</p><div class="ds-directory-list finance-operation-directory">' .
                $rows .
                "</div>",
            "finance-list finance-operations-directory-card",
        ) .
        "</div>";
}
function financial_creditor_upsert_from_post(int $cid, int $uid): int
{

    person_common_profile_schema_ready();
    $name = trim((string) ($_POST["creditor_name"] ?? ""));
    $type = (string) ($_POST["creditor_legal_type"] ?? "cpf");
    $doc = only_digits((string) ($_POST["creditor_legal_document"] ?? ""));
    $birth = trim((string) ($_POST["creditor_birth_date"] ?? "")) ?: null;
    if ($name === "") {
        throw new RuntimeException("Informe o nome do Credor.");
    }
    if ($type === "cnpj") {
        if (!valid_cnpj($doc)) {
            throw new RuntimeException("Informe um CNPJ válido para o Credor.");
        }
        $pid = save_person_by_document($name, $doc, null);
    } else {
        if (!valid_cpf($doc)) {
            throw new RuntimeException("Informe um CPF válido para o Credor.");
        }
        $pid = save_person_flexible($name, $doc, $birth);
    }
    $profile = person_common_profile_from_array($_POST, "creditor_");
    $profile["legal_type"] = $type === "cnpj" ? "cnpj" : "cpf";
    $profile["legal_document"] = $doc;
    person_common_profile_update($pid, $profile);
    $notes = trim((string) ($_POST["creditor_notes"] ?? ""));
    q(
        "INSERT INTO pi_financial_counterparties (clinic_id,person_id,kind,notes,active,created_by,created_at) VALUES (?,?,?,?,1,?,NOW()) ON DUPLICATE KEY UPDATE notes=VALUES(notes), active=1, updated_at=NOW()",
        [$cid, $pid, "credor", $notes ?: null, $uid],
    );
    $id =
        (int) (val(
            "SELECT id FROM pi_financial_counterparties WHERE clinic_id=? AND person_id=? AND kind='credor' LIMIT 1",
            [$cid, $pid],
        ) ?:
        0);
    audit("credor_salvo", "pessoa", $id, [
        "nome" => $name,
        "documento" => $doc,
        "audit_body" => "Credor cadastrado ou atualizado no contexto Pessoas.",
    ]);
    return $id;
}
function financial_creditor_directory_card(array $r, int $cid): string
{

    $name = (string) ($r["full_name"] ?? "Credor #" . ($r["id"] ?? ""));
    $doc = only_digits(
        (string) ($r["legal_document"] ?? "" ?: $r["cpf"] ?? ""),
    );
    $docLabel = $doc !== "" ? mask($doc) : "CPF/CNPJ não informado";
    $phone = phone_br((string) ($r["phone"] ?? ""));
    $phoneLabel = trim($phone) !== "" ? $phone : "Sem telefone";
    $email = trim((string) ($r["email"] ?? ""));
    $city = trim((string) ($r["address_city"] ?? ""));
    $state = trim((string) ($r["address_state"] ?? ""));
    $cityLabel =
        $city !== ""
            ? $city . ($state !== "" ? " / " . $state : "")
            : "Endereço não informado";
    $paidTotal = (int) ($r["paid_total_cents"] ?? 0);
    $lastPaid = trim((string) ($r["last_paid_at"] ?? ""));
    $lastPaidLabel =
        $lastPaid !== ""
            ? (function_exists("app_date_br")
                ? app_date_br($lastPaid, $cid)
                : date_br($lastPaid))
            : "Sem pagamento registrado";
    $overdue = (int) ($r["overdue_expenses"] ?? 0);
    $contactOk = $email !== "" || trim($phone) !== "";
    $searchData =
        $name .
        " " .
        $docLabel .
        " " .
        $phoneLabel .
        " " .
        $email .
        " " .
        $cityLabel .
        " " .
        $lastPaidLabel .
        " " .
        ($paidTotal > 0 ? money_br($paidTotal) : "");
    $status = $overdue > 0 ? "pendência" : "ativo";
    $statusClass = $overdue > 0 ? "warn" : "ok";
    return '<article class="patient-card-row ds-person-row ds-creditor-row creditor-status-' .
        e($statusClass) .
        '" data-creditor-row data-patient-search="' .
        e($searchData) .
        '">' .
        '<span class="patient-card-avatar ds-person-avatar" aria-hidden="true">' .
        icon("receipt_long") .
        "</span>" .
        '<div class="patient-card-main ds-person-main"><div class="patient-card-title ds-person-title"><strong>' .
        e($name) .
        '</strong><span class="pill patient-status-pill ds-status-pill ' .
        e($statusClass) .
        '">' .
        e($status) .
        "</span></div>" .
        '<div class="patient-card-meta ds-person-meta"><span>' .
        icon("badge") .
        "<span>" .
        e($docLabel) .
        "</span></span><span>" .
        icon("call") .
        "<span>" .
        e($phoneLabel) .
        "</span></span>" .
        ($email !== ""
            ? "<span>" .
                icon("alternate_email") .
                "<span>" .
                e($email) .
                "</span></span>"
            : "") .
        "<span>" .
        icon("location_on") .
        "<span>" .
        e($cityLabel) .
        "</span></span><span>" .
        icon("payments") .
        "<span>Total pago: " .
        e(money_br($paidTotal)) .
        "</span></span><span>" .
        icon("event_available") .
        "<span>Último pagamento: " .
        e($lastPaidLabel) .
        "</span></span>" .
        ($contactOk
            ? ""
            : '<span class="pill warn">' .
                icon("priority_high") .
                "Contato pendente</span>") .
        ($overdue > 0
            ? '<span class="pill bad">' .
                icon("warning") .
                e((string) $overdue) .
                " vencida(s)</span>"
            : "") .
        "</div></div>" .
        '<div class="patient-card-actions ds-person-actions"><form method="post" class="inline" onsubmit="return confirm(&quot;Desativar este credor?&quot;)">' .
        csrf_field() .
        '<input type="hidden" name="act" value="creditor_deactivate"><input type="hidden" name="creditor_id" value="' .
        (int) $r["id"] .
        '"><button class="ghost small" type="submit">' .
        icon("visibility_off") .
        "<span>Desativar</span></button></form></div>" .
        "</article>";
}
function page_creditors(): void
{

    $c = require_can("patients");
    if (!has_effective_role($c, "gerente")) {
        throw new ProntooHttpError(
            403,
            "Credores são exclusivos do ambiente Administrador.",
        );
    }
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    financial_operational_schema_ready();
    person_common_profile_schema_ready();
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "creditor_save");
        try {
            if ($act === "creditor_save") {
                financial_creditor_upsert_from_post($cid, $uid);
                flash("Credor salvo.");
                redirect("creditors");
            }
            if ($act === "creditor_deactivate") {
                $id = (int) ($_POST["creditor_id"] ?? 0);
                q(
                    "UPDATE pi_financial_counterparties SET active=0,updated_at=NOW() WHERE id=? AND clinic_id=? AND kind='credor'",
                    [$id, $cid],
                );
                audit("credor_desativado", "pessoa", $id, [
                    "audit_body" => "Credor desativado no contexto Pessoas.",
                ]);
                flash("Credor desativado.");
                redirect("creditors");
            }
        } catch (Throwable $e) {
            error_log("[Prontoo credores] " . $e->getMessage());
            flash(
                app_public_error_message(
                    $e,
                    "Não foi possível concluir a operação financeira.",
                ),
                "bad",
            );
            redirect("creditors");
        }
    }
    $search = trim((string) ($_GET["q"] ?? ""));
    $newMode = isset($_GET["new"]) && (string) $_GET["new"] !== "0";
    $formActions =
        '<div class="form-actions"><a class="ghost" href="' .
        href("creditors") .
        '">' .
        icon("close") .
        '<span>Cancelar</span></a><button type="submit" class="primary">' .
        icon(form_submit_icon("Salvar credor")) .
        "<span>Salvar credor</span></button></div>";
    $form =
        '<form method="post" class="compact creditor-form patient-cpf-first-form">' .
        csrf_field() .
        '<input type="hidden" name="act" value="creditor_save"><div class="two">' .
        form_row(
            "Nome/Razão social do Credor",
            input("creditor_name", "text", "", 'required autocomplete="name"'),
        ) .
        select_label(
            "Identificação",
            "creditor_legal_type",
            ["cpf" => "CPF", "cnpj" => "CNPJ"],
            "cpf",
            "required",
        ) .
        '</div><div class="two">' .
        form_row(
            "CPF/CNPJ",
            input(
                "creditor_legal_document",
                "text",
                "",
                'required inputmode="numeric" data-doc-mask data-document-validate',
            ),
        ) .
        form_row(
            "Nascimento (se pessoa física)",
            input("creditor_birth_date", "date", ""),
        ) .
        "</div>" .
        person_common_profile_fields_html($cid, [], "creditor_") .
        form_row("Observações", textarea("creditor_notes", "", 'rows="3"')) .
        $formActions .
        "</form>";
    if ($newMode) {
        page(
            "Novo Credor",
            page_head("Novo Credor", "") .
                card(
                    '<h2>Novo Credor</h2><p class="muted">Cadastre pessoas ou empresas que podem receber pagamentos administrativos do Consultório.</p>' .
                        $form,
                    "patient-new-screen-card creditor-new-card",
                ),
        );
        return;
    }
    $activeCount =
        (int) (val(
            "SELECT COUNT(*) FROM pi_financial_counterparties WHERE clinic_id=? AND kind='credor' AND active=1",
            [$cid],
        ) ?? 0);
    $withDocCount =
        (int) (val(
            "SELECT COUNT(*) FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.kind='credor' AND fc.active=1 AND COALESCE(NULLIF(p.legal_document,''),NULLIF(p.cpf,''),'')<>''",
            [$cid],
        ) ?? 0);
    $withContactCount =
        (int) (val(
            "SELECT COUNT(*) FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE fc.clinic_id=? AND fc.kind='credor' AND fc.active=1 AND (COALESCE(p.phone,'')<>'' OR COALESCE(p.email,'')<>'')",
            [$cid],
        ) ?? 0);
    $paid30Cents =
        (int) (val(
            "SELECT COALESCE(SUM(e.amount_cents),0) FROM pi_financial_expenses e JOIN pi_financial_counterparties fc ON fc.id=e.counterparty_id AND fc.clinic_id=e.clinic_id WHERE e.clinic_id=? AND fc.kind='credor' AND fc.active=1 AND e.status='paga' AND e.paid_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$cid],
        ) ?? 0);
    $statHtml =
        '<section class="patient-directory-overview kpis kpi-info-strip creditor-directory-overview" aria-label="Resumo de credores"><div class="patient-kpi-card kpi-card ' .
        ($activeCount > 0 ? "is-total" : "is-muted") .
        '">' .
        icon("receipt_long") .
        "<p><b>" .
        number_format($activeCount, 0, ",", ".") .
        '</b><span>Credores ativos</span></p></div><div class="patient-kpi-card kpi-card ' .
        ($withDocCount === $activeCount && $activeCount > 0
            ? "is-ok"
            : "is-warn warn") .
        '">' .
        icon("badge") .
        "<p><b>" .
        number_format($withDocCount, 0, ",", ".") .
        '</b><span>Com CPF/CNPJ</span></p></div><div class="patient-kpi-card kpi-card ' .
        ($withContactCount === $activeCount && $activeCount > 0
            ? "is-ok"
            : "is-warn warn") .
        '">' .
        icon("contact_phone") .
        "<p><b>" .
        number_format($withContactCount, 0, ",", ".") .
        '</b><span>Com contato</span></p></div><div class="patient-kpi-card kpi-card ' .
        ($paid30Cents > 0 ? "is-ok" : "is-muted") .
        '">' .
        icon("payments") .
        "<p><b>" .
        e(money_br($paid30Cents)) .
        "</b><span>Pago em 30 dias</span></p></div></section>";
    $params = [$cid];
    $where = "fc.clinic_id=? AND fc.kind='credor' AND fc.active=1";
    if ($search !== "") {
        $like = "%" . $search . "%";
        $digits = only_digits($search);
        if ($digits !== "") {
            $where .=
                " AND (p.full_name LIKE ? OR p.cpf LIKE ? OR p.legal_document LIKE ? OR p.phone LIKE ? OR p.email LIKE ? OR fc.notes LIKE ?)";
            array_push(
                $params,
                $like,
                "%" . $digits . "%",
                "%" . $digits . "%",
                "%" . $digits . "%",
                $like,
                $like,
            );
        } else {
            $where .=
                " AND (p.full_name LIKE ? OR p.email LIKE ? OR p.address_city LIKE ? OR fc.notes LIKE ?)";
            array_push($params, $like, $like, $like, $like);
        }
    }
    $rows = q(
        "SELECT fc.id,fc.notes,p.*,(SELECT COALESCE(SUM(e.amount_cents),0) FROM pi_financial_expenses e WHERE e.clinic_id=fc.clinic_id AND e.counterparty_id=fc.id AND e.status='paga') paid_total_cents,(SELECT MAX(e.paid_at) FROM pi_financial_expenses e WHERE e.clinic_id=fc.clinic_id AND e.counterparty_id=fc.id AND e.status='paga') last_paid_at,(SELECT COUNT(*) FROM pi_financial_expenses e WHERE e.clinic_id=fc.clinic_id AND e.counterparty_id=fc.id AND e.status='prevista' AND e.due_at<CURDATE()) overdue_expenses FROM pi_financial_counterparties fc JOIN pi_persons p ON p.id=fc.person_id WHERE $where ORDER BY p.full_name ASC LIMIT 160",
        $params,
    )->fetchAll();
    $rowsHtml = "";
    foreach ($rows as $r) {
        $rowsHtml .= financial_creditor_directory_card($r, $cid);
    }
    $empty =
        $search !== ""
            ? "Nenhum credor encontrado para esta busca."
            : "Nenhum Credor cadastrado.";
    $list =
        $rowsHtml !== ""
            ? '<div class="patient-directory-list ds-person-list ds-creditor-list">' .
                $rowsHtml .
                "</div>"
            : '<div class="empty patient-directory-empty">' .
                icon("manage_search") .
                "<strong>" .
                $empty .
                "</strong><span>Use a busca ou cadastre um novo credor para registrar pagamentos administrativos com mais segurança.</span></div>";
    $clear =
        $search !== ""
            ? '<a class="ghost small" href="' .
                href("creditors") .
                '">' .
                icon("close") .
                "<span>Limpar</span></a>"
            : "";
    $searchBar =
        '<section class="patient-directory-search ds-search-block creditor-directory-search"><form method="get" class="patient-search-bar creditor-search-bar" role="search"><input type="hidden" name="r" value="creditors"><label class="search-field"><input name="q" type="search" value="' .
        e($search) .
        '" placeholder="Nome, CPF/CNPJ, telefone, e-mail ou cidade" autocomplete="off" aria-label="Buscar credor por nome, CPF/CNPJ, telefone, e-mail ou cidade"></label><button class="primary small" type="submit">' .
        icon("search") .
        "<span>Busca rápida</span></button>" .
        $clear .
        "</form></section>";
    $filterLabel =
        $search !== ""
            ? '<span class="patient-filter-chip active is-active patient-filter-found" aria-current="page">' .
                icon("manage_search") .
                "<span>Encontrados</span><small>" .
                number_format(count($rows), 0, ",", ".") .
                "</small></span>"
            : '<span class="patient-filter-chip active is-active" aria-current="page">' .
                icon("receipt_long") .
                "<span>Ativos</span><small>" .
                number_format($activeCount, 0, ",", ".") .
                "</small></span>";
    $filterList =
        '<nav class="patient-filter-chips ds-filter-list-chips" aria-label="Filtros de credores">' .
        $filterLabel .
        "</nav><div>" .
        $list .
        "</div>";
    $headAction =
        '<a class="primary small cmdlike" href="' .
        href("creditors", ["new" => 1]) .
        '">' .
        action_summary_label("Novo Credor", "person_add") .
        "</a>";
    page(
        "Credores",
        page_head(
            "Credores",
            "Pessoas ou empresas que podem receber pagamentos administrativos do Consultório.",
            $headAction,
        ) .
            $statHtml .
            card(
                $searchBar,
                "patient-search-card ds-search-card creditor-search-card",
            ) .
            card(
                $filterList,
                "patient-list-card patient-directory-card creditor-directory-card ds-filter-list-block",
            ),
    );
}
function financial_admin_attention_panel(int $cid): string
{

    financial_operational_schema_ready();
    $today = financial_today($cid);
    [$dayStart, $dayEnd] = app_local_day_utc_range($today, $cid);
    $items = "";
    $pending = (int) safe_val(
        "SELECT COUNT(*) FROM pi_financial_revenues r LEFT JOIN pi_appointments a ON a.id=r.appointment_id AND a.clinic_id=r.clinic_id WHERE r.clinic_id=? AND r.status='prevista' AND r.amount_cents>0 AND r.expected_at<? AND (a.id IS NULL OR a.status NOT IN ('cancelado','nao_compareceu'))",
        [$cid, $dayEnd],
        0,
    );
    $closure = financial_daily_drawer_closure_state($cid, $today);
    $openDrawers = (int) $closure["blocking_count"];
    $reviews = (int) safe_val(
        "SELECT COUNT(*) FROM pi_cash_sessions WHERE clinic_id=? AND status IN ('closed_pending_review','opening_pending_review')",
        [$cid],
        0,
    );
    $diffs = (int) safe_val(
        "SELECT COALESCE(SUM(ABS(difference_cents)),0) FROM pi_cash_sessions WHERE clinic_id=? AND business_date=? AND difference_cents<>0",
        [$cid, $today],
        0,
    );
    if ($pending > 0) {
        $items .=
            '<a class="finance-attention-item" href="' .
            href("financial", ["tab" => "conferencias"]) .
            '">' .
            icon("pending_actions") .
            "<span><b>" .
            n($pending) .
            " atendimento(s) ainda pendente(s) de pagamento</b><small>Conclua recebimentos antes de fechar o dia.</small></span></a>";
    }
    if ($openDrawers > 0) {
        $items .=
            '<a class="finance-attention-item" href="' .
            href("financial", ["tab" => "locais"]) .
            '">' .
            icon("point_of_sale") .
            "<span><b>" .
            n($openDrawers) .
            " gaveta(s) aberta(s)</b><small>Confira se a Recepção ainda precisa fechar a Gaveta.</small></span></a>";
    }
    if ($reviews > 0) {
        $items .=
            '<a class="finance-attention-item" href="' .
            href("financial", ["tab" => "conferencias"]) .
            '">' .
            icon("fact_check") .
            "<span><b>" .
            n($reviews) .
            " conferência(s) aguardando</b><small>Autorizações e fechamentos ficam concentrados em Conferências.</small></span></a>";
    }
    if ($diffs > 0) {
        $items .=
            '<a class="finance-attention-item is-danger" href="' .
            href("financial", ["tab" => "conferencias"]) .
            '">' .
            icon("difference") .
            "<span><b>" .
            money_br($diffs) .
            " em divergências de gaveta hoje</b><small>Revise sobras ou faltas antes da consolidação.</small></span></a>";
    }
    if ($items === "") {
        $items =
            '<div class="empty">Nada exige atenção financeira neste momento.</div>';
    }
    return card(
        "<h2>" .
            icon("notifications_active") .
            '<span>Precisa de atenção</span></h2><p class="muted">O Prontoo destaca apenas o que pode atrapalhar o fechamento do dia.</p><div class="finance-attention-list">' .
            $items .
            "</div>",
        "finance-attention-card",
    );
}
function financial_admin_conferences_panel(int $cid, int $uid): string
{

    $pending = card(
        "<h2>" .
            icon("pending_actions") .
            '<span>Pendências de recebimento</span></h2><p class="muted">Atendimentos com valor previsto ainda não recebido. A Recepção recebe pela Gaveta; o Administrador recebe pela tela Operações, sempre vinculando a pendência quando ela existir.</p>' .
            financial_cashier_pending_receipts_html($cid),
        "finance-list finance-conference-pending-card",
    );
    return '<div class="finance-workspace finance-conferences-workspace">' .
        $pending .
        financial_admin_reviews_panel($cid, $uid) .
        "</div>";
}
function financial_admin_page(array $c): void
{

    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    financial_operational_schema_ready();
    financial_ensure_admin_safe($cid, $uid);
    financial_seed_payment_methods($cid, $uid);
    $rawTab = (string) ($_GET["tab"] ?? "painel");
    if (
        in_array($rawTab, ["receber", "pagar", "transferir"], true) &&
        empty($_GET["op"])
    ) {
        $_GET["op"] = $rawTab;
    }
    $tab = $rawTab;
    $valid = [
        "painel",
        "locais",
        "operacoes",
        "conferencias",
        "meta",
        "consolidacao",
    ];
    if (!in_array($tab, $valid, true)) {
        $tab = "painel";
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "");
        try {
            if ($act === "drawer_create") {
                financial_create_drawer(
                    $cid,
                    $uid,
                    (string) ($_POST["drawer_name"] ?? ""),
                );
                flash("Gaveta criada.");
                redirect("financial", ["tab" => "locais"]);
            }
            if ($act === "drawer_rename") {
                financial_rename_drawer(
                    $cid,
                    (int) ($_POST["drawer_id"] ?? 0),
                    $uid,
                    (string) ($_POST["drawer_name"] ?? ""),
                );
                flash("Gaveta renomeada.");
                redirect("financial", ["tab" => "locais"]);
            }
            if ($act === "drawer_assign") {
                financial_link_drawer_user(
                    $cid,
                    (int) ($_POST["drawer_id"] ?? 0),
                    (int) ($_POST["cashier_user_id"] ?? 0),
                    $uid,
                );
                flash("Colaborador vinculado à Gaveta.");
                redirect("financial", ["tab" => "locais"]);
            }
            if ($act === "drawer_unassign") {
                financial_unlink_drawer_user(
                    $cid,
                    (int) ($_POST["link_id"] ?? 0),
                    $uid,
                );
                flash("Vínculo removido.");
                redirect("financial", ["tab" => "locais"]);
            }
            if ($act === "drawer_deactivate") {
                financial_deactivate_drawer(
                    $cid,
                    (int) ($_POST["drawer_id"] ?? 0),
                    $uid,
                );
                flash("Gaveta desativada.");
                redirect("financial", ["tab" => "locais"]);
            }
            if ($act === "drawer_schedule_unlock") {
                financial_schedule_drawer_unlock(
                    $cid,
                    (int) ($_POST["drawer_id"] ?? 0),
                    $uid,
                    (string) ($_POST["drawer_unlock_at"] ?? ""),
                    trim((string) ($_POST["notes"] ?? "")),
                );
                flash("Destravamento da Gaveta agendado.");
                redirect("financial", ["tab" => "locais"]);
            }
            if ($act === "goal") {
                $target = parse_money_cents((string) ($_POST["target"] ?? "0"));
                $share = isset($_POST["share_with_team"]) ? 1 : 0;
                $base = (string) ($_POST["base_metric"] ?? "efetivada");
                if (!in_array($base, ["prevista", "efetivada"], true)) {
                    $base = "efetivada";
                }
                $month = app_month_in_timezone($cid);
                q(
                    "INSERT INTO pi_financial_goals (clinic_id,month_key,target_cents,base_metric,share_with_team,updated_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE target_cents=VALUES(target_cents), base_metric=VALUES(base_metric), share_with_team=VALUES(share_with_team), updated_by=VALUES(updated_by), updated_at=NOW()",
                    [$cid, $month, $target, $base, $share, $uid],
                );
                audit("meta_financeira_salva", "financeiro", $cid, [
                    "valor" => $target,
                    "base" => $base,
                    "compartilhar" => $share,
                ]);
                flash("Meta mensal atualizada.");
                redirect("financial", ["tab" => "meta"]);
            }
            if ($act === "review_close") {
                financial_review_session(
                    $cid,
                    $uid,
                    (int) ($_POST["session_id"] ?? 0),
                    (string) ($_POST["decision"] ?? "approve"),
                    trim((string) ($_POST["notes"] ?? "")),
                    (string) ($_POST["drawer_unlock_at"] ?? ""),
                );
                flash("Conferência registrada.");
                redirect("financial", ["tab" => "conferencias"]);
            }
            if ($act === "review_opening") {
                financial_review_opening_request(
                    $cid,
                    $uid,
                    (int) ($_POST["session_id"] ?? 0),
                    (string) ($_POST["decision"] ?? "approve"),
                    trim((string) ($_POST["notes"] ?? "")),
                );
                flash("Autorização de abertura registrada.");
                redirect("financial", ["tab" => "conferencias"]);
            }
            if ($act === "daily_consolidate") {
                financial_admin_daily_consolidate($cid, $uid);
                flash("Consolidação do dia registrada.");
                redirect("financial", ["tab" => "painel"]);
            }
            if ($act === "admin_receive") {
                financial_admin_save_receipt($cid, $uid);
                flash("Recebimento registrado.");
                redirect("financial", ["tab" => "painel"]);
            }
            if ($act === "admin_payment") {
                financial_admin_save_payment($cid, $uid);
                flash("Pagamento registrado.");
                redirect("financial", ["tab" => "painel"]);
            }
            if ($act === "admin_transfer") {
                financial_admin_save_transfer($cid, $uid);
                flash("Transferência registrada.");
                redirect("financial", ["tab" => "painel"]);
            }
            if ($act === "safe_payment") {
                financial_admin_save_payment($cid, $uid);
                flash("Pagamento registrado.");
                redirect("financial", ["tab" => "painel"]);
            }
            if ($act === "safe_receipt") {
                financial_admin_save_receipt($cid, $uid);
                flash("Recebimento registrado.");
                redirect("financial", ["tab" => "painel"]);
            }
            if ($act === "bank_account") {
                $name = trim((string) ($_POST["name"] ?? ""));
                if ($name === "") {
                    throw new RuntimeException(
                        "Informe o nome da conta bancária.",
                    );
                }
                q(
                    "INSERT INTO pi_financial_accounts (clinic_id,name,bank_name,account_type,opening_balance_cents,active,created_by,created_at) VALUES (?,?,?,?,0,1,?,NOW())",
                    [
                        $cid,
                        $name,
                        trim((string) ($_POST["bank_name"] ?? "")),
                        "conta_corrente",
                        $uid,
                    ],
                );
                $acc = db_last_insert_id();
                financial_ensure_bank_location($cid, $acc, $uid);
                audit("conta_bancaria_criada", "financeiro", $acc, [
                    "name" => $name,
                ]);
                flash("Banco cadastrado.");
                redirect("financial", ["tab" => "locais"]);
            }
            if ($act === "deposit_bank") {
                $safe = financial_ensure_admin_safe($cid, $uid);
                $acc = (int) ($_POST["account_id"] ?? 0);
                $bank = financial_ensure_bank_location($cid, $acc, $uid);
                $amount = parse_money_cents((string) ($_POST["amount"] ?? "0"));
                if ($bank <= 0) {
                    throw new RuntimeException(
                        "Escolha uma conta bancária válida.",
                    );
                }
                financial_create_movement(
                    $cid,
                    "deposit",
                    $amount,
                    $safe,
                    $bank,
                    null,
                    $uid,
                    "Depósito em conta bancária",
                    "transferencia",
                    trim((string) ($_POST["notes"] ?? "")),
                );
                flash("Depósito registrado.");
                redirect("financial", ["tab" => "locais"]);
            }
        } catch (Throwable $e) {
            error_log(
                "[Prontoo financeiro administrativo] " . $e->getMessage(),
            );
            flash(
                app_public_error_message(
                    $e,
                    "Não foi possível concluir a operação financeira.",
                ),
                "bad",
            );
            redirect("financial", ["tab" => $tab]);
        }
    }
    $pos = financial_global_position($cid);
    $tabs = financial_tabs_html(
        [
            "painel" => ["Painel", "monitoring"],
            "locais" => ["Locais", "account_balance_wallet"],
            "operacoes" => ["Operações", "payments"],
            "conferencias" => ["Conferências", "fact_check"],
            "meta" => ["Meta", "flag"],
        ],
        $tab,
    );
    $content = "";
    if ($tab === "painel") {
        $content =
            financial_admin_balance_kpis_html($pos) .
            financial_admin_daily_consolidation_html($cid) .
            card(
                "<h2>" .
                    icon("receipt_long") .
                    '<span>Movimentos do dia</span></h2><p class="muted">O que entrou, saiu ou foi transferido hoje.</p>' .
                    financial_admin_daily_ledger_timeline($cid),
                "finance-ledger-card finance-panel-ledger-card",
            );
    } elseif ($tab === "consolidacao") {
        $content = financial_admin_daily_conference_panel($cid, $uid);
    } elseif ($tab === "locais") {
        $content = financial_admin_locations_panel($cid, $uid, $pos);
    } elseif ($tab === "operacoes") {
        $content = financial_admin_operations_panel($cid, $uid);
    } elseif ($tab === "conferencias") {
        $content = financial_admin_conferences_panel($cid, $uid);
    } elseif ($tab === "meta") {
        $st = monthly_goal_status($cid);
        $goalPreview =
            '<div class="finance-goal-progress"><div><strong data-goal-percent>' .
            e(number_format((float) $st["percent"], 1, ",", ".")) .
            "%</strong><span data-goal-values>" .
            e(
                (string) ($st["values"] ??
                    money_br((int) ($st["done_cents"] ?? 0)) .
                        " de " .
                        money_br((int) ($st["target_cents"] ?? 0))),
            ) .
            '</span></div><div class="goal-bar"><i data-goal-bar style="width:' .
            max(0, min(100, (float) $st["percent"])) .
            '%"></i></div><small>Base atual: ' .
            e($st["base_label"]) .
            ".</small></div>";
        $goalForm =
            '<form method="post" id="finance-goal-form" class="compact finance-lite-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="goal"><div class="two">' .
            form_row(
                "Meta mensal gerencial",
                input(
                    "target",
                    "text",
                    $st["target_cents"] > 0
                        ? money_br($st["target_cents"])
                        : "",
                    'inputmode="decimal" placeholder="R$ 0,00"',
                ),
            ) .
            select_label(
                "Base da meta",
                "base_metric",
                financial_goal_base_options(),
                $st["base_metric"],
            ) .
            '</div><label class="checkline"><input type="checkbox" name="share_with_team" value="1" ' .
            ($st["share"] ? "checked" : "") .
            "><span>Compartilhar percentual cumprido da meta com a equipe.</span></label>" .
            form_actions("Salvar meta") .
            "</form>";
        $content = card(
            '<h2>Meta</h2><p class="muted">Acompanhe o mês com uma meta simples, sem transformar o Financeiro do Consultório em sistema contábil avançado.</p>' .
                $goalPreview .
                $goalForm,
            "finance-report-card finance-goal-card-panel",
        );
    }
    page(
        "Financeiro",
        page_head(
            "Painel financeiro",
            "Guia operacional para acompanhar o dia, registrar movimentos e conferir os locais do Consultório.",
            $tabs,
        ) . $content,
    );
}
function page_financial(): void
{

    $c = require_can("financial");
    ensure_financial_operational_schema();
    if (financial_is_cashier($c)) {
        financial_cashier_page($c);
        return;
    }
    financial_admin_page($c);
}
