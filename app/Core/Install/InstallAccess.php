<?php
declare(strict_types=1);

namespace Prontoo\Core\Install;

final class InstallAccess
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1'];
    private const FORWARDED_HEADERS = [
        'HTTP_FORWARDED',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CF_CONNECTING_IP',
        'HTTP_TRUE_CLIENT_IP',
    ];

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
    }

    public static function isLoopbackAddress(string $address): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::isLoopbackAddress
         * Responsabilidade: Implementa a responsabilidade “is loopback address” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.InstallAccess::isLocalServer`.
         * Dependências chamadas: `trim`, `filter_var`, `str_starts_with`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $address = trim($address);
        if ($address === '::1') {
            return true;
        }
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        return str_starts_with($address, '127.');
    }

    public static function requestHostFrom(array $server): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::requestHostFrom
         * Responsabilidade: Implementa a responsabilidade “request host from” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.InstallAccess::requestHost`, `Core.Install.InstallAccess::isLocalServer`.
         * Dependências chamadas: `strtolower`, `trim`, `str_starts_with`, `strpos`, `substr`, `preg_replace`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        $host = strtolower(trim((string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '')));
        if ($host === '') {
  return '';
        }
        if (str_starts_with($host, '[')) {
  $end = strpos($host, ']');
  return $end === false ? '' : substr($host, 1, $end - 1);
        }
        return preg_replace('/:\d+$/', '', $host) ?? '';
    }

    public static function requestHost(): string
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::requestHost
         * Responsabilidade: Implementa a responsabilidade “request host” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `self::requestHostFrom`.
         * Estado externo lido: `$_SERVER`.
         * Efeitos colaterais: consome dados da requisição HTTP.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return self::requestHostFrom($_SERVER);
    }

    public static function isLocalServer(array $server): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::isLocalServer
         * Responsabilidade: Implementa a responsabilidade “is local server” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.InstallAccess::isLocalHttpRequest`.
         * Dependências chamadas: `self::isLoopbackAddress`, `trim`, `in_array`, `self::requestHostFrom`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (!self::isLoopbackAddress((string) ($server['REMOTE_ADDR'] ?? ''))) {
  return false;
        }
        foreach (self::FORWARDED_HEADERS as $header) {
  if (trim((string) ($server[$header] ?? '')) !== '') {
      return false;
  }
        }
        return in_array(self::requestHostFrom($server), self::LOCAL_HOSTS, true);
    }

    public static function isLocalHttpRequest(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::isLocalHttpRequest
         * Responsabilidade: Implementa a responsabilidade “is local http request” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SchemaMutationLock::mayOpenInstallerWindow`, `Core.Install.InstallAccess::isLocalExecution`, `prontoo_run`.
         * Dependências chamadas: `self::isLocalServer`.
         * Estado externo lido: `$_SERVER`.
         * Efeitos colaterais: consome dados da requisição HTTP.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return PHP_SAPI !== 'cli' && self::isLocalServer($_SERVER);
    }

    public static function isLocalExecution(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::isLocalExecution
         * Responsabilidade: Implementa a responsabilidade “is local execution” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.InstallAccess::assertLocalEntry`.
         * Dependências chamadas: `self::isLocalHttpRequest`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return PHP_SAPI === 'cli' || self::isLocalHttpRequest();
    }

    public static function assertLocalEntry(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::assertLocalEntry
         * Responsabilidade: Implementa a responsabilidade “assert local entry” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `prontoo_install`.
         * Dependências chamadas: `self::isLocalExecution`, `self::denyPublicAccess`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        if (!self::isLocalExecution()) {
            self::denyPublicAccess();
        }
    }

    public static function denyPublicAccess(): never
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::denyPublicAccess
         * Responsabilidade: Implementa a responsabilidade “deny public access” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.InstallAccess::assertLocalEntry`, `prontoo_install`.
         * Dependências chamadas: `headers_sent`, `http_response_code`, `header`.
         * Efeitos colaterais: controla cabeçalhos, redirecionamento ou resposta HTTP; produz conteúdo de saída.
         * Cuidado 1: Não produza saída antes de cabeçalhos ou redirecionamentos e preserve a validação CSRF nos POSTs.
         */
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }
        echo "Not Found\\n";
        exit;
    }
}
