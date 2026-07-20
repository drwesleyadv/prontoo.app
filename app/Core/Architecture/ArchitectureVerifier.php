<?php
declare(strict_types=1);

namespace Prontoo\Core\Architecture;

use Prontoo\Application\Authorization\ActionCatalog;

final class ArchitectureVerifier
{
    private const POLICY = 'php-layered-invariants-v1';

    /** @var array<string,array<string,mixed>> */
    private static array $cache = [];

    private function __construct() {}

    public static function report(string $root, bool $strictActions = false): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $cacheKey = $root . '|' . ($strictActions ? 'strict' : 'runtime');
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $errors = [];
        $warnings = [];
        $files = LayerMap::phpFiles($root);
        $classified = [];
        $layerCounts = [];

        foreach ($files as $file) {
            $relative = ltrim(str_replace($root . '/', '', $file), '/');
            $layer = LayerMap::layerFor($relative);
            if ($layer === null) {
                $errors[] = 'unclassified:' . $relative;
                continue;
            }
            $classified[$relative] = $layer;
            $layerCounts[$layer] = ($layerCounts[$layer] ?? 0) + 1;
            self::inspectDependencies($root, $relative, $layer, $errors);
            self::inspectLayerNativeFile($root, $relative, $layer, $errors);
        }

        $runtimeModules = function_exists('prontoo_full_runtime_modules')
            ? array_values(array_unique(array_map('strval', \prontoo_full_runtime_modules())))
            : [];
        foreach ($runtimeModules as $module) {
            $relative = 'app/' . ltrim($module, '/');
            if (!isset($classified[$relative])) {
                $errors[] = 'runtime_module_without_layer:' . $relative;
            }
        }

        $routes = function_exists('prontoo_route_map')
            ? array_fill_keys(array_map('strval', \prontoo_route_map()), true)
            : [];
        $knownActions = [];
        $sources = [];
        $contracts = ActionCatalog::all();
        foreach ($contracts as $contract) {
            $knownActions[$contract->action] = true;
            $source = 'app/' . ltrim($contract->source, '/');
            $sources[$source] = true;
            if (!is_file($root . '/' . $source)) {
                $errors[] = 'action_source_missing:' . $contract->route . ':' . $contract->action . ':' . $source;
            }
            if ($routes !== [] && !isset($routes[$contract->route])) {
                $errors[] = 'action_route_missing:' . $contract->route . ':' . $contract->action;
            }
            if ($contract->action !== ActionCatalog::DEFAULT_ACTION &&
                $contract->action !== 'onboarding_tip_dismiss' &&
                is_file($root . '/' . $source)) {
                $content = (string) @file_get_contents($root . '/' . $source);
                if (!str_contains($content, $contract->action)) {
                    $errors[] = 'action_token_not_in_source:' . $contract->route . ':' . $contract->action . ':' . $source;
                }
            }
        }

        $catalogTest = ActionCatalog::logicSelfTest();
        if (empty($catalogTest['ok'])) {
            $errors[] = 'action_catalog_selftest:' . implode(',', (array) ($catalogTest['failed'] ?? []));
        }

        $discovered = [];
        $discoveredSources = [];
        foreach (array_keys($sources) as $source) {
            $path = $root . '/' . $source;
            if (!is_file($path)) {
                continue;
            }
            foreach (self::discoverActionTokens((string) @file_get_contents($path)) as $token) {
                $discovered[$token] = true;
                $discoveredSources[$token][$source] = true;
            }
        }
        $unregistered = array_values(array_diff(array_keys($discovered), array_keys($knownActions)));
        sort($unregistered, SORT_STRING);
        foreach ($unregistered as $token) {
            $locations = implode('|', array_keys((array) ($discoveredSources[$token] ?? [])));
            $message = 'action_literal_without_contract:' . $token . ($locations !== '' ? ':' . $locations : '');
            if ($strictActions) {
                $errors[] = $message;
            } else {
                $warnings[] = $message;
            }
        }

        self::inspectArchitectureManifest($root, $errors);
        self::inspectRemovedLegacy($root, $errors, $warnings);

        $total = count($files);
        $covered = count($classified);
        $coverage = $total > 0 ? round(($covered / $total) * 100, 2) : 0.0;
        if ($coverage !== 100.0) {
            $errors[] = 'architecture_coverage_below_100:' . $coverage;
        }

        $report = [
            'ok' => $errors === [],
            'policy' => self::POLICY,
            'files_total' => $total,
            'files_classified' => $covered,
            'coverage_percent' => $coverage,
            'runtime_modules_total' => count($runtimeModules),
            'action_contracts_total' => count($contracts),
            'action_literals_discovered' => count($discovered),
            'action_literal_sources' => array_map(
                static fn(array $items): array => array_keys($items),
                $discoveredSources,
            ),
            'layers' => $layerCounts,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'catalog' => $catalogTest,
        ];
        return self::$cache[$cacheKey] = $report;
    }

    public static function assert(string $root, bool $strictActions = false): void
    {
        $report = self::report($root, $strictActions);
        if (empty($report['ok'])) {
            throw new \RuntimeException(
                'Contrato arquitetural divergente: ' .
                    implode(', ', array_slice((array) $report['errors'], 0, 12)),
            );
        }
    }

    private static function inspectDependencies(
        string $root,
        string $relative,
        string $fromLayer,
        array &$errors,
    ): void {
        $content = (string) @file_get_contents($root . '/' . $relative);
        if ($content === '') {
            return;
        }
        preg_match_all(
            '/Prontoo\\\\(?:Core|Domain|Application|Infrastructure|Presentation|Runtime|Install)\\\\[A-Za-z0-9_\\\\]+/',
            $content,
            $matches,
        );
        foreach (array_unique((array) ($matches[0] ?? [])) as $reference) {
            $toLayer = LayerMap::layerFromNamespace((string) $reference);
            if ($toLayer !== null && !LayerMap::dependencyAllowed($fromLayer, $toLayer)) {
                $errors[] = 'layer_dependency_violation:' .
                    $relative . ':' . $fromLayer . '->' . $toLayer . ':' . $reference;
            }
        }
    }

    private static function inspectLayerNativeFile(
        string $root,
        string $relative,
        string $layer,
        array &$errors,
    ): void {
        $nativePrefixes = [
            'app/Core/Architecture/',
            'app/Domain/Authorization/',
            'app/Application/',
            'app/Infrastructure/',
            'app/Presentation/',
            'app/Runtime/LayeredKernel.php',
        ];
        $native = false;
        foreach ($nativePrefixes as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                $native = true;
                break;
            }
        }
        if (!$native) {
            return;
        }
        $content = (string) @file_get_contents($root . '/' . $relative);
        if (!preg_match('/^\s*<\?php\s+declare\(strict_types=1\);\s+namespace\s+Prontoo\\\\/s', $content)) {
            $errors[] = 'layer_native_file_without_namespace:' . $relative;
        }
        if (in_array($layer, [LayerMap::CORE, LayerMap::DOMAIN, LayerMap::APPLICATION, LayerMap::PRESENTATION], true) &&
            preg_match('/(?<![A-Za-z0-9_\\\\])pdo\s*\(/i', $content)) {
            $errors[] = 'persistence_leak_outside_infrastructure:' . $relative;
        }
        if (preg_match('/^\s*function\s+[a-zA-Z_]/m', $content)) {
            $errors[] = 'layer_native_global_function:' . $relative;
        }
    }

    private static function inspectArchitectureManifest(string $root, array &$errors): void
    {
        $file = $root . '/app/architecture.manifest.json';
        $raw = is_file($file) ? @file_get_contents($file) : false;
        $manifest = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($manifest)) {
            $errors[] = 'architecture_manifest_missing_or_invalid';
            return;
        }
        if ((string) ($manifest['policy'] ?? '') !== self::POLICY) {
            $errors[] = 'architecture_manifest_policy';
        }
        if ((float) ($manifest['coverage_target_percent'] ?? 0) !== 100.0) {
            $errors[] = 'architecture_manifest_coverage_target';
        }
        if ((string) ($manifest['version'] ?? '') !== (defined('PRONTOO_VERSION') ? (string) PRONTOO_VERSION : (string) ($manifest['version'] ?? ''))) {
            $errors[] = 'architecture_manifest_version';
        }
    }

    private static function inspectRemovedLegacy(string $root, array &$errors, array &$warnings): void
    {
        foreach ([
            'app/Core/Invariant/ActionCapabilityCatalog.php',
            'app/Core/Invariant/CapabilityInvariant.php',
        ] as $legacyFile) {
            if (is_file($root . '/' . $legacyFile)) {
                $errors[] = 'legacy_authorization_file_present:' . $legacyFile;
            }
        }
        $bridge = $root . '/app/Core/Invariant/Request/ActionProof.php';
        if (is_file($bridge)) {
            $content = (string) @file_get_contents($bridge);
            if (str_contains($content, 'pdo(') || str_contains($content, 'AuthorizationService')) {
                $errors[] = 'compatibility_bridge_contains_business_logic:app/Core/Invariant/Request/ActionProof.php';
            }
        }
        $kernel = $root . '/app/Core/Invariant/InvariantKernel.php';
        if (is_file($kernel) && preg_match('/CapabilityInvariant|AuthorizationService|ActionCatalog/', (string) @file_get_contents($kernel))) {
            $errors[] = 'core_kernel_depends_on_authorization_application';
        }
        $security = $root . '/app/Support/SecurityAccess.php';
        if (is_file($security) && str_contains((string) @file_get_contents($security), 'function enforce_action_integrity')) {
            $warnings[] = 'thin_compatibility_adapter_active:enforce_action_integrity';
        }
    }

    /** @return list<string> */
    private static function discoverActionTokens(string $content): array
    {
        $tokens = [];
        $patterns = [
            '/name=["\']act["\'][^>]{0,180}value=["\']([a-z0-9_-]+)["\']/i',
            '/value=["\']([a-z0-9_-]+)["\'][^>]{0,180}name=["\']act["\']/i',
            '/\$act\s*={2,3}\s*["\']([a-z0-9_-]+)["\']/i',
        ];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            foreach ((array) ($matches[1] ?? []) as $token) {
                $token = trim((string) $token);
                if ($token !== '' && $token !== '0' && $token !== '1') {
                    $tokens[$token] = true;
                }
            }
        }
        preg_match_all('/in_array\(\s*\$act\s*,\s*\[([^\]]+)\]/is', $content, $groups);
        foreach ((array) ($groups[1] ?? []) as $group) {
            preg_match_all('/["\']([a-z0-9_-]+)["\']/i', (string) $group, $values);
            foreach ((array) ($values[1] ?? []) as $token) {
                $tokens[(string) $token] = true;
            }
        }
        ksort($tokens, SORT_STRING);
        return array_keys($tokens);
    }
}
