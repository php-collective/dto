# Troubleshooting

Common issues, error messages, and debugging tips.

## Exception Reference

### Runtime Exceptions (Using DTOs)

#### `InvalidArgumentException: Required fields missing: {fields}`

**Cause:** Creating a DTO without providing required field values.

```php
// This throws - 'email' is required
$user = new UserDto(['name' => 'John']);
```

**Fix:** Provide all required fields:

```php
$user = new UserDto(['name' => 'John', 'email' => 'john@example.com']);
```

---

#### `InvalidArgumentException: Missing field '{field}' in '{DtoClass}' for type '{type}'`

**Cause:** Passing an unknown field name when `ignoreMissing` is false.

```php
// 'unknownField' doesn't exist in UserDto
$user = new UserDto(['unknownField' => 'value']);
```

**Fix:** Either fix the field name or ignore missing fields:

```php
// Option 1: Use correct field name
$user = new UserDto(['name' => 'value']);

// Option 2: Ignore unknown fields
$user = new UserDto(['unknownField' => 'value'], ignoreMissing: true);
```

---

#### `RuntimeException: Field does not exist: {field}`

**Cause:** Using `get()`, `set()`, or `has()` with an invalid field name.

```php
$dto->get('nonExistentField');
$dto->set('invalidField', 'value');
```

**Fix:** Use a valid field name. Check available fields with:

```php
$validFields = $dto->fields();
```

---

#### `InvalidArgumentException: Invalid field lookup for type '{type}': '{field}' does not exist`

**Cause:** Using inflected key type (underscored/dashed) but the field mapping doesn't exist.

```php
$dto->toArray(Dto::TYPE_UNDERSCORED);  // Field not in keyMap
```

**Fix:** Ensure the DTO was generated with key mapping, or use default type:

```php
$dto->toArray();  // Use default camelCase
```

---

#### `InvalidArgumentException: Type of field '{field}' is '{actual}', expected '{expected}'`

**Cause:** Assigning a value with wrong type to an immutable DTO.

```php
$dto = new ImmutableDto(['count' => 'not-a-number']);  // Expected int
```

**Fix:** Provide correct types:

```php
$dto = new ImmutableDto(['count' => 42]);
```

---

#### `RuntimeException: Unknown field(s) '{fields}' in serialized data`

**Cause:** Unserializing data that contains fields not in the DTO.

```php
$serialized = '{"name":"John","deletedField":"value"}';
$dto = unserialize($serialized);  // 'deletedField' no longer exists
```

**Fix:** This typically happens after removing fields from a DTO. Clean up serialized data or recreate it.

---

#### `RuntimeException: Factory method '{method}' does not exist on class '{class}'`

**Cause:** The factory method specified in configuration doesn't exist.

```xml
<field name="date" type="\DateTime" factory="nonExistentMethod"/>
```

**Fix:** Ensure the factory method exists and is static:

```php
class DateTime {
    public static function createFromFormat(...) { ... }
}
```

---

#### `InvalidArgumentException: Expected UnitEnum instance`

**Cause:** Internal error when serializing a non-enum value marked as unit enum.

**Fix:** Ensure enum fields contain valid enum instances.

### Generation Exceptions

#### `InvalidArgumentException: DTO name missing, but required`

**Cause:** DTO definition without a name.

```xml
<dto>  <!-- Missing name attribute -->
    <field name="id" type="int"/>
</dto>
```

**Fix:**

```xml
<dto name="User">
    <field name="id" type="int"/>
</dto>
```

---

#### `InvalidArgumentException: Invalid DTO name '{name}'`

**Cause:** DTO name contains invalid characters or is a reserved word.

**Fix:** Use valid PHP class names (PascalCase, no special characters).

---

#### `InvalidArgumentException: Field attribute '{field}:type' missing`

**Cause:** Field defined without a type.

```xml
<field name="id"/>  <!-- Missing type -->
```

**Fix:**

```xml
<field name="id" type="int"/>
```

---

#### `InvalidArgumentException: Invalid field type '{type}'... expected a collection`

**Cause:** Using `collection="true"` without array type notation.

```xml
<field name="items" type="Item" collection="true"/>  <!-- Should be Item[] -->
```

**Fix:**

```xml
<field name="items" type="Item[]" collection="true" singular="item"/>
```

---

#### `InvalidArgumentException: Extended DTO is immutable / not immutable`

**Cause:** Mixing mutable and immutable inheritance.

```xml
<dto name="Base" immutable="true">...</dto>
<dto name="Child" extends="Base">...</dto>  <!-- Error: Child is mutable -->
```

**Fix:** Both parent and child must have same immutability:

```xml
<dto name="Base" immutable="true">...</dto>
<dto name="Child" extends="Base" immutable="true">...</dto>
```

---

#### `InvalidArgumentException: Invalid singular name '{name}'... already exists as field`

**Cause:** Singular name for collection conflicts with existing field.

```xml
<dto name="Order">
    <field name="item" type="string"/>
    <field name="items" type="Item[]" collection="true" singular="item"/>  <!-- Conflict! -->
</dto>
```

**Fix:** Use a different singular name:

```xml
<field name="items" type="Item[]" collection="true" singular="orderItem"/>
```

### Configuration File Exceptions

#### `RuntimeException: XML parsing failed`

**Cause:** Malformed XML syntax.

**Fix:** Validate XML syntax. Common issues:
- Unclosed tags
- Invalid characters
- Missing quotes around attributes

#### `InvalidArgumentException: Invalid YAML/NEON file`

**Cause:** Syntax error in YAML or NEON file.

**Fix:** Validate file syntax using online validators or IDE plugins.

## Common Pitfalls

### 1. Mutable Reference Sharing

```php
$owner = new OwnerDto(['name' => 'John']);
$car1 = new CarDto();
$car2 = new CarDto();

$car1->setOwner($owner);
$car2->setOwner($owner);

// Modifying owner affects BOTH cars!
$owner->setName('Jane');
echo $car1->getOwner()->getName();  // 'Jane'
echo $car2->getOwner()->getName();  // 'Jane'
```

**Fix:** Clone when needed:

```php
$car1->setOwner($owner->clone());
$car2->setOwner($owner->clone());
```

Or use immutable DTOs.

### 2. Key Map Direction Confusion

The `_keyMap` maps **inflected name → field name**, not the reverse:

```php
// CORRECT
'underscored' => [
    'first_name' => 'firstName',  // inflected => field
],

// WRONG
'underscored' => [
    'firstName' => 'first_name',  // This is backwards!
],
```

### 3. Forgetting ignoreMissing for Partial Data

```php
// API returns only some fields
$partialData = ['name' => 'John'];  // Missing 'email', 'age', etc.

// This throws if those fields don't exist in DTO
$dto = new UserDto($partialData);

// Use ignoreMissing for partial data
$dto = new UserDto($partialData, ignoreMissing: true);
```

### 4. Circular Reference in toArray()

Self-referencing DTOs can cause infinite loops:

```php
$parent = new CategoryDto(['name' => 'Electronics']);
$child = new CategoryDto(['name' => 'Phones', 'parent' => $parent]);
$parent->addChild($child);
$child->setParent($parent);

// This may cause infinite recursion!
$array = $parent->toArray();
```

**Fix:** Use `touchedToArray()` or manually handle the serialization.

### 5. Collection Factory Not Set Early Enough

```php
// WRONG: DTOs created before factory is set
$dto = new OrderDto($data);
Dto::setCollectionFactory(fn($items) => collect($items));
// Items are still ArrayObject, not Laravel Collection

// CORRECT: Set factory before any DTO instantiation
Dto::setCollectionFactory(fn($items) => collect($items));
$dto = new OrderDto($data);
// Now items are Laravel Collection
```

### 6. Type Coercion Assumptions

PHP's native type system handles some coercion, but not all:

```php
// Works: string "123" coerced to int
$dto = new UserDto(['id' => '123']);
echo $dto->getId();  // int 123

// Fails: "abc" cannot become int
$dto = new UserDto(['id' => 'abc']);
// TypeError
```

## Debugging

### Using __debugInfo()

```php
$dto = new UserDto(['name' => 'John']);
var_dump($dto);

// Output:
// object(UserDto)#1 {
//   ["data"] => ["name" => "John", "email" => null, ...]
//   ["touched"] => ["name"]
//   ["extends"] => "PhpCollective\Dto\Dto\AbstractDto"
//   ["immutable"] => false
// }
```

### Inspecting Touched Fields

```php
$dto = new UserDto();
$dto->setName('John');

// See which fields were explicitly set
$touched = $dto->touchedFields();
// ['name']

// Get only touched values
$changes = $dto->touchedToArray();
// ['name' => 'John']
```

### Checking Available Fields

```php
$dto = new UserDto();

// List all fields
$fields = $dto->fields();
// ['id', 'name', 'email', ...]

// Check if field exists
if (in_array('email', $dto->fields())) {
    // Field exists
}
```

### JSON Output for Debugging

```php
$dto = new UserDto($data);

// Pretty-printed JSON
echo json_encode($dto->toArray(), JSON_PRETTY_PRINT);
```

### Dry Run Generation

```bash
# See what would be generated without writing files
vendor/bin/dto generate --dry-run --verbose
```

## Getting Help

If you encounter issues not covered here:

1. Check the [GitHub Issues](https://github.com/php-collective/dto/issues)
2. Search existing issues for similar problems
3. Open a new issue with:
   - PHP version
   - Library version
   - Minimal reproduction code
   - Full error message and stack trace
