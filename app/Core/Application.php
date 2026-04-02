<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use InvalidArgumentException;

final class Application
{
    /**
     * @var array<class-string, Closure(self): object>
     */
    private array $bindings = [];

    /**
     * @var array<class-string, object>
     */
    private array $instances = [];

    public function __construct(
        private readonly string $rootPath
    ) {
    }

    public function rootPath(string $path = ''): string
    {
        return rtrim($this->rootPath . '/' . ltrim($path, '/'), '/');
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @param Closure(self): T $factory
     */
    public function singleton(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    public function get(string $abstract): object
    {
        if (array_key_exists($abstract, $this->instances)) {
            /** @var T $instance */
            $instance = $this->instances[$abstract];

            return $instance;
        }

        if (!array_key_exists($abstract, $this->bindings)) {
            throw new InvalidArgumentException(sprintf('No binding registered for [%s].', $abstract));
        }

        $this->instances[$abstract] = $this->bindings[$abstract]($this);

        /** @var T $instance */
        $instance = $this->instances[$abstract];

        return $instance;
    }
}
