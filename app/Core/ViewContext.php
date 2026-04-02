<?php

declare(strict_types=1);

namespace App\Core;

use LogicException;

final class ViewContext
{
    /** @var array<string, mixed> */
    private array $data = [];

    private ?string $layout = null;

    /** @var array<string, mixed> */
    private array $layoutData = [];

    /** @var array<string, string> */
    private array $sections = [];

    private ?string $activeSection = null;

    public function __construct(
        private readonly View $view
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function allData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function layout(string $template, array $data = []): void
    {
        $this->layout = $template;
        $this->layoutData = $data;
    }

    public function layoutTemplate(): ?string
    {
        return $this->layout;
    }

    /**
     * @return array<string, mixed>
     */
    public function layoutData(): array
    {
        return $this->layoutData;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPartial(string $template, array $data = []): string
    {
        return $this->view->renderPartial($template, array_merge($this->data, $data));
    }

    public function capture(callable $callback): string
    {
        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    public function start(string $section): void
    {
        if ($this->activeSection !== null) {
            throw new LogicException('A section is already being captured.');
        }

        $this->activeSection = $section;
        ob_start();
    }

    public function end(): void
    {
        if ($this->activeSection === null) {
            throw new LogicException('No section is currently being captured.');
        }

        $this->sections[$this->activeSection] = (string) ob_get_clean();
        $this->activeSection = null;
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return array_key_exists($name, $this->sections);
    }

    public function setSection(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }
}
