<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Config;

/**
 * Fluent builder for complete DTO schema configuration.
 *
 * @example
 * return Schema::create()
 *     ->dto(Dto::create('User')->fields(
 *         Field::int('id')->required(),
 *         Field::string('email')->required(),
 *     ))
 *     ->dto(Dto::create('Address')->fields(
 *         Field::string('city')->required(),
 *     ))
 *     ->toArray();
 */
class SchemaBuilder
{
    /**
     * @var array<\PhpCollective\Dto\Config\DtoBuilder>
     */
    protected array $dtos = [];

    /**
     * Create a new schema builder.
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * Add a DTO to the schema.
     */
    public function dto(DtoBuilder $dto): static
    {
        $this->dtos[] = $dto;

        return $this;
    }

    /**
     * Add multiple DTOs to the schema.
     */
    public function dtos(DtoBuilder ...$dtos): static
    {
        $this->dtos = array_merge($this->dtos, $dtos);

        return $this;
    }

    /**
     * Build the complete schema configuration array.
     *
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        $config = [];

        foreach ($this->dtos as $dto) {
            $config[$dto->getName()] = $dto->toArray();
        }

        return $config;
    }
}
