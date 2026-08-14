from pathlib import Path
import re

# Move global developer operation specs out of Runtime03 to keep the canonical runtime budget flat.
runtime_path = Path('app/Runtime/UiComponents/UiComponentsRuntimeOperations03.php')
runtime = runtime_path.read_text()
pattern = r'''        if \(\(\$c\["scope"\] \?\? ""\) === "global"\) \{.*?\n        \}\n        \$role ='''
replacement = r'''        if (($c["scope"] ?? "") === "global") {
            return \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations01::admin_global_operation_specs(
                $current,
                $parent,
                (string) ($_GET["view"] ?? "received"),
            );
        }
        $role ='''
runtime, count = re.subn(pattern, lambda _m: replacement, runtime, count=1, flags=re.S)
if count != 1:
    raise SystemExit(f'Runtime03 global operations extraction match count={count}')
runtime_path.write_text(runtime)

presentation_path = Path('app/Presentation/AdminPages/AdminPagesPresentationOperations01.php')
presentation = presentation_path.read_text()
if 'admin_global_operation_specs' in presentation:
    raise SystemExit('admin_global_operation_specs already exists')
helper = r'''

    public static function admin_global_operation_specs(string $current, string $parent, string $alertView): array

    {

        if (!in_array($alertView, ["received", "sent"], true)) {
            $alertView = "received";
        }
        if ($current === "admin_alerts") {
            return [
                ["admin_alerts", "Recebidos", "inbox", ["view" => "received"]],
                ["admin_alerts", "Enviados", "outbox", ["view" => "sent"]],
                ["admin_alerts", "Novo aviso", "add_comment", ["view" => $alertView, "compose" => "1"]],
            ];
        }
        if ($current === "admin_maintenance") {
            return [
                ["admin_maintenance", "Manutenção", "construction"],
                ["admin_settings", "Configurações", "settings"],
                ["admin_global_notices", "Avisos globais", "campaign"],
            ];
        }
        return match ($parent) {
            "admin_painel" => [["admin_painel", "Visão geral", "space_dashboard"]],
            "admin_clinics" => [
                ["admin_clinics", "Consultórios", "home_health"],
                ["admin_onboarding", "Onboarding", "playlist_add_check"],
                ["admin_operations", "Operação", "account_tree"],
            ],
            "admin_health" => [
                ["admin_health", "Confiabilidade", "shield"],
                ["admin_errors", "Erros", "bug_report"],
                ["admin_security", "Segurança", "security"],
                ["admin_integrity", "Integridade", "verified_user"],
                ["admin_diagnostics", "Diagnósticos", "troubleshoot"],
                ["admin_deleted", "Recuperação", "restore_from_trash"],
            ],
            "admin_performance" => [["admin_performance", "Observabilidade", "monitoring"]],
            "admin_administration" => [
                ["admin_administration", "Administração", "tune"],
                ["admin_people", "Usuários", "groups"],
                ["admin_alerts", "Comunicação", "campaign"],
                ["admin_global_notices", "Avisos globais", "notifications_active"],
                ["admin_maintenance", "Manutenção", "construction"],
                ["admin_settings", "Configurações", "settings"],
                ["admin_audit", "Auditoria", "history"],
            ],
            default => [],
        };
    }
'''
pos = presentation.rfind('\n}')
if pos < 0:
    raise SystemExit('AdminPagesPresentationOperations01 closing brace not found')
presentation_path.write_text(presentation[:pos] + helper + presentation[pos:])

# Scope every new control-center selector to global pages. This also prevents adding a duplicate
# unscoped selector header to the existing presentation debt budget.
css_path = Path('public/assets/presentation.css')
css = css_path.read_text()
marker = '/* Developer control center v1 */'
if marker not in css:
    raise SystemExit('developer CSS marker missing')
head, tail = css.split(marker, 1)
replacements = {
    '.developer-control-status': 'body.scope-global .developer-control-status',
    '.developer-state-badge': 'body.scope-global .developer-state-badge',
    '.developer-empty-state': 'body.scope-global .developer-empty-state',
    '.developer-overview-grid': 'body.scope-global .developer-overview-grid',
    '.developer-domain-card': 'body.scope-global .developer-domain-card',
    '.developer-observability-card': 'body.scope-global .developer-observability-card',
    '.developer-status-card': 'body.scope-global .developer-status-card',
    '.developer-actions-card': 'body.scope-global .developer-actions-card',
    '.developer-domain-link': 'body.scope-global .developer-domain-link',
    '.developer-compact-stats': 'body.scope-global .developer-compact-stats',
    '.developer-platform-stats': 'body.scope-global .developer-platform-stats',
    '.developer-tool-grid': 'body.scope-global .developer-tool-grid',
    '.developer-tool-card': 'body.scope-global .developer-tool-card',
    '.developer-tool-icon': 'body.scope-global .developer-tool-icon',
}
# Longest first prevents a shorter token from corrupting a more specific token.
for old in sorted(replacements, key=len, reverse=True):
    tail = tail.replace(old, replacements[old])
css_path.write_text(head + marker + tail)
