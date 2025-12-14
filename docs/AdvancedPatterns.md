# Advanced Patterns

This document covers advanced use cases and patterns for php-collective/dto.

## Custom Collection Factories

By default, collections use `ArrayObject`. You can customize this globally to use your framework's collection class.

### Setting a Global Factory

```php
use PhpCollective\Dto\Dto\Dto;

// CakePHP Collection
Dto::setCollectionFactory(fn(array $items) => new \Cake\Collection\Collection($items));

// Laravel Collection
Dto::setCollectionFactory(fn(array $items) => collect($items));

// Doctrine ArrayCollection
Dto::setCollectionFactory(fn(array $items) => new \Doctrine\Common\Collections\ArrayCollection($items));
```

**Important:** Set the factory early in your application bootstrap, before any DTOs are instantiated.

### Using Framework Collection Methods

Once set, all DTO collections gain the framework's collection methods:

```php
// With Laravel collection factory
Dto::setCollectionFactory(fn($items) => collect($items));

$order = new OrderDto($data);

// Now you can use Laravel collection methods
$total = $order->getItems()
    ->filter(fn($item) => $item->getQuantity() > 0)
    ->sum(fn($item) => $item->getPrice() * $item->getQuantity());

$productNames = $order->getItems()
    ->pluck('name')
    ->unique()
    ->values();
```

### Resetting to Default

```php
// Reset to default ArrayObject
Dto::setCollectionFactory(null);
```

## DTO Inheritance

### Basic Inheritance

```php
// config/dto.php
return Schema::create()
    ->dto(Dto::create('BaseEntity')->fields(
        Field::int('id')->required(),
        Field::class('createdAt', \DateTimeImmutable::class),
        Field::class('updatedAt', \DateTimeImmutable::class),
    ))
    ->dto(Dto::create('User')->extends('BaseEntity')->fields(
        Field::string('email')->required(),
        Field::string('name'),
    ))
    ->dto(Dto::create('Post')->extends('BaseEntity')->fields(
        Field::string('title')->required(),
        Field::string('content'),
        Field::dto('author', 'User'),
    ))
    ->toArray();
```

Generated UserDto will have: `id`, `createdAt`, `updatedAt`, `email`, `name`

### Immutable Inheritance

Immutable DTOs can extend other immutable DTOs:

```php
Dto::immutable('BaseEvent')->fields(
    Field::string('eventId')->required(),
    Field::class('occurredAt', \DateTimeImmutable::class)->required(),
)

Dto::immutable('UserCreatedEvent')->extends('BaseEvent')->fields(
    Field::int('userId')->required(),
    Field::string('email')->required(),
)
```

**Note:** An immutable DTO cannot extend a mutable DTO and vice versa.

## Associative Collections

### Basic Associative Collection

```php
Dto::create('Config')->fields(
    Field::collection('settings', 'Setting')
        ->singular('setting')
        ->associative(),
)
```

```php
$config = new ConfigDto();

// Add with string keys
$config->addSetting('theme', new SettingDto(['value' => 'dark']));
$config->addSetting('language', new SettingDto(['value' => 'en']));

// Access by key
$theme = $config->getSetting('theme');

// Check existence
if ($config->hasSetting('notifications')) {
    // ...
}

// Remove by key
$config->removeSetting('theme');
```

### Associative with Custom Key Field

When your DTO has a natural key field:

```php
// Setting DTO with 'key' field
Dto::create('Setting')->fields(
    Field::string('key')->required(),
    Field::string('value')->required(),
)

// Config with associative collection using 'key' as index
Dto::create('Config')->fields(
    Field::collection('settings', 'Setting')
        ->singular('setting')
        ->associative('key'),  // Use 'key' field as collection index
)
```

```php
$config = new ConfigDto([
    'settings' => [
        ['key' => 'theme', 'value' => 'dark'],
        ['key' => 'lang', 'value' => 'en'],
    ],
]);

// Access by the 'key' field value
$theme = $config->getSetting('theme');
echo $theme->getValue(); // 'dark'
```

## Factory Methods for Complex Instantiation

### Static Factory Method

For classes that use named constructors:

```php
Dto::create('Event')->fields(
    Field::class('date', \DateTimeImmutable::class)
        ->factory('createFromFormat'),
)
```

The generator will call `DateTimeImmutable::createFromFormat()` when hydrating from array.

### Custom Parser Factory

```php
// For a Money class with custom parsing
Dto::create('Product')->fields(
    Field::class('price', \Money\Money::class)
        ->factory('Money\MoneyParser::parse'),
)
```

### Factory with Interface

```php
// The DTO field uses the interface
Field::class('logger', \Psr\Log\LoggerInterface::class)
    ->factory('App\Factory\LoggerFactory::create')
```

## Nested DTO Patterns

### Deep Nesting

```php
Dto::create('Company')->fields(
    Field::string('name')->required(),
    Field::collection('departments', 'Department')->singular('department'),
)

Dto::create('Department')->fields(
    Field::string('name')->required(),
    Field::collection('teams', 'Team')->singular('team'),
)

Dto::create('Team')->fields(
    Field::string('name')->required(),
    Field::collection('members', 'Employee')->singular('member'),
)

Dto::create('Employee')->fields(
    Field::string('name')->required(),
    Field::string('email')->required(),
)
```

Access deeply nested data:

```php
$company = new CompanyDto($data);

// Navigate the tree
foreach ($company->getDepartments() as $dept) {
    foreach ($dept->getTeams() as $team) {
        foreach ($team->getMembers() as $employee) {
            echo $employee->getEmail();
        }
    }
}

// Or use the read() helper for safe access
$email = $company->read(['departments', 0, 'teams', 0, 'members', 0, 'email']);
```

### Self-Referencing DTOs

For tree structures:

```php
Dto::create('Category')->fields(
    Field::int('id')->required(),
    Field::string('name')->required(),
    Field::dto('parent', 'Category'),  // Nullable reference to self
    Field::collection('children', 'Category')->singular('child'),
)
```

```php
$category = new CategoryDto([
    'id' => 1,
    'name' => 'Electronics',
    'children' => [
        ['id' => 2, 'name' => 'Phones', 'children' => []],
        ['id' => 3, 'name' => 'Laptops', 'children' => []],
    ],
]);
```

## Partial Updates with touchedToArray()

Track which fields were explicitly set:

```php
$user = new UserDto();
$user->setEmail('new@example.com');
// Only email was "touched"

$changes = $user->touchedToArray();
// ['email' => 'new@example.com']

// Use for partial database updates
$this->repository->update($userId, $changes);
```

### Form Handling Pattern

```php
public function updateProfile(Request $request, int $userId): Response
{
    // Load existing data
    $existing = $this->userRepository->find($userId);

    // Create DTO and apply only submitted fields
    $dto = new UserDto();

    if ($request->has('email')) {
        $dto->setEmail($request->input('email'));
    }
    if ($request->has('name')) {
        $dto->setName($request->input('name'));
    }

    // Get only the fields that were actually set
    $changes = $dto->touchedToArray();

    if (empty($changes)) {
        return new Response('No changes');
    }

    $this->userRepository->update($userId, $changes);

    return new Response('Updated');
}
```

## Immutable Event Sourcing Pattern

```php
// Base event
Dto::immutable('DomainEvent')->fields(
    Field::string('eventId')->required(),
    Field::string('aggregateId')->required(),
    Field::class('occurredAt', \DateTimeImmutable::class)->required(),
    Field::int('version')->required(),
)

// Specific events
Dto::immutable('OrderPlaced')->extends('DomainEvent')->fields(
    Field::dto('order', 'Order')->required(),
    Field::string('customerId')->required(),
)

Dto::immutable('OrderShipped')->extends('DomainEvent')->fields(
    Field::string('trackingNumber')->required(),
    Field::string('carrier')->required(),
)
```

```php
// Events are immutable - create new instances for modifications
$event = new OrderPlacedDto([
    'eventId' => Uuid::uuid4()->toString(),
    'aggregateId' => $orderId,
    'occurredAt' => new DateTimeImmutable(),
    'version' => 1,
    'order' => $orderDto,
    'customerId' => $customerId,
]);

// To "modify", create new event with changes
$correctedEvent = $event->withVersion(2);
```

## API Response Transformation

### Different Output Formats

```php
$userDto = new UserDto($data);

// Default camelCase for JavaScript frontend
$jsonResponse = $userDto->toArray();
// ['firstName' => 'John', 'lastName' => 'Doe']

// Snake case for Python/Ruby APIs
$snakeResponse = $userDto->toArray(UserDto::TYPE_UNDERSCORED);
// ['first_name' => 'John', 'last_name' => 'Doe']

// Dash case for URL parameters
$dashResponse = $userDto->toArray(UserDto::TYPE_DASHED);
// ['first-name' => 'John', 'last-name' => 'Doe']
```

### Input Normalization

```php
// Accept any format from external APIs
public function importUser(array $externalData, string $format): UserDto
{
    $dto = new UserDto();

    $type = match ($format) {
        'snake' => UserDto::TYPE_UNDERSCORED,
        'dash' => UserDto::TYPE_DASHED,
        default => UserDto::TYPE_DEFAULT,
    };

    $dto->fromArray($externalData, false, $type);

    return $dto;
}
```

## Conditional Field Inclusion

Using the fields() method to check what's set:

```php
$user = new UserDto($data);

// Get list of all fields
$allFields = $user->fields();
// ['id', 'name', 'email', 'phone', 'address', ...]

// Build response with only non-null fields
$response = [];
foreach ($user->fields() as $field) {
    $getter = 'get' . ucfirst($field);
    $value = $user->$getter();
    if ($value !== null) {
        $response[$field] = $value;
    }
}
```

## Cloning and Isolation

### Deep Clone

```php
$original = new OrderDto($data);

// Deep clone - all nested DTOs are also cloned
$clone = $original->clone();

// Modify clone without affecting original
$clone->getCustomer()->setEmail('different@example.com');

// Original is unchanged
assert($original->getCustomer()->getEmail() !== 'different@example.com');
```

### Comparison

```php
$dto1 = new UserDto(['id' => 1, 'name' => 'John']);
$dto2 = new UserDto(['id' => 1, 'name' => 'John']);

// Compare by value using toArray()
$areEqual = $dto1->toArray() === $dto2->toArray(); // true
```
