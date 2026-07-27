<?php
if (!function_exists("storage_path")) {
    function storage_path(string $suffix = ""): string
    {
        /*
         * GUIA DE MANUTENÇÃO — storage_path
         * Responsabilidade: Implementa a responsabilidade “storage path” dentro do módulo de site público e landing page.
         * Local arquitetural: br/runtime-telemetry.php (site público e landing page).
         * Chamadores detectados: `platform_storage_status`, `db_runtime_notice_once`, `schema_lock_file`, `subscription_payment_proof_storage`, `subscription_payment_proof_absolute_path`, `document_pdf_storage_dir`, `document_pdf_cleanup_due`, `maestro_cron_run` e mais 18.
         * Dependências chamadas: `dirname`, `ltrim`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . "ssd";
        return $suffix === ""
            ? $base
            : $base . DIRECTORY_SEPARATOR . ltrim($suffix, "/\\");
    }
}

function br_landing_register_route_telemetry(float $startedAt): void
{
    /*
     * GUIA DE MANUTENÇÃO — br_landing_register_route_telemetry
     * Responsabilidade: Valida e executa a mutação “br landing register route telemetry”, preservando as invariantes do módulo de site público e landing page.
     * Local arquitetural: br/runtime-telemetry.php (site público e landing page).
     * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
     * Dependências chamadas: `register_shutdown_function`, `dirname`, `is_file`, `round`, `max`, `microtime`, `http_response_code`, `error_get_last`, `is_array`, `in_array`, `function_exists`, `telemetry_append_route_performance_metric` e mais 3.
     * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
     * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
     */
    register_shutdown_function(static function () use ($startedAt): void {
        /*
         * GUIA DE MANUTENÇÃO — closure@br/runtime-telemetry.php:14
         * Responsabilidade: Executa uma etapa anônima e localizada do fluxo do módulo de site público e landing page.
         * Local arquitetural: br/runtime-telemetry.php (site público e landing page).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `dirname`, `is_file`, `round`, `max`, `microtime`, `http_response_code`, `error_get_last`, `is_array`, `in_array`, `function_exists`, `telemetry_append_route_performance_metric`, `time` e mais 2.
         * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP; gera trilha de auditoria ou telemetria.
         * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
         */
        $configFile = dirname(__DIR__) . "/app/config.php";
        if (!is_file($configFile)) {
            return;
        }
        $elapsedMs = round(max(0.0, (microtime(true) - $startedAt) * 1000), 3);
        $status = (int) http_response_code();
        if ($status < 100) {
            $status = 200;
        }
        $lastError = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        $fatal = is_array($lastError) && in_array((int) ($lastError["type"] ?? 0), $fatalTypes, true);
        try {
            require_once dirname(__DIR__) . "/app/Support/Telemetry.php";
            if (!function_exists("telemetry_append_route_performance_metric")) {
                return;
            }
            telemetry_append_route_performance_metric([
                "ts" => time(),
                "route" => "landing",
                "queries" => 0,
                "elapsed_ms" => $elapsedMs,
                "query_ms" => 0.0,
                "success" => !$fatal && $status < 500 ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            error_log("[Prontoo landing telemetry] " . $e->getMessage());
        }
    });
}

br_landing_register_route_telemetry($brLandingRequestStartedAt);

