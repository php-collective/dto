<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Generator\DependencyAnalyzer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for DependencyAnalyzer class.
 */
class DependencyAnalyzerTest extends TestCase
{
    /**
     * Test that circular dependencies between eager fields are detected.
     *
     * @return void
     */
    public function testCircularDependencyWithEagerFieldsThrowsException(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'UserState' => [
                'name' => 'UserState',
                'fields' => [
                    'createdByUser' => [
                        'name' => 'createdByUser',
                        'type' => 'UserDto',
                        'dto' => 'User',
                        'lazy' => false,
                    ],
                ],
            ],
            'User' => [
                'name' => 'User',
                'fields' => [
                    'usersState' => [
                        'name' => 'usersState',
                        'type' => 'UserStateDto',
                        'dto' => 'UserState',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $analyzer->analyze($dtos);
    }

    /**
     * Test that circular dependencies are allowed when all fields in the cycle are lazy.
     *
     * @return void
     */
    public function testCircularDependencyWithLazyFieldsDoesNotThrow(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'UserState' => [
                'name' => 'UserState',
                'fields' => [
                    'createdByUser' => [
                        'name' => 'createdByUser',
                        'type' => 'UserDto',
                        'dto' => 'User',
                        'lazy' => true,
                    ],
                ],
            ],
            'User' => [
                'name' => 'User',
                'fields' => [
                    'usersState' => [
                        'name' => 'usersState',
                        'type' => 'UserStateDto',
                        'dto' => 'UserState',
                        'lazy' => true,
                    ],
                ],
            ],
        ];

        // Should not throw - lazy fields break the circular dependency
        $analyzer->analyze($dtos);

        // If we get here, the test passes
        $this->assertTrue(true);
    }

    /**
     * Test that circular dependencies are allowed when at least one field in the cycle is lazy.
     *
     * @return void
     */
    public function testCircularDependencyWithOneLazyFieldDoesNotThrow(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'UserState' => [
                'name' => 'UserState',
                'fields' => [
                    'createdByUser' => [
                        'name' => 'createdByUser',
                        'type' => 'UserDto',
                        'dto' => 'User',
                        'lazy' => true, // Lazy - breaks the cycle
                    ],
                ],
            ],
            'User' => [
                'name' => 'User',
                'fields' => [
                    'usersState' => [
                        'name' => 'usersState',
                        'type' => 'UserStateDto',
                        'dto' => 'UserState',
                        'lazy' => false, // Eager
                    ],
                ],
            ],
        ];

        // Should not throw - one lazy field is enough to break the cycle
        $analyzer->analyze($dtos);

        // If we get here, the test passes
        $this->assertTrue(true);
    }

    /**
     * Test that a three-way circular dependency with lazy fields is allowed.
     *
     * @return void
     */
    public function testThreeWayCircularDependencyWithLazyFields(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'A' => [
                'name' => 'A',
                'fields' => [
                    'b' => [
                        'name' => 'b',
                        'type' => 'BDto',
                        'dto' => 'B',
                        'lazy' => true,
                    ],
                ],
            ],
            'B' => [
                'name' => 'B',
                'fields' => [
                    'c' => [
                        'name' => 'c',
                        'type' => 'CDto',
                        'dto' => 'C',
                        'lazy' => false,
                    ],
                ],
            ],
            'C' => [
                'name' => 'C',
                'fields' => [
                    'a' => [
                        'name' => 'a',
                        'type' => 'ADto',
                        'dto' => 'A',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        // Should not throw - A->B is lazy, breaking the cycle A->B->C->A
        $analyzer->analyze($dtos);

        $this->assertTrue(true);
    }

    /**
     * Test that a three-way circular dependency with all eager fields throws.
     *
     * @return void
     */
    public function testThreeWayCircularDependencyWithEagerFieldsThrows(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'A' => [
                'name' => 'A',
                'fields' => [
                    'b' => [
                        'name' => 'b',
                        'type' => 'BDto',
                        'dto' => 'B',
                        'lazy' => false,
                    ],
                ],
            ],
            'B' => [
                'name' => 'B',
                'fields' => [
                    'c' => [
                        'name' => 'c',
                        'type' => 'CDto',
                        'dto' => 'C',
                        'lazy' => false,
                    ],
                ],
            ],
            'C' => [
                'name' => 'C',
                'fields' => [
                    'a' => [
                        'name' => 'a',
                        'type' => 'ADto',
                        'dto' => 'A',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $analyzer->analyze($dtos);
    }

    /**
     * Test that non-circular dependencies work normally.
     *
     * @return void
     */
    public function testNonCircularDependenciesWork(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Order' => [
                'name' => 'Order',
                'fields' => [
                    'user' => [
                        'name' => 'user',
                        'type' => 'UserDto',
                        'dto' => 'User',
                        'lazy' => false,
                    ],
                    'items' => [
                        'name' => 'items',
                        'type' => 'OrderItemDto[]',
                        'dto' => 'OrderItem',
                        'lazy' => false,
                    ],
                ],
            ],
            'User' => [
                'name' => 'User',
                'fields' => [
                    'name' => [
                        'name' => 'name',
                        'type' => 'string',
                    ],
                ],
            ],
            'OrderItem' => [
                'name' => 'OrderItem',
                'fields' => [
                    'product' => [
                        'name' => 'product',
                        'type' => 'string',
                    ],
                ],
            ],
        ];

        // Should not throw
        $analyzer->analyze($dtos);

        $this->assertTrue(true);
    }

    /**
     * Test that lazy collection fields with singularType are properly skipped.
     *
     * @return void
     */
    public function testLazyCollectionWithSingularTypeDoesNotThrow(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Parent' => [
                'name' => 'Parent',
                'fields' => [
                    'children' => [
                        'name' => 'children',
                        'type' => 'ChildDto[]',
                        'singularType' => 'ChildDto',
                        'dto' => 'Child',
                        'lazy' => true,
                    ],
                ],
            ],
            'Child' => [
                'name' => 'Child',
                'fields' => [
                    'parent' => [
                        'name' => 'parent',
                        'type' => 'ParentDto',
                        'dto' => 'Parent',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        // Should not throw - lazy collection breaks the cycle
        $analyzer->analyze($dtos);

        $this->assertTrue(true);
    }

    /**
     * Test that eager collection with singularType in circular dependency throws.
     *
     * @return void
     */
    public function testEagerCollectionWithSingularTypeThrows(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Parent' => [
                'name' => 'Parent',
                'fields' => [
                    'children' => [
                        'name' => 'children',
                        'type' => 'ChildDto[]',
                        'singularType' => 'ChildDto',
                        'dto' => 'Child',
                        'lazy' => false,
                    ],
                ],
            ],
            'Child' => [
                'name' => 'Child',
                'fields' => [
                    'parent' => [
                        'name' => 'parent',
                        'type' => 'ParentDto',
                        'dto' => 'Parent',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $analyzer->analyze($dtos);
    }

    /**
     * Test that self-referential lazy fields are allowed.
     *
     * @return void
     */
    public function testSelfReferenceLazyFieldAllowed(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'TreeNode' => [
                'name' => 'TreeNode',
                'fields' => [
                    'parent' => [
                        'name' => 'parent',
                        'type' => 'TreeNodeDto',
                        'dto' => 'TreeNode',
                        'lazy' => true,
                    ],
                    'children' => [
                        'name' => 'children',
                        'type' => 'TreeNodeDto[]',
                        'singularType' => 'TreeNodeDto',
                        'dto' => 'TreeNode',
                        'lazy' => true,
                    ],
                ],
            ],
        ];

        // Self-references with lazy should be allowed
        $analyzer->analyze($dtos);

        $this->assertTrue(true);
    }

    /**
     * Test that self-referential eager fields are detected (but filtered by same-name check).
     *
     * Note: The analyzer excludes self-references from dependencies by design,
     * so eager self-references don't cause circular dependency errors.
     *
     * @return void
     */
    public function testSelfReferenceEagerFieldAllowed(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'TreeNode' => [
                'name' => 'TreeNode',
                'fields' => [
                    'parent' => [
                        'name' => 'parent',
                        'type' => 'TreeNodeDto',
                        'dto' => 'TreeNode',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        // Self-references are excluded from dependencies by design ($dtoName !== $dto['name'])
        $analyzer->analyze($dtos);

        $this->assertTrue(true);
    }

    /**
     * Test that fields without explicit lazy key are treated as eager (default behavior).
     *
     * @return void
     */
    public function testFieldsWithoutLazyKeyTreatedAsEager(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'A' => [
                'name' => 'A',
                'fields' => [
                    'b' => [
                        'name' => 'b',
                        'type' => 'BDto',
                        'dto' => 'B',
                        // No 'lazy' key - should default to eager behavior
                    ],
                ],
            ],
            'B' => [
                'name' => 'B',
                'fields' => [
                    'a' => [
                        'name' => 'a',
                        'type' => 'ADto',
                        'dto' => 'A',
                        // No 'lazy' key - should default to eager behavior
                    ],
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $analyzer->analyze($dtos);
    }

    /**
     * Test multiple independent cycles - one broken by lazy, one not.
     *
     * @return void
     */
    public function testMultipleCyclesOneEagerThrows(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            // Cycle 1: A <-> B (both lazy - OK)
            'A' => [
                'name' => 'A',
                'fields' => [
                    'b' => [
                        'name' => 'b',
                        'type' => 'BDto',
                        'dto' => 'B',
                        'lazy' => true,
                    ],
                ],
            ],
            'B' => [
                'name' => 'B',
                'fields' => [
                    'a' => [
                        'name' => 'a',
                        'type' => 'ADto',
                        'dto' => 'A',
                        'lazy' => true,
                    ],
                ],
            ],
            // Cycle 2: C <-> D (both eager - should throw)
            'C' => [
                'name' => 'C',
                'fields' => [
                    'd' => [
                        'name' => 'd',
                        'type' => 'DDto',
                        'dto' => 'D',
                        'lazy' => false,
                    ],
                ],
            ],
            'D' => [
                'name' => 'D',
                'fields' => [
                    'c' => [
                        'name' => 'c',
                        'type' => 'CDto',
                        'dto' => 'C',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $analyzer->analyze($dtos);
    }

    /**
     * Test multiple independent cycles - all broken by lazy.
     *
     * @return void
     */
    public function testMultipleCyclesAllLazyAllowed(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            // Cycle 1: A <-> B (one lazy)
            'A' => [
                'name' => 'A',
                'fields' => [
                    'b' => [
                        'name' => 'b',
                        'type' => 'BDto',
                        'dto' => 'B',
                        'lazy' => true,
                    ],
                ],
            ],
            'B' => [
                'name' => 'B',
                'fields' => [
                    'a' => [
                        'name' => 'a',
                        'type' => 'ADto',
                        'dto' => 'A',
                        'lazy' => false,
                    ],
                ],
            ],
            // Cycle 2: C <-> D (one lazy)
            'C' => [
                'name' => 'C',
                'fields' => [
                    'd' => [
                        'name' => 'd',
                        'type' => 'DDto',
                        'dto' => 'D',
                        'lazy' => false,
                    ],
                ],
            ],
            'D' => [
                'name' => 'D',
                'fields' => [
                    'c' => [
                        'name' => 'c',
                        'type' => 'CDto',
                        'dto' => 'C',
                        'lazy' => true,
                    ],
                ],
            ],
        ];

        // Both cycles are broken by at least one lazy field
        $analyzer->analyze($dtos);

        $this->assertTrue(true);
    }

    /**
     * Test circular dependency via dto field attribute only (no type match).
     *
     * @return void
     */
    public function testCircularDependencyViaDtoFieldAttribute(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Order' => [
                'name' => 'Order',
                'fields' => [
                    'customer' => [
                        'name' => 'customer',
                        'type' => 'array', // Not a DTO type
                        'dto' => 'Customer', // But dto attribute points to Customer
                        'lazy' => false,
                    ],
                ],
            ],
            'Customer' => [
                'name' => 'Customer',
                'fields' => [
                    'orders' => [
                        'name' => 'orders',
                        'type' => 'array',
                        'dto' => 'Order',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $analyzer->analyze($dtos);
    }

    /**
     * Test lazy field via dto field attribute breaks circular dependency.
     *
     * @return void
     */
    public function testLazyDtoFieldAttributeBreaksCycle(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Order' => [
                'name' => 'Order',
                'fields' => [
                    'customer' => [
                        'name' => 'customer',
                        'type' => 'array',
                        'dto' => 'Customer',
                        'lazy' => true, // Lazy breaks cycle
                    ],
                ],
            ],
            'Customer' => [
                'name' => 'Customer',
                'fields' => [
                    'orders' => [
                        'name' => 'orders',
                        'type' => 'array',
                        'dto' => 'Order',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        // Lazy on dto attribute should break the cycle
        $analyzer->analyze($dtos);

        $this->assertTrue(true);
    }

    /**
     * Test that union types with DTOs are correctly analyzed for circular dependencies.
     *
     * Union types like 'UserDto|AdminDto' are split and each type is checked.
     *
     * @return void
     */
    public function testUnionTypeCircularDependencyDetected(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Container' => [
                'name' => 'Container',
                'fields' => [
                    'item' => [
                        'name' => 'item',
                        'type' => 'ItemADto|ItemBDto', // Union type - both types are checked
                        'lazy' => false,
                    ],
                ],
            ],
            'ItemA' => [
                'name' => 'ItemA',
                'fields' => [
                    'container' => [
                        'name' => 'container',
                        'type' => 'ContainerDto',
                        'dto' => 'Container',
                        'lazy' => false,
                    ],
                ],
            ],
            'ItemB' => [
                'name' => 'ItemB',
                'fields' => [
                    'name' => [
                        'name' => 'name',
                        'type' => 'string',
                    ],
                ],
            ],
        ];

        // Union types are now parsed - Container -> ItemA -> Container is detected as a cycle
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $analyzer->analyze($dtos);
    }

    /**
     * Test that lazy union type fields don't contribute to dependencies.
     *
     * @return void
     */
    public function testLazyUnionTypeDoesNotContributeToDependencies(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Container' => [
                'name' => 'Container',
                'fields' => [
                    'item' => [
                        'name' => 'item',
                        'type' => 'ItemADto|ItemBDto',
                        'lazy' => true, // Even if union parsing worked, lazy should skip
                    ],
                ],
            ],
            'ItemA' => [
                'name' => 'ItemA',
                'fields' => [
                    'container' => [
                        'name' => 'container',
                        'type' => 'ContainerDto',
                        'dto' => 'Container',
                        'lazy' => false,
                    ],
                ],
            ],
            'ItemB' => [
                'name' => 'ItemB',
                'fields' => [],
            ],
        ];

        // Lazy union types should be skipped entirely
        $analyzer->analyze($dtos);

        $this->assertTrue(true);
    }

    /**
     * Test that union types without circular dependencies work correctly.
     *
     * @return void
     */
    public function testUnionTypeWithoutCircularDependency(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Container' => [
                'name' => 'Container',
                'fields' => [
                    'item' => [
                        'name' => 'item',
                        'type' => 'ItemADto|ItemBDto', // Union type
                        'lazy' => false,
                    ],
                ],
            ],
            'ItemA' => [
                'name' => 'ItemA',
                'fields' => [
                    'value' => [
                        'name' => 'value',
                        'type' => 'int',
                    ],
                ],
            ],
            'ItemB' => [
                'name' => 'ItemB',
                'fields' => [
                    'name' => [
                        'name' => 'name',
                        'type' => 'string',
                    ],
                ],
            ],
        ];

        // No circular dependency - should not throw
        $analyzer->analyze($dtos);

        $this->assertTrue(true);
    }

    /**
     * Test that union types with nullable and array notations are parsed.
     *
     * @return void
     */
    public function testUnionTypeWithNullableAndArrayNotation(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Container' => [
                'name' => 'Container',
                'fields' => [
                    'items' => [
                        'name' => 'items',
                        'type' => '?ItemADto[]|ItemBDto', // Nullable array union
                        'lazy' => false,
                    ],
                ],
            ],
            'ItemA' => [
                'name' => 'ItemA',
                'fields' => [
                    'container' => [
                        'name' => 'container',
                        'type' => 'ContainerDto',
                        'dto' => 'Container',
                        'lazy' => false,
                    ],
                ],
            ],
            'ItemB' => [
                'name' => 'ItemB',
                'fields' => [],
            ],
        ];

        // Container -> ItemA -> Container is a cycle
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $analyzer->analyze($dtos);
    }

    /**
     * Test union type with mixed scalar and DTO types.
     *
     * @return void
     */
    public function testUnionTypeWithMixedScalarAndDto(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Container' => [
                'name' => 'Container',
                'fields' => [
                    'value' => [
                        'name' => 'value',
                        'type' => 'string|int|ItemDto', // Mixed union with scalars
                        'lazy' => false,
                    ],
                ],
            ],
            'Item' => [
                'name' => 'Item',
                'fields' => [
                    'container' => [
                        'name' => 'container',
                        'type' => 'ContainerDto',
                        'dto' => 'Container',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        // Container -> Item -> Container is a cycle (via ItemDto in union)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $analyzer->analyze($dtos);
    }

    /**
     * Test that union type with one lazy part still breaks the cycle.
     *
     * When the entire field is lazy, all union type parts are skipped.
     *
     * @return void
     */
    public function testUnionTypeFieldLazyBreaksCycle(): void
    {
        $analyzer = new DependencyAnalyzer();

        $dtos = [
            'Container' => [
                'name' => 'Container',
                'fields' => [
                    'item' => [
                        'name' => 'item',
                        'type' => 'ItemADto|ItemBDto',
                        'lazy' => true, // Lazy field - entire union is skipped
                    ],
                ],
            ],
            'ItemA' => [
                'name' => 'ItemA',
                'fields' => [
                    'container' => [
                        'name' => 'container',
                        'type' => 'ContainerDto',
                        'dto' => 'Container',
                        'lazy' => false,
                    ],
                ],
            ],
            'ItemB' => [
                'name' => 'ItemB',
                'fields' => [
                    'container' => [
                        'name' => 'container',
                        'type' => 'ContainerDto',
                        'dto' => 'Container',
                        'lazy' => false,
                    ],
                ],
            ],
        ];

        // Container's union field is lazy, so both cycles are broken
        $analyzer->analyze($dtos);

        $this->assertTrue(true);
    }
}
