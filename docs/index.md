---
layout: home

hero:
  name: PHP DTO
  text: Code Generation for Data Transfer Objects
  tagline: Framework-agnostic DTOs with zero runtime overhead, perfect IDE support, and TypeScript generation.
  image:
    src: /logo.svg
    alt: PHP DTO
  actions:
    - theme: brand
      text: Get Started
      link: /guide/
    - theme: alt
      text: View on GitHub
      link: https://github.com/php-collective/dto

features:
  - icon: |
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
    title: 25-63x Faster
    details: Code generation at build time means zero runtime reflection. The only code-gen approach in PHP for DTOs.
  - icon: |
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
    title: Perfect IDE Support
    details: Generated methods give you full autocomplete, go-to-definition, and refactoring support in any IDE.
  - icon: |
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
    title: TypeScript Generation
    details: Generate TypeScript interfaces directly from your DTO configs. Keep frontend and backend types in sync.
  - icon: |
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>
    title: Reviewable Code
    details: Generated DTOs show up in pull requests. No hidden magic—review exactly what your DTOs look like.
  - icon: |
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
    title: JSON Schema Support
    details: Bidirectional JSON Schema—both import and export. Bootstrap DTOs from APIs or generate documentation.
  - icon: |
      <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h14a2 2 0 0 0 2-2V7.5L14.5 2H6a2 2 0 0 0-2 2v4"/><polyline points="14 2 14 8 20 8"/><path d="m3 15 2 2 4-4"/></svg>
    title: Multiple Config Formats
    details: Define DTOs in PHP, XML, YAML, or NEON. XML includes XSD validation with IDE autocomplete.
---

<style>
:root {
  --vp-home-hero-name-color: transparent;
  --vp-home-hero-name-background: -webkit-linear-gradient(120deg, #7c3aed 30%, #ec4899);
}
</style>

## Quick Example

Define your DTOs with the fluent PHP builder:

```php
// config/dto.php
use PhpCollective\Dto\Config\{Dto, Field, Schema};

return Schema::create()
    ->dto(Dto::create('User')->fields(
        Field::int('id')->required(),
        Field::string('name')->required(),
        Field::string('email')->required(),
        Field::dto('address', 'Address'),
    ))
    ->dto(Dto::create('Address')->fields(
        Field::string('street'),
        Field::string('city'),
        Field::string('country'),
    ))
    ->toArray();
```

Generate and use:

```bash
vendor/bin/dto generate
```

```php
$user = UserDto::createFromArray($apiResponse);

$user->getName();           // string
$user->getAddress();        // AddressDto|null
$user->getAddressOrFail();  // AddressDto (throws if null)
```

## Installation

```bash
composer require php-collective/dto
```

## Framework Integrations

<div class="integration-links">

| Framework | Package |
|-----------|---------|
| CakePHP | [dereuromark/cakephp-dto](https://github.com/dereuromark/cakephp-dto) |
| Laravel | [php-collective/laravel-dto](https://github.com/php-collective/laravel-dto) |
| Symfony | [php-collective/symfony-dto](https://github.com/php-collective/symfony-dto) |

</div>
