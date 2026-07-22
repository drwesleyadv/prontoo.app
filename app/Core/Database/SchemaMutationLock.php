<?php
declare(strict_types=1);

namespace Prontoo\Core\Database;


final class SchemaMutationLock
{
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
         * Cuidado 1: O `schema.sql` é congelado em runtime; após o comissionamento, a janela estrutural existe somente na certificação CLI do GitHub Actions com todos os marcadores exigidos.
         */
    }

    public static function runForInstaller(callable $callback): mixed
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaMutationLock::runForInstaller
         * Responsabilidade: Implementa a responsabilidade “run for installer” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `prontoo_install`.
         * Dependências chamadas: `self::mayOpenInstallerWindow`, `bin2hex`, `random_bytes`.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Estado externo lido: `$GLOBALS`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: O `schema.sql` é congelado em runtime; após o comissionamento, a janela estrutural existe somente na certificação CLI do GitHub Actions com todos os marcadores exigidos.
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
         * Responsabilidade: Implementa a responsabilidade “is active” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SchemaMutationLock::assertActive`, `db_reject_runtime_ddl`, `run_schema_sql`, `closure@tools/install-security-check.php:44`.
         * Dependências chamadas: `hash_equals`.
         * Estado externo lido: `$GLOBALS`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; após o comissionamento, a janela estrutural existe somente na certificação CLI do GitHub Actions com todos os marcadores exigidos.
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
         * Responsabilidade: Implementa a responsabilidade “assert active” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `schema_cleanup_failed_install`, `install_fresh_schema`.
         * Dependências chamadas: `self::isActive`.
         * Classes ou serviços instanciados: `.RuntimeException`.
         * Efeitos colaterais: pode interromper o fluxo por exceção.
         * Cuidado 1: O `schema.sql` é congelado em runtime; após o comissionamento, a janela estrutural existe somente na certificação CLI do GitHub Actions com todos os marcadores exigidos.
         */
        if (!self::isActive()) {
            throw new \RuntimeException('A estrutura do banco está congelada fora da janela privada do instalador.');
        }
    }

    private static function mayOpenInstallerWindow(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaMutationLock::mayOpenInstallerWindow
         * Responsabilidade: Autoriza a abertura transitória da estrutura exclusivamente na certificação CLI do GitHub Actions. HTTP, localhost e variáveis parciais permanecem bloqueados.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SchemaMutationLock::runForInstaller`.
         * Dependências chamadas: `getenv`.
         * Estado externo lido: `PHP_SAPI` e os quatro marcadores da certificação.
         * Efeitos colaterais: nenhum; apenas produz uma decisão fail-closed.
         * Cuidado 1: Não reintroduza bypass de instalação local, autorização HTTP ou exceção por localhost.
         * Cuidado 2: A condição deve continuar exigindo `GITHUB_ACTIONS=true`, `CI=true`, `PRONTOO_SCHEMA_TEST_MODE=1` e `PRONTOO_INSTALLER_CLI_MODE=1` ao mesmo tempo.
         */
        return PHP_SAPI === 'cli' &&
            (string) getenv('GITHUB_ACTIONS') === 'true' &&
            (string) getenv('CI') === 'true' &&
            (string) getenv('PRONTOO_SCHEMA_TEST_MODE') === '1' &&
            (string) getenv('PRONTOO_INSTALLER_CLI_MODE') === '1';
    }
}
