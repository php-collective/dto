# Framework Integration

This library is **framework-agnostic** by design. It works with any PHP framework without requiring wrapper packages, while still offering deep integration possibilities.

Do We Need Wrapper Libraries?

| Feature            | Without Wrapper    | With Wrapper                |
  |--------------------|--------------------|-----------------------------|
| Basic usage        | ✅ Works           | ✅ Works                    |
| Collection factory | 1 line setup       | Auto-configured             |
| Code generation    | bin/dto generate   | artisan dto:generate        |
| Request → DTO      | Manual             | Auto via injection          |
| Validation         | Manual integration | Native framework validators |
| IDE support        | ✅ Full            | ✅ Full                     |

## Quick Setup

### Laravel

```php
// app/Providers/AppServiceProvider.php
use PhpCollective\Dto\Dto\Dto;

public function boot(): void
{
    // Enable Laravel collections for DTO collection fields
    Dto::setCollectionFactory(fn (array $items) => collect($items));
}
```

### Symfony

```php
// src/Kernel.php or an event listener
use PhpCollective\Dto\Dto\Dto;

// In boot or kernel.request listener
Dto::setCollectionFactory(fn (array $items) => new ArrayCollection($items));
```

### CakePHP

```php
// config/bootstrap.php or Application.php
use PhpCollective\Dto\Dto\Dto;
use Cake\Collection\Collection;

Dto::setCollectionFactory(fn (array $items) => new Collection($items));
```

### CakePHP projectAs() and Special Fields

CakePHP 5.3+ introduces `projectAs()` for projecting ORM results directly into DTOs. Some CakePHP features use underscore-prefixed field names:

- `_joinData` - BelongsToMany pivot table data
- `_matchingData` - Data from `matching()` queries

These field names are fully supported:

```xml
<!-- config/dto.xml -->
<dto name="Tag" immutable="true">
    <field name="id" type="int"/>
    <field name="name" type="string"/>
    <field name="_joinData" type="Tagged"/>
</dto>

<dto name="UserWithMatching" immutable="true">
    <field name="id" type="int"/>
    <field name="username" type="string"/>
    <field name="_matchingData" type="MatchingData"/>
</dto>
```

The generated methods use proper camelCase (the leading underscore is stripped for method/constant names):

```php
// Field: _joinData
$tag->getJoinData();      // getter
$tag->withJoinData($data); // immutable setter
TagDto::FIELD_JOIN_DATA;   // constant

// Field: _matchingData
$user->getMatchingData();
$user->withMatchingData($data);
UserWithMatchingDto::FIELD_MATCHING_DATA;
```

Usage with CakePHP's projectAs():

```php
// BelongsToMany with _joinData
$posts = $postsTable->find()
    ->contain(['Tags'])
    ->projectAs(PostDto::class)
    ->toArray();

foreach ($posts as $post) {
    foreach ($post->getTags() as $tag) {
        $pivotData = $tag->getJoinData(); // Access pivot table data
    }
}

// Matching query with _matchingData
$users = $usersTable->find()
    ->matching('Roles', fn ($q) => $q->where(['Roles.id' => 1]))
    ->projectAs(UserWithMatchingDto::class)
    ->toArray();

foreach ($users as $user) {
    $matchingRoles = $user->getMatchingData(); // Access matched association data
}
```

## Controller Usage

### Laravel

```php
use App\Dto\UserDto;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // From validated request
        $dto = new UserDto($request->validated());

        // Or with ignoreMissing for partial updates
        $dto = UserDto::createFromArray($request->all(), ignoreMissing: true);

        // Use Laravel collection methods on DTO collections
        $activeUsers = $dto->getRoles()->filter(fn ($role) => $role->isActive());

        return response()->json($dto->toArray());
    }

    public function show(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $dto = new UserDto($user->toArray());

        return response()->json($dto);
    }
}
```

### Symfony

```php
use App\Dto\UserDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends AbstractController
{
    #[Route('/users', methods: ['POST'])]
    public function store(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $dto = new UserDto($data);

        return $this->json($dto->toArray());
    }
}
```

### CakePHP

```php
use App\Dto\UserDto;

class UsersController extends AppController
{
    public function add()
    {
        if ($this->request->is('post')) {
            $dto = new UserDto($this->request->getData());

            // Use CakePHP collection methods
            $items = $dto->getItems()->filter(fn ($item) => $item->getQuantity() > 0);

            // ... save logic
        }
    }
}
```

## Validation

The library doesn't include validation - use your framework's validator.

### Laravel

```php
use Illuminate\Support\Facades\Validator;

$dto = new UserDto($request->all(), ignoreMissing: true);

$validator = Validator::make($dto->toArray(), [
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users',
]);

if ($validator->fails()) {
    return response()->json(['errors' => $validator->errors()], 422);
}
```

### Symfony

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{
    public function store(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new UserDto(json_decode($request->getContent(), true));

        // Validate the DTO array representation
        $violations = $validator->validate($dto->toArray(), new Assert\Collection([
            'name' => [new Assert\NotBlank(), new Assert\Length(['max' => 255])],
            'email' => [new Assert\NotBlank(), new Assert\Email()],
        ]));

        if (count($violations) > 0) {
            return $this->json(['errors' => (string) $violations], 422);
        }

        return $this->json($dto->toArray());
    }
}
```

See [Validation.md](Validation.md) for more validation patterns.

## Code Generation

The CLI tool works the same way in any framework:

```bash
# Generate DTOs from config
bin/dto generate config/dto.php src/Dto App\\Dto

# Or with XML
bin/dto generate config/dto.xml src/Dto App\\Dto
```

### Framework-Specific Paths

| Framework | Config Path | Output Path | Namespace |
|-----------|-------------|-------------|-----------|
| Laravel | `config/dto.php` | `app/Dto` | `App\Dto` |
| Symfony | `config/dto.xml` | `src/Dto` | `App\Dto` |
| CakePHP | `config/dto.php` | `src/Dto` | `App\Dto` |

### Composer Scripts

Add to your `composer.json` for convenience:

```json
{
    "scripts": {
        "dto:generate": "bin/dto generate config/dto.php src/Dto App\\Dto"
    }
}
```

Then run: `composer dto:generate`

## What Wrapper Packages Could Add

While not required, framework-specific packages could provide:

### Potential Laravel Package Features

| Feature | Current (Manual) | With Package |
|---------|------------------|--------------|
| Collection factory | 1 line in ServiceProvider | Auto-configured |
| Request → DTO | `new UserDto($request->validated())` | `UserDto::fromRequest($request)` |
| Route binding | Manual | `Route::dto(UserDto::class)` |
| Artisan command | `bin/dto generate` | `php artisan dto:generate` |
| Config publishing | Manual | `php artisan vendor:publish` |

```php
// Hypothetical Laravel package usage
public function store(UserDto $dto): JsonResponse  // Auto-injected from request
{
    return response()->json($dto);
}
```

### Potential Symfony Bundle Features

| Feature | Current (Manual) | With Bundle |
|---------|------------------|-------------|
| Collection factory | Kernel listener | Auto-configured |
| Request → DTO | Manual JSON decode | ParamConverter |
| Console command | `bin/dto generate` | `bin/console dto:generate` |
| Autowiring | Manual | Service definitions |

```php
// Hypothetical Symfony bundle usage
#[Route('/users', methods: ['POST'])]
public function store(#[MapRequestPayload] UserDto $dto): JsonResponse
{
    return $this->json($dto);
}
```

## Comparison With Other Libraries

| Library | Framework Integration |
|---------|----------------------|
| **php-collective/dto** | Framework-agnostic, optional adapters |
| spatie/laravel-data | Laravel-only, deep integration |
| cuyz/valinor | Framework-agnostic + optional Symfony bundle |
| symfony/serializer | Symfony-native, usable standalone |

### Philosophy

This library follows the approach of [cuyz/valinor](https://github.com/CuyZ/Valinor) and similar tools:

1. **Core library** - Works everywhere, no dependencies
2. **Optional adapters** - Framework-specific convenience packages
3. **No lock-in** - Switch frameworks without changing DTOs

## Input Transformation

Unlike spatie/laravel-data which has built-in [Casts](https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/casts) and [Transformers](https://spatie.be/docs/laravel-data/v4/as-a-resource/transformers), this library keeps transformation simple:

### Recommended Patterns

**Pre-process data before DTO creation:**

```php
// Sanitize input
$data = $request->validated();
$data['name'] = trim($data['name'] ?? '');
$data['email'] = strtolower($data['email'] ?? '');

$dto = new UserDto($data);
```

**Use a static factory method:**

```php
class UserDto extends AbstractDto
{
    public static function fromRequest(Request $request): static
    {
        $data = $request->validated();

        // Transform as needed
        $data['name'] = trim($data['name'] ?? '');

        return new static($data);
    }
}
```

**Use the `factory` config option for objects:**

```php
// config/dto.php
'Payment' => [
    'fields' => [
        'amount' => [
            'type' => '\Money\Money',
            'factory' => 'Money\Parser::parse',
        ],
    ],
],
```

### Why No Built-in Transformers?

1. **Performance** - Runtime callbacks slow down the optimized `setFromArrayFast` path
2. **Simplicity** - Code generation produces simple, debuggable code
3. **Flexibility** - Pre-processing works with any transformation library
4. **Framework choice** - Use Laravel's mutators, Symfony's normalizers, or plain PHP

## Service Container Integration

### Laravel

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(OrderDto::class, function ($app, $params) {
        return new OrderDto($params['data'] ?? []);
    });
}
```

### Symfony

```yaml
# config/services.yaml
services:
    App\Dto\OrderDto:
        autoconfigure: false
        autowire: false
```

DTOs are typically instantiated directly rather than through the container, as they're data holders, not services.

## Doctrine ORM Integration

Generated DTOs work well with Doctrine for read-optimized queries.

### Partial Select + DTO Hydration

The recommended approach: fetch only needed fields as arrays, then hydrate DTOs:

```php
use App\Dto\UserSummaryDto;

// Fetch partial data as array (fast - no entity hydration)
$rows = $entityManager->createQuery(
    'SELECT u.id, u.name, u.email
     FROM App\Entity\User u
     WHERE u.active = true'
)->getArrayResult();

// Hydrate generated DTOs
$users = array_map(fn($row) => new UserSummaryDto($row), $rows);

// All generated methods available
foreach ($users as $user) {
    echo $user->getName();
    echo $user->getEmail();
}
```

### Why Not SELECT NEW?

Doctrine's `SELECT NEW` passes **positional arguments**:
```php
SELECT NEW App\Dto\UserDto(u.id, u.name, u.email)
// Calls: new UserDto($id, $name, $email)
```

Generated DTOs expect an **array**:
```php
new UserDto(['id' => 1, 'name' => 'John', 'email' => 'john@example.com'])
```

Using `getArrayResult()` + DTO hydration gives the same performance benefits while working with generated DTOs.

However, if you prefer Doctrine's native `SELECT NEW` syntax, you can use **mapper classes**.

### Doctrine SELECT NEW with Mappers

Generate mapper classes alongside your DTOs:

```bash
vendor/bin/dto generate --mapper
```

This creates mapper classes that extend DTOs with positional constructors:

```
src/Dto/
├── UserSummaryDto.php           # Standard DTO (array constructor)
└── Mapper/
    └── UserSummaryDtoMapper.php # Mapper (positional constructor)
```

**Generated Mapper Structure:**

```php
namespace App\Dto\Mapper;

use App\Dto\UserSummaryDto;

class UserSummaryDtoMapper extends UserSummaryDto
{
    public function __construct(
        int $id,
        string $name,
        ?string $email
    ) {
        parent::__construct([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);
    }
}
```

**Usage with Doctrine:**

```php
use App\Dto\Mapper\UserSummaryDtoMapper;

// Use the mapper in SELECT NEW
$users = $entityManager->createQuery(
    'SELECT NEW App\Dto\Mapper\UserSummaryDtoMapper(u.id, u.name, u.email)
     FROM App\Entity\User u
     WHERE u.active = true'
)->getResult();

// Results are fully-functional DTOs
foreach ($users as $user) {
    echo $user->getName();    // All getter methods work
    echo $user->toArray();    // Serialization works
}

// Type hints work - mapper IS-A DTO
function processUser(UserSummaryDto $dto): void { /* ... */ }
processUser($users[0]);  // Works because UserSummaryDtoMapper extends UserSummaryDto
```

**Benefits of Mappers:**

| Feature | getArrayResult + DTO | SELECT NEW + Mapper |
|---------|---------------------|---------------------|
| Performance | ✅ Fast | ✅ Fast |
| Type safety | ✅ Full | ✅ Full |
| Code style | Manual mapping | Native DQL |
| Single query | ✅ Yes | ✅ Yes |
| IDE support | ✅ Full | ✅ Full |

Both approaches produce identical results. Choose based on preference:
- **getArrayResult**: More explicit, works with any DTO
- **SELECT NEW + Mapper**: Native DQL syntax, requires `--mapper` flag

### DTO Definition for Query Results

Define lightweight DTOs for specific queries:

```php
// config/dto.php
return Schema::create()
    ->dto(Dto::create('UserSummary')->fields(
        Field::int('id'),
        Field::string('name'),
        Field::string('email'),
    ))
    ->toArray();
```

### With Query Builder

```php
$qb = $entityManager->createQueryBuilder();
$rows = $qb->select('o.id', 'o.total', 'c.name AS customerName')
   ->from(Order::class, 'o')
   ->join('o.customer', 'c')
   ->where('o.status = :status')
   ->setParameter('status', 'completed')
   ->getQuery()
   ->getArrayResult();

$orders = array_map(fn($row) => new OrderSummaryDto($row), $rows);
```

### Repository Pattern

```php
class UserRepository extends ServiceEntityRepository
{
    /**
     * @return UserSummaryDto[]
     */
    public function findActiveSummaries(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.id', 'u.name', 'u.email')
            ->where('u.active = true')
            ->getQuery()
            ->getArrayResult();

        return array_map(fn($row) => new UserSummaryDto($row), $rows);
    }
}
```

### Entity to DTO Conversion

For converting full entities to DTOs:

```php
// Simple conversion
$user = $entityManager->find(User::class, 1);
$dto = new UserDto($user->toArray());

// Or with a static factory
class UserDto extends AbstractDto
{
    public static function fromEntity(User $user): static
    {
        return new static([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles()->map(fn ($r) => [
                'id' => $r->getId(),
                'name' => $r->getName(),
            ])->toArray(),
        ]);
    }
}
```

### Why Use DTOs with Doctrine?

| Approach | Use Case |
|----------|----------|
| **Entity directly** | Internal domain logic, persistence |
| **DTO via getArrayResult** | Simple queries, manual mapping |
| **DTO via SELECT NEW + Mapper** | Native DQL syntax, type-safe results |
| **DTO from Entity** | When you need the full entity first, then transform |

Benefits of DTOs for query results:
- **Performance**: Only hydrate needed fields, no proxy objects
- **API safety**: No lazy-loading surprises in JSON responses
- **Clear contracts**: DTOs define exactly what the API returns
- **Immutability**: Use immutable DTOs for read-only data

## Further Reading

- [Custom Collection Types](Examples.md#custom-collection-types)
- [Validation](Validation.md)
- [Migration Guide](Migration.md)
