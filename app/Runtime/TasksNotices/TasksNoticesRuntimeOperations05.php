<?php
declare(strict_types=1);

namespace Prontoo\Runtime\TasksNotices;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class TasksNoticesRuntimeOperations05
{
    private function __construct()
    {
    }

    public static function page_notices(): void
    {
        if (
            ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "GET" ||
            ($_GET["new"] ?? "") !== "1"
        ) {
            \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations06::page_notices();
            return;
        }

        $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::require_can("notices");
        if (!empty(($c["billing"] ?? [])["read_only"])) {
            \Prontoo\Runtime\TasksNotices\TasksNoticesRuntimeOperations06::page_notices();
            return;
        }

        $cid = (int) $c["clinic_id"];
        $view = preg_replace(
            "/[^a-z_]/",
            "",
            (string) ($_GET["view"] ?? "received"),
        );
        if (!in_array($view, ["received", "sent", "archived"], true)) {
            $view = "received";
        }

        $team = \Prontoo\Runtime\UsersPermissions\UsersPermissionsRuntimeOperations02::team_options($cid);
        $userOpts = '<option value="">Selecione</option>';
        foreach ($team as $id => $name) {
            $userOpts .=
                '<option value="' . (int) $id . '">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($name) .
                "</option>";
        }

        $backUrl = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("notices", ["view" => $view]);
        $back =
            '<a class="ghost small" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($backUrl) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("arrow_back") .
            "<span>Voltar</span></a>";

        $contentSection =
            '<section class="notice-form-section form-section">' .
            '<div class="section-head"><div><span class="eyebrow">Conteúdo</span><h2>Mensagem</h2><span class="field-help">Use um título objetivo e escreva somente o que a equipe precisa saber.</span></div></div>' .
            '<div class="compact">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Título",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "title",
                    "text",
                    "",
                    'required placeholder="Ex.: Alteração no fluxo de atendimento"',
                ),
            ) .
            '<label class="field notice-message-field"><span>Mensagem</span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                "body",
                "",
                'required rows="6" placeholder="Escreva a orientação que deve ser lida pela equipe."',
            ) .
            "</label></div></section>";

        $deliverySection =
            '<section class="notice-form-section form-section">' .
            '<div class="section-head"><div><span class="eyebrow">Entrega</span><h2>Destinatários e leitura</h2><span class="field-help">Defina quem recebe o aviso e se a leitura precisa ser confirmada.</span></div></div>' .
            '<div class="notice-form-grid">' .
            '<label class="field notice-target-scope"><span>Destinatários</span><select name="target_scope" data-notice-target-scope><option value="all">Toda a clínica</option><option value="role">Colaboradores do meu cargo</option><option value="user">Colaborador específico</option></select><span class="field-help">A opção por cargo usa o seu cargo atual como referência.</span></label>' .
            '<label class="field notice-target-user" data-notice-target-user hidden><span>Colaborador específico</span><select name="target_user_id" data-notice-target-user-select>' .
            $userOpts .
            '</select><span class="field-help">Selecione uma pessoa da equipe deste consultório.</span></label>' .
            '</div><label class="check notice-ack-field"><span><strong>Confirmação de leitura</strong><small class="field-help">Mantém registrado que o destinatário confirmou a leitura do aviso.</small></span><input type="checkbox" name="requires_ack" checked></label></section>';

        $form =
            '<form method="post" class="notice-form ds-entity-form ds-standalone-form" data-notice-form>' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="view" value="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($view) .
            '">' .
            $contentSection .
            $deliverySection .
            '<div class="form-actions notice-form-actions"><a class="ghost" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($backUrl) .
            '">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("close") .
            '<span>Cancelar</span></a><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("campaign") .
            "<span>Publicar aviso</span></button></div></form>";

        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Novo aviso",
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::page_head(
                "Novo aviso",
                "Crie a comunicação, escolha quem deve recebê-la e defina como a leitura será acompanhada.",
                $back,
            ) .
                '<section class="new-notice-screen form-screen">' .
                $form .
                "</section>",
        );
    }

    public static function readonly_support_notice_screen(array $c, string $view = "sent"): string
    
    {
    
        \Prontoo\Runtime\Operational\OperationalComposition::tasks()->ensureSchema("readonly_support_alerts");
        $cid = (int) ($c["clinic_id"] ?? 0);
        $uid = (int) ($c["user"]["id"] ?? 0);
        $rows = [];
        try {
            $rows = \Prontoo\Runtime\Operational\OperationalComposition::tasks()->result('operational.tasks_notices.05.readonly_support_notice_screen.01', [$uid, $cid], [])->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo readonly support alerts list] " . $e->getMessage());
        }
        $form =
            '<details class="form-panel notice-compose-panel readonly-support-compose" open><summary class="pagehead-control pagehead-control--primary">' .
            \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations03::action_summary_label("Mensagem para o suporte", "support_agent") .
            '</summary><form method="post" class="compact notice-form notice-form-refined" data-notice-form>' .
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::csrf_field() .
            '<input type="hidden" name="act" value="support_message"><div class="notice-form-grid">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::form_row(
                "Assunto",
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::input(
                    "title",
                    "text",
                    "",
                    'required placeholder="Ex.: Regularização da assinatura"',
                ),
            ) .
            '<label class="field notice-message-field"><span>Mensagem</span>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::textarea(
                "body",
                "",
                'required rows="5" placeholder="Explique sua dúvida sobre pagamento, liberação ou acesso aos seus dados."',
            ) .
            '</label></div><p class="field-help readonly-support-help">Neste modo, Avisos fica restrito ao contato com o Desenvolvedor. Mensagens internas para equipe ficam bloqueadas até a regularização.</p><div class="form-actions notice-form-actions"><button type="submit" class="primary">' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("support_agent") .
            "<span>Enviar ao suporte</span></button></div></form></details>";
        $cards = "";
        foreach ($rows as $r) {
            $cards .=
                '<article class="notice-card notice-gmail-row ds-notice-row notice-minimal-row admin-alert-row readonly-support-row"><span class="notice-minimal-icon" aria-hidden="true">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon(empty($r["read_at"]) ? "outgoing_mail" : "done_all") .
                '</span><span class="notice-minimal-sender">Suporte</span><span class="notice-minimal-subject">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($r["title"]) .
                '</span><time class="notice-minimal-time">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations01::dt_notice_br($r["created_at"])) .
                "</time></article>";
        }
        if ($cards === "") {
            $cards =
                '<div class="notice-empty">' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::icon("support_agent") .
                "<strong>Nenhuma mensagem enviada ao suporte.</strong><span>Use o formulário acima para falar com o Desenvolvedor.</span></div>";
        }
        $stats = "";
        $list = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::card(
            '<div class="notice-section-head ds-section-head"><h2>Mensagens para suporte</h2><span>' .
                count($rows) .
                '</span></div><div class="notice-list ds-notice-list admin-alert-list">' .
                $cards .
                "</div>",
            "notice-card-shell ds-notice-shell admin-alert-shell",
        );
        return $stats . $form . $list;
    
    }
}