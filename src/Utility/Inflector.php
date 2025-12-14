<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Utility;

/**
 * Pure PHP inflector for string transformations.
 */
class Inflector {

	/**
	 * @var array<string, string>
	 */
	protected static array $singularRules = [
		'/(s)tatuses$/i' => '\1tatus',
		'/^(.*)(menu)s$/i' => '\1\2',
		'/(quiz)zes$/i' => '\1',
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
		'/(m)ovies$/i' => '\1ovie',
		'/(s)eries$/i' => '\1eries',
		'/([^aeiouy]|qu)ies$/i' => '\1y',
		'/([lr])ves$/i' => '\1f',
		'/(tive)s$/i' => '\1',
		'/(hive)s$/i' => '\1',
		'/(drive)s$/i' => '\1',
		'/([^fo])ves$/i' => '\1fe',
		'/(^analy)ses$/i' => '\1sis',
		'/(analy|diagno|parenthe|progno|synop|the|empha|cri|ne)ses$/i' => '\1sis',
		'/([ti])a$/i' => '\1um',
		'/(p)eople$/i' => '\1erson',
		'/(m)en$/i' => '\1an',
		'/(c)hildren$/i' => '\1hild',
		'/(n)ews$/i' => '\1ews',
		'/eaus$/' => 'eau',
		'/^(.*us)$/' => '\1',
		'/s$/i' => '',
	];

	/**
	 * @var array<string, string>
	 */
	protected static array $uninflected = [
		'.*[nrlm]ese',
		'.*data',
		'.*deer',
		'.*fish',
		'.*measles',
		'.*media',
		'.*news',
		'.*offspring',
		'.*pox',
		'.*series',
		'.*sheep',
		'.*species',
		'.*swiss',
	];

	/**
	 * @var array<string, string>
	 */
	protected static array $irregular = [
		'foes' => 'foe',
		'waves' => 'wave',
		'curves' => 'curve',
		'employees' => 'employee',
		'slaves' => 'slave',
		'moves' => 'move',
		'feet' => 'foot',
		'geese' => 'goose',
		'teeth' => 'tooth',
		'criteria' => 'criterion',
	];

	/**
	 * Convert a string to CamelCase (PascalCase).
	 *
	 * @param string $string
	 * @return string
	 */
	public static function camelize(string $string): string {
		$result = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string)));

		return $result;
	}

	/**
	 * Convert a string to snake_case.
	 *
	 * @param string $string
	 * @return string
	 */
	public static function underscore(string $string): string {
		$result = preg_replace('/([a-z\d])([A-Z])/', '\1_\2', $string);
		$result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '\1_\2', (string)$result);

		return strtolower((string)$result);
	}

	/**
	 * Convert a string to camelCase (first letter lowercase).
	 *
	 * @param string $string
	 * @return string
	 */
	public static function variable(string $string): string {
		$camelized = static::camelize($string);

		return lcfirst($camelized);
	}

	/**
	 * Return the singular form of a word.
	 *
	 * @param string $word
	 * @return string
	 */
	public static function singularize(string $word): string {
		if (empty($word)) {
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
	 * @return string
	 */
	public static function dasherize(string $string): string {
		return str_replace('_', '-', static::underscore($string));
	}

}
