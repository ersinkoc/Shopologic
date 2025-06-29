<?php

declare(strict_types=1);

namespace Shopologic\Core\Router;

class CompiledRoute
{
    public function __construct(
        private string $pattern,
        private array $variables,
        private array $methods,
        private ?string $domain = null
    ) {}

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function matches(string $method, string $path, ?string $domain = null): array|false
    {
        if (!in_array($method, $this->methods)) {
            return false;
        }

        if ($this->domain && $this->domain !== $domain) {
            return false;
        }

        if (preg_match($this->pattern, $path, $matches)) {
            $parameters = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $parameters[$key] = $value;
                }
            }
            return $parameters;
        }

        return false;
    }
}