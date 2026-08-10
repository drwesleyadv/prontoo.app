<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Routing;

final readonly class RouteDefinition
{
    public function __construct(
        public string $name,
        public string $handlerClass,
        public string $handlerMethod,
        public bool $public,
        public bool $json,
        public array $publicLightMethods,
        public array $moduleGroups,
    ) {
    }

    public function isPublicLight(string $method): bool
    {
        $method = strtoupper($method);
        return in_array('*', $this->publicLightMethods, true) ||
            in_array($method, $this->publicLightMethods, true);
    }
}
