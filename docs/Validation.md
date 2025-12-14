# Validation

This document explains what validation php-collective/dto provides and how to integrate with validation libraries for more complex rules.

## Built-in Validation

The library provides **required field validation** only. When a field is marked as `required`, the DTO will throw an exception if that field is missing or null during instantiation.

```xml
<dto name="User">
    <field name="id" type="int" required="true"/>
    <field name="email" type="string" required="true"/>
    <field name="nickname" type="string"/>  <!-- optional -->
</dto>
```

```php
// This throws RuntimeException: Required field 'email' is missing
$user = new UserDto(['id' => 1]);

// This works - nickname is optional
$user = new UserDto(['id' => 1, 'email' => 'test@example.com']);
```

## What's NOT Included

The library intentionally does **not** include:

- Min/max length validation
- Numeric range validation (min, max)
- Pattern/regex validation
- Email/URL format validation
- Custom validation rules
- Validation error messages

**Why?** The library focuses on **data structure and transfer**, not business logic validation. This keeps the generated code lean and allows you to choose your preferred validation approach.

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
| Min/max length | ❌ | ✅ |
| Numeric ranges | ❌ | ✅ |
| Regex patterns | ❌ | ✅ |
| Email/URL format | ❌ | ✅ |
| Custom rules | ❌ | ✅ |
| Error messages | Basic | Rich |

**Recommendation:** Use php-collective/dto for structure and a validation library for business rules. This separation of concerns keeps each tool focused on what it does best.
