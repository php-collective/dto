<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Utility;

/**
 * Pure PHP inflector for string transformations.
 */
class Inflector
{
    /**
     * @var array<string, string>
     */
    protected static array $singularRules = [
        '/(s)tatuses$/i' => '\1\2tatus',
        '/^(.*)(menu)s$/i' => '\1\2',
        '/(quiz)zes$/i' => '\\1',
        '/(matr)ices$/i' => '\1ix',
        '/(vert|ind)ices$/i' => '\1ex',
        '/^(ox)en/i' => '\1',
        '/(alias|lens)(es)*$/i' => '\1',
        '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|viri?)i$/i' => '\1us',
        '/([ftw]ax)es/i' => '\1',
        '/(cris|ax|test)es$/i' => '\1is',
        '/(shoe)s$/i' => '\1',
        '/(o)es$/i' => '\1',
        '/ouses$/' => 'ouse',
        '/([^a])uses$/' => '\1us',
        '/([m|l])ice$/i' => '\1ouse',
        '/(x|ch|ss|sh)es$/i' => '\1',
        '/(m)ovies$/i' => '\1\2ovie',
        '/(s)eries$/i' => '\1\2eries',
        '/(s)pecies$/i' => '\1\2pecies',
        '/([^aeiouy]|qu)ies$/i' => '\1y',
        '/([le])ves$/i' => '\1f',
        '/(tive)s$/i' => '\1',
        '/(hive)s$/i' => '\1',
        '/(drive)s$/i' => '\1',
        '/([^rfoa])ves$/i' => '\1fe',
        '/(^analy)ses$/i' => '\1sis',
        '/(analy|diagno|^ba|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
        '/([ti])a$/i' => '\1um',
        '/(p)eople$/i' => '\1\2erson',
        '/(m)en$/i' => '\1an',
        '/(c)hildren$/i' => '\1\2hild',
        '/(n)ews$/i' => '\1\2ews',
        '/eaus$/' => 'eau',
        '/^(.*us)$/' => '\\1',
        '/s$/i' => '',
    ];

    /**
     * @var array<int, string>
     */
    protected static array $uninflected = [
        '.*[nrlm]ese',
        '.*data',
        '.*deer',
        '.*fish',
        '.*measles',
        '.*ois',
        '.*pox',
        '.*sheep',
        'feedback',
        'stadia',
        '.*?media',
        'chassis',
        'clippers',
        'debris',
        'diabetes',
        'equipment',
        'gallows',
        'headquarters',
        'information',
        'innings',
        'news',
        'nexus',
        'pokemon',
        'proceedings',
        'research',
        'sea[- ]bass',
        'series',
        'species',
        'weather',
        '.*offspring',
        '.*swiss',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $irregular = [
        // CakePHP core irregulars (plural => singular)
        'atlases' => 'atlas',
        'beefs' => 'beef',
        'beeves' => 'beef',
        'briefs' => 'brief',
        'brothers' => 'brother',
        'cafes' => 'cafe',
        'children' => 'child',
        'cookies' => 'cookie',
        'corpuses' => 'corpus',
        'cows' => 'cow',
        'criteria' => 'criterion',
        'ganglions' => 'ganglion',
        'genies' => 'genie',
        'genera' => 'genus',
        'graffiti' => 'graffito',
        'hoofs' => 'hoof',
        'loaves' => 'loaf',
        'men' => 'man',
        'monies' => 'money',
        'mongooses' => 'mongoose',
        'moves' => 'move',
        'mythoi' => 'mythos',
        'niches' => 'niche',
        'numina' => 'numen',
        'occiputs' => 'occiput',
        'octopuses' => 'octopus',
        'opuses' => 'opus',
        'oxen' => 'ox',
        'penises' => 'penis',
        'people' => 'person',
        'sexes' => 'sex',
        'soliloquies' => 'soliloquy',
        'testes' => 'testis',
        'trilbys' => 'trilby',
        'turfs' => 'turf',
        'potatoes' => 'potato',
        'heroes' => 'hero',
        'teeth' => 'tooth',
        'geese' => 'goose',
        'feet' => 'foot',
        'foes' => 'foe',
        'sieves' => 'sieve',
        'caches' => 'cache',
        // Plurals ending in -ves where singular ends in -f (not -fe)
        'leaves' => 'leaf',
        'sheaves' => 'sheaf',
        'thieves' => 'thief',
        'dwarves' => 'dwarf',
        'scarves' => 'scarf',
        // Additional -ves words
        'waves' => 'wave',
        'curves' => 'curve',
        'employees' => 'employee',
        'slaves' => 'slave',
        // Latin/Greek plurals
        'appendices' => 'appendix',
        'indices' => 'index',
        'octopi' => 'octopus',
        'cacti' => 'cactus',
        'fungi' => 'fungus',
        'nuclei' => 'nucleus',
        'radii' => 'radius',
        'stimuli' => 'stimulus',
        'syllabi' => 'syllabus',
        'alumni' => 'alumnus',
        'phenomena' => 'phenomenon',
        'automata' => 'automaton',
        'strata' => 'stratum',
        'errata' => 'erratum',
        'memoranda' => 'memorandum',
        'curricula' => 'curriculum',
        'millennia' => 'millennium',
        'addenda' => 'addendum',
        'bacteria' => 'bacterium',
        'corpora' => 'corpus',
    ];

    /**
     * Convert a string to CamelCase (PascalCase).
     *
     * @param string $string
     *
     * @return string
     */
    public static function camelize(string $string): string
    {
        $result = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string)));

        return $result;
    }

    /**
     * Convert a string to snake_case.
     *
     * @param string $string
     *
     * @return string
     */
    public static function underscore(string $string): string
    {
        $result = preg_replace('/([a-z\d])([A-Z])/', '\1_\2', $string);
        $result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '\1_\2', (string)$result);

        return strtolower((string)$result);
    }

    /**
     * Convert a string to camelCase (first letter lowercase).
     *
     * @param string $string
     *
     * @return string
     */
    public static function variable(string $string): string
    {
        $camelized = static::camelize($string);

        return lcfirst($camelized);
    }

    /**
     * Return the singular form of a word.
     *
     * @param string $word
     *
     * @return string
     */
    public static function singularize(string $word): string
    {
        if (!$word) {
            return $word;
        }

        // Check uninflected
        foreach (static::$uninflected as $pattern) {
            if (preg_match('/^(' . $pattern . ')$/i', $word)) {
                return $word;
            }
        }

        // Check irregulars
        $lower = strtolower($word);
        if (isset(static::$irregular[$lower])) {
            $result = static::$irregular[$lower];
            // Preserve case
            if (ctype_upper($word[0])) {
                $result = ucfirst($result);
            }

            return $result;
        }

        // Apply rules
        foreach (static::$singularRules as $pattern => $replacement) {
            if (preg_match($pattern, $word)) {
                return (string)preg_replace($pattern, $replacement, $word);
            }
        }

        return $word;
    }

    /**
     * Convert a string to dash-case.
     *
     * @param string $string
     *
     * @return string
     */
    public static function dasherize(string $string): string
    {
        return str_replace('_', '-', static::underscore($string));
    }
}
