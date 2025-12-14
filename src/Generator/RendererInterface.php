<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Interface for template rendering.
 */
interface RendererInterface
{
 /**
  * Set template variables.
  *
  * @param array<string, mixed> $vars
  *
  * @return void
  */
    public function set(array $vars): void;

    /**
     * Render a template with the current variables.
     *
     * @param string $template Template name
     * @param array<string, mixed>|null $vars Additional variables
     *
     * @return string Rendered content
     */
    public function generate(string $template, ?array $vars = null): string;
}
