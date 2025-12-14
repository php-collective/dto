# Testing DTOs

Best practices for unit testing and integration testing with DTOs.

## Unit Testing DTOs

### Basic Creation Tests

```php
use PHPUnit\Framework\TestCase;
use App\Dto\UserDto;

class UserDtoTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $dto = new UserDto([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertSame(1, $dto->getId());
        $this->assertSame('John Doe', $dto->getName());
        $this->assertSame('john@example.com', $dto->getEmail());
    }

    public function testCreateWithSetters(): void
    {
        $dto = new UserDto();
        $dto->setId(1);
        $dto->setName('John Doe');

        $this->assertSame(1, $dto->getId());
        $this->assertSame('John Doe', $dto->getName());
    }
}
```

### Testing Required Fields

```php
public function testRequiredFieldsThrowException(): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Required fields missing');

    new UserDto(['name' => 'John']);  // Missing required 'email'
}

public function testRequiredFieldsPass(): void
{
    $dto = new UserDto([
        'name' => 'John',
        'email' => 'john@example.com',
    ]);

    $this->assertSame('john@example.com', $dto->getEmail());
}
```

### Testing Default Values

```php
public function testDefaultValues(): void
{
    $dto = new PaginationDto();

    $this->assertSame(1, $dto->getPage());
    $this->assertSame(20, $dto->getPerPage());
}

public function testDefaultValuesCanBeOverridden(): void
{
    $dto = new PaginationDto(['page' => 5, 'perPage' => 50]);

    $this->assertSame(5, $dto->getPage());
    $this->assertSame(50, $dto->getPerPage());
}
```

### Testing toArray() Conversion

```php
public function testToArray(): void
{
    $dto = new UserDto([
        'id' => 1,
        'name' => 'John',
        'email' => 'john@example.com',
    ]);

    $array = $dto->toArray();

    $this->assertSame([
        'id' => 1,
        'name' => 'John',
        'email' => 'john@example.com',
        'phone' => null,  // Optional field
    ], $array);
}

public function testTouchedToArray(): void
{
    $dto = new UserDto();
    $dto->setName('John');

    $touched = $dto->touchedToArray();

    $this->assertSame(['name' => 'John'], $touched);
    $this->assertArrayNotHasKey('email', $touched);
}
```

### Testing Key Format Conversion

```php
public function testToArrayUnderscored(): void
{
    $dto = new UserDto([
        'firstName' => 'John',
        'lastName' => 'Doe',
    ]);

    $array = $dto->toArray(UserDto::TYPE_UNDERSCORED);

    $this->assertArrayHasKey('first_name', $array);
    $this->assertArrayHasKey('last_name', $array);
}

public function testFromArrayUnderscored(): void
{
    $dto = new UserDto();
    $dto->fromArray([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ], false, UserDto::TYPE_UNDERSCORED);

    $this->assertSame('John', $dto->getFirstName());
    $this->assertSame('Doe', $dto->getLastName());
}
```

## Testing Nested DTOs

```php
public function testNestedDto(): void
{
    $dto = new OrderDto([
        'id' => 1,
        'customer' => [
            'name' => 'John',
            'email' => 'john@example.com',
        ],
    ]);

    $this->assertInstanceOf(CustomerDto::class, $dto->getCustomer());
    $this->assertSame('John', $dto->getCustomer()->getName());
}

public function testNestedDtoToArray(): void
{
    $dto = new OrderDto([
        'id' => 1,
        'customer' => [
            'name' => 'John',
        ],
    ]);

    $array = $dto->toArray();

    $this->assertIsArray($array['customer']);
    $this->assertSame('John', $array['customer']['name']);
}
```

## Testing Collections

```php
public function testCollectionFromArray(): void
{
    $dto = new OrderDto([
        'items' => [
            ['productId' => 1, 'quantity' => 2],
            ['productId' => 2, 'quantity' => 1],
        ],
    ]);

    $this->assertCount(2, $dto->getItems());
    $this->assertSame(1, $dto->getItems()[0]->getProductId());
}

public function testAddToCollection(): void
{
    $dto = new OrderDto();
    $dto->addItem(new OrderItemDto(['productId' => 1, 'quantity' => 2]));
    $dto->addItem(new OrderItemDto(['productId' => 2, 'quantity' => 1]));

    $this->assertCount(2, $dto->getItems());
}

public function testCollectionToArray(): void
{
    $dto = new OrderDto();
    $dto->addItem(new OrderItemDto(['productId' => 1]));

    $array = $dto->toArray();

    $this->assertIsArray($array['items']);
    $this->assertCount(1, $array['items']);
    $this->assertSame(1, $array['items'][0]['productId']);
}
```

## Testing Immutable DTOs

```php
public function testImmutableWithMethod(): void
{
    $original = new ConfigDto(['timeout' => 30]);
    $modified = $original->withTimeout(60);

    // Original unchanged
    $this->assertSame(30, $original->getTimeout());
    // New instance has new value
    $this->assertSame(60, $modified->getTimeout());
    // Different instances
    $this->assertNotSame($original, $modified);
}

public function testImmutableChaining(): void
{
    $dto = new ConfigDto(['timeout' => 30])
        ->withTimeout(60)
        ->withRetries(3)
        ->withDebug(true);

    $this->assertSame(60, $dto->getTimeout());
    $this->assertSame(3, $dto->getRetries());
    $this->assertTrue($dto->getDebug());
}
```

## Testing Cloning

```php
public function testDeepClone(): void
{
    $original = new OrderDto([
        'customer' => ['name' => 'John'],
    ]);

    $clone = $original->clone();
    $clone->getCustomer()->setName('Jane');

    // Original unchanged
    $this->assertSame('John', $original->getCustomer()->getName());
    // Clone modified
    $this->assertSame('Jane', $clone->getCustomer()->getName());
}
```

## Testing Enums

```php
public function testEnumFromValue(): void
{
    $dto = new OrderDto(['status' => 'pending']);

    $this->assertSame(OrderStatus::Pending, $dto->getStatus());
}

public function testEnumFromInstance(): void
{
    $dto = new OrderDto(['status' => OrderStatus::Shipped]);

    $this->assertSame(OrderStatus::Shipped, $dto->getStatus());
}

public function testEnumToArray(): void
{
    $dto = new OrderDto(['status' => OrderStatus::Pending]);

    $array = $dto->toArray();

    $this->assertSame('pending', $array['status']);
}
```

## Testing Serialization

```php
public function testJsonSerialization(): void
{
    $dto = new UserDto(['name' => 'John', 'email' => 'john@example.com']);

    $json = $dto->serialize();
    $decoded = json_decode($json, true);

    $this->assertSame('John', $decoded['name']);
}

public function testNativeSerialization(): void
{
    $original = new UserDto(['name' => 'John']);

    $serialized = serialize($original);
    $restored = unserialize($serialized);

    $this->assertInstanceOf(UserDto::class, $restored);
    $this->assertSame('John', $restored->getName());
}

public function testFromUnserialized(): void
{
    $original = new UserDto(['name' => 'John']);
    $json = $original->serialize();

    $restored = UserDto::fromUnserialized($json);

    $this->assertSame('John', $restored->getName());
}
```

## Integration Testing

### Testing with Services

```php
class UserServiceTest extends TestCase
{
    public function testCreateUser(): void
    {
        $dto = new CreateUserDto([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $result = $this->userService->create($dto);

        $this->assertInstanceOf(UserDto::class, $result);
        $this->assertNotNull($result->getId());
    }
}
```

### Testing API Responses

```php
public function testApiResponse(): void
{
    $response = $this->get('/api/users/1');

    $response->assertStatus(200);

    $dto = new UserDto($response->json());

    $this->assertSame(1, $dto->getId());
    $this->assertNotNull($dto->getName());
}
```

### Testing Form Handling

```php
public function testFormSubmission(): void
{
    $formData = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ];

    $dto = new UserDto();
    $dto->fromArray($formData, false, UserDto::TYPE_UNDERSCORED);

    $this->assertSame('John', $dto->getFirstName());
    $this->assertSame('Doe', $dto->getLastName());
}
```

## Test Data Builders

For complex DTOs, consider a builder pattern:

```php
class UserDtoBuilder
{
    private array $data = [
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
    ];

    public static function create(): self
    {
        return new self();
    }

    public function withId(int $id): self
    {
        $this->data['id'] = $id;
        return $this;
    }

    public function withName(string $name): self
    {
        $this->data['name'] = $name;
        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->data['email'] = $email;
        return $this;
    }

    public function build(): UserDto
    {
        return new UserDto($this->data);
    }
}

// Usage in tests
$dto = UserDtoBuilder::create()
    ->withName('Custom Name')
    ->build();
```

## Collection Factory in Tests

Reset the collection factory between tests to avoid state pollution:

```php
protected function tearDown(): void
{
    parent::tearDown();
    Dto::setCollectionFactory(null);
    Dto::setDefaultKeyType(null);
}

public function testWithLaravelCollection(): void
{
    Dto::setCollectionFactory(fn($items) => collect($items));

    $dto = new OrderDto([
        'items' => [['productId' => 1], ['productId' => 2]],
    ]);

    $filtered = $dto->getItems()->filter(fn($item) => $item->getProductId() === 1);

    $this->assertCount(1, $filtered);
}
```

## Data Providers

Use data providers for testing multiple scenarios:

```php
/**
 * @dataProvider validUserDataProvider
 */
public function testValidUserData(array $data, string $expectedName): void
{
    $dto = new UserDto($data);

    $this->assertSame($expectedName, $dto->getName());
}

public static function validUserDataProvider(): array
{
    return [
        'simple' => [
            ['name' => 'John', 'email' => 'john@example.com'],
            'John',
        ],
        'with spaces' => [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            'John Doe',
        ],
        'unicode' => [
            ['name' => 'Jöhn Döe', 'email' => 'john@example.com'],
            'Jöhn Döe',
        ],
    ];
}

/**
 * @dataProvider invalidUserDataProvider
 */
public function testInvalidUserData(array $data, string $expectedError): void
{
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage($expectedError);

    new UserDto($data);
}

public static function invalidUserDataProvider(): array
{
    return [
        'missing email' => [
            ['name' => 'John'],
            'Required fields missing',
        ],
        'unknown field' => [
            ['name' => 'John', 'email' => 'j@e.com', 'unknown' => 'x'],
            'Missing field',
        ],
    ];
}
```

## Tips

1. **Test the edges**: Empty collections, null values, maximum lengths
2. **Test roundtrips**: `toArray()` → `fromArray()` → `toArray()` should be idempotent
3. **Test immutability**: Verify originals remain unchanged
4. **Test key formats**: If you use multiple formats, test conversions
5. **Reset global state**: Clear collection factory and default key type in tearDown
