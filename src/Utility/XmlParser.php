<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Utility;

use DOMDocument;
use RuntimeException;
use SimpleXMLElement;

/**
 * Pure PHP XML parser.
 */
class XmlParser {

	/**
	 * Build a SimpleXMLElement from a string.
	 *
	 * @param string $xml
	 * @return \SimpleXMLElement
	 * @throws \RuntimeException
	 */
	public static function build(string $xml): SimpleXMLElement {
		libxml_use_internal_errors(true);

		$element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

		if ($element === false) {
			$errors = libxml_get_errors();
			libxml_clear_errors();
			$errorMessages = [];
			foreach ($errors as $error) {
				$errorMessages[] = trim($error->message) . ' on line ' . $error->line;
			}

			throw new RuntimeException('XML parsing failed: ' . implode(', ', $errorMessages));
		}

		return $element;
	}

	/**
	 * Convert a SimpleXMLElement to an array.
	 *
	 * @param \SimpleXMLElement $xml
	 * @return array<string, mixed>
	 */
	public static function toArray(SimpleXMLElement $xml): array {
		$result = [];
		$name = $xml->getName();

		// Get attributes
		$attributes = [];
		foreach ($xml->attributes() as $attrName => $attrValue) {
			$attributes['@' . $attrName] = (string)$attrValue;
		}

		// Get children
		$children = [];
		foreach ($xml->children() as $childName => $child) {
			$childArray = static::elementToArray($child);
			if (isset($children[$childName])) {
				if (!is_array($children[$childName]) || !isset($children[$childName][0])) {
					$children[$childName] = [$children[$childName]];
				}
				$children[$childName][] = $childArray;
			} else {
				$children[$childName] = $childArray;
			}
		}

		// Get text content
		$text = trim((string)$xml);

		// Build result
		if (!empty($attributes) || !empty($children)) {
			$result[$name] = array_merge($attributes, $children);
			if (!empty($text) && empty($children)) {
				$result[$name]['@value'] = $text;
			}
		} elseif (!empty($text)) {
			$result[$name] = $text;
		} else {
			$result[$name] = [];
		}

		return $result;
	}

	/**
	 * Convert a child element to array format.
	 *
	 * @param \SimpleXMLElement $element
	 * @return array<string, mixed>|string
	 */
	protected static function elementToArray(SimpleXMLElement $element): array|string {
		$result = [];

		// Get attributes
		foreach ($element->attributes() as $attrName => $attrValue) {
			$result['@' . $attrName] = (string)$attrValue;
		}

		// Get children
		foreach ($element->children() as $childName => $child) {
			$childArray = static::elementToArray($child);
			if (isset($result[$childName])) {
				if (!is_array($result[$childName]) || !isset($result[$childName][0])) {
					$result[$childName] = [$result[$childName]];
				}
				$result[$childName][] = $childArray;
			} else {
				$result[$childName] = $childArray;
			}
		}

		// Get text content
		$text = trim((string)$element);

		if (empty($result)) {
			return $text;
		}

		if (!empty($text)) {
			$result['@value'] = $text;
		}

		return $result;
	}

	/**
	 * Validate an XML file against an XSD schema.
	 *
	 * @param string $xmlFile
	 * @param string $xsdFile
	 * @return void
	 * @throws \RuntimeException
	 */
	public static function validate(string $xmlFile, string $xsdFile): void {
		libxml_use_internal_errors(true);

		$dom = new DOMDocument();
		$dom->load($xmlFile);

		if (!$dom->schemaValidate($xsdFile)) {
			$errors = libxml_get_errors();
			libxml_clear_errors();
			$errorMessages = [];
			foreach ($errors as $error) {
				$errorMessages[] = trim($error->message) . ' on line ' . $error->line;
			}

			throw new RuntimeException('XML validation failed in ' . $xmlFile . ': ' . implode(', ', $errorMessages));
		}
	}

}
