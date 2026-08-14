from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def patch(path, old, new):
    file = ROOT / path
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"expected one occurrence in {path}, got {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


patch(
    "app/Runtime/AdminPages/AdminPagesRuntimeOperations01.php",
    '''        $scope = (int) ($checks["scope_alerts_24h"] ?? 0);\n        $securityOk = $locks === 0 && $scope === 0;\n        $critical = !$databaseOk || !$storageOk || !$integrityOk || !$versionOk;\n        $attention = $openErrors > 0 || !$securityOk;\n''',
    '''        $scope = (int) ($checks["scope_alerts_24h"] ?? 0);\n        $scopeLogicOk = !empty(($checks["scope_guard_logic"] ?? [])["ok"]);\n        $scopeContextOk = !empty(($checks["scope_guard_context"] ?? [])["ok"]);\n        $securityInvariantOk = $scopeLogicOk && $scopeContextOk;\n        $securityOk = $securityInvariantOk && $locks === 0 && $scope === 0;\n        $critical = !$databaseOk || !$storageOk || !$integrityOk || !$versionOk || !$securityInvariantOk;\n        $attention = $openErrors > 0 || $locks > 0 || $scope > 0;\n''',
)
patch(
    "app/Runtime/AdminPages/AdminPagesRuntimeOperations01.php",
    '''                "scope_alerts_24h" => $scope,\n            ],\n''',
    '''                "scope_alerts_24h" => $scope,\n                "security_invariants_ok" => $securityInvariantOk,\n            ],\n''',
)

patch(
    "app/Runtime/AdminPages/AdminPagesRuntimeOperations03.php",
    '''        $locks = (int) ($counts["login_locks"] ?? 0);\n        $scope = (int) ($counts["scope_alerts_24h"] ?? 0);\n        if ($locks > 0 || $scope > 0) {\n            $items[] = ["icon" => "security", "time" => "Segurança", "title" => ($locks + $scope) . " sinal(is) para revisar", "body" => $locks . " bloqueio(s) de login · " . $scope . " operação(ões) de escopo bloqueada(s).", "html" => '<a class="ghost small" href="' . \\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::href("admin_security") . '">Ver segurança</a>'];\n        }\n''',
    '''        $locks = (int) ($counts["login_locks"] ?? 0);\n        $scope = (int) ($counts["scope_alerts_24h"] ?? 0);\n        $securityInvariantsOk = !empty($counts["security_invariants_ok"]);\n        if ($locks > 0 || $scope > 0 || !$securityInvariantsOk) {\n            $securityBody = $locks . " bloqueio(s) de login · " . $scope . " operação(ões) de escopo bloqueada(s).";\n            if (!$securityInvariantsOk) {\n                $securityBody .= " O autoteste determinístico de isolamento exige revisão.";\n            }\n            $items[] = ["icon" => "security", "time" => "Segurança", "title" => !$securityInvariantsOk ? "Isolamento exige revisão" : ($locks + $scope) . " sinal(is) para revisar", "body" => $securityBody, "html" => '<a class="ghost small" href="' . \\Prontoo\\Runtime\\SupportFoundation\\SupportFoundationRuntimeOperations01::href("admin_security") . '">Ver segurança</a>'];\n        }\n''',
)

patch(
    "app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php",
    '''        if (empty($checks["storage"])) {\n            $actions[] = [\n                "icon" => "folder_off",\n                "time" => "Arquivos",\n                "title" => "Storage sem permissão de escrita",\n                "body" =>\n                    "Arquivos temporários, métricas e comprovantes dependem de escrita em /ssd.",\n                "meta" => "Verifique permissões.",\n            ];\n        }\n''',
    '''        if (empty($checks["storage"])) {\n            $actions[] = [\n                "icon" => "folder_off",\n                "time" => "Arquivos",\n                "title" => "Storage sem permissão de escrita",\n                "body" =>\n                    "Arquivos temporários, métricas e comprovantes dependem de escrita em /ssd.",\n                "meta" => "Verifique permissões.",\n            ];\n        }\n        $scopeLogicOk = !empty(($checks["scope_guard_logic"] ?? [])["ok"]);\n        $scopeContextOk = !empty(($checks["scope_guard_context"] ?? [])["ok"]);\n        if (!$scopeLogicOk || !$scopeContextOk) {\n            $actions[] = [\n                "icon" => "shield_lock",\n                "time" => "Isolamento",\n                "title" => "Autoteste de isolamento exige revisão",\n                "body" => "A prova determinística do guardião de escopo ou do contexto entre consultórios não concluiu todos os casos críticos.",\n                "meta" => "Trate como condição estrutural até a investigação.",\n            ];\n        }\n        if (empty(($health["components"]["integrity"] ?? [])["ok"])) {\n            $actions[] = [\n                "icon" => "gpp_bad",\n                "time" => "Integridade",\n                "title" => "Integridade da auditoria exige revisão",\n                "body" => "A cadeia de auditoria ou registros recentes não concluíram a verificação.",\n                "meta" => "Investigue antes de considerar a plataforma operacional.",\n            ];\n        }\n        if (empty(($health["components"]["version"] ?? [])["ok"])) {\n            $actions[] = [\n                "icon" => "deployed_code_alert",\n                "time" => "Versão",\n                "title" => "Contrato de versão divergente",\n                "body" => "A release publicada não concluiu o contrato determinístico de versão.",\n                "meta" => "Revise os artefatos canônicos da release.",\n            ];\n        }\n''',
)
patch(
    "app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php",
    '''        if ($scopeViolations24h > 0) {\n''',
    '''        if ($scopeViolations24h > 0 && $scopeLogicOk && $scopeContextOk) {\n''',
)
patch(
    "app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php",
    '''                "Segurança", "Isolamento", "Banco", "Arquivos" => "admin_health",\n                "Assinaturas" => "admin_clinics",\n                "Onboarding" => "admin_onboarding",\n''',
    '''                "Segurança", "Isolamento", "Banco", "Arquivos" => "admin_health",\n                "Integridade" => "admin_integrity",\n                "Versão" => "admin_diagnostics",\n                "Assinaturas" => "admin_clinics",\n''',
)

patch(
    "app/Runtime/AdminPages/AdminPagesRuntimeOperations08.php",
    '''                $onboardingHead . $filters . $advanced . $table,\n''',
    '''                $onboardingHead . $filters . $table . $advanced,\n''',
)

print("final control center invariants applied")
