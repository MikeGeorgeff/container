# Container

[![Build Status](https://github.com/MikeGeorgeff/container/actions/workflows/ci.yml/badge.svg)](https://github.com/MikeGeorgeff/container/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/georgeff/container)](https://packagist.org/packages/georgeff/container)

A lightweight dependency injection container implementing [PSR-11](https://www.php-fig.org/psr/psr-11/).

## Status

This package is considered **feature-complete** as of 2.0. It exists to strictly implement the PSR-11 contract with the minimal feature set needed to support it — bugfixes and PSR-11 contract-compliance changes remain in scope, but no new capabilities are planned.

Anything beyond bare resolution — tagging, decoration, overriding, conditional/fallback registration — is deliberately out of scope here. That's [`georgeff/kernel`](https://github.com/MikeGeorgeff/kernel)'s job: `Container` owns resolution-lifecycle mechanism (the hooks below), the kernel owns the DI policy built on top of it.

## Installation

```bash
composer require georgeff/container
```

## Usage

### Registering Definitions

Register a definition by providing an ID and a callable factory. The container instance is passed to the factory.

```php
use Georgeff\Container\Container;

$container = new Container();

$container->add('database', function (Container $container) {
    return new DatabaseConnection('localhost', 'mydb');
});
```

### Shared Definitions

Shared definitions are resolved once and the same instance is returned on subsequent calls.

```php
$container->addShared('database', function (Container $container) {
    return new DatabaseConnection('localhost', 'mydb');
});

// Or pass true as the third argument to add()
$container->add('database', function (Container $container) {
    return new DatabaseConnection('localhost', 'mydb');
}, true);
```

### Resolving Definitions

```php
$db = $container->get('database');
```

### Aliases

Aliases allow you to resolve a definition by an alternate name, useful for binding interfaces to implementations.

```php
$container->addShared(DatabaseConnection::class, function (Container $container) {
    return new DatabaseConnection('localhost', 'mydb');
});

$container->addAlias(DatabaseConnection::class, ConnectionInterface::class);

// Resolves the DatabaseConnection definition
$db = $container->get(ConnectionInterface::class);
```

### Resolution Hooks

Hooks allow you to observe or react to service resolution. Four hooks are available: global pre, service-specific pre, service-specific post, and global post. When multiple hooks are registered they fire in that order — global pre first, global post last.

Pre-resolution hooks fire only when a resolution is genuinely attempted — never on a cache hit for an already-resolved shared service. They still fire if the attempt subsequently fails (a thrown exception, or a circular dependency), since the attempt itself did happen; only the post-resolution hooks are skipped in that case.

**Global pre-resolution** — fires when a resolution is attempted; does not fire on cache hits:

```php
$container->onResolving(function (string $id): void {
    echo "Resolving: $id";
});
```

**Service-specific pre-resolution** — fires only when the given ID's resolution is attempted; does not fire on cache hits:

```php
$container->onResolvingId('database', function (string $id): void {
    echo "Resolving database";
});
```

**Service-specific post-resolution** — fires after the factory runs for the given ID; does not fire on cache hits:

```php
$container->afterResolvedId('database', function (string $id, mixed $instance): void {
    echo "Resolved database";
});
```

**Global post-resolution** — fires after the factory runs for any service; does not fire on cache hits:

```php
$container->afterResolved(function (string $id, mixed $instance): void {
    echo "Resolved: $id";
});
```

All hooks receive the canonical ID after alias resolution, not the alias used to call `get()`. Multiple hooks of the same type can be registered and all will fire in registration order.

### Checking for Definitions

```php
$container->has('database');    // true
$container->has('nonexistent'); // false
```

## Exceptions

- `DefinitionNotFoundException` — thrown when getting a definition that does not exist or aliasing a non-existing definition. Implements PSR-11 `NotFoundExceptionInterface`.
- `CircularDependencyException` — thrown when a circular dependency is detected during resolution. Implements PSR-11 `ContainerExceptionInterface`.
- `ContainerException` — thrown when an error occurs during definition resolution. Implements PSR-11 `ContainerExceptionInterface`.

## License

MIT
