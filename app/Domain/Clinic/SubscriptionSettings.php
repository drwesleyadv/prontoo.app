<?php
declare(strict_types=1);
function default_monthly_price_cents(): int
{
    /*
     * GUIA DE MANUTENÇÃO — default_monthly_price_cents
     * Responsabilidade: Implementa a responsabilidade “default monthly price cents” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_admin_maintenance`, `page_admin_clinics`, `page_signup`, `ensure_clinic_trial_active`, `closure@app/Domain/Clinic/SubscriptionSettings.php:114`, `clinic_subscription_status_card`, `clinic_subscription_cta`, `clinic_subscription_register_claim` e mais 2.
     * Dependências chamadas: `meta_get`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $v = meta_get("default_monthly_price_cents", PRONTOO_MONTHLY_PRICE_CENTS);
    $c = (int) $v;
    return $c > 0 ? $c : PRONTOO_MONTHLY_PRICE_CENTS;
}
function default_trial_days(): int
{
    /*
     * GUIA DE MANUTENÇÃO — default_trial_days
     * Responsabilidade: Implementa a responsabilidade “default trial days” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_admin_maintenance`, `page_admin_clinics`, `page_signup`, `page_onboarding`, `subscription_trial_end_from_start`, `ensure_clinic_trial_active`, `closure@app/Domain/Clinic/SubscriptionSettings.php:114`, `clinic_subscription_status_card` e mais 1.
     * Dependências chamadas: `meta_get`, `meta_set`, `error_log`, `->getMessage`.
     * Efeitos colaterais: gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $v = meta_get("default_trial_days", PRONTOO_TRIAL_DAYS);
    $days = (int) $v;
    $policyRevision = "trial_30_days_2026_07_14";
    $storedRevision = (string) meta_get("trial_days_policy_revision", "");
    if ($days === 90 && $storedRevision !== $policyRevision) {
        try {
            meta_set("default_trial_days", (string) PRONTOO_TRIAL_DAYS);
            meta_set("trial_days_policy_revision", $policyRevision);
        } catch (Throwable $ignored) {
            error_log(
                "[Prontoo recoverable " .
                    __FUNCTION__ .
                    "] " .
                    $ignored->getMessage(),
            );
        }
        return PRONTOO_TRIAL_DAYS;
    }
    if ($days < 0 || $days > 3650) {
        return PRONTOO_TRIAL_DAYS;
    }
    return $days;
}
function trial_period_label(int $days): string
{
    /*
     * GUIA DE MANUTENÇÃO — trial_period_label
     * Responsabilidade: Monta a representação de interface associada a “trial period label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_signup`, `clinic_subscription_status_card`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($days === 90) {
        return "três meses";
    }
    if ($days === 60) {
        return "dois meses";
    }
    if ($days === 30) {
        return "30 dias";
    }
    if ($days === 1) {
        return "1 dia";
    }
    if ($days <= 0) {
        return "sem gratuidade";
    }
    return $days . " dias";
}
function subscription_pix_key(): string
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_pix_key
     * Responsabilidade: Implementa a responsabilidade “subscription pix key” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_admin_maintenance`, `clinic_subscription_cta`.
     * Dependências chamadas: `trim`, `meta_get`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $key = trim((string) meta_get("subscription_pix_key", "pix@prontoo.app"));
    return $key !== "" ? $key : "pix@prontoo.app";
}
function normalize_subscription_pix_key(string $key): string
{
    /*
     * GUIA DE MANUTENÇÃO — normalize_subscription_pix_key
     * Responsabilidade: Transforma e normaliza “normalize subscription pix key” para um formato canônico utilizado pelo restante da aplicação.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_admin_maintenance`.
     * Dependências chamadas: `trim`, `RuntimeException`, `mb_strlen`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $key = trim($key);
    if ($key === "") {
        throw new RuntimeException("Informe a chave Pix da assinatura.");
    }
    if (mb_strlen($key) > 140) {
        throw new RuntimeException(
            "A chave Pix deve ter no máximo 140 caracteres.",
        );
    }
    return $key;
}
function subscription_time_ts(
    null|string|int $value,
    bool $endOfDay = false,
): int {
    /*
     * GUIA DE MANUTENÇÃO — subscription_time_ts
     * Responsabilidade: Implementa a responsabilidade “subscription time ts” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `subscription_trial_end_from_start`, `subscription_trial_is_active`, `ensure_clinic_trial_active`, `closure@app/Domain/Clinic/SubscriptionSettings.php:114`.
     * Dependências chamadas: `app_storage_timestamp`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return app_storage_timestamp($value, $endOfDay);
}
function subscription_trial_end_from_start(
    null|string|int $start = null,
    ?int $days = null,
): int {
    /*
     * GUIA DE MANUTENÇÃO — subscription_trial_end_from_start
     * Responsabilidade: Implementa a responsabilidade “subscription trial end from start” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_signup`, `page_onboarding`, `ensure_clinic_trial_active`, `closure@app/Domain/Clinic/SubscriptionSettings.php:114`.
     * Dependências chamadas: `default_trial_days`, `subscription_time_ts`, `time`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $days = $days ?? default_trial_days();
    if ($days <= 0) {
        $days = PRONTOO_TRIAL_DAYS;
    }
    if ($days <= 0) {
        $days = 30;
    }
    $startTs = subscription_time_ts($start);
    if ($startTs <= 0) {
        $startTs = time();
    }
    return $startTs + $days * 86400;
}
function subscription_trial_is_active(null|string|int $trialEnd): bool
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_trial_is_active
     * Responsabilidade: Avalia ou impõe a regra “subscription trial is active”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_read_only_db`, `clinic_subscription_kind`.
     * Dependências chamadas: `subscription_time_ts`, `time`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ts = subscription_time_ts($trialEnd, true);
    return $ts <= 0 || $ts >= time();
}
function subscription_paid_is_active(
    null|string|int $paidUntil,
    int $clinicId = 0,
    ?array $context = null,
): bool {
    /*
     * GUIA DE MANUTENÇÃO — subscription_paid_is_active
     * Responsabilidade: Avalia ou impõe a regra “subscription paid is active”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_read_only_db`, `ensure_clinic_trial_active`, `closure@app/Domain/Clinic/SubscriptionSettings.php:114`, `clinic_subscription_kind`.
     * Dependências chamadas: `app_date_only_end_timestamp`, `time`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $ts = app_date_only_end_timestamp($paidUntil, $clinicId, $context);
    return $ts > 0 && $ts >= time();
}
function ensure_clinic_trial_active(
    int $clinicId,
    bool $onlyIfOnboardingPending = true,
): void {
    /*
     * GUIA DE MANUTENÇÃO — ensure_clinic_trial_active
     * Responsabilidade: Implementa a responsabilidade “ensure clinic trial active” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_signup`, `page_onboarding`, `prontoo_run`.
     * Dependências chamadas: `has_cfg`, `one`, `subscription_paid_is_active`, `subscription_time_ts`, `time`, `subscription_trial_end_from_start`, `default_trial_days`, `max`, `default_monthly_price_cents`, `q`, `function_exists`, `with_read_only_guard_disabled`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    if ($clinicId <= 0 || !has_cfg()) {
        return;
    }
    $fn = function () use ($clinicId, $onlyIfOnboardingPending): void {
        /*
         * GUIA DE MANUTENÇÃO — closure@app/Domain/Clinic/SubscriptionSettings.php:114
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de domínio e regras de negócio.
         * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `one`, `subscription_paid_is_active`, `subscription_time_ts`, `time`, `subscription_trial_end_from_start`, `default_trial_days`, `max`, `default_monthly_price_cents`, `q`.
         * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados.
         * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
         */
        $cl = one(
            "SELECT id,onboarding_done,created_at,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents FROM pi_clinics WHERE id=? LIMIT 1",
            [$clinicId],
        );
        if (!$cl) {
            return;
        }
        if (
            $onlyIfOnboardingPending &&
            (int) ($cl["onboarding_done"] ?? 0) === 1
        ) {
            return;
        }
        $status = (string) ($cl["subscription_status"] ?? "trial");
        if (
            $status === "exempt" ||
            subscription_paid_is_active($cl["paid_until"] ?? null, $clinicId)
        ) {
            return;
        }
        $startedTs = subscription_time_ts($cl["trial_started_at"] ?? null);
        if ($startedTs <= 0) {
            $startedTs = subscription_time_ts($cl["created_at"] ?? null);
        }
        if ($startedTs <= 0) {
            $startedTs = time();
        }
        $trialTs = subscription_time_ts($cl["trial_ends_at"] ?? null, true);
        $minTrialTs = subscription_trial_end_from_start(
            $startedTs,
            default_trial_days(),
        );
        if ($trialTs < time()) {
            $trialTs = max(
                $minTrialTs,
                subscription_trial_end_from_start(time(), default_trial_days()),
            );
        }
        $price = (int) ($cl["monthly_price_cents"] ?? 0);
        if ($price <= 0) {
            $price = default_monthly_price_cents();
        }
        q(
            "UPDATE pi_clinics SET subscription_status='trial', trial_started_at=?, trial_ends_at=?, monthly_price_cents=?, paid_until=NULL, updated_at=NOW() WHERE id=?",
            [$startedTs, $trialTs, $price, $clinicId],
        );
    };
    if (function_exists("with_read_only_guard_disabled")) {
        with_read_only_guard_disabled($fn);
    } else {
        $fn();
    }
}
function clinic_subscription_kind(array $cl): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_subscription_kind
     * Responsabilidade: Implementa a responsabilidade “clinic subscription kind” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_subscription_status_card`, `clinic_subscription_cta`.
     * Dependências chamadas: `trim`, `subscription_paid_is_active`, `subscription_trial_is_active`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $status = (string) ($cl["subscription_status"] ?? "trial");
    $paid = $cl["paid_until"] ?? null;
    $trial = $cl["trial_ends_at"] ?? null;
    if ($status === "read_only") {
        return "pause";
    }
    if ($status === "exempt") {
        return "active";
    }
    if (
        $status === "active" &&
        ($paid === null ||
            trim((string) $paid) === "" ||
            subscription_paid_is_active($paid, (int) ($cl["id"] ?? 0), $cl))
    ) {
        return "active";
    }
    if (subscription_paid_is_active($paid, (int) ($cl["id"] ?? 0), $cl)) {
        return "active";
    }
    if ($status === "trial" && subscription_trial_is_active($trial)) {
        return "trial";
    }
    return "pause";
}
function clinic_subscription_action_label(string $kind): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_subscription_action_label
     * Responsabilidade: Monta a representação de interface associada a “clinic subscription action label” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_subscription_cta`.
     * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return $kind === "trial"
        ? "Realizar assinatura"
        : ($kind === "pause"
            ? "Reativar assinatura"
            : "Adicionar mais um mês");
}
function clinic_subscription_status_card(array $cl): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_subscription_status_card
     * Responsabilidade: Monta a representação de interface associada a “clinic subscription status card” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_settings`.
     * Dependências chamadas: `clinic_subscription_kind`, `default_monthly_price_cents`, `trim`, `date_br`, `trial_period_label`, `default_trial_days`, `icon`, `e`, `money_br`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $kind = clinic_subscription_kind($cl);
    $price =
        (int) ($cl["monthly_price_cents"] ?? default_monthly_price_cents());
    if ($price <= 0) {
        $price = default_monthly_price_cents();
    }
    $until = trim((string) ($cl["paid_until"] ?? ""));
    $trial = trim((string) ($cl["trial_ends_at"] ?? ""));
    if ($kind === "active") {
        $isExempt = (string) ($cl["subscription_status"] ?? "") === "exempt";
        $title = $isExempt ? "Assinatura isenta" : "Assinatura ativa";
        $status = $isExempt ? "Isento" : "Ativo";
        $ico = $isExempt ? "workspace_premium" : "verified";
        $body = $isExempt
            ? "Este consultório está isento de cobrança e liberado para testes ou uso administrativo."
            : "O consultório está liberado para atendimento, agenda, pessoas, documentos e financeiro.";
        $vigencia =
            $until !== "" ? date_br($until) : "Sem vencimento informado";
        $tone = "ok";
    } elseif ($kind === "trial") {
        $title = "Assinatura ativa";
        $status = "Ativo";
        $ico = "verified";
        $body =
            "O consultório está liberado durante o período inicial. Ao final do prazo, regularize a mensalidade para manter a operação liberada.";
        $vigencia =
            $trial !== ""
                ? date_br($trial)
                : trial_period_label(default_trial_days());
        $tone = "trial";
    } else {
        $title = "Somente leitura";
        $status = "Somente leitura";
        $ico = "lock";
        $body =
            "A consulta aos dados segue disponível. Para voltar a incluir, agendar e movimentar financeiro, regularize a assinatura.";
        $vigencia = "Aguardando pagamento";
        $tone = "pause";
    }
    return '<section class="subscription-status-card subscription-hero ' .
        $kind .
        '">' .
        '<div class="subscription-hero-main">' .
        '<span class="subscription-hero-icon">' .
        icon($ico) .
        "</span>" .
        '<div class="subscription-hero-copy"><span class="eyebrow">Assinaturas</span><h2>' .
        e($title) .
        "</h2><p>" .
        e($body) .
        "</p></div>" .
        "</div>" .
        '<div class="subscription-metric-row" aria-label="Resumo da assinatura">' .
        '<span class="subscription-metric ' .
        $tone .
        '"><small>Status</small><strong>' .
        e($status) .
        "</strong></span>" .
        '<span class="subscription-metric"><small>Mensalidade</small><strong>' .
        e(money_br($price)) .
        "</strong></span>" .
        '<span class="subscription-metric"><small>Vigência</small><strong>' .
        e($vigencia) .
        "</strong></span>" .
        "</div>" .
        "</section>";
}
function subscription_payment_proof_guard(int $cid, int $uid): void
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_guard
     * Responsabilidade: Avalia ou impõe a regra “subscription payment proof guard”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `subscription_payment_proof_upload`.
     * Dependências chamadas: `security_rate_limit`, `security_client_bucket`, `security_ip_bucket`, `security_value_bucket`, `RuntimeException`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (
        security_rate_limit(security_client_bucket("payment_proof"), 8, 3600) ||
        security_rate_limit(security_ip_bucket("payment_proof"), 30, 3600) ||
        security_rate_limit(
            security_value_bucket("payment_proof_clinic", (string) $cid),
            20,
            3600,
        )
    ) {
        throw new RuntimeException(
            "Muitas tentativas de envio de comprovante. Aguarde alguns instantes.",
        );
    }
}
function subscription_payment_proof_validate_image(
    string $tmp,
    string $mime,
): array {
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_validate_image
     * Responsabilidade: Avalia ou impõe a regra “subscription payment proof validate image”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `subscription_payment_proof_upload`.
     * Dependências chamadas: `getimagesize`, `RuntimeException`, `strtolower`, `trim`, `in_array`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $info = @getimagesize($tmp);
    if (!$info || empty($info[0]) || empty($info[1])) {
        throw new RuntimeException("Imagem inválida.");
    }
    if ((int) $info[0] > 6000 || (int) $info[1] > 6000) {
        throw new RuntimeException("Imagem muito grande em dimensões.");
    }
    $detectedMime = strtolower(trim((string) ($info["mime"] ?? "")));
    if (!in_array($detectedMime, ["image/jpeg", "image/png", "image/webp"], true)) {
        throw new RuntimeException("Formato de imagem não permitido.");
    }
    if ($detectedMime !== $mime) {
        throw new RuntimeException(
            "O conteúdo do comprovante não confere com o formato informado.",
        );
    }
    return [(int) $info[0], (int) $info[1], $detectedMime];
}
function subscription_payment_proof_reencode_image(
    string $tmp,
    string $dest,
    string $mime,
): bool {
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_reencode_image
     * Responsabilidade: Implementa a responsabilidade “subscription payment proof reencode image” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `subscription_payment_proof_upload`.
     * Dependências chamadas: `function_exists`, `imagecreatefromjpeg`, `imagecreatefrompng`, `imagecreatefromwebp`, `imagejpeg`, `is_resource`, `imagedestroy`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (!function_exists("imagejpeg")) {
        return false;
    }
    $img = null;
    if ($mime === "image/jpeg" && function_exists("imagecreatefromjpeg")) {
        $img = @imagecreatefromjpeg($tmp);
    } elseif ($mime === "image/png" && function_exists("imagecreatefrompng")) {
        $img = @imagecreatefrompng($tmp);
    } elseif (
        $mime === "image/webp" &&
        function_exists("imagecreatefromwebp")
    ) {
        $img = @imagecreatefromwebp($tmp);
    }
    if (!$img) {
        return false;
    }
    $ok = @imagejpeg($img, $dest, 88);
    if (is_resource($img) || $img instanceof GdImage) {
        @imagedestroy($img);
    }
    return (bool) $ok;
}
function subscription_payment_proof_validate_pdf(string $tmp): void
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_validate_pdf
     * Responsabilidade: Avalia ou impõe a regra “subscription payment proof validate pdf”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `subscription_payment_proof_upload`.
     * Dependências chamadas: `fopen`, `RuntimeException`, `fread`, `fclose`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: acessa o sistema de arquivos; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $fh = @fopen($tmp, "rb");
    if (!$fh) {
        throw new RuntimeException("Não foi possível ler o PDF.");
    }
    $head = (string) fread($fh, 5);
    fclose($fh);
    if ($head !== "%PDF-") {
        throw new RuntimeException("PDF inválido.");
    }
}
function subscription_payment_proof_storage(int $cid, bool $image = false): array
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_storage
     * Responsabilidade: Resolve comprovantes no SSD, separando imagens sob `/ssd/img/`.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `subscription_payment_proof_upload`.
     * Dependências chamadas: `RuntimeException`, `storage_path`, `is_link`, `is_dir`, `mkdir`, `chmod`, `realpath`, `str_starts_with`.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Efeitos colaterais: acessa o sistema de arquivos; pode interromper o fluxo por exceção.
     * Cuidado 1: Imagens enviadas devem permanecer exclusivamente sob `/ssd/img/`.
     */
    if ($cid <= 0) {
        throw new RuntimeException("Consultório inválido para o comprovante.");
    }
    $relativeRoot = $image ? "ssd/img/payment-proofs" : "ssd/payment-proofs";
    $root = $image ? storage_path("img/payment-proofs") : storage_path("payment-proofs");
    $directory = $root . "/clinic-" . $cid;
    foreach ([$root, $directory] as $path) {
        if (is_link($path)) {
            throw new RuntimeException("Diretório de comprovantes inválido.");
        }
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException("Não foi possível preparar o armazenamento do comprovante.");
        }
        @chmod($path, 0750);
    }
    $resolvedRoot = realpath($root);
    $resolvedDirectory = realpath($directory);
    if ($resolvedRoot === false || $resolvedDirectory === false || ($resolvedDirectory !== $resolvedRoot && !str_starts_with($resolvedDirectory, $resolvedRoot . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException("Diretório de comprovantes inválido.");
    }
    return ["absolute" => $resolvedDirectory, "relative" => $relativeRoot . "/clinic-" . $cid];
}

function subscription_payment_proof_upload(int $cid, int $uid): ?string
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_upload
     * Responsabilidade: Implementa a responsabilidade “subscription payment proof upload” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_subscription_register_claim`.
     * Dependências chamadas: `is_array`, `RuntimeException`, `subscription_payment_proof_guard`, `is_uploaded_file`, `strtolower`, `pathinfo`, `function_exists`, `finfo_open`, `finfo_file`, `finfo_close`, `subscription_payment_proof_validate_pdf`, `subscription_payment_proof_validate_image` e mais 8.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Estado externo lido: `$_FILES`.
     * Efeitos colaterais: consome dados da requisição HTTP; acessa o sistema de arquivos; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (
        empty($_FILES["payment_proof"]) ||
        !is_array($_FILES["payment_proof"])
    ) {
        return null;
    }
    $f = $_FILES["payment_proof"];
    if ((int) ($f["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int) $f["error"] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Falha no envio do comprovante.");
    }
    subscription_payment_proof_guard($cid, $uid);
    $tmp = (string) ($f["tmp_name"] ?? "");
    if ($tmp === "" || !is_uploaded_file($tmp)) {
        throw new RuntimeException("Upload inválido.");
    }
    $size = (int) ($f["size"] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException("Comprovante deve ter até 5 MB.");
    }
    $orig = (string) ($f["name"] ?? "comprovante");
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = [
        "pdf" => "application/pdf",
        "jpg" => "image/jpeg",
        "jpeg" => "image/jpeg",
        "png" => "image/png",
        "webp" => "image/webp",
    ];
    if (!isset($allowed[$ext])) {
        throw new RuntimeException(
            "Formato de comprovante não permitido. Use PDF, JPG, PNG ou WEBP.",
        );
    }
    $finfo = function_exists("finfo_open")
        ? finfo_open(FILEINFO_MIME_TYPE)
        : null;
    $mime = $finfo
        ? (string) finfo_file($finfo, $tmp)
        : (string) ($f["type"] ?? "");
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($mime === "" || $mime === "application/octet-stream") {
        $mime = $allowed[$ext];
    }
    if (
        $mime !== $allowed[$ext] &&
        !($ext === "jpg" && $mime === "image/jpeg")
    ) {
        throw new RuntimeException(
            "O conteúdo do comprovante não confere com a extensão.",
        );
    }
    if ($mime === "application/pdf") {
        subscription_payment_proof_validate_pdf($tmp);
    } else {
        subscription_payment_proof_validate_image($tmp, $mime);
    }
    $storage = subscription_payment_proof_storage(
        $cid,
        $mime !== "application/pdf",
    );
    $dir = (string) $storage["absolute"];
    $baseName =
        "proof_" .
        $uid .
        "_" .
        date("Ymd_His") .
        "_" .
        bin2hex(random_bytes(8));
    if ($mime === "application/pdf") {
        $storedExt = "pdf";
        $name = $baseName . "." . $storedExt;
        $dest = $dir . "/" . $name;
        $stored = move_uploaded_file($tmp, $dest);
    } else {
        $storedExt = "jpg";
        $name = $baseName . ".jpg";
        $dest = $dir . "/" . $name;
        $stored = subscription_payment_proof_reencode_image($tmp, $dest, $mime);
        if (!$stored) {
            @unlink($dest);
            $storedExt = match ($mime) {
                "image/png" => "png",
                "image/webp" => "webp",
                default => "jpg",
            };
            $name = $baseName . "." . $storedExt;
            $dest = $dir . "/" . $name;
            $stored = move_uploaded_file($tmp, $dest);
        }
    }
    if (!$stored) {
        @unlink($dest);
        throw new RuntimeException("Não foi possível salvar o comprovante.");
    }
    @chmod($dest, 0640);
    return (string) $storage["relative"] . "/" . $name;
}
function subscription_payment_proof_absolute_path(?string $proofPath): ?string
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_absolute_path
     * Responsabilidade: Resolve comprovantes atuais e caminhos legados já gravados.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `subscription_payment_delete_proof`, `page_admin_payment_proof`.
     * Dependências chamadas: `trim`, `str_replace`, `str_starts_with`, `str_contains`, `realpath`, `storage_path`, `is_file`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Preserve a compatibilidade de leitura de `storage/payment-proofs/`.
     */
    $proofPath = trim((string) $proofPath);
    if ($proofPath === "") {
        return null;
    }
    $proofPath = str_replace("\", "/", $proofPath);
    if (str_contains($proofPath, "..")) {
        return null;
    }
    $roots = [
        "ssd/img/payment-proofs/" => storage_path("img/payment-proofs"),
        "ssd/payment-proofs/" => storage_path("payment-proofs"),
        "storage/payment-proofs/" => storage_path("payment-proofs"),
    ];
    $matchedPrefix = null;
    $rootPath = null;
    foreach ($roots as $prefix => $candidateRoot) {
        if (str_starts_with($proofPath, $prefix)) {
            $matchedPrefix = $prefix;
            $rootPath = $candidateRoot;
            break;
        }
    }
    if ($matchedPrefix === null || $rootPath === null) {
        return null;
    }
    $root = realpath($rootPath);
    $relative = substr($proofPath, strlen($matchedPrefix));
    if ($root === false || $relative === false || $relative === "") {
        return null;
    }
    $candidate = $root . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relative);
    $full = realpath($candidate);
    if ($full === false || !is_file($full) || !str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $full;
}
function subscription_payment_delete_proof(?string $proofPath): bool
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_delete_proof
     * Responsabilidade: Valida e executa a mutação “subscription payment delete proof”, preservando as invariantes do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_admin_painel`, `clinic_subscription_register_claim`.
     * Dependências chamadas: `subscription_payment_proof_absolute_path`, `is_file`, `unlink`, `error_log`.
     * Efeitos colaterais: acessa o sistema de arquivos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $full = subscription_payment_proof_absolute_path($proofPath);
    if ($full === null) {
        return true;
    }
    if (!is_file($full)) {
        return true;
    }
    $ok = @unlink($full);
    if (!$ok) {
        error_log(
            "[Prontoo payment proof delete] Falha ao excluir comprovante: " .
                $full,
        );
    }
    return $ok;
}
function subscription_payment_proof_view_link(array $payment): string
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_proof_view_link
     * Responsabilidade: Monta a representação de interface associada a “subscription payment proof view link” sem alterar o contrato visual externo.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_admin_painel`.
     * Dependências chamadas: `trim`, `href`, `action_summary_label`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if (trim((string) ($payment["proof_path"] ?? "")) === "") {
        return "";
    }
    return '<a class="ghost small" target="_blank" rel="noopener" href="' .
        href("admin_payment_proof", ["payment_id" => (int) $payment["id"]]) .
        '">' .
        action_summary_label("Visualizar comprovante", "visibility") .
        "</a>";
}
function page_admin_payment_proof(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_admin_payment_proof
     * Responsabilidade: Coordena a rota e renderiza a tela “page admin payment proof”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `require_can`, `one`, `trim`, `flash`, `redirect`, `subscription_payment_proof_absolute_path`, `is_file`, `audit`, `strtolower`, `pathinfo`, `ob_get_level`, `ob_end_clean` e mais 3.
     * Estado externo lido: `$_GET`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     * Cuidado 2: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     * Cuidado 3: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    require_can("admin_painel");
    $pid = (int) ($_GET["payment_id"] ?? 0);
    $p =
        $pid > 0
            ? one(
                "SELECT sp.id,sp.clinic_id,sp.status,sp.proof_path,c.display_name FROM pi_subscription_payments sp JOIN pi_clinics c ON c.id=sp.clinic_id WHERE sp.id=?",
                [$pid],
            )
            : null;
    if (!$p || trim((string) ($p["proof_path"] ?? "")) === "") {
        flash("Comprovante não encontrado para este pagamento.", "bad");
        redirect("admin_painel");
    }
    $full = subscription_payment_proof_absolute_path((string) $p["proof_path"]);
    if ($full === null || !is_file($full)) {
        flash("O arquivo do comprovante não está mais disponível.", "bad");
        redirect("admin_painel");
    }
    audit(
        "assinatura_comprovante_visualizado",
        "assinatura",
        (int) $p["clinic_id"],
        [
            "pagamento_id" => $pid,
            "audit_body" =>
                "Desenvolvedor abriu o comprovante enviado para conferência do pagamento.",
        ],
    );
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        "pdf" => "application/pdf",
        "png" => "image/png",
        "jpg", "jpeg" => "image/jpeg",
        "webp" => "image/webp",
        default => "application/octet-stream",
    };
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header("Content-Type: " . $mime);
    header("Content-Length: " . (string) filesize($full));
    header("Cache-Control: private, no-store, max-age=0");
    header("X-Content-Type-Options: nosniff");
    header(
        'Content-Disposition: attachment; filename="comprovante-prontoo-' .
            $pid .
            "." .
            $ext .
            '"',
    );
    readfile($full);
    exit();
}
function clinic_subscription_pending_payment(int $cid): ?array
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_subscription_pending_payment
     * Responsabilidade: Implementa a responsabilidade “clinic subscription pending payment” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_subscription_cta`, `clinic_subscription_register_claim`.
     * Dependências chamadas: `one`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    try {
        $row = one(
            "SELECT id,amount_cents,proof_path,applied_until,created_at FROM pi_subscription_payments WHERE clinic_id=? AND status='pending_admin' ORDER BY id DESC LIMIT 1",
            [$cid],
        );
        return $row ?: null;
    } catch (Throwable $e) {
        error_log("[Prontoo subscription pending] " . $e->getMessage());
        return null;
    }
}
function subscription_payment_is_proof_review(array $payment): bool
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_payment_is_proof_review
     * Responsabilidade: Avalia ou impõe a regra “subscription payment is proof review”, falhando de forma controlada quando a pré-condição não é satisfeita.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_admin_painel`, `clinic_subscription_cta`.
     * Dependências chamadas: `trim`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return trim((string) ($payment["proof_path"] ?? "")) !== "";
}
function subscription_trust_release_until(): string
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_trust_release_until
     * Responsabilidade: Implementa a responsabilidade “subscription trust release until” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_subscription_register_claim`.
     * Dependências chamadas: `date`, `strtotime`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    return date("Y-m-d", strtotime("+" . PRONTOO_TRUST_RELEASE_DAYS . " days"));
}
function subscription_renewal_until(array $cl): string
{
    /*
     * GUIA DE MANUTENÇÃO — subscription_renewal_until
     * Responsabilidade: Implementa a responsabilidade “subscription renewal until” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_subscription_register_claim`.
     * Dependências chamadas: `trim`, `strtotime`, `date`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $base = trim((string) ($cl["paid_until"] ?? ""));
    $baseTs = $base !== "" ? strtotime($base . " 23:59:59") : 0;
    $startDate =
        $baseTs !== false && $baseTs >= strtotime("today")
            ? $base
            : date("Y-m-d");
    return date("Y-m-d", strtotime($startDate . " +30 days"));
}
function later_date(?string $a, ?string $b): string
{
    /*
     * GUIA DE MANUTENÇÃO — later_date
     * Responsabilidade: Implementa a responsabilidade “later date” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `clinic_subscription_register_claim`.
     * Dependências chamadas: `trim`, `strtotime`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $a = trim((string) $a);
    $b = trim((string) $b);
    if ($a === "") {
        return $b;
    }
    if ($b === "") {
        return $a;
    }
    $ta = strtotime($a . " 23:59:59");
    $tb = strtotime($b . " 23:59:59");
    if ($ta === false) {
        return $b;
    }
    if ($tb === false) {
        return $a;
    }
    return $ta >= $tb ? $a : $b;
}
function clinic_subscription_cta(array $cl, string $tab = "assinatura"): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_subscription_cta
     * Responsabilidade: Implementa a responsabilidade “clinic subscription cta” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_settings`.
     * Dependências chamadas: `clinic_subscription_pending_payment`, `default_monthly_price_cents`, `subscription_payment_is_proof_review`, `icon`, `e`, `card`, `clinic_subscription_kind`, `clinic_subscription_action_label`, `trim`, `strtotime`, `time`, `subscription_pix_key` e mais 5.
     * Efeitos colaterais: produz conteúdo de saída.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    if ($tab !== "assinatura") {
        return "";
    }
    $cid = (int) ($cl["id"] ?? 0);
    $pending = $cid > 0 ? clinic_subscription_pending_payment($cid) : null;
    $price =
        (int) ($cl["monthly_price_cents"] ?? default_monthly_price_cents());
    if ($price <= 0) {
        $price = default_monthly_price_cents();
    }
    if ($pending) {
        if (subscription_payment_is_proof_review($pending)) {
            $ico = "pending_actions";
            $eyebrow = "Comprovante em análise";
            $title = "Comprovante recebido";
            $body =
                "O arquivo foi enviado para conferência do Desenvolvedor. Enquanto isso, acompanhe o retorno por Avisos.";
            $pill = "Aguardando conferência";
        } else {
            $ico = "task_alt";
            $eyebrow = "Pagamento informado";
            $title = "Acesso liberado em confiança";
            $body =
                "Recebemos a confirmação de pagamento e a assinatura foi liberada enquanto a transação é conferida.";
            $pill = "Em conferência";
        }
        $msg =
            '<div class="subscription-pending-friendly subscription-state-card">' .
            '<span class="subscription-hero-icon">' .
            icon($ico) .
            "</span>" .
            '<div class="subscription-state-copy"><span class="eyebrow">' .
            e($eyebrow) .
            "</span><h3>" .
            e($title) .
            "</h3><p>" .
            e($body) .
            "</p></div>" .
            '<span class="subscription-state-pill">' .
            icon("schedule") .
            "<span>" .
            e($pill) .
            "</span></span>" .
            "</div>";
        return card(
            $msg,
            "subscription-payment-card subscription-payment-soft only-signature-screen subscription-payment-pending",
        );
    }
    $kind = clinic_subscription_kind($cl);
    $label = clinic_subscription_action_label($kind);
    $blocked = trim((string) ($cl["subscription_trust_blocked_until"] ?? ""));
    $blockedActive = $blocked !== "" && strtotime($blocked) > time();
    $needProof = $blockedActive;
    $headline = $needProof ? "Enviar comprovante" : $label;
    $intro = $needProof
        ? "Envie o comprovante do Pix para conferência. A liberação ocorre após a aprovação administrativa."
        : ($kind === "active"
            ? "Faça o Pix e confirme o pagamento para acrescentar mais 30 dias à assinatura."
            : "Faça o Pix e confirme o pagamento para reativar a operação do consultório.");
    $pixKey = subscription_pix_key();
    $copyStatus =
        '<span class="pix-copy-status" data-pix-copy-status aria-live="polite"></span>';
    $pixBox =
        '<button type="button" class="pix-key-box subscription-pix-copy" data-copy-pix-key data-pix-value="' .
        e($pixKey) .
        '" title="Clique para copiar a chave Pix">' .
        '<span class="pix-key-symbol">' .
        pix_symbol() .
        "</span>" .
        '<span class="pix-key-text"><small>Chave Pix</small><strong>' .
        e($pixKey) .
        "</strong>" .
        $copyStatus .
        "</span>" .
        '<span class="pix-key-value" aria-label="Valor da assinatura mensal">' .
        e(money_br($price)) .
        "</span>" .
        "</button>";
    $steps =
        '<div class="subscription-steps" aria-label="Etapas para regularizar a assinatura">' .
        '<div class="subscription-step"><span>1</span><p>Faça o Pix no valor indicado.</p></div>' .
        '<div class="subscription-step"><span>2</span><p>' .
        ($needProof
            ? "Anexe o comprovante para análise."
            : "Marque a confirmação de pagamento.") .
        "</p></div>" .
        '<div class="subscription-step"><span>3</span><p>' .
        ($needProof
            ? "Aguarde a conferência do suporte."
            : "A liberação ocorre em confiança enquanto conferimos a transação.") .
        "</p></div>" .
        "</div>";
    $html =
        '<form method="post" enctype="multipart/form-data" class="subscription-payment-box subscription-payment-friendly subscription-checkout" data-subscription-payment-form>' .
        csrf_field() .
        '<input type="hidden" name="act" value="subscription_claim"><input type="hidden" name="tab" value="assinatura">' .
        '<div class="subscription-payment-head subscription-checkout-head">' .
        '<div class="subscription-payment-icon">' .
        pix_symbol() .
        "</div>" .
        '<div><span class="eyebrow">Pagamento Pix</span><h3>' .
        e($headline) .
        "</h3><p>" .
        e($intro) .
        "</p></div>" .
        "</div>" .
        '<div class="subscription-checkout-grid"><div class="subscription-pix-panel"><h4>Dados do pagamento</h4>' .
        $pixBox .
        "</div>" .
        $steps .
        "</div>";
    if ($needProof) {
        $html .=
            '<div class="subscription-confirm-panel">' .
            '<p class="subscription-note">Como um pagamento anterior não foi confirmado, nesta etapa pediremos o comprovante. Após o envio, a administração receberá uma Ação Recomendada para visualizar, aprovar ou recusar o arquivo.</p>' .
            form_row(
                "Comprovante de pagamento",
                input(
                    "payment_proof",
                    "file",
                    "",
                    'accept="application/pdf,image/png,image/jpeg,image/webp" required',
                ),
            ) .
            "</div>";
    } else {
        $html .=
            '<div class="subscription-confirm-panel">' .
            '<label class="checkline subscription-checkline"><input type="checkbox" name="already_paid" value="1" data-payment-paid-toggle required><span>Já paguei o Pix informado acima.</span></label>' .
            '<div data-payment-paid-fields hidden class="subscription-paid-fields">' .
            '<label class="checkline"><input type="checkbox" name="paid_own_account" value="1" data-own-account-toggle><span>Enviei de uma conta no meu próprio nome.</span></label>' .
            "<div data-holder-field>" .
            form_row(
                "Quem é o titular da conta que fez o Pix?",
                input(
                    "payment_holder_name",
                    "text",
                    "",
                    'maxlength="180" placeholder="Informe quando a conta não estiver no próprio nome"',
                ),
            ) .
            "</div>" .
            "</div>" .
            "</div>";
    }
    $html .=
        '<div class="form-actions subscription-actions"><button class="primary" type="submit">' .
        icon($needProof ? "upload_file" : "check_circle") .
        "<span>" .
        ($needProof ? "Enviar comprovante" : "Confirmar pagamento") .
        "</span></button></div></form>";
    return card(
        $html,
        "subscription-payment-card subscription-payment-soft only-signature-screen",
    );
}
function clinic_subscription_rejected_notice(
    int $cid,
    bool $proofRejected = false,
): void {
    /*
     * GUIA DE MANUTENÇÃO — clinic_subscription_rejected_notice
     * Responsabilidade: Implementa a responsabilidade “clinic subscription rejected notice” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_admin_painel`.
     * Dependências chamadas: `one`, `q`, `error_log`, `->getMessage`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     */
    try {
        $cl =
            one(
                "SELECT owner_user_id,manager_user_id FROM pi_clinics WHERE id=?",
                [$cid],
            ) ?:
            [];
        $uid = (int) ($cl["owner_user_id"] ?? 0);
        if ($uid <= 0) {
            $uid = (int) ($cl["manager_user_id"] ?? 0);
        }
        if ($uid <= 0) {
            return;
        }
        $title = $proofRejected
            ? "Comprovante não aprovado"
            : "Pagamento não confirmado";
        $body = $proofRejected
            ? "O comprovante enviado não foi aprovado. Envie um novo comprovante de pagamento na tela Meu Consultório > Assinatura para nova conferência."
            : "O pagamento informado não foi confirmado. Envie o arquivo do comprovante de pagamento na tela Meu Consultório > Assinatura para nova conferência.";
        q(
            "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_user_id,created_at) VALUES (?,?,?,?,?,?,NOW())",
            [$cid, $title, $body, 1, "user", $uid],
        );
    } catch (Throwable $e) {
        error_log("[Prontoo subscription notice] " . $e->getMessage());
    }
}
function clinic_subscription_register_claim(
    int $cid,
    int $uid,
    array $cl,
): string {
    /*
     * GUIA DE MANUTENÇÃO — clinic_subscription_register_claim
     * Responsabilidade: Valida e executa a mutação “clinic subscription register claim”, preservando as invariantes do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_settings`.
     * Dependências chamadas: `clinic_subscription_pending_payment`, `RuntimeException`, `trim`, `strtotime`, `time`, `subscription_payment_proof_upload`, `default_monthly_price_cents`, `subscription_renewal_until`, `subscription_trust_release_until`, `later_date`, `pdo`, `->inTransaction` e mais 7.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Estado externo lido: `$_POST`.
     * Efeitos colaterais: acessa a camada de persistência; pode gravar ou remover dados; consome dados da requisição HTTP; gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Mantenha o evento de auditoria depois da confirmação da operação para não registrar uma ação que falhou.
     */
    if (clinic_subscription_pending_payment($cid)) {
        throw new RuntimeException(
            "Continue trabalhando enquanto confirmamos o recebimento. Nenhuma providência é necessária neste momento.",
        );
    }
    $blocked = trim((string) ($cl["subscription_trust_blocked_until"] ?? ""));
    $blockedActive = $blocked !== "" && strtotime($blocked) > time();
    $proof = null;
    if ($blockedActive) {
        $proof = subscription_payment_proof_upload($cid, $uid);
        if ($proof === null) {
            throw new RuntimeException(
                "Envie o comprovante de pagamento para continuar.",
            );
        }
    } else {
        if (empty($_POST["already_paid"])) {
            throw new RuntimeException(
                "Marque a opção Já paguei para concluir.",
            );
        }
    }
    $own = isset($_POST["paid_own_account"]) ? 1 : 0;
    $holder = trim((string) ($_POST["payment_holder_name"] ?? ""));
    if (!$blockedActive && !$own && $holder === "") {
        throw new RuntimeException(
            "Informe o nome do titular da conta usada no pagamento.",
        );
    }
    $price =
        (int) ($cl["monthly_price_cents"] ?? default_monthly_price_cents());
    if ($price <= 0) {
        $price = default_monthly_price_cents();
    }
    $until = subscription_renewal_until($cl);
    $trustUntil = subscription_trust_release_until();
    $provisionalUntil = later_date(
        (string) ($cl["paid_until"] ?? ""),
        $trustUntil,
    );
    $db = pdo();
    $startedTx = false;
    try {
        if (!$db->inTransaction()) {
            db_begin_transaction();
            $startedTx = true;
        }
        if ($blockedActive) {
            q(
                "INSERT INTO pi_subscription_payments (clinic_id,created_by,amount_cents,status,trust_release,account_self,account_holder_name,proof_path,applied_until,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    $uid,
                    $price,
                    "pending_admin",
                    0,
                    $own,
                    $holder !== "" ? $holder : null,
                    $proof,
                    $until,
                ],
            );
            $pid = db_last_insert_id();
            audit("assinatura_comprovante_enviado", "assinatura", $cid, [
                "pagamento_id" => $pid,
                "valor" => $price,
                "renovacao_ate" => $until,
                "audit_body" =>
                    "Responsável enviou comprovante após recusa anterior. Uma nova Ação Recomendada foi disponibilizada para o Desenvolvedor visualizar, aprovar ou recusar o comprovante. O consultório permanece em Somente Leitura até aprovação administrativa.",
            ]);
            $message =
                "Comprovante enviado. A administração vai conferir o arquivo para liberar a assinatura.";
        } else {
            q(
                "UPDATE pi_clinics SET active=1, subscription_status='active', paid_until=?, subscription_trust_blocked_until=NULL, subscription_last_payment_claim_at=NOW(), updated_at=NOW() WHERE id=?",
                [$provisionalUntil, $cid],
            );
            q(
                "INSERT INTO pi_subscription_payments (clinic_id,created_by,amount_cents,status,trust_release,account_self,account_holder_name,proof_path,applied_until,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())",
                [
                    $cid,
                    $uid,
                    $price,
                    "pending_admin",
                    1,
                    $own,
                    $holder !== "" ? $holder : null,
                    null,
                    $until,
                ],
            );
            $pid = db_last_insert_id();
            audit("assinatura_pagamento_informado", "assinatura", $cid, [
                "pagamento_id" => $pid,
                "valor" => $price,
                "liberado_ate" => $provisionalUntil,
                "renovacao_ate" => $until,
                "audit_body" =>
                    "Responsável informou pagamento da assinatura. Uma Ação Recomendada foi disponibilizada para confirmação ou recusa pelo Desenvolvedor. O acesso operacional foi liberado automaticamente em confiança por prazo operacional interno enquanto aguarda conferência administrativa.",
            ]);
            $message =
                "Obrigado. Já liberamos sua assinatura em confiança enquanto o banco confirma sua transação. Aproveite!";
        }
        if ($startedTx && $db->inTransaction()) {
            db_commit();
        }
        return $message;
    } catch (Throwable $e) {
        if ($startedTx && $db->inTransaction()) {
            db_rollback();
        }
        if ($proof !== null) {
            subscription_payment_delete_proof($proof);
        }
        throw $e;
    }
}
function clinic_settings_nav(string $tab): string
{
    /*
     * GUIA DE MANUTENÇÃO — clinic_settings_nav
     * Responsabilidade: Implementa a responsabilidade “clinic settings nav” dentro do módulo de domínio e regras de negócio.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: `page_settings`.
     * Dependências chamadas: `href`, `icon`, `e`.
     * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
     * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
     */
    $tabs = [
        "perfil" => ["Identificação", "home_health"],
        "setores" => ["Departamentos", "corporate_fare"],
        "visual" => ["Aparência", "palette"],
        "assinatura" => ["Assinatura", "credit_card"],
    ];
    $h = "";
    foreach ($tabs as $k => $v) {
        $cls = $tab === $k ? " primary" : " ghost";
        $h .=
            '<a class="' .
            $cls .
            ' small" href="' .
            href("settings", ["tab" => $k]) .
            '">' .
            icon($v[1]) .
            "<span>" .
            e($v[0]) .
            "</span></a>";
    }
    return $h;
}
function page_settings(): void
{
    /*
     * GUIA DE MANUTENÇÃO — page_settings
     * Responsabilidade: Coordena a rota e renderiza a tela “page settings”, reunindo validação, leitura de dados e resposta HTTP.
     * Local arquitetural: app/Domain/Clinic/SubscriptionSettings.php (domínio e regras de negócio).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `require_can`, `seed_clinic_roles`, `trim`, `function_exists`, `mb_strtolower`, `strtolower`, `in_array`, `one`, `flash`, `redirect`, `strtoupper`, `br_states` e mais 33.
     * Classes ou serviços instanciados: `RuntimeException`.
     * Estado externo lido: `$_GET`, `$_POST`, `$_SERVER`.
     * Efeitos colaterais: acessa a camada de persistência; consulta dados persistidos; pode gravar ou remover dados; consome dados da requisição HTTP; controla cabeçalhos, redirecionamento ou resposta HTTP; produz conteúdo de saída; gera trilha de auditoria ou telemetria; pode interromper o fluxo por exceção.
     * Cuidado 1: Ao alterar a gravação, mantenha o escopo `clinic_id`, a atomicidade e a auditoria exigida pelo Guardião.
     * Cuidado 2: Qualquer nova ação protegida precisa de contrato exato no `ActionCatalog`; ações ausentes devem continuar falhando fechadas.
     * Cuidado 3: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    $c = require_can("settings");
    $cid = (int) $c["clinic_id"];
    $uid = (int) $c["user"]["id"];
    seed_clinic_roles($cid);
    $tab = (string) ($_GET["tab"] ?? ($_POST["tab"] ?? "perfil"));
    $tab = trim(
        function_exists("mb_strtolower")
            ? mb_strtolower($tab, "UTF-8")
            : strtolower($tab),
    );
    if (!in_array($tab, ["perfil", "setores", "visual", "assinatura"], true)) {
        $tab = "perfil";
    }
    $cl = one(
        "SELECT id,display_name,legal_name,legal_document,phone,responsible_profession,clinic_icon,accent_color,address_line,address_state,address_city,address_city_ibge,timezone,workflow_note,onboarding_done,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents,subscription_trust_blocked_until,subscription_last_payment_claim_at FROM pi_clinics WHERE id=?",
        [$cid],
    );
    if (!$cl) {
        flash("Consultório não encontrado.", "bad");
        redirect("appointments");
    }
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        $act = (string) ($_POST["act"] ?? "profile");
        try {
            if ($act === "profile") {
                $uf = strtoupper(
                    trim((string) ($_POST["address_state"] ?? "")),
                );
                $city = trim((string) ($_POST["address_city"] ?? ""));
                $cityIbge = (int) ($_POST["address_city_ibge"] ?? 0);
                if (
                    !isset(br_states()[$uf]) ||
                    $city === "" ||
                    $cityIbge <= 0
                ) {
                    throw new RuntimeException(
                        "Escolha uma cidade da lista do IBGE.",
                    );
                }
                $tz = timezone_from_location($uf, $city);
                $profession = normalize_profession(
                    (string) ($_POST["responsible_profession"] ?? ""),
                );
                $legalDoc = only_digits((string) $_POST["legal_document"]);
                if (strlen($legalDoc) === 11 && !valid_cpf($legalDoc)) {
                    throw new RuntimeException("Este CPF não existe.");
                }
                if (strlen($legalDoc) === 14 && !valid_cnpj($legalDoc)) {
                    throw new RuntimeException(
                        "Informe CNPJ válido para o consultório.",
                    );
                }
                if (!in_array(strlen($legalDoc), [11, 14], true)) {
                    throw new RuntimeException(
                        "Informe CPF ou CNPJ válido para o consultório.",
                    );
                }
                q(
                    "UPDATE pi_clinics SET display_name=?, legal_name=?, legal_document=?, phone=?, responsible_profession=?, address_line=?, address_state=?, address_city=?, address_city_ibge=?, timezone=?, updated_at=NOW() WHERE id=?",
                    [
                        trim((string) $_POST["display_name"]),
                        trim((string) $_POST["legal_name"]),
                        $legalDoc,
                        phone_br((string) $_POST["phone"]),
                        $profession,
                        trim((string) ($_POST["address_line"] ?? "")),
                        $uf,
                        $city,
                        $cityIbge,
                        $tz,
                        $cid,
                    ],
                );
                audit("consultorio_atualizado", "consultorio", $cid, [
                    "audit_body" =>
                        "Identificação cadastral do consultório atualizada.",
                ]);
                flash("Identificação atualizada.");
                redirect("settings", ["tab" => "perfil"]);
            }
            if ($act === "sectors") {
                $roleLabels = (array) ($_POST["role_label"] ?? []);
                $roleIcons = (array) ($_POST["role_icon"] ?? []);
                $defs = [
                    "recepcionista" => "Recepção",
                    "assistente" => "Assistente",
                    "medico" => "Profissional",
                    "gerente" => "Administrativo",
                ];
                $i = 1;
                foreach ($defs as $role => $fallback) {
                    $opts = role_icon_options($role);
                    $label = trim((string) ($roleLabels[$role] ?? ""));
                    if ($label === "") {
                        $label = $fallback;
                    }
                    $ico = trim(
                        (string) ($roleIcons[$role] ??
                            default_role_icon($role)),
                    );
                    if (!isset($opts[$ico])) {
                        $ico = default_role_icon($role);
                    }
                    q(
                        "INSERT INTO pi_clinic_roles (clinic_id,role_code,label,icon_name,enabled,sort_order) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label), icon_name=VALUES(icon_name), sort_order=VALUES(sort_order), enabled=VALUES(enabled)",
                        [$cid, $role, $label, $ico, 1, $i++],
                    );
                }
                audit("consultorio_atualizado", "consultorio", $cid, [
                    "audit_body" => "Departamentos do consultório atualizados.",
                ]);
                flash("Departamentos atualizados.");
                redirect("settings", ["tab" => "setores"]);
            }
            if ($act === "visual") {
                $clinicIcon = normalize_clinic_icon(
                    (string) ($_POST["clinic_icon"] ?? ""),
                );
                $accentColor = normalize_accent_color(
                    (string) ($_POST["accent_color"] ?? ""),
                );
                q(
                    "UPDATE pi_clinics SET clinic_icon=?, accent_color=?, updated_at=NOW() WHERE id=?",
                    [$clinicIcon, $accentColor, $cid],
                );
                audit("consultorio_atualizado", "consultorio", $cid, [
                    "clinic_icon" => $clinicIcon,
                    "accent_color" => $accentColor,
                    "audit_body" => "Aparência do consultório atualizada.",
                ]);
                flash("Aparência atualizada.");
                redirect("settings", ["tab" => "visual"]);
            }
            if ($act === "subscription_claim") {
                $msg = clinic_subscription_register_claim($cid, $uid, $cl);
                flash($msg);
                redirect("settings", ["tab" => "assinatura"]);
            }
        } catch (Throwable $e) {
            flash(
                app_public_error_message(
                    $e,
                    "Não foi possível salvar as configurações.",
                ),
                "bad",
            );
            redirect("settings", ["tab" => $tab]);
        }
    }
    $cl = one(
        "SELECT id,display_name,legal_name,legal_document,phone,responsible_profession,clinic_icon,accent_color,address_line,address_state,address_city,address_city_ibge,timezone,workflow_note,onboarding_done,trial_started_at,trial_ends_at,subscription_status,paid_until,monthly_price_cents,subscription_trust_blocked_until,subscription_last_payment_claim_at FROM pi_clinics WHERE id=?",
        [$cid],
    );
    $nav = clinic_settings_nav($tab);
    $content = "";
    $screenClass = "settings-ds-screen settings-ds-" . e($tab);
    if ($tab === "perfil") {
        $identity =
            '<section class="settings-ds-panel settings-data-identity"><div class="settings-ds-head"><span class="settings-ds-icon">' .
            icon("home_health") .
            '</span><div><span class="eyebrow">Identificação</span><h2>Identificação do consultório</h2><p class="field-help">Mantenha nome, documento, telefone e profissão responsáveis pela identidade operacional do ambiente.</p></div></div><div class="settings-ds-form-grid">' .
            form_row(
                "Nome fantasia",
                input(
                    "display_name",
                    "text",
                    $cl["display_name"],
                    'required maxlength="120"',
                ),
            ) .
            form_row(
                "Razão social/nome",
                input(
                    "legal_name",
                    "text",
                    $cl["legal_name"],
                    'required maxlength="180"',
                ),
            ) .
            form_row(
                "CPF/CNPJ",
                input(
                    "legal_document",
                    "text",
                    $cl["legal_document"],
                    'required inputmode="numeric" data-doc-mask data-document-validate',
                ),
            ) .
            form_row(
                "Telefone",
                input("phone", "text", $cl["phone"], 'inputmode="tel"'),
            ) .
            profession_select_fields($cl) .
            "</div></section>";
        $location =
            '<section class="settings-ds-panel settings-data-location"><div class="settings-ds-head"><span class="settings-ds-icon">' .
            icon("location_on") .
            '</span><div><span class="eyebrow">Localização</span><h2>Cidade, estado e fuso</h2><p class="field-help">A cidade escolhida pelo IBGE define o fuso usado na Agenda, em horários, bloqueios e registros do consultório.</p></div></div><div class="settings-ds-form-grid">' .
            clinic_location_fields($cl) .
            '</div><div class="settings-ds-note">' .
            icon("schedule") .
            "<span>O fuso é calculado automaticamente pela cidade selecionada.</span></div></section>";
        $form =
            '<form method="post" class="compact clinic-settings-form clinic-settings-ds-form clinic-data-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="profile"><input type="hidden" name="tab" value="perfil"><div class="settings-data-grid">' .
            $identity .
            $location .
            '</div><div class="form-actions full settings-ds-actions"><button type="submit" class="primary">' .
            icon("save") .
            "<span>Salvar identificação</span></button></div></form>";
        $content =
            '<div class="' .
            $screenClass .
            '">' .
            card(
                $form,
                "clinic-settings-card clinic-data-card settings-ds-card-shell",
            ) .
            "</div>";
    } elseif ($tab === "setores") {
        $form =
            '<form method="post" class="compact clinic-settings-form clinic-settings-ds-form clinic-departments-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="sectors"><input type="hidden" name="tab" value="setores"><section class="settings-section settings-departments full settings-ds-panel"><div class="settings-section-head settings-ds-head"><span class="settings-section-icon settings-ds-icon">' .
            icon("corporate_fare") .
            '</span><div><span class="eyebrow">Do seu jeito</span><h2>Departamentos</h2><p class="field-help">Escolha como os setores aparecem para a equipe. Os nomes organizam menus, ambientes, permissões e responsáveis em todo o consultório.</p></div></div>' .
            clinic_role_sector_fields($cid) .
            '</section><div class="form-actions full departments-actions settings-ds-actions"><button type="submit" class="primary">' .
            icon("save") .
            "<span>Salvar departamentos</span></button></div></form>";
        $content =
            '<div class="' .
            $screenClass .
            '">' .
            card($form, "clinic-departments-card settings-ds-card-shell") .
            "</div>";
    } elseif ($tab === "visual") {
        $visual = clinic_visual_from_values(
            $cl["clinic_icon"] ?? null,
            $cl["accent_color"] ?? null,
            $cl["responsible_profession"] ?? null,
        );
        $previewStyle =
            "--appearance-accent:" .
            e($visual["brand"]) .
            ";--appearance-accent-dark:" .
            e($visual["brand_dark"]) .
            ";--appearance-accent-soft:" .
            e($visual["brand_soft"]) .
            ";--appearance-on-accent:" .
            e($visual["on_brand"]) .
            ";";
        $form =
            '<form method="post" class="compact clinic-settings-form clinic-settings-ds-form clinic-appearance-form">' .
            csrf_field() .
            '<input type="hidden" name="act" value="visual"><input type="hidden" name="tab" value="visual"><section class="settings-section settings-appearance full settings-ds-panel"><div class="settings-section-head settings-ds-head"><span class="settings-section-icon settings-ds-icon">' .
            icon("palette") .
            '</span><div><span class="eyebrow">Aparência</span><h2>Identidade visual</h2><p class="field-help">Escolha o ícone e a cor que organizam a identidade visual do consultório em menus, botões, cartões e destaques.</p></div></div><div class="clinic-appearance-preview" style="' .
            $previewStyle .
            '"><span class="clinic-appearance-mark">' .
            icon($visual["icon"]) .
            '</span><div class="clinic-appearance-copy"><span class="eyebrow">Prévia do consultório</span><strong>' .
            e($cl["display_name"] ?? "Consultório") .
            "</strong><small>" .
            e(
                normalize_profession(
                    (string) ($cl["responsible_profession"] ?? "Profissional"),
                ),
            ) .
            '</small></div><span class="clinic-appearance-chip">' .
            icon("verified") .
            '<span>Identidade ativa</span></span></div><div class="appearance-picker-block"><div class="appearance-picker-head"><h3>Ícone do consultório</h3><p class="field-help">Use um símbolo simples e reconhecível. Ele aparece no cabeçalho, na credencial e nos atalhos do sistema.</p></div>' .
            clinic_icon_picker($visual["icon"]) .
            '</div><div class="appearance-picker-block"><div class="appearance-picker-head"><h3>Cor de destaque</h3><p class="field-help">A cor selecionada gera automaticamente variações claras, médias e escuras para manter contraste e consistência.</p></div>' .
            clinic_color_picker($visual["brand"]) .
            '</div></section><div class="form-actions full settings-ds-actions"><button type="submit" class="primary">' .
            icon("save") .
            "<span>Salvar aparência</span></button></div></form>";
        $content =
            '<div class="' .
            $screenClass .
            '">' .
            card($form, "clinic-appearance-card settings-ds-card-shell") .
            "</div>";
    } else {
        $content =
            '<div class="settings-ds-screen settings-ds-assinatura">' .
            card(
                clinic_subscription_status_card($cl),
                "subscription-status-shell settings-ds-card-shell",
            ) .
            clinic_subscription_cta($cl, $tab) .
            "</div>";
    }
    page("Meu Consultório", page_head("Meu Consultório", "") . $content);
}
