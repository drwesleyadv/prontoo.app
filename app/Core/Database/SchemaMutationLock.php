<?php
declare(strict_types=1);

namespace Prontoo\Core\Database;

use Prontoo\Core\Install\InstallAccess;

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
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
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
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if (!self::mayOpenInstallerWindow()) {
            throw new \RuntimeException('A janela estrutural só pode ser aberta pelo instalador local ou pela certificação controlada.');
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
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
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
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if (!self::isActive()) {
            throw new \RuntimeException('A estrutura do banco está congelada fora da janela privada do instalador.');
        }
    }

    private static function mayOpenInstallerWindow(): bool
    {
        /*
         * GUIA DE MANUTENÇÃO — Core.Database.SchemaMutationLock::mayOpenInstallerWindow
         * Responsabilidade: Implementa a responsabilidade “may open installer window” dentro do módulo de núcleo de invariantes e decisões canônicas.
         * Local arquitetural: app/Core/Database/SchemaMutationLock.php (núcleo de invariantes e decisões canônicas).
         * Chamadores detectados: `Core.Database.SchemaMutationLock::runForInstaller`.
         * Dependências chamadas: `getenv`, `InstallAccess::isLocalHttpRequest`.
         * Efeitos colaterais: nenhum efeito externo evidente na análise estática.
         * Cuidado 1: O `schema.sql` é congelado em runtime; mudanças estruturais só podem ocorrer na instalação local ou no CI autorizado.
         */
        if (PHP_SAPI === 'cli') {
            return (string) getenv('PRONTOO_SCHEMA_TEST_MODE') === '1' ||
                (string) getenv('PRONTOO_ALLOW_LOCAL_INSTALL') === '1';
        }
        return InstallAccess::isLocalHttpRequest();
    }
}
