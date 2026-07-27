<?php
declare(strict_types=1);

namespace Prontoo\Core\Database;

final class SchemaMutationLock
{
    private const PUBLIC_INSTALL_WINDOW_START_UNIX = 1785186607;
    private const PUBLIC_INSTALL_WINDOW_END_UNIX = 1785190207;

    private static int $depth = 0;
    private static ?string $nonce = null;

    private function __construct() {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaMutationLock::__construct
         * Responsabilidade: Inicializa ou restringe a criação da instância responsável por este serviço, estabelecendo as dependências necessárias antes do uso.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: nenhuma dependência direta detectada estaticamente.
         * Dependências chamadas: nenhuma dependência direta detectada estaticamente.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` permanece congelado fora da certificação CLI ou do POST público temporário e estritamente controlado de instalação limpa.
         */
    }

    public static function runForInstaller(callable $callback): mixed
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaMutationLock::runForInstaller
         * Responsabilidade: Abre uma janela estrutural de escopo process-local somente para o instalador autorizado.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `prontoo_install`.
         * Dependências chamadas: `self::mayOpenInstallerWindow`, `bin2hex`, `random_bytes`.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Estado externo lido: `$GLOBALS`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: A janela estrutural deve continuar fechada para todo runtime comum e expirar automaticamente com a janela Unix pública.
         */
        if (!self::mayOpenInstallerWindow()) {
            throw new \RuntimeException('A janela estrutural só pode ser aberta pelo instalador autorizado ou pela certificação controlada.');
        }
        if (self::$depth !== 0 || self::$nonce !== null) {
            throw new \RuntimeException('Janela estrutural já está ativa.');
        }
        $nonce = bin2hex(random_bytes(32));
        self::$depth = 1;
        self::$nonce = $nonce;
        $GLOBALS['PRONTOO_SCHEMA_MUTATION_NONCE'] = $nonce;
        $GLOBALS['PRONTOO_SCHEMA_INSTALLING'] = true;
        try {
            return $callback();
        } finally {
            unset($GLOBALS['PRONTOO_SCHEMA_INSTALLING'], $GLOBALS['PRONTOO_SCHEMA_MUTATION_NONCE']);
            self::$nonce = null;
            self::$depth = 0;
        }
    }

    public static function isActive(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaMutationLock::isActive
         * Responsabilidade: Confirma que a janela estrutural atual foi aberta por `runForInstaller` e mantém o nonce interno íntegro.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SchemaMutationLock::assertActive`, `db_reject_runtime_ddl`, `run_schema_sql`, `closure@tools/install-security-check.php:44`.
         * Dependências chamadas: `hash_equals`.
         * Estado externo lido: `$GLOBALS`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: Não aceite marcador parcial nem valor vindo da requisição.
         */
        $provided = (string) ($GLOBALS['PRONTOO_SCHEMA_MUTATION_NONCE'] ?? '');
        return self::$depth === 1 &&
            self::$nonce !== null &&
            $provided !== '' &&
            hash_equals(self::$nonce, $provided);
    }

    public static function assertActive(): void
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaMutationLock::assertActive
         * Responsabilidade: Impede qualquer DDL fora da janela privada aberta por `runForInstaller`.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `schema_cleanup_failed_install`, `install_fresh_schema`.
         * Dependências chamadas: `self::isActive`.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: O runtime normal deve continuar invariavelmente congelado.
         */
        if (!self::isActive()) {
            throw new \RuntimeException('A estrutura do banco está congelada fora da janela privada do instalador.');
        }
    }

    private static function mayOpenInstallerWindow(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaMutationLock::mayOpenInstallerWindow
         * Responsabilidade: Autoriza a certificação CLI integralmente marcada ou o mesmo POST público de instalação limpa durante a janela Unix temporária.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SchemaMutationLock::runForInstaller`.
         * Dependências chamadas: `getenv`, `time`, `strtolower`, `trim`, `preg_replace`, `dirname`, `is_file`.
         * Estado externo lido: `PHP_SAPI`, `$_SERVER`, `$_GET` e `ssd/install.lock`.
         * Efeitos colaterais: nenhum; apenas produz uma decisão fail-closed.
         * Cuidado 1: O caminho HTTP deve exigir POST, rota `install`, HTTPS, host canônico, janela Unix ativa e ausência de `install.lock`.
         * Cuidado 2: A certificação deve continuar exigindo os quatro marcadores simultaneamente.
         */
        if (
            PHP_SAPI === 'cli' &&
            (string) getenv('GITHUB_ACTIONS') === 'true' &&
            (string) getenv('CI') === 'true' &&
            (string) getenv('PRONTOO_SCHEMA_TEST_MODE') === '1' &&
            (string) getenv('PRONTOO_INSTALLER_CLI_MODE') === '1'
        ) {
            return true;
        }

        if (PHP_SAPI === 'cli') {
            return false;
        }

        $now = time();
        if ($now < self::PUBLIC_INSTALL_WINDOW_START_UNIX ||
            $now >= self::PUBLIC_INSTALL_WINDOW_END_UNIX) {
            return false;
        }

        if (strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? ''))) !== 'POST') {
            return false;
        }
        if ((string) ($_GET['r'] ?? '') !== 'install') {
            return false;
        }

        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
        if (!in_array($https, ['on', '1'], true) &&
            (string) ($_SERVER['SERVER_PORT'] ?? '') !== '443') {
            return false;
        }

        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host) ?? '';
        if ($host !== 'prontoo.app') {
            return false;
        }

        $root = dirname(__DIR__, 3);
        return !is_file($root . '/ssd/install.lock');
    }
}
