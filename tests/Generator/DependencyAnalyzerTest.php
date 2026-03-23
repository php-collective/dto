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
}
