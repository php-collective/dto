<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use PhpCollective\Dto\Collection\CollectionAdapterInterface;
use PhpCollective\Dto\Collection\CollectionAdapterRegistry;
use PhpCollective\Dto\Utility\Inflector;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig-based template renderer for DTO code generation.
 */
class TwigRenderer implements RendererInterface
{
    /**
     * @var \Twig\Environment
     */
    protected Environment $twig;

    /**
     * @var array<string, mixed>
     */
    protected array $vars = [];

    /**
     * @var \PhpCollective\Dto\Generator\ConfigInterface
     */
    protected ConfigInterface $config;

    /**
     * @param string|null $templatePath Path to templates directory
     * @param \PhpCollective\Dto\Generator\ConfigInterface|null $config
     */
    public function __construct(?string $templatePath = null, ?ConfigInterface $config = null)
    {
        if ($templatePath === null) {
            $templatePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'templates';
        }

        $loader = new FilesystemLoader($templatePath);
        $this->twig = new Environment($loader, [
            'autoescape' => false,
        ]);

        $this->config = $config ?? new ArrayConfig([]);

        $this->registerFilters();
        $this->registerFunctions();
        $this->setGlobalConfiguration();
    }

    /**
     * Register custom Twig filters.
     *
     * @return void
     */
    protected function registerFilters(): void
    {
        $this->twig->addFilter(new TwigFilter('stringify', [$this, 'stringifyList']));
        $this->twig->addFilter(new TwigFilter('phpExport', [$this, 'phpExport']));
        $this->twig->addFilter(new TwigFilter('underscore', [Inflector::class, 'underscore']));
        $this->twig->addFilter(new TwigFilter('camelize', [Inflector::class, 'camelize']));
        $this->twig->addFilter(new TwigFilter('variable', [Inflector::class, 'variable']));
        $this->twig->addFilter(new TwigFilter('stripLeadingUnderscore', [$this, 'stripLeadingUnderscore']));
    }

    /**
     * Register custom Twig functions.
     *
     * @return void
     */
    protected function registerFunctions(): void
    {
        $this->twig->addFunction(new TwigFunction('getCollectionAdapter', [$this, 'getCollectionAdapter']));
    }

    /**
     * Strip leading underscore from a string.
     *
     * Used for underscore-prefixed field names (e.g., _joinData, _matchingData)
     * to generate proper camelCase method names and constant names.
     *
     * @param string $value
     *
     * @return string
     */
    public function stripLeadingUnderscore(string $value): string
    {
        if (str_starts_with($value, '_')) {
            return substr($value, 1);
        }

        return $value;
    }

    /**
     * Get a collection adapter for the given collection type.
     *
     * This allows templates to generate collection-type-specific code
     * without needing separate template files for each collection type.
     *
     * @param string $collectionType The collection class name
     *
     * @return CollectionAdapterInterface
     */
    public function getCollectionAdapter(string $collectionType): CollectionAdapterInterface
    {
        return CollectionAdapterRegistry::getOrDefault($collectionType);
    }

    /**
     * Set global configuration variables.
     *
     * @return void
     */
    protected function setGlobalConfiguration(): void
    {
        $this->vars['strictTypes'] = (bool)$this->config->get('strictTypes', false);
        $this->vars['scalarAndReturnTypes'] = (bool)$this->config->get('scalarAndReturnTypes', true);
        $this->vars['typedConstants'] = (bool)$this->config->get('typedConstants', false);
    }

    /**
     * @inheritDoc
     */
    public function set(array $vars): void
    {
        $this->vars = array_merge($this->vars, $vars);
    }

    /**
     * @inheritDoc
     */
    public function generate(string $template, ?array $vars = null): string
    {
        if ($vars !== null) {
            $this->vars = array_merge($this->vars, $vars);
        }

        return $this->twig->render($template . '.twig', $this->vars);
    }

    /**
     * Returns an array converted into a formatted multiline string.
     *
     * @param array $list array of items to be stringified
     * @param array<string, mixed> $options options to use
     *
     * @return string
     */
    public function stringifyList(array $list, array $options = []): string
    {
        $options += [
            'indent' => 3,
            'tab' => "\t",
            'trailingComma' => true,
        ];

        if (!$list) {
            return '';
        }

        foreach ($list as $k => &$v) {
            if (is_string($v)) {
                $v = "'$v'";
            } elseif (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            } elseif ($v === null) {
                $v = 'null';
            }

            if (!is_numeric($k)) {
                $nestedOptions = $options;
                if ($nestedOptions['indent']) {
                    $nestedOptions['indent'] += 1;
                }
                if (is_array($v)) {
                    $v = sprintf(
                        "'%s' => [%s]",
                        $k,
                        $this->stringifyList($v, $nestedOptions),
                    );
                } else {
                    $v = "'$k' => $v";
                }
            } elseif (is_array($v)) {
                $nestedOptions = $options;
                if ($nestedOptions['indent']) {
                    $nestedOptions['indent'] += 1;
                }
                $v = sprintf(
                    '[%s]',
                    $this->stringifyList($v, $nestedOptions),
                );
            }
        }

        $start = $end = '';
        $join = ', ';
        if ($options['indent']) {
            $join = ',';
            $start = "\n" . str_repeat($options['tab'], $options['indent']);
            $join .= $start;
            $end = "\n" . str_repeat($options['tab'], $options['indent'] - 1);
        }

        if ($options['trailingComma']) {
            $end = ',' . $end;
        }

        return $start . implode($join, $list) . $end;
    }

    /**
     * Export a PHP value to its code representation.
     *
     * Handles booleans, strings, numbers, arrays, and null properly.
     *
     * @param mixed $value The value to export
     *
     * @return string
     */
    public function phpExport(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            return "'" . addcslashes($value, "'\\") . "'";
        }
        if (is_array($value)) {
            return var_export($value, true);
        }

        return (string)$value;
    }
}
