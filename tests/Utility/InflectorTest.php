<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Utility;

use PhpCollective\Dto\Utility\Inflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InflectorTest extends TestCase
{
 /**
  * @return array<array{string, string}>
  */
    public static function camelizeDataProvider(): array
    {
        return [
            ['test_string', 'TestString'],
            ['test-string', 'TestString'],
            ['test_string_value', 'TestStringValue'],
            ['test-string-value', 'TestStringValue'],
            ['testString', 'TestString'],
            ['TestString', 'TestString'],
            ['', ''],
        ];
    }

    #[DataProvider('camelizeDataProvider')]
    public function testCamelize(string $input, string $expected): void
    {
        $result = Inflector::camelize($input);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<array{string, string}>
     */
    public static function underscoreDataProvider(): array
    {
        return [
            ['TestString', 'test_string'],
            ['testString', 'test_string'],
            ['test_string', 'test_string'],
            ['HTMLParser', 'html_parser'],
            ['simpleXMLParser', 'simple_xml_parser'],
            ['', ''],
        ];
    }

    #[DataProvider('underscoreDataProvider')]
    public function testUnderscore(string $input, string $expected): void
    {
        $result = Inflector::underscore($input);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<array{string, string}>
     */
    public static function variableDataProvider(): array
    {
        return [
            ['test_string', 'testString'],
            ['test-string', 'testString'],
            ['TestString', 'testString'],
            ['testString', 'testString'],
            ['', ''],
        ];
    }

    #[DataProvider('variableDataProvider')]
    public function testVariable(string $input, string $expected): void
    {
        $result = Inflector::variable($input);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<array{string, string}>
     */
    public static function singularizeDataProvider(): array
    {
        return [
            // Basic plurals
            ['users', 'user'],
            ['statuses', 'status'],
            ['quizzes', 'quiz'],
            ['matrices', 'matrix'],
            ['vertices', 'vertex'],
            ['indices', 'index'],
            ['aliases', 'alias'],
            ['shoes', 'shoe'],
            ['movies', 'movie'],
            ['series', 'series'],
            ['news', 'news'],
            ['sheep', 'sheep'],
            ['deer', 'deer'],
            ['children', 'child'],
            ['men', 'man'],
            ['people', 'person'],
            ['teeth', 'tooth'],
            ['feet', 'foot'],
            ['geese', 'goose'],
            ['employees', 'employee'],
            // Words ending in -sses, -xes, -ches, -shes
            ['classes', 'class'],
            ['addresses', 'address'],
            ['processes', 'process'],
            ['taxes', 'tax'],
            ['boxes', 'box'],
            ['buses', 'bus'],
            ['wishes', 'wish'],
            ['watches', 'watch'],
            // Words ending in -ves where singular is -f (not -fe)
            ['leaves', 'leaf'],
            ['sheaves', 'sheaf'],
            ['loaves', 'loaf'],
            ['thieves', 'thief'],
            // Words ending in -ves where singular is -fe
            ['knives', 'knife'],
            ['wives', 'wife'],
            ['lives', 'life'],
            ['wolves', 'wolf'],
            ['halves', 'half'],
            ['selves', 'self'],
            ['calves', 'calf'],
            ['elves', 'elf'],
            ['dwarves', 'dwarf'],
            ['scarves', 'scarf'],
            // Latin/Greek plurals
            ['phenomena', 'phenomenon'],
            ['criteria', 'criterion'],
            ['bacteria', 'bacterium'],
            ['curricula', 'curriculum'],
            ['memoranda', 'memorandum'],
            ['strata', 'stratum'],
            ['appendices', 'appendix'],
            // Words ending in -ies
            ['categories', 'category'],
            ['factories', 'factory'],
            ['countries', 'country'],
            ['babies', 'baby'],
            ['cities', 'city'],
            ['parties', 'party'],
            // Words ending in -es after vowel
            ['potatoes', 'potato'],
            ['heroes', 'hero'],
            ['echoes', 'echo'],
            // Case preservation
            ['Leaves', 'Leaf'],
            ['Categories', 'Category'],
            // Empty string
            ['', ''],
        ];
    }

    #[DataProvider('singularizeDataProvider')]
    public function testSingularize(string $input, string $expected): void
    {
        $result = Inflector::singularize($input);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<array{string, string}>
     */
    public static function dasherizeDataProvider(): array
    {
        return [
            ['TestString', 'test-string'],
            ['testString', 'test-string'],
            ['test_string', 'test-string'],
            ['', ''],
        ];
    }

    #[DataProvider('dasherizeDataProvider')]
    public function testDasherize(string $input, string $expected): void
    {
        $result = Inflector::dasherize($input);
        $this->assertSame($expected, $result);
    }
}
