# Changelog

All notable changes to `georgeff/container` are documented here.

---

## [1.1.0] — 2026-06-19

### Added
- `onResolving(callable $callback): void` — registers a global pre-resolution hook; fires on every `get()` call (including cache hits for shared services) with the canonical service ID after alias resolution; callable signature: `(string $id): void`
- `onResolvingId(string $id, callable $callback): void` — registers a service-specific pre-resolution hook; fires only when the given ID is resolved; same signature as `onResolving`
- `afterResolved(callable $callback): void` — registers a global post-resolution hook; fires after the factory runs for any service; does not fire on cache hits; callable signature: `(string $id, mixed $instance): void`
- `afterResolvedId(string $id, callable $callback): void` — registers a service-specific post-resolution hook; fires only after the factory runs for the given ID; does not fire on cache hits; same signature as `afterResolved`
- Multiple hooks of the same type can be registered and fire in registration order; firing order across types: global pre → service-specific pre → factory → service-specific post → global post

---

## [1.0.0] — 2026-02-07

### Added
- `Container` implementing PSR-11 `ContainerInterface`
- `add(string $id, callable $factory, bool $shared = false): void` — registers a definition
- `addShared(string $id, callable $factory): void` — shorthand for registering a shared definition
- `addAlias(string $id, string $alias): void` — registers an alias for an existing definition; resolving the alias resolves the original definition
- `isShared(string $id): bool` — returns whether a definition (or alias) is shared
- `DefinitionNotFoundException` — thrown when resolving or aliasing a non-existing ID; implements PSR-11 `NotFoundExceptionInterface`
- `ContainerException` — thrown when a factory throws during resolution; wraps the original exception as previous; implements PSR-11 `ContainerExceptionInterface`
- `CircularDependencyException` — thrown when a circular dependency is detected during resolution; implements PSR-11 `ContainerExceptionInterface`
