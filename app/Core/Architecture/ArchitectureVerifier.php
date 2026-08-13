<?php
declare(strict_types=1);

namespace Prontoo\Core\Architecture;

use Prontoo\Application\Authorization\ActionCatalog;

final class ArchitectureVerifier
{
    private const POLICY = 'php-layered-invariants-v2';

    private static array $cache = [];

    private function __construct() {

    }

    public static function report(
        string $root,
        bool $strictActions = false,
        array $runtimeModules = [],
        array $routes = [],
    ): array
    {

        $root = rtrim(str_replace('\\', '/', $root), '/');
        $runtimeModules = array_values(array_unique(array_map('strval', $runtimeModules)));
        $routes = array_values(array_unique(array_map('strval', $routes)));
        $cacheKey = $root . '|' .
            ($strictActions ? 'strict' : 'runtime') . '|' .
            hash('sha256', serialize([$runtimeModules, $routes]));
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $errors = [];
        $warnings = [];
        $files = LayerMap::phpFiles($root);
        $classified = [];
        $layerCounts = [];
        $compositionRoleCounts = [];
        $nativeFiles = [];
        $transitionalFiles = [];

        foreach ($files as $file) {
            $relative = ltrim(str_replace($root . '/', '', $file), '/');
            $layer = LayerMap::layerFor($relative);
            if ($layer === null) {
                $errors[] = 'unclassified:' . $relative;
                continue;
            }
            $classified[$relative] = $layer;
            $layerCounts[$layer] = ($layerCounts[$layer] ?? 0) + 1;
            $compositionRole = LayerMap::compositionRoleFor($relative);
            if ($compositionRole !== null) {
                $compositionRoleCounts[$compositionRole] = ($compositionRoleCounts[$compositionRole] ?? 0) + 1;
            }
            if (LayerMap::isNativePath($relative)) {
                $nativeFiles[] = $relative;
            } else {
                $transitionalFiles[] = $relative;
            }
            if (LayerMap::isNativePath($relative)) {
                self::inspectDependencies($root, $relative, $layer, $errors);
                self::inspectLayerNativeFile($root, $relative, $layer, $errors);
            }
        }

        foreach ($runtimeModules as $module) {
            $relative = 'app/' . ltrim($module, '/');
            if (!isset($classified[$relative])) {
                $errors[] = 'runtime_module_without_layer:' . $relative;
            }
        }

        $routes = array_fill_keys($routes, true);
        $sources = [];
        $knownBySource = [];
        $contracts = ActionCatalog::all();
        foreach ($contracts as $contract) {
            $source = 'app/' . ltrim($contract->source, '/');
            $sources[$source] = true;
            if ($contract->action !== ActionCatalog::DEFAULT_ACTION) {
                $knownBySource[$source][$contract->action] = true;
            }
            if (self::canonicalSourceContent($root, $source) === '') {
                $errors[] = 'action_source_missing:' . $contract->route . ':' . $contract->action . ':' . $source;
            }
            if ($routes !== [] && !isset($routes[$contract->route])) {
                $errors[] = 'action_route_missing:' . $contract->route . ':' . $contract->action;
            }
            if ($contract->action !== ActionCatalog::DEFAULT_ACTION && self::canonicalSourceContent($root, $source) !== '') {
                $content = self::canonicalSourceContent($root, $source);
                if (!str_contains($content, $contract->action)) {
                    $errors[] = 'action_token_not_in_handler:' . $contract->route . ':' . $contract->action . ':' . $source;
                }
            }
            foreach ($contract->producers as $producerPath) {
                $producer = 'app/' . mb_ltrim((string) $producerPath, '/');
                $sources[$producer] = true;
                if ($contract->action !== ActionCatalog::DEFAULT_ACTION) {
                    $knownBySource[$producer][$contract->action] = true;
                }
                if (self::canonicalSourceContent($root, $producer) === '') {
                    $errors[] = 'action_producer_missing:' . $contract->route . ':' . $contract->action . ':' . $producer;
                    continue;
                }
                if ($contract->action !== ActionCatalog::DEFAULT_ACTION &&
                    !str_contains(self::canonicalSourceContent($root, $producer), $contract->action)) {
                    $errors[] = 'action_token_not_in_producer:' . $contract->route . ':' . $contract->action . ':' . $producer;
                }
            }
        }

        $catalogTest = ActionCatalog::logicSelfTest();
        if (empty($catalogTest['ok'])) {
            $errors[] = 'action_catalog_selftest:' . implode(',', (array) ($catalogTest['failed'] ?? []));
        }

        $discovered = [];
        $discoveredSources = [];
        $unregisteredPairs = [];
        foreach (array_keys($sources) as $source) {
            $content = self::canonicalSourceContent($root, $source);
            if ($content === '') {
                continue;
            }
            foreach (self::discoverActionTokens($content) as $token) {
                $discovered[$token] = true;
                $discoveredSources[$token][$source] = true;
                if (!isset($knownBySource[$source][$token])) {
                    $unregisteredPairs[$source . ':' . $token] = true;
                }
            }
        }
        foreach (array_keys($unregisteredPairs) as $pair) {
            $message = 'action_literal_without_source_contract:' . $pair;
            if ($strictActions) {
                $errors[] = $message;
            } else {
                $warnings[] = $message;
            }
        }

        self::inspectArchitectureManifest(
            $root,
            count($nativeFiles),
            count($transitionalFiles),
            $errors,
        );

        $total = count($files);
        $covered = count($classified);
        $coverage = $total > 0 ? round(($covered / $total) * 100, 2, \RoundingMode::HalfAwayFromZero) : 0.0;
        $nativeCoverage = $total > 0
            ? round((count($nativeFiles) / $total) * 100, 2, \RoundingMode::HalfAwayFromZero)
            : 0.0;
        if ($coverage !== 100.0) {
            $errors[] = 'architecture_classification_coverage_below_100:' . $coverage;
        }
        if ($transitionalFiles !== []) {
            $warnings[] = 'transitional_php_files_active:' . count($transitionalFiles);
        }

        $report = [
            'ok' => $errors === [],
            'policy' => self::POLICY,
            'files_total' => $total,
            'files_classified' => $covered,
            'classification_coverage_percent' => $coverage,
            'native_files_total' => count($nativeFiles),
            'transitional_files_total' => count($transitionalFiles),
            'native_coverage_percent' => $nativeCoverage,
            'native_files' => $nativeFiles,
            'runtime_modules_total' => count($runtimeModules),
            'action_contracts_total' => count($contracts),
            'action_source_contracts_total' => array_sum(array_map('count', $knownBySource)),
            'action_literals_discovered' => count($discovered),
            'action_literal_sources' => array_map(
                static  fn(array $items): array => array_keys($items),
                $discoveredSources,
            ),
            'layers' => $layerCounts,
            'composition_roles' => $compositionRoleCounts,
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

        if (!LayerMap::isNativePath($relative)) {
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

    private static function inspectArchitectureManifest(
        string $root,
        int $nativeFiles,
        int $transitionalFiles,
        array &$errors,
    ): void {

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
        if ((float) ($manifest['classification_coverage_target_percent'] ?? 0) !== 100.0) {
            $errors[] = 'architecture_manifest_classification_target';
        }
        if ($nativeFiles < (int) ($manifest['native_files_min'] ?? PHP_INT_MAX)) {
            $errors[] = 'architecture_native_files_below_baseline:' . $nativeFiles;
        }
        if ($transitionalFiles > (int) ($manifest['transitional_files_max'] ?? -1)) {
            $errors[] = 'architecture_transitional_files_above_ceiling:' . $transitionalFiles;
        }
        if ((string) ($manifest['version'] ?? '') !== (defined('PRONTOO_VERSION') ? (string) PRONTOO_VERSION : (string) ($manifest['version'] ?? ''))) {
            $errors[] = 'architecture_manifest_version';
        }
    }

    private static function canonicalSourceContent(string $root, string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '') {
            return '';
        }
        $path = rtrim($root, '/') . '/' . $relative;
        if (is_file($path)) {
            return (string) @file_get_contents($path);
        }
        if (!is_dir($path)) {
            return '';
        }
        $files = glob($path . '/*.php');
        if (!is_array($files) || $files === []) {
            return '';
        }
        sort($files, SORT_STRING);
        $chunks = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $chunks[] = (string) @file_get_contents($file);
            }
        }
        return implode("\n", $chunks);
    }

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
                $token = mb_trim((string) $token);
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
