# Design Decisions

## Readonly Properties (PHP 8.1+)

- Generate truly readonly DTO properties.

Low value for this library because:

1. Breaks current patterns - fromArray(), setFromArray(), touched tracking don't work with readonly
1. Already have immutable DTOs - with*() pattern works and is more flexible
1. Public properties - Forces public visibility (debatable if good, we don't think so)

Verdict: Nope. The current immutable DTO with with*() methods is more flexible for this use case.
