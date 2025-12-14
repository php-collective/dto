# Examples

Practical examples demonstrating common DTO patterns and use cases.

## Mutable DTOs

Mutable DTOs allow direct modification. Changes affect all references to the same object.

```php
use App\Dto\CarDto;
use App\Dto\OwnerDto;

// Create and configure a mutable DTO
$ownerDto = new OwnerDto();
$ownerDto->setName('The Owner');

$carDto = new CarDto();
$carDto->setOwner($ownerDto);

// References share the same object
$otherCarDto = $carDto;
$otherCarDto->getOwner()->setName('The new owner');

// Both references see the change
assert($carDto->getOwner()->getName() === 'The new owner');
```

This is the default behavior. If you need isolated copies, use immutable DTOs or explicit cloning.

## Immutable DTOs

Immutable DTOs create new instances for each modification, preserving the original.

```xml
<dto name="Article" immutable="true">
    <field name="id" type="int" required="true"/>
    <field name="title" type="string" required="true"/>
    <field name="author" type="Author"/>
    <field name="created" type="\DateTimeImmutable"/>
</dto>
```

```php
use App\Dto\ArticleDto;

$array = [
    'id' => 2,
    'author' => ['id' => 1, 'name' => 'me'],
    'title' => 'My title',
    'created' => new DateTimeImmutable('-1 day'),
];

$articleDto = new ArticleDto($array);
$modifiedArticleDto = $articleDto->withTitle('My new title');

// Original remains unchanged
assert($articleDto->getTitle() === 'My title');
assert($modifiedArticleDto->getTitle() === 'My new title');
```

## Data Conversion Patterns

### Entity to DTO

Convert database entities or models to DTOs for safe transport across layers:

```php
// From any ORM/database layer
$userData = $repository->findById(123);

// Convert to DTO (array or object with toArray())
$userDto = UserDto::createFromArray($userData->toArray());

// Or pass array directly
$userDto = UserDto::createFromArray([
    'id' => $userData->id,
    'email' => $userData->email,
    'name' => $userData->name,
]);
```

### DTO to Entity

Convert DTOs back for persistence:

```php
// Get only the modified fields
$changes = $userDto->touchedToArray();

// Update entity with changes
$entity->fill($changes);
$repository->save($entity);
```

### Working with Relations

```xml
<dto name="Order">
    <field name="id" type="int" required="true"/>
    <field name="customer" type="Customer"/>
    <field name="items" type="OrderItem[]" collection="true" singular="item"/>
    <field name="createdAt" type="\DateTimeImmutable"/>
</dto>
```

```php
// Convert order with relations
$orderData = $orderRepository->findWithRelations($orderId);

// Deep conversion happens automatically
$orderDto = OrderDto::createFromArray([
    'id' => $orderData['id'],
    'customer' => $orderData['customer'], // Converted to CustomerDto
    'items' => $orderData['items'],       // Converted to OrderItemDto[]
    'createdAt' => $orderData['created_at'],
]);

// Access nested data with type safety
$customerName = $orderDto->getCustomer()->getName();

foreach ($orderDto->getItems() as $item) {
    echo $item->getProduct()->getName();
}
```

## Key Format Handling

### From Snake Case (Database/Forms)

```php
$formData = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email_address' => 'john@example.com',
];

$dto = new UserDto();
$dto->fromArray($formData, false, UserDto::TYPE_UNDERSCORED);

// Access with camelCase getters
echo $dto->getFirstName();     // "John"
echo $dto->getEmailAddress();  // "john@example.com"
```

### From Dash Case (URLs/APIs)

```php
$queryParams = [
    'sort-by' => 'created_at',
    'sort-order' => 'desc',
    'page-size' => 25,
];

$dto = new PaginationDto();
$dto->fromArray($queryParams, false, PaginationDto::TYPE_DASHED);

echo $dto->getSortBy();    // "created_at"
echo $dto->getPageSize();  // 25
```

### Export to Different Formats

```php
// Default camelCase
$camelCase = $dto->toArray();
// ['firstName' => 'John', 'lastName' => 'Doe']

// Snake case for database
$snakeCase = $dto->toArray(UserDto::TYPE_UNDERSCORED);
// ['first_name' => 'John', 'last_name' => 'Doe']

// Dash case for URLs
$dashCase = $dto->toArray(UserDto::TYPE_DASHED);
// ['first-name' => 'John', 'last-name' => 'Doe']
```

## Collections

### Simple Collections

```xml
<dto name="Cart">
    <field name="items" type="CartItem[]" collection="true" singular="item"/>
</dto>
```

```php
$cart = new CartDto();

// Add items one by one
$cart->addItem(new CartItemDto(['productId' => 1, 'quantity' => 2]));
$cart->addItem(new CartItemDto(['productId' => 5, 'quantity' => 1]));

// Check collection
echo count($cart->getItems());  // 2

// Iterate
foreach ($cart->getItems() as $item) {
    echo $item->getProductId() . ': ' . $item->getQuantity();
}
```

### Associative Collections

```xml
<dto name="Config">
    <field name="settings" type="Setting[]" collection="true"
           singular="setting" associative="true"/>
</dto>
```

```php
$config = new ConfigDto();

// Add with keys
$config->addSetting('theme', new SettingDto(['value' => 'dark']));
$config->addSetting('language', new SettingDto(['value' => 'en']));

// Access by key
$theme = $config->getSetting('theme');
echo $theme->getValue();  // "dark"

// Check existence
if ($config->hasSetting('notifications')) {
    // ...
}
```

## Nested Reading

Safe access to deeply nested values:

```php
$orderDto = OrderDto::createFromArray([
    'customer' => [
        'address' => [
            'city' => 'New York',
            'country' => 'USA',
        ],
    ],
]);

// Safe nested access
$city = $orderDto->read(['customer', 'address', 'city']);
// "New York"

// With default for missing values
$state = $orderDto->read(['customer', 'address', 'state'], 'Unknown');
// "Unknown"

// Returns null for missing paths (without default)
$zipCode = $orderDto->read(['customer', 'address', 'zipCode']);
// null
```

## Deep Cloning

Create independent copies of nested structures:

```php
$original = new OrderDto();
$original->setCustomer(new CustomerDto(['name' => 'John']));

// Deep clone
$clone = $original->clone();
$clone->getCustomer()->setName('Jane');

// Original is unchanged
assert($original->getCustomer()->getName() === 'John');
assert($clone->getCustomer()->getName() === 'Jane');
```

## Required Fields

Ensure critical data is always present:

```xml
<dto name="User">
    <field name="id" type="int" required="true"/>
    <field name="email" type="string" required="true"/>
    <field name="name" type="string"/>
</dto>
```

```php
// This throws an exception - required fields missing
$user = new UserDto(['name' => 'John']);
// RuntimeException: Required field 'id' is missing

// Proper initialization
$user = new UserDto([
    'id' => 1,
    'email' => 'john@example.com',
    'name' => 'John',
]);
```

## OrFail Methods

Get values with guaranteed non-null returns:

```php
// Standard getter - may return null
$email = $userDto->getEmail();  // string|null

// OrFail getter - throws if null
$email = $userDto->getEmailOrFail();  // string (throws if not set)
```

Useful when you know a value must exist:

```php
// After validation, we know email exists
$validatedDto = $this->validateUser($inputDto);
$email = $validatedDto->getEmailOrFail();  // Safe - validation ensures it exists
```

## Default Values

Provide sensible defaults:

```xml
<dto name="Pagination">
    <field name="page" type="int" defaultValue="1"/>
    <field name="perPage" type="int" defaultValue="20"/>
    <field name="sortOrder" type="string" defaultValue="asc"/>
</dto>
```

```php
$pagination = new PaginationDto();

echo $pagination->getPage();      // 1
echo $pagination->getPerPage();   // 20
echo $pagination->getSortOrder(); // "asc"

// Override defaults as needed
$pagination->setPage(5);
```

## Custom Collection Types

By default, DTO collections return `ArrayObject`. You can customize this globally to use your framework's collection class, gaining access to powerful collection methods like `filter()`, `map()`, `reduce()`, etc.

### Why Use Custom Collections?

`ArrayObject` is functional but limited. Framework collections provide:
- Fluent, chainable operations (`->filter()->map()->sum()`)
- Lazy evaluation (in some implementations)
- Framework-specific integrations (e.g., Laravel's `pluck()`, CakePHP's `groupBy()`)

### Setting a Global Factory

Set the factory early in your application bootstrap, before any DTOs are instantiated:

```php
use PhpCollective\Dto\Dto\Dto;

// CakePHP Collection
Dto::setCollectionFactory(fn(array $items) => new \Cake\Collection\Collection($items));

// Laravel Collection
Dto::setCollectionFactory(fn(array $items) => collect($items));

// Doctrine ArrayCollection
Dto::setCollectionFactory(fn(array $items) => new \Doctrine\Common\Collections\ArrayCollection($items));
```

### Using Framework Collection Methods

Once set, all DTO collections gain the framework's collection methods:

```php
// With Laravel collection factory
Dto::setCollectionFactory(fn($items) => collect($items));

$cart = new CartDto();
// ... add items

// Use Laravel collection methods
$total = $cart->getItems()
    ->filter(fn($item) => $item->getQuantity() > 0)
    ->sum(fn($item) => $item->getPrice() * $item->getQuantity());

$productNames = $cart->getItems()
    ->pluck('name')
    ->unique()
    ->values();
```

### Resetting to Default

```php
// Reset to default ArrayObject
Dto::setCollectionFactory(null);
```

### When to Use

- **API applications**: Laravel/Symfony collections for response transformation
- **Domain logic**: Filter, aggregate, transform collections fluently
- **Testing**: Reset factory between tests to avoid state pollution

## API Response Pattern

```php
class UserController
{
    public function show(int $id): array
    {
        $user = $this->userRepository->find($id);

        $dto = UserDto::createFromArray([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'createdAt' => $user->created_at,
        ]);

        // Return as snake_case for JSON API
        return $dto->toArray(UserDto::TYPE_UNDERSCORED);
    }
}
```

## Form Handling Pattern

```php
public function update(Request $request, int $id): Response
{
    // Convert form input to DTO
    $dto = new UserDto();
    $dto->fromArray($request->all(), false, UserDto::TYPE_UNDERSCORED);

    // Validate and process
    $this->validator->validate($dto);

    // Get only changed fields for partial update
    $changes = $dto->touchedToArray();

    $this->userRepository->update($id, $changes);

    return new Response('Updated');
}
```

## Value Object Integration

DTOs work seamlessly with value objects:

```xml
<dto name="Product">
    <field name="id" type="int"/>
    <field name="name" type="string"/>
    <field name="price" type="\Money\Money"/>
    <field name="createdAt" type="\DateTimeImmutable"/>
</dto>
```

```php
use Money\Money;
use Money\Currency;

$product = new ProductDto([
    'id' => 1,
    'name' => 'Widget',
    'price' => Money::of(1999, new Currency('USD')),
    'createdAt' => new DateTimeImmutable(),
]);

$price = $product->getPrice();
echo $price->getAmount();  // 1999
```

## Enum Support

```xml
<dto name="Order">
    <field name="status" type="\App\Enum\OrderStatus"/>
</dto>
```

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
}

$order = new OrderDto([
    'status' => OrderStatus::Pending,
]);

// Or from string value
$order = new OrderDto([
    'status' => 'confirmed',  // Automatically converted to enum
]);

$status = $order->getStatus();  // OrderStatus::Confirmed
```

## Deprecation Handling

Mark fields as deprecated for gradual migration:

```xml
<dto name="User">
    <field name="username" type="string" deprecated="Use email instead"/>
    <field name="email" type="string"/>
</dto>
```

Your IDE will show deprecation warnings when using `getUsername()` or `setUsername()`.
