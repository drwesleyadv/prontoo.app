from pathlib import Path
import re

runtime = Path('app/Runtime/AdminPages/AdminPagesRuntimeOperations06.php')
text = runtime.read_text()
pattern = r'\n    public static function page_admin_administration\(\): void.*?\n    \}\n\n    public static function page_admin_people'
replacement = r'''
    public static function page_admin_administration(): void

    {

        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("admin_administration");
        $content = \Prontoo\Presentation\AdminPages\AdminPagesPresentationOperations03::developer_administration_tools_html(
            static fn(string $route): string => \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route),
        );
        $body =
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head("Administração", "") .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card($content, "developer-administration-card");
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page("Administração · Desenvolvedor", $body);
    }

    public static function page_admin_people'''
new, count = re.subn(pattern, lambda _m: replacement, text, count=1, flags=re.S)
if count != 1:
    raise SystemExit(f'Runtime06 administration extraction match count={count}')
runtime.write_text(new)

presentation = Path('app/Presentation/AdminPages/AdminPagesPresentationOperations03.php')
text = presentation.read_text()
if 'developer_administration_tools_html' in text:
    raise SystemExit('Presentation helper already exists')
helper = r'''

    public static function developer_administration_tools_html(callable $href): string

    {

        $tools = [
            ["groups", "Usuários e pessoas", "Credenciais, vínculos e identidades administrativas da plataforma.", "admin_people"],
            ["campaign", "Comunicação técnica", "Avisos entre perfis do Desenvolvedor e acompanhamento das mensagens recebidas.", "admin_alerts"],
            ["notifications_active", "Avisos globais", "Comunicações institucionais exibidas nos ambientes dos consultórios.", "admin_global_notices"],
            ["construction", "Manutenção", "Modo de manutenção e controles operacionais extraordinários.", "admin_maintenance"],
            ["settings", "Configurações", "Parâmetros globais que não pertencem à operação cotidiana.", "admin_settings"],
            ["history", "Auditoria", "Rastreabilidade administrativa e eventos relevantes da plataforma.", "admin_audit"],
        ];
        $grid = '<div class="developer-tool-grid">';
        foreach ($tools as [$icon, $title, $description, $route]) {
            $grid .=
                '<a class="developer-tool-card" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e((string) $href($route)) .
                '"><span class="developer-tool-icon">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon($icon) .
                '</span><span><b>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
                '</b><small>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($description) .
                '</small></span><span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span></a>';
        }
        return '<div class="section-head"><div><h2>Ferramentas administrativas</h2><p>Governança, comunicação e suporte ficam agrupados aqui para não competir com a operação diária.</p></div></div>' .
            $grid .
            "</div>";
    }
'''
pos = text.rfind('\n}')
if pos < 0:
    raise SystemExit('Presentation class closing brace not found')
presentation.write_text(text[:pos] + helper + text[pos:])
