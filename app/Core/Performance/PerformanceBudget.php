<?php
declare(strict_types=1);

namespace Prontoo\Core\Performance;

use InvalidArgumentException;

final class PerformanceBudget
{
    private const METRICS = [
        'max_elapsed_ms',
        'max_query_ms',
        'max_queries',
        'max_wide_selects',
        'max_module_files',
        'max_module_bytes',
    ];

    public static function resolve(array $contract, string $route): array
    {
        $defaults = isset($contract['defaults']) && is_array($contract['defaults'])
            ? $contract['defaults']
            : [];
        $routes = isset($contract['routes']) && is_array($contract['routes'])
            ? $contract['routes']
            : [];
        $specific = isset($routes[$route]) && is_array($routes[$route])
            ? $routes[$route]
            : [];
        return self::normalize(array_replace($defaults, $specific));
    }

    public static function evaluate(array $contract, string $route, array $event): array
    {
        $budget = self::resolve($contract, $route);
        $mapping = [
            'max_elapsed_ms' => 'elapsed_ms',
            'max_query_ms' => 'query_ms',
            'max_queries' => 'queries',
            'max_wide_selects' => 'wide_selects',
            'max_module_files' => 'module_files',
            'max_module_bytes' => 'module_bytes',
        ];
        $breaches = [];
        foreach ($mapping as $limitKey => $eventKey) {
            $actual = max(0, (float) ($event[$eventKey] ?? 0));
            $limit = (float) $budget[$limitKey];
            if ($actual > $limit) {
                $breaches[$eventKey] = [
                    'actual' => $actual,
                    'limit' => $limit,
                    'ratio' => round($limit > 0 ? $actual / $limit : 0, 4, \RoundingMode::HalfAwayFromZero),
                ];
            }
        }
        return [
            'route' => $route,
            'ok' => $breaches === [],
            'budget' => $budget,
            'breaches' => $breaches,
        ];
    }

    public static function normalize(array $budget): array
    {
        $normalized = [];
        foreach (self::METRICS as $metric) {
            if (!array_key_exists($metric, $budget) || !is_numeric($budget[$metric])) {
                throw new InvalidArgumentException('Orçamento de desempenho incompleto: ' . $metric);
            }
            $value = (float) $budget[$metric];
            if ($value < 0) {
                throw new InvalidArgumentException('Orçamento de desempenho negativo: ' . $metric);
            }
            $normalized[$metric] = in_array($metric, ['max_elapsed_ms', 'max_query_ms'], true)
                ? round($value, 3)
, \RoundingMode::HalfAwayFromZero                : (int) round($value);, 0, \RoundingMode::HalfAwayFromZero
        }
        return $normalized;
    }
}
