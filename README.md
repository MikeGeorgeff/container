# Container

A lightweight dependency injection container implementing [PSR-11](https://www.php-fig.org/psr/psr-11/).

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
