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
    private const PUBLIC_INSTALL_WINDOW_START_UNIX = 1785186607;
    private const PUBLIC_INSTALL_WINDOW_END_UNIX = 1785190207;

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
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: `self::isLocalServer`.
         * Estado externo lido: `$_SERVER`.
         * Efeitos colaterais: consome dados da requisição HTTP.
         * Cuidado 1: Ao modificar esta rotina, revise os chamadores e preserve tipos, valores de retorno e comportamento de falha.
         */
        return PHP_SAPI !== 'cli' && self::isLocalServer($_SERVER);
    }

    public static function isInstallerExecutionAllowed(?array $server = null, ?int $now = null): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::isInstallerExecutionAllowed
         * Responsabilidade: Autoriza a certificação CLI integralmente marcada e, temporariamente, a instalação web pública em HTTPS no host canônico durante a janela Unix [1785186607, 1785190207), desde que o ambiente esteja fresh.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.InstallAccess::assertInstallerEntry`.
         * Dependências chamadas: `getenv`, `time`, `strtoupper`, `trim`, `in_array`, `strtolower`, `self::requestHostFrom`, `dirname`, `is_file`.
         * Estado externo lido: `PHP_SAPI`, `$_SERVER`, configuração e lock da instalação.
         * Efeitos colaterais: nenhum; apenas produz uma decisão fail-closed.
         * Cuidado 1: A janela web deve expirar automaticamente em 1785190207 e jamais aceitar ambiente com `app/config.php` ou `ssd/install.lock`.
         * Cuidado 2: A certificação exige simultaneamente `GITHUB_ACTIONS=true`, `CI=true`, `PRONTOO_SCHEMA_TEST_MODE=1` e `PRONTOO_INSTALLER_CLI_MODE=1`; nenhum marcador isolado deve abrir a instalação.
         */
        if (PHP_SAPI === 'cli' && $server === null) {
            return (string) getenv('GITHUB_ACTIONS') === 'true' &&
                (string) getenv('CI') === 'true' &&
                (string) getenv('PRONTOO_SCHEMA_TEST_MODE') === '1' &&
                (string) getenv('PRONTOO_INSTALLER_CLI_MODE') === '1';
        }
        if (PHP_SAPI === 'cli' || $server !== null) {
            return false;
        }

        $now ??= time();
        if ($now < self::PUBLIC_INSTALL_WINDOW_START_UNIX ||
            $now >= self::PUBLIC_INSTALL_WINDOW_END_UNIX) {
            return false;
        }

        $request = $_SERVER;
        $method = strtoupper(trim((string) ($request['REQUEST_METHOD'] ?? 'GET')));
        if (!in_array($method, ['GET', 'POST'], true)) {
            return false;
        }
        $https = strtolower(trim((string) ($request['HTTPS'] ?? '')));
        if (!in_array($https, ['on', '1'], true) &&
            (string) ($request['SERVER_PORT'] ?? '') !== '443') {
            return false;
        }
        if (self::requestHostFrom($request) !== 'prontoo.app') {
            return false;
        }

        $root = dirname(__DIR__, 3);
        return !is_file($root . '/app/config.php') &&
            !is_file($root . '/ssd/install.lock');
    }

    public static function assertInstallerEntry(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::assertInstallerEntry
         * Responsabilidade: Protege o primeiro ponto executável do instalador e permite a janela pública temporária somente até o timestamp Unix 1785190207; depois disso, ou após configuração/lock, volta a responder 404 automaticamente.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `install.php`, `index.php`, `prontoo_install`.
         * Dependências chamadas: `self::isInstallerExecutionAllowed`, `self::denyPublicAccess`.
         * Estado externo lido: contexto HTTP ou CLI por meio de `isInstallerExecutionAllowed`.
         * Efeitos colaterais: pode encerrar a requisição antes do bootstrap da aplicação.
         * Cuidado 1: Esta guarda deve continuar antes de `app/prontoo.php`; movê-la para depois do bootstrap expõe trabalho e diagnóstico desnecessários.
         * Cuidado 2: Não prolongue nem reabra a janela sem nova autorização explícita.
         */
        if (!self::isInstallerExecutionAllowed()) {
            self::denyPublicAccess();
        }
    }

    public static function denyPublicAccess(): never
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Install.InstallAccess::denyPublicAccess
         * Responsabilidade: Implementa a responsabilidade “deny public access” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Install/InstallAccess.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Install.InstallAccess::assertInstallerEntry`, `prontoo_install`.
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
        echo "Not Found\n";
        exit;
    }
}
