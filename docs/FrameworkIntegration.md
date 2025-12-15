# Framework Integration

This library is **framework-agnostic** by design. It works with any PHP framework without requiring wrapper packages, while still offering deep integration possibilities.

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

## Further Reading

- [Custom Collection Types](Examples.md#custom-collection-types)
- [Validation](Validation.md)
- [Migration Guide](Migration.md)
