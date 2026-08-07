<?php
declare(strict_types=1);

namespace Prontoo\Presentation\Http;

use Throwable;

final class JsonResponder
{
    private function __construct()
    {
    }

    public static function send(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Robots-Tag: noindex, nofollow');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function failureMessage(string $route, int $status, Throwable $error): string
    {
        if ($status < 500) {
            return $error->getMessage();
        }
        if (in_array($route, ['patient_lookup', 'patient_suggest', 'person_lookup'], true)) {
            return 'Não foi possível verificar os dados do paciente agora. Tente novamente em instantes.';
        }
        if (in_array($route, ['lead_lookup', 'lead_patient_lookup'], true)) {
            return 'Não foi possível verificar os dados do interessado agora. Tente novamente em instantes.';
        }
        if (in_array($route, ['counterparty_lookup', 'counterparty_suggest'], true)) {
            return 'Não foi possível verificar os dados financeiros agora. Tente novamente em instantes.';
        }
        return 'Não foi possível concluir esta verificação agora. Tente novamente em instantes.';
    }
}
