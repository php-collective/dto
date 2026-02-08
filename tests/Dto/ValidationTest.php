<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Dto;

use InvalidArgumentException;
use PhpCollective\Dto\Test\TestDto\ValidatedDto;
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public function testValidData(): void
    {
        $dto = new ValidatedDto(['name' => 'Hello']);
        $this->assertSame('Hello', $dto->getName());
    }

    public function testMinLengthFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name must be at least 2 characters');

        new ValidatedDto(['name' => 'A']);
    }

    public function testMaxLengthFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name must be at most 50 characters');

        new ValidatedDto(['name' => str_repeat('x', 51)]);
    }

    public function testMinFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('age must be at least 0');

        new ValidatedDto(['name' => 'Test', 'age' => -1]);
    }

    public function testMaxFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('age must be at most 150');

        new ValidatedDto(['name' => 'Test', 'age' => 200]);
    }

    public function testMinFloat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('score must be at least 0');

        new ValidatedDto(['name' => 'Test', 'score' => -0.1]);
    }

    public function testMaxFloat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('score must be at most 100');

        new ValidatedDto(['name' => 'Test', 'score' => 100.1]);
    }

    public function testPatternFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('email must match pattern');

        new ValidatedDto(['name' => 'Test', 'email' => 'not-an-email']);
    }

    public function testPatternPasses(): void
    {
        $dto = new ValidatedDto(['name' => 'Test', 'email' => 'user@example.com']);
        $this->assertSame('user@example.com', $dto->getEmail());
    }

    public function testNullFieldsSkipValidation(): void
    {
        $dto = new ValidatedDto(['name' => 'Test']);
        $this->assertNull($dto->getEmail());
        $this->assertNull($dto->getAge());
        $this->assertNull($dto->getScore());
    }

    public function testAllValidFields(): void
    {
        $dto = new ValidatedDto([
            'name' => 'Test',
            'email' => 'a@b.com',
            'age' => 25,
            'score' => 99.5,
        ]);
        $this->assertSame('Test', $dto->getName());
        $this->assertSame('a@b.com', $dto->getEmail());
        $this->assertSame(25, $dto->getAge());
        $this->assertSame(99.5, $dto->getScore());
    }

    public function testBoundaryValues(): void
    {
        $dto = new ValidatedDto([
            'name' => 'AB',
            'age' => 0,
            'score' => 0.0,
        ]);
        $this->assertSame('AB', $dto->getName());
        $this->assertSame(0, $dto->getAge());
        $this->assertSame(0.0, $dto->getScore());

        $dto2 = new ValidatedDto([
            'name' => str_repeat('x', 50),
            'age' => 150,
            'score' => 100.0,
        ]);
        $this->assertSame(150, $dto2->getAge());
        $this->assertSame(100.0, $dto2->getScore());
    }

    public function testRequiredFieldStillWorks(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required fields missing: name');

        new ValidatedDto([]);
    }

    public function testToArray(): void
    {
        $dto = new ValidatedDto(['name' => 'Test', 'age' => 25]);
        $result = $dto->toArray();

        $this->assertSame('Test', $result['name']);
        $this->assertSame(25, $result['age']);
        $this->assertNull($result['email']);
        $this->assertNull($result['score']);
    }
}
