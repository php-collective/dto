<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Generator\ArrayShapeBuilder;
use PhpCollective\Dto\Generator\FieldCompletor;
use PhpCollective\Dto\Generator\FieldKey;
use PhpCollective\Dto\Generator\TypeResolver;
use PhpCollective\Dto\Generator\TypeValidator;
use PHPUnit\Framework\TestCase;

class FieldCompletorTest extends TestCase
{
    protected FieldCompletor $completor;

    protected function setUp(): void
    {
        $typeValidator = new TypeValidator();
        $typeResolver = new TypeResolver($typeValidator, true);
        $arrayShapeBuilder = new ArrayShapeBuilder('Dto');

        $this->completor = new FieldCompletor(
            $typeValidator,
            $typeResolver,
            $arrayShapeBuilder,
            [
                'defaultCollectionType' => '\ArrayObject',
                'suffix' => 'Dto',
                'scalarAndReturnTypes' => true,
            ],
        );
    }

    public function testInterfaceWithoutFactoryWarning(): void
    {
        $dto = [
            FieldKey::NAME => 'Test',
            FieldKey::FIELDS => [
                'date' => [
                    'name' => 'date',
                    'type' => '\DateTimeInterface',
                ],
            ],
        ];

        $result = $this->completor->complete($dto, 'App');
        $warnings = $this->completor->getWarnings();

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('interface type', $warnings[0]);
        $this->assertStringContainsString('DateTimeInterface', $warnings[0]);
        $this->assertStringContainsString('factory', $warnings[0]);
    }

    public function testInterfaceWithFactoryNoWarning(): void
    {
        $dto = [
            FieldKey::NAME => 'Test',
            FieldKey::FIELDS => [
                'date' => [
                    'name' => 'date',
                    'type' => '\DateTimeInterface',
                    'factory' => '\DateTime',
                ],
            ],
        ];

        $result = $this->completor->complete($dto, 'App');
        $warnings = $this->completor->getWarnings();

        $this->assertCount(0, $warnings);
    }

    public function testConcreteClassNoWarning(): void
    {
        $dto = [
            FieldKey::NAME => 'Test',
            FieldKey::FIELDS => [
                'date' => [
                    'name' => 'date',
                    'type' => '\DateTime',
                ],
            ],
        ];

        $result = $this->completor->complete($dto, 'App');
        $warnings = $this->completor->getWarnings();

        $this->assertCount(0, $warnings);
    }

    public function testClearWarnings(): void
    {
        $dto = [
            FieldKey::NAME => 'Test',
            FieldKey::FIELDS => [
                'date' => [
                    'name' => 'date',
                    'type' => '\DateTimeInterface',
                ],
            ],
        ];

        $this->completor->complete($dto, 'App');
        $this->assertCount(1, $this->completor->getWarnings());

        $this->completor->clearWarnings();
        $this->assertCount(0, $this->completor->getWarnings());
    }

    public function testEnumInterfaceNoWarning(): void
    {
        // Enums implement BackedEnum/UnitEnum interfaces but are handled specially
        $dto = [
            FieldKey::NAME => 'Test',
            FieldKey::FIELDS => [
                'status' => [
                    'name' => 'status',
                    'type' => '\PhpCollective\Dto\Test\Generator\Fixtures\IntBackedEnum',
                ],
            ],
        ];

        $result = $this->completor->complete($dto, 'App');
        $warnings = $this->completor->getWarnings();

        // Enums should not trigger warnings - they're handled separately
        $this->assertCount(0, $warnings);
    }
}
