<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;
use PhpCollective\Dto\Test\Generator\Fixtures\PlainClass;

/**
 * Mimics generated output for a DTO with a class-typed field and fast-path methods.
 */
class FastPathCasterDto extends AbstractDto
{
    /**
     * @var bool
     */
    protected const HAS_FAST_PATH = true;

    protected ?PlainClass $plainData = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'plainData' => [
            'type' => PlainClass::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => true,
            'enum' => null,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'plain_data' => 'plainData',
        ],
        'dashed' => [
            'plain-data' => 'plainData',
        ],
    ];

    public function getPlainData(): ?PlainClass
    {
        return $this->plainData;
    }

    public function setPlainData(?PlainClass $plainData): self
    {
        $this->plainData = $plainData;
        $this->_touchedFields['plainData'] = true;

        return $this;
    }

    public function hasPlainData(): bool
    {
        return $this->plainData !== null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return void
     */
    protected function setFromArrayFast(array $data): void
    {
        if (isset($data['plainData'])) {
            $value = $data['plainData'];
            if (!$value instanceof PlainClass) {
                $value = new PlainClass($value);
            }
            $this->plainData = $value;
            $this->_touchedFields['plainData'] = true;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArrayFast(): array
    {
        return [
            'plainData' => $this->plainData !== null ? $this->plainData->value : null,
        ];
    }

    /**
     * @param string|null $type
     * @param array<string>|null $fields
     * @param bool $touched
     *
     * @return array<string, mixed>
     */
    public function toArray(?string $type = null, ?array $fields = null, bool $touched = false): array
    {
        return $this->_toArrayInternal($type, $fields, $touched);
    }

    /**
     * @param array<string, mixed> $data
     * @param bool $ignoreMissing
     * @param string|null $type
     *
     * @return static
     */
    public static function createFromArray(array $data, bool $ignoreMissing = false, ?string $type = null): static
    {
        return static::_createFromArrayInternal($data, $ignoreMissing, $type);
    }
}
