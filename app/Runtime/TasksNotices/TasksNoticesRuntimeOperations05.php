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
    
        readonly_support_alerts_ensure_schema();
        $cid = (int) ($c["clinic_id"] ?? 0);
        $uid = (int) ($c["user"]["id"] ?? 0);
        $rows = [];
        try {
            $rows = q(
                "SELECT id,title,body,severity,read_at,created_at FROM pi_admin_alerts WHERE sender_user_id=? AND sender_clinic_id=? AND source_scope='clinic_readonly' ORDER BY id DESC LIMIT 80",
                [$uid, $cid],
            )->fetchAll();
        } catch (Throwable $e) {
            error_log("[Prontoo readonly support alerts list] " . $e->getMessage());
        }
        $form =
            '<details class="form-panel notice-compose-panel readonly-support-compose" open><summary class="primary small cmdlike">' .
            action_summary_label("Mensagem para o suporte", "support_agent") .
            '</summary><form method="post" class="compact notice-form notice-form-refined" data-notice-form>' .
            csrf_field() .
            '<input type="hidden" name="act" value="support_message"><div class="notice-form-grid">' .
            form_row(
                "Assunto",
                input(
                    "title",
                    "text",
                    "",
                    'required placeholder="Ex.: Regularização da assinatura"',
                ),
            ) .
            '<label class="field notice-message-field"><span>Mensagem</span>' .
            textarea(
                "body",
                "",
                'required rows="5" placeholder="Explique sua dúvida sobre pagamento, liberação ou acesso aos seus dados."',
            ) .
            '</label></div><p class="field-help readonly-support-help">Neste modo, Avisos fica restrito ao contato com o Desenvolvedor. Mensagens internas para equipe ficam bloqueadas até a regularização.</p><div class="form-actions notice-form-actions"><button type="submit" class="primary">' .
            icon("support_agent") .
            "<span>Enviar ao suporte</span></button></div></form></details>";
        $cards = "";
        foreach ($rows as $r) {
            $cards .=
                '<article class="notice-card notice-gmail-row ds-notice-row notice-minimal-row admin-alert-row readonly-support-row"><span class="notice-minimal-icon" aria-hidden="true">' .
                icon(empty($r["read_at"]) ? "outgoing_mail" : "done_all") .
                '</span><span class="notice-minimal-sender">Suporte</span><span class="notice-minimal-subject">' .
                e($r["title"]) .
                '</span><time class="notice-minimal-time">' .
                e(dt_notice_br($r["created_at"])) .
                "</time></article>";
        }
        if ($cards === "") {
            $cards =
                '<div class="notice-empty">' .
                icon("support_agent") .
                "<strong>Nenhuma mensagem enviada ao suporte.</strong><span>Use o formulário acima para falar com o Desenvolvedor.</span></div>";
        }
        $stats = "";
        $list = card(
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
