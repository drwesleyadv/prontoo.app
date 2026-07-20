<?php
declare(strict_types=1);
namespace Prontoo\Core\Invariant;

final readonly class Decision
{
    public function __construct(
        public bool $allowed,
        public bool $skipped,
        public string $scope,
        public ?string $module,
        public string $operation,
        public string $reason,
        public array $evidence,
        public string $proofHash,
    ) {}

    public static function allow(
        string $scope,
        ?string $module,
        string $operation,
        string $reason,
        array $evidence = [],
    ): self {
        return self::make(true, false, $scope, $module, $operation, $reason, $evidence);
    }

    public static function deny(
        string $scope,
        ?string $module,
        string $operation,
        string $reason,
        array $evidence = [],
    ): self {
        return self::make(false, false, $scope, $module, $operation, $reason, $evidence);
    }

    public static function skip(string $reason, array $evidence = []): self
    {
        return self::make(true, true, "none", null, "view", $reason, $evidence);
    }

    private static function make(
        bool $allowed,
        bool $skipped,
        string $scope,
        ?string $module,
        string $operation,
        string $reason,
        array $evidence,
    ): self {
        $payload = [
            "allowed" => $allowed,
            "skipped" => $skipped,
            "scope" => $scope,
            "module" => $module,
            "operation" => $operation,
            "reason" => $reason,
            "evidence" => $evidence,
        ];
        return new self(
            $allowed,
            $skipped,
            $scope,
            $module,
            $operation,
            $reason,
            $evidence,
            Canonical::hash("decision", $payload),
        );
    }

    public function toArray(): array
    {
        return [
            "allowed" => $this->allowed,
            "skipped" => $this->skipped,
            "scope" => $this->scope,
            "module" => $this->module,
            "operation" => $this->operation,
            "reason" => $this->reason,
            "evidence" => $this->evidence,
            "proof_hash" => $this->proofHash,
        ];
    }
}
