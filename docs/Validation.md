# Validation

This document explains what validation php-collective/dto provides and how to integrate with validation libraries for more complex rules.

## Built-in Validation

### Required Fields

When a field is marked as `required`, the DTO will throw an exception if that field is missing or null during instantiation.

```xml
<dto name="User">
    <field name="id" type="int" required="true"/>
    <field name="email" type="string" required="true"/>
    <field name="nickname" type="string"/>  <!-- optional -->
</dto>
```

```php
// This throws InvalidArgumentException: Required fields missing: email
$user = new UserDto(['id' => 1]);

// This works - nickname is optional
$user = new UserDto(['id' => 1, 'email' => 'test@example.com']);
```

### Validation Rules

Fields support built-in validation rules for common constraints:

```php
use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\Field;
use PhpCollective\Dto\Config\Schema;

return Schema::create()
    ->dto(Dto::create('User')->fields(
        Field::string('name')->required()->minLength(2)->maxLength(100),
        Field::string('email')->required()->pattern('/^[^@]+@[^@]+\.[^@]+$/'),
        Field::int('age')->min(0)->max(150),
        Field::float('score')->min(0.0)->max(100.0),
    ))
    ->toArray();
```

Or in XML:

```xml
<dto name="User">
    <field name="name" type="string" required="true" minLength="2" maxLength="100"/>
    <field name="email" type="string" required="true" pattern="/^[^@]+@[^@]+\.[^@]+$/"/>
    <field name="age" type="int" min="0" max="150"/>
    <field name="score" type="float" min="0" max="100"/>
</dto>
```

#### Available Rules

| Rule | Applies To | Description |
|------|-----------|-------------|
| `minLength` | string | Minimum string length (via `mb_strlen`) |
| `maxLength` | string | Maximum string length (via `mb_strlen`) |
| `min` | int, float | Minimum numeric value (inclusive) |
| `max` | int, float | Maximum numeric value (inclusive) |
| `pattern` | string | Regex pattern that must match (via `preg_match`) |

#### Behavior

- Null fields skip validation — rules are only checked when a value is present
- Required check runs before validation rules
- On failure, an `InvalidArgumentException` is thrown with a descriptive message:

```php
// InvalidArgumentException: Validation failed: name must be at least 2 characters
$user = new UserDto(['name' => 'A', 'email' => 'a@b.com']);

// InvalidArgumentException: Validation failed: email must match pattern /^[^@]+@[^@]+\.[^@]+$/
$user = new UserDto(['name' => 'Test', 'email' => 'invalid']);
```

## Integrating with Validation Libraries

### Symfony Validator

```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

// Create your DTO
$userDto = new UserDto($formData);

// Define constraints separately
$constraints = new Assert\Collection([
    'email' => [new Assert\NotBlank(), new Assert\Email()],
    'age' => [new Assert\Range(['min' => 18, 'max' => 120])],
    'name' => [new Assert\Length(['min' => 2, 'max' => 100])],
]);

// Validate
$validator = Validation::createValidator();
$violations = $validator->validate($userDto->toArray(), $constraints);

if (count($violations) > 0) {
    foreach ($violations as $violation) {
        echo $violation->getPropertyPath() . ': ' . $violation->getMessage();
    }
}
```

### Laravel Validation (Standalone)

```php
use Illuminate\Validation\Factory;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;

$loader = new ArrayLoader();
$translator = new Translator($loader, 'en');
$factory = new Factory($translator);

$validator = $factory->make($userDto->toArray(), [
    'email' => 'required|email',
    'age' => 'required|integer|min:18|max:120',
    'name' => 'required|string|min:2|max:100',
]);

if ($validator->fails()) {
    $errors = $validator->errors()->all();
}
```

### Respect/Validation

```php
use Respect\Validation\Validator as v;

$userValidator = v::key('email', v::email())
    ->key('age', v::intVal()->between(18, 120))
    ->key('name', v::stringType()->length(2, 100));

try {
    $userValidator->assert($userDto->toArray());
} catch (\Respect\Validation\Exceptions\NestedValidationException $e) {
    $errors = $e->getMessages();
}
```

### Webmozart Assert (Simple Assertions)

For simple assertions without full validation framework:

```php
use Webmozart\Assert\Assert;

$data = $userDto->toArray();

Assert::email($data['email'], 'Invalid email format');
Assert::range($data['age'], 18, 120, 'Age must be between 18 and 120');
Assert::lengthBetween($data['name'], 2, 100, 'Name must be 2-100 characters');
```

## Validation in Controllers

A common pattern is to validate before creating the DTO:

```php
class UserController
{
    public function create(Request $request): Response
    {
        // 1. Validate raw input first
        $validated = $this->validate($request->all(), [
            'email' => 'required|email',
            'name' => 'required|string|max:100',
        ]);

        // 2. Create DTO from validated data
        $userDto = new UserDto($validated);

        // 3. Process the DTO
        $this->userService->create($userDto);

        return new Response('Created');
    }
}
```

Or validate the DTO after creation:

```php
class UserController
{
    public function create(Request $request): Response
    {
        // 1. Create DTO (handles type coercion, required fields)
        $userDto = new UserDto($request->all());

        // 2. Validate business rules
        $this->validator->validate($userDto->toArray(), $this->getUserRules());

        // 3. Process
        $this->userService->create($userDto);

        return new Response('Created');
    }
}
```

## Type Coercion vs Validation

The library performs **type coercion**, not validation:

```php
// String "123" is coerced to int 123
$dto = new UserDto(['id' => '123', 'email' => 'test@example.com']);
echo $dto->getId(); // int 123

// But invalid types throw TypeError
$dto = new UserDto(['id' => 'not-a-number', 'email' => 'test@example.com']);
// TypeError: Cannot assign string to property UserDto::$id of type int
```

This is PHP's native type system at work, not library validation.

## Summary

| Feature | php-collective/dto | Validation Library |
|---------|:------------------:|:------------------:|
| Required fields | ✅ | ✅ |
| Type checking | ✅ (PHP native) | ✅ |
| Min/max length | ✅ | ✅ |
| Numeric ranges | ✅ | ✅ |
| Regex patterns | ✅ | ✅ |
| Email/URL format | ✅ (via pattern) | ✅ |
| Custom rules | ❌ | ✅ |
| Error messages | Basic | Rich |

**Recommendation:** Use the built-in validation rules for simple structural constraints. For complex business logic validation (conditional rules, cross-field dependencies, custom messages), use a dedicated validation library alongside your DTOs.

## Extracting Rules for Framework Validation

The `validationRules()` method returns a framework-agnostic array of validation rules from the DTO metadata. This is useful for bridging DTO rules to framework-native validators:

```php
$dto = new UserDto();
$rules = $dto->validationRules();
// [
//     'name' => ['required' => true, 'minLength' => 2, 'maxLength' => 50],
//     'email' => ['pattern' => '/^[^@]+@[^@]+\.[^@]+$/'],
//     'age' => ['min' => 0, 'max' => 150],
// ]
```

Only fields with at least one active rule are included. The returned keys match the config rule names: `required`, `minLength`, `maxLength`, `min`, `max`, `pattern`.

The framework integration plugins provide ready-made bridges:

- **CakePHP:** `DtoValidator::fromDto($dto)` → `Cake\Validation\Validator`
- **Laravel:** `DtoValidationRules::fromDto($dto)` → Laravel rule arrays
- **Symfony:** `DtoConstraintBuilder::fromDto($dto)` → `Symfony\Component\Validator\Constraints\Collection`
