# Design Decisions

## Validation

The library provides minimal built-in validation:
- Required Fields
- Type Checking

For more complex validation, you can use a service like [Respect/Validation](https://respect-validation.readthedocs.io/en/latest/).

We dont want to bloat the library with complex validation rules that are better handled by dedicated libraries.

### Best Practices

1. **Keep DTOs simple** - DTOs are data containers, not business logic holders. Consider using a separate validation service for complex rules.

2. **Fail fast** - Use `required` for fields that must always be present. This catches errors at construction time.

3. **Use type hints** - PHP's type system already validates types. A `string` field won't accept an integer.

4. **Validate at boundaries** - Validate input at API/form boundaries, not deep in business logic.

5. **Don't over-validate** - Internal DTOs used between trusted services don't need the same validation as user input DTOs.

6. **Test validation** - Write unit tests for your validation rules.


## Custom Casters / Transformers

Current features are sufficient:
```php
// Input transformation
->factory('fromArray')           // Calls ClassName::fromArray($value)
->factory('fromString')          // Calls ClassName::fromString($value)
->factory('External::create')    // Calls External::create($value)

// Output transformation  
->serialize('array')             // Calls $obj->toArray()
->serialize('string')            // Calls $obj->__toString()
->serialize('FromArrayToArray')  // Full round-trip
```
Plus auto-detection for:
- FromArrayToArrayInterface
- JsonSerializable
- Enums (backed/unit)

Additional casters add complexity without real benefit.

## Readonly Properties (PHP 8.1+)

- Generate truly readonly DTO properties.

Low value for this library because:

1. Breaks current patterns - fromArray(), setFromArray(), touched tracking don't work with readonly
1. Already have immutable DTOs - with*() pattern works and is more flexible
1. Public properties - Forces public visibility (debatable if good, we don't think so)

Verdict: Nope. The current immutable DTO with with*() methods is more flexible for this use case.

### Best Practices
1. Use immutable DTOs for readonly behavior.
2. Avoid mixing mutable and immutable DTOs.

## Additional Serialization Formats (XML, CSV)
Not at this point.

Reason: Out of scope. Use symfony/serializer for complex serialization needs.

## Advanced Type Constraints (positive-int, non-empty-string)
Not at this point.

Reason: Validation concern, not type concern. Use validation rules instead.


## Computed Properties
No need on the generated part.

**Reason**: Use traits instead.
```php
// config
Dto::create('User')->traits(\App\Traits\UserComputedTrait::class)->fields(...)

// trait
trait UserComputedTrait {
    public function getFullName(): string {
        return $this->firstName . ' ' . $this->lastName;
    }
}
```

## Lifecycle Hooks
Not needed.

**Reason**: Use traits instead.
```php
class UserDto extends AbstractUserDto {
    protected function setFromArray(...): static {
        // pre-processing
        parent::setFromArray(...);
        // post-processing
        return $this;
    }
}
```
