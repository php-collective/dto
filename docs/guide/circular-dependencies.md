---
title: Circular Dependencies
---

# Circular Dependencies

The generator analyzes DTO relationships before rendering classes and throws early when it finds an eager dependency cycle.

This is a generation-time check, not a runtime hydration check.

## When Cycle Detection Runs

Cycle detection happens during definition building, before DTO files are written.

That means a cycle fails generation even if you have not instantiated the DTOs yet.

## What Counts as a Dependency

The dependency analyzer currently looks at:

- DTO field types such as `User`
- array and collection element types such as `User[]`
- nullable and namespaced types such as `?User[]` or `\App\Dto\UserDto`
- union and intersection members such as `Foo|Bar`, `Foo&Bar`, and parenthesized DNF shapes
- collection `singularType`
- explicit `dto` field metadata
- DTO inheritance via `extends`

## What Breaks a Cycle

Lazy fields are excluded from the dependency graph:

```php
Dto::create('User')->fields(
    Field::dto('manager', 'User')->asLazy(),
)
```

This lets you model recursive graphs without blocking generation.

### Important

Nullable fields alone do **not** currently remove a dependency from the analyzer. If you need to break a generation-time cycle, use `lazy`.

## Basic Example

### Eager Cycle That Fails

```php
return Schema::create()
    ->dto(Dto::create('User')->fields(
        Field::dto('team', 'Team'),
    ))
    ->dto(Dto::create('Team')->fields(
        Field::dto('owner', 'User'),
    ))
    ->toArray();
```

Generation fails because `User -> Team -> User` is an eager cycle.

### Lazy Edge That Passes

```php
return Schema::create()
    ->dto(Dto::create('User')->fields(
        Field::dto('team', 'Team')->asLazy(),
    ))
    ->dto(Dto::create('Team')->fields(
        Field::dto('owner', 'User'),
    ))
    ->toArray();
```

This works because the lazy `team` field is skipped during cycle analysis.

## Collections and Advanced Types

Cycle detection also applies to:

- collections using `singularType`
- unions such as `User|Team`
- intersections such as `Foo&Bar`
- parenthesized DNF shapes such as `(Foo|Bar)&Baz`

If any eager branch introduces a cycle, generation fails. Making the field lazy skips the whole field from the analyzer.

## Self-References

Direct self-references do not count as a cycle in the analyzer:

```php
Dto::create('Category')->fields(
    Field::dto('parent', 'Category'),
)
```

This is allowed by the generator. In practice, recursive structures are still usually better modeled as lazy fields when large subtrees are involved.

## Inheritance

`extends` relationships are part of the dependency graph. A DTO that extends another DTO depends on that parent during analysis.

## Troubleshooting

When generation fails, the exception message shows the discovered cycle path.

Typical fixes:

1. Mark one edge in the cycle as lazy.
2. Reconsider whether two DTOs need to reference each other directly.
3. Move one side of the relationship to an identifier field instead of a nested DTO.

## Related Guides

- [Config Builder](./config-builder) for `asLazy()`
- [Performance](./performance) for lazy hydration tradeoffs
- [Troubleshooting](./troubleshooting) for generator error messages
