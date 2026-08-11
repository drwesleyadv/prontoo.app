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
            '<details class="form-panel notice-compose-panel readonly-support-compose" open><summary class="primary small cmdlike">' .
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
